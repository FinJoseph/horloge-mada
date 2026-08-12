<?php

use Livewire\Component;

new class extends Component {};
?>

<div
    x-data="gamesHub()"
    class="relative min-h-screen overflow-hidden bg-[#0b1026]"
>
    <div class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute -top-32 -left-32 h-96 w-96 rounded-full bg-cyan-500/20 blur-3xl"></div>
        <div class="absolute -right-32 top-1/3 h-96 w-96 rounded-full bg-fuchsia-500/20 blur-3xl"></div>
        <div class="absolute -bottom-32 left-1/3 h-96 w-96 rounded-full bg-amber-500/10 blur-3xl"></div>
    </div>

    <div class="relative z-10 mx-auto flex min-h-screen max-w-6xl flex-col px-4 py-6 sm:px-8">
        <header class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ url('/') }}" class="glass flex h-11 w-11 items-center justify-center rounded-2xl transition hover:scale-105 active:scale-95" title="{{ __('games_back') }}">
                    <x-icon name="arrow-left" class="h-5 w-5" />
                </a>
                <div>
                    <h1 class="text-lg font-bold tracking-tight">🎮 {{ __('games_title') }}</h1>
                    <p class="text-xs text-white/60">{{ __('games_subtitle') }}</p>
                </div>
            </div>
            <button
                type="button"
                x-show="active"
                @click="back()"
                class="glass hidden h-11 items-center gap-2 rounded-2xl px-4 text-sm font-bold transition hover:scale-105 active:scale-95 sm:flex"
            >← {{ __('games_back') }}</button>
        </header>

        <template x-if="active === null">
            <div class="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <template x-for="g in [
                    { id: 'dactylo', emoji: '⌨️', title: @js(__('games_dactylo_title')), desc: @js(__('games_dactylo_desc')), tag: '3 {{ __('games_dactylo_mode') }}' },
                    { id: 'anagram', emoji: '🔤', title: @js(__('games_anagram_title')), desc: @js(__('games_anagram_desc')), tag: '' },
                    { id: 'catch', emoji: '🧺', title: @js(__('games_catch_title')), desc: @js(__('games_catch_desc')), tag: '' },
                    { id: 'rps', emoji: '✊', title: @js(__('games_rps_title')), desc: @js(__('games_rps_desc')), tag: '' },
                    { id: 'morpion', emoji: '⭕', title: @js(__('games_morpion_title')), desc: @js(__('games_morpion_desc')), tag: '' },
                    { id: 'memory', emoji: '🧠', title: @js(__('games_memory_title')), desc: @js(__('games_memory_desc')), tag: '' },
                    { id: 'snake', emoji: '🐍', title: @js(__('games_snake_title')), desc: @js(__('games_snake_desc')), tag: '' },
                ]" :key="g.id">
                    <button
                        type="button"
                        @click="open(g.id)"
                        class="glass group flex flex-col items-start gap-3 rounded-3xl p-5 text-left transition hover:scale-[1.02] active:scale-[0.98]"
                    >
                        <span class="flex w-full items-start justify-between">
                            <span class="text-4xl transition group-hover:scale-110" x-text="g.emoji"></span>
                            <span x-show="g.tag" class="rounded-xl bg-cyan-500/15 px-2 py-0.5 text-[10px] font-bold text-cyan-300" x-text="g.tag"></span>
                        </span>
                        <span class="text-sm font-bold" x-text="g.title"></span>
                        <span class="text-xs text-white/50" x-text="g.desc"></span>
                        <span class="mt-1 inline-flex items-center gap-2 rounded-xl bg-white/10 px-3 py-1 text-[11px] font-bold text-amber-300">
                            🏆 <span x-text="'{{ __('games_best') }} ' + (best[g.id] || 0)"></span>
                        </span>
                        <span class="mt-1 rounded-xl bg-gradient-to-r from-cyan-500 to-fuchsia-500 px-4 py-1.5 text-xs font-bold text-white">{{ __('games_play') }}</span>
                    </button>
                </template>
            </div>
        </template>

        <template x-if="active === 'dactylo'">
            <div x-data="gameDactylo()" x-init="init()" class="glass mx-auto mt-10 w-full max-w-2xl rounded-3xl p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold">⌨️ {{ __('games_dactylo_title') }}</h2>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="rounded-xl bg-white/10 px-3 py-1 text-amber-300">🏆 <span x-text="best[mode]"></span></span>
                        <span class="rounded-xl bg-white/10 px-3 py-1">{{ __('games_level') }} <b x-text="level"></b>/5</span>
                    </div>
                </div>

                <template x-if="screen === 'menu'">
                    <div>
                        <p class="mb-2 text-xs font-bold uppercase tracking-widest text-white/40">🎮 {{ __('games_dactylo_mode') }}</p>
                        <div class="mb-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
                            <template x-for="m in modes" :key="m.id">
                                <button
                                    type="button"
                                    @click="setMode(m.id)"
                                    class="rounded-2xl p-3 text-left transition hover:scale-[1.02] active:scale-[0.98]"
                                    :class="mode === m.id ? 'bg-gradient-to-br from-cyan-500/25 to-fuchsia-500/25 ring-1 ring-cyan-400/50' : 'bg-white/5 hover:bg-white/10'"
                                >
                                    <span class="text-2xl" x-text="m.emoji"></span>
                                    <p class="mt-1 text-sm font-bold" x-text="m.id === 'course' ? @js(__('games_dactylo_course')) : m.id === 'survie' ? @js(__('games_dactylo_survie')) : @js(__('games_dactylo_deft'))"></p>
                                    <p class="mt-0.5 text-[11px] text-white/50" x-text="m.id === 'course' ? @js(__('games_dactylo_course_desc')) : m.id === 'survie' ? @js(__('games_dactylo_survie_desc')) : @js(__('games_dactylo_deft_desc'))"></p>
                                </button>
                            </template>
                        </div>

                        <p class="mb-2 text-xs font-bold uppercase tracking-widest text-white/40">📶 {{ __('games_dactylo_level') }}</p>
                        <div class="mb-4 flex flex-wrap gap-2">
                            <template x-for="l in [1, 2, 3, 4, 5]" :key="l">
                                <button
                                    type="button"
                                    @click="setLevel(l)"
                                    class="h-10 w-10 rounded-xl text-sm font-bold transition hover:scale-105 active:scale-95"
                                    :class="level === l ? 'bg-gradient-to-br from-cyan-500 to-fuchsia-500 text-white' : 'bg-white/10 text-white/60 hover:bg-white/15'"
                                    x-text="l"
                                ></button>
                            </template>
                        </div>

                        <p class="mb-2 text-xs font-bold uppercase tracking-widest text-white/40">🎨 {{ __('games_dactylo_style') }}</p>
                        <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <template x-for="s in styles" :key="s.id">
                                <button
                                    type="button"
                                    @click="setStyle(s.id)"
                                    class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-bold transition hover:scale-[1.02] active:scale-[0.98]"
                                    :class="style === s.id ? 'bg-white/15 ring-1 ring-cyan-400/50' : 'bg-white/5 hover:bg-white/10'"
                                >
                                    <span x-text="s.emoji"></span>
                                    <span x-text="s.id === 'neon' ? @js(__('games_dactylo_style_neon')) : s.id === 'retro' ? @js(__('games_dactylo_style_retro')) : s.id === 'ocean' ? @js(__('games_dactylo_style_ocean')) : @js(__('games_dactylo_style_sunset'))"></span>
                                </button>
                            </template>
                        </div>

                        <details class="mb-4 rounded-2xl bg-white/5 p-3">
                            <summary class="cursor-pointer select-none text-sm font-bold text-white/70">📖 {{ __('games_dactylo_rules') }}</summary>
                            <p class="mt-2 text-sm leading-relaxed text-white/60" x-text="mode === 'course' ? @js(__('games_dactylo_howto_course')) : mode === 'survie' ? @js(__('games_dactylo_howto_survie')) : @js(__('games_dactylo_howto_deft'))"></p>
                        </details>

                        <button type="button" @click="start()" class="w-full rounded-xl bg-gradient-to-r from-cyan-500 to-fuchsia-500 py-3 text-sm font-bold text-white transition hover:brightness-110">🚀 {{ __('games_play') }}</button>
                    </div>
                </template>

                <template x-if="screen === 'play'">
                    <div x-init="$nextTick(() => { const el = $refs.dactyloInput; if (el) el.focus(); })">
                        <div class="mb-3 flex flex-wrap items-center gap-2 text-xs">
                            <span class="rounded-xl bg-white/10 px-3 py-1">⭐ <b x-text="score"></b></span>
                            <span x-show="mode !== 'survie'" class="rounded-xl bg-white/10 px-3 py-1">🔥 <b x-text="combo ? 1 + combo : 1"></b>x</span>
                            <span x-show="mode === 'survie'" class="rounded-xl bg-white/10 px-3 py-1">❤️ <b x-text="lives"></b></span>
                            <span x-show="mode === 'course'" class="rounded-xl bg-white/10 px-3 py-1" :class="Math.max(0, Math.ceil(60 - elapsed)) <= 5 ? 'text-red-300' : ''">⏱ <b x-text="Math.max(0, Math.ceil(60 - elapsed))"></b>s</span>
                            <span x-show="mode === 'deft'" class="rounded-xl bg-white/10 px-3 py-1">⏱ <b x-text="elapsed.toFixed(1)"></b>s</span>
                            <span x-show="mode !== 'survie'" class="rounded-xl bg-white/10 px-3 py-1">⚡ <b x-text="liveWpm()"></b> WPM</span>
                            <span x-show="mode === 'survie'" class="rounded-xl bg-white/10 px-3 py-1">📶 {{ __('games_level') }} <b x-text="level"></b></span>
                        </div>

                        <template x-if="mode === 'survie'">
                            <div class="relative h-72 overflow-hidden rounded-xl bg-black/30 p-2" :class="'typing-style-' + style">
                                <template x-for="f in falling" :key="f.word + '_' + f.y">
                                    <div
                                        class="absolute text-lg font-bold"
                                        :class="f.word.startsWith(typed) && typed ? styleAccent() : 'text-white/85'"
                                        :style="'left:' + f.x + '%; top:' + f.y + '%'"
                                    >
                                        <span x-show="f.word.startsWith(typed) && typed" x-text="typed"></span>
                                        <span x-text="f.word.slice(f.word.startsWith(typed) && typed ? typed.length : 0)"></span>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="mode !== 'survie'">
                            <div class="rounded-xl bg-black/20 py-8 text-center" :class="'typing-style-' + style">
                                <span class="text-3xl font-bold tracking-wider" x-text="word.slice(0, typed.length)" :class="styleAccent()"></span>
                                <span class="text-3xl font-bold tracking-wider text-white/30" x-text="word.slice(typed.length)"></span>
                            </div>
                        </template>

                        <input
                            type="text"
                            autofocus
                            x-ref="dactyloInput"
                            x-model="typed"
                            @input="onInput($event.target.value)"
                            class="chat-input mt-3 w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-center text-xl font-bold tracking-widest outline-none placeholder:text-white/30 focus:border-cyan-400/60"
                            :placeholder="@js(__('games_dactylo_type'))"
                        >
                    </div>
                </template>

                <template x-if="screen === 'over'">
                    <div>
                        <div class="mb-4 rounded-2xl bg-gradient-to-br from-emerald-500/15 to-cyan-500/15 p-4 text-center">
                            <p class="text-lg font-bold text-emerald-300">🎉 {{ __('games_dactylo_finished') }}</p>
                            <div class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                                <div class="rounded-xl bg-white/5 py-2">⭐ {{ __('games_dactylo_score') }}<br><b class="text-lg text-white" x-text="score"></b></div>
                                <div class="rounded-xl bg-white/5 py-2" x-show="mode !== 'survie'">⚡ WPM<br><b class="text-lg text-white" x-text="wpm"></b></div>
                                <div class="rounded-xl bg-white/5 py-2" x-show="mode !== 'survie'">🎯 {{ __('games_dactylo_precision') }}<br><b class="text-lg text-white" x-text="precision + '%'"></b></div>
                                <div class="rounded-xl bg-white/5 py-2">🔥 {{ __('games_dactylo_combo') }}<br><b class="text-lg text-white" x-text="bestCombo"></b></div>
                            </div>
                            <p class="mt-3 text-xs text-amber-300">🏆 {{ __('games_best') }} <b x-text="best[mode]"></b></p>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" @click="start()" class="rounded-xl bg-gradient-to-r from-cyan-500 to-fuchsia-500 py-2 text-sm font-bold text-white transition hover:brightness-110">🔁 {{ __('games_dactylo_replay') }}</button>
                            <button type="button" @click="backToMenu()" class="rounded-xl bg-white/10 py-2 text-sm font-bold transition hover:bg-white/15">🗂️ {{ __('games_dactylo_menu') }}</button>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="active === 'anagram'">
            <div x-data="gameAnagram()" x-init="init()" class="glass mx-auto mt-10 w-full max-w-lg rounded-3xl p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold">🔤 {{ __('games_anagram_title') }}</h2>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="rounded-xl bg-white/10 px-3 py-1 text-amber-300">🏆 <span x-text="best"></span></span>
                        <span class="rounded-xl bg-white/10 px-3 py-1">{{ __('games_level') }} <b x-text="level"></b>/3</span>
                        <span class="rounded-xl bg-white/10 px-3 py-1">❤️ <b x-text="lives"></b></span>
                        <span class="rounded-xl bg-white/10 px-3 py-1">⭐ <b x-text="score"></b></span>
                        <span class="rounded-xl bg-white/10 px-3 py-1" :class="time <= 5 ? 'text-red-300' : ''">⏱ <b x-text="time"></b>s</span>
                    </div>
                </div>
                <p class="mb-2 text-center text-sm"><span class="text-white/50">{{ __('games_anagram_hint') }}</span> <b class="text-cyan-300" x-text="word ? word.hint : ''"></b></p>
                <div class="mb-4 flex flex-wrap justify-center gap-2">
                    <template x-for="(l, i) in scrambled" :key="i">
                        <button
                            type="button"
                            @click="addLetter(l)"
                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/10 text-xl font-bold transition hover:scale-110 hover:bg-white/15 active:scale-95"
                            x-text="l"
                        ></button>
                    </template>
                </div>
                <div class="mb-3 flex items-center gap-2">
                    <input
                        type="text"
                        x-model="typed"
                        @keydown.enter.prevent="submit()"
                        class="chat-input w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-center text-xl font-bold tracking-widest outline-none placeholder:text-white/30 focus:border-cyan-400/60"
                        :placeholder="@js(__('games_anagram_find'))"
                    >
                    <button type="button" @click="backspace()" class="shrink-0 rounded-xl bg-white/10 px-3 py-2.5 text-lg transition hover:bg-white/15">⌫</button>
                    <button type="button" @click="submit()" class="shrink-0 rounded-xl bg-gradient-to-r from-cyan-500 to-fuchsia-500 px-4 py-2.5 text-sm font-bold text-white">OK</button>
                </div>
                <details class="mb-3 rounded-2xl bg-white/5 p-3">
                    <summary class="cursor-pointer select-none text-xs font-bold text-white/70">📖 {{ __('games_dactylo_rules') }}</summary>
                    <p class="mt-2 text-xs leading-relaxed text-white/60">{{ __('games_anagram_rules') }}</p>
                </details>
                <div x-show="over" class="rounded-xl py-2 text-center text-sm font-bold"
                     :class="won ? 'bg-emerald-500/10 text-emerald-300' : 'bg-red-500/10 text-red-300'"
                     x-text="won ? '🏆 {{ __('games_anagram_title') }} : ' + score + ' ⭐' : '💥 ' + @js(__('games_win'))"></div>
                <button type="button" @click="replay()" x-show="over" class="mt-3 w-full rounded-xl bg-white/10 py-2 text-sm font-bold transition hover:bg-white/15">{{ __('games_restart') }}</button>
            </div>
        </template>

        <template x-if="active === 'catch'">
            <div x-data="gameCatch()" x-init="init()" class="glass mx-auto mt-10 w-full max-w-xl rounded-3xl p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold">🧺 {{ __('games_catch_title') }}</h2>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="rounded-xl bg-white/10 px-3 py-1 text-amber-300">🏆 <span x-text="best"></span></span>
                        <span class="rounded-xl bg-white/10 px-3 py-1">{{ __('games_level') }} <b x-text="level"></b>/5</span>
                        <span class="rounded-xl bg-white/10 px-3 py-1">❤️ <b x-text="lives"></b></span>
                        <span class="rounded-xl bg-white/10 px-3 py-1">⭐ <b x-text="score"></b></span>
                    </div>
                </div>
                <p class="mb-2 text-center text-sm">
                    <span class="text-white/50">{{ __('games_catch_target') }}</span>
                    <template x-for="(ch, i) in target.split('')" :key="i">
                        <span class="text-xl font-bold" :class="i < pos ? 'text-cyan-300' : 'text-white/30'">
                            <span x-text="i < pos ? ch : '_'"></span>
                        </span>
                    </template>
                </p>
                <div x-show="over" class="mb-3 rounded-xl py-2 text-center text-sm font-bold"
                     :class="won ? 'bg-emerald-500/10 text-emerald-300' : 'bg-red-500/10 text-red-300'"
                     x-text="won ? '🏆 {{ __('games_catch_win') }}' : '💥 {{ __('games_catch_lose') }}'"></div>
                <div
                    class="relative h-72 overflow-hidden rounded-xl bg-black/30 p-2"
                    @mousemove="basketX = Math.min(0.95, Math.max(0.05, $event.offsetX / $el.offsetWidth))"
                    @touchmove.prevent="const r = $el.getBoundingClientRect(); basketX = Math.min(0.95, Math.max(0.05, ($event.touches[0].clientX - r.left) / r.width))"
                >
                    <template x-for="it in items" :key="it.id">
                        <div
                            class="absolute text-2xl"
                            :class="it.needed ? 'text-cyan-300' : 'text-white/60'"
                            :style="'left: calc(' + (it.col / (cols - 1) * 100) + '%); top: ' + it.y + '%'"
                            x-text="it.letter"
                        ></div>
                    </template>
                    <div
                        class="absolute bottom-1 flex h-10 w-16 items-center justify-center rounded-xl border-2 border-cyan-400/60 bg-cyan-500/20 text-xl transition-[left] duration-100"
                        :style="'left: calc(' + (basketX * 100) + '% - 32px)'"
                    >🧺</div>
                </div>
                <p class="mt-2 text-center text-xs text-white/50">{{ __('games_catch_hint') }}</p>
                <details class="mt-3 rounded-2xl bg-white/5 p-3">
                    <summary class="cursor-pointer select-none text-xs font-bold text-white/70">📖 {{ __('games_dactylo_rules') }}</summary>
                    <p class="mt-2 text-xs leading-relaxed text-white/60">{{ __('games_catch_rules') }}</p>
                </details>
                <button
                    type="button"
                    @click="replay()"
                    x-show="over"
                    class="mt-3 w-full rounded-xl bg-white/10 py-2 text-sm font-bold transition hover:bg-white/15"
                >{{ __('games_restart') }}</button>
            </div>
        </template>

        <template x-if="active === 'rps'">
            <div x-data="gameRps()" x-init="init()" class="glass mx-auto mt-10 w-full max-w-lg rounded-3xl p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold">✊ {{ __('games_rps_title') }}</h2>
                    <span class="rounded-xl bg-white/10 px-3 py-1 text-xs font-bold text-amber-300">🏆 <span x-text="best"></span></span>
                </div>
                <div class="mb-4 grid grid-cols-2 gap-2 text-center text-sm">
                    <div class="rounded-xl bg-white/5 py-2">
                        <span class="text-white/50">{{ __('games_you') }} :</span>
                        <span class="font-bold text-cyan-300" x-text="pScore"></span>
                    </div>
                    <div class="rounded-xl bg-white/5 py-2">
                        <span class="text-white/50">{{ __('games_cpu') }} :</span>
                        <span class="font-bold text-fuchsia-300" x-text="cScore"></span>
                    </div>
                </div>
                <div class="mb-4 flex h-24 items-center justify-center gap-6 text-5xl">
                    <span x-text="player ? player.emoji : '❓'" class="transition"></span>
                    <span class="text-2xl text-white/30">VS</span>
                    <span x-text="cpu ? cpu.emoji : '❓'" class="transition"></span>
                </div>
                <p x-show="result" class="mb-4 text-center text-lg font-bold"
                   :class="result === 'win' ? 'text-emerald-300' : result === 'lose' ? 'text-red-300' : 'text-white/60'"
                   x-text="result === 'win' ? '🎉 {{ __('games_win') }}' : result === 'lose' ? '😅 {{ __('games_lose') }}' : '🤝 {{ __('games_draw') }}'"></p>
                <div x-show="over" class="mb-4 rounded-xl bg-emerald-500/10 py-2 text-center text-sm font-bold text-emerald-300">
                    {{ __('games_rps_over') }}
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <template x-for="c in choices" :key="c.id">
                        <button
                            type="button"
                            @click="play(c.id)"
                            :disabled="over"
                            class="rounded-2xl bg-white/5 py-3 text-3xl transition hover:scale-105 hover:bg-white/10 active:scale-95 disabled:opacity-40"
                            :title="c.label"
                        ><span x-text="c.emoji"></span></button>
                    </template>
                </div>
                <button type="button" @click="reset()" class="mt-4 w-full rounded-xl bg-white/10 py-2 text-sm font-bold transition hover:bg-white/15">{{ __('games_restart') }}</button>
            </div>
        </template>

        <template x-if="active === 'morpion'">
            <div x-data="gameMorpion()" x-init="init()" class="glass mx-auto mt-10 w-full max-w-md rounded-3xl p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold">⭕ {{ __('games_morpion_title') }}</h2>
                    <span class="rounded-xl bg-white/10 px-3 py-1 text-xs font-bold text-amber-300">🏆 <span x-text="best"></span></span>
                </div>
                <p class="mb-4 text-center text-sm text-white/60" x-text="over ? (winner === 'X' ? '🎉 {{ __('games_win') }}' : winner === 'O' ? '😅 {{ __('games_lose') }}' : '🤝 {{ __('games_draw') }}') : '{{ __('games_morpion_turn') }}: ' + (turn === 'X' ? '❌' : '⭕')"></p>
                <div class="grid grid-cols-3 gap-2">
                    <template x-for="(cell, i) in board" :key="i">
                        <button
                            type="button"
                            @click="play(i)"
                            :disabled="over || !!cell || turn !== 'X'"
                            class="aspect-square rounded-2xl bg-white/5 text-4xl font-bold transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-60"
                            x-text="cell"
                            :class="cell === 'X' ? 'text-cyan-300' : cell === 'O' ? 'text-fuchsia-300' : ''"
                        ></button>
                    </template>
                </div>
                <div class="mt-4 flex items-center justify-between gap-2">
                    <span class="text-xs text-white/50">{{ __('games_morpion_score') }}: <b x-text="wins"></b></span>
                    <button type="button" @click="reset()" class="rounded-xl bg-white/10 px-4 py-2 text-sm font-bold transition hover:bg-white/15">{{ __('games_restart') }}</button>
                </div>
            </div>
        </template>

        <template x-if="active === 'memory'">
            <div x-data="gameMemory()" x-init="init()" class="glass mx-auto mt-10 w-full max-w-xl rounded-3xl p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold">🧠 {{ __('games_memory_title') }}</h2>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="rounded-xl bg-white/10 px-3 py-1 text-amber-300">🏆 <span x-text="best"></span></span>
                        <span class="rounded-xl bg-white/10 px-3 py-1">{{ __('games_level') }} <b x-text="level"></b>/3</span>
                    </div>
                </div>
                <div class="mb-4 grid grid-cols-3 gap-2 text-center text-xs text-white/60">
                    <div class="rounded-xl bg-white/5 py-2">{{ __('games_memory_moves') }}: <b class="text-white" x-text="moves"></b></div>
                    <div class="rounded-xl bg-white/5 py-2">{{ __('games_memory_time') }}: <b class="text-white" x-text="time + 's'"></b></div>
                    <div class="rounded-xl bg-white/5 py-2">{{ __('games_memory_pairs') }}: <b class="text-white" x-text="matched / 2 + '/' + cards.length / 2"></b></div>
                </div>
                <div class="grid grid-cols-4 gap-2 sm:grid-cols-6">
                    <template x-for="(card, i) in cards" :key="card.id">
                        <button
                            type="button"
                            @click="flip(i)"
                            class="flex aspect-square items-center justify-center rounded-xl text-2xl transition"
                            :class="card.matched || open.includes(i) ? 'bg-cyan-500/20' : 'bg-white/5 hover:bg-white/10'"
                            :disabled="card.matched"
                        >
                            <span x-show="card.matched || open.includes(i)" x-text="card.emoji"></span>
                            <span x-show="!card.matched && !open.includes(i)" class="text-white/20">?</span>
                        </button>
                    </template>
                </div>
                <div x-show="over" class="mt-4 rounded-xl bg-emerald-500/10 py-3 text-center text-sm font-bold text-emerald-300">
                    🎉 {{ __('games_memory_done') }}
                    <template x-if="level < 3">
                        <button type="button" @click="nextLevel()" class="ml-3 rounded-lg bg-emerald-500/20 px-3 py-1 font-bold transition hover:bg-emerald-500/30">{{ __('games_next_level') }} →</button>
                    </template>
                </div>
                <button type="button" @click="newGame()" class="mt-4 w-full rounded-xl bg-white/10 py-2 text-sm font-bold transition hover:bg-white/15">{{ __('games_restart') }}</button>
            </div>
        </template>

        <template x-if="active === 'snake'">
            <div x-data="gameSnake()" x-init="init()" class="glass mx-auto mt-10 w-full max-w-2xl rounded-3xl p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold">🐍 {{ __('games_snake_title') }}</h2>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="rounded-xl bg-white/10 px-3 py-1 text-amber-300">🏆 <span x-text="best"></span></span>
                        <span class="rounded-xl bg-white/10 px-3 py-1">⭐ <b x-text="score"></b></span>
                    </div>
                </div>
                <div x-show="!running && !over" class="mb-3 rounded-xl bg-cyan-500/10 py-2 text-center text-xs text-cyan-200">{{ __('games_snake_hint') }}</div>
                <div x-show="over" class="mb-3 rounded-xl bg-red-500/10 py-2 text-center text-xs font-bold text-red-300">{{ __('games_snake_over') }}</div>
                <div
                    class="grid gap-px overflow-hidden rounded-xl bg-black/30 p-1"
                    :style="'grid-template-columns: repeat(' + cols + ', 1fr)'"
                >
                    <template x-for="(cell, i) in cells" :key="i">
                        <div class="aspect-square text-center text-[10px] leading-none sm:text-sm" x-text="cell"></div>
                    </template>
                </div>
                <div class="mt-4 grid grid-cols-3 gap-2 sm:hidden">
                    <div></div>
                    <button type="button" @click="setDir(0, -1)" class="rounded-xl bg-white/10 py-2 text-lg transition hover:bg-white/15 active:scale-95">⬆</button>
                    <div></div>
                    <button type="button" @click="setDir(-1, 0)" class="rounded-xl bg-white/10 py-2 text-lg transition hover:bg-white/15 active:scale-95">⬅</button>
                    <button type="button" @click="setDir(0, 1)" class="rounded-xl bg-white/10 py-2 text-lg transition hover:bg-white/15 active:scale-95">⬇</button>
                    <button type="button" @click="setDir(1, 0)" class="rounded-xl bg-white/10 py-2 text-lg transition hover:bg-white/15 active:scale-95">➡</button>
                </div>
                <div class="mt-4 flex items-center justify-between gap-2">
                    <span class="text-xs text-white/40">{{ __('games_snake_keys') }}: ← ↑ → ↓</span>
                    <button type="button" @click="build(); start()" class="rounded-xl bg-white/10 px-4 py-2 text-sm font-bold transition hover:bg-white/15">{{ __('games_restart') }}</button>
                </div>
            </div>
        </template>
    </div>
</div>
