import { computed, onUnmounted, ref } from 'vue';

const SPEED_OPTIONS = [1, 1.25, 1.5, 2] as const;

// Module-level singleton — shared across all chaos components in the page
const audioCache = new Map<string, HTMLAudioElement>();

const isPlaying  = ref(false);
const isLoading  = ref(false);
const currentTime = ref(0);
const duration   = ref(0);
const playbackRate = ref(1);
const activeKey  = ref<string | null>(null);

let currentAudio: HTMLAudioElement | null = null;
let rafId: number | null = null;

function updateTime() {
    if (currentAudio) {
        currentTime.value = currentAudio.currentTime;
        duration.value    = currentAudio.duration || 0;
    }
    if (isPlaying.value) {
        rafId = requestAnimationFrame(updateTime);
    }
}

function stopTimeUpdates() {
    if (rafId !== null) {
        cancelAnimationFrame(rafId);
        rafId = null;
    }
}

function attachListeners(audio: HTMLAudioElement, key: string) {
    if ((audio as any).__chaosListenersAttached) return;
    (audio as any).__chaosListenersAttached = true;

    audio.addEventListener('canplay', () => {
        isLoading.value = false;
        duration.value  = audio.duration || 0;
    });
    audio.addEventListener('playing', () => {
        isPlaying.value = true;
        isLoading.value = false;
        duration.value  = audio.duration || 0;
        updateTime();
    });
    audio.addEventListener('ended', () => {
        isPlaying.value = false;
        stopTimeUpdates();
    });
    audio.addEventListener('pause', () => {
        isPlaying.value = false;
        stopTimeUpdates();
    });
    audio.addEventListener('error', () => {
        isPlaying.value = false;
        isLoading.value = false;
        stopTimeUpdates();
        console.warn('[ChaosTTS] audio error', key);
    });
    audio.addEventListener('loadedmetadata', () => {
        duration.value = audio.duration || 0;
    });
}

/** Play the narrator turn at `turnIndex` for the given chaos session. */
function play(sessionId: string, turnIndex: number) {
    const key = `${sessionId}:${turnIndex}`;

    if (currentAudio && activeKey.value === key && !currentAudio.ended) {
        if (currentAudio.paused) {
            currentAudio.play().catch(() => {
                isPlaying.value = false;
                isLoading.value = false;
            });
        }
        return;
    }

    stop();

    if (audioCache.has(key)) {
        currentAudio              = audioCache.get(key)!;
        activeKey.value           = key;
        currentAudio.currentTime  = 0;
        currentAudio.playbackRate = playbackRate.value;
        currentAudio.play().catch(() => {
            isPlaying.value = false;
            isLoading.value = false;
        });
        return;
    }

    isLoading.value = true;
    const audio = new Audio(`/chaos-mode/${sessionId}/tts/${turnIndex}`);
    audio.preload         = 'auto';
    audio.playbackRate    = playbackRate.value;
    attachListeners(audio, key);
    audioCache.set(key, audio);
    currentAudio  = audio;
    activeKey.value = key;
    audio.play().catch(() => {
        isPlaying.value = false;
        isLoading.value = false;
    });
}

function stop() {
    if (currentAudio) {
        currentAudio.pause();
        currentAudio.currentTime = 0;
    }
    isPlaying.value   = false;
    isLoading.value   = false;
    currentTime.value = 0;
    stopTimeUpdates();
}

function dismiss() {
    stop();
    activeKey.value = null;
}

function pause() {
    if (currentAudio && !currentAudio.paused) {
        currentAudio.pause();
    }
}

function resume() {
    if (currentAudio && currentAudio.paused && !currentAudio.ended) {
        currentAudio.play().catch(() => {
            isPlaying.value = false;
        });
    }
}

function togglePause() {
    if (isPlaying.value) pause();
    else resume();
}

function toggle(sessionId: string, turnIndex: number) {
    const key = `${sessionId}:${turnIndex}`;
    if (isPlaying.value && activeKey.value === key) {
        pause();
    } else if (activeKey.value === key && currentAudio && !currentAudio.ended) {
        resume();
    } else {
        play(sessionId, turnIndex);
    }
}

function cycleSpeed() {
    const idx  = SPEED_OPTIONS.indexOf(playbackRate.value as (typeof SPEED_OPTIONS)[number]);
    const next = (idx + 1) % SPEED_OPTIONS.length;
    playbackRate.value = SPEED_OPTIONS[next];
    if (currentAudio) currentAudio.playbackRate = playbackRate.value;
}

const isActive = computed(() => activeKey.value !== null);

const formattedCurrentTime = computed(() => formatTime(currentTime.value));
const formattedDuration    = computed(() => formatTime(duration.value));

function formatTime(seconds: number): string {
    if (!seconds || !isFinite(seconds)) return '0:00';
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${s.toString().padStart(2, '0')}`;
}

export function useChaosTextToSpeech() {
    onUnmounted(() => {
        stopTimeUpdates();
    });

    return {
        isPlaying,
        isLoading,
        isActive,
        currentTime,
        duration,
        playbackRate,
        formattedCurrentTime,
        formattedDuration,
        activeKey,
        play,
        stop,
        dismiss,
        pause,
        resume,
        toggle,
        togglePause,
        cycleSpeed,
    };
}
