<?php
namespace Modules\Rules\Application\ValueObjects;
final readonly class RuleEvaluationResult
{
    public function __construct(public array $matchedRuleIds, public array $actions, public array $executions) {}
    public function matched(): bool { return $this->matchedRuleIds !== []; }
}
