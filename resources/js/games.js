function localGet(key, fallback) {
    try {
        const raw = localStorage.getItem(key);
        return raw === null ? fallback : Number(raw);
    } catch {
        return fallback;
    }
}

function localSet(key, val) {
    try {
        localStorage.setItem(key, String(val));
    } catch {
        /* noop */
    }
}

function shuffle(arr) {
    const a = arr.slice();
    for (let i = a.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
}

const DACTYLO_POOLS = {
    1: ['oui', 'non', 'sala', 'hora', 'tana', 'rano', 'asa', 'mada', 'stop', 'teny', 'vita', 'chat'],
    2: ['bonjour', 'merci', 'tsara', 'fiasa', 'pause', 'appel', 'equipe', 'veloma', 'sinoa', 'manto', 'maso', 'vala'],
    3: ['travail', 'message', 'fiteny', 'salama', 'journee', 'valiny', 'mifona', 'mpiasa', 'collegue', 'clients', 'trano', 'faingana'],
    4: ['telephone', 'assistance', 'production', 'responsable', 'operateur', 'miarahaba', 'horloge', 'finance', 'progres', 'fandrakofana'],
    5: ['collaborateur', 'professionnel', 'organisation', 'coordination', 'tanindrazana', 'mpiandraikitra', 'mieritreritra', 'fampandrosoana', 'perfectionnement', 'responsabilite'],
};

window.gamesHub = () => ({
    active: null,
    best: {
        dactylo: localGet('mada-best-dactylo-course', 0),
        anagram: localGet('mada-best-anagram', 0),
        catch: localGet('mada-best-catch', 0),
        rps: localGet('mada-best-rps', 0),
        morpion: localGet('mada-best-morpion', 0),
        memory: localGet('mada-best-memory', 0),
        snake: localGet('mada-best-snake', 0),
    },
    open(id) {
        this.active = id;
    },
    back() {
        this.active = null;
        this.refreshBest();
    },
    refreshBest() {
        this.best = {
            dactylo: localGet('mada-best-dactylo-course', 0),
            anagram: localGet('mada-best-anagram', 0),
            catch: localGet('mada-best-catch', 0),
            rps: localGet('mada-best-rps', 0),
            morpion: localGet('mada-best-morpion', 0),
            memory: localGet('mada-best-memory', 0),
            snake: localGet('mada-best-snake', 0),
        };
    },
});

window.gameDactylo = () => {
    let mode = 'course';
    try {
        mode = localStorage.getItem('mada-dactylo-mode') || 'course';
    } catch {
        /* noop */
    }
    let style = 'neon';
    try {
        style = localStorage.getItem('mada-dactylo-style') || 'neon';
    } catch {
        /* noop */
    }

    return {
        mode,
        level: 1,
        style,
        screen: 'menu',
        modes: [
            { id: 'course', emoji: '⚡', name: 'course' },
            { id: 'survie', emoji: '🛡️', name: 'survie' },
            { id: 'deft', emoji: '🏆', name: 'deft' },
        ],
        styles: [
            { id: 'neon', emoji: '🌃', name: 'neon' },
            { id: 'retro', emoji: '👾', name: 'retro' },
            { id: 'ocean', emoji: '🌊', name: 'ocean' },
            { id: 'sunset', emoji: '🌅', name: 'sunset' },
        ],
        best: { course: 0, survie: 0, deft: 0 },
        word: '',
        typed: '',
        wordList: [],
        wordIdx: 0,
        score: 0,
        combo: 0,
        bestCombo: 0,
        errors: 0,
        chars: 0,
        elapsed: 0,
        timeLeft: 60,
        lives: 3,
        kills: 0,
        falling: [],
        wpm: 0,
        precision: 100,
        finished: false,
        clockTimer: null,
        spawnTimer: null,
        loopTimer: null,

        init() {
            this.best.course = localGet('mada-best-dactylo-course', 0);
            this.best.survie = localGet('mada-best-dactylo-survie', 0);
            this.best.deft = localGet('mada-best-dactylo-deft', 0);
        },
        destroy() {
            this.stopAll();
        },
        stopAll() {
            if (this.clockTimer) clearInterval(this.clockTimer);
            if (this.spawnTimer) clearInterval(this.spawnTimer);
            if (this.loopTimer) clearInterval(this.loopTimer);
            this.clockTimer = null;
            this.spawnTimer = null;
            this.loopTimer = null;
        },
        setMode(id) {
            if (this.screen !== 'play' && this.modes.some((m) => m.id === id)) {
                this.mode = id;
                try {
                    localStorage.setItem('mada-dactylo-mode', id);
                } catch {
                    /* noop */
                }
            }
        },
        setLevel(l) {
            if (this.screen !== 'play') this.level = l;
        },
        setStyle(id) {
            if (this.screen !== 'play' && this.styles.some((s) => s.id === id)) {
                this.style = id;
                try {
                    localStorage.setItem('mada-dactylo-style', id);
                } catch {
                    /* noop */
                }
            }
        },
        styleAccent() {
            return {
                neon: 'text-cyan-300',
                retro: 'text-amber-300',
                ocean: 'text-sky-300',
                sunset: 'text-orange-300',
            }[this.style] || 'text-cyan-300';
        },
        start() {
            this.stopAll();
            this.score = 0;
            this.combo = 0;
            this.bestCombo = 0;
            this.errors = 0;
            this.chars = 0;
            this.elapsed = 0;
            this.typed = '';
            this.finished = false;
            this.wpm = 0;
            this.precision = 100;
            this.wordList = shuffle(DACTYLO_POOLS[this.level] || DACTYLO_POOLS[5]);
            if (this.mode === 'deft') this.wordList = this.wordList.slice(0, 10);
            this.wordIdx = 0;
            this.word = this.wordList[0];
            if (this.mode === 'survie') this.startSurvie();
            else this.startWordMode();
            this.screen = 'play';
        },
        startWordMode() {
            this.clockTimer = setInterval(() => {
                this.elapsed += 0.01;
                if (this.mode === 'course' && this.elapsed >= 60) this.finish();
            }, 10);
        },
        startSurvie() {
            this.lives = 3;
            this.kills = 0;
            this.falling = [];
            this.applySpawn();
            this.loopTimer = setInterval(() => this.loopSurvie(), 40);
        },
        applySpawn() {
            const ms = Math.max(1400, 2400 - this.level * 180);
            if (this.spawnTimer) clearInterval(this.spawnTimer);
            this.spawnTimer = setInterval(() => this.spawnWord(), ms);
        },
        spawnWord() {
            if (this.falling.length >= 6) return;
            const pool = DACTYLO_POOLS[this.level] || DACTYLO_POOLS[5];
            const w = pool[Math.floor(Math.random() * pool.length)];
            if (this.falling.some((f) => f.word === w)) return;
            this.falling.push({ word: w, x: 5 + Math.random() * 85, y: 0 });
        },
        loopSurvie() {
            if (this.screen !== 'play') return;
            const speed = 0.5 + (this.level - 1) * 0.25;
            this.falling = this.falling.filter((f) => {
                f.y += speed;
                if (f.y >= 90) {
                    this.lives--;
                    this.typed = '';
                    if (this.lives <= 0) {
                        this.survieOver();
                        return false;
                    }
                    return false;
                }
                return true;
            });
        },
        nextWord() {
            this.wordIdx++;
            if (this.wordIdx >= this.wordList.length) {
                this.wordList = shuffle(this.wordList);
                this.wordIdx = 0;
            }
            this.word = this.wordList[this.wordIdx];
            this.typed = '';
        },
        onInput(v) {
            if (this.screen !== 'play' || this.finished) return;
            if (this.mode === 'survie') return this.onInputSurvie(v);
            const w = v.toLowerCase();
            const cur = this.word;
            if (cur.startsWith(w)) {
                this.typed = w;
            } else {
                this.errors++;
                this.combo = 0;
                let i = 0;
                while (i < w.length && i < cur.length && cur[i] === w[i]) i++;
                this.typed = w.slice(0, i);
            }
            if (this.typed === cur && cur.length) this.wordDone();
        },
        onInputSurvie(v) {
            const w = v.toLowerCase();
            this.typed = w;
            const hit = this.falling.find((f) => f.word.startsWith(w) && w.length);
            if (hit && w === hit.word) {
                this.wordDoneSurvie(hit);
            } else if (!this.falling.some((f) => f.word.startsWith(w))) {
                this.typed = w.slice(0, Math.max(0, w.length - 1));
            }
        },
        wordDone() {
            this.chars += this.word.length;
            this.score += this.word.length * 10 * (1 + this.combo);
            this.combo++;
            if (this.combo > this.bestCombo) this.bestCombo = this.combo;
            if (this.mode === 'deft') {
                this.wordIdx++;
                this.typed = '';
                if (this.wordIdx >= this.wordList.length) {
                    this.finish();
                    return;
                }
                this.word = this.wordList[this.wordIdx];
                return;
            }
            this.nextWord();
        },
        wordDoneSurvie(hit) {
            this.falling = this.falling.filter((f) => f !== hit);
            this.typed = '';
            this.score += hit.word.length * 10 * (1 + this.combo);
            this.combo++;
            if (this.combo > this.bestCombo) this.bestCombo = this.combo;
            this.kills++;
            if (this.level < 5 && this.kills >= this.level * 5) {
                this.level++;
                this.kills = 0;
                this.applySpawn();
            }
        },
        liveWpm() {
            const minutes = Math.max(0.001, this.elapsed / 60);
            return Math.round((this.chars / 5) / minutes);
        },
        finish() {
            this.stopAll();
            this.screen = 'over';
            this.finished = true;
            const minutes = Math.max(0.001, this.elapsed / 60);
            this.wpm = Math.round((this.chars / 5) / minutes);
            this.precision = Math.max(0, Math.round((this.chars / Math.max(1, this.chars + this.errors)) * 100));
            if (this.score > this.best[this.mode]) {
                this.best[this.mode] = this.score;
                localSet('mada-best-dactylo-' + this.mode, this.score);
            }
        },
        survieOver() {
            this.stopAll();
            this.screen = 'over';
            this.finished = true;
            this.wpm = 0;
            this.precision = 100;
            if (this.score > this.best.survie) {
                this.best.survie = this.score;
                localSet('mada-best-dactylo-survie', this.score);
            }
        },
        backToMenu() {
            this.stopAll();
            this.screen = 'menu';
        },
    };
};

window.gameAnagram = () => ({
    level: 1,
    lives: 3,
    score: 0,
    time: 30,
    timer: null,
    word: null,
    scrambled: [],
    typed: '',
    over: false,
    running: false,
    won: false,
    best: 0,
    WORDS: {
        1: [
            ['chat', 'Discussion en direct'], ['pause', 'Coupure / repos'], ['sala', 'Bonjour (mg)'], ['hora', 'Heure (mg)'],
            ['tana', 'Capitale (mg)'], ['vita', 'Terminé (mg)'], ['maso', 'Yeux (mg)'], ['rano', 'Eau (mg)'],
            ['foana', 'Toujours (mg)'], ['tsara', 'Bien (mg)'],
        ],
        2: [
            ['appel', 'Téléphoner (fr)'], ['agora', 'Marché (mg)'], ['sinoa', 'Rire (mg)'], ['manto', 'Âme (mg)'],
            ['valiny', 'Réponse (mg)'], ['fiteny', 'Langue (mg)'], ['fiasa', 'Emploi (mg)'], ['clients', 'Usagers (fr)'],
            ['miara', 'Ensemble (mg)'], ['mifona', 'Pardonner (mg)'],
        ],
        3: [
            ['responsable', 'Le chef (fr)'], ['collegue', 'Coéquipier (fr)'], ['assistance', 'Aide (fr)'],
            ['telephone', "L'outil (fr)"], ['miarahaba', 'Saluer (mg)'], ['mpiasa', 'Employé (mg)'],
            ['faingana', 'Vite (mg)'], ['operateur', 'Agent (fr)'], ['progres', 'Avancée (fr)'], ['communication', 'Échange (fr)'],
        ],
    },
    init() {
        this.best = localGet('mada-best-anagram', 0);
        this.newWord();
    },
    destroy() {
        this.stopTimer();
    },
    stopTimer() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    },
    newWord() {
        const pool = this.WORDS[this.level] || this.WORDS[3];
        const pick = pool[Math.floor(Math.random() * pool.length)];
        this.word = { original: pick[0], hint: pick[1] };
        this.scrambled = this.scramble(pick[0]);
        this.typed = '';
        this.time = 30;
        this.running = true;
        this.over = false;
        this.stopTimer();
        this.timer = setInterval(() => {
            this.time--;
            if (this.time <= 0) this.timeout();
        }, 1000);
    },
    scramble(w) {
        const arr = w.split('');
        for (let i = arr.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [arr[i], arr[j]] = [arr[j], arr[i]];
        }
        const s = arr.join('');
        return s === w ? this.scramble(w) : s;
    },
    addLetter(ch) {
        if (!this.running || this.over) return;
        this.typed += ch;
    },
    backspace() {
        this.typed = this.typed.slice(0, -1);
    },
    submit() {
        if (!this.word || !this.running) return;
        if (this.typed.toLowerCase().trim() === this.word.original) {
            this.score += 10 + Math.ceil(this.time / 5);
            if (this.score > this.best) {
                this.best = this.score;
                localSet('mada-best-anagram', this.best);
            }
            if (this.level < 3) {
                this.level++;
                this.newWord();
            } else {
                this.won = true;
                this.end();
            }
        } else {
            this.lives--;
            this.typed = '';
            if (this.lives <= 0) this.end();
        }
    },
    timeout() {
        this.lives--;
        if (this.lives <= 0) this.end();
        else this.newWord();
    },
    end() {
        this.running = false;
        this.over = true;
        this.stopTimer();
    },
    replay() {
        this.level = 1;
        this.lives = 3;
        this.score = 0;
        this.won = false;
        this.newWord();
    },
});

window.gameCatch = () => ({
    level: 1,
    lives: 3,
    score: 0,
    target: '',
    pos: 0,
    items: [],
    cols: 8,
    basketX: 0.5,
    running: false,
    over: false,
    won: false,
    spawnedNeeded: false,
    tickTimer: null,
    timer: null,
    best: 0,
    WORDS: [
        ['sala', 'hora', 'tana', 'rano', 'asa'],
        ['valiny', 'fiteny', 'manto', 'sinoa', 'fiasa'],
        ['travail', 'equipe', 'miara', 'teny', 'vita'],
        ['telephone', 'message', 'collegue', 'mpiasa', 'progres'],
        ['assistance', 'operateur', 'faingana', 'miarahaba', 'horloge'],
    ],
    LETTERS: 'abcdefghijklmnopqrstuvwxyz',
    init() {
        this.best = localGet('mada-best-catch', 0);
        this.newLevel();
    },
    destroy() {
        this.stopAll();
    },
    stopAll() {
        if (this.tickTimer) clearInterval(this.tickTimer);
        if (this.timer) clearTimeout(this.timer);
        this.tickTimer = null;
        this.timer = null;
    },
    newLevel() {
        this.stopAll();
        this.items = [];
        this.running = false;
        this.over = false;
        this.won = false;
        this.pos = 0;
        this.spawnedNeeded = false;
        const pool = this.WORDS[this.level - 1] || this.WORDS[4];
        this.target = pool[Math.floor(Math.random() * pool.length)];
        this.interval = Math.max(550, 900 - this.level * 70);
        this.start();
    },
    start() {
        this.running = true;
        this.tickTimer = setInterval(() => {
            if (!this.running) return;
            let letter;
            let needed = false;
            if (this.pos < this.target.length && !this.spawnedNeeded) {
                letter = this.target[this.pos];
                needed = true;
                this.spawnedNeeded = true;
            } else {
                do {
                    letter = this.LETTERS[Math.floor(Math.random() * this.LETTERS.length)];
                } while (letter === this.target[this.pos]);
            }
            this.items.push({ id: Math.random(), col: Math.floor(Math.random() * this.cols), y: 0, letter, needed });
        }, this.interval);
        this.loop();
    },
    loop() {
        if (!this.running) return;
        const dy = 0.8 + (this.level - 1) * 0.15;
        this.items.forEach((it) => (it.y += dy));
        const basketCol = Math.round(this.basketX * (this.cols - 1));
        this.items = this.items.filter((it) => {
            if (it.y < 90) return true;
            const caught = it.col === basketCol;
            if (caught) {
                if (it.needed) {
                    this.score += 10;
                    if (this.score > this.best) {
                        this.best = this.score;
                        localSet('mada-best-catch', this.best);
                    }
                    this.pos++;
                    this.spawnedNeeded = false;
                    if (this.pos >= this.target.length) {
                        if (this.level >= 5) this.end(true);
                        else {
                            this.level++;
                            this.newLevel();
                        }
                    }
                } else {
                    this.lives--;
                    if (this.lives <= 0) this.end(false);
                }
            } else if (it.needed) {
                this.lives--;
                if (this.lives <= 0) this.end(false);
                this.spawnedNeeded = false;
            }
            return false;
        });
        if (this.over) return;
        this.timer = setTimeout(() => this.loop(), 40);
    },
    move(dir) {
        this.basketX = Math.min(1, Math.max(0, this.basketX + dir * 0.08));
    },
    end(won) {
        this.running = false;
        this.stopAll();
        this.over = true;
        this.won = won;
    },
    replay() {
        this.level = 1;
        this.lives = 3;
        this.score = 0;
        this.newLevel();
    },
});

window.gameRps = () => ({
    choices: [
        { id: 'rock', emoji: '🪨', beats: 'scissors', label: 'Pierre' },
        { id: 'paper', emoji: '📄', beats: 'rock', label: 'Feuille' },
        { id: 'scissors', emoji: '✂️', beats: 'paper', label: 'Ciseaux' },
    ],
    player: null,
    cpu: null,
    result: null,
    pScore: 0,
    cScore: 0,
    rounds: 0,
    target: 5,
    over: false,
    best: 0,
    init() {
        this.best = localGet('mada-best-rps', 0);
    },
    play(id) {
        if (this.over) return;
        const player = this.choices.find((c) => c.id === id);
        const cpu = this.choices[Math.floor(Math.random() * this.choices.length)];
        this.player = player;
        this.cpu = cpu;
        this.rounds++;
        if (player.beats === cpu.id) {
            this.result = 'win';
            this.pScore++;
        } else if (player.id === cpu.id) {
            this.result = 'draw';
        } else {
            this.result = 'lose';
            this.cScore++;
        }
        if (this.pScore === this.target || this.cScore === this.target) {
            this.over = true;
            if (this.pScore > this.best) {
                this.best = this.pScore;
                localSet('mada-best-rps', this.best);
            }
        }
    },
    reset() {
        this.player = null;
        this.cpu = null;
        this.result = null;
        this.pScore = 0;
        this.cScore = 0;
        this.rounds = 0;
        this.over = false;
    },
});

window.gameMorpion = () => ({
    board: Array(9).fill(null),
    turn: 'X',
    over: false,
    winner: null,
    wins: 0,
    best: 0,
    lines: [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 3, 6], [1, 4, 7], [2, 5, 8], [0, 4, 8], [2, 4, 6]],
    init() {
        this.best = localGet('mada-best-morpion', 0);
    },
    win(b) {
        for (const [a, c, d] of this.lines) {
            if (b[a] && b[a] === b[c] && b[a] === b[d]) return b[a];
        }
        if (b.every((x) => x)) return 'draw';
        return null;
    },
    play(i) {
        if (this.over || this.board[i]) return;
        this.board[i] = 'X';
        const w = this.win(this.board);
        if (w) return this.finish(w);
        this.turn = 'O';
        this.think();
    },
    think() {
        const best = this.minimax(this.board, 'O');
        if (best.index !== null && !this.board[best.index]) {
            this.board[best.index] = 'O';
            const w = this.win(this.board);
            if (w) this.finish(w);
            else this.turn = 'X';
        }
    },
    minimax(b, p) {
        const w = this.win(b);
        if (w === 'X') return { score: -10 };
        if (w === 'O') return { score: 10 };
        if (w === 'draw') return { score: 0 };
        const moves = [];
        for (let i = 0; i < 9; i++) {
            if (!b[i]) {
                b[i] = p;
                const s = this.minimax(b, p === 'X' ? 'O' : 'X').score;
                b[i] = null;
                moves.push({ index: i, score: s });
            }
        }
        return p === 'O'
            ? moves.reduce((a, m) => (m.score > a.score ? m : a), { score: -Infinity })
            : moves.reduce((a, m) => (m.score < a.score ? m : a), { score: Infinity });
    },
    finish(w) {
        this.over = true;
        this.winner = w;
        this.turn = null;
        if (w === 'X') {
            this.wins++;
            if (this.wins > this.best) {
                this.best = this.wins;
                localSet('mada-best-morpion', this.best);
            }
        }
    },
    reset() {
        this.board = Array(9).fill(null);
        this.turn = 'X';
        this.over = false;
        this.winner = null;
    },
});

window.gameMemory = () => ({
    level: 1,
    cards: [],
    open: [],
    matched: 0,
    moves: 0,
    timer: null,
    time: 0,
    over: false,
    best: 0,
    PAIRS: ['🍎', '🍌', '🍇', '🍓', '🍉', '🍒', '🥝', '🍑', '🍍', '🥥', '🍋', '🫐'],
    init() {
        this.best = localGet('mada-best-memory', 0);
        this.newGame();
    },
    destroy() {
        this.clearTimer();
    },
    newGame() {
        this.clearTimer();
        const pairs = this.PAIRS.slice(0, 4 + this.level * 2);
        const deck = [...pairs, ...pairs].map((emoji, i) => ({ id: i, emoji, matched: false }));
        for (let i = deck.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [deck[i], deck[j]] = [deck[j], deck[i]];
        }
        this.cards = deck;
        this.open = [];
        this.matched = 0;
        this.moves = 0;
        this.time = 0;
        this.over = false;
    },
    startTimer() {
        if (this.timer) return;
        this.timer = setInterval(() => {
            this.time++;
        }, 1000);
    },
    clearTimer() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    },
    flip(i) {
        if (this.over || this.cards[i].matched || this.open.includes(i) || this.open.length === 2) return;
        if (!this.timer) this.startTimer();
        this.open.push(i);
        if (this.open.length === 2) {
            this.moves++;
            this.check();
        }
    },
    check() {
        const [a, b] = this.open;
        if (this.cards[a].emoji === this.cards[b].emoji) {
            this.cards[a].matched = true;
            this.cards[b].matched = true;
            this.matched += 2;
            this.open = [];
            if (this.matched === this.cards.length) this.win();
        } else {
            setTimeout(() => {
                this.open = [];
            }, 700);
        }
    },
    win() {
        this.clearTimer();
        this.over = true;
        const score = Math.max(0, 1000 - this.moves * 10 - this.time * 2);
        if (score > this.best) {
            this.best = score;
            localSet('mada-best-memory', this.best);
        }
    },
    nextLevel() {
        if (this.level < 3) {
            this.level++;
            this.newGame();
        }
    },
});

window.gameSnake = () => ({
    rows: 15,
    cols: 20,
    cells: [],
    snake: [],
    dir: { x: 1, y: 0 },
    nextDir: { x: 1, y: 0 },
    food: null,
    score: 0,
    speed: 180,
    running: false,
    timer: null,
    over: false,
    best: 0,
    keyHandler: null,
    init() {
        this.best = localGet('mada-best-snake', 0);
        this.keyHandler = (e) => {
            const k = e.key;
            if (k === 'ArrowUp' || k === 'w' || k === 'W') this.setDir(0, -1);
            else if (k === 'ArrowDown' || k === 's' || k === 'S') this.setDir(0, 1);
            else if (k === 'ArrowLeft' || k === 'a' || k === 'A') this.setDir(-1, 0);
            else if (k === 'ArrowRight' || k === 'd' || k === 'D') this.setDir(1, 0);
        };
        window.addEventListener('keydown', this.keyHandler);
        this.build();
    },
    destroy() {
        if (this.keyHandler) window.removeEventListener('keydown', this.keyHandler);
        this.stop();
    },
    build() {
        this.stop();
        this.snake = [{ x: 5, y: 7 }, { x: 4, y: 7 }, { x: 3, y: 7 }];
        this.dir = { x: 1, y: 0 };
        this.nextDir = { x: 1, y: 0 };
        this.food = this.placeFood();
        this.score = 0;
        this.speed = 180;
        this.running = false;
        this.over = false;
        this.render();
    },
    placeFood() {
        let p;
        do {
            p = { x: Math.floor(Math.random() * this.cols), y: Math.floor(Math.random() * this.rows) };
        } while (this.snake.some((s) => s.x === p.x && s.y === p.y));
        return p;
    },
    start() {
        if (this.over) this.build();
        this.running = true;
        this.tick();
    },
    tick() {
        this.dir = this.nextDir;
        const head = { x: this.snake[0].x + this.dir.x, y: this.snake[0].y + this.dir.y };
        const hitWall = head.x < 0 || head.y < 0 || head.x >= this.cols || head.y >= this.rows;
        const hitSelf = this.snake.some((s) => s.x === head.x && s.y === head.y);
        if (hitWall || hitSelf) {
            this.gameOver();
            return;
        }
        this.snake.unshift(head);
        if (head.x === this.food.x && head.y === this.food.y) {
            this.score += 10;
            if (this.score > this.best) {
                this.best = this.score;
                localSet('mada-best-snake', this.best);
            }
            if (this.score % 50 === 0) this.speed = Math.max(60, this.speed - 20);
            this.food = this.placeFood();
        } else {
            this.snake.pop();
        }
        this.render();
        this.timer = setTimeout(() => this.tick(), this.speed);
    },
    stop() {
        if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
        }
    },
    gameOver() {
        this.running = false;
        this.over = true;
        this.stop();
    },
    setDir(dx, dy) {
        if (!this.running && !this.over) this.start();
        if (this.over) return;
        if ((dx === -this.dir.x && dy === -this.dir.y) || (dx === this.dir.x && dy === this.dir.y)) return;
        this.nextDir = { x: dx, y: dy };
    },
    render() {
        this.cells = Array(this.rows * this.cols).fill('');
        this.snake.forEach((s, i) => {
            this.cells[s.y * this.cols + s.x] = i === 0 ? '🐍' : '🟩';
        });
        if (this.food) this.cells[this.food.y * this.cols + this.food.x] = '🍎';
    },
});
