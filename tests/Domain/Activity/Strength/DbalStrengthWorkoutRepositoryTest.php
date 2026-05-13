<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Strength;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Strength\DbalStrengthWorkoutRepository;
use App\Domain\Activity\Strength\ExerciseName;
use App\Domain\Activity\Strength\ExerciseSet;
use App\Domain\Activity\Strength\StrengthWorkoutExercises;
use App\Domain\Activity\Strength\StrengthWorkoutRepository;
use App\Infrastructure\ValueObject\Measurement\Mass\Pound;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;

class DbalStrengthWorkoutRepositoryTest extends ContainerTestCase
{
    private StrengthWorkoutRepository $strengthWorkoutRepository;

    public function testSaveForActivityAndFindByActivityId(): void
    {
        $activityId = ActivityId::fromUnprefixed('test');
        $exercises = StrengthWorkoutExercises::fromArray([
            ExerciseSet::create(ExerciseName::fromString('Squat'), 3, 5, Pound::from(315.0)),
            ExerciseSet::create(ExerciseName::fromString('Pull Up'), 3, 10),
        ]);

        $this->strengthWorkoutRepository->saveForActivity($activityId, $exercises);

        $found = $this->strengthWorkoutRepository->findByActivityId($activityId);
        $this->assertCount(2, $found);

        $items = $found->toArray();
        $this->assertSame('Squat', (string) $items[0]->getExerciseName());
        $this->assertSame(3, $items[0]->getNumberOfSets());
        $this->assertSame(5, $items[0]->getNumberOfReps());
        $this->assertSame(315.0, $items[0]->getWeightLbs()->toFloat());

        $this->assertSame('Pull Up', (string) $items[1]->getExerciseName());
        $this->assertTrue($items[1]->isBodyweight());
    }

    public function testEstimatedOneRepMaxIsComputedOnSave(): void
    {
        $activityId = ActivityId::fromUnprefixed('test');
        $exercises = StrengthWorkoutExercises::fromArray([
            ExerciseSet::create(ExerciseName::fromString('Deadlift'), 1, 5, Pound::from(300.0)),
        ]);

        $this->strengthWorkoutRepository->saveForActivity($activityId, $exercises);

        $row = $this->getConnection()
            ->executeQuery('SELECT estimatedOneRepMax FROM ActivityStrengthSet WHERE activityId = :id ORDER BY position ASC', ['id' => $activityId])
            ->fetchOne();

        // Epley: 300 * (1 + 5/30) = 300 * 1.1667 = 350.0
        $this->assertEqualsWithDelta(300.0 * (1 + 5 / 30.0), (float) $row, 0.001);
    }

    public function testBodyweightSetHasNullOneRepMax(): void
    {
        $activityId = ActivityId::fromUnprefixed('test');
        $exercises = StrengthWorkoutExercises::fromArray([
            ExerciseSet::create(ExerciseName::fromString('Dip'), 3, 12),
        ]);

        $this->strengthWorkoutRepository->saveForActivity($activityId, $exercises);

        $row = $this->getConnection()
            ->executeQuery('SELECT estimatedOneRepMax FROM ActivityStrengthSet WHERE activityId = :id ORDER BY position ASC', ['id' => $activityId])
            ->fetchOne();

        $this->assertNull($row ?: null);
    }

    public function testIsImportedForActivity(): void
    {
        $activityId = ActivityId::fromUnprefixed('test');
        $exercises = StrengthWorkoutExercises::fromArray([
            ExerciseSet::create(ExerciseName::fromString('Bench Press'), 4, 8, Pound::from(185.0)),
        ]);

        $this->assertFalse($this->strengthWorkoutRepository->isImportedForActivity($activityId));

        $this->strengthWorkoutRepository->saveForActivity($activityId, $exercises);

        $this->assertTrue($this->strengthWorkoutRepository->isImportedForActivity($activityId));
        $this->assertFalse($this->strengthWorkoutRepository->isImportedForActivity(ActivityId::fromUnprefixed('other')));
    }

    public function testDeleteForActivity(): void
    {
        $activityIdOne = ActivityId::fromUnprefixed('test1');
        $activityIdTwo = ActivityId::fromUnprefixed('test2');
        $exercises = StrengthWorkoutExercises::fromArray([
            ExerciseSet::create(ExerciseName::fromString('Squat'), 3, 5, Pound::from(225.0)),
        ]);

        $this->strengthWorkoutRepository->saveForActivity($activityIdOne, $exercises);
        $this->strengthWorkoutRepository->saveForActivity($activityIdTwo, $exercises);

        $this->strengthWorkoutRepository->deleteForActivity($activityIdOne);

        $this->assertSame(
            1,
            (int) $this->getConnection()->executeQuery('SELECT COUNT(*) FROM ActivityStrengthSet')->fetchOne()
        );
        $this->assertFalse($this->strengthWorkoutRepository->isImportedForActivity($activityIdOne));
        $this->assertTrue($this->strengthWorkoutRepository->isImportedForActivity($activityIdTwo));
    }

    public function testFindAllTimePRPerExercise(): void
    {
        $activityId1 = ActivityId::fromUnprefixed('pr1');
        $activityId2 = ActivityId::fromUnprefixed('pr2');

        $this->strengthWorkoutRepository->saveForActivity($activityId1, StrengthWorkoutExercises::fromArray([
            ExerciseSet::create(ExerciseName::fromString('Squat'), 1, 5, Pound::from(315.0)),
            ExerciseSet::create(ExerciseName::fromString('Bench Press'), 1, 5, Pound::from(185.0)),
        ]));
        $this->strengthWorkoutRepository->saveForActivity($activityId2, StrengthWorkoutExercises::fromArray([
            ExerciseSet::create(ExerciseName::fromString('Squat'), 1, 5, Pound::from(275.0)),
            ExerciseSet::create(ExerciseName::fromString('Deadlift'), 1, 5, Pound::from(405.0)),
        ]));

        $prs = $this->strengthWorkoutRepository->findAllTimePRPerExercise();

        $this->assertArrayHasKey('Deadlift', $prs);
        $this->assertArrayHasKey('Squat', $prs);
        $this->assertArrayHasKey('Bench Press', $prs);
        // Deadlift has highest 1RM and should come first (ORDER BY pr DESC)
        $this->assertSame(array_key_first($prs), 'Deadlift');
        // Squat: Epley 315*(1+5/30) = 367.5
        $this->assertEqualsWithDelta(315.0 * (1 + 5 / 30.0), $prs['Squat'], 0.001);
    }

    public function testFindDailyBestByExercise(): void
    {
        $activityId1 = ActivityId::fromUnprefixed('daily-act-1');
        $activityId2 = ActivityId::fromUnprefixed('daily-act-2');
        $this->insertActivity($activityId1, '2024-03-01 10:00:00');
        $this->insertActivity($activityId2, '2024-03-10 10:00:00');

        $this->strengthWorkoutRepository->saveForActivity($activityId1, StrengthWorkoutExercises::fromArray([
            ExerciseSet::create(ExerciseName::fromString('Squat'), 3, 5, Pound::from(315.0)),
            ExerciseSet::create(ExerciseName::fromString('Bench Press'), 4, 8, Pound::from(185.0)),
        ]));
        $this->strengthWorkoutRepository->saveForActivity($activityId2, StrengthWorkoutExercises::fromArray([
            ExerciseSet::create(ExerciseName::fromString('Squat'), 3, 5, Pound::from(335.0)),
        ]));

        $result = $this->strengthWorkoutRepository->findDailyBestByExercise(
            SerializableDateTime::fromString('2024-01-01'),
        );

        $this->assertArrayHasKey('Squat', $result);
        $this->assertArrayHasKey('Bench Press', $result);
        $this->assertCount(2, $result['Squat']);
        $this->assertSame('2024-03-01', $result['Squat'][0]['date']);
        $this->assertSame('2024-03-10', $result['Squat'][1]['date']);
        // Epley 335*(1+5/30)
        $this->assertEqualsWithDelta(335.0 * (1 + 5 / 30.0), $result['Squat'][1]['oneRepMax'], 0.001);
    }

    public function testFindDailyBestByExerciseRespectsDateFilter(): void
    {
        $activityId = ActivityId::fromUnprefixed('filter-act');
        $this->insertActivity($activityId, '2023-06-01 10:00:00');

        $this->strengthWorkoutRepository->saveForActivity($activityId, StrengthWorkoutExercises::fromArray([
            ExerciseSet::create(ExerciseName::fromString('Deadlift'), 1, 3, Pound::from(405.0)),
        ]));

        // Activity is from 2023, so querying since 2024 should return empty
        $result = $this->strengthWorkoutRepository->findDailyBestByExercise(
            SerializableDateTime::fromString('2024-01-01'),
        );
        $this->assertArrayNotHasKey('Deadlift', $result);
    }

    public function testFindWeeklyRollingTotal(): void
    {
        $activityId1 = ActivityId::fromUnprefixed('sbd-act-1');
        $this->insertActivity($activityId1, '2024-03-04 10:00:00');

        $this->strengthWorkoutRepository->saveForActivity($activityId1, StrengthWorkoutExercises::fromArray([
            ExerciseSet::create(ExerciseName::fromString('Squat'), 1, 5, Pound::from(315.0)),
            ExerciseSet::create(ExerciseName::fromString('Bench Press'), 1, 5, Pound::from(185.0)),
            ExerciseSet::create(ExerciseName::fromString('Deadlift'), 1, 5, Pound::from(405.0)),
        ]));

        $result = $this->strengthWorkoutRepository->findWeeklyRollingTotal(
            SerializableDateTime::fromString('2024-01-01'),
        );

        $this->assertCount(1, $result);
        // Epley totals: Squat + Bench + Deadlift
        $squat1RM = 315.0 * (1 + 5 / 30.0);
        $bench1RM = 185.0 * (1 + 5 / 30.0);
        $deadlift1RM = 405.0 * (1 + 5 / 30.0);
        $this->assertEqualsWithDelta($squat1RM + $bench1RM + $deadlift1RM, $result[0]['total'], 0.01);
    }

    public function testFindWeeklyRollingTotalSkipsWeeksWithMissingLifts(): void
    {
        $activityId = ActivityId::fromUnprefixed('partial-act');
        $this->insertActivity($activityId, '2024-03-04 10:00:00');

        // Only Squat and Bench — no Deadlift → should be excluded
        $this->strengthWorkoutRepository->saveForActivity($activityId, StrengthWorkoutExercises::fromArray([
            ExerciseSet::create(ExerciseName::fromString('Squat'), 1, 5, Pound::from(315.0)),
            ExerciseSet::create(ExerciseName::fromString('Bench Press'), 1, 5, Pound::from(185.0)),
        ]));

        $result = $this->strengthWorkoutRepository->findWeeklyRollingTotal(
            SerializableDateTime::fromString('2024-01-01'),
        );

        $this->assertCount(0, $result);
    }

    private function insertActivity(ActivityId $activityId, string $startDateTime): void
    {
        $this->getConnection()->executeStatement(
            "INSERT INTO Activity
             (activityId, startDateTime, sportType, name, distance, elevation, averageSpeed, maxSpeed, movingTimeInSeconds, kudoCount, totalImageCount)
             VALUES (:activityId, :startDateTime, 'WeightTraining', 'Test Workout', 0, 0, 0, 0, 3600, 0, 0)",
            ['activityId' => (string) $activityId, 'startDateTime' => $startDateTime],
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->strengthWorkoutRepository = new DbalStrengthWorkoutRepository($this->getConnection());
    }
}
