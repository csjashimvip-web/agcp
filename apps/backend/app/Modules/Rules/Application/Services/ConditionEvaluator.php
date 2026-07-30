<?php
namespace Modules\Rules\Application\Services;
final class ConditionEvaluator
{
    public function matches(array $conditions, string $mode, array $context): bool
    {
        if ($conditions === []) return true;
        $results = array_map(fn(array $condition): bool => $this->match($condition, $context), $conditions);
        return $mode === 'any' ? in_array(true, $results, true) : !in_array(false, $results, true);
    }

    private function match(array $condition, array $context): bool
    {
        $field=(string)($condition['field'] ?? '');
        $operator=(string)($condition['operator'] ?? 'eq');
        $expected=$condition['value'] ?? null;
        $actual=data_get($context,$field);
        return match($operator) {
            'eq' => $this->comparable($actual) === $this->comparable($expected),
            'neq' => $this->comparable($actual) !== $this->comparable($expected),
            'gt' => is_numeric($actual) && is_numeric($expected) && (float)$actual > (float)$expected,
            'gte' => is_numeric($actual) && is_numeric($expected) && (float)$actual >= (float)$expected,
            'lt' => is_numeric($actual) && is_numeric($expected) && (float)$actual < (float)$expected,
            'lte' => is_numeric($actual) && is_numeric($expected) && (float)$actual <= (float)$expected,
            'in' => is_array($expected) && in_array($actual,$expected,true),
            'not_in' => is_array($expected) && !in_array($actual,$expected,true),
            'contains' => is_string($actual) && str_contains(mb_strtolower($actual),mb_strtolower((string)$expected)),
            'between' => is_array($expected) && count($expected)===2 && is_numeric($actual) && (float)$actual >= (float)$expected[0] && (float)$actual <= (float)$expected[1],
            'exists' => $actual !== null,
            'missing' => $actual === null,
            default => false,
        };
    }
    private function comparable(mixed $value): mixed { return is_string($value) ? mb_strtolower(trim($value)) : $value; }
}
