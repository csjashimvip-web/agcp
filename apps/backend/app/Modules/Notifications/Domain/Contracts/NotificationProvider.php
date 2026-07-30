<?php
namespace Modules\Notifications\Domain\Contracts;
interface NotificationProvider
{
    public function channel(): string;
    public function send(string $recipient, array $message): array;
}
