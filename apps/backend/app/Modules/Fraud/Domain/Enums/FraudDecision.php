<?php
namespace Modules\Fraud\Domain\Enums;
enum FraudDecision: string { case Allow='allow'; case StepUp='step_up'; case Review='review'; case Block='block'; }
