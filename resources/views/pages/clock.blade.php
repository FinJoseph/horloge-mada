<?php

use Livewire\Component;

new class extends Component
{
    public function i18nJson(): string
    {
        $file = lang_path(app()->getLocale().'.json');

        if (! is_file($file)) {
            $file = lang_path('fr.json');
        }

        return (string) file_get_contents($file);
    }
};
?>

<div
    x-data="madaClock()"
    x-init="init()"
    class="relative min-h-screen overflow-hidden bg-[#0b1026]"
    :data-theme="sky()"
>
    <div hidden id="shift-config">{{ json_encode(config('shift')) }}</div>
    <div hidden id="i18n-config">{!! $this->i18nJson() !!}</div>

    <div class="sky scene-perspective absolute inset-0 transition-colors duration-1000" :class="skyClass()">
        <div class="stars absolute inset-0" :class="sky() === 'night' ? 'opacity-100' : 'opacity-30'">
            <i class="star" style="top: 12%; left: 8%"></i>
            <i class="star" style="top: 20%; left: 22%; animation-delay: .8s"></i>
            <i class="star" style="top: 9%; left: 38%; animation-delay: 1.6s"></i>
            <i class="star" style="top: 18%; left: 55%; animation-delay: .4s"></i>
            <i class="star" style="top: 8%; left: 72%; animation-delay: 2s"></i>
            <i class="star" style="top: 15%; left: 88%; animation-delay: 1.2s"></i>
            <i class="star" style="top: 30%; left: 15%; animation-delay: 2.4s"></i>
            <i class="star" style="top: 27%; left: 64%; animation-delay: .6s"></i>
        </div>
        <div
            x-show="['dawn', 'day', 'dusk'].includes(sky())"
            x-transition:enter="transition-opacity duration-1000"
            class="sun absolute -translate-x-1/2 -translate-y-1/2 transition-all duration-1000"
            :style="sunStyle()"
        >
            <div class="sun-rays"></div>
        </div>
        <div
            x-show="sky() === 'night'"
            x-transition:enter="transition-opacity duration-1000"
            class="moon absolute -translate-x-1/2 -translate-y-1/2 transition-all duration-1000"
            :style="sunStyle()"
        ></div>
        <div class="haze absolute inset-x-0 bottom-0 h-2/3"></div>
    </div>

    <div class="relative z-10 mx-auto flex min-h-screen max-w-7xl flex-col px-4 py-6 sm:px-8">
        <header class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="glass flex h-11 w-11 items-center justify-center rounded-2xl">
                    <x-icon name="clock" class="h-6 w-6 text-cyan-300" />
                </div>
                <div>
                    <h1 class="text-lg font-bold tracking-tight">Horloge Mada</h1>
                    <p class="text-xs text-white/60" x-text="city + ' · ' + weekday() + ' ' + date()"></p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div x-data="languagePicker()" class="relative">
                    <button
                        type="button"
                        @click="open = !open"
                        class="glass flex h-11 items-center gap-2 rounded-2xl px-3 text-sm transition hover:scale-105 active:scale-95"
                        :title="'🌐 ' + current"
                    >
                        <x-icon name="globe" class="h-5 w-5" />
                        <span class="font-bold uppercase tracking-wider" x-text="current"></span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-white/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div
                        x-show="open"
                        @click.away="open = false"
                        x-transition
                        class="dropdown-panel absolute right-0 z-40 mt-2 w-52 overflow-hidden rounded-2xl p-1.5"
                    >
                        <template x-for="lang in languages" :key="lang.code">
                            <button
                                type="button"
                                @click="setLocale(lang.code)"
                                class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-sm transition hover:bg-white/10"
                                :class="lang.code === current ? 'bg-white/10 font-bold' : ''"
                            >
                                <span class="text-base" x-text="lang.flag"></span>
                                <span x-text="lang.name"></span>
                                <span class="ml-auto text-xs text-white/40 uppercase" x-text="lang.code"></span>
                            </button>
                        </template>
                    </div>
                </div>
                <button
                    type="button"
                    @click="toggleSound()"
                    class="glass h-11 w-11 rounded-2xl text-lg transition hover:scale-105 active:scale-95"
                    :title="soundOn ? i18n.clock_sound_on : i18n.clock_sound_off"
                >
                    <span x-show="soundOn"><x-icon name="bell" class="mx-auto h-5 w-5" /></span>
                    <span x-show="!soundOn" class="opacity-50"><x-icon name="bell-off" class="mx-auto h-5 w-5" /></span>
                </button>
                <div class="glass hidden rounded-2xl px-4 py-2 text-sm text-white/80 sm:block">
                    <span class="font-semibold text-white" x-text="phaseLabel()"></span>
                </div>
            </div>
        </header>

        <main class="mt-6 grid flex-1 gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
            <section class="flex flex-col gap-6">
                <div
                    class="clock-panel glass rise-in relative flex min-h-0 flex-1 flex-col overflow-hidden rounded-[2rem] p-6 sm:p-10"
                    x-data="tilt3d(7)"
                    @mousemove="tilt($event)"
                    @mouseleave="reset()"
                    :style="tiltStyle()"
                >
                    <div class="holo-scan pointer-events-none absolute inset-x-0 h-40 opacity-40"></div>

                    <div class="flex flex-1 flex-col items-center gap-6">
                        <div class="digital text-7xl font-bold tabular-nums tracking-tight sm:text-8xl" x-text="time()"></div>

                        <div class="flex flex-wrap items-center justify-center gap-3 text-white/70">
                            <span class="date-pill glass flex items-center gap-2 rounded-full px-4 py-2 text-sm">
                                <x-icon name="calendar" class="h-4 w-4" />
                                <span x-text="weekday() + ' ' + date()"></span>
                            </span>
                            <span class="payday-pill glass flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold">
                                <x-icon name="banknote" class="h-4 w-4" />
                                <span x-text="paydayLabel()"></span>
                            </span>
                        </div>

                        <div class="w-full">
                            <div class="mb-2 flex items-end justify-between text-sm">
                                <span class="font-medium text-white/70">{{ __('clock_work_day') }}</span>
                                <span class="font-bold tabular-nums" x-text="progressPct() + '%'"></span>
                            </div>
                            <div class="progress-track relative h-4 overflow-hidden rounded-full">
                                <div class="progress-bar relative h-full overflow-hidden rounded-full transition-all duration-1000" :style="'width: ' + progressPct() + '%'"></div>
                                <div class="marker absolute top-0 h-full w-1 rounded-full bg-white/80" :style="'left: ' + lunchMarker() + '%'"></div>
                            </div>
                            <div class="mt-2 flex justify-between text-xs text-white/50">
                                <span x-text="cfg.start"></span>
                                <span class="flex items-center gap-1"><x-icon name="utensils" class="h-3.5 w-3.5" /> <span x-text="cfg.lunch"></span></span>
                                <span x-text="cfg.end"></span>
                            </div>
                        </div>

                        <div class="mt-auto grid w-full gap-4 sm:grid-cols-3">
                            <div class="stat rounded-2xl bg-white/5 p-4 text-center ring-1 ring-white/10">
                                <div class="text-xs uppercase tracking-widest text-white/50">{{ __('clock_state') }}</div>
                                <div class="mt-2 flex items-center justify-center gap-1.5 text-lg font-bold">
                                    <x-icon name="sunrise" class="h-5 w-5" x-show="phaseKey() === 'before'" />
                                    <x-icon name="sun" class="h-5 w-5" x-show="phaseKey() === 'morning'" />
                                    <x-icon name="utensils" class="h-5 w-5" x-show="phaseKey() === 'lunch'" />
                                    <x-icon name="flame" class="h-5 w-5" x-show="phaseKey() === 'afternoon'" />
                                    <x-icon name="party-popper" class="h-5 w-5" x-show="phaseKey() === 'after'" />
                                    <span x-text="phaseLabel()"></span>
                                </div>
                            </div>
                            <div class="stat rounded-2xl bg-white/5 p-4 text-center ring-1 ring-white/10">
                                <div class="text-xs uppercase tracking-widest text-white/50">{{ __('clock_countdown') }}</div>
                                <div class="mt-1 text-lg font-bold tabular-nums" x-text="countdownLabel()"></div>
                            </div>
                            <div class="stat rounded-2xl bg-white/5 p-4 text-center ring-1 ring-white/10">
                                <div class="text-xs uppercase tracking-widest text-white/50">{{ __('clock_message') }}</div>
                                <div class="mt-1 text-sm font-semibold" x-text="message()"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="flex flex-col gap-6">
                <div class="glass rise-in flex flex-1 flex-col rounded-2xl p-5" style="animation-delay: .12s">
                    <h2 class="flex items-center gap-2 text-sm font-bold uppercase tracking-widest text-white/70"><x-icon name="settings" class="h-4 w-4" /> {{ __('clock_my_shift') }}</h2>
                    <div class="mt-3 flex flex-1 flex-col justify-between gap-2 text-sm text-white/80">
                        <div class="flex items-center justify-between"><span class="flex items-center gap-2"><x-icon name="play" class="h-3.5 w-3.5 text-white/50" /> {{ __('clock_start') }}</span><span class="font-semibold tabular-nums" x-text="cfg.start"></span></div>
                        <div class="flex items-center justify-between"><span class="flex items-center gap-2"><x-icon name="coffee" class="h-3.5 w-3.5 text-white/50" /> {{ __('clock_lunch_break') }}</span><span class="font-semibold tabular-nums" x-text="cfg.lunch"></span></div>
                        <div class="flex items-center justify-between"><span class="flex items-center gap-2"><x-icon name="flag" class="h-3.5 w-3.5 text-white/50" /> {{ __('clock_end') }}</span><span class="font-semibold tabular-nums" x-text="cfg.end"></span></div>
                        <div class="flex items-center justify-between"><span class="flex items-center gap-2"><x-icon name="timer" class="h-3.5 w-3.5 text-white/50" /> {{ __('clock_duration') }}</span><span class="font-semibold tabular-nums">12h</span></div>
                    </div>
                </div>

                <div
                    class="author-card glass-3d rise-in flex flex-1 flex-col items-center justify-center rounded-2xl p-5 text-center"
                    style="animation-delay: .22s"
                    x-data="tilt3d(9)"
                    @mousemove="tilt($event)"
                    @mouseleave="reset()"
                    :style="tiltStyle()"
                >
                    <i class="author-spark" style="top: 18%; left: 14%; animation-delay: .2s"></i>
                    <i class="author-spark" style="top: 30%; right: 16%; animation-delay: 1.4s"></i>
                    <i class="author-spark" style="top: 66%; left: 12%; animation-delay: 2.6s"></i>
                    <i class="author-spark" style="top: 74%; right: 22%; animation-delay: 3.4s"></i>
                    <div class="author-avatar mx-auto">
                        <x-icon name="code" class="h-7 w-7 text-white" />
                    </div>
                    <p class="mt-3 flex items-center justify-center gap-1 text-sm text-white/70">{{ __('footer_created_by') }} <x-icon name="heart" class="h-4 w-4 text-rose-400" /> par</p>
                    <p class="mt-1 text-lg font-bold">Fin Joseph</p>
                    <div class="mt-3 flex justify-center gap-2">
                        <a
                            href="https://finjoseph.onrender.com/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn-modern btn-portfolio flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-white"
                        ><x-icon name="link" class="h-4 w-4" /> Portfolio</a>
                        <a
                            href="https://github.com/FinJoseph"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn-modern btn-github flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-white"
                        ><x-icon name="github" class="h-4 w-4" /> GitHub</a>
                    </div>
                </div>
            </aside>
        </main>

        <div class="mt-6">
            <livewire:chat />
        </div>

        <footer class="mt-6 pb-2 text-center text-xs text-white/40">
            <x-icon name="clock" class="mb-0.5 inline-block h-3.5 w-3.5" /> {{ __('clock_footer') }} · {{ __('footer_created_by') }}
            <x-icon name="heart" class="mb-0.5 inline-block h-3 w-3 text-rose-400" />
            <a href="https://github.com/FinJoseph" target="_blank" rel="noopener noreferrer" class="underline decoration-white/30 underline-offset-2 hover:text-white/70">Fin Joseph</a>
        </footer>
    </div>

    <div x-data="madaCat()" x-init="init()" class="cat-wrap pointer-events-none fixed left-0 top-0 z-40" aria-hidden="true" :style="pos">
        <div class="cat-bubble" x-show="bubble" x-transition x-text="bubble"></div>
        <div class="cat-stage" :style="{ transform: pos.flip }">
            <div class="cat-body" x-text="mood().emoji"></div>
        </div>
        <div class="cat-shadow"></div>
    </div>
</div>
