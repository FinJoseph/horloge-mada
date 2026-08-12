import { EMOJI_CATEGORIES, STICKERS, GIFS } from './emoji.js';
import './games.js';
import intersect from '@alpinejs/intersect';
import persist from '@alpinejs/persist';
import 'emoji-picker-element';
import emojiDataUrl from 'emoji-picker-element-data/en/emojibase/data.json?url';

function registerPlugins() {
    const A = window.Alpine;
    if (!A || A.__chatPlugins) return;
    A.__chatPlugins = true;
    A.plugin(intersect);
    if (!window.Alpine.__fromLivewire) {
        A.plugin(persist);
    }
}

if (window.Alpine) {
    registerPlugins();
} else {
    document.addEventListener('alpine:init', registerPlugins, { once: true });
}

const THEMES = [
    { id: 'classic', emoji: '🌙', name: 'Classic', mascot: '🐱' },
    { id: 'foot', emoji: '⚽', name: 'Foot', mascot: '⚽' },
    { id: 'fleur', emoji: '🌸', name: 'Fleur', mascot: '🌸' },
    { id: 'amour', emoji: '❤️', name: 'Amour', mascot: '🥰' },
    { id: 'tech', emoji: '💻', name: 'Tech', mascot: '🤖' },
    { id: 'cool', emoji: '🕶️', name: 'Cool', mascot: '😎' },
    { id: 'reggae', emoji: '🇯🇲', name: 'Reggae', mascot: '🦜' },
    { id: 'geek', emoji: '👾', name: 'Geek', mascot: '👾' },
    { id: 'animaux', emoji: '🐾', name: 'Animaux', mascot: '🦁' },
    { id: 'ocean', emoji: '🌊', name: 'Océan', mascot: '🐬' },
    { id: 'noel', emoji: '🎄', name: 'Noël', mascot: '🎅' },
    { id: 'nuit', emoji: '✨', name: 'Nuit', mascot: '🌙' },
];

const savedTheme = (() => {
    try {
        return localStorage.getItem('mada-theme') || 'classic';
    } catch {
        return 'classic';
    }
})();

document.documentElement.setAttribute('data-app-theme', savedTheme);

const INTL_LOCALES = { fr: 'fr-FR', mg: 'mg-MG', en: 'en-GB', hi: 'hi-IN', zh: 'zh-CN' };

function loadI18n() {
    const el = document.getElementById('i18n-config');
    if (!el) return {};
    try {
        return JSON.parse(el.textContent);
    } catch {
        return {};
    }
}

function loadShift() {
    const el = document.getElementById('shift-config');
    if (!el) return {};
    try {
        return JSON.parse(el.textContent);
    } catch {
        return {};
    }
}

const i18n = loadI18n();
const SHIFT = loadShift();
const INTL_LOCALE = INTL_LOCALES[i18n.locale] || 'fr-FR';

const DEFAULT_TZ = SHIFT.timezone || 'Indian/Antananarivo';

const TIMEZONES = [
    { id: 'Indian/Antananarivo', name: 'Madagascar', flag: '🇲🇬' },
    { id: 'Europe/Paris', name: 'Paris', flag: '🇫🇷' },
    { id: 'Europe/London', name: 'Londres', flag: '🇬🇧' },
    { id: 'Africa/Nairobi', name: 'Nairobi', flag: '🇰🇪' },
    { id: 'America/New_York', name: 'New York', flag: '🇺🇸' },
    { id: 'America/Los_Angeles', name: 'Los Angeles', flag: '🇺🇸' },
    { id: 'America/Sao_Paulo', name: 'São Paulo', flag: '🇧🇷' },
    { id: 'Asia/Tokyo', name: 'Tokyo', flag: '🇯🇵' },
    { id: 'Asia/Shanghai', name: 'Shanghai', flag: '🇨🇳' },
    { id: 'Asia/Kolkata', name: 'Mumbai', flag: '🇮🇳' },
    { id: 'Australia/Sydney', name: 'Sydney', flag: '🇦🇺' },
    { id: 'Etc/UTC', name: 'UTC', flag: '🌐' },
];

const TZ_CITY = Object.fromEntries(TIMEZONES.map((t) => [t.id, t.name]));

let currentTz = (() => {
    try {
        const saved = localStorage.getItem('mada-tz');
        return saved || DEFAULT_TZ;
    } catch {
        return DEFAULT_TZ;
    }
})();

let clockOffset = 0;
let clockSyncOk = true;

function tzOffset(tz) {
    try {
        const parts = new Intl.DateTimeFormat('en-GB', { timeZone: tz, timeZoneName: 'shortOffset' }).formatToParts(new Date());
        const name = (parts.find((p) => p.type === 'timeZoneName') || {}).value || '';
        return name.replace('GMT', 'UTC');
    } catch {
        return '';
    }
}

const MESSAGES = {
    before: [
        'Le travail commence à 07:00. Prépare-toi ! ⏰',
        'Encore un peu de repos, le shift arrive…',
        'Café prêt ? La journée va être belle ☕',
    ],
    morning: [
        'Courage, le café est chaud ☕',
        'Une conversation à la fois 💪',
        'Garde le rythme, tu gères 🔥',
        'Respire… tu es fort(e) 🧘',
        'Les pauses ne sont jamais loin 😉',
    ],
    lunch: [
        'Pause déjeuner ! 🍽️ Lâche tout et mange.',
        'Coupe-toi du travail, repose tes yeux 😌',
        'Bien manger, c’est bien finir la journée 🥗',
    ],
    afternoon: [
        'Dernière ligne droite ! 🚀',
        'Encore un petit effort 💪',
        'Tu tiens le coup, bravo 👏',
        'Le 19h approche… 🙌',
        'Presque fini, ne lâche rien !',
    ],
    after: [
        'FINI ! 🎉 C’est l’heure de rentrer !',
        'Aujourd’hui est terminé. Bravo 🏆',
        'Tu as assuré. À demain ! 👋',
    ],
};

const CAT_MOODS = { before: '😾', morning: '😼', lunch: '😸', afternoon: '🐈', after: '😻' };

function shiftToMin(value) {
    const [h, m] = value.split(':').map(Number);
    return h * 60 + m;
}

function createFormatter(locale, opts, fallback) {
    try {
        return new Intl.DateTimeFormat(locale, opts);
    } catch {
        return new Intl.DateTimeFormat(fallback, opts);
    }
}

const DAY_OPTS = {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hourCycle: 'h23',
    hour12: false,
};

const NUM_OPTS = {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
};

const fmtCache = new Map();

function partsFor(now, tz) {
    let f = fmtCache.get(tz);

    if (!f) {
        f = {
            day: createFormatter(INTL_LOCALE, { ...DAY_OPTS, timeZone: tz }, 'fr-FR'),
            num: createFormatter('en-CA', { ...NUM_OPTS, timeZone: tz }, 'en-CA'),
        };
        fmtCache.set(tz, f);
    }

    const raw = Object.fromEntries(f.day.formatToParts(now).map((x) => [x.type, x.value]));
    const n = Object.fromEntries(f.num.formatToParts(now).map((x) => [x.type, parseInt(x.value, 10)]));
    let hour = parseInt(raw.hour, 10);
    if (hour === 24) hour = 0;

    return {
        h: hour,
        m: parseInt(raw.minute, 10),
        s: parseInt(raw.second, 10),
        ms: now.getMilliseconds(),
        weekday: raw.weekday,
        date: `${raw.day} ${raw.month} ${raw.year}`,
        y: n.year,
        mo: n.month,
        d: n.day,
    };
}

function madaParts(now) {
    return partsFor(now, currentTz);
}

function phaseKeyFor(min, cfg) {
    const start = shiftToMin(cfg.start);
    const lunch = shiftToMin(cfg.lunch);
    const lunchEnd = lunch + (cfg.lunch_duration || 60);
    const end = shiftToMin(cfg.end);
    if (min < start) return 'before';
    if (min < lunch) return 'morning';
    if (min < lunchEnd) return 'lunch';
    if (min < end) return 'afternoon';
    return 'after';
}

function sageMessage(key, minOfDay, minute) {
    const sits = i18n['sit_' + key];
    const sages = i18n.sage;
    if (Array.isArray(sits) && Array.isArray(sages) && sits.length && sages.length) {
        const i = minOfDay % sits.length;
        const j = Math.floor(minOfDay / sits.length) % sages.length;
        return sits[i] + ' · ' + sages[j];
    }
    const list = (i18n['msg_' + key]) || MESSAGES[key];
    return list[minute % list.length];
}

window.tilt3d = (max = 8) => ({
    rx: 0,
    ry: 0,
    tilt(e) {
        const r = e.currentTarget.getBoundingClientRect();
        if (!r.width || !r.height) return;
        const px = (e.clientX - r.left) / r.width - 0.5;
        const py = (e.clientY - r.top) / r.height - 0.5;
        this.rx = (-py * max * 2).toFixed(2);
        this.ry = (px * max * 2).toFixed(2);
    },
    reset() {
        this.rx = 0;
        this.ry = 0;
    },
    tiltStyle() {
        return `transform: perspective(1100px) rotateX(${this.rx}deg) rotateY(${this.ry}deg)`;
    },
});

window.madaClock = () => {
    const start = shiftToMin(SHIFT.start);
    const lunch = shiftToMin(SHIFT.lunch);
    const lunchEnd = lunch + (SHIFT.lunch_duration || 60);
    const end = shiftToMin(SHIFT.end);

    return {
        cfg: SHIFT,
        i18n,
        now: new Date(),
        p: {},
        timer: null,
        syncTimer: null,
        soundOn: localStorage.getItem('mada-sound') !== 'off',
        appThemeId: savedTheme,
        audio: null,
        toast: null,
        toastTimer: null,
        lastReminderMin: -999,
        prevPhase: null,

        init() {
            this.tick();
            this.timer = setInterval(() => this.tick(), 100);
            this.sync();
            this.syncTimer = setInterval(() => this.sync(), 5 * 60 * 1000);
            document.addEventListener('pointerdown', () => this.unlockAudio(), { once: true });
            window.addEventListener('app-theme', (e) => {
                this.appThemeId = e.detail;
            });
            window.addEventListener('app-sound', (e) => {
                this.soundOn = e.detail;
                if (this.soundOn) {
                    this.unlockAudio();
                    this.beep(660, 0.1);
                }
            });
        },

        destroy() {
            clearInterval(this.timer);
            clearInterval(this.syncTimer);
        },

        async sync() {
            try {
                const res = await fetch('/api/time');
                if (!res.ok) {
                    throw new Error('time');
                }
                const data = await res.json();
                clockOffset = Math.round((data.unix * 1000) - Date.now());
                clockSyncOk = true;
            } catch {
                clockSyncOk = false;
            }
        },

        now() {
            return new Date(Date.now() + clockOffset);
        },

        syncOk() {
            return clockSyncOk;
        },

        unlockAudio() {
            this.audio = this.audio || new (window.AudioContext || window.webkitAudioContext)();
        },

        beep(freq, dur = 0.2, when = 0, type = 'sine', vol = 0.25) {
            if (!this.soundOn || !this.audio) return;
            const osc = this.audio.createOscillator();
            const gain = this.audio.createGain();
            osc.type = type;
            osc.frequency.value = freq;
            osc.connect(gain);
            gain.connect(this.audio.destination);
            const t = this.audio.currentTime + when;
            gain.gain.setValueAtTime(0.0001, t);
            gain.gain.exponentialRampToValueAtTime(vol, t + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, t + dur);
            osc.start(t);
            osc.stop(t + dur + 0.05);
        },

        chime() {
            this.beep(880, 0.15, 0);
            this.beep(1174.66, 0.15, 0.18);
            this.beep(1567.98, 0.25, 0.36);
        },

        fanfare() {
            const notes = [523.25, 659.25, 783.99, 1046.5];
            notes.forEach((f, i) => this.beep(f, 0.18, i * 0.15, 'triangle', 0.3));
        },

        tick() {
            this.now = this.now();
            this.p = this.parts();
            this.checkAlarms();
        },

        parts() {
            return madaParts(this.now);
        },

        cityName() {
            return TZ_CITY[currentTz] || currentTz;
        },

        nowMin() {
            return this.p.h * 60 + this.p.m;
        },

        weekday() {
            return this.p.weekday;
        },

        date() {
            return this.p.date;
        },

        phaseKey() {
            return phaseKeyFor(this.nowMin(), SHIFT);
        },

        phaseLabel() {
            const key = this.phaseKey();
            const labels = {
                before: 'Avant le travail',
                morning: 'Matin',
                lunch: 'Pause déjeuner',
                afternoon: 'Après-midi',
                after: 'Fin de travail',
            };
            return (i18n['clock_phase_' + key]) || labels[key] || key;
        },

        countdownLabel() {
            const min = this.nowMin();
            const targets = { before: start, morning: lunch, lunch: lunchEnd, afternoon: end };
            const target = targets[this.phaseKey()];
            if (target === undefined) return (i18n.clock_countdown_done) || 'C’est fini !';
            let seconds = (target - min) * 60 - this.p.s;
            if (seconds < 0) seconds = 0;
            const hh = Math.floor(seconds / 3600);
            const mm = Math.floor((seconds % 3600) / 60);
            const ss = seconds % 60;
            const pad = (n) => String(n).padStart(2, '0');
            return `${pad(hh)}:${pad(mm)}:${pad(ss)}`;
        },

        message() {
            return sageMessage(this.phaseKey(), this.nowMin(), this.p.m);
        },

        progressPct() {
            const work = end - start - (lunchEnd - lunch);
            let done = 0;
            if (this.nowMin() > start) done += Math.min(this.nowMin(), lunch) - start;
            if (this.nowMin() > lunchEnd) done += Math.min(this.nowMin(), end) - lunchEnd;
            const pct = Math.round((Math.max(0, Math.min(done, work)) / work) * 100);
            return Math.min(100, Math.max(0, pct));
        },

        lunchMarker() {
            const work = end - start - (lunchEnd - lunch);
            const untilLunch = lunch - start;
            return ((untilLunch / work) * 100).toFixed(1);
        },

        progressPctPrecise() {
            const secs = this.nowMin() * 60 + this.p.s + this.p.ms / 1000;
            const s = start * 60;
            const e = end * 60;
            const l = lunch * 60;
            const le = lunchEnd * 60;
            const work = e - s - (le - l);
            let done = 0;
            if (secs > s) done += Math.min(secs, l) - s;
            if (secs > le) done += Math.min(secs, e) - le;
            done = Math.max(0, Math.min(done, work));
            const pct = (done / work) * 100;
            return Math.min(100, Math.max(0, pct));
        },

        time() {
            const pad = (n) => String(n).padStart(2, '0');
            return `${pad(this.p.h)}:${pad(this.p.m)}:${pad(this.p.s)}`;
        },

        sky() {
            const h = this.p.h;
            if (h >= 5 && h < 7) return 'dawn';
            if (h >= 7 && h < 17) return 'day';
            if (h >= 17 && h < 19) return 'dusk';
            return 'night';
        },

        skyClass() {
            return `sky-${this.sky()}`;
        },

        sunStyle() {
            const min = this.nowMin();
            const dayStart = 6 * 60;
            const dayEnd = 18 * 60;
            let t = (min - dayStart) / (dayEnd - dayStart);
            t = Math.max(0, Math.min(1, t));
            return {
                left: `${(t * 100).toFixed(1)}%`,
                top: `${(60 - Math.sin(t * Math.PI) * 50).toFixed(1)}%`,
            };
        },

        liveDot() {
            const beat = Math.floor(this.p.ms / 500) % 2 === 0;
            return beat ? 'dot-on' : '';
        },

        paydayInfo() {
            const { y, mo, d } = this.p;
            const daysInMonth = new Date(Date.UTC(y, mo, 0)).getUTCDate();
            const days = d === 15 ? 0 : d < 15 ? 15 - d : daysInMonth - d + 15;
            return { today: d === 15, days };
        },

        paydayLabel() {
            const info = this.paydayInfo();
            if (info.today) return i18n.payday_today || 'C’est le jour de paie !';
            return (i18n.payday_in || 'Paie dans :days j').replace(':days', String(info.days));
        },

        toggleSound() {
            this.soundOn = !this.soundOn;
            localStorage.setItem('mada-sound', this.soundOn ? 'on' : 'off');
            if (this.soundOn) {
                this.unlockAudio();
                this.beep(660, 0.1);
            }
        },

        showToast(text) {
            this.toast = text;
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => (this.toast = null), 8000);
        },

        checkAlarms() {
            const key = this.phaseKey();
            const min = this.nowMin();

            if (key === 'lunch' && this.prevPhase === 'morning' && this.p.s < 2) {
                this.chime();
                this.showToast(i18n.toast_lunch || 'C’est l’heure de manger ! Pause déjeuner');
            }

            if (key === 'after' && this.prevPhase !== 'after') {
                this.fanfare();
                this.showToast(i18n.toast_done || 'C’est FINI ! Bonne fin de journée');
            }

            if (['morning', 'afternoon'].includes(key) && min - this.lastReminderMin >= 120) {
                this.lastReminderMin = min;
                this.beep(720, 0.2, 0);
                this.showToast(i18n.toast_break || 'Lève-toi 2 minutes : bois de l’eau, étire-toi !');
            }

            this.prevPhase = key;
        },
    };
};

window.settingsMenu = () => ({
    open: false,
    tab: 'lang',
    tabs: [
        { id: 'lang', emoji: '🌐', label: (i18n.settings_language) || 'Langue' },
        { id: 'tz', emoji: '🕒', label: (i18n.settings_timezone) || 'Fuseau horaire' },
        { id: 'theme', emoji: '🎨', label: (i18n.settings_theme) || 'Thème' },
        { id: 'sound', emoji: '🔊', label: (i18n.settings_sound) || 'Son' },
    ],
    langCurrent: i18n.locale || 'fr',
    languages: [
        { code: 'fr', flag: '🇫🇷', name: 'Français' },
        { code: 'mg', flag: '🇲🇬', name: 'Malagasy' },
        { code: 'en', flag: '🇬🇧', name: 'English' },
        { code: 'hi', flag: '🇮🇳', name: 'हिन्दी' },
        { code: 'zh', flag: '🇨🇳', name: '中文' },
    ],
    zones: TIMEZONES,
    tzCurrent: currentTz,
    themes: THEMES,
    themeCurrent: savedTheme,
    soundOn: localStorage.getItem('mada-sound') !== 'off',

    offsetFor(tz) {
        return tzOffset(tz);
    },
    setLocale(code) {
        document.cookie = `locale=${code}; path=/; max-age=31536000; samesite=lax`;
        window.location.reload();
    },
    setTz(id) {
        this.tzCurrent = id;
        currentTz = id;
        try {
            localStorage.setItem('mada-tz', id);
        } catch {
            /* noop */
        }
        this.open = false;
    },
    setTheme(id) {
        this.themeCurrent = id;
        try {
            localStorage.setItem('mada-theme', id);
        } catch {
            /* noop */
        }
        document.documentElement.setAttribute('data-app-theme', id);
        window.dispatchEvent(new CustomEvent('app-theme', { detail: id }));
    },
    toggleSound() {
        this.soundOn = !this.soundOn;
        try {
            localStorage.setItem('mada-sound', this.soundOn ? 'on' : 'off');
        } catch {
            /* noop */
        }
        window.dispatchEvent(new CustomEvent('app-sound', { detail: this.soundOn }));
    },
});

window.madaCat = () => {
    let vw = () => Math.max(360, window.innerWidth);
    let vh = () => Math.max(480, window.innerHeight);

    const rand = (min, max) => min + Math.random() * (max - min);
    const pick = () => ({
        x: rand(16, vw() - 96),
        y: rand(70, vh() - 120),
    });

    return {
        now: new Date(),
        p: {},
        timer: null,
        themeId: savedTheme,
        bubble: '',
        bubbleTimer: null,
        bubbleClear: null,
        roamTimer: null,
        facing: 1,
        pos: { left: '8px', top: 'calc(100% - 140px)', flip: '', transition: 'left 1s ease-out, top 1s ease-out' },

        init() {
            this.tick();
            this.timer = setInterval(() => this.tick(), 1000);
            this.showBubble();
            this.bubbleTimer = setInterval(() => this.showBubble(), 15000);
            setTimeout(() => this.roam(), 400);
            window.addEventListener('app-theme', (e) => {
                this.themeId = e.detail;
            });
        },

        destroy() {
            clearInterval(this.timer);
            clearInterval(this.bubbleTimer);
            clearTimeout(this.bubbleClear);
            clearTimeout(this.roamTimer);
        },

        tick() {
            this.now = new Date(Date.now() + clockOffset);
            this.p = madaParts(this.now);
        },

        mood() {
            const meta = THEMES.find((t) => t.id === this.themeId) || THEMES[0];
            return { key: phaseKeyFor(this.p.h * 60 + this.p.m, SHIFT), emoji: meta.mascot };
        },

        showBubble() {
            const key = phaseKeyFor(this.p.h * 60 + this.p.m, SHIFT);
            const sages = i18n.sage;
            this.bubble = Array.isArray(sages) && sages.length
                ? sages[(this.p.m + this.p.s) % sages.length]
                : (MESSAGES[key] ? MESSAGES[key][0] : 'Mrrrr 😸');
            clearTimeout(this.bubbleClear);
            this.bubbleClear = setTimeout(() => { this.bubble = ''; }, 8000);
        },

        roam() {
            const target = pick();
            const curX = parseFloat(this.pos.left) || 8;
            const jump = Math.random() < 0.22;
            const dur = rand(3200, 9500);
            const fall = jump ? dur * 0.45 : dur * rand(0.7, 1);

            this.facing = target.x >= curX ? 1 : -1;
            this.pos.flip = this.facing === -1 ? 'scaleX(-1)' : '';
            this.pos.transition = `left ${dur}ms cubic-bezier(.22,.7,.25,1), top ${fall}ms cubic-bezier(.4,.1,.6,.9)`;
            this.pos.left = `${target.x}px`;
            this.pos.top = `${target.y}px`;

            const pause = jump ? rand(600, 2200) : rand(250, 2600);
            this.roamTimer = setTimeout(() => this.roam(), Math.max(dur, fall) + pause);
        },
    };
};

const chatMeta = (() => {
    const el = document.getElementById('chat-config');
    if (!el) return { klipy: false, tenor: false, delete_confirm: 'Supprimer ce message ?', sender_id: '' };
    try {
        return JSON.parse(el.textContent);
    } catch {
        return { klipy: false, tenor: false, delete_confirm: 'Supprimer ce message ?', sender_id: '' };
    }
})();

const escRe = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

window.chatComposer = () => ({
    picker: false,
    pickerStyle: {},
    pollComposer: false,
    pollQuestion: '',
    pollOption: '',
    pollOptions: [],
    announceComposer: false,
    activeTab: window.Alpine.$persist('emoji'),
    soundOn: window.Alpine.$persist(true),
    category: null,
    categories: EMOJI_CATEGORIES,
    stickers: STICKERS,
    gifs: GIFS,
    chars: 0,
    scrolledUp: false,
    unread: 0,
    copied: false,
    lastTitle: document.title,
    audio: null,

    emojiDataSource: emojiDataUrl,

    replyTo: null,
    editingId: null,
    editingText: '',

    menuId: null,

    searchOpen: false,
    searchQ: '',
    searchEmpty: false,

    klipyOn: !!chatMeta.klipy,
    tenorOn: !!chatMeta.tenor,
    klipyGifs: [],
    klipyStickers: [],
    tenorStickers: [],
    gifQuery: '',
    stickerQuery: '',
    stickerSource: (() => {
        try {
            const saved = localStorage.getItem('mada-sticker-source');
            if (saved) return saved;
        } catch {
            /* noop */
        }
        if (chatMeta.tenor) return 'tenor';
        if (chatMeta.klipy) return 'klipy';
        return 'emoji';
    })(),
    gifLoaded: false,
    stickerLoaded: false,
    gifLoading: false,
    stickerLoading: false,
    gifFailed: false,
    stickerFailed: false,
    tenorLoaded: false,
    tenorLoading: false,
    tenorFailed: false,

    emojiUrl: (hex) => `https://fonts.gstatic.com/s/e/notoemoji/latest/${hex}/512.gif`,

    init() {
        this.lastTitle = document.title;
        document.addEventListener('pointerdown', () => this.unlockAudio(), { once: true });
        document.addEventListener('keydown', () => this.unlockAudio(), { once: true });
        document.addEventListener('visibilitychange', () => { if (!document.hidden) this.resetNotif(); });
    },

    destroy() {
        document.title = this.lastTitle;
    },

    unlockAudio() {
        if (!this.audio) {
            this.audio = new (window.AudioContext || window.webkitAudioContext)();
        }
        if (this.audio.state === 'suspended') {
            this.audio.resume().catch(() => {});
        }
    },

    beep(freq = 720, dur = 0.15) {
        if (!this.soundOn || !this.audio) return;
        const osc = this.audio.createOscillator();
        const gain = this.audio.createGain();
        osc.type = 'sine';
        osc.frequency.value = freq;
        osc.connect(gain);
        gain.connect(this.audio.destination);
        const t = this.audio.currentTime;
        gain.gain.setValueAtTime(0.0001, t);
        gain.gain.exponentialRampToValueAtTime(0.2, t + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, t + dur);
        osc.start(t);
        osc.stop(t + dur + 0.05);
    },

    resetNotif() {
        if (this.unread > 0 || document.title !== this.lastTitle) {
            this.unread = 0;
            document.title = this.lastTitle;
        }
    },

    addEmoji(emoji) {
        const unicode = Array.isArray(emoji) ? emoji[0] : emoji;
        if (!unicode) return;
        const input = this.$refs.msgInput;
        if (!input) return;
        const start = input.selectionStart ?? input.value.length;
        const end = input.selectionEnd ?? input.value.length;
        input.value = input.value.slice(0, start) + unicode + input.value.slice(end);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        this.chars = input.value.length;
        this.autoGrow(input);
        input.focus();
    },

    addSticker(hex) {
        this.addEmoji(` [[stk:${hex}]] `);
    },

    onEmojiClick(e) {
        this.addEmoji(e.detail.unicode);
    },

    autoGrow(el) {
        if (!el) return;
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 120) + 'px';
    },

    async togglePicker(e) {
        this.picker = !this.picker;
        this.pollComposer = false;
        this.announceComposer = false;
        if (this.picker) {
            this.$nextTick(() => this.placePicker(e));
        }
    },

    placePicker(e) {
        const panel = this.$refs.pickerPanel;
        if (!panel) return;
        if (window.innerWidth < 640) {
            this.pickerStyle = {};
            return;
        }
        const btn = (e && e.currentTarget) || this.$refs.pickerBtn;
        const rect = btn ? btn.getBoundingClientRect() : { left: 12, top: window.innerHeight - 60 };
        const size = Math.min(420, window.innerWidth - 16, window.innerHeight - 140);
        let left = rect.left;
        let bottom = window.innerHeight - rect.top + 10;
        if (left + size > window.innerWidth - 8) left = Math.max(8, window.innerWidth - size - 8);
        if (bottom > window.innerHeight - 8) bottom = 8;
        this.pickerStyle = {
            position: 'fixed',
            left: `${left}px`,
            bottom: `${bottom}px`,
            width: `${size}px`,
            height: `${size}px`,
            zIndex: 70,
        };
    },

    closePicker() {
        this.picker = false;
    },

    addPollOption() {
        const label = this.pollOption.trim().slice(0, 60);
        if (!label || this.pollOptions.length >= 12) return;
        this.pollOptions.push(label);
        this.pollOption = '';
        this.$nextTick(() => {
            const ta = this.$refs.msgInput;
            if (ta) ta.focus();
        });
    },

    removePollOption(i) {
        this.pollOptions.splice(i, 1);
    },

    publishPoll() {
        const question = this.pollQuestion.trim().slice(0, 160);
        if (!question || this.pollOptions.length < 2) return;
        const options = [...this.pollOptions];
        this.pollQuestion = '';
        this.pollOptions = [];
        this.pollOption = '';
        this.pollComposer = false;
        this.$wire.createPoll(question, options);
    },

    async submitChat() {
        if (this.editingId) {
            const id = this.editingId;
            const text = this.editingText.trim();
            if (!text) return;
            this.editingId = null;
            this.editingText = '';
            this.replyTo = null;
            this.$wire.updateMessage(id, text);
            return;
        }
        const input = this.$refs.msgInput;
        if (!input || !input.value.trim()) return;
        const text = input.value.trim();
        input.value = '';
        this.autoGrow(input);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        await this.$wire.set('message', text, true);
        const r = this.$refs.replyInput;
        if (r) {
            r.value = this.replyTo ? this.replyTo.id : '';
            r.dispatchEvent(new Event('input', { bubbles: true }));
        }
        this.chars = 0;
        this.replyTo = null;
        this.$wire.send();
    },

    _row(id) {
        return this.$root.querySelector(`.msg-row[data-id="${id}"]`);
    },

    toggleMenu(id) {
        this.menuId = this.menuId === id ? null : id;
        this.$nextTick(() => this.placeMenu(id));
    },

    placeMenu(id) {
        const wrap = this.$root.querySelector(`.msg-row[data-id="${id}"] .msg-menu-wrap`);
        const menu = wrap && wrap.querySelector('.msg-menu');
        const scroll = this.$refs.scroll;
        if (!wrap || !menu || !scroll || !menu.offsetHeight) return;
        wrap.classList.remove('open-up');
        const sr = scroll.getBoundingClientRect();
        const wr = wrap.getBoundingClientRect();
        const roomBelow = sr.bottom - wr.bottom - 8;
        const roomAbove = wr.top - sr.top - 8;
        const fitsBelow = roomBelow >= menu.offsetHeight;
        const fitsAbove = roomAbove >= menu.offsetHeight;
        if (fitsAbove && (!fitsBelow || roomAbove > roomBelow)) {
            wrap.classList.add('open-up');
        }
    },

    startReply(id) {
        const row = this._row(id);
        if (!row) return;
        this.replyTo = { id, user: row.dataset.user || '', text: row.dataset.text || '' };
        this.menuId = null;
        this.$nextTick(() => {
            const r = this.$refs.replyInput;
            if (r) {
                r.value = this.replyTo.id;
                r.dispatchEvent(new Event('input', { bubbles: true }));
            }
            const ta = this.$refs.msgInput;
            if (ta) ta.focus();
        });
    },

    clearReply() {
        this.replyTo = null;
        const r = this.$refs.replyInput;
        if (r) {
            r.value = '';
            r.dispatchEvent(new Event('input', { bubbles: true }));
        }
    },

    startEdit(id) {
        const row = this._row(id);
        if (!row) return;
        this.editingId = id;
        this.editingText = row.dataset.text || '';
        this.replyTo = null;
        this.menuId = null;
        this.picker = false;
        this.$nextTick(() => {
            const ta = this.$refs.editInput;
            if (ta) {
                ta.focus();
                ta.setSelectionRange(ta.value.length, ta.value.length);
                this.autoGrow(ta);
            }
        });
    },

    cancelEdit() {
        this.editingId = null;
        this.editingText = '';
    },

    async copyRow(id) {
        const row = this._row(id);
        if (!row) return;
        try {
            await navigator.clipboard.writeText(row.dataset.text || '');
            this.copied = true;
            clearTimeout(this._copyTimer);
            this._copyTimer = setTimeout(() => (this.copied = false), 1600);
        } catch {
            /* clipboard indisponible */
        }
        this.menuId = null;
    },

    deleteMsg(id) {
        this.menuId = null;
        if (window.confirm(chatMeta.delete_confirm)) {
            this.$wire.deleteMessage(id);
        }
    },

    forwardMsg(id) {
        this.menuId = null;
        this.$wire.forwardMessage(id);
        this.scrollToBottom();
    },

    reactFromMenu(id, emoji) {
        this.menuId = null;
        this.$wire.react(id, emoji);
    },

    toggleSearch() {
        this.searchOpen = !this.searchOpen;
        if (this.searchOpen) {
            this.$nextTick(() => { const i = this.$refs.searchInput; if (i) i.focus(); });
        } else {
            this.searchQ = '';
            this.applySearch();
        }
    },

    applySearch() {
        const q = this.searchQ.trim().toLowerCase();
        const rows = this.$root.querySelectorAll('.msg-row');
        let shown = 0;
        rows.forEach((r) => {
            const text = (r.dataset.text || '').toLowerCase();
            const visible = q ? text.includes(q) : true;
            r.style.display = visible ? '' : 'none';
            if (visible) shown++;
        });
        this.searchEmpty = q !== '' && shown === 0;
        this._highlight(q);
    },

    _highlight(q) {
        const root = this.$refs.scroll;
        if (!root) return;
        root.querySelectorAll('mark.chat-search-hit').forEach((m) => {
            const t = document.createTextNode(m.textContent);
            m.replaceWith(t);
        });
        if (!q) return;
        const re = new RegExp(`(${escRe(q)})`, 'gi');
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: (node) => {
                const p = node.parentElement;
                return p && (p.closest('.chat-meta') || p.tagName === 'MARK' || p.closest('video'))
                    ? NodeFilter.FILTER_REJECT
                    : NodeFilter.FILTER_ACCEPT;
            },
        });
        const targets = [];
        while (walker.nextNode()) targets.push(walker.currentNode);
        targets.forEach((node) => {
            const parent = node.parentElement;
            if (!parent) return;
            const frag = document.createDocumentFragment();
            let last = 0;
            let m;
            const text = node.nodeValue;
            while ((m = re.exec(text))) {
                if (m.index > last) frag.appendChild(document.createTextNode(text.slice(last, m.index)));
                const mark = document.createElement('mark');
                mark.className = 'chat-search-hit';
                mark.textContent = m[1];
                frag.appendChild(mark);
                last = m.index + m[1].length;
            }
            if (!last) return;
            if (last < text.length) frag.appendChild(document.createTextNode(text.slice(last)));
            parent.replaceChild(frag, node);
        });
    },

    liveDot() {
        return Math.floor(Date.now() / 500) % 2 === 0 ? 'dot-on' : '';
    },

    nearBottom() {
        const el = this.$refs.scroll;
        if (!el) return true;
        return el.scrollHeight - el.scrollTop - el.clientHeight < 60;
    },

    watchScroll() {
        const el = this.$refs.scroll;
        if (!el) return;
        const onScroll = () => {
            this.scrolledUp = !this.nearBottom();
            if (!this.scrolledUp) this.unread = 0;
        };
        el.addEventListener('scroll', onScroll, { passive: true });

        const obs = new MutationObserver(() => {
            const prev = this._lastCount ?? 0;
            const rows = el.querySelectorAll('.msg-row');
            const count = rows.length;
            const bottom = this.nearBottom();
            if (count > prev) {
                const incoming = [...rows].slice(prev).filter((r) => r.dataset.uid !== chatMeta.sender_id);
                if (incoming.length) {
                    if (!bottom || document.hidden) this.unread += incoming.length;
                    if (this.soundOn) this.beep();
                    if (this.unread > 0) document.title = `(${this.unread}) ${this.lastTitle}`;
                }
            }
            this._lastCount = count;
            if (bottom) el.scrollTop = el.scrollHeight;
            if (this.searchOpen) this.applySearch();
        });
        obs.observe(el, { childList: true, subtree: true });

        this._lastCount = el.querySelectorAll('.msg-row').length;
        el.scrollTop = el.scrollHeight;
        onScroll();
    },

    scrollToBottom() {
        const el = this.$refs.scroll;
        if (!el) return;
        el.scrollTop = el.scrollHeight;
        this.unread = 0;
        this.scrolledUp = false;
        this.resetNotif();
    },

    async copyMessage(event) {
        if (event.target.closest('a, button, .chat-meta, .stk-inline, .chat-reply-quote, .msg-menu-btn')) return;
        const bubble = event.target.closest('.chat-bubble');
        if (!bubble) return;
        const row = bubble.closest('.msg-row');
        const text = bubble.dataset.text || (row ? row.dataset.text : '');
        if (!text) return;
        try {
            await navigator.clipboard.writeText(text);
            this.copied = true;
            clearTimeout(this._copyTimer);
            this._copyTimer = setTimeout(() => (this.copied = false), 1600);
        } catch {
            /* clipboard indisponible */
        }
    },

    async loadKlipy(kind) {
        if (!this.klipyOn || this[kind + 'Loaded']) return;
        this[kind + 'Loading'] = true;
        this[kind + 'Failed'] = false;
        try {
            const res = await this.$wire.trendingMedia(kind === 'klipyGifs' ? 'gif' : 'sticker');
            if (Array.isArray(res)) this[kind] = res;
        } catch {
            this[kind + 'Failed'] = true;
        }
        this[kind + 'Loading'] = false;
    },

    async searchKlipy(kind) {
        if (!this.klipyOn) return;
        const q = (kind === 'klipyGifs' ? this.gifQuery : this.stickerQuery).trim();
        if (!q) return;
        this[kind + 'Loading'] = true;
        try {
            const res = await this.$wire.searchMedia(q, kind === 'klipyGifs' ? 'gif' : 'sticker');
            if (Array.isArray(res)) this[kind] = res;
        } catch {
            /* noop */
        }
        this[kind + 'Loading'] = false;
    },

    setStickerSource(src) {
        if (!this[src === 'klipy' ? 'klipyOn' : src === 'tenor' ? 'tenorOn' : true]) return;
        this.stickerSource = src;
        try {
            localStorage.setItem('mada-sticker-source', src);
        } catch {
            /* noop */
        }
        if (src === 'tenor' && !this.tenorLoaded && !this.tenorLoading) {
            this.loadTenor();
        }
    },

    async loadTenor() {
        if (!this.tenorOn || this.tenorLoaded) return;
        this.tenorLoading = true;
        this.tenorFailed = false;
        try {
            const res = await fetch('/api/stickers');
            const data = await res.json();
            if (Array.isArray(data.results)) {
                this.tenorStickers = data.results;
                this.tenorLoaded = true;
            }
        } catch {
            this.tenorFailed = true;
        }
        this.tenorLoading = false;
    },

    async searchTenor() {
        const q = this.stickerQuery.trim();
        if (!q) return;
        this.tenorLoading = true;
        this.tenorFailed = false;
        try {
            const res = await fetch('/api/stickers?q=' + encodeURIComponent(q));
            const data = await res.json();
            this.tenorStickers = Array.isArray(data.results) ? data.results : [];
            this.tenorLoaded = true;
        } catch {
            this.tenorFailed = true;
        }
        this.tenorLoading = false;
    },

    sendMedia(item, type) {
        if (!item || !item.url) return;
        this.$wire.sendMedia(item.url, item.preview || '', type, item.alt || '');
        this.picker = false;
    },

    playGif(e) {
        const img = e.currentTarget;
        if (!img || !img.dataset.gif) return;
        img.src = img.dataset.gif;
    },
});

window.hidePreloader = () => {
    const el = document.getElementById('preloader');
    if (!el) return;
    const percent = el.querySelector('.pre-percent');
    if (percent) percent.textContent = '100%';
    el.classList.add('hide');
    setTimeout(() => el.remove(), 700);
};

window.addEventListener('load', () => setTimeout(window.hidePreloader, 350));
setTimeout(window.hidePreloader, 6000);

if (window.Alpine && !window.Alpine.__fromLivewire) {
    window.Alpine.start();
}
