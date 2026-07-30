<?php
namespace Modules\Notifications\Application\Services;
use Modules\Notifications\Domain\Contracts\NotificationProvider;
use InvalidArgumentException;
final class NotificationProviderRegistry
{
    /** @param iterable<NotificationProvider> $providers */ public function __construct(private iterable $providers){}
    public function for(string $channel):NotificationProvider{foreach($this->providers as $provider)if($provider->channel()===$channel)return $provider;throw new InvalidArgumentException('No notification provider for channel '.$channel);}
}
