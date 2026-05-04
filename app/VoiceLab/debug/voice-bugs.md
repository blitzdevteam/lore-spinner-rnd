# Voice Lab — Bug Log & Latency Analysis

Status: open. Captured 2026-04-17 during the first round of field testing
of the isolated Voice Lab module.

## 1. How Voice Lab Generates a "Game" Today

It is not a game in the main-app sense — it is a stateless-ish conversational
demo grounded in Alice's world.

```
[tap orb]  → MediaRecorder (webm/opus) starts
[tap orb]  → stop recording
   ├─► POST /user/voice-lab/transcribe   (Whisper, blocking)
   ├─► POST /user/voice-lab/respond      (OpenAI GPT, blocking)
   │     └─► ElevenLabs TTS              (full MP3, blocking)
   └─► audio Blob returned, <audio> plays
```

- No event tree, no chapter progression, no advancement flags.
- `VoiceChatAgent` returns `{response, choices}` grounded only in Alice's
  `system_prompt` JSON (character / world / tone) plus the last 6 turns
  from `voice_lab_prompts`.

## 2. Honest Performance Assessment

The pipeline is **fully sequential and fully buffered**. The user is waiting
through every stage before anything plays:

| Stage                                | Typical time | Source                                  |
|--------------------------------------|--------------|-----------------------------------------|
| Whisper upload + transcription       | 0.8 – 2.5 s  | `TranscribeController`                  |
| GPT-5.2 structured output            | 2 – 5 s      | `VoiceChatAgent` + `ProcessVoiceTurnAction` |
| ElevenLabs `eleven_v3` full MP3      | 2 – 6 s      | `ElevenLabsVoiceService`                |
| **User-perceived silence before audio** | **5 – 13 s** | before any sound plays                  |

We *request* streaming from ElevenLabs (`/stream` endpoint), but then do
`$response->body()`, which **buffers the entire MP3 in PHP memory** before
returning. The `StreamedResponse` also just `echo`es the full body at once.
End-to-end, zero streaming actually happens.

## 3. Reported Bugs — Root Causes & Proposed Fixes

### Bug 1 — "1-second echo of my voice after stopping"

**Root cause.** When the user taps to stop:

- `MediaRecorder` is configured with `mediaRecorder.start(250)` — 250 ms
  chunks.
- On `.stop()` it flushes whatever is in its internal buffer (typically
  ~250 ms to ~1 s of audio captured **after** the tap).
- `trackMicLevel` keeps the shared `AnalyserNode` hot until
  `stopMicLevel()` runs inside the `'stop'` event — which fires **after**
  that flush. The residual mic level feeds through the shared audio graph.
- Whisper sometimes transcribes that trailing audio into the user message,
  which can also leak into the next turn's context.

**Fixes, in order of effort:**

1. Call `stopMicLevel()` and `releaseMic()` **synchronously before**
   `mediaRecorder.stop()` so the analyser is disconnected the instant the
   user taps.
2. Give mic and playback **separate analyser nodes** — currently both
   `trackMicLevel` and `trackAudioLevel` share the same `analyser` singleton
   from `ensureAnalyser()`, which is the real root of the cross-feed.
3. Trim the trailing audio before sending: either slice the last ~200 ms
   off the blob, or call `mediaRecorder.requestData()` right before
   `.stop()` so the flush is already on disk.

### Bug 2 — Latency

Ranked by ROI (highest first):

#### (a) TTS is not actually streaming

We hit `/v1/text-to-speech/{voice}/stream` but discard the stream by
buffering. Switching to true streaming (flush chunks to the client as they
arrive from ElevenLabs) drops perceived latency from *total pipeline time*
to *first-byte*, typically **1.5 – 2.5 s** vs the current 5 – 13 s.

Requires:
- Rewrite `ElevenLabsVoiceService::speak()` to yield chunks instead of
  returning a full string. Use `Http::withOptions(['stream' => true])` or
  drop to a native cURL handle.
- Rewrite `RespondController` to use a real streaming callback in
  `StreamedResponse` (no `echo $body` at the end — `echo` each chunk as
  it arrives and `flush()`).
- Verify Laravel Cloud's reverse proxy (and any FastCGI buffering) is
  not re-buffering the response. May require `X-Accel-Buffering: no`
  or explicit header tuning.

#### (b) `eleven_v3` is the slowest model

Great quality, bad for realtime. For a demo:
- `eleven_turbo_v2_5` or `eleven_flash_v2_5` → ~75 – 150 ms first-chunk
  latency vs ~1 – 3 s for v3.
- Pure config change: `config/voice-lab.php` → `model_id` (or
  `VOICELAB_MODEL_ID` env var). Zero code changes.

#### (c) LLM model is larger than needed

For a stateless conversational demo without the event engine, we do not
need the full `gpt-5.2`. Dropping to a "mini" variant saves ~1 – 2 s per
turn.

- Change `#[Model('gpt-5.2')]` in `app/VoiceLab/Agents/VoiceChatAgent.php`
  to whatever mini equivalent is registered in `Laravel\Ai`.

#### (d) Nuclear option — collapse STT+LLM+TTS

ElevenLabs Conversational AI (native STT → LLM → TTS over a single
WebSocket session) gives sub-second turn times but is a full rewrite, not
a tweak. Considered out of scope for the current demo.

### Bug 3 — "Written decision points need to be clearer and maybe longer"

Buttons on screen are terse because the current system prompt at
`resources/views/voice-lab/agents/voice-chat/system-prompt.blade.php`
instructs the AI:

> - Always finish by offering 2-3 organic next directions the listener could take.
> - The `choices` array in your JSON output MUST match the options you
>   just verbally offered, as **short action strings** (for UI buttons).

The spoken narration is rich ("follow the rabbit deeper into the
tunnel..."), but the array collapses to "Follow the rabbit".

**Fixes:**
- Relax the prompt: let `choices[]` be **short sentences, 6 – 14 words**,
  written for *reading*, not for terseness. Example: "Follow the White
  Rabbit down the darkening tunnel."
- Require a **pre-scene context line** on the very first turn: one extra
  sentence establishing mood before the first question, so the listener
  isn't dropped straight into "You could...".

### Bug 4 — Replay option requested

The pipeline already supports this on the client. Two options:

1. **Client-only (ship today):** keep the last response `Blob` in
   `useVoiceLab`, expose a `replay()` function that calls
   `playResponse(lastAudio)`. Zero backend change. ~20 lines.
2. **Persisted replay:** write the MP3 to Laravel Cloud's private disk
   when we store the `VoiceLabPrompt` row, serve via signed URL. Needed
   only if replay must survive a page reload. The `voice_lab_prompts`
   table already has a reserved `audio_ms` column — add `audio_path`.

## 4. Recommended Phased Fix

Three incremental PRs, smallest scope first:

| Phase | Scope                                                    | Expected wins                                   | Effort |
|-------|----------------------------------------------------------|-------------------------------------------------|--------|
| 1     | Echo bug + richer choice sentences + client replay       | Kills most annoying bug, ships 2 of 4 requests  | ~1–2 h |
| 2     | Swap TTS model + LLM model (env-only)                    | 3 – 5 s off every turn                          | ~15 m  |
| 3     | True ElevenLabs streaming end-to-end                     | 5 – 10 s off first-audio latency                | ~½ day |

Phase 1 should go first because it's pure prompt + composable work with no
infra risk and no model cost change. Phase 2 is a toggle — can be A/B
tested by flipping env vars in Laravel Cloud. Phase 3 needs real testing
against the Laravel Cloud proxy to confirm chunks aren't re-buffered.

## 5. Files Involved

- `resources/js/voice-lab/composables/useVoiceLab.ts` — mic capture, STT
  call, playback, shared analyser (**bug 1**).
- `resources/views/voice-lab/agents/voice-chat/system-prompt.blade.php`
  — choice verbosity and pre-scene context (**bug 3**).
- `app/VoiceLab/Services/ElevenLabsVoiceService.php` — buffered vs
  streaming TTS (**bug 2a**).
- `app/VoiceLab/Http/Controllers/RespondController.php` — buffered
  `StreamedResponse` (**bug 2a**).
- `config/voice-lab.php` + env — `model_id`, voice settings (**bug 2b**).
- `app/VoiceLab/Agents/VoiceChatAgent.php` — `#[Model('gpt-5.2')]`
  attribute (**bug 2c**).
- `resources/js/voice-lab/pages/Index.vue` — UI for replay button
  (**bug 4**).
