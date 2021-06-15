<?php

namespace RTippin\Messenger\Tests\Http;

use RTippin\Messenger\Facades\Messenger;
use RTippin\Messenger\Models\Bot;
use RTippin\Messenger\Models\Participant;
use RTippin\Messenger\Models\Thread;
use RTippin\Messenger\Tests\FeatureTestCase;

class BotsTest extends FeatureTestCase
{
    /** @test */
    public function admin_can_view_bots()
    {
        $thread = $this->createGroupThread($this->tippin);
        Bot::factory()->for($thread)->owner($this->tippin)->count(2)->create();
        $this->actingAs($this->tippin);

        $this->getJson(route('api.messenger.threads.bots.index', [
            'thread' => $thread->id,
        ]))
            ->assertSuccessful()
            ->assertJsonCount(2);
    }

    /** @test */
    public function non_admin_can_view_bots()
    {
        $thread = $this->createGroupThread($this->tippin, $this->doe);
        Bot::factory()->for($thread)->owner($this->tippin)->count(2)->create();
        $this->actingAs($this->doe);

        $this->getJson(route('api.messenger.threads.bots.index', [
            'thread' => $thread->id,
        ]))
            ->assertSuccessful()
            ->assertJsonCount(2);
    }

    /** @test */
    public function admin_can_add_bot()
    {
        $thread = $this->createGroupThread($this->tippin);
        $this->actingAs($this->tippin);

        $this->postJson(route('api.messenger.threads.bots.store', [
            'thread' => $thread->id,
        ]), [
            'name' => 'Test Bot',
            'enabled' => true,
            'cooldown' => 0,
        ])
            ->assertSuccessful()
            ->assertJson([
                'name' => 'Test Bot',
                'owner_id' => $this->tippin->getKey(),
                'owner_type' => $this->tippin->getMorphClass(),
            ]);
    }

    /** @test */
    public function participant_with_permission_can_add_bot()
    {
        $thread = Thread::factory()->group()->create();
        Participant::factory()->for($thread)->owner($this->doe)->create(['manage_bots' => true]);
        $this->actingAs($this->doe);

        $this->postJson(route('api.messenger.threads.bots.store', [
            'thread' => $thread->id,
        ]), [
            'name' => 'Test Bot',
            'enabled' => true,
            'cooldown' => 0,
        ])
            ->assertSuccessful()
            ->assertJson([
                'name' => 'Test Bot',
                'owner_id' => $this->doe->getKey(),
                'owner_type' => $this->doe->getMorphClass(),
            ]);
    }

    /** @test */
    public function forbidden_to_add_bot_when_disabled_in_config()
    {
        Messenger::setBots(false);
        $thread = $this->createGroupThread($this->tippin);
        $this->actingAs($this->tippin);

        $this->postJson(route('api.messenger.threads.bots.store', [
            'thread' => $thread->id,
        ]), [
            'name' => 'Test Bot',
            'enabled' => true,
            'cooldown' => 0,
        ])
            ->assertForbidden();
    }

    /** @test */
    public function forbidden_to_add_bot_when_disabled_in_thread()
    {
        $thread = Thread::factory()->group()->create(['chat_bots' => false]);
        Participant::factory()->for($thread)->admin()->owner($this->tippin)->create();
        $this->actingAs($this->tippin);

        $this->postJson(route('api.messenger.threads.bots.store', [
            'thread' => $thread->id,
        ]), [
            'name' => 'Test Bot',
            'enabled' => true,
            'cooldown' => 0,
        ])
            ->assertForbidden();
    }

    /** @test */
    public function participant_without_permission_forbidden_to_add_bot()
    {
        $thread = $this->createGroupThread($this->tippin, $this->doe);
        $this->actingAs($this->doe);

        $this->postJson(route('api.messenger.threads.bots.store', [
            'thread' => $thread->id,
        ]), [
            'name' => 'Test Bot',
            'enabled' => true,
            'cooldown' => 0,
        ])
            ->assertForbidden();
    }

    /** @test */
    public function admin_can_view_bot()
    {
        $thread = $this->createGroupThread($this->tippin);
        $bot = Bot::factory()->for($thread)->owner($this->tippin)->create(['name' => 'Test Bot']);
        $this->actingAs($this->tippin);

        $this->getJson(route('api.messenger.threads.bots.show', [
            'thread' => $thread->id,
            'bot' => $bot->id,
        ]))
            ->assertSuccessful()
            ->assertJson([
                'name' => 'Test Bot',
                'owner_id' => $this->tippin->getKey(),
                'owner_type' => $this->tippin->getMorphClass(),
            ]);
    }

    /** @test */
    public function participant_can_view_bot()
    {
        $thread = $this->createGroupThread($this->tippin, $this->doe);
        $bot = Bot::factory()->for($thread)->owner($this->tippin)->create(['name' => 'Test Bot']);
        $this->actingAs($this->doe);

        $this->getJson(route('api.messenger.threads.bots.show', [
            'thread' => $thread->id,
            'bot' => $bot->id,
        ]))
            ->assertSuccessful()
            ->assertJson([
                'name' => 'Test Bot',
                'owner_id' => $this->tippin->getKey(),
                'owner_type' => $this->tippin->getMorphClass(),
            ]);
    }

    /** @test */
    public function admin_can_remove_bot()
    {
        $thread = $this->createGroupThread($this->tippin);
        $bot = Bot::factory()->for($thread)->owner($this->tippin)->create();
        $this->actingAs($this->tippin);

        $this->deleteJson(route('api.messenger.threads.bots.destroy', [
            'thread' => $thread->id,
            'bot' => $bot->id,
        ]))
            ->assertSuccessful();
    }

    /** @test */
    public function participant_with_permission_can_remove_bot()
    {
        $thread = Thread::factory()->group()->create();
        Participant::factory()->for($thread)->owner($this->doe)->create(['manage_bots' => true]);
        $bot = Bot::factory()->for($thread)->owner($this->tippin)->create();
        $this->actingAs($this->doe);

        $this->deleteJson(route('api.messenger.threads.bots.destroy', [
            'thread' => $thread->id,
            'bot' => $bot->id,
        ]))
            ->assertSuccessful();
    }

    /** @test */
    public function participant_without_permission_forbidden_to_remove_bot()
    {
        $thread = $this->createGroupThread($this->tippin, $this->doe);
        $bot = Bot::factory()->for($thread)->owner($this->tippin)->create();
        $this->actingAs($this->doe);

        $this->deleteJson(route('api.messenger.threads.bots.destroy', [
            'thread' => $thread->id,
            'bot' => $bot->id,
        ]))
            ->assertForbidden();
    }

    /**
     * @test
     * @dataProvider botFailsValidation
     * @param $name
     * @param $enabled
     * @param $cooldown
     * @param $errors
     */
    public function store_bot_fails_validation($name, $enabled, $cooldown, $errors)
    {
        $thread = $this->createGroupThread($this->tippin);
        $this->actingAs($this->tippin);

        $this->postJson(route('api.messenger.threads.bots.store', [
            'thread' => $thread->id,
        ]), [
            'name' => $name,
            'enabled' => $enabled,
            'cooldown' => $cooldown,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors($errors);
    }

    /**
     * @test
     * @dataProvider botPassesValidation
     * @param $name
     * @param $enabled
     * @param $cooldown
     */
    public function store_bot_passes_validation($name, $enabled, $cooldown)
    {
        $thread = $this->createGroupThread($this->tippin);
        $this->actingAs($this->tippin);

        $this->postJson(route('api.messenger.threads.bots.store', [
            'thread' => $thread->id,
        ]), [
            'name' => $name,
            'enabled' => $enabled,
            'cooldown' => $cooldown,
        ])
            ->assertSuccessful();
    }

    public function botFailsValidation(): array
    {
        return [
            'All values required' => [null, null, null, ['name', 'enabled', 'cooldown']],
            'Name and cooldown cannot be boolean' => [true, false, false, ['name', 'cooldown']],
            'Name must be at least two characters' => ['T', false, 0, ['name']],
            'Cooldown cannot be negative' => ['Test', false, -1, ['cooldown']],
            'Cooldown cannot be over 900' => ['Test', false, 901, ['cooldown']],
        ];
    }

    public function botPassesValidation(): array
    {
        return [
            ['Te', false, 0],
            ['Test', true, 900],
            ['Test More', true, 1],
        ];
    }
}
