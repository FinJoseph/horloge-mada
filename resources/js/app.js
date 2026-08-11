import { EMOJI_CATEGORIES, STICKERS, GIFS } from './emoji.js';

const MADA_TZ = 'Indian/Antananarivo';

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
const CITY = i18n.clock_city || 'Fianarantsoa';

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

const dayFormatter = createFormatter(INTL_LOCALE, {
    timeZone: MADA_TZ,
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hourCycle: 'h23',
    hour12: false,
}, 'fr-FR');

const numFormatter = createFormatter('en-CA', {
    timeZone: MADA_TZ,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
}, 'en-CA');

function madaParts(now) {
    const raw = Object.fromEntries(dayFormatter.formatToParts(now).map((x) => [x.type, x.value]));
    const n = Object.fromEntries(numFormatter.formatToParts(now).map((x) => [x.type, parseInt(x.value, 10)]));
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
        city: CITY,
        now: new Date(),
        p: {},
        timer: null,
        soundOn: localStorage.getItem('mada-sound') !== 'off',
        audio: null,
        toast: null,
        toastTimer: null,
        lastReminderMin: -999,
        prevPhase: null,

        init() {
            this.tick();
            this.timer = setInterval(() => this.tick(), 100);
            document.addEventListener('pointerdown', () => this.unlockAudio(), { once: true });
        },

        destroy() {
            clearInterval(this.timer);
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
            this.now = new Date();
            this.p = this.parts();
            this.checkAlarms();
        },

        parts() {
            return madaParts(this.now);
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

window.languagePicker = () => ({
    open: false,
    current: i18n.locale || 'fr',
    languages: [
        { code: 'fr', flag: '🇫🇷', name: 'Français' },
        { code: 'mg', flag: '🇲🇬', name: 'Malagasy' },
        { code: 'en', flag: '🇬🇧', name: 'English' },
        { code: 'hi', flag: '🇮🇳', name: 'हिन्दी' },
        { code: 'zh', flag: '🇨🇳', name: '中文' },
    ],
    setLocale(code) {
        document.cookie = `locale=${code}; path=/; max-age=31536000; samesite=lax`;
        window.location.reload();
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
        },

        destroy() {
            clearInterval(this.timer);
            clearInterval(this.bubbleTimer);
            clearTimeout(this.bubbleClear);
            clearTimeout(this.roamTimer);
        },

        tick() {
            this.now = new Date();
            this.p = madaParts(this.now);
        },

        mood() {
            const key = phaseKeyFor(this.p.h * 60 + this.p.m, SHIFT);
            return { key, emoji: CAT_MOODS[key] || '🐱' };
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

window.chatComposer = () => ({
    picker: false,
    activeTab: 'emoji',
    category: EMOJI_CATEGORIES[0].id,
    categories: EMOJI_CATEGORIES,
    stickers: STICKERS,
    gifs: GIFS,
    emojiUrl: (hex) => `https://fonts.gstatic.com/s/e/notoemoji/latest/${hex}/512.gif`,
    addEmoji(emoji) {
        const input = this.$refs.msgInput;
        if (!input) return;
        const start = input.selectionStart ?? input.value.length;
        const end = input.selectionEnd ?? input.value.length;
        input.value = input.value.slice(0, start) + emoji + input.value.slice(end);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.focus();
    },
    addSticker(hex) {
        this.addEmoji(` [[stk:${hex}]] `);
    },
    liveDot() {
        return Math.floor(Date.now() / 500) % 2 === 0 ? 'dot-on' : '';
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
