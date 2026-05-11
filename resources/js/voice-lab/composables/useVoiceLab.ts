import { computed, onUnmounted, ref } from 'vue';
import { useVoiceSfx } from './useVoiceSfx';

export type VoiceLabState = 'idle' | 'listening' | 'thinking' | 'speaking' | 'error';

export interface VoiceLabIntro {
    enabled: boolean;
    audioUrl: string;
    choices: string[];
}

function getXsrfToken(): string | null {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : null;
}

function makeHeaders(extra: Record<string, string> = {}): Record<string, string> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...extra,
    };
    const xsrf = getXsrfToken();
    if (xsrf) headers['X-XSRF-TOKEN'] = xsrf;
    return headers;
}

function getSupportedMimeType(): string {
    const types = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'];
    for (const type of types) {
        if (MediaRecorder.isTypeSupported(type)) return type;
    }
    return 'audio/webm';
}

export function useVoiceLab(intro: VoiceLabIntro | null = null) {
    const state = ref<VoiceLabState>('idle');
    const audioLevel = ref(0);
    const errorMessage = ref<string | null>(null);
    const choices = ref<string[]>([]);
    const hasStartedIntro = ref(false);

    const sfx = useVoiceSfx();

    let mediaRecorder: MediaRecorder | null = null;
    let audioChunks: Blob[] = [];
    let micStream: MediaStream | null = null;
    /** True between requesting the mic and MediaRecorder.start() — fast touch release must cancel here. */
    let listenPending = false;

    let audioContext: AudioContext | null = null;
    let micAnalyser: AnalyserNode | null = null;
    let playbackAnalyser: AnalyserNode | null = null;
    let audioSource: MediaElementAudioSourceNode | null = null;
    let micSource: MediaStreamAudioSourceNode | null = null;
    let currentAudio: HTMLAudioElement | null = null;
    let levelRaf: number | null = null;
    let thinkingTickTimer: number | null = null;

    const isActive = computed(() => state.value !== 'idle');

    function resetError() {
        errorMessage.value = null;
    }

    function setError(msg: string) {
        errorMessage.value = msg;
        state.value = 'error';
        try {
            sfx.playError();
        } catch {
            /* sfx is best-effort */
        }
        stopThinkingTicks();
        setTimeout(() => {
            if (state.value === 'error') {
                state.value = 'idle';
                errorMessage.value = null;
            }
        }, 2500);
    }

    function releaseMic() {
        if (micStream) {
            micStream.getTracks().forEach((t) => t.stop());
            micStream = null;
        }
    }

    function stopAudioLevel() {
        if (levelRaf !== null) {
            cancelAnimationFrame(levelRaf);
            levelRaf = null;
        }
        audioLevel.value = 0;
    }

    function stopMicLevel() {
        if (micSource) {
            try {
                micSource.disconnect();
            } catch {
                /* already disconnected */
            }
            micSource = null;
        }
        stopAudioLevel();
    }

    function stopPlayback() {
        if (currentAudio) {
            try {
                currentAudio.pause();
            } catch {
                /* noop */
            }
            currentAudio.src = '';
            currentAudio = null;
        }
        if (audioSource) {
            try {
                audioSource.disconnect();
            } catch {
                /* noop */
            }
            audioSource = null;
        }
        stopAudioLevel();
    }

    function getAudioContext(): AudioContext {
        if (!audioContext) {
            audioContext = new AudioContext();
        }
        return audioContext;
    }

    function makeAnalyser(): AnalyserNode {
        const ctx = getAudioContext();
        const node = ctx.createAnalyser();
        node.fftSize = 256;
        node.smoothingTimeConstant = 0.7;
        return node;
    }

    function startLevelLoop(node: AnalyserNode) {
        const dataArray = new Uint8Array(node.frequencyBinCount);

        function tick() {
            node.getByteFrequencyData(dataArray);
            let sum = 0;
            for (let i = 0; i < dataArray.length; i++) {
                sum += dataArray[i];
            }
            audioLevel.value = Math.min(1, (sum / dataArray.length / 255) * 2.5);
            levelRaf = requestAnimationFrame(tick);
        }

        if (levelRaf !== null) cancelAnimationFrame(levelRaf);
        levelRaf = requestAnimationFrame(tick);
    }

    function trackMicLevel(stream: MediaStream) {
        const ctx = getAudioContext();
        micAnalyser = makeAnalyser();
        micSource = ctx.createMediaStreamSource(stream);
        micSource.connect(micAnalyser);
        startLevelLoop(micAnalyser);
    }

    function trackAudioLevel(audio: HTMLAudioElement) {
        const ctx = getAudioContext();
        playbackAnalyser = makeAnalyser();
        audioSource = ctx.createMediaElementSource(audio);
        audioSource.connect(playbackAnalyser);
        playbackAnalyser.connect(ctx.destination);
        startLevelLoop(playbackAnalyser);
    }

    function startThinkingTicks() {
        stopThinkingTicks();
        thinkingTickTimer = window.setTimeout(function loop() {
            if (state.value !== 'thinking') return;
            sfx.playThinkingTick();
            thinkingTickTimer = window.setTimeout(loop, 900);
        }, 1500);
    }

    function stopThinkingTicks() {
        if (thinkingTickTimer !== null) {
            clearTimeout(thinkingTickTimer);
            thinkingTickTimer = null;
        }
    }

    async function startListening() {
        if (listenPending || (state.value !== 'idle' && state.value !== 'error')) return;

        resetError();
        listenPending = true;

        try {
            micStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch {
            listenPending = false;
            setError('Microphone access denied');
            return;
        }

        // Finger may have lifted before getUserMedia resolved (mobile quick tap).
        if (!listenPending) {
            releaseMic();
            return;
        }

        listenPending = false;
        state.value = 'listening';
        sfx.playListenStart();

        audioChunks = [];
        const mimeType = getSupportedMimeType();
        mediaRecorder = new MediaRecorder(micStream, { mimeType });

        mediaRecorder.addEventListener('dataavailable', (e: BlobEvent) => {
            if (e.data.size > 0) audioChunks.push(e.data);
        });

        trackMicLevel(micStream);
        mediaRecorder.start(250);
    }

    // Tap-to-toggle: tap once to start recording, tap again to stop and send.
    async function activate() {
        sfx.prime();

        // Cancel a slow getUserMedia (second tap while permission dialog open).
        if (listenPending) {
            listenPending = false;
            releaseMic();
            state.value = 'idle';
            return;
        }

        if (intro?.enabled && !hasStartedIntro.value) {
            hasStartedIntro.value = true;
            sfx.playWake();
            await playIntro();
            return;
        }

        if (state.value === 'listening') {
            await finishRecording();
            return;
        }

        if (state.value === 'speaking') {
            stopPlayback();
            state.value = 'idle';
            return;
        }

        await startListening();
    }

    async function finishRecording(): Promise<void> {
        return new Promise((resolve) => {
            if (!mediaRecorder || mediaRecorder.state === 'inactive') {
                state.value = 'idle';
                resolve();
                return;
            }

            // --- Bug 1 fix: disconnect the mic graph synchronously BEFORE stopping
            //     the recorder, so any residual audio flushed on .stop() never
            //     reaches the analyser or leaks into the next turn. Also request
            //     a final data tick so the tail chunk is already collected.
            sfx.playListenStop();
            stopMicLevel();
            releaseMic();
            try {
                mediaRecorder.requestData();
            } catch {
                /* some browsers throw if inactive; safe to ignore */
            }

            mediaRecorder.addEventListener(
                'stop',
                async () => {
                    const mimeType = mediaRecorder?.mimeType || 'audio/webm';
                    const audioBlob = new Blob(audioChunks, { type: mimeType });
                    audioChunks = [];

                    if (audioBlob.size < 500) {
                        state.value = 'idle';
                        resolve();
                        return;
                    }

                    state.value = 'thinking';
                    startThinkingTicks();

                    try {
                        const text = await transcribe(audioBlob);
                        if (!text.trim()) {
                            setError('Could not understand audio');
                            resolve();
                            return;
                        }

                        const result = await streamAiResponse(text);
                        stopThinkingTicks();
                        choices.value = result.choices;
                        await result.played;
                        sfx.playReady();
                    } catch (err) {
                        stopThinkingTicks();
                        setError(err instanceof Error ? err.message : 'Something went wrong');
                    }

                    resolve();
                },
                { once: true },
            );

            mediaRecorder.stop();
        });
    }

    async function transcribe(audioBlob: Blob): Promise<string> {
        const ext = audioBlob.type.includes('webm') ? 'webm' : audioBlob.type.includes('mp4') ? 'mp4' : 'webm';

        const formData = new FormData();
        formData.append('audio', audioBlob, `recording.${ext}`);

        const response = await fetch('/user/voice-lab/transcribe', {
            method: 'POST',
            credentials: 'same-origin',
            headers: makeHeaders(),
            body: formData,
        });

        if (!response.ok) throw new Error('Transcription failed');

        const data = await response.json();
        return data.text ?? '';
    }

    async function streamAiResponse(message: string): Promise<{ played: Promise<void>; choices: string[] }> {
        const response = await fetch('/user/voice-lab/respond', {
            method: 'POST',
            credentials: 'same-origin',
            headers: makeHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ message }),
        });

        if (!response.ok || !response.body) throw new Error('AI response failed');

        let parsedChoices: string[] = [];
        try {
            const raw = response.headers.get('X-VoiceLab-Choices');
            if (raw) parsedChoices = JSON.parse(raw);
        } catch {
            /* header absent or malformed — non-fatal */
        }

        const mimeType = 'audio/mpeg';
        const canStream =
            typeof window.MediaSource !== 'undefined' &&
            window.MediaSource.isTypeSupported(mimeType);

        const played = canStream
            ? playStreamedAudio(response.body, mimeType)
            : playBufferedAudio(response);

        return { played, choices: parsedChoices };
    }

    function playStreamedAudio(stream: ReadableStream<Uint8Array>, mimeType: string): Promise<void> {
        return new Promise((resolve) => {
            const mediaSource = new MediaSource();
            const audio = new Audio();
            currentAudio = audio;
            const url = URL.createObjectURL(mediaSource);
            audio.src = url;

            let started = false;
            let finished = false;
            const pending: Uint8Array[] = [];
            let sourceBuffer: SourceBuffer | null = null;

            const finish = (errored = false) => {
                if (finished) return;
                finished = true;
                // Detach listeners BEFORE stopPlayback() because clearing
                // currentAudio.src fires a synthetic 'error' event that we
                // must not let flip us into the error state after a clean end.
                audio.removeEventListener('ended', onEnded);
                audio.removeEventListener('error', onError);
                stopPlayback();
                URL.revokeObjectURL(url);
                if (errored) {
                    setError('Audio playback failed');
                } else {
                    state.value = 'idle';
                }
                resolve();
            };

            const pumpPending = () => {
                if (!sourceBuffer || sourceBuffer.updating) return;
                const next = pending.shift();
                if (next) {
                    try {
                        sourceBuffer.appendBuffer(next as unknown as BufferSource);
                    } catch (e) {
                        console.warn('[voice-lab] appendBuffer failed', e);
                        finish(true);
                    }
                    return;
                }
                if (mediaSource.readyState === 'open' && (mediaSource as MediaSource & { _done?: boolean })._done) {
                    try {
                        mediaSource.endOfStream();
                    } catch {
                        /* already ended */
                    }
                }
            };

            const onEnded = () => finish(false);
            const onError = () => {
                if (finished) return;
                finish(true);
            };
            audio.addEventListener('ended', onEnded);
            audio.addEventListener('error', onError);

            mediaSource.addEventListener('sourceopen', async () => {
                try {
                    sourceBuffer = mediaSource.addSourceBuffer(mimeType);
                } catch (e) {
                    console.warn('[voice-lab] addSourceBuffer failed', e);
                    finish();
                    return;
                }
                sourceBuffer.addEventListener('updateend', pumpPending);

                const reader = stream.getReader();

                try {
                    while (true) {
                        const { value, done } = await reader.read();
                        if (done) break;
                        if (!value || value.byteLength === 0) continue;
                        pending.push(value);
                        pumpPending();

                        if (!started) {
                            started = true;
                            const ctx = getAudioContext();
                            if (ctx.state === 'suspended') void ctx.resume();
                            trackAudioLevel(audio);
                            state.value = 'speaking';
                            void audio.play();
                        }
                    }
                } catch (e) {
                    console.warn('[voice-lab] stream read failed', e);
                }

                (mediaSource as MediaSource & { _done?: boolean })._done = true;
                pumpPending();
            }, { once: true });
        });
    }

    async function playBufferedAudio(response: Response): Promise<void> {
        const blob = await response.blob();
        return playResponse(blob);
    }

    async function playIntro(): Promise<void> {
        if (!intro?.audioUrl) return;
        state.value = 'speaking';

        try {
            const response = await fetch(intro.audioUrl, { credentials: 'same-origin' });
            if (!response.ok) throw new Error(`Intro fetch failed (${response.status})`);
            const blob = await response.blob();
            await playResponse(blob);
            choices.value = [...intro.choices];
            sfx.playReady();
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Intro failed to load');
        }
    }

    async function playResponse(audioBlob: Blob): Promise<void> {
        return new Promise((resolve) => {
            const url = URL.createObjectURL(audioBlob);
            const audio = new Audio(url);
            currentAudio = audio;

            let finished = false;

            const finish = (errored: boolean) => {
                if (finished) return;
                finished = true;
                // Detach BEFORE cleanup — stopPlayback() sets src='' which
                // triggers a synthetic 'error' event we must ignore on a
                // clean 'ended'.
                audio.removeEventListener('ended', onEnded);
                audio.removeEventListener('error', onError);
                stopPlayback();
                URL.revokeObjectURL(url);
                if (errored) {
                    setError('Audio playback failed');
                } else {
                    state.value = 'idle';
                }
                resolve();
            };

            const onEnded = () => finish(false);
            const onError = () => {
                if (finished) return;
                finish(true);
            };

            audio.addEventListener(
                'canplay',
                () => {
                    const ctx = getAudioContext();
                    if (ctx.state === 'suspended') ctx.resume();

                    trackAudioLevel(audio);
                    state.value = 'speaking';
                    void audio.play();
                },
                { once: true },
            );

            audio.addEventListener('ended', onEnded);
            audio.addEventListener('error', onError);

            audio.load();
        });
    }

    async function sendChoice(choice: string): Promise<void> {
        if (state.value !== 'idle') return;

        choices.value = [];
        state.value = 'thinking';
        startThinkingTicks();

        try {
            const result = await streamAiResponse(choice);
            stopThinkingTicks();
            choices.value = result.choices;
            await result.played;
            sfx.playReady();
        } catch (err) {
            stopThinkingTicks();
            setError(err instanceof Error ? err.message : 'Something went wrong');
        }
    }

    async function clearHistory(): Promise<void> {
        await fetch('/user/voice-lab/history', {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: makeHeaders(),
        });
        hasStartedIntro.value = false;
        choices.value = [];
        listenPending = false;
        state.value = 'idle';
    }

    function cleanup() {
        listenPending = false;
        stopThinkingTicks();
        stopPlayback();
        stopMicLevel();
        releaseMic();
        stopAudioLevel();
        micAnalyser = null;
        playbackAnalyser = null;
        if (audioContext) {
            audioContext.close();
            audioContext = null;
        }
    }

    onUnmounted(cleanup);

    return {
        state,
        audioLevel,
        errorMessage,
        choices,
        isActive,
        hasStartedIntro,
        activate,
        sendChoice,
        clearHistory,
        cleanup,
    };
}
