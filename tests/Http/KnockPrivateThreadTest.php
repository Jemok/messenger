<?php

namespace RTippin\Messenger\Tests\Http;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use RTippin\Messenger\Broadcasting\KnockBroadcast;
use RTippin\Messenger\Events\KnockEvent;
use RTippin\Messenger\Models\Thread;
use RTippin\Messenger\Tests\FeatureTestCase;

class KnockPrivateThreadTest extends FeatureTestCase
{
    private Thread $private;

    protected function setUp(): void
    {
        parent::setUp();

        $this->private = $this->makePrivateThread(
            $this->userTippin(),
            $this->userDoe()
        );
    }

    /** @test */
    public function user_can_knock_at_private_thread()
    {
        $tippin = $this->userTippin();

        $doe = $this->userDoe();

        Event::fake([
            KnockBroadcast::class,
            KnockEvent::class,
        ]);

        $this->actingAs($tippin);

        $this->postJson(route('api.messenger.threads.knock', [
            'thread' => $this->private->id,
        ]))
            ->assertSuccessful();

        $this->assertTrue(Cache::has('knock.knock.'.$this->private->id.'.'.$tippin->getKey()));

        Event::assertDispatched(function (KnockBroadcast $event) use ($tippin, $doe) {
            $this->assertContains('private-user.'.$doe->getKey(), $event->broadcastOn());
            $this->assertEquals($this->private->id, $event->broadcastWith()['thread']['id']);

            return true;
        });

        Event::assertDispatched(function (KnockEvent $event) use ($tippin) {
            $this->assertEquals($tippin->getKey(), $event->provider->getKey());
            $this->assertEquals($this->private->id, $event->thread->id);

            return true;
        });
    }

    /** @test */
    public function user_forbidden_to_knock_at_private_thread_when_timeout_exist()
    {
        $tippin = $this->userTippin();

        Cache::put('knock.knock.'.$this->private->id.'.'.$tippin->getKey(), true);

        $this->actingAs($tippin);

        $this->postJson(route('api.messenger.threads.knock', [
            'thread' => $this->private->id,
        ]))
            ->assertForbidden();
    }

    /** @test */
    public function non_participant_forbidden_to_knock_at_private_thread()
    {
        $this->actingAs($this->companyDevelopers());

        $this->postJson(route('api.messenger.threads.knock', [
            'thread' => $this->private->id,
        ]))
            ->assertForbidden();
    }
}
