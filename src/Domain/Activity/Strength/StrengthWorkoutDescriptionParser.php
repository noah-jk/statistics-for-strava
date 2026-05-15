<?php

declare(strict_types=1);

namespace App\Domain\Activity\Strength;

use App\Infrastructure\ValueObject\Measurement\Mass\Pound;

final class StrengthWorkoutDescriptionParser
{
    // Extracts the exercise name and the raw tokens string from a line.
    // The lookahead (?=\d+x\d+) anchors the boundary between name and the first set token,
    // allowing multi-word names like "Bench Press" without ambiguity.
    private const string LINE_PATTERN =
        '/^(?P<name>[A-Za-z][A-Za-z0-9 ]*?)\s+(?=\d+x\d+)(?P<tokens>.+)$/';

    // Matches a single set token: [sets]x[reps] or [sets]x[reps]@[weight]
    // Sets/reps must be positive (no zero, no leading zeros).
    private const string SET_TOKEN_PATTERN =
        '/^(?P<sets>[1-9]\d*)x(?P<reps>[1-9]\d*)(?:@(?P<weight>\d+(?:\.\d+)?))?$/';

    public function parse(string $description): StrengthWorkoutExercises
    {
        $exercises = StrengthWorkoutExercises::empty();

        if ('' === trim($description)) {
            return $exercises;
        }

        foreach (preg_split('/\r?\n/', $description) ?: [] as $line) {
            $trimmed = trim($line);
            if ('' === $trimmed) {
                continue;
            }
            $trimmed = (string) preg_replace('/\s+RPE\d+(?:\.\d+)?$/i', '', $trimmed);
            if (!preg_match(self::LINE_PATTERN, $trimmed, $matches)) {
                continue;
            }

            $exerciseName = ExerciseName::fromString($matches['name']);
            foreach (preg_split('/\s*,\s*/', $matches['tokens']) ?: [] as $token) {
                if (!preg_match(self::SET_TOKEN_PATTERN, $token, $t)) {
                    continue;
                }

                $exercises->add(ExerciseSet::create(
                    exerciseName: $exerciseName,
                    numberOfSets: (int) $t['sets'],
                    numberOfReps: (int) $t['reps'],
                    weightLbs: '' !== ($t['weight'] ?? '')
                        ? Pound::from((float) $t['weight'])
                        : null,
                ));
            }
        }

        return $exercises;
    }
}
