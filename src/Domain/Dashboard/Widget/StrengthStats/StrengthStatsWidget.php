<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\StrengthStats;

use App\Domain\Activity\Strength\StrengthConfig;
use App\Domain\Activity\Strength\StrengthWorkoutRepository;
use App\Domain\Dashboard\Widget\Widget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class StrengthStatsWidget implements Widget
{
    public function __construct(
        private StrengthWorkoutRepository $strengthWorkoutRepository,
        private StrengthConfig $strengthConfig,
        private Environment $twig,
        private TranslatorInterface $translator,
    ) {
    }

    public function getDefaultConfiguration(): WidgetConfiguration
    {
        return WidgetConfiguration::empty();
    }

    public function guardValidConfiguration(WidgetConfiguration $configuration): void
    {
    }

    public function render(SerializableDateTime $now, WidgetConfiguration $configuration): ?string
    {
        $allTimePRs = $this->strengthWorkoutRepository->findAllTimePRPerExercise();
        if ([] === $allTimePRs) {
            return null;
        }

        $since = SerializableDateTime::fromString($now->modify('-1 year')->format('Y-m-d'));
        $dailyBest = $this->strengthWorkoutRepository->findDailyBestByExercise($since);
        $weeklyTotals = $this->strengthWorkoutRepository->findWeeklyRollingTotal($since);

        $chart = StrengthStatsChart::create($dailyBest, $this->translator);
        $liftsChart = $chart->buildLiftsChart();
        $totalChart = $chart->buildTotalChart($weeklyTotals);

        return $this->twig->load('html/dashboard/widget/widget--strength-stats.html.twig')->render([
            'primaryLifts' => $this->strengthConfig->getPrimaryLifts(),
            'allTimePRs' => $allTimePRs,
            'liftsChart' => [] !== $liftsChart ? Json::encode($liftsChart) : null,
            'totalChart' => [] !== $totalChart ? Json::encode($totalChart) : null,
        ]);
    }
}
