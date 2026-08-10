<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

new class extends Component
{
    public string $username = '';

    public string $message = '';

    public array $messages = [];

    public function mount(): void
    {
        $this->username = session('chat_user', '');
        $this->messages = $this->load();
    }

    public function send(): void
    {
        $this->validate([
            'message' => ['required', 'string', 'max:280'],
            'username' => ['nullable', 'string', 'max:30'],
        ]);

        $user = trim($this->username) !== '' ? trim($this->username) : __('chat_anonymous');
        session(['chat_user' => $user]);

        $messages = $this->load();
        $messages[] = [
            'user' => $user,
            'text' => trim($this->message),
            'time' => now()->timezone(config('shift.timezone'))->format('H:i'),
        ];

        $this->messages = collect($messages)->slice(-50)->values()->all();
        $this->message = '';

        Cache::put('chat:messages', $this->messages, $this->ttl());
    }

    public function refreshMessages(): void
    {
        $this->messages = $this->load();
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
};
?>

<div
    wire:poll.3s="refreshMessages"
    x-data="chatComposer()"
    class="glass flex h-[26rem] flex-col overflow-hidden rounded-2xl"
>
    <div class="flex items-center justify-between border-b border-white/10 px-5 py-4">
        <h2 class="flex items-center gap-2 text-sm font-bold uppercase tracking-widest text-white/70">
            <x-icon name="message-square" class="h-4 w-4" /> {{ __('chat_title') }}
            <span class="dot" :class="liveDot()"></span>
        </h2>
        <span class="rounded-full bg-white/10 px-3 py-1 text-xs text-white/60">{{ __('chat_cleared_at', ['time' => config('shift.end')]) }}</span>
    </div>

    <div
        x-ref="scroll"
        class="flex-1 space-y-3 overflow-y-auto px-5 py-4 chat-scroll"
        x-init="new MutationObserver(() => { $refs.scroll.scrollTop = $refs.scroll.scrollHeight; }).observe($refs.scroll, { childList: true })"
    >
        @forelse ($messages as $msg)
            <div wire:key="msg-{{ $loop->index }}" class="msg-in flex gap-2.5 animate-fade-up">
                <div class="avatar mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-fuchsia-500 to-cyan-500 text-sm font-bold">
                    {{ mb_strtoupper(mb_substr($msg['user'], 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-baseline gap-2">
                        <span class="text-sm font-bold">{{ $msg['user'] }}</span>
                        <span class="text-xs text-white/40 tabular-nums">{{ $msg['time'] }}</span>
                    </div>
                    <p class="text-sm text-white/85">{!! preg_replace_callback(
                        '/\[\[stk:([0-9a-f]+)\]\]/',
                        fn ($m) => '<img class="stk-inline align-middle" src="https://fonts.gstatic.com/s/e/notoemoji/latest/' . $m[1] . '/512.gif" alt="sticker" loading="lazy">',
                        e($msg['text'])
                    ) !!}</p>
                </div>
            </div>
        @empty
            <div class="flex h-full flex-col items-center justify-center gap-2 text-center text-white/40">
                <x-icon name="message-square" class="h-10 w-10" />
                <p class="text-sm">{{ __('chat_empty') }}<br>{{ __('chat_empty_sub') }}</p>
            </div>
        @endforelse
    </div>

    @error('message')
        <p class="px-5 text-xs text-rose-300">{{ $message }}</p>
    @enderror

    <form wire:submit="send" class="space-y-2 border-t border-white/10 p-4">
        <input
            type="text"
            wire:model.live.debounce.500ms="username"
            placeholder="{{ __('chat_username_placeholder') }}"
            maxlength="30"
            class="chat-input w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm outline-none placeholder:text-white/40 focus:border-cyan-400/60"
        >

        <div x-show="picker" x-transition class="glass rounded-xl border border-white/10 p-2">
            <div class="mb-1 flex items-center gap-1 text-xs">
                <button
                    type="button"
                    @click="category = null; activeTab = 'gif'"
                    class="rounded-lg px-2.5 py-1 transition"
                    :class="activeTab === 'gif' ? 'bg-white/15 font-bold' : 'text-white/50 hover:bg-white/10'"
                ><x-icon name="film" class="mx-auto h-4 w-4" /></button>
                <button
                    type="button"
                    @click="category = null; activeTab = 'stickers'"
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
            </div>
            <div x-show="activeTab === 'gif'" class="grid max-h-32 grid-cols-4 gap-1 overflow-y-auto chat-scroll">
                <template x-for="gif in gifs" :key="'g' + gif.hex">
                    <button
                        type="button"
                        @click="addSticker(gif.hex)"
                        class="rounded-lg bg-black/20 p-1 transition hover:scale-110 hover:bg-white/10 active:scale-95"
                        :title="gif.emoji"
                    ><img :src="emojiUrl(gif.hex)" class="chat-sticker-preview" alt="gif" loading="lazy"></button>
                </template>
            </div>
            <div x-show="activeTab === 'stickers'" class="grid max-h-32 grid-cols-4 gap-1 overflow-y-auto chat-scroll">
                <template x-for="sticker in stickers" :key="'s' + sticker.hex">
                    <button
                        type="button"
                        @click="addSticker(sticker.hex)"
                        class="rounded-lg bg-black/20 p-1 transition hover:scale-110 hover:bg-white/10 active:scale-95"
                        :title="sticker.emoji"
                    ><img :src="emojiUrl(sticker.hex)" class="chat-sticker-preview" alt="sticker" loading="lazy"></button>
                </template>
            </div>
            <div x-show="activeTab === 'emoji'" class="chat-scroll max-h-32 overflow-y-auto">
                <template x-for="c in categories" :key="c.id">
                    <div x-show="category === c.id" class="grid grid-cols-8 gap-1">
                        <template x-for="emoji in c.items" :key="emoji">
                            <button
                                type="button"
                                @click="addEmoji(emoji)"
                                class="rounded-lg p-1.5 text-lg leading-none transition hover:scale-125 hover:bg-white/10 active:scale-95"
                            ><span x-text="emoji"></span></button>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <div class="flex gap-2">
            <button
                type="button"
                @click="picker = !picker"
                class="shrink-0 rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-lg transition hover:scale-105 hover:bg-white/10 active:scale-95"
                :class="picker ? 'bg-white/15' : ''"
                title="😊 / 🧸"
            ><x-icon name="smile" class="mx-auto h-5 w-5" /></button>
            <input
                type="text"
                wire:model.live.debounce.200ms="message"
                wire:keydown.enter="send"
                x-ref="msgInput"
                placeholder="{{ __('chat_message_placeholder') }}"
                maxlength="280"
                autocomplete="off"
                class="chat-input flex-1 rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm outline-none placeholder:text-white/40 focus:border-cyan-400/60"
            >
            <button
                type="submit"
                class="flex shrink-0 items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-fuchsia-500 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-cyan-500/20 transition hover:scale-105 active:scale-95"
            >
                <x-icon name="send" class="h-4 w-4" />
                {{ __('chat_send') }}
            </button>
        </div>
    </form>
</div>
