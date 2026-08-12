<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

new class extends Component
{
    public string $username = '';

    public string $message = '';

    public string $senderId = '';

    public string $replyId = '';

    public array $messages = [];

    public array $typing = [];

    public int $onlineCount = 1;

    public bool $klipyOn = false;

    public string $pollQuestion = '';

    public array $pollOptions = [];

    public string $announceText = '';

    public bool $announceComposer = false;

    public function mount(): void
    {
        $this->username = (string) session('chat_user', '');
        $this->senderId = $this->clientId();
        $this->messages = $this->load();
        $this->klipyOn = $this->klipyConfigured();
        $this->syncPresence();
        $this->maybeJoinSystem();
    }

    public function send(): void
    {
        $this->validate([
            'message' => ['required', 'string', 'max:280'],
            'username' => ['nullable', 'string', 'max:30'],
        ]);

        $user = trim($this->username) !== '' ? trim($this->username) : __('chat_anonymous');
        $this->username = $user;
        session(['chat_user' => $user]);

        $messages = $this->load();
        $messages = array_merge($messages, $this->systemEvents($user));
        $messages[] = [
            'id' => uniqid('', true),
            'uid' => $this->senderId,
            'user' => $user,
            'text' => trim($this->message),
            'time' => now()->timezone(config('shift.timezone'))->format('H:i'),
            'ts' => now()->timestamp,
            'reactions' => [],
            'reply' => $this->findReply(),
        ];

        $this->messages = collect($messages)->slice(-50)->values()->all();
        $this->message = '';
        $this->replyId = '';

        Cache::put('chat:messages', $this->messages, $this->ttl());

        $this->clearTyping();
        $this->syncPresence();
    }

    public function refreshMessages(): void
    {
        $this->messages = $this->load();
        $this->syncPresence();
    }

    public function typingIndicator(): void
    {
        $typing = Cache::get('chat:typing', []);
        $typing[$this->senderId] = ['user' => $this->user(), 'ts' => now()->timestamp];
        Cache::put('chat:typing', $typing, $this->ttl());

        $this->syncPresence();
    }

    public function react(string $id, string $emoji): void
    {
        if ($emoji === '') {
            return;
        }

        $messages = $this->load();

        foreach ($messages as &$m) {
            if (($m['id'] ?? null) !== $id) {
                continue;
            }

            $m['reactions'] ??= [];
            $list = $m['reactions'][$emoji] ?? [];

            if (in_array($this->senderId, $list, true)) {
                $list = array_values(array_diff($list, [$this->senderId]));
            } else {
                $list[] = $this->senderId;
            }

            if ($list !== []) {
                $m['reactions'][$emoji] = $list;
            } else {
                unset($m['reactions'][$emoji]);
            }

            break;
        }
        unset($m);

        Cache::put('chat:messages', $messages, $this->ttl());
        $this->messages = $messages;
    }

    public function deleteMessage(string $id): void
    {
        $messages = collect($this->load())
            ->filter(fn ($m) => ($m['id'] ?? null) !== $id || ($m['uid'] ?? null) !== $this->senderId)
            ->values()
            ->all();

        Cache::put('chat:messages', $messages, $this->ttl());
        $this->messages = $messages;
    }

    public function updateMessage(string $id, string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $messages = $this->load();

        foreach ($messages as &$m) {
            if (($m['id'] ?? null) !== $id || ($m['uid'] ?? null) !== $this->senderId) {
                continue;
            }
            if (($m['type'] ?? 'text') !== 'text') {
                return;
            }

            $m['text'] = mb_substr($text, 0, 280);
            $m['edited'] = true;
            break;
        }
        unset($m);

        Cache::put('chat:messages', $messages, $this->ttl());
        $this->messages = $messages;
    }

    public function forwardMessage(string $id): void
    {
        $messages = $this->load();

        foreach ($messages as $m) {
            if (($m['id'] ?? null) !== $id) {
                continue;
            }

            $new = $m;
            $new['id'] = uniqid('', true);
            $new['uid'] = $this->senderId;
            $new['user'] = $this->user();
            $new['time'] = now()->timezone(config('shift.timezone'))->format('H:i');
            $new['ts'] = now()->timestamp;
            $new['forwarded'] = true;
            $new['reactions'] = [];
            unset($new['reply']);
            $messages[] = $new;
            break;
        }

        $this->messages = collect($messages)->slice(-50)->values()->all();
        Cache::put('chat:messages', $this->messages, $this->ttl());
        $this->clearTyping();
        $this->syncPresence();
    }

    public function sendMedia(string $url, string $preview = '', string $type = 'gif', string $alt = ''): void
    {
        if ($url === '') {
            return;
        }
        if (! in_array($type, ['gif', 'sticker'], true)) {
            $type = 'gif';
        }

        $messages = $this->load();
        $messages[] = [
            'id' => uniqid('', true),
            'uid' => $this->senderId,
            'user' => $this->user(),
            'type' => $type,
            'media' => ['url' => $url, 'preview' => $preview, 'alt' => mb_substr($alt, 0, 80)],
            'time' => now()->timezone(config('shift.timezone'))->format('H:i'),
            'ts' => now()->timestamp,
            'reactions' => [],
        ];

        $this->messages = collect($messages)->slice(-50)->values()->all();
        Cache::put('chat:messages', $this->messages, $this->ttl());
        $this->clearTyping();
        $this->syncPresence();
    }

    public function trendingMedia(string $kind = 'gif'): array
    {
        return $this->klipyFetch('trending', '', $kind);
    }

    public function searchMedia(string $query, string $kind = 'gif'): array
    {
        return $this->klipyFetch('search', trim(mb_substr($query, 0, 60)), $kind);
    }

    public function createPoll(?string $question = null, ?array $options = null): void
    {
        $question = trim(mb_substr($question ?? $this->pollQuestion, 0, 160));
        $options = array_values($options ?? $this->pollOptions);

        if ($question === '' || count($options) < 2) {
            return;
        }

        $messages = $this->load();
        $messages[] = [
            'id' => uniqid('', true),
            'uid' => $this->senderId,
            'user' => $this->user(),
            'type' => 'poll',
            'poll' => [
                'question' => $question,
                'closed' => false,
                'options' => array_map(
                    fn ($label) => ['id' => uniqid('', true), 'label' => mb_substr(trim((string) $label), 0, 60), 'votes' => []],
                    $options,
                ),
            ],
            'time' => now()->timezone(config('shift.timezone'))->format('H:i'),
            'ts' => now()->timestamp,
            'reactions' => [],
        ];

        $this->messages = collect($messages)->slice(-50)->values()->all();
        Cache::put('chat:messages', $this->messages, $this->ttl());
        $this->pollQuestion = '';
        $this->pollOptions = [];
        $this->clearTyping();
        $this->syncPresence();
    }

    public function votePoll(string $msgId, string $optionId): void
    {
        $messages = $this->load();

        foreach ($messages as &$m) {
            if (($m['id'] ?? null) !== $msgId || ($m['type'] ?? '') !== 'poll' || ! empty($m['poll']['closed'])) {
                continue;
            }

            foreach ($m['poll']['options'] as &$opt) {
                if (($opt['id'] ?? null) !== $optionId) {
                    continue;
                }

                $votes = $opt['votes'] ?? [];

                if (in_array($this->senderId, $votes, true)) {
                    // Same choice clicked again → remove the vote
                    $opt['votes'] = array_values(array_diff($votes, [$this->senderId]));
                } else {
                    // One person = one vote : remove from every other option, then vote here
                    foreach ($m['poll']['options'] as &$other) {
                        $other['votes'] = array_values(array_diff($other['votes'] ?? [], [$this->senderId]));
                    }
                    unset($other);

                    $opt['votes'] = array_values(array_unique([...$votes, $this->senderId]));
                }

                break;
            }
            unset($opt);

            break;
        }
        unset($m);

        Cache::put('chat:messages', $messages, $this->ttl());
        $this->messages = $messages;
    }

    public function closePoll(string $msgId): void
    {
        $messages = $this->load();

        foreach ($messages as &$m) {
            if (($m['id'] ?? null) === $msgId && ($m['uid'] ?? null) === $this->senderId && ($m['type'] ?? '') === 'poll') {
                $m['poll']['closed'] = true;
                break;
            }
        }
        unset($m);

        Cache::put('chat:messages', $messages, $this->ttl());
        $this->messages = $messages;
    }

    public function sendAnnouncement(): void
    {
        $text = trim(mb_substr($this->announceText, 0, 500));
        if ($text === '') {
            return;
        }

        $messages = $this->load();
        $messages[] = [
            'id' => uniqid('ann', true),
            'type' => 'announce',
            'uid' => $this->senderId,
            'user' => $this->user(),
            'text' => $text,
            'time' => now()->timezone(config('shift.timezone'))->format('H:i'),
            'ts' => now()->timestamp,
        ];

        $this->messages = collect($messages)->slice(-50)->values()->all();
        Cache::put('chat:messages', $this->messages, $this->ttl());
        $this->announceText = '';
        $this->announceComposer = false;
        $this->clearTyping();
        $this->syncPresence();
    }

    public function dayLabel(int $ts): string
    {
        $tz = config('shift.timezone');
        $d = Carbon::createFromTimestamp($ts)->timezone($tz);
        $today = Carbon::now($tz)->toDateString();
        $yesterday = Carbon::now($tz)->subDay()->toDateString();

        if ($d->toDateString() === $today) {
            return __('chat_today');
        }
        if ($d->toDateString() === $yesterday) {
            return __('chat_yesterday');
        }

        return $d->translatedFormat('l j F');
    }

    protected function user(): string
    {
        return trim($this->username) !== '' ? trim($this->username) : __('chat_anonymous');
    }

    protected function clientId(): string
    {
        return substr(md5((string) session()->getId()), 0, 12);
    }

    protected function load(): array
    {
        return Cache::get('chat:messages', []);
    }

    protected function ttl(): int
    {
        $tz = config('shift.timezone');
        $now = Carbon::now($tz);
        $end = Carbon::now($tz)->setTimeFromTimeString(config('shift.end'));

        if ($now->gte($end)) {
            $end = $end->addDay();
        }

        return max(60, $now->diffInSeconds($end));
    }

    protected function heartbeat(): void
    {
        $online = Cache::get('chat:online', []);
        $online[$this->senderId] = ['user' => $this->user(), 'ts' => now()->timestamp];
        Cache::put('chat:online', $online, $this->ttl());
    }

    protected function syncPresence(): void
    {
        $this->heartbeat();

        $now = now()->timestamp;

        $online = collect(Cache::get('chat:online', []))
            ->filter(fn ($d) => $now - ($d['ts'] ?? 0) < 35)
            ->values();

        $this->onlineCount = max(1, $online->count());

        $typing = collect(Cache::get('chat:typing', []))
            ->filter(fn ($d, $uid) => $uid !== $this->senderId && $now - ($d['ts'] ?? 0) < 5)
            ->pluck('user')
            ->values();

        $this->typing = $typing->all();
    }

    protected function clearTyping(): void
    {
        $typing = Cache::get('chat:typing', []);
        unset($typing[$this->senderId]);
        Cache::put('chat:typing', $typing, $this->ttl());
    }

    protected function system(string $text): array
    {
        return [
            'id' => uniqid('sys', true),
            'type' => 'system',
            'text' => $text,
            'time' => now()->timezone(config('shift.timezone'))->format('H:i'),
            'ts' => now()->timestamp,
        ];
    }

    protected function systemEvents(string $user): array
    {
        $key = 'chat:names:'.$this->senderId;
        $prev = (string) Cache::get($key, '');
        $messages = [];

        if ($prev === '') {
            $messages[] = $this->system(__('chat_join', ['name' => $user]));
        } elseif ($prev !== $user) {
            $messages[] = $this->system(__('chat_renamed', ['old' => $prev, 'new' => $user]));
        }

        Cache::put($key, $user, $this->ttl());

        return $messages;
    }

    protected function maybeJoinSystem(): void
    {
        if (trim($this->username) === '') {
            return;
        }

        $key = 'chat:names:'.$this->senderId;

        if (Cache::has($key)) {
            return;
        }

        $messages = $this->load();
        $messages[] = $this->system(__('chat_join', ['name' => $this->username]));

        $this->messages = collect($messages)->slice(-50)->values()->all();
        Cache::put('chat:messages', $this->messages, $this->ttl());
        Cache::put($key, $this->username, $this->ttl());
    }

    protected function findReply(): ?array
    {
        if ($this->replyId === '') {
            return null;
        }

        foreach ($this->load() as $m) {
            if (($m['id'] ?? null) !== $this->replyId) {
                continue;
            }

            return [
                'id' => $m['id'],
                'user' => $m['user'] ?? '',
                'text' => in_array($m['type'] ?? 'text', ['text', 'announce'], true)
                    ? ($m['text'] ?? '')
                    : (($m['media']['alt'] ?? '') !== '' ? $m['media']['alt'] : ($m['type'] ?? 'gif')),
            ];
        }

        return null;
    }

    protected function klipyConfigured(): bool
    {
        return (string) config('klipy.api_key') !== '';
    }

    protected function klipyFetch(string $action, string $query, string $kind): array
    {
        if (! $this->klipyConfigured()) {
            return [];
        }

        $kind = $kind === 'sticker' ? 'sticker' : 'gif';
        $cacheKey = 'chat:klipy:'.$action.':'.$kind.':'.md5($query);

        return Cache::remember($cacheKey, 600, function () use ($action, $query, $kind) {
            try {
                $client = app(\Klipy\Klipy::class);
                $resource = $kind === 'sticker' ? $client->stickers() : $client->gifs();
                $page = $action === 'search'
                    ? $resource->search($query, perPage: 30)
                    : $resource->trending(perPage: 30);

                $out = [];

                foreach ($page->items as $item) {
                    $url = $item['files']['mp4']['url']
                        ?? $item['files']['gif']['url']
                        ?? $item['media_formats']['mp4']['url']
                        ?? '';
                    $preview = $item['preview']['url']
                        ?? $item['files']['preview']['url']
                        ?? $item['files']['tinygif']['url']
                        ?? $item['files']['gif']['url']
                        ?? $item['media_formats']['tinygif']['url']
                        ?? $url;
                    $alt = $item['title'] ?? $item['slug'] ?? $item['h1_title'] ?? '';

                    if ($url !== '') {
                        $out[] = ['url' => $url, 'preview' => $preview, 'alt' => $alt];
                    }
                }

                return $out;
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    public function trendingStickers(): array
    {
        return \App\Support\Tenor::trending();
    }

    public function searchStickers(string $query): array
    {
        return \App\Support\Tenor::search($query);
    }

    protected function renderText(string $text): string
    {
        $text = e($text);
        $text = preg_replace('/@([\p{L}\p{N}_-]+)/u', '<span class="chat-mention">@$1</span>', $text) ?? $text;
        $text = preg_replace(
            '~(https?://[^\s<]+)~',
            '<a href="$1" target="_blank" rel="noopener noreferrer" class="chat-link">$1</a>',
            $text
        ) ?? $text;

        return preg_replace_callback(
            '/\[\[stk:([0-9a-f]+)\]\]/',
            fn ($m) => '<img class="stk-inline align-middle" src="https://fonts.gstatic.com/s/e/notoemoji/latest/'.$m[1].'/512.gif" alt="sticker" loading="lazy">',
            $text
        ) ?? $text;
    }
};
?>

<div
    wire:poll.3s="refreshMessages"
    x-data="chatComposer()"
    x-init="watchScroll()"
    class="glass chat-card flex max-h-[85vh] h-[min(76vh,26rem)] sm:h-[30rem] flex-col overflow-hidden rounded-2xl"
>
    <div hidden id="chat-config">{{ json_encode(['klipy' => $this->klipyOn, 'tenor' => \App\Support\Tenor::configured(), 'delete_confirm' => __('chat_delete_confirm'), 'sender_id' => $this->senderId]) }}</div>

    <header class="flex shrink-0 items-center justify-between gap-3 border-b border-white/10 px-4 py-3 sm:px-5">
        <div class="flex min-w-0 items-center gap-3">
            <div class="relative shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-cyan-500 to-fuchsia-500">
                    <x-icon name="message-square" class="h-5 w-5" />
                </div>
                <span class="dot absolute -bottom-0.5 -right-0.5 border-2 border-[#0b1026]" :class="liveDot()"></span>
            </div>
            <div class="min-w-0">
                <h2 class="truncate text-sm font-bold uppercase tracking-widest text-white/80">{{ __('chat_title') }}</h2>
                <p class="truncate text-xs text-white/50">
                    @if (count($typing) > 0)
                        <span class="text-cyan-300">
                            @if (count($typing) === 1)
                                {{ __('chat_typing_one', ['name' => $typing[0]]) }}
                            @else
                                {{ __('chat_typing_many', ['names' => implode(', ', array_slice($typing, 0, 2)).(count($typing) > 2 ? '…' : '')]) }}
                            @endif
                        </span>
                    @else
                        <span class="text-emerald-300">{{ $onlineCount }} {{ __('chat_online') }}</span>
                    @endif
                    @if ($username !== '')
                        <span class="text-white/35">· {{ $username }}</span>
                    @endif
                </p>
            </div>
        </div>
        <div class="flex shrink-0 items-center gap-1">
            <button
                type="button"
                @click="toggleSearch()"
                class="rounded-lg p-2 text-white/60 transition hover:bg-white/10"
                :class="searchOpen ? 'bg-white/15 text-white' : ''"
            >
                <x-icon name="search" class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="soundOn = !soundOn"
                class="rounded-lg p-2 text-white/60 transition hover:bg-white/10"
                :class="soundOn ? '' : 'text-white/30'"
            >
                <x-icon name="bell" x-show="soundOn" class="h-4 w-4" />
                <x-icon name="bell-off" x-show="!soundOn" class="h-4 w-4" />
            </button>
            <span class="hidden rounded-full bg-white/10 px-3 py-1 text-xs text-white/50 sm:block">{{ __('chat_cleared_at', ['time' => config('shift.end')]) }}</span>
        </div>
    </header>

    <div x-show="searchOpen" x-transition class="shrink-0 border-b border-white/10 px-3 py-2 sm:px-4">
        <div class="flex items-center gap-2">
            <x-icon name="search" class="h-4 w-4 shrink-0 text-white/40" />
            <input
                type="text"
                x-ref="searchInput"
                x-model="searchQ"
                @input="applySearch()"
                class="chat-input w-full bg-transparent text-sm outline-none placeholder:text-white/40"
                placeholder="{{ __('chat_search_placeholder') }}"
            >
            <button type="button" @click="toggleSearch()" class="shrink-0 rounded-lg p-1 text-white/50 hover:bg-white/10">
                <x-icon name="x" class="h-4 w-4" />
            </button>
        </div>
        <p x-show="searchEmpty" x-transition class="mt-1 text-xs text-white/40">{{ __('chat_no_results') }}</p>
    </div>

    <div class="relative min-h-0 flex-1">
        <div x-ref="scroll" @click="menuId = null; picker = false" class="chat-scroll absolute inset-0 overflow-y-auto px-3 py-4 sm:px-5">
            <div class="flex flex-col gap-2.5">
                @php $prevDay = null; @endphp
                @forelse ($messages as $msg)
                    @php
                        $type = $msg['type'] ?? 'text';
                        $mine = ($msg['uid'] ?? '') === $this->senderId;
                    @endphp
                    @if ($type === 'system')
                        <div wire:key="sys-{{ $msg['id'] }}" class="animate-fade-up flex justify-center py-0.5">
                            <span class="sys-pill">{{ $msg['text'] }}</span>
                        </div>
                    @else
                        @php
                            $day = \Illuminate\Support\Carbon::createFromTimestamp((int) ($msg['ts'] ?? 0))->timezone(config('shift.timezone'))->toDateString();
                        @endphp
                        @if ($day !== $prevDay)
                            <div class="day-sep"><span>{{ $this->dayLabel((int) ($msg['ts'] ?? 0)) }}</span></div>
                        @endif
                        @php $prevDay = $day; @endphp

                        @if ($type === 'announce')
                            <div
                                wire:key="msg-{{ $msg['id'] }}"
                                class="msg-row flex items-end justify-center gap-2 animate-fade-up"
                                data-id="{{ $msg['id'] }}"
                                data-uid="{{ $msg['uid'] ?? '' }}"
                                data-user="{{ $msg['user'] ?? '' }}"
                                data-text="{{ $msg['text'] }}"
                            >
                                <div class="msg-col flex min-w-0 max-w-[92%] flex-col">
                                    <div class="flex items-end gap-1.5">
                                        <div class="msg-menu-wrap msg-menu-wrap-in">
                                            <button
                                                type="button"
                                                @click.stop="toggleMenu('{{ $msg['id'] }}')"
                                                class="msg-menu-btn"
                                                aria-label="…"
                                            >
                                                <x-icon name="ellipsis-vertical" class="h-4 w-4" />
                                            </button>
                                            <div x-show="menuId === '{{ $msg['id'] }}'" x-transition class="msg-menu" @click.stop>
                                                <button type="button" @click.stop="startReply('{{ $msg['id'] }}')"><x-icon name="reply" class="h-3.5 w-3.5" /> {{ __('chat_reply') }}</button>
                                                <button type="button" @click.stop="reactFromMenu('{{ $msg['id'] }}', '❤️')">❤️</button>
                                                <button type="button" @click.stop="reactFromMenu('{{ $msg['id'] }}', '👍')">👍</button>
                                                <button type="button" @click.stop="reactFromMenu('{{ $msg['id'] }}', '😂')">😂</button>
                                                <button type="button" @click.stop="copyRow('{{ $msg['id'] }}')"><x-icon name="check" class="h-3.5 w-3.5" /> {{ __('chat_copy') }}</button>
                                                @if ($mine)
                                                    <button type="button" @click.stop="deleteMsg('{{ $msg['id'] }}')"><x-icon name="trash" class="h-3.5 w-3.5" /> {{ __('chat_delete') }}</button>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="announce-banner">
                                            <div class="announce-icon"><x-icon name="megaphone" class="h-4 w-4" /></div>
                                            <div class="min-w-0 flex-1">
                                                <div class="announce-head">{{ __('chat_announce_label') }} · {{ $msg['user'] }}</div>
                                                <p class="announce-text">{!! $this->renderText($msg['text']) !!}</p>
                                                <span class="chat-time">{{ $msg['time'] }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center justify-center gap-1">
                                        @foreach (($msg['reactions'] ?? []) as $emoji => $uids)
                                            <button
                                                type="button"
                                                @click="$wire.react('{{ $msg['id'] }}', '{{ $emoji }}')"
                                                class="reaction-pill {{ in_array($this->senderId, $uids, true) ? 'reaction-pill-active' : '' }}"
                                            >
                                                <span>{{ $emoji }}</span>
                                                <span class="reaction-count">{{ count($uids) }}</span>
                                            </button>
                                        @endforeach
                                        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                            <button type="button" @click="open = !open" class="react-plus" title="+">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                                            </button>
                                            <div x-show="open" x-transition class="quick-react">
                                                @foreach (['❤️', '👍', '😂', '😮', '😢'] as $re)
                                                    <button type="button" @click="$wire.react('{{ $msg['id'] }}', '{{ $re }}'); open = false" x-text="'{{ $re }}'"></button>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @elseif ($type === 'poll')
                            @php $poll = $msg['poll'] ?? null; @endphp
                            @if ($poll)
                                <div wire:key="msg-{{ $msg['id'] }}" class="msg-row flex items-end gap-2 animate-fade-up {{ $mine ? 'justify-end' : '' }}" data-uid="{{ $msg['uid'] ?? '' }}">
                                    @if (!$mine)
                                        <div class="mb-6 mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-fuchsia-500 to-cyan-500 text-xs font-bold">{{ mb_strtoupper(mb_substr($msg['user'], 0, 1)) }}</div>
                                    @endif
                                    <div class="poll-card w-full max-w-[340px] {{ $mine ? 'poll-card-out' : '' }}">
                                        <div class="poll-question">
                                            <x-icon name="chart-bar" class="h-4 w-4 shrink-0" />
                                            <span>{{ $poll['question'] }}</span>
                                        </div>
                                        @php
                                            $total = collect($poll['options'] ?? [])->sum(fn ($o) => count($o['votes'] ?? []));
                                            $closed = !empty($poll['closed']);
                                            $maxVotes = $total > 0 ? max(array_map(fn ($o) => count($o['votes'] ?? []), $poll['options'] ?? [])) : 0;
                                        @endphp
                                        @foreach (($poll['options'] ?? []) as $opt)
                                            @php
                                                $votes = count($opt['votes'] ?? []);
                                                $pct = $total > 0 ? ($votes / $total) * 100 : 0.0;
                                                $pctLabel = $total > 0 ? rtrim(rtrim(number_format($pct, 3, ',', ''), '0'), ',') : '0';
                                                $voted = in_array($this->senderId, $opt['votes'] ?? [], true);
                                                $winner = $closed && $maxVotes > 0 && $votes === $maxVotes;
                                            @endphp
                                            <button
                                                type="button"
                                                wire:click="votePoll('{{ $msg['id'] }}', '{{ $opt['id'] }}')"
                                                class="poll-option {{ $voted ? 'poll-option-active' : '' }} {{ $winner ? 'poll-option-winner' : '' }}"
                                                {{ $closed ? 'disabled' : '' }}
                                            >
                                                <span class="poll-option-label">{{ $winner ? '🏆 ' : '' }}{{ $opt['label'] }}</span>
                                                <span class="poll-bar"><span class="poll-bar-fill" style="width: {{ number_format($pct, 3, '.', '') }}%"></span></span>
                                                <span class="poll-pct">{{ $pctLabel }}%</span>
                                            </button>
                                        @endforeach
                                        <div class="poll-meta">
                                            <span class="poll-total">{{ $total }} {{ __('chat_poll_votes') }}</span>
                                            @if ($closed)
                                                <span class="poll-closed">🔒 {{ __('chat_poll_closed') }}</span>
                                            @endif
                                            @if ($mine && !$closed)
                                                <button type="button" wire:click="closePoll('{{ $msg['id'] }}')" class="poll-close">{{ __('chat_poll_close') }}</button>
                                            @endif
                                            <span class="chat-time ml-auto">{{ $msg['time'] }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @else
                            @php
                                $mText = $type === 'text';
                                $media = $msg['media'] ?? null;
                            @endphp
                            <div
                                wire:key="msg-{{ $msg['id'] }}"
                                class="msg-row flex items-end gap-2 animate-fade-up {{ $mine ? 'justify-end' : '' }}"
                                data-id="{{ $msg['id'] }}"
                                data-uid="{{ $msg['uid'] ?? '' }}"
                                data-user="{{ $msg['user'] ?? '' }}"
                                data-text="{{ $mText ? $msg['text'] : ($media['alt'] ?? '') }}"
                            >
                                @if (!$mine)
                                    <div class="mb-5 mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-fuchsia-500 to-cyan-500 text-xs font-bold">{{ mb_strtoupper(mb_substr($msg['user'], 0, 1)) }}</div>
                                @endif

                                <div class="msg-col flex min-w-0 max-w-[82%] flex-col sm:max-w-[72%]">
                                    @if (!$mine)
                                        <div class="mb-0.5 flex items-baseline gap-2 px-1.5">
                                            <span class="chat-username">{{ $msg['user'] }}</span>
                                            <span class="chat-time">{{ $msg['time'] }}</span>
                                        </div>
                                    @endif

                                    <div class="flex items-end gap-1.5">
                                        <div class="msg-menu-wrap {{ $mine ? 'msg-menu-wrap-out' : 'msg-menu-wrap-in' }}">
                                            <button
                                                type="button"
                                                @click.stop="toggleMenu('{{ $msg['id'] }}')"
                                                class="msg-menu-btn"
                                                aria-label="…"
                                            >
                                                <x-icon name="ellipsis-vertical" class="h-4 w-4" />
                                            </button>
                                            <div x-show="menuId === '{{ $msg['id'] }}'" x-transition class="msg-menu" @click.stop>
                                                <button type="button" @click.stop="startReply('{{ $msg['id'] }}')"><x-icon name="reply" class="h-3.5 w-3.5" /> {{ __('chat_reply') }}</button>
                                                <button type="button" @click.stop="reactFromMenu('{{ $msg['id'] }}', '❤️')">❤️</button>
                                                <button type="button" @click.stop="reactFromMenu('{{ $msg['id'] }}', '👍')">👍</button>
                                                <button type="button" @click.stop="reactFromMenu('{{ $msg['id'] }}', '😂')">😂</button>
                                                <button type="button" @click.stop="copyRow('{{ $msg['id'] }}')"><x-icon name="check" class="h-3.5 w-3.5" /> {{ __('chat_copy') }}</button>
                                                <button type="button" @click.stop="forwardMsg('{{ $msg['id'] }}')"><x-icon name="forward" class="h-3.5 w-3.5" /> {{ __('chat_forward') }}</button>
                                                @if ($mine)
                                                    <button type="button" @click.stop="startEdit('{{ $msg['id'] }}')"><x-icon name="edit" class="h-3.5 w-3.5" /> {{ __('chat_edit') }}</button>
                                                    <button type="button" @click.stop="deleteMsg('{{ $msg['id'] }}')"><x-icon name="trash" class="h-3.5 w-3.5" /> {{ __('chat_delete') }}</button>
                                                @endif
                                            </div>
                                        </div>

                                        <div
                                            class="chat-bubble {{ $mine ? 'chat-bubble-out' : 'chat-bubble-in' }} px-3.5 py-2 text-sm text-white/95"
                                            @click="copyMessage($event)"
                                            @contextmenu.prevent="toggleMenu('{{ $msg['id'] }}')"
                                            :title="copied ? '{{ __('chat_copied') }}' : '{{ __('chat_copy') }}'"
                                        >
                                            @if (!empty($msg['forwarded']))
                                                <span class="msg-forwarded"><x-icon name="forward" class="h-3 w-3" /> {{ __('chat_forwarded') }}</span>
                                            @endif

                                            @if (!empty($msg['reply']))
                                                <div class="chat-reply-quote">
                                                    <span class="block truncate text-xs font-bold">{{ $msg['reply']['user'] }}</span>
                                                    <span class="block truncate text-xs opacity-70">{{ $msg['reply']['text'] }}</span>
                                                </div>
                                            @endif

                                            @if ($mText)
                                                {!! $this->renderText($msg['text']) !!}
                                                @if (!empty($msg['edited']))
                                                    <span class="msg-edited">{{ __('chat_edited') }}</span>
                                                @endif
                                            @else
                                                @if (str_ends_with($media['url'] ?? '', '.mp4'))
                                                    <video src="{{ $media['url'] }}" poster="{{ $media['preview'] ?? '' }}" autoplay loop muted playsinline class="chat-media"></video>
                                                @else
                                                    <img
                                                        src="{{ $media['preview'] ?: $media['url'] }}"
                                                        data-gif="{{ $media['url'] ?? '' }}"
                                                        @click.stop="playGif($event)"
                                                        class="chat-media"
                                                        loading="lazy"
                                                        alt=""
                                                    >
                                                @endif
                                            @endif

                                            <span class="chat-meta">
                                                <span class="text-[10px]">{{ $msg['time'] }}</span>
                                                @if ($mine)
                                                    @if ($onlineCount > 1)
                                                        <x-icon name="check-double" class="chat-tick chat-tick-read ml-0.5 h-3.5 w-3.5" />
                                                    @else
                                                        <x-icon name="check" class="chat-tick ml-0.5 h-3.5 w-3.5" />
                                                    @endif
                                                @endif
                                            </span>
                                        </div>
                                    </div>

                                    <div class="mt-1 flex flex-wrap items-center gap-1">
                                        @foreach (($msg['reactions'] ?? []) as $emoji => $uids)
                                            <button
                                                type="button"
                                                @click="$wire.react('{{ $msg['id'] }}', '{{ $emoji }}')"
                                                class="reaction-pill {{ in_array($this->senderId, $uids, true) ? 'reaction-pill-active' : '' }}"
                                            >
                                                <span>{{ $emoji }}</span>
                                                <span class="reaction-count">{{ count($uids) }}</span>
                                            </button>
                                        @endforeach

                                        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                            <button type="button" @click="open = !open" class="react-plus" title="+">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                                            </button>
                                            <div x-show="open" x-transition class="quick-react">
                                                @foreach (['❤️', '👍', '😂', '😮', '😢'] as $re)
                                                    <button type="button" @click="$wire.react('{{ $msg['id'] }}', '{{ $re }}'); open = false" x-text="'{{ $re }}'"></button>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endif
                @empty
                    <div class="flex h-full flex-col items-center justify-center gap-2 py-16 text-center text-white/40">
                        <x-icon name="message-square" class="h-10 w-10" />
                        <p class="text-sm">{{ __('chat_empty') }}<br>{{ __('chat_empty_sub') }}</p>
                    </div>
                @endforelse

                @if (count($typing) > 0)
                    <div class="flex items-end gap-2">
                        <div class="typing-bubble">
                            <span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <button
            type="button"
            @click="scrollToBottom()"
            x-show="scrolledUp"
            x-transition
            class="chat-down"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
            <span class="chat-unread" x-show="unread > 0" x-text="unread"></span>
        </button>
    </div>

    <form @submit.prevent="submitChat()" class="shrink-0 border-t border-white/10 p-3 sm:p-4">
        <input type="hidden" wire:model.live.immediate="replyId" x-ref="replyInput" :value="replyTo ? replyTo.id : ''">

        <div class="mb-2">
            <input
                type="text"
                wire:model.live.debounce.500ms="username"
                placeholder="{{ __('chat_username_placeholder') }}"
                maxlength="30"
                class="chat-input w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm outline-none placeholder:text-white/40 focus:border-cyan-400/60"
            >
        </div>

        <div x-show="replyTo" x-transition class="mb-2 flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2">
            <x-icon name="reply" class="h-4 w-4 shrink-0 text-cyan-300" />
            <div class="min-w-0 flex-1">
                <span class="block truncate text-xs font-bold text-cyan-300" x-text="'{{ __('chat_reply_to') }} ' + (replyTo?.user || '')"></span>
                <span class="block truncate text-[11px] text-white/50" x-text="replyTo?.text || ''"></span>
            </div>
            <button type="button" @click="clearReply()" class="shrink-0 rounded-lg p-1 text-white/50 hover:bg-white/10">
                <x-icon name="x" class="h-3.5 w-3.5" />
            </button>
        </div>

        <div x-show="editingId" x-transition class="mb-2 flex items-center gap-2 rounded-xl border border-cyan-400/30 bg-cyan-400/10 px-3 py-2">
            <x-icon name="edit" class="h-4 w-4 shrink-0 text-cyan-300" />
            <span class="min-w-0 flex-1 truncate text-xs text-cyan-200">{{ __('chat_editing') }}</span>
            <button type="button" @click="cancelEdit()" class="shrink-0 rounded-lg p-1 text-white/50 hover:bg-white/10">
                <x-icon name="x" class="h-3.5 w-3.5" />
            </button>
        </div>

        <div x-show="pollComposer" x-transition class="mb-2 rounded-xl border border-cyan-400/20 bg-cyan-500/10 p-3">
            <div class="mb-2 flex items-center justify-between gap-2">
                <span class="text-xs font-bold text-cyan-300"><x-icon name="chart-bar" class="mr-1 inline h-3.5 w-3.5" /> {{ __('chat_poll') }}</span>
                <button type="button" @click="pollComposer = false" class="rounded-lg p-1 text-white/50 hover:bg-white/10">
                    <x-icon name="x" class="h-3.5 w-3.5" />
                </button>
            </div>
            <input
                type="text"
                x-model="pollQuestion"
                placeholder="{{ __('chat_poll_question') }}"
                maxlength="160"
                class="chat-input mb-2 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm outline-none placeholder:text-white/40 focus:border-cyan-400/60"
            >
            <template x-for="(opt, i) in pollOptions" :key="i">
                <div class="mb-2 flex items-center gap-1.5">
                    <span class="flex-1 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/90" x-text="opt"></span>
                    <button type="button" @click="removePollOption(i)" class="rounded-lg p-1.5 text-white/50 hover:bg-white/10 hover:text-red-300">
                        <x-icon name="trash" class="h-3.5 w-3.5" />
                    </button>
                </div>
            </template>
            <div class="flex items-center gap-2">
                <input
                    type="text"
                    x-model="pollOption"
                    placeholder="{{ __('chat_poll_option') }}"
                    maxlength="60"
                    class="chat-input flex-1 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm outline-none placeholder:text-white/40 focus:border-cyan-400/60"
                    @keydown.enter.prevent="addPollOption()"
                >
                <button
                    type="button"
                    @click="addPollOption()"
                    :disabled="pollOptions.length >= 12"
                    class="shrink-0 rounded-lg bg-white/10 px-3 py-2 text-sm text-white/80 hover:bg-white/15 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {{ __('chat_poll_add') }}
                </button>
            </div>
            <div class="mt-1 flex items-center justify-between text-[11px] text-white/40">
                <span x-text="pollOptions.length + '/12'"></span>
                <span x-show="pollOptions.length < 2">⚠ {{ __('chat_poll_min') }}</span>
            </div>
            <button
                type="button"
                @click="publishPoll()"
                :disabled="!pollQuestion.trim() || pollOptions.length < 2"
                class="mt-2 w-full rounded-lg bg-gradient-to-r from-cyan-500 to-fuchsia-500 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-40"
            >
                {{ __('chat_poll_send') }}
            </button>
        </div>

        <div x-show="announceComposer" x-transition class="mb-2 rounded-xl border border-amber-400/30 bg-amber-500/10 p-3">
            <div class="mb-2 flex items-center justify-between gap-2">
                <span class="text-xs font-bold text-amber-300"><x-icon name="megaphone" class="mr-1 inline h-3.5 w-3.5" /> {{ __('chat_announce') }}</span>
                <button type="button" @click="announceComposer = false" class="rounded-lg p-1 text-white/50 hover:bg-white/10">
                    <x-icon name="x" class="h-3.5 w-3.5" />
                </button>
            </div>
            <textarea
                wire:model.live="announceText"
                rows="2"
                placeholder="{{ __('chat_announce_placeholder') }}"
                maxlength="500"
                class="chat-input w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm outline-none placeholder:text-white/40 focus:border-amber-400/60"
            ></textarea>
            <button
                type="button"
                @click="announceComposer = false; $wire.sendAnnouncement()"
                class="mt-2 w-full rounded-lg bg-gradient-to-r from-amber-500 to-orange-500 py-2 text-sm font-bold text-white"
            >
                {{ __('chat_announce_send') }}
            </button>
        </div>

        <div
            x-ref="pickerPanel"
            x-show="picker"
            x-transition
            x-intersect="loadKlipy('klipyGifs'); loadKlipy('klipyStickers')"
            @click.away="closePicker()"
            :style="pickerStyle"
            class="picker-sheet glass mb-2 flex flex-col rounded-xl border border-white/10 p-2"
        >
            <div class="mb-1 flex items-center gap-1 text-xs">
                <button
                    type="button"
                    @click="activeTab = 'gif'"
                    class="rounded-lg px-2.5 py-1 transition"
                    :class="activeTab === 'gif' ? 'bg-white/15 font-bold' : 'text-white/50 hover:bg-white/10'"
                ><x-icon name="film" class="mx-auto h-4 w-4" /></button>
                <button
                    type="button"
                    @click="activeTab = 'stickers'"
                    class="rounded-lg px-2.5 py-1 transition"
                    :class="activeTab === 'stickers' ? 'bg-white/15 font-bold' : 'text-white/50 hover:bg-white/10'"
                ><x-icon name="sticker" class="mx-auto h-4 w-4" /></button>
                <div class="chat-scroll flex-1 overflow-x-auto whitespace-nowrap">
                    <template x-for="c in categories" :key="c.id">
                        <button
                            type="button"
                            @click="activeTab = 'emoji'; category = c.id"
                            class="inline-block rounded-lg px-2 py-1 transition"
                            :class="activeTab === 'emoji' && category === c.id ? 'bg-white/15' : 'text-white/50 hover:bg-white/10'"
                        ><span x-text="c.label"></span></button>
                    </template>
                </div>
                <button
                    type="button"
                    @click="picker = false"
                    class="ml-auto shrink-0 rounded-lg p-1.5 text-white/50 transition hover:bg-white/10 hover:text-white"
                    aria-label="{{ __('chat_close') }}"
                >
                    <x-icon name="x" class="h-4 w-4" />
                </button>
            </div>

            <div x-show="activeTab === 'gif'" class="flex h-full min-h-0 flex-col">
                <template x-if="klipyOn">
                    <div class="flex h-full min-h-0 flex-col">
                        <div class="mb-2 flex shrink-0 gap-2">
                            <input
                                x-model="gifQuery"
                                @keydown.enter.prevent="searchKlipy('klipyGifs')"
                                type="text"
                                class="chat-input w-full rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs outline-none placeholder:text-white/40"
                                placeholder="{{ __('chat_search_placeholder') }}"
                            >
                            <button type="button" @click="searchKlipy('klipyGifs')" class="shrink-0 rounded-lg bg-white/10 px-2 text-white/70 hover:bg-white/15">
                                <x-icon name="search" class="h-3.5 w-3.5" />
                            </button>
                        </div>
                        <div class="chat-scroll grid flex-1 min-h-0 grid-cols-4 content-start gap-1 overflow-y-auto">
                            <template x-for="g in klipyGifs" :key="g.url">
                                <button
                                    type="button"
                                    @click="sendMedia(g, 'gif')"
                                    class="rounded-lg bg-black/20 p-1 transition hover:scale-110 hover:bg-white/10 active:scale-95"
                                ><img :src="g.preview || g.url" class="chat-sticker-preview" alt="gif" loading="lazy"></button>
                            </template>
                        </div>
                        <p x-show="gifLoading" class="shrink-0 py-2 text-center text-xs text-white/40">{{ __('chat_loading') }}</p>
                        <p x-show="!gifLoading && (gifFailed || (gifLoaded && klipyGifs.length === 0))" class="shrink-0 py-2 text-center text-xs text-white/40">{{ __('chat_no_results') }}</p>
                    </div>
                </template>
                <template x-if="!klipyOn">
                    <div class="chat-scroll grid h-full grid-cols-4 content-start gap-1 overflow-y-auto">
                        <template x-for="gif in gifs" :key="'g' + gif.hex">
                            <button
                                type="button"
                                @click="addSticker(gif.hex)"
                                class="rounded-lg bg-black/20 p-1 transition hover:scale-110 hover:bg-white/10 active:scale-95"
                                :title="gif.emoji"
                            ><img :src="emojiUrl(gif.hex)" class="chat-sticker-preview" alt="gif" loading="lazy"></button>
                        </template>
                    </div>
                </template>
            </div>

            <div x-show="activeTab === 'stickers'" class="flex h-full min-h-0 flex-col">
                <div class="mb-2 flex shrink-0 gap-1 rounded-xl border border-white/10 bg-white/5 p-1">
                    <button
                        type="button"
                        @click="setStickerSource('tenor')"
                        x-show="tenorOn"
                        class="flex-1 rounded-lg px-2 py-1 text-[11px] font-bold transition"
                        :class="stickerSource === 'tenor' ? 'bg-white/15 text-white' : 'text-white/50 hover:bg-white/10'"
                    >Tenor</button>
                    <button
                        type="button"
                        @click="setStickerSource('klipy')"
                        x-show="klipyOn"
                        class="flex-1 rounded-lg px-2 py-1 text-[11px] font-bold transition"
                        :class="stickerSource === 'klipy' ? 'bg-white/15 text-white' : 'text-white/50 hover:bg-white/10'"
                    >Klipy</button>
                    <button
                        type="button"
                        @click="setStickerSource('emoji')"
                        class="flex-1 rounded-lg px-2 py-1 text-[11px] font-bold transition"
                        :class="stickerSource === 'emoji' ? 'bg-white/15 text-white' : 'text-white/50 hover:bg-white/10'"
                    >😀 Emoji</button>
                </div>

                <template x-if="stickerSource === 'tenor'">
                    <div class="flex h-full min-h-0 flex-col">
                        <div class="mb-2 flex shrink-0 gap-2">
                            <input
                                x-model="stickerQuery"
                                @keydown.enter.prevent="searchTenor()"
                                type="text"
                                class="chat-input w-full rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs outline-none placeholder:text-white/40"
                                placeholder="{{ __('chat_search_placeholder') }}"
                            >
                            <button type="button" @click="searchTenor()" class="shrink-0 rounded-lg bg-white/10 px-2 text-white/70 hover:bg-white/15">
                                <x-icon name="search" class="h-3.5 w-3.5" />
                            </button>
                        </div>
                        <div class="chat-scroll grid flex-1 min-h-0 grid-cols-4 content-start gap-1 overflow-y-auto">
                            <template x-for="s in tenorStickers" :key="'t' + (s.url || s.id)">
                                <button
                                    type="button"
                                    @click="sendMedia(s, 'sticker')"
                                    class="rounded-lg bg-black/20 p-1 transition hover:scale-110 hover:bg-white/10 active:scale-95"
                                ><img :src="s.preview || s.url" class="chat-sticker-preview" alt="sticker" loading="lazy"></button>
                            </template>
                        </div>
                        <p x-show="tenorLoading" class="shrink-0 py-2 text-center text-xs text-white/40">{{ __('chat_loading') }}</p>
                        <p x-show="!tenorLoading && (tenorFailed || (tenorLoaded && tenorStickers.length === 0))" class="shrink-0 py-2 text-center text-xs text-white/40">{{ __('chat_no_results') }}</p>
                    </div>
                </template>

                <template x-if="stickerSource === 'klipy' && klipyOn">
                    <div class="flex h-full min-h-0 flex-col">
                        <div class="mb-2 flex shrink-0 gap-2">
                            <input
                                x-model="stickerQuery"
                                @keydown.enter.prevent="searchKlipy('klipyStickers')"
                                type="text"
                                class="chat-input w-full rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs outline-none placeholder:text-white/40"
                                placeholder="{{ __('chat_search_placeholder') }}"
                            >
                            <button type="button" @click="searchKlipy('klipyStickers')" class="shrink-0 rounded-lg bg-white/10 px-2 text-white/70 hover:bg-white/15">
                                <x-icon name="search" class="h-3.5 w-3.5" />
                            </button>
                        </div>
                        <div class="chat-scroll grid flex-1 min-h-0 grid-cols-4 content-start gap-1 overflow-y-auto">
                            <template x-for="s in klipyStickers" :key="s.url">
                                <button
                                    type="button"
                                    @click="sendMedia(s, 'sticker')"
                                    class="rounded-lg bg-black/20 p-1 transition hover:scale-110 hover:bg-white/10 active:scale-95"
                                ><img :src="s.preview || s.url" class="chat-sticker-preview" alt="sticker" loading="lazy"></button>
                            </template>
                        </div>
                        <p x-show="stickerLoading" class="shrink-0 py-2 text-center text-xs text-white/40">{{ __('chat_loading') }}</p>
                        <p x-show="!stickerLoading && (stickerFailed || (stickerLoaded && klipyStickers.length === 0))" class="shrink-0 py-2 text-center text-xs text-white/40">{{ __('chat_no_results') }}</p>
                    </div>
                </template>

                <template x-if="stickerSource === 'emoji'">
                    <div class="chat-scroll grid h-full grid-cols-4 content-start gap-1 overflow-y-auto">
                        <template x-for="sticker in stickers" :key="'s' + sticker.hex">
                            <button
                                type="button"
                                @click="addSticker(sticker.hex)"
                                class="rounded-lg bg-black/20 p-1 transition hover:scale-110 hover:bg-white/10 active:scale-95"
                                :title="sticker.emoji"
                            ><img :src="emojiUrl(sticker.hex)" class="chat-sticker-preview" alt="sticker" loading="lazy"></button>
                        </template>
                    </div>
                </template>
            </div>

            <div x-show="activeTab === 'emoji'" class="flex h-full min-h-0 flex-col">
                <template x-if="picker">
                    <emoji-picker class="chat-emoji-picker h-full" :data-source="emojiDataSource" @emoji-click="onEmojiClick($event)"></emoji-picker>
                </template>
            </div>
        </div>

        <div class="flex items-end gap-2">
            <button
                type="button"
                @click="pollComposer = !pollComposer; announceComposer = false; picker = false"
                class="shrink-0 rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 transition hover:scale-105 hover:bg-white/10 active:scale-95"
                :class="pollComposer ? 'bg-white/15' : ''"
                :title="'{{ __('chat_poll') }}'"
            ><x-icon name="chart-bar" class="mx-auto h-5 w-5" /></button>

            <button
                type="button"
                @click="announceComposer = !announceComposer; pollComposer = false; picker = false"
                class="shrink-0 rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 transition hover:scale-105 hover:bg-white/10 active:scale-95"
                :class="announceComposer ? 'bg-white/15' : ''"
                :title="'{{ __('chat_announce') }}'"
            ><x-icon name="megaphone" class="mx-auto h-5 w-5" /></button>

            <button
                type="button"
                x-ref="pickerBtn"
                @click.stop="togglePicker($event)"
                class="shrink-0 rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-lg transition hover:scale-105 hover:bg-white/10 active:scale-95"
                :class="picker ? 'bg-white/15' : ''"
            ><x-icon name="smile" class="mx-auto h-5 w-5" /></button>

            <div class="relative min-w-0 flex-1">
                <textarea
                    x-ref="msgInput"
                    x-show="!editingId"
                    wire:model.live.debounce.200ms="message"
                    @keydown.enter.exact.prevent="submitChat()"
                    @keydown.debounce.1200ms="$wire.typingIndicator()"
                    @input="chars = $refs.msgInput.value.length; autoGrow($refs.msgInput)"
                    rows="1"
                    maxlength="280"
                    autocomplete="off"
                    placeholder="{{ __('chat_message_placeholder') }}"
                    class="chat-input w-full min-w-0 resize-none rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 pr-14 text-sm outline-none placeholder:text-white/40 focus:border-cyan-400/60"
                ></textarea>
                <textarea
                    x-ref="editInput"
                    x-show="editingId"
                    x-model="editingText"
                    @keydown.enter.exact.prevent="submitChat()"
                    @input="autoGrow($refs.editInput)"
                    rows="1"
                    maxlength="280"
                    class="chat-input w-full min-w-0 resize-none rounded-xl border border-cyan-400/40 bg-white/5 px-4 py-2.5 pr-14 text-sm outline-none placeholder:text-white/40 focus:border-cyan-400/60"
                ></textarea>
                <span
                    class="pointer-events-none absolute bottom-2 right-3 text-[10px] tabular-nums text-white/30"
                    x-show="editingId ? editingText.length > 0 : chars > 0"
                    x-text="(editingId ? editingText.length : chars) + '/280'"
                ></span>
            </div>

            <button
                type="submit"
                class="chat-send-btn flex shrink-0 items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-fuchsia-500 px-3.5 py-2.5 text-sm font-bold text-white shadow-lg shadow-cyan-500/20 transition hover:scale-105 active:scale-95 sm:px-4"
            >
                <x-icon name="send" class="h-4 w-4" />
                <span class="hidden sm:inline">{{ __('chat_send') }}</span>
            </button>
        </div>
    </form>
</div>
