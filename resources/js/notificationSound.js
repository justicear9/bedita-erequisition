let audioCtx = null;
let unlocked = false;
let fallbackAudio = null;

const SOUND_URL = '/sounds/notify.wav';

function buildPingBuffer(ctx) {
    const duration = 0.28;
    const sampleRate = ctx.sampleRate;
    const length = Math.floor(sampleRate * duration);
    const buffer = ctx.createBuffer(1, length, sampleRate);
    const data = buffer.getChannelData(0);

    for (let i = 0; i < length; i += 1) {
        const t = i / sampleRate;
        const envelope = Math.exp(-6 * t);
        const tone = Math.sin(2 * Math.PI * 880 * t) * 0.55 + Math.sin(2 * Math.PI * 1320 * t) * 0.25;
        data[i] = tone * envelope * 0.4;
    }

    return buffer;
}

export function isNotificationSoundUnlocked() {
    return unlocked;
}

/**
 * Must run from a user gesture (tap/click) on mobile browsers.
 */
export async function unlockNotificationSound() {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (AudioContext) {
            if (!audioCtx) {
                audioCtx = new AudioContext();
            }
            if (audioCtx.state === 'suspended') {
                await audioCtx.resume();
            }
        }

        if (!fallbackAudio) {
            fallbackAudio = new Audio(SOUND_URL);
            fallbackAudio.preload = 'auto';
            fallbackAudio.volume = 0.01;
            try {
                await fallbackAudio.play();
                fallbackAudio.pause();
                fallbackAudio.currentTime = 0;
            } catch {
                // Still mark unlocked if Web Audio resumed.
            }
            fallbackAudio.volume = 0.85;
        }

        unlocked = true;
        try {
            window.sessionStorage.setItem('ereq_sound_unlocked', '1');
        } catch {
            // ignore
        }

        return true;
    } catch {
        return false;
    }
}

export function playNotificationSound() {
    const tryFallback = () => {
        try {
            if (!fallbackAudio) {
                fallbackAudio = new Audio(SOUND_URL);
                fallbackAudio.volume = 0.85;
            }
            fallbackAudio.currentTime = 0;
            void fallbackAudio.play().catch(() => {});
        } catch {
            // ignore
        }
    };

    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) {
            tryFallback();
            return;
        }

        if (!audioCtx) {
            audioCtx = new AudioContext();
        }

        if (audioCtx.state === 'suspended') {
            audioCtx
                .resume()
                .then(() => {
                    unlocked = true;
                    playViaWebAudio();
                })
                .catch(tryFallback);
            return;
        }

        playViaWebAudio();
    } catch {
        tryFallback();
    }
}

function playViaWebAudio() {
    if (!audioCtx) return;

    const source = audioCtx.createBufferSource();
    source.buffer = buildPingBuffer(audioCtx);
    const gain = audioCtx.createGain();
    gain.gain.value = 1;
    source.connect(gain);
    gain.connect(audioCtx.destination);
    source.start(0);
}

export function installNotificationSoundGestureUnlock() {
    if (typeof window === 'undefined') return;

    const unlock = () => {
        void unlockNotificationSound();
        window.removeEventListener('pointerdown', unlock, true);
        window.removeEventListener('keydown', unlock, true);
        window.removeEventListener('touchstart', unlock, true);
    };

    window.addEventListener('pointerdown', unlock, true);
    window.addEventListener('keydown', unlock, true);
    window.addEventListener('touchstart', unlock, true);
}
