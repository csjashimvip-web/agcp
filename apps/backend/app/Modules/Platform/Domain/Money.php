<?php

namespace App\Modules\Platform\Domain;

use InvalidArgumentException;

final readonly class Money
{
    public function __construct(
        public int $minor,
        public string $currency,
    ) {
        if ($minor < 0) {
            throw new InvalidArgumentException('Money cannot contain a negative unsigned amount.');
        }

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Currency must be a three-letter uppercase ISO-style code.');
        }
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currency mismatch.');
        }
    }
}