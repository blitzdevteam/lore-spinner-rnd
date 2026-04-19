import { computed, onUnmounted, ref } from 'vue';

export type VoiceLabState = 'idle' | 'listening' | 'thinking' | 'speaking' | 'error';

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

export function useVoiceLab() {
    const state = ref<VoiceLabState>('idle');
    const audioLevel = ref(0);
    const errorMessage = ref<string | null>(null);
    const choices = ref<string[]>([]);

    let mediaRecorder: MediaRecorder | null = null;
    let audioChunks: Blob[] = [];
    let micStream: MediaStream | null = null;

    let audioContext: AudioContext | null = null;
    let analyser: AnalyserNode | null = null;
    let audioSource: MediaElementAudioSourceNode | null = null;
    let micSource: MediaStreamAudioSourceNode | null = null;
    let currentAudio: HTMLAudioElement | null = null;
    let levelRaf: number | null = null;

    const isActive = computed(() => state.value !== 'idle');

    function resetError() {
        errorMessage.value = null;
    }

    function setError(msg: string) {
        errorMessage.value = msg;
        state.value = 'error';
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
            micSource.disconnect();
            micSource = null;
        }
        stopAudioLevel();
    }

    function stopPlayback() {
        if (currentAudio) {
            currentAudio.pause();
            currentAudio.src = '';
            currentAudio = null;
        }
        if (audioSource) {
            audioSource.disconnect();
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

    function ensureAnalyser(): AnalyserNode {
        const ctx = getAudioContext();
        if (!analyser) {
            analyser = ctx.createAnalyser();
            analyser.fftSize = 256;
            analyser.smoothingTimeConstant = 0.7;
        }
        return analyser;
    }

    function startLevelLoop() {
        const node = analyser;
        if (!node) return;

        const dataArray = new Uint8Array(node.frequencyBinCount);

        function tick() {
            if (!analyser) return;
            analyser.getByteFrequencyData(dataArray);
            let sum = 0;
            for (let i = 0; i < dataArray.length; i++) {
                sum += dataArray[i];
            }
            audioLevel.value = Math.min(1, (sum / dataArray.length / 255) * 2.5);
            levelRaf = requestAnimationFrame(tick);
        }

        levelRaf = requestAnimationFrame(tick);
    }

    function trackMicLevel(stream: MediaStream) {
        const ctx = getAudioContext();
        const node = ensureAnalyser();

        micSource = ctx.createMediaStreamSource(stream);
        micSource.connect(node);

        startLevelLoop();
    }

    function trackAudioLevel(audio: HTMLAudioElement) {
        const node = ensureAnalyser();

        audioSource = getAudioContext().createMediaElementSource(audio);
        audioSource.connect(node);
        node.connect(getAudioContext().destination);

        startLevelLoop();
    }

    async function activate() {
        if (state.value === 'listening') {
            await finishRecording();
            return;
        }

        if (state.value === 'speaking') {
            stopPlayback();
            state.value = 'idle';
            return;
        }

        if (state.value !== 'idle' && state.value !== 'error') return;

        resetError();
        state.value = 'listening';

        try {
            micStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch {
            setError('Microphone access denied');
            return;
        }

        audioChunks = [];
        const mimeType = getSupportedMimeType();
        mediaRecorder = new MediaRecorder(micStream, { mimeType });

        mediaRecorder.addEventListener('dataavailable', (e: BlobEvent) => {
            if (e.data.size > 0) audioChunks.push(e.data);
        });

        trackMicLevel(micStream);
        mediaRecorder.start(250);
    }

    async function finishRecording(): Promise<void> {
        return new Promise((resolve) => {
            if (!mediaRecorder || mediaRecorder.state === 'inactive') {
                state.value = 'idle';
                resolve();
                return;
            }

            mediaRecorder.addEventListener(
                'stop',
                async () => {
                    stopMicLevel();
                    releaseMic();

                    const mimeType = mediaRecorder?.mimeType || 'audio/webm';
                    const audioBlob = new Blob(audioChunks, { type: mimeType });
                    audioChunks = [];

                    if (audioBlob.size < 500) {
                        state.value = 'idle';
                        resolve();
                        return;
                    }

                    state.value = 'thinking';

                    try {
                        const text = await transcribe(audioBlob);
                        if (!text.trim()) {
                            setError('Could not understand audio');
                            resolve();
                            return;
                        }

                        const result = await getAiResponse(text);
                        choices.value = result.choices;
                        await playResponse(result.audio);
                    } catch (err) {
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

    async function getAiResponse(message: string): Promise<{ audio: Blob; choices: string[] }> {
        const response = await fetch('/user/voice-lab/respond', {
            method: 'POST',
            credentials: 'same-origin',
            headers: makeHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ message }),
        });

        if (!response.ok) throw new Error('AI response failed');

        let parsedChoices: string[] = [];
        try {
            const raw = response.headers.get('X-VoiceLab-Choices');
            if (raw) parsedChoices = JSON.parse(raw);
        } catch {
            /* header absent or malformed — non-fatal */
        }

        return { audio: await response.blob(), choices: parsedChoices };
    }

    async function playResponse(audioBlob: Blob): Promise<void> {
        return new Promise((resolve) => {
            const url = URL.createObjectURL(audioBlob);
            const audio = new Audio(url);
            currentAudio = audio;

            audio.addEventListener(
                'canplay',
                () => {
                    const ctx = getAudioContext();
                    if (ctx.state === 'suspended') ctx.resume();

                    trackAudioLevel(audio);
                    state.value = 'speaking';
                    audio.play();
                },
                { once: true },
            );

            audio.addEventListener(
                'ended',
                () => {
                    stopPlayback();
                    URL.revokeObjectURL(url);
                    state.value = 'idle';
                    resolve();
                },
                { once: true },
            );

            audio.addEventListener(
                'error',
                () => {
                    stopPlayback();
                    URL.revokeObjectURL(url);
                    setError('Audio playback failed');
                    resolve();
                },
                { once: true },
            );

            audio.load();
        });
    }

    async function sendChoice(choice: string): Promise<void> {
        if (state.value !== 'idle') return;

        choices.value = [];
        state.value = 'thinking';

        try {
            const result = await getAiResponse(choice);
            choices.value = result.choices;
            await playResponse(result.audio);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Something went wrong');
        }
    }

    async function clearHistory(): Promise<void> {
        await fetch('/user/voice-lab/history', {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: makeHeaders(),
        });
    }

    function cleanup() {
        stopPlayback();
        stopMicLevel();
        releaseMic();
        stopAudioLevel();
        if (audioContext) {
            audioContext.close();
            audioContext = null;
            analyser = null;
        }
    }

    onUnmounted(cleanup);

    return {
        state,
        audioLevel,
        errorMessage,
        choices,
        isActive,
        activate,
        sendChoice,
        clearHistory,
        cleanup,
    };
}
