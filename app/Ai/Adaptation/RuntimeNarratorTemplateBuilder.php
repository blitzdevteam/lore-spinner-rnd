<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

use App\ChaosMode\ChaosStoryConfig;
use App\Models\Event;
use App\Models\SessionAdaptation;
use App\Models\Story;
use Illuminate\Support\Facades\View;

/**
 * Pipeline Upgrade V2 — Deliverable 8 assembler.
 *
 * Reads every pipeline output the runtime narrator needs and renders the
 * 17-section template at `ai.agents.chaos.runtime-narrator-template` into a
 * single string suitable for caching on `session_adaptations.runtime_narrator_prompt`.
 *
 * Bounded to MAX_PROMPT_CHARS. Implements the compression
 * cascade described in the deliverable:
 *   1. Compress Section 12 (Full Source Script) — keep first/last event
 *      verbatim, summarize middle events to titles + objectives only.
 *   2. Drop Voice Profile direct quotes (keep technique names + IP-specific bans).
 *   3. Flag for editorial split (raise an exception caller can handle by
 *      splitting the story session in half).
 *
 * The injection-point tokens left in the template
 * (`[SYMBOLIC_MEMORY_INJECTION_POINT]`, `[ALIGNMENT_TILT_INJECTION_POINT]`,
 * `[OPENING_SCENE_INJECTION_POINT]`, `[WORLD_STATE_TIERED_INJECTION_POINT]`)
 * are filled in by ChaosModeController at runtime, not here.
 */
final class RuntimeNarratorTemplateBuilder
{
    /**
     * Hard cap for the cached runtime narrator prompt.
     */
    public const MAX_PROMPT_CHARS = 128_000;

    /**
     * Build the cached runtime narrator prompt for a given session adaptation.
     *
     * @throws \RuntimeException if no compression strategy fits the prompt under
     *                            MAX_PROMPT_CHARS — caller should mark the
     *                            session as "needs editorial split".
     */
    public function build(Story $story, SessionAdaptation $session): string
    {
        $adaptation = $session->storyAdaptation;
        $totalSessions = $adaptation->sessionAdaptations()->count();

        $sessionEvents = $this->loadSessionEvents($story, $session->session_number);

        $compressionAttempts = [
            'full' => fn () => $sessionEvents,
            'compressed_source' => fn () => $this->compressSourceEvents($sessionEvents),
            'titles_only_source' => fn () => $this->titlesOnlySourceEvents($sessionEvents),
        ];

        $voiceCompression = [
            'full' => fn (array $voice) => $voice,
            'drop_quotes' => fn (array $voice) => $this->dropVoiceQuotes($voice),
        ];

        foreach ($compressionAttempts as $sourceMode => $sourceFn) {
            foreach ($voiceCompression as $voiceMode => $voiceFn) {
                $rendered = $this->render($story, $session, $totalSessions, $sourceFn(), $voiceFn((array) ($adaptation->voice_profile ?? [])));

                if (mb_strlen($rendered) <= self::MAX_PROMPT_CHARS) {
                    return $rendered;
                }
            }
        }

        throw new \RuntimeException(sprintf(
            'Runtime narrator template exceeds %d chars after all compression strategies (story %d, session %d). Editorial split required.',
            self::MAX_PROMPT_CHARS,
            $story->id,
            $session->session_number,
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $sessionEvents
     * @param  array<string, mixed>              $voiceProfile
     */
    private function render(Story $story, SessionAdaptation $session, int $totalSessions, array $sessionEvents, array $voiceProfile): string
    {
        $adaptation = $session->storyAdaptation;
        $trimming = (array) ($adaptation->ip_trimming ?? []);
        $sessionMap = (array) ($adaptation->story_session_map ?? []);
        $allocation = $this->findAllocationRow($sessionMap, $session->session_number);
        $architecture = (array) ($session->session_architecture ?? []);
        $choiceDesign = (array) ($session->session_choice_design ?? []);
        $consequenceMap = (array) ($session->choice_consequence_map ?? []);
        $sessionSpine = [
            'dramatic_question' => (string) ($allocation['primary_dramatic_question'] ?? ''),
            'emotional_promise' => (string) (($session->entry_point_diagnosis['emotional_promise'] ?? '')),
            'emotional_register' => (string) ($allocation['emotional_register'] ?? ''),
            'chapters_covered' => (string) ($allocation['chapters_covered'] ?? ''),
            'session_destination' => (string) (
                $session->session_close_design['hook_transition']
                ?? $session->session_close_design['session_end_choice']['choice_question']
                ?? ''
            ),
            'next_session_seed' => (string) ($architecture['next_session_awareness']['seed_for_next_session'] ?? ''),
        ];
        $persistentState = $this->scopePersistentState(
            (array) ($sessionMap['persistent_state_schema'] ?? []),
            $sessionEvents,
            $sessionSpine,
            $architecture,
            $choiceDesign,
            $consequenceMap,
        );

        $rendered = View::make('ai.agents.chaos.runtime-narrator-template', [
            'storyTitle' => $story->title,
            'authorName' => $story->creator?->name ?? 'the author',
            'sessionNumber' => $session->session_number,
            'totalSessions' => $totalSessions,
            'protagonist' => $this->protagonistName($story, $adaptation),

            'spine' => $this->normalizeSpine($trimming, $story),
            'worldRules' => (array) ($trimming['world_rules'] ?? []),
            'storyGuard' => (array) ($sessionMap['story_guard_canon'] ?? []),
            'sceneRules' => (array) ($choiceDesign['scene_rules_layer_4'] ?? []),
            'voice' => $voiceProfile,
            'persistentState' => $persistentState,
            'reactivityRules' => (array) ($sessionMap['world_reactivity_rules'] ?? []),
            'alignmentLabels' => (array) ($sessionMap['alignment_labels'] ?? []),

            'sessionSpine' => $sessionSpine,
            'beatMap' => (array) ($architecture['beat_map'] ?? []),
            'sessionEvents' => $sessionEvents,

            'branchingChoices' => (array) ($choiceDesign['branching_choices'] ?? []),
            'emotionalChoices' => (array) ($choiceDesign['emotional_choices'] ?? []),
            'postureShifts' => (array) ($choiceDesign['posture_shifts'] ?? []),

            'consequenceMaps' => (array) ($consequenceMap['branching_consequences'] ?? []),
            'freeformGuidelines' => (array) ($consequenceMap['freeform_guidelines'] ?? []),

            'editorialStatus' => (string) (
                data_get($session->editorial_verification, 'final_verdict.production_status')
                ?? data_get($session->editorial_verification, 'production_status')
                ?? 'UNVERIFIED'
            ),
        ])->render();

        return html_entity_decode($rendered, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @param  array<string, mixed>  $adaptation
     */
    private function protagonistName(Story $story, $adaptation): string
    {
        return (string) (
            ChaosStoryConfig::find($story->slug)['protagonist'] ?? null
            ?? $adaptation->ip_trimming['story_spine']['protagonist_name'] ?? null
            ?? $adaptation->ip_trimming['story_spine']['protagonist']
            ?? $adaptation->voice_profile['author_voice_dna_profile']['dialogue_fingerprint_per_character'][0]['character']
            ?? 'the protagonist'
        );
    }

    /**
     * @param  array<string, mixed>  $trimming
     * @return array<string, mixed>
     */
    private function normalizeSpine(array $trimming, Story $story): array
    {
        $spine = (array) ($trimming['story_spine'] ?? []);

        // ChaosStoryConfig is the canonical playable protagonist — it takes
        // priority over whatever the AI wrote during the trimming merge pass.
        $protagonist = ChaosStoryConfig::find($story->slug)['protagonist']
            ?? (string) ($spine['protagonist'] ?? 'the protagonist');

        return [
            'protagonist' => $protagonist,
            'dramatic_question' => (string) ($spine['dramatic_question'] ?? '(not specified)'),
            'world' => (string) ($spine['world'] ?? '(not specified)'),
            'major_turning_points' => (array) ($spine['major_turning_points'] ?? []),
            'irreversible_events' => (array) ($spine['irreversible_events'] ?? []),
        ];
    }

    /**
     * Scope the static tracking schema to the current session without touching
     * actual runtime memory. `chaos_sessions.world_state` still carries every
     * real player interaction across sessions; this only keeps future schema
     * details from flooding early prompts.
     *
     * @param  array<string, mixed>  $persistentState
     * @param  array<int, array<string, mixed>>  $sessionEvents
     * @param  array<string, mixed>  $sessionSpine
     * @param  array<string, mixed>  $architecture
     * @param  array<string, mixed>  $choiceDesign
     * @param  array<string, mixed>  $consequenceMap
     * @return array<string, mixed>
     */
    private function scopePersistentState(
        array $persistentState,
        array $sessionEvents,
        array $sessionSpine,
        array $architecture,
        array $choiceDesign,
        array $consequenceMap,
    ): array {
        $haystack = $this->stateScopeHaystack(
            $sessionEvents,
            $sessionSpine,
            $architecture,
            $choiceDesign,
            $consequenceMap,
        );

        $objects = (array) ($persistentState['objects'] ?? []);
        $npcs = (array) ($persistentState['npcs'] ?? []);
        $flags = (array) ($persistentState['world_flags'] ?? []);

        $activeObjects = [];
        $dormantObjects = [];
        foreach ($objects as $object) {
            $name = (string) ($object['name'] ?? '');
            if ($this->stateEntryMatches($name, $haystack, requireAllTokens: true)) {
                $activeObjects[] = $object;
            } elseif ($name !== '') {
                $dormantObjects[] = $name;
            }
        }

        $activeNpcs = [];
        $dormantNpcs = [];
        foreach ($npcs as $npc) {
            $name = (string) ($npc['name'] ?? '');
            if ($this->stateEntryMatches($name, $haystack, requireAllTokens: false)) {
                $activeNpcs[] = $npc;
            } elseif ($name !== '') {
                $dormantNpcs[] = $name;
            }
        }

        $activeFlags = [];
        $dormantFlags = [];
        foreach ($flags as $flag) {
            $name = (string) ($flag['name'] ?? '');
            if ($this->stateEntryMatches($name, $haystack, requireAllTokens: false)) {
                $activeFlags[] = $flag;
            } elseif ($name !== '') {
                $dormantFlags[] = $name;
            }
        }

        return [
            'objects' => $activeObjects,
            'npcs' => $activeNpcs,
            'world_flags' => $activeFlags,
            'dormant_objects' => $dormantObjects,
            'dormant_npcs' => $dormantNpcs,
            'dormant_world_flags' => $dormantFlags,
            'player_historical_archive_categories' => (array) ($persistentState['player_historical_archive_categories'] ?? []),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sessionEvents
     * @param  array<string, mixed>  $sessionSpine
     * @param  array<string, mixed>  $architecture
     * @param  array<string, mixed>  $choiceDesign
     * @param  array<string, mixed>  $consequenceMap
     */
    private function stateScopeHaystack(
        array $sessionEvents,
        array $sessionSpine,
        array $architecture,
        array $choiceDesign,
        array $consequenceMap,
    ): string {
        $payload = [
            'events' => array_map(static fn (array $event) => [
                'title' => $event['title'] ?? '',
                'objectives' => $event['objectives'] ?? '',
                'content' => $event['content'] ?? '',
                'chapter_title' => $event['chapter_title'] ?? '',
            ], $sessionEvents),
            'session_spine' => $sessionSpine,
            'beat_map' => $architecture['beat_map'] ?? [],
            'choice_design' => [
                'branching_choices' => $choiceDesign['branching_choices'] ?? [],
                'emotional_choices' => $choiceDesign['emotional_choices'] ?? [],
                'posture_shifts' => $choiceDesign['posture_shifts'] ?? [],
            ],
            'consequence_map' => $consequenceMap,
        ];

        return $this->normalizeStateText(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    private function stateEntryMatches(string $name, string $haystack, bool $requireAllTokens): bool
    {
        $tokens = $this->stateNameTokens($name);
        if ($tokens === []) {
            return false;
        }

        $hits = array_filter($tokens, static fn (string $token) => str_contains($haystack, $token));

        return $requireAllTokens
            ? count($hits) === count($tokens)
            : $hits !== [];
    }

    /**
     * @return array<int, string>
     */
    private function stateNameTokens(string $name): array
    {
        $normalized = $this->normalizeStateText($name);
        $tokens = preg_split('/\s+/', $normalized) ?: [];
        $stopwords = [
            'a', 'an', 'and', 'as', 'by', 'current', 'for', 'in', 'of', 'or',
            'state', 'status', 'the', 'to', 'with',
        ];

        return array_values(array_unique(array_filter(
            $tokens,
            static fn (string $token) => mb_strlen($token) >= 3 && ! in_array($token, $stopwords, true),
        )));
    }

    private function normalizeStateText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = mb_strtolower($text);

        return trim((string) preg_replace('/[^a-z0-9]+/u', ' ', $text));
    }

    /**
     * @param  array<string, mixed>  $sessionMap
     * @return array<string, mixed>
     */
    private function findAllocationRow(array $sessionMap, int $sessionNumber): array
    {
        foreach ((array) ($sessionMap['session_allocation'] ?? []) as $row) {
            if ((int) ($row['session_number'] ?? 0) === $sessionNumber) {
                return (array) $row;
            }
        }
        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadSessionEvents(Story $story, int $sessionNumber): array
    {
        return Event::query()
            ->join('chapters', 'events.chapter_id', '=', 'chapters.id')
            ->where('chapters.story_id', $story->id)
            ->where('events.session_number', $sessionNumber)
            ->orderBy('chapters.position')
            ->orderBy('events.position')
            ->get([
                'events.id',
                'events.chapter_id',
                'events.position',
                'events.title',
                'events.content',
                'events.objectives',
                'events.session_number',
                'chapters.position as chapter_position',
                'chapters.title as chapter_title',
            ])
            ->map(fn (Event $e) => [
                'id' => (int) $e->id,
                'chapter_id' => (int) $e->chapter_id,
                'chapter_position' => (int) $e->chapter_position,
                'chapter_title' => (string) $e->chapter_title,
                'position' => (int) $e->position,
                'session_number' => (int) $e->session_number,
                'title' => (string) $e->title,
                'content' => (string) $e->content,
                'objectives' => $e->objectives,
            ])
            ->all();
    }

    /**
     * Compression strategy 1: keep first + last event verbatim; replace
     * middle events with title + objective summaries only.
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, array<string, mixed>>
     */
    private function compressSourceEvents(array $events): array
    {
        if (count($events) <= 2) {
            return $events;
        }

        $first = array_shift($events);
        $last = array_pop($events);

        $middle = array_map(static fn ($e) => [
            'id' => $e['id'] ?? null,
            'chapter_id' => $e['chapter_id'] ?? null,
            'chapter_position' => $e['chapter_position'] ?? null,
            'chapter_title' => $e['chapter_title'] ?? null,
            'position' => $e['position'],
            'session_number' => $e['session_number'] ?? null,
            'title' => $e['title'],
            'objectives' => $e['objectives'],
            'content' => '(content compressed to save context budget — see objective above for dramatic shape; this beat falls between the first and last events.)',
        ], $events);

        return array_merge([$first], $middle, [$last]);
    }

    /**
     * Compression strategy 2: titles + objectives only, all events.
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, array<string, mixed>>
     */
    private function titlesOnlySourceEvents(array $events): array
    {
        return array_map(static fn ($e) => [
            'id' => $e['id'] ?? null,
            'chapter_id' => $e['chapter_id'] ?? null,
            'chapter_position' => $e['chapter_position'] ?? null,
            'chapter_title' => $e['chapter_title'] ?? null,
            'position' => $e['position'],
            'session_number' => $e['session_number'] ?? null,
            'title' => $e['title'],
            'objectives' => $e['objectives'],
            'content' => '(source compressed to title + objective only.)',
        ], $events);
    }

    /**
     * Voice compression: drop the source-quote arrays from the voice DNA
     * profile (keep technique names, ban list, sentence/diction summary).
     *
     * @param  array<string, mixed>  $voice
     * @return array<string, mixed>
     */
    private function dropVoiceQuotes(array $voice): array
    {
        $dna = $voice['author_voice_dna_profile'] ?? [];

        foreach ((array) ($dna['signature_writing_techniques'] ?? []) as $i => $t) {
            $dna['signature_writing_techniques'][$i]['quotes'] = [];
        }
        if (isset($dna['sentence_level_patterns']['demonstrative_quotes'])) {
            $dna['sentence_level_patterns']['demonstrative_quotes'] = [];
        }
        if (isset($dna['diction_fingerprint']['distinctive_diction_quotes'])) {
            $dna['diction_fingerprint']['distinctive_diction_quotes'] = [];
        }
        if (isset($dna['paragraph_architecture']['demonstrative_quotes'])) {
            $dna['paragraph_architecture']['demonstrative_quotes'] = [];
        }
        foreach ((array) ($dna['emotional_range_map'] ?? []) as $key => $entry) {
            $dna['emotional_range_map'][$key]['quote'] = '';
        }

        foreach ((array) ($dna['collocation_fingerprint'] ?? []) as $i => $col) {
            $dna['collocation_fingerprint'][$i]['quotes'] = [];
        }
        foreach ((array) ($dna['comparative_exclusion'] ?? []) as $i => $ex) {
            $dna['comparative_exclusion'][$i]['differentiating_techniques'] = array_map(
                fn ($t) => is_string($t) ? strtok($t, '—') ?: $t : $t,
                (array) ($ex['differentiating_techniques'] ?? [])
            );
        }

        $voice['author_voice_dna_profile'] = $dna;
        return $voice;
    }
}
