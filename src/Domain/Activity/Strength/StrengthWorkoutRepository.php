<?php

declare(strict_types=1);

namespace App\Domain\Activity\Strength;

use App\Domain\Activity\ActivityId;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

interface StrengthWorkoutRepository
{
    public function saveForActivity(ActivityId $activityId, StrengthWorkoutExercises $exercises): void;

    public function findByActivityId(ActivityId $activityId): StrengthWorkoutExercises;

    public function isImportedForActivity(ActivityId $activityId): bool;

    public function deleteForActivity(ActivityId $activityId): void;

    /** @return ActivityId[] */
    public function findUnprocessedWeightTrainingActivityIds(): array;

    /** @return array<string, list<array{date: string, oneRepMax: float}>> */
    public function findDailyBestByExercise(SerializableDateTime $since): array;

    /** @return list<array{week: string, total: float}> */
    public function findWeeklyRollingTotal(SerializableDateTime $since): array;

    /** @return array<string, float> */
    public function findAllTimePRPerExercise(): array;

    /**
     * @param string[] $exercises
     *
     * @return array<string, list<array{date: string, value: float}>>
     */
    public function findAllTimeDailyBestByExercise(array $exercises): array;

    /**
     * @param string[] $exercises
     *
     * @return array<string, list<array{week: string, volume: float}>>
     */
    public function findAllTimeWeeklyVolumeByExercise(array $exercises): array;
}
