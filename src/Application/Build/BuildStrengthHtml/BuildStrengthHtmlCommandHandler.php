<?php

declare(strict_types=1);

namespace App\Application\Build\BuildStrengthHtml;

use App\Domain\Activity\Strength\StrengthConfig;
use App\Domain\Activity\Strength\StrengthProgressionChart;
use App\Domain\Activity\Strength\StrengthVolumeChart;
use App\Domain\Activity\Strength\StrengthWorkoutRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Serialization\Json;
use League\Flysystem\FilesystemOperator;
use Twig\Environment;

final readonly class BuildStrengthHtmlCommandHandler implements CommandHandler
{
    public function __construct(
        private StrengthWorkoutRepository $strengthWorkoutRepository,
        private StrengthConfig $strengthConfig,
        private Environment $twig,
        private FilesystemOperator $buildStorage,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof BuildStrengthHtml);

        $exercises = $this->strengthConfig->getPrimaryLifts();

        $progressionChart = Json::encode(
            StrengthProgressionChart::create(
                $this->strengthWorkoutRepository->findAllTimeDailyBestByExercise($exercises),
            )->build(),
        );

        $volumeChart = Json::encode(
            StrengthVolumeChart::create(
                $this->strengthWorkoutRepository->findAllTimeWeeklyVolumeByExercise($exercises),
            )->build(),
        );

        $this->buildStorage->write(
            'strength.html',
            $this->twig->load('html/strength.html.twig')->render([
                'progressionChart' => $progressionChart,
                'volumeChart' => $volumeChart,
                'primaryLifts' => $exercises,
            ]),
        );
    }
}
