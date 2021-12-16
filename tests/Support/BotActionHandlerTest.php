<?php

namespace RTippin\Messenger\Tests\Support;

use RTippin\Messenger\Exceptions\BotException;
use RTippin\Messenger\Facades\Messenger;
use RTippin\Messenger\Models\Bot;
use RTippin\Messenger\Models\BotAction;
use RTippin\Messenger\Models\Message;
use RTippin\Messenger\Models\Thread;
use RTippin\Messenger\Support\MessengerComposer;
use RTippin\Messenger\Tests\Fixtures\BrokenBotHandler;
use RTippin\Messenger\Tests\Fixtures\FunBotHandler;
use RTippin\Messenger\Tests\Fixtures\SillyBotHandler;
use RTippin\Messenger\Tests\MessengerTestCase;

class BotActionHandlerTest extends MessengerTestCase
{
    /** @test */
    public function it_can_call_to_release_cooldown()
    {
        $handler = new FunBotHandler;

        $this->assertFalse($handler->shouldReleaseCooldown());

        $handler->releaseCooldown();

        $this->assertTrue($handler->shouldReleaseCooldown());
    }

    /** @test */
    public function it_has_rules()
    {
        $overrides = [
            'test' => ['required', 'array', 'min:1'],
            'test.*' => ['required', 'string'],
            'special' => ['nullable', 'boolean'],
        ];

        $this->assertSame($overrides, (new FunBotHandler)->rules());
        $this->assertSame([], (new SillyBotHandler)->rules());
    }

    /** @test */
    public function it_has_error_messages()
    {
        $overrides = [
            'test' => 'Test Needed.',
            'test.*' => 'Tests must be string.',
        ];

        $this->assertSame($overrides, (new FunBotHandler)->errorMessages());
        $this->assertSame([], (new SillyBotHandler)->errorMessages());
    }

    /** @test */
    public function it_can_be_handled()
    {
        $this->expectException(BotException::class);

        (new BrokenBotHandler)->handle();
    }

    /** @test */
    public function it_can_serialize_payload()
    {
        $handler = new FunBotHandler;

        $this->assertNull($handler->serializePayload(null));
        $this->assertSame('{"test":true}', $handler->serializePayload(['test' => true]));
    }

    /** @test */
    public function it_can_access_messenger_composer_and_sets_bot_as_provider()
    {
        $thread = Thread::factory()->group()->make();
        $message = Message::factory()->make();
        $bot = Bot::factory()->for($thread)->make();
        $action = BotAction::factory()->for($bot)->make();
        $action->setRelation('bot', $bot);
        $handler = (new FunBotHandler)->setDataForHandler($thread, $action, $message);

        $composer = $handler->composer();

        $this->assertInstanceOf(MessengerComposer::class, $composer);
        $this->assertSame(Messenger::getProvider(), $bot);
    }

    /** @test */
    public function it_can_get_actions_payload()
    {
        $thread = Thread::factory()->group()->make();
        $message = Message::factory()->make();
        $action = BotAction::factory()
            ->payload('{"test":{"test":"fun","more":"yes","ok":"dokie"},"special":true}')
            ->make();
        $emptyAction = BotAction::factory()->make();

        $emptyHandler = (new FunBotHandler)->setDataForHandler($thread, $emptyAction, $message);
        $handler = (new FunBotHandler)->setDataForHandler($thread, $action, $message);

        $this->assertNull($emptyHandler->getPayload());
        $this->assertNull($emptyHandler->getPayload('unknown'));
        $this->assertTrue($handler->getPayload('special'));
        $this->assertSame('fun', $handler->getPayload('test')['test']);
        $this->assertSame([
            'test' => [
                'test' => 'fun',
                'more' => 'yes',
                'ok' => 'dokie',
            ],
            'special' => true,
        ], $handler->getPayload());
    }

    /** @test */
    public function it_can_get_actions_parsed_message()
    {
        $thread = Thread::factory()->group()->make();
        $message = Message::factory()->body('!command Do Something Fun')->make();
        $action = BotAction::factory()->make();

        $handler = (new FunBotHandler)->setDataForHandler($thread, $action, $message, '!command');
        $emptyHandler = (new FunBotHandler)->setDataForHandler($thread, $action, $message, '!command Do Something Fun');

        $this->assertSame('Do Something Fun', $handler->getParsedMessage());
        $this->assertSame('do something fun', $handler->getParsedMessage(true));
        $this->assertSame(['Do', 'Something', 'Fun'], $handler->getParsedWords());
        $this->assertSame(['do', 'something', 'fun'], $handler->getParsedWords(true));
        $this->assertNull($emptyHandler->getParsedMessage());
        $this->assertNull($emptyHandler->getParsedMessage(true));
        $this->assertNull($emptyHandler->getParsedWords());
        $this->assertNull($emptyHandler->getParsedWords(true));
    }

    /** @test */
    public function it_can_get_actions_parsed_message_when_no_trigger()
    {
        $thread = Thread::factory()->group()->make();
        $message = Message::factory()->body('!command Do Something Fun')->make();
        $action = BotAction::factory()->make();

        $handler = (new FunBotHandler)->setDataForHandler($thread, $action, $message);

        $this->assertSame('!command Do Something Fun', $handler->getParsedMessage());
        $this->assertSame('!command do something fun', $handler->getParsedMessage(true));
        $this->assertSame(['!command', 'Do', 'Something', 'Fun'], $handler->getParsedWords());
        $this->assertSame(['!command', 'do', 'something', 'fun'], $handler->getParsedWords(true));
    }
}
