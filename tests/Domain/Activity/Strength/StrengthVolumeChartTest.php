<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Strength;

use App\Domain\Activity\Strength\StrengthVolumeChart;
use PHPUnit\Framework\TestCase;

class StrengthVolumeChartTest extends TestCase
{
    public function testBuildReturnsEmptyArrayWhenNoData(): void
    {
        $chart = StrengthVolumeChart::create([]);

        $this->assertSame([], $chart->build());
    }

    public function testBuildReturnsSingleExerciseSeries(): void
    {
        $data = [
            'Squat' => [
                ['week' => '2024-W01', 'volume' => 4725.0],
                ['week' => '2024-W02', 'volume' => 5040.0],
            ],
        ];

        $result = StrengthVolumeChart::create($data)->build();

        $this->assertArrayHasKey('series', $result);
        $this->assertCount(1, $result['series']);
        $this->assertSame('Squat', $result['series'][0]['name']);
        $this->assertSame('bar', $result['series'][0]['type']);
        $this->assertSame([4725.0, 5040.0], $result['series'][0]['data']);
    }

    public function testBuildAlignsWeeksAcrossMultipleExercises(): void
    {
        $data = [
            'Squat' => [
                ['week' => '2024-W01', 'volume' => 4725.0],
                ['week' => '2024-W02', 'volume' => 5040.0],
            ],
            'Deadlift' => [
                ['week' => '2024-W02', 'volume' => 3000.0],
            ],
        ];

        $result = StrengthVolumeChart::create($data)->build();

        $this->assertCount(2, $result['series']);

        $squatSeries = $result['series'][0];
        $deadliftSeries = $result['series'][1];

        $this->assertSame('Squat', $squatSeries['name']);
        $this->assertSame([4725.0, 5040.0], $squatSeries['data']);

        $this->assertSame('Deadlift', $deadliftSeries['name']);
        $this->assertSame([0, 3000.0], $deadliftSeries['data']);
    }

    public function testBuildRoundsVolumesToWholeNumbers(): void
    {
        $data = [
            'Bench Press' => [
                ['week' => '2024-W01', 'volume' => 5920.333],
            ],
        ];

        $result = StrengthVolumeChart::create($data)->build();

        $this->assertSame(5920.0, $result['series'][0]['data'][0]);
    }

    public function testBuildIncludesDataZoom(): void
    {
        $data = [
            'Squat' => [
                ['week' => '2024-W01', 'volume' => 4725.0],
            ],
        ];

        $result = StrengthVolumeChart::create($data)->build();

        $this->assertArrayHasKey('dataZoom', $result);
        $this->assertCount(1, $result['dataZoom']);
        $this->assertSame('slider', $result['dataZoom'][0]['type']);
    }

    public function testBuildSortsWeeksChronologically(): void
    {
        $data = [
            'Squat' => [
                ['week' => '2024-W03', 'volume' => 300.0],
                ['week' => '2024-W01', 'volume' => 100.0],
                ['week' => '2024-W02', 'volume' => 200.0],
            ],
        ];

        $result = StrengthVolumeChart::create($data)->build();

        $xAxisData = $result['xAxis'][0]['data'];
        $this->assertSame(['2024-W01', '2024-W02', '2024-W03'], $xAxisData);
    }
}
