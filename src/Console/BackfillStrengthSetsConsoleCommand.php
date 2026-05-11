<?php

declare(strict_types=1);

namespace App\Console;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Strength\StrengthWorkoutDescriptionParser;
use App\Domain\Activity\Strength\StrengthWorkoutRepository;
use App\Infrastructure\Console\ProvideConsoleIntro;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:strava:backfill-strength-sets', description: 'Parses and saves strength sets for WeightTraining activities that have not yet been processed')]
class BackfillStrengthSetsConsoleCommand extends Command
{
    use ProvideConsoleIntro;

    public function __construct(
        private readonly ActivityRepository $activityRepository,
        private readonly StrengthWorkoutRepository $strengthWorkoutRepository,
        private readonly StrengthWorkoutDescriptionParser $strengthWorkoutDescriptionParser,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);
        $this->outputConsoleIntro($output);

        $activityIds = $this->strengthWorkoutRepository->findUnprocessedWeightTrainingActivityIds();

        if ([] === $activityIds) {
            $output->success('All WeightTraining activities already have strength data.');

            return Command::SUCCESS;
        }

        $processed = 0;
        foreach ($activityIds as $activityId) {
            $activity = $this->activityRepository->find($activityId);
            $exercises = $this->strengthWorkoutDescriptionParser->parse($activity->getDescription());
            if ($exercises->isEmpty()) {
                continue;
            }

            $this->strengthWorkoutRepository->saveForActivity($activityId, $exercises);
            ++$processed;
        }

        $output->success(sprintf(
            'Backfill complete: %d of %d unprocessed activities had parseable strength data.',
            $processed,
            count($activityIds),
        ));

        return Command::SUCCESS;
    }
}
