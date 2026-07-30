<?php
namespace Modules\Observability\Infrastructure;
use Illuminate\Support\ServiceProvider;
use Modules\Observability\Infrastructure\Console\CaptureOperationsSnapshot;
final class ObservabilityServiceProvider extends ServiceProvider { public function boot():void{if($this->app->runningInConsole())$this->commands([CaptureOperationsSnapshot::class]);} }
