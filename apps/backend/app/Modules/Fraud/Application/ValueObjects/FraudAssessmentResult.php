<?php
namespace Modules\Fraud\Application\ValueObjects;
use Modules\Fraud\Domain\Enums\FraudDecision;
use Modules\Fraud\Domain\Enums\RiskLevel;
final readonly class FraudAssessmentResult
{
    public function __construct(
        public string $assessmentId,
        public int $score,
        public RiskLevel $level,
        public FraudDecision $decision,
        public array $signals,
    ) {}
}
