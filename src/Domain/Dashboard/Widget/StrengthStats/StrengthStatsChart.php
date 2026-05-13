<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\StrengthStats;

use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class StrengthStatsChart
{
    private function __construct(
        /** @var array<string, list<array{date: string, oneRepMax: float}>> */
        private array $dailyBestByExercise,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, list<array{date: string, oneRepMax: float}>> $dailyBestByExercise
     */
    public static function create(
        array $dailyBestByExercise,
        TranslatorInterface $translator,
    ): self {
        return new self($dailyBestByExercise, $translator);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildLiftsChart(): array
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
                $dataMap[$entry['date']] = $entry['oneRepMax'];
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

        $minZoomValueSpan = min(10, count($allDates));
        $maxZoomValueSpan = min(52, count($allDates));

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
                    'startValue' => count($allDates) - $minZoomValueSpan,
                    'endValue' => count($allDates),
                    'minValueSpan' => $minZoomValueSpan,
                    'maxValueSpan' => $maxZoomValueSpan,
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

    /**
     * @param list<array{week: string, total: float}> $weeklyTotals
     *
     * @return array<string, mixed>
     */
    public function buildTotalChart(array $weeklyTotals): array
    {
        if ([] === $weeklyTotals) {
            return [];
        }

        $weeks = array_column($weeklyTotals, 'week');
        $totals = array_map(fn (array $row): float => round($row['total'], 0), $weeklyTotals);

        $minZoomValueSpan = min(10, count($weeks));
        $maxZoomValueSpan = min(52, count($weeks));

        return [
            'animation' => true,
            'backgroundColor' => null,
            'color' => ['#E34902'],
            'grid' => [
                'left' => '10px',
                'right' => '10px',
                'bottom' => '50px',
                'containLabel' => true,
            ],
            'tooltip' => ['trigger' => 'axis'],
            'dataZoom' => [
                [
                    'type' => 'slider',
                    'startValue' => count($weeks) - $minZoomValueSpan,
                    'endValue' => count($weeks),
                    'minValueSpan' => $minZoomValueSpan,
                    'maxValueSpan' => $maxZoomValueSpan,
                    'brushSelect' => false,
                    'zoomLock' => false,
                ],
            ],
            'xAxis' => [
                [
                    'type' => 'category',
                    'data' => $weeks,
                    'axisLabel' => ['rotate' => -30, 'interval' => 'auto'],
                ],
            ],
            'yAxis' => [
                [
                    'type' => 'value',
                    'axisLabel' => ['formatter' => '{value} lbs'],
                ],
            ],
            'series' => [
                [
                    'name' => $this->translator->trans('SBD Total'),
                    'type' => 'bar',
                    'data' => $totals,
                ],
            ],
        ];
    }
}
