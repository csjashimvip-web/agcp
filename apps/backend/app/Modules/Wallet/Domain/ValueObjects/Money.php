<?php
namespace Modules\Wallet\Domain\ValueObjects;
use InvalidArgumentException;
final readonly class Money
{
    public function __construct(public int $minor, public string $currency)
    {
        $currency = strtoupper($this->currency);
        if ($this->minor < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative.');
        }
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Currency must be a three-letter ISO code.');
        }
        if ($currency !== $this->currency) {
            throw new InvalidArgumentException('Currency must be uppercase.');
        }
    }

    public static function fromDecimal(string $amount, string $currency): self
    {
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('Amount must have no more than two decimal places.');
        }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $minor = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
        return new self($minor, strtoupper($currency));
    }

    public function decimal(): string
    {
        return number_format($this->minor / 100, 2, '.', '');
    }
}
