# Voice Hallucination Guard

## Problem

ElevenLabs `eleven_v3` hallucinates on long or dense passages — it speaks words that don't exist in the source text. Observed on user `tiffyb`'s session: after an all-caps system message (`ACCESS LEVEL: DENIED`), the voice generated several lines of narration that aren't present in the stored `prompts.response` at all.

Root cause: the entire response (multiple `<p>` blocks of stylized prose) is sent as a single text blob to the TTS API. The autoregressive model loses alignment with the source text and starts generating plausible-sounding but fabricated speech.

### Current Flow

```
prompt.response (HTML)
    → strip_tags()
    → single ElevenLabs API call with full text
    → MP3 returned and cached
```

Both `TextToSpeechController` and `VoiceLab/RespondController` have this pattern.

## Approach: Paragraph-Chunked Generation

Split the HTML response by `<p>` tags before stripping, send each paragraph as a separate TTS request, and concatenate the resulting audio buffers into a single MP3.

### Proposed Flow

```
prompt.response (HTML)
    → split on </p> boundaries
    → strip_tags() each chunk
    → filter empty chunks
    → parallel ElevenLabs calls (one per paragraph)
    → concatenate MP3 buffers in order
    → cache final MP3
```

### Implementation Sketch — TextToSpeechController

```php
private function generate(Prompt $prompt, string $path): void
{
    $chunks = $this->splitIntoParagraphs($prompt->response);
    $voiceId = config('services.elevenlabs.voice_id');
    $apiKey = config('services.elevenlabs.api_key');
    $modelId = config('services.elevenlabs.model_id', 'eleven_v3');

    abort_unless(filled($apiKey), 503, 'Voice generation is not configured.');

    $audioBuffers = [];

    // Could use Http::pool() for parallel requests
    foreach ($chunks as $chunk) {
        $response = Http::withHeaders([
            'xi-api-key' => $apiKey,
        ])->timeout(60)->post(
            "https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}/stream?output_format=mp3_44100_128",
            [
                'text' => $chunk,
                'model_id' => $modelId,
                'voice_settings' => [
                    'stability' => 0.50,
                    'similarity_boost' => 0.75,
                    'style' => 0.0,
                    'speed' => 1.0,
                ],
            ]
        );

        if (! $response->successful()) {
            logger()->warning('ElevenLabs TTS chunk failed', [
                'status' => $response->status(),
                'prompt_id' => $prompt->id,
                'chunk_length' => mb_strlen($chunk),
            ]);
            abort(502, 'Voice generation unavailable.');
        }

        $audioBuffers[] = $response->body();
    }

    Storage::disk('local')->put($path, implode('', $audioBuffers));
}

private function splitIntoParagraphs(string $html): array
{
    // Split on closing </p> tags to get paragraph boundaries
    $parts = preg_split('/<\/p>/i', $html);

    return collect($parts)
        ->map(fn (string $part) => trim(strip_tags($part)))
        ->filter(fn (string $part) => filled($part))
        ->values()
        ->toArray();
}
```

## Considerations

### MP3 concatenation
Raw MP3 frame concatenation works because MP3 is a streaming format — frames are independent. No re-encoding needed. There may be tiny artifacts at chunk boundaries, but they're inaudible for speech.

### Parallel requests
Use `Http::pool()` to send all paragraph chunks concurrently. Reduces total latency from `N * avg_latency` to roughly `max(latency_per_chunk)`. ElevenLabs rate limits apply — check plan tier.

### Chunk size guardrails
- **Min chunk**: if a paragraph is under ~20 chars, merge it with the next to avoid weird short utterances.
- **Max chunk**: if a single paragraph exceeds ~2000 chars, split further on sentence boundaries (`. ` or `— `).
- **All-caps normalization**: convert runs of 3+ uppercase words to sentence case before sending. Caps cause the model to shift delivery aggressively, which contributes to drift.

### Stability tuning
Current `stability: 0.50` is moderate. For chunked generation:
- Each chunk resets the model's internal state, so drift risk is already lower.
- Could keep 0.50 for expressiveness since chunks are short enough to stay anchored.
- If hallucinations persist on specific chunks, bump to 0.60.

### Cache invalidation
Cached MP3s at `tts/{prompt_id}.mp3` won't regenerate unless deleted. If a user already heard the hallucinated version, the cached file needs to be purged. Consider a cache-bust mechanism or versioned paths.

## Files to Change

- `app/Http/Controllers/User/Game/TextToSpeechController.php`
- `app/Http/Controllers/User/VoiceLab/RespondController.php`
- Optionally extract shared logic into `App\Services\TextToSpeechService`

## Testing

1. Export the `tiffyb` user's prompts and replay the problematic response through the chunked pipeline.
2. Compare audio output against the stored `response` text — verify no fabricated speech.
3. Test edge cases: very short responses (1 sentence), very long responses (5+ paragraphs), responses with all-caps blocks, responses with heavy `<em>`/`<strong>` formatting.
