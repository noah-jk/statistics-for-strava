<?php

declare(strict_types=1);

namespace App\Domain\Activity\Strength;

final readonly class StrengthVolumeChart
{
    private function __construct(
        /** @var array<string, list<array{week: string, volume: float}>> */
        private array $weeklyVolumeByExercise,
    ) {
    }

    /**
     * @param array<string, list<array{week: string, volume: float}>> $weeklyVolumeByExercise
     */
    public static function create(array $weeklyVolumeByExercise): self
    {
        return new self($weeklyVolumeByExercise);
    }

    /**
     * @return array<mixed>
     */
    public function build(): array
    {
        if ([] === $this->weeklyVolumeByExercise) {
            return [];
        }

        $allWeeks = [];
        foreach ($this->weeklyVolumeByExercise as $entries) {
            foreach ($entries as $entry) {
                $allWeeks[] = $entry['week'];
            }
        }
        $allWeeks = array_values(array_unique($allWeeks));
        sort($allWeeks);

        $series = [];
        foreach ($this->weeklyVolumeByExercise as $exerciseName => $entries) {
            $dataMap = [];
            foreach ($entries as $entry) {
                $dataMap[$entry['week']] = $entry['volume'];
            }

            $data = [];
            foreach ($allWeeks as $week) {
                $data[] = isset($dataMap[$week]) ? round($dataMap[$week], 0) : 0;
            }

            $series[] = [
                'name' => $exerciseName,
                'type' => 'bar',
                'data' => $data,
            ];
        }

        $count = count($allWeeks);
        $defaultSpan = min(52, $count);

        return [
            'animation' => true,
            'backgroundColor' => null,
            'grid' => [
                'left' => '10px',
                'right' => '10px',
                'bottom' => '50px',
                'containLabel' => true,
            ],
            'legend' => ['show' => true],
            'tooltip' => ['trigger' => 'axis'],
            'dataZoom' => [
                [
                    'type' => 'slider',
                    'startValue' => $count - $defaultSpan,
                    'endValue' => $count,
                    'brushSelect' => false,
                    'zoomLock' => false,
                ],
            ],
            'xAxis' => [
                [
                    'type' => 'category',
                    'data' => $allWeeks,
                    'axisLabel' => ['rotate' => -30, 'interval' => 'auto'],
                ],
            ],
            'yAxis' => [
                [
                    'type' => 'value',
                    'axisLabel' => ['formatter' => '{value} lbs'],
                ],
            ],
            'series' => $series,
        ];
    }
}
