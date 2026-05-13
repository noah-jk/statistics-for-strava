<?php

declare(strict_types=1);

namespace App\Domain\Activity\Strength;

final readonly class StrengthProgressionChart
{
    private function __construct(
        /** @var array<string, list<array{date: string, value: float}>> */
        private array $dailyBestByExercise,
    ) {
    }

    /**
     * @param array<string, list<array{date: string, value: float}>> $dailyBestByExercise
     */
    public static function create(array $dailyBestByExercise): self
    {
        return new self($dailyBestByExercise);
    }

    /**
     * @return array<mixed>
     */
    public function build(): array
    {
        if ([] === $this->dailyBestByExercise) {
            return [];
        }

        $allDates = [];
        foreach ($this->dailyBestByExercise as $entries) {
            foreach ($entries as $entry) {
                $allDates[] = $entry['date'];
            }
        }
        $allDates = array_values(array_unique($allDates));
        sort($allDates);

        $series = [];
        foreach ($this->dailyBestByExercise as $exerciseName => $entries) {
            $dataMap = [];
            foreach ($entries as $entry) {
                $dataMap[$entry['date']] = $entry['value'];
            }

            $data = [];
            foreach ($allDates as $date) {
                $data[] = isset($dataMap[$date]) ? round($dataMap[$date], 1) : null;
            }

            $series[] = [
                'name' => $exerciseName,
                'type' => 'line',
                'smooth' => false,
                'connectNulls' => true,
                'lineStyle' => ['width' => 2],
                'symbolSize' => 6,
                'showSymbol' => true,
                'data' => $data,
            ];
        }

        $count = count($allDates);
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
                    'boundaryGap' => false,
                    'data' => $allDates,
                    'axisLabel' => ['rotate' => -30, 'interval' => 'auto'],
                ],
            ],
            'yAxis' => [
                [
                    'type' => 'value',
                    'axisLabel' => ['formatter' => '{value} lbs'],
                    'splitLine' => ['show' => true, 'lineStyle' => ['color' => '#E0E6F1']],
                ],
            ],
            'series' => $series,
        ];
    }
}
