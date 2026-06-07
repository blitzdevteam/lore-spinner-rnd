import { computed, onUnmounted, ref } from 'vue';

const SPEED_OPTIONS = [1, 1.25, 1.5, 1.75, 2] as const;

// Minimal silent WAV — played once during a user gesture to unlock mobile autoplay.
const SILENT_WAV =
    'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=';

const IOS = typeof navigator !== 'undefined' && /iP(hone|od|ad)/.test(navigator.userAgent);

const audioCache = new Map<string, HTMLAudioElement>();

const isPlaying = ref(false);
const isLoading = ref(false);
const currentTime = ref(0);
const duration = ref(0);
const playbackRate = ref(1);
const volume = ref(1);
const isMuted = ref(false);
const isLooping = ref(false);
const activeKey = ref<string | null>(null);
const playedKeys = new Set<string>();
const mediaCollapsed = ref(false);

function revealMediaPlayer() {
    mediaCollapsed.value = false;
}

function collapseMediaPlayer() {
    mediaCollapsed.value = true;
}

let currentAudio: HTMLAudioElement | null = null;
let rafId: number | null = null;
let unlockAudio: HTMLAudioElement | null = null;
let audioUnlocked = false;
let pendingAutoplay: { gameId: string; promptId: string } | null = null;

function configureAudioElement(audio: HTMLAudioElement) {
    audio.setAttribute('playsinline', 'true');
    if (IOS) {
        audio.crossOrigin = 'anonymous';
    }
}

function isAutoplayBlocked(err: unknown): boolean {
    return err instanceof DOMException && err.name === 'NotAllowedError';
}

function queuePendingAutoplay(gameId: string, promptId: string) {
    pendingAutoplay = { gameId, promptId };
}

function flushPendingAutoplay() {
    if (!pendingAutoplay || !audioUnlocked) return;
    const { gameId, promptId } = pendingAutoplay;
    pendingAutoplay = null;
    play(gameId, promptId);
}

/** Call synchronously from a user-gesture handler (tap/click) to unlock mobile audio. */
function primeAudio() {
    if (typeof window === 'undefined') return;

    if (audioUnlocked) {
        flushPendingAutoplay();
        return;
    }

    if (!unlockAudio) {
        unlockAudio = new Audio(SILENT_WAV);
        unlockAudio.volume = 0.001;
        configureAudioElement(unlockAudio);
    }

    const attempt = unlockAudio.play();
    if (!attempt) return;

    attempt
        .then(() => {
            unlockAudio?.pause();
            if (unlockAudio) unlockAudio.currentTime = 0;
            audioUnlocked = true;
            flushPendingAutoplay();
        })
        .catch(() => {
            // Gesture may have expired; a later interaction will retry.
        });
}

function handlePlayRejected(key: string, err: unknown, gameId: string, promptId: string) {
    if (isAutoplayBlocked(err)) {
        queuePendingAutoplay(gameId, promptId);
    }
    console.warn('[TTS] play() rejected', key, err);
    isPlaying.value = false;
    isLoading.value = false;
}

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
        playedKeys.add(key);
        console.debug('[TTS] playing', key);
        updateTime();
    });
    audio.addEventListener('ended', () => {
        if (isLooping.value && currentAudio) {
            currentAudio.currentTime = 0;
            currentAudio.play().catch(() => {
                isPlaying.value = false;
            });
            return;
        }
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
        isLoading.value = true;
        console.debug('[TTS] waiting (buffering)', key);
    });
}

function applyAudioSettings(audio: HTMLAudioElement) {
    audio.playbackRate = playbackRate.value;
    audio.volume = volume.value;
    audio.muted = isMuted.value;
    audio.loop = false; // looping handled manually in the `ended` listener
}

function play(gameId: string, promptId: string) {
    const key = `${gameId}:${promptId}`;

    if (currentAudio && activeKey.value === key && !currentAudio.ended) {
        if (currentAudio.paused) {
            isLoading.value = true;
            revealMediaPlayer();
            currentAudio.play().catch((err) => {
                handlePlayRejected(key, err, gameId, promptId);
            });
        }
        return;
    }

    revealMediaPlayer();
    stop();

    if (audioCache.has(key)) {
        currentAudio = audioCache.get(key)!;
        activeKey.value = key;
        currentAudio.currentTime = 0;
        applyAudioSettings(currentAudio);
        isLoading.value = true;
        currentAudio.play().catch((err) => {
            handlePlayRejected(key, err, gameId, promptId);
        });
        return;
    }

    isLoading.value = true;
    console.debug('[TTS] fetching', key);
    const audio = new Audio(`/user/games/${gameId}/tts/${promptId}`);
    audio.preload = 'auto';
    configureAudioElement(audio);
    applyAudioSettings(audio);
    attachListeners(audio, key);
    audioCache.set(key, audio);
    currentAudio = audio;
    activeKey.value = key;
    audio.play().catch((err) => {
        handlePlayRejected(key, err, gameId, promptId);
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
        isLoading.value = true;
        currentAudio.play().catch((err) => {
            const key = activeKey.value ?? 'unknown';
            const [gameId = '', promptId = ''] = key.split(':');
            handlePlayRejected(key, err, gameId, promptId);
        });
    }
}

function togglePause() {
    if (isPlaying.value) {
        pause();
    } else {
        primeAudio();
        resume();
    }
}

function toggle(gameId: string, promptId: string) {
    primeAudio();
    const key = `${gameId}:${promptId}`;
    if (isPlaying.value && activeKey.value === key) {
        pause();
    } else if (activeKey.value === key && currentAudio && !currentAudio.ended) {
        revealMediaPlayer();
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

function setVolume(value: number) {
    const clamped = Math.min(1, Math.max(0, value));
    volume.value = clamped;
    if (clamped > 0) {
        isMuted.value = false;
    }
    if (currentAudio) {
        currentAudio.volume = clamped;
        currentAudio.muted = isMuted.value;
    }
}

function toggleMute() {
    isMuted.value = !isMuted.value;
    if (currentAudio) {
        currentAudio.muted = isMuted.value;
    }
}

function toggleLoop() {
    isLooping.value = !isLooping.value;
}

function seekTo(seconds: number) {
    if (!currentAudio) return;
    const max = currentAudio.duration || duration.value || 0;
    const clamped = Math.min(max, Math.max(0, seconds));
    currentAudio.currentTime = clamped;
    currentTime.value = clamped;
}

function seekBy(delta: number) {
    if (!currentAudio) return;
    seekTo(currentAudio.currentTime + delta);
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
        volume,
        isMuted,
        isLooping,
        formattedCurrentTime,
        formattedDuration,
        activeKey,
        mediaCollapsed,
        revealMediaPlayer,
        collapseMediaPlayer,
        play,
        stop,
        dismiss,
        pause,
        resume,
        toggle,
        togglePause,
        cycleSpeed,
        setSpeed,
        setVolume,
        toggleMute,
        toggleLoop,
        seekTo,
        seekBy,
        primeAudio,
        hasPlayed: (gameId: string, promptId: string) => playedKeys.has(`${gameId}:${promptId}`),
    };
}
