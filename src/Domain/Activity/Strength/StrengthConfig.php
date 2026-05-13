<?php

declare(strict_types=1);

namespace App\Domain\Activity\Strength;

final readonly class StrengthConfig
{
    /** @param string[] $primaryLifts */
    private function __construct(
        private array $primaryLifts,
    ) {
    }

    /** @param string[] $primaryLifts */
    public static function fromArray(array $primaryLifts): self
    {
        return new self($primaryLifts);
    }

    /** @return string[] */
    public function getPrimaryLifts(): array
    {
        return $this->primaryLifts;
    }
}
