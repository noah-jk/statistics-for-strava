<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Strength;

use App\Domain\Activity\Strength\StrengthProgressionChart;
use PHPUnit\Framework\TestCase;

class StrengthProgressionChartTest extends TestCase
{
    public function testBuildReturnsEmptyArrayWhenNoData(): void
    {
        $chart = StrengthProgressionChart::create([]);

        $this->assertSame([], $chart->build());
    }

    public function testBuildReturnsSingleExerciseSeries(): void
    {
        $data = [
            'Squat' => [
                ['date' => '2024-01-01', 'value' => 350.0],
                ['date' => '2024-01-15', 'value' => 360.0],
            ],
        ];

        $result = StrengthProgressionChart::create($data)->build();

        $this->assertArrayHasKey('series', $result);
        $this->assertCount(1, $result['series']);
        $this->assertSame('Squat', $result['series'][0]['name']);
        $this->assertSame('line', $result['series'][0]['type']);
        $this->assertSame([350.0, 360.0], $result['series'][0]['data']);
    }

    public function testBuildAlignsDatesAcrossMultipleExercises(): void
    {
        $data = [
            'Squat' => [
                ['date' => '2024-01-01', 'value' => 350.0],
                ['date' => '2024-01-15', 'value' => 360.0],
            ],
            'Deadlift' => [
                ['date' => '2024-01-15', 'value' => 400.0],
            ],
        ];

        $result = StrengthProgressionChart::create($data)->build();

        $this->assertCount(2, $result['series']);
        $squatSeries = $result['series'][0];
        $deadliftSeries = $result['series'][1];

        $this->assertSame('Squat', $squatSeries['name']);
        $this->assertSame([350.0, 360.0], $squatSeries['data']);

        $this->assertSame('Deadlift', $deadliftSeries['name']);
        $this->assertSame([null, 400.0], $deadliftSeries['data']);
    }

    public function testBuildRoundsValuesToOneDecimalPlace(): void
    {
        $data = [
            'Bench Press' => [
                ['date' => '2024-01-01', 'value' => 185.333333],
            ],
        ];

        $result = StrengthProgressionChart::create($data)->build();

        $this->assertSame(185.3, $result['series'][0]['data'][0]);
    }

    public function testBuildIncludesDataZoom(): void
    {
        $data = [
            'Squat' => [
                ['date' => '2024-01-01', 'value' => 350.0],
            ],
        ];

        $result = StrengthProgressionChart::create($data)->build();

        $this->assertArrayHasKey('dataZoom', $result);
        $this->assertCount(1, $result['dataZoom']);
        $this->assertSame('slider', $result['dataZoom'][0]['type']);
    }
}
