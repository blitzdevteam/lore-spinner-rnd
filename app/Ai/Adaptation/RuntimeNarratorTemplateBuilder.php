<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

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
 * Bounded to 65,000 characters per Deliverable 8. Implements the compression
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
     * Hard cap from Deliverable 8.
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

        return View::make('ai.agents.chaos.runtime-narrator-template', [
            'storyTitle' => $story->title,
            'authorName' => $story->creator?->name ?? 'the author',
            'sessionNumber' => $session->session_number,
            'totalSessions' => $totalSessions,
            'protagonist' => $this->protagonistName($adaptation),

            'spine' => $this->normalizeSpine($trimming),
            'worldRules' => (array) ($trimming['world_rules'] ?? []),
            'storyGuard' => (array) ($sessionMap['story_guard_canon'] ?? []),
            'sceneRules' => (array) ($choiceDesign['scene_rules_layer_4'] ?? []),
            'voice' => $voiceProfile,
            'persistentState' => (array) ($sessionMap['persistent_state_schema'] ?? []),
            'reactivityRules' => (array) ($sessionMap['world_reactivity_rules'] ?? []),
            'alignmentLabels' => (array) ($sessionMap['alignment_labels'] ?? []),

            'sessionSpine' => [
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
            ],
            'beatMap' => (array) ($architecture['beat_map'] ?? []),
            'sessionEvents' => $sessionEvents,

            'branchingChoices' => (array) ($choiceDesign['branching_choices'] ?? []),
            'emotionalChoices' => (array) ($choiceDesign['emotional_choices'] ?? []),
            'postureShifts' => (array) ($choiceDesign['posture_shifts'] ?? []),

            'consequenceMaps' => (array) ($consequenceMap['branching_consequences'] ?? []),
            'freeformGuidelines' => (array) ($consequenceMap['freeform_guidelines'] ?? []),

            'editorialStatus' => (string) (($session->editorial_verification['production_status'] ?? 'UNVERIFIED')),
        ])->render();
    }

    /**
     * @param  array<string, mixed>  $adaptation
     */
    private function protagonistName($adaptation): string
    {
        return (string) (
            $adaptation->ip_trimming['story_spine']['protagonist']
            ?? $adaptation->voice_profile['author_voice_dna_profile']['dialogue_fingerprint_per_character'][0]['character']
            ?? 'the protagonist'
        );
    }

    /**
     * @param  array<string, mixed>  $trimming
     * @return array<string, mixed>
     */
    private function normalizeSpine(array $trimming): array
    {
        $spine = (array) ($trimming['story_spine'] ?? []);

        return [
            'protagonist' => (string) ($spine['protagonist'] ?? 'the protagonist'),
            'dramatic_question' => (string) ($spine['dramatic_question'] ?? '(not specified)'),
            'world' => (string) ($spine['world'] ?? '(not specified)'),
            'major_turning_points' => (array) ($spine['major_turning_points'] ?? []),
            'irreversible_events' => (array) ($spine['irreversible_events'] ?? []),
        ];
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
            ->get(['events.position', 'events.title', 'events.content', 'events.objectives'])
            ->map(fn (Event $e) => [
                'position' => (int) $e->position,
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
            'position' => $e['position'],
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
            'position' => $e['position'],
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

        $voice['author_voice_dna_profile'] = $dna;
        return $voice;
    }
}
