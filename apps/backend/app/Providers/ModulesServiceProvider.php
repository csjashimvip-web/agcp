<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
class ModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (config('modules.providers', []) as $provider) {
            if (!is_string($provider) || !class_exists($provider)) {
                throw new RuntimeException('Invalid module provider.');
            }
            $this->app->register($provider);
        }
    }
}
