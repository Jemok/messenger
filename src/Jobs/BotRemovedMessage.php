<?php

namespace RTippin\Messenger\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RTippin\Messenger\Actions\Messages\StoreSystemMessage;
use RTippin\Messenger\Events\BotArchivedEvent;
use RTippin\Messenger\Support\MessageTransformer;
use Throwable;

class BotRemovedMessage implements ShouldQueue
{
    use Dispatchable,
        InteractsWithQueue,
        Queueable,
        SerializesModels;

    /**
     * @var BotArchivedEvent
     */
    private BotArchivedEvent $event;

    /**
     * Create a new job instance.
     *
     * @param BotArchivedEvent $event
     */
    public function __construct(BotArchivedEvent $event)
    {
        $this->event = $event;
    }

    /**
     * Execute the job.
     *
     * @param StoreSystemMessage $message
     * @return void
     * @throws Throwable
     */
    public function handle(StoreSystemMessage $message): void
    {
        $message->execute(...$this->systemMessage());
    }

    /**
     * @return array
     */
    private function systemMessage(): array
    {
        return MessageTransformer::makeBotRemoved(
            $this->event->bot->thread,
            $this->event->provider,
            $this->event->bot->name
        );
    }
}
