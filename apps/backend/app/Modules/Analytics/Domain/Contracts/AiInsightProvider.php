<?php
namespace Modules\Analytics\Domain\Contracts;
interface AiInsightProvider
{
    public function key(): string;
    public function version(): string;
    /** @return array<int, array<string, mixed>> */
    public function generate(array $context): array;
}
