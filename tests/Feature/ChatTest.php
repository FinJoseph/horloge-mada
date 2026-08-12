<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class ChatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('chat:messages');
        Cache::forget('chat:online');
        Cache::forget('chat:typing');
    }

    public function test_page_renders_chat_features(): void
    {
        Cache::put('chat:online', [
            'u1' => ['user' => 'Alfa', 'ts' => time()],
            'u2' => ['user' => 'Bravo', 'ts' => time()],
        ], 3600);

        Livewire::test('chat')
            ->set('username', 'Tia')
            ->set('message', 'Salut')
            ->call('send')
            ->assertSee('chatComposer()', false)
            ->assertSee('emoji-picker', false)
            ->assertSee('msg-menu-wrap', false)
            ->assertSee('chat-tick-read', false)
            ->assertSee('textarea', false);

        $this->get('/')
            ->assertOk()
            ->assertSee('chatComposer()')
            ->assertSee('emoji-picker', false)
            ->assertSee('msg-menu-wrap', false)
            ->assertSee('chat-config', false);
    }

    public function test_first_send_emits_join_system_message(): void
    {
        Livewire::test('chat')
            ->set('username', 'Vony')
            ->set('message', 'Manao ahoana')
            ->call('send');

        $messages = Cache::get('chat:messages');
        $this->assertCount(2, $messages);
        $this->assertSame('system', $messages[0]['type']);
        $this->assertStringContainsString('Vony', $messages[0]['text']);
    }

    public function test_rename_emits_system_message(): void
    {
        $component = Livewire::test('chat')
            ->set('username', 'Ancien')
            ->set('message', 'premier')
            ->call('send');

        $component
            ->set('username', 'Nouveau')
            ->set('message', 'deuxieme')
            ->call('send');

        $messages = Cache::get('chat:messages');
        $this->assertCount(4, $messages);
        $renamed = collect($messages)->first(fn ($m) => ($m['type'] ?? '') === 'system'
            && str_contains($m['text'], 'Ancien') && str_contains($m['text'], 'Nouveau'));
        $this->assertNotNull($renamed);
    }

    public function test_reply_attaches_quoted_message(): void
    {
        Livewire::test('chat')
            ->set('username', 'Mina')
            ->set('message', 'Question')
            ->call('send');

        $messages = Cache::get('chat:messages');
        $target = $messages[1];

        Livewire::test('chat')
            ->set('message', 'Reponse')
            ->set('replyId', $target['id'])
            ->call('send');

        $messages = Cache::get('chat:messages');
        $last = $messages[count($messages) - 1];
        $this->assertSame('Mina', $last['reply']['user']);
        $this->assertSame('Question', $last['reply']['text']);
    }

    public function test_reply_with_unknown_id_is_null(): void
    {
        Livewire::test('chat')
            ->set('message', 'Sans cible')
            ->set('replyId', 'inexistant')
            ->call('send');

        $messages = Cache::get('chat:messages');
        $last = $messages[count($messages) - 1];
        $this->assertNull($last['reply']);
    }

    public function test_forward_copies_message_and_marks_forwarded(): void
    {
        Livewire::test('chat')
            ->set('username', 'Solo')
            ->set('message', 'A transférer')
            ->call('send');

        $messages = Cache::get('chat:messages');
        $target = $messages[1];

        Livewire::test('chat')
            ->set('username', 'Solo')
            ->call('forwardMessage', $target['id']);

        $messages = Cache::get('chat:messages');
        $last = $messages[count($messages) - 1];
        $this->assertTrue($last['forwarded']);
        $this->assertSame('A transférer', $last['text']);
        $this->assertNotSame($target['id'], $last['id']);
    }

    public function test_update_message_edits_own_message(): void
    {
        Livewire::test('chat')
            ->set('username', 'Zo')
            ->set('message', 'Avant')
            ->call('send');

        $messages = Cache::get('chat:messages');
        $target = $messages[1];

        Livewire::test('chat')
            ->set('username', 'Zo')
            ->call('updateMessage', $target['id'], 'Après correction');

        $messages = Cache::get('chat:messages');
        $updated = collect($messages)->firstWhere('id', $target['id']);
        $this->assertSame('Après correction', $updated['text']);
        $this->assertTrue($updated['edited']);
    }

    public function test_update_message_rejects_foreign_message(): void
    {
        $senderId = Livewire::test('chat')->instance()->senderId;

        Cache::put('chat:messages', [[
            'id' => 'x1',
            'uid' => 'someone-else',
            'user' => 'Zo',
            'text' => 'Avant',
            'time' => now()->timezone(config('shift.timezone'))->format('H:i'),
            'ts' => now()->timestamp,
            'reactions' => [],
        ]], 3600);

        $this->assertNotSame($senderId, 'someone-else');

        Livewire::test('chat')
            ->call('updateMessage', 'x1', 'Hack');

        $messages = Cache::get('chat:messages');
        $updated = collect($messages)->firstWhere('id', 'x1');
        $this->assertSame('Avant', $updated['text']);
        $this->assertArrayNotHasKey('edited', $updated);
    }

    public function test_send_media_stores_media_message(): void
    {
        Livewire::test('chat')
            ->set('username', 'Kely')
            ->call('sendMedia', 'https://example.com/cat.mp4', 'https://example.com/cat-preview.gif', 'gif', 'Un chat');

        $messages = Cache::get('chat:messages');
        $last = $messages[count($messages) - 1];
        $this->assertSame('gif', $last['type']);
        $this->assertSame('https://example.com/cat.mp4', $last['media']['url']);
        $this->assertSame('Un chat', $last['media']['alt']);
    }

    public function test_trending_media_is_empty_without_klipy_key(): void
    {
        config(['klipy.api_key' => '']);

        Livewire::test('chat')
            ->call('trendingMedia', 'gif')
            ->assertNotSet('klipyOn', true);

        $this->assertSame([], Livewire::test('chat')->instance()->trendingMedia('gif'));
    }

    public function test_create_poll_stores_poll_message(): void
    {
        Livewire::test('chat')
            ->set('username', 'Rakoto')
            ->call('createPoll', 'Manao ahoana ?', ['Tsara', 'Mety']);

        $messages = Cache::get('chat:messages');
        $poll = collect($messages)->firstWhere('type', 'poll');
        $this->assertNotNull($poll);
        $this->assertSame('Manao ahoana ?', $poll['poll']['question']);
        $this->assertCount(2, $poll['poll']['options']);
        $this->assertSame(['Tsara', 'Mety'], array_column($poll['poll']['options'], 'label'));
    }

    public function test_create_poll_requires_two_options(): void
    {
        Cache::forget('chat:messages');

        Livewire::test('chat')
            ->call('createPoll', 'Une question', ['Un seul']);

        $this->assertSame([], Cache::get('chat:messages', []));
    }

    public function test_create_poll_rejects_empty_question(): void
    {
        Cache::forget('chat:messages');

        Livewire::test('chat')
            ->call('createPoll', '', ['Un', 'Deux']);

        $this->assertSame([], Cache::get('chat:messages', []));
    }

    public function test_vote_poll_tallies_and_dedupes(): void
    {
        Cache::forget('chat:messages');

        $creator = Livewire::test('chat')
            ->set('username', 'Rakoto')
            ->call('createPoll', 'Question', ['A', 'B']);
        $sid = $creator->instance()->senderId;

        $poll = collect(Cache::get('chat:messages'))->firstWhere('type', 'poll');
        $msgId = $poll['id'];
        $optA = $poll['poll']['options'][0]['id'];
        $optB = $poll['poll']['options'][1]['id'];

        Livewire::test('chat')->set('senderId', $sid)->call('votePoll', $msgId, $optA);

        $poll = collect(Cache::get('chat:messages'))->firstWhere('type', 'poll');
        $this->assertCount(1, $poll['poll']['options'][0]['votes']);
        $this->assertCount(0, $poll['poll']['options'][1]['votes']);

        Livewire::test('chat')->set('senderId', $sid)->call('votePoll', $msgId, $optA);
        Livewire::test('chat')->set('senderId', $sid)->call('votePoll', $msgId, $optB);

        $poll = collect(Cache::get('chat:messages'))->firstWhere('type', 'poll');
        $this->assertCount(1, $poll['poll']['options'][0]['votes']);
        $this->assertCount(1, $poll['poll']['options'][1]['votes']);
    }

    public function test_close_poll_only_by_author(): void
    {
        Cache::forget('chat:messages');

        $creator = Livewire::test('chat')
            ->set('username', 'Rakoto')
            ->call('createPoll', 'Question', ['A', 'B']);
        $sid = $creator->instance()->senderId;

        $poll = collect(Cache::get('chat:messages'))->firstWhere('type', 'poll');
        $msgId = $poll['id'];

        Livewire::test('chat')->set('senderId', 'e7a4cfea3d5b4c10')->call('closePoll', $msgId);
        $poll = collect(Cache::get('chat:messages'))->firstWhere('type', 'poll');
        $this->assertFalse($poll['poll']['closed']);

        Livewire::test('chat')->set('senderId', $sid)->call('closePoll', $msgId);
        $poll = collect(Cache::get('chat:messages'))->firstWhere('type', 'poll');
        $this->assertTrue($poll['poll']['closed']);
    }

    public function test_day_label_today_and_yesterday(): void
    {
        $tz = config('shift.timezone');
        $today = Carbon::now($tz);
        $yesterday = Carbon::now($tz)->subDay();
        $older = Carbon::createFromFormat('Y-m-d H:i', '2026-01-02 10:00', $tz);

        $component = Livewire::test('chat')->instance();

        $this->assertSame(__('chat_today'), $component->dayLabel($today->timestamp));
        $this->assertSame(__('chat_yesterday'), $component->dayLabel($yesterday->timestamp));
        $this->assertStringContainsString('2', $component->dayLabel($older->timestamp));
    }
}
