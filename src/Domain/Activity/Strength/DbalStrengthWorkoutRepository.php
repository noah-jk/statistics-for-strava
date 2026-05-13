<?php

declare(strict_types=1);

namespace App\Domain\Activity\Strength;

use App\Domain\Activity\ActivityId;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\ValueObject\Measurement\Mass\Pound;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class DbalStrengthWorkoutRepository extends DbalRepository implements StrengthWorkoutRepository
{
    public function saveForActivity(ActivityId $activityId, StrengthWorkoutExercises $exercises): void
    {
        $this->deleteForActivity($activityId);

        $sql = 'INSERT INTO ActivityStrengthSet (
            activityId, position, exerciseName, numberOfSets, numberOfReps, weightLbs, estimatedOneRepMax
        ) VALUES (
            :activityId, :position, :exerciseName, :numberOfSets, :numberOfReps, :weightLbs, :estimatedOneRepMax
        )';

        $position = 1;
        foreach ($exercises as $set) {
            /** @var ExerciseSet $set */
            $weightLbs = $set->getWeightLbs()?->toFloat();
            $estimatedOneRepMax = null !== $weightLbs
                ? $weightLbs * (1 + $set->getNumberOfReps() / 30.0)
                : null;

            $this->connection->executeStatement($sql, [
                'activityId' => $activityId,
                'position' => $position++,
                'exerciseName' => (string) $set->getExerciseName(),
                'numberOfSets' => $set->getNumberOfSets(),
                'numberOfReps' => $set->getNumberOfReps(),
                'weightLbs' => $weightLbs,
                'estimatedOneRepMax' => $estimatedOneRepMax,
            ]);
        }
    }

    public function findByActivityId(ActivityId $activityId): StrengthWorkoutExercises
    {
        $results = $this->connection->executeQuery(
            'SELECT * FROM ActivityStrengthSet WHERE activityId = :activityId ORDER BY position ASC',
            ['activityId' => $activityId],
        )->fetchAllAssociative();

        return StrengthWorkoutExercises::fromArray(array_map($this->hydrate(...), $results));
    }

    public function isImportedForActivity(ActivityId $activityId): bool
    {
        return $this->connection->executeQuery(
            'SELECT COUNT(*) FROM ActivityStrengthSet WHERE activityId = :activityId',
            ['activityId' => $activityId],
        )->fetchOne() > 0;
    }

    public function deleteForActivity(ActivityId $activityId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM ActivityStrengthSet WHERE activityId = :activityId',
            ['activityId' => $activityId],
        );
    }

    public function findUnprocessedWeightTrainingActivityIds(): array
    {
        $rows = $this->connection->executeQuery(
            "SELECT activityId FROM Activity
             WHERE sportType = 'WeightTraining'
               AND activityId NOT IN (SELECT DISTINCT activityId FROM ActivityStrengthSet)",
        )->fetchFirstColumn();

        return array_map(ActivityId::fromString(...), $rows);
    }

    public function findDailyBestByExercise(SerializableDateTime $since): array
    {
        $rows = $this->connection->executeQuery(
            'SELECT date(a.startDateTime) AS activityDate, s.exerciseName, MAX(s.estimatedOneRepMax) AS best1RM
             FROM ActivityStrengthSet s
             JOIN Activity a ON s.activityId = a.activityId
             WHERE a.startDateTime >= :since AND s.estimatedOneRepMax IS NOT NULL
             GROUP BY activityDate, s.exerciseName
             ORDER BY activityDate ASC, s.exerciseName ASC',
            ['since' => $since->format('Y-m-d H:i:s')],
        )->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['exerciseName']][] = [
                'date' => $row['activityDate'],
                'oneRepMax' => (float) $row['best1RM'],
            ];
        }

        return $result;
    }

    public function findWeeklyRollingTotal(SerializableDateTime $since): array
    {
        $rows = $this->connection->executeQuery(
            "SELECT strftime('%Y-W%W', a.startDateTime) AS week,
                    MAX(CASE WHEN s.exerciseName = 'Squat' THEN s.estimatedOneRepMax END) AS squat,
                    MAX(CASE WHEN s.exerciseName = 'Bench Press' THEN s.estimatedOneRepMax END) AS bench,
                    MAX(CASE WHEN s.exerciseName = 'Deadlift' THEN s.estimatedOneRepMax END) AS deadlift
             FROM ActivityStrengthSet s
             JOIN Activity a ON s.activityId = a.activityId
             WHERE a.startDateTime >= :since AND s.estimatedOneRepMax IS NOT NULL
             GROUP BY week
             ORDER BY week ASC",
            ['since' => $since->format('Y-m-d H:i:s')],
        )->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            if (null === $row['squat']) {
                continue;
            }
            if (null === $row['bench']) {
                continue;
            }
            if (null === $row['deadlift']) {
                continue;
            }
            $result[] = [
                'week' => (string) $row['week'],
                'total' => (float) $row['squat'] + (float) $row['bench'] + (float) $row['deadlift'],
            ];
        }

        return $result;
    }

    public function findAllTimePRPerExercise(): array
    {
        $rows = $this->connection->executeQuery(
            'SELECT exerciseName, MAX(estimatedOneRepMax) AS pr
             FROM ActivityStrengthSet
             WHERE estimatedOneRepMax IS NOT NULL
             GROUP BY exerciseName
             ORDER BY pr DESC',
        )->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['exerciseName']] = (float) $row['pr'];
        }

        return $result;
    }

    public function findAllTimeDailyBestByExercise(array $exercises): array
    {
        if ([] === $exercises) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($exercises), '?'));
        $rows = $this->connection->executeQuery(
            "SELECT date(a.startDateTime) AS activityDate, s.exerciseName, MAX(s.estimatedOneRepMax) AS best1RM
             FROM ActivityStrengthSet s
             JOIN Activity a ON s.activityId = a.activityId
             WHERE s.exerciseName IN ({$placeholders}) AND s.estimatedOneRepMax IS NOT NULL
             GROUP BY activityDate, s.exerciseName
             ORDER BY activityDate ASC, s.exerciseName ASC",
            $exercises,
        )->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['exerciseName']][] = [
                'date' => $row['activityDate'],
                'value' => (float) $row['best1RM'],
            ];
        }

        return $result;
    }

    public function findAllTimeWeeklyVolumeByExercise(array $exercises): array
    {
        if ([] === $exercises) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($exercises), '?'));
        $rows = $this->connection->executeQuery(
            "SELECT strftime('%Y-W%W', a.startDateTime) AS week, s.exerciseName,
                    SUM(s.numberOfSets * s.numberOfReps * COALESCE(s.weightLbs, 0)) AS volume
             FROM ActivityStrengthSet s
             JOIN Activity a ON s.activityId = a.activityId
             WHERE s.exerciseName IN ({$placeholders})
             GROUP BY week, s.exerciseName
             ORDER BY week ASC, s.exerciseName ASC",
            $exercises,
        )->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['exerciseName']][] = [
                'week' => (string) $row['week'],
                'volume' => (float) $row['volume'],
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrate(array $result): ExerciseSet
    {
        return ExerciseSet::create(
            exerciseName: ExerciseName::fromString($result['exerciseName']),
            numberOfSets: (int) $result['numberOfSets'],
            numberOfReps: (int) $result['numberOfReps'],
            weightLbs: null !== $result['weightLbs'] ? Pound::from((float) $result['weightLbs']) : null,
        );
    }
}
