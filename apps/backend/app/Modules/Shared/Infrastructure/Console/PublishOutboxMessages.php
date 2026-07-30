<?php
namespace Modules\Shared\Infrastructure\Console;
use Illuminate\Console\Command;
use Modules\Shared\Infrastructure\Events\OutboxMessagePublished;
use Modules\Shared\Infrastructure\Models\OutboxMessage;
use Throwable;
class PublishOutboxMessages extends Command
{
    protected $signature = 'outbox:publish {--limit=100}';
    protected $description = 'Publish pending transactional outbox messages.';
    public function handle(): int
    {
        $messages = OutboxMessage::query()->whereNull('published_at')->whereNull('failed_at')->where('available_at','<=',now())->oldest('occurred_at')->limit(max(1,min(1000,(int)$this->option('limit'))))->get();
        foreach ($messages as $message) {
            try {
                event(new OutboxMessagePublished($message->id,$message->event_name,$message->schema_version,$message->payload,$message->metadata ?? [],$message->tenant_id));
                $message->forceFill(['published_at'=>now(),'attempts'=>$message->attempts+1,'last_error'=>null])->save();
            } catch (Throwable $e) {
                $attempts = $message->attempts + 1;
                $message->forceFill([
                    'attempts'=>$attempts,
                    'last_error'=>mb_substr($e->getMessage(),0,2000),
                    'available_at'=>now()->addSeconds(min(3600,2 ** min($attempts,10))),
                    'failed_at'=>$attempts >= (int)env('OUTBOX_MAX_ATTEMPTS',10) ? now() : null,
                ])->save();
            }
        }
        $this->info('Processed '.$messages->count().' message(s).');
        return self::SUCCESS;
    }
}
