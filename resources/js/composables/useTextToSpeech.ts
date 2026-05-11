import { computed, onUnmounted, ref } from 'vue';

const SPEED_OPTIONS = [1, 1.25, 1.5, 2] as const;

const audioCache = new Map<string, HTMLAudioElement>();

const isPlaying = ref(false);
const isLoading = ref(false);
const currentTime = ref(0);
const duration = ref(0);
const playbackRate = ref(1);
const activeKey = ref<string | null>(null);

let currentAudio: HTMLAudioElement | null = null;
let rafId: number | null = null;

function updateTime() {
    if (currentAudio) {
        currentTime.value = currentAudio.currentTime;
        duration.value = currentAudio.duration || 0;
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
    // Guard: only attach once per element so cached replays don't stack handlers.
    if ((audio as any).__ttsListenersAttached) return;
    (audio as any).__ttsListenersAttached = true;

    audio.addEventListener('canplay', () => {
        isLoading.value = false;
        duration.value = audio.duration || 0;
        console.debug('[TTS] canplay', key, 'duration', duration.value);
    });
    audio.addEventListener('playing', () => {
        isPlaying.value = true;
        isLoading.value = false;
        duration.value = audio.duration || 0;
        console.debug('[TTS] playing', key);
        updateTime();
    });
    audio.addEventListener('ended', () => {
        isPlaying.value = false;
        stopTimeUpdates();
        console.debug('[TTS] ended', key);
    });
    audio.addEventListener('pause', () => {
        isPlaying.value = false;
        stopTimeUpdates();
        console.debug('[TTS] pause', key);
    });
    audio.addEventListener('error', (e) => {
        isPlaying.value = false;
        isLoading.value = false;
        stopTimeUpdates();
        console.warn('[TTS] error', key, (e.target as HTMLAudioElement).error);
    });
    audio.addEventListener('loadedmetadata', () => {
        duration.value = audio.duration || 0;
        console.debug('[TTS] loadedmetadata', key, 'duration', duration.value);
    });
    audio.addEventListener('stalled', () => {
        console.warn('[TTS] stalled', key);
    });
    audio.addEventListener('waiting', () => {
        console.debug('[TTS] waiting (buffering)', key);
    });
}

function play(gameId: string, promptId: string) {
    const key = `${gameId}:${promptId}`;

    if (currentAudio && activeKey.value === key && !currentAudio.ended) {
        if (currentAudio.paused) {
            currentAudio.play().catch((err) => {
                console.warn('[TTS] play() rejected (resume)', key, err);
                isPlaying.value = false;
                isLoading.value = false;
            });
        }
        return;
    }

    stop();

    if (audioCache.has(key)) {
        currentAudio = audioCache.get(key)!;
        activeKey.value = key;
        currentAudio.currentTime = 0;
        currentAudio.playbackRate = playbackRate.value;
        currentAudio.play().catch((err) => {
            console.warn('[TTS] play() rejected (cache replay)', key, err);
            isPlaying.value = false;
            isLoading.value = false;
        });
        return;
    }

    isLoading.value = true;
    console.debug('[TTS] fetching', key);
    const audio = new Audio(`/user/games/${gameId}/tts/${promptId}`);
    audio.preload = 'auto';
    audio.playbackRate = playbackRate.value;
    attachListeners(audio, key);
    audioCache.set(key, audio);
    currentAudio = audio;
    activeKey.value = key;
    audio.play().catch((err) => {
        console.warn('[TTS] play() rejected (new)', key, err);
        isPlaying.value = false;
        isLoading.value = false;
    });
}

function stop() {
    if (currentAudio) {
        currentAudio.pause();
        currentAudio.currentTime = 0;
    }
    isPlaying.value = false;
    isLoading.value = false;
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
        currentAudio.play().catch((err) => {
            console.warn('[TTS] play() rejected (togglePause resume)', activeKey.value, err);
            isPlaying.value = false;
        });
    }
}

function togglePause() {
    if (isPlaying.value) {
        pause();
    } else {
        resume();
    }
}

function toggle(gameId: string, promptId: string) {
    const key = `${gameId}:${promptId}`;
    if (isPlaying.value && activeKey.value === key) {
        pause();
    } else if (activeKey.value === key && currentAudio && !currentAudio.ended) {
        resume();
    } else {
        play(gameId, promptId);
    }
}

function cycleSpeed() {
    const currentIndex = SPEED_OPTIONS.indexOf(playbackRate.value as (typeof SPEED_OPTIONS)[number]);
    const nextIndex = (currentIndex + 1) % SPEED_OPTIONS.length;
    playbackRate.value = SPEED_OPTIONS[nextIndex];
    if (currentAudio) {
        currentAudio.playbackRate = playbackRate.value;
    }
}

function setSpeed(rate: number) {
    playbackRate.value = rate;
    if (currentAudio) {
        currentAudio.playbackRate = rate;
    }
}

const isActive = computed(() => activeKey.value !== null);

const formattedCurrentTime = computed(() => formatTime(currentTime.value));
const formattedDuration = computed(() => formatTime(duration.value));

function formatTime(seconds: number): string {
    if (!seconds || !isFinite(seconds)) return '0:00';
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${s.toString().padStart(2, '0')}`;
}

export function useTextToSpeech() {
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
        setSpeed,
    };
}
