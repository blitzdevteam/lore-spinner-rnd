/**
 * Synthesized Apple-style UI tones for the Voice Lab orb.
 *
 * Zero assets, zero network. Single shared AudioContext (created lazily so
 * autoplay policies don't reject it), short sine/triangle envelopes. Every
 * orb interaction has a distinct voice:
 *
 *   wake        — first tap that kicks off the experience
 *   listenStart — mic opened, ready to record
 *   listenStop  — mic closed, going into processing
 *   thinkingTick— subtle loop while we wait for the AI (>1.5s only)
 *   ready       — narration finished, idle again, choices visible
 *   error       — something went wrong, state auto-recovers
 */

type EnvelopePoint = { t: number; v: number };

interface ToneOptions {
    type?: OscillatorType;
    frequency: number;
    frequencyEnd?: number;
    duration: number;
    envelope?: EnvelopePoint[];
    detune?: number;
    gain?: number;
    delay?: number;
}

let ctx: AudioContext | null = null;
let masterGain: GainNode | null = null;

function ensureContext(): AudioContext | null {
    if (ctx) return ctx;
    try {
        const AC = window.AudioContext || (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
        if (!AC) return null;
        ctx = new AC();
        masterGain = ctx.createGain();
        masterGain.gain.value = 0.28;
        masterGain.connect(ctx.destination);
    } catch {
        ctx = null;
    }
    return ctx;
}

function playTone(opts: ToneOptions) {
    const context = ensureContext();
    if (!context || !masterGain) return;

    if (context.state === 'suspended') {
        void context.resume();
    }

    const now = context.currentTime + (opts.delay ?? 0);
    const duration = opts.duration;

    const osc = context.createOscillator();
    const gain = context.createGain();

    osc.type = opts.type ?? 'sine';
    osc.frequency.setValueAtTime(opts.frequency, now);
    if (typeof opts.frequencyEnd === 'number') {
        osc.frequency.exponentialRampToValueAtTime(Math.max(1, opts.frequencyEnd), now + duration);
    }
    if (typeof opts.detune === 'number') {
        osc.detune.setValueAtTime(opts.detune, now);
    }

    const peak = opts.gain ?? 1;
    const env = opts.envelope ?? [
        { t: 0, v: 0 },
        { t: 0.02, v: peak },
        { t: duration, v: 0 },
    ];

    gain.gain.setValueAtTime(0, now);
    for (const point of env) {
        const time = now + Math.max(0, point.t);
        const value = Math.max(0.0001, point.v);
        if (point.t === 0) {
            gain.gain.setValueAtTime(value, time);
        } else {
            gain.gain.exponentialRampToValueAtTime(value, time);
        }
    }
    gain.gain.exponentialRampToValueAtTime(0.0001, now + duration + 0.02);

    osc.connect(gain);
    gain.connect(masterGain);
    osc.start(now);
    osc.stop(now + duration + 0.05);
}

export function useVoiceSfx() {
    /** Call once from any user gesture (e.g. first orb tap) to arm the audio context. */
    function prime() {
        ensureContext();
    }

    /** Wake — first tap of the experience. Two-tone rising chime. */
    function playWake() {
        playTone({
            type: 'sine',
            frequency: 523.25, // C5
            duration: 0.18,
            envelope: [
                { t: 0, v: 0 },
                { t: 0.015, v: 1 },
                { t: 0.18, v: 0 },
            ],
            gain: 1,
        });
        playTone({
            type: 'sine',
            frequency: 783.99, // G5
            duration: 0.24,
            envelope: [
                { t: 0, v: 0 },
                { t: 0.02, v: 0.9 },
                { t: 0.24, v: 0 },
            ],
            delay: 0.09,
        });
    }

    /** Listen start — clean rising ping, "recording on". */
    function playListenStart() {
        playTone({
            type: 'sine',
            frequency: 660,
            frequencyEnd: 990,
            duration: 0.12,
            envelope: [
                { t: 0, v: 0 },
                { t: 0.01, v: 0.9 },
                { t: 0.12, v: 0 },
            ],
        });
    }

    /** Listen stop — soft descending ping, "recording off, thinking". */
    function playListenStop() {
        playTone({
            type: 'sine',
            frequency: 880,
            frequencyEnd: 523,
            duration: 0.14,
            envelope: [
                { t: 0, v: 0 },
                { t: 0.01, v: 0.8 },
                { t: 0.14, v: 0 },
            ],
        });
    }

    /** Thinking tick — very short low tick. Caller decides cadence. */
    function playThinkingTick() {
        playTone({
            type: 'triangle',
            frequency: 320,
            duration: 0.05,
            envelope: [
                { t: 0, v: 0 },
                { t: 0.005, v: 0.4 },
                { t: 0.05, v: 0 },
            ],
            gain: 0.5,
        });
    }

    /** Ready — brief soft bloom when narration ends and choices appear. */
    function playReady() {
        playTone({
            type: 'sine',
            frequency: 880,
            duration: 0.16,
            envelope: [
                { t: 0, v: 0 },
                { t: 0.02, v: 0.7 },
                { t: 0.16, v: 0 },
            ],
            gain: 0.8,
        });
    }

    /** Error — two descending, slightly detuned pings. Apple's "nope" feel. */
    function playError() {
        playTone({
            type: 'triangle',
            frequency: 520,
            duration: 0.16,
            envelope: [
                { t: 0, v: 0 },
                { t: 0.01, v: 0.8 },
                { t: 0.16, v: 0 },
            ],
            gain: 0.9,
        });
        playTone({
            type: 'triangle',
            frequency: 392,
            duration: 0.22,
            envelope: [
                { t: 0, v: 0 },
                { t: 0.01, v: 0.7 },
                { t: 0.22, v: 0 },
            ],
            gain: 0.9,
            delay: 0.1,
        });
    }

    return {
        prime,
        playWake,
        playListenStart,
        playListenStop,
        playThinkingTick,
        playReady,
        playError,
    };
}
