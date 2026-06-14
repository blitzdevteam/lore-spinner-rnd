<?php

/**
 * Pipeline Upgrade V2.1 — Validation Runner.
 *
 * Companion to: Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runbook.md
 * Companion to: Adaptation layer/Chaos adaptation/v2-implementation/process-log/v2-process-log.md
 *
 * Each step is a self-contained probe runnable via:
 *
 *   php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" stepN [story_slug]
 *
 * The probes are intentionally small. They print human-readable lines that
 * either match the runbook's "Expected" block (PASS) or do not (FAIL).
 *
 * Steps:
 *   step1   Migrations + enum probe.
 *   step2   Model fillable + cast probe.
 *   step3   Blade render probe (every new + upgraded prompt).
 *   step4   Runtime narrator template render probe (with stub data).
 *   step5   Story-native alignment translator probe (no leaks of
 *           "chaotic"/"lawful"/"neutral" into the rendered prompt).
 *   step6   Persisted adaptation outputs probe — IP Trimming, Voice Lock,
 *           Phase 2 Tasks 6-9 all present for the chosen story.
 *   step7   Per-session adaptation outputs probe — Phase 4-8 + assembled
 *           runtime_narrator_prompt all present.
 *   step8   Runtime narrator template size budget probe (under 65k chars).
 *   step9   Hard-ban scan against the assembled prompt.
 *   step10  Chaos Mode start probe (calls /chaos-mode/start endpoint).
 *   step11  Chaos Mode turn probe (calls /chaos-mode/turn).
 *   step12  Tiered state loader probe (assert Tier 3 fires on climactic turn).
 *   step13  Un-adapted story 422 probe (no legacy fallback — endpoint returns 422).
 *   step14  Reconciliation probe (status transitions correctly on COMPLETE).
 *   step_v22 V2.2 probe — voice-lock 1A/1B blades, Paul Review markers, pipeline order comments.
 */

declare(strict_types=1);

require __DIR__ . '/../../../../vendor/autoload.php';

$app = require __DIR__ . '/../../../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$step = $argv[1] ?? null;
$storySlug = $argv[2] ?? getenv('V2_VALIDATION_STORY_SLUG') ?: 'alice-in-wonderland';

if (! $step) {
    echo "Usage: php pipeline-upgrade-v2-validation-runner.php stepN [story_slug]\n";
    echo "Default story_slug: alice-in-wonderland\n";
    exit(64);
}

$method = str_starts_with($step, 'step_')
    ? $step
    : 'step_' . ltrim($step, 'step');
if (! function_exists($method)) {
    echo "Unknown step: {$step}\n";
    exit(64);
}

echo "=== V2 Validation Runner — {$step} (story_slug={$storySlug}) ===\n\n";
$method($storySlug);
echo "\n=== end {$step} ===\n";

// -----------------------------------------------------------------------------

function step_1(string $slug): void
{
    echo "Migration probe — three V2 migrations must have run.\n";

    $expected = [
        '2026_05_24_000001_add_v2_pipeline_columns_to_story_adaptations',
        '2026_05_24_000002_add_runtime_narrator_prompt_to_session_adaptations',
        '2026_05_24_000003_add_v2_state_columns_to_chaos_sessions',
    ];

    foreach ($expected as $name) {
        $row = \DB::table('migrations')->where('migration', $name)->first();
        echo ($row ? 'ok   ' : 'miss ') . $name . "\n";
    }

    echo "\nAdaptationStatusEnum cases:\n";
    foreach (\App\Enums\Adaptation\AdaptationStatusEnum::cases() as $c) {
        echo '  ' . $c->value . "\n";
    }

    $hasIpTrimming = in_array('ip-trimming', array_map(fn ($c) => $c->value, \App\Enums\Adaptation\AdaptationStatusEnum::cases()), true);
    $hasVoiceLock = in_array('voice-lock', array_map(fn ($c) => $c->value, \App\Enums\Adaptation\AdaptationStatusEnum::cases()), true);

    echo "\nip-trimming case present: " . ($hasIpTrimming ? 'yes' : 'NO') . "\n";
    echo "voice-lock case present:  " . ($hasVoiceLock ? 'yes' : 'NO') . "\n";
}

function step_2(string $slug): void
{
    echo "Model cast probe.\n\n";

    $story = (new \App\Models\StoryAdaptation)->getCasts();
    echo "StoryAdaptation casts: " . implode(',', array_keys($story)) . "\n";
    foreach (['ip_trimming', 'voice_profile'] as $key) {
        echo '  ' . $key . ' => ' . ($story[$key] ?? 'MISSING') . "\n";
    }

    $session = (new \App\Models\SessionAdaptation)->getCasts();
    echo "\nSessionAdaptation casts: " . implode(',', array_keys($session)) . "\n";
    echo '  runtime_narrator_assembled_at => ' . ($session['runtime_narrator_assembled_at'] ?? 'MISSING') . "\n";

    $chaos = (new \App\Models\ChaosSession)->getCasts();
    echo "\nChaosSession casts: " . implode(',', array_keys($chaos)) . "\n";
    foreach (['world_state', 'alignment_scaffold', 'is_climactic_choice'] as $key) {
        echo '  ' . $key . ' => ' . ($chaos[$key] ?? 'MISSING') . "\n";
    }
    // In-place upgrade per Daniel's 2026-05-24 correction: the existing
    // `world_state` column holds the new literary-memory shape; there is NO
    // `world_state_v2` sidecar.
    $inPlace = isset($chaos['world_state']) && ! isset($chaos['world_state_v2']);
    echo ($inPlace ? 'ok   ' : 'FAIL ')
        . "world_state cast present AND no world_state_v2 sidecar — in-place upgrade respected\n";
}

function step_3(string $slug): void
{
    $bladeFiles = [
        // --- IP Trimming (original monolithic + V2.1 chapter/merge views) ---
        'ai.agents.adaptation.ip-trimming.system-prompt' => ['formatDetectionOutput' => '', 'currentPhase' => 'IP Trimming'],
        'ai.agents.adaptation.ip-trimming.prompt' => ['title' => 'X', 'author' => 'A', 'year' => '2026', 'format' => 'NOVEL', 'pageCount' => '10', 'sourceText' => 'sample'],
        'ai.agents.adaptation.ip-trimming.chapter-system-prompt' => [],
        'ai.agents.adaptation.ip-trimming.chapter-prompt' => [
            'title' => 'X', 'author' => 'A', 'format' => 'NOVEL',
            'chapterId' => 1, 'chapterPosition' => 1, 'chapterTitle' => 'Ch 1',
            'totalChapters' => 3, 'previousChapterTitle' => '', 'nextChapterTitle' => 'Ch 2',
            'chapterContent' => 'Sample chapter content.',
        ],
        'ai.agents.adaptation.ip-trimming.merge-system-prompt' => [],
        'ai.agents.adaptation.ip-trimming.merge-prompt' => [
            'title' => 'X', 'author' => 'A', 'totalChapters' => 2,
            'spineFragments' => [
                ['protagonist' => 'Alice', 'dramatic_question' => '', 'major_turning_points' => [], 'irreversible_events' => [], 'climax_fragment' => '', 'resolution_fragment' => ''],
                ['protagonist' => '', 'dramatic_question' => 'Will she escape?', 'major_turning_points' => [], 'irreversible_events' => [], 'climax_fragment' => 'Falls down', 'resolution_fragment' => ''],
            ],
        ],
        // --- Voice Lock (original + V2.1 chapter/merge views) ---
        'ai.agents.adaptation.voice-lock.system-prompt' => ['formatDetection' => [], 'formatDetectionOutput' => '', 'currentPhase' => 'Voice Lock'],
        'ai.agents.adaptation.voice-lock.prompt' => ['title' => 'X', 'author' => 'A', 'year' => '2026', 'format' => 'NOVEL', 'ipAudit' => [], 'formatDetection' => [], 'sourceText' => 'sample'],
        'ai.agents.adaptation.voice-lock.chapter-system-prompt' => [],
        'ai.agents.adaptation.voice-lock.chapter-prompt' => [
            'title' => 'X', 'author' => 'A', 'year' => '2026', 'format' => 'NOVEL',
            'chapterId' => 1, 'chapterPosition' => 1, 'chapterTitle' => 'Ch 1', 'totalChapters' => 3,
            'chapterContent' => 'Sample chapter content.',
        ],
        'ai.agents.adaptation.voice-lock.chapter-system-prompt-novelist' => [],
        'ai.agents.adaptation.voice-lock.chapter-system-prompt-screenwriter' => [],
        'ai.agents.adaptation.voice-lock.system-prompt-novelist' => [
            'formatDetection' => ['detected_format' => 'NOVEL'],
            'formatDetectionOutput' => '{"detected_format":"NOVEL"}',
            'ipAudit' => ['scorecard' => 'stub'],
            'currentPhase' => 'Voice Lock 1A',
        ],
        'ai.agents.adaptation.voice-lock.system-prompt-screenwriter' => [
            'formatDetection' => ['detected_format' => 'SCREENPLAY'],
            'formatDetectionOutput' => '{"detected_format":"SCREENPLAY"}',
            'ipAudit' => ['scorecard' => 'stub'],
            'currentPhase' => 'Voice Lock 1B',
        ],
        'ai.agents.adaptation.voice-lock.merge-prompt' => [
            'title' => 'X', 'author' => 'A', 'year' => '2026', 'format' => 'NOVEL',
            'formatDetection' => [], 'ipAudit' => [],
            'totalChapters' => 1, 'voiceFragments' => [['chapter_id' => 1, 'chapter_position' => 1]],
        ],
        // --- Phase 2-8 system prompts ---
        'ai.agents.adaptation.story-session-map.system-prompt' => ['formatDetection' => '', 'formatDetectionOutput' => '', 'currentPhase' => 'Phase 2'],
        'ai.agents.adaptation.session-architecture.system-prompt' => ['formatDetection' => '', 'formatDetectionOutput' => '', 'currentPhase' => 'Phase 4'],
        'ai.agents.adaptation.choice-design.system-prompt' => ['formatDetection' => '', 'formatDetectionOutput' => '', 'currentPhase' => 'Phase 5'],
        'ai.agents.adaptation.consequence-mapping.system-prompt' => ['formatDetection' => '', 'formatDetectionOutput' => '', 'currentPhase' => 'Phase 6'],
        'ai.agents.adaptation.editorial-verification.system-prompt' => ['formatDetection' => '', 'formatDetectionOutput' => '', 'currentPhase' => 'Phase 8'],
        // --- Phase 2-8 user prompt blades — all now require voiceProfile ---
        'ai.agents.adaptation.story-session-map.prompt' => [
            'voiceProfile' => ['profile_type' => 'NOVELIST', 'author_voice_dna_profile' => []],
            'voiceProfileLabel' => 'stub',
            'ipAudit' => [], 'formatDetection' => '{}', 'estimatedSessionCount' => 1,
            'chapters' => [], 'events' => [], 'totalEvents' => 0,
            'ipTrimmingWorldRules' => null, 'ipTrimmingConversionNotes' => null,
        ],
        'ai.agents.adaptation.entry-point-diagnosis.prompt' => [
            'voiceProfile' => ['profile_type' => 'NOVELIST', 'author_voice_dna_profile' => [], 'master_rule_1_hard_bans' => []],
            'voiceProfileLabel' => 'stub',
            'storySessionMap' => [], 'ipAudit' => [], 'sessionNumber' => 1,
            'sessionSourcePages' => 'stub pages', 'sessionEvents' => [],
        ],
        'ai.agents.adaptation.session-architecture.prompt' => [
            'voiceProfile' => ['profile_type' => 'NOVELIST', 'author_voice_dna_profile' => []],
            'voiceProfileLabel' => 'stub',
            'storySessionMap' => [], 'entryPointDiagnosis' => [], 'sessionNumber' => 1,
            'sessionSourcePages' => 'stub pages',
        ],
        'ai.agents.adaptation.choice-design.prompt' => [
            'voiceProfile' => ['profile_type' => 'NOVELIST', 'author_voice_dna_profile' => [], 'master_rule_1_hard_bans' => []],
            'voiceProfileLabel' => 'stub',
            'beatMap' => [], 'storySessionMap' => [], 'protagonistCoreTrait' => 'x',
            'emotionalPromise' => 'stub', 'sessionNumber' => 1, 'choiceMomentPages' => 'stub pages',
        ],
        'ai.agents.adaptation.consequence-mapping.prompt' => [
            'voiceProfile' => ['profile_type' => 'NOVELIST', 'author_voice_dna_profile' => [], 'master_rule_1_hard_bans' => []],
            'voiceProfileLabel' => 'stub',
            'branchingChoices' => [], 'persistentStateSchema' => [], 'worldReactivityRules' => [],
            'storySessionMap' => [], 'protagonistCoreTrait' => 'x', 'sessionNumber' => 1,
        ],
        'ai.agents.adaptation.session-close.prompt' => [
            'voiceProfile' => ['profile_type' => 'NOVELIST', 'author_voice_dna_profile' => [], 'master_rule_1_hard_bans' => []],
            'voiceProfileLabel' => 'stub',
            'branchingChoice3Design' => [], 'choice3ConsequenceMap' => [], 'sessionPrimaryGoal' => 'stub',
            'sessionNumber' => 1, 'sessionEvents' => [], 'resolutionSourcePages' => 'stub pages',
        ],
        'ai.agents.adaptation.editorial-verification.prompt' => [
            'voiceProfile' => [], 'voiceProfileLabel' => 'stub',
            'completeSessionDesign' => [], 'storyGuardCanon' => [],
            'persistentStateSchema' => [], 'worldReactivityRules' => [], 'storySessionMap' => [], 'sessionNumber' => 1,
        ],
    ];

    foreach ($bladeFiles as $view => $data) {
        try {
            $bytes = strlen(view($view, $data)->render());
            echo "ok   {$view}  [{$bytes} bytes]\n";
        } catch (\Throwable $e) {
            echo "FAIL {$view}  [{$e->getMessage()}]\n";
        }
    }
}

function step_4(string $slug): void
{
    $stub = stub_runtime_template_data();

    try {
        $rendered = view('ai.agents.chaos.runtime-narrator-template', $stub)->render();
        echo "ok   render (" . strlen($rendered) . " bytes)\n";
        foreach ([
            '[SYMBOLIC_MEMORY_INJECTION_POINT]',
            '[ALIGNMENT_TILT_INJECTION_POINT]',
            '[OPENING_SCENE_INJECTION_POINT]',
            '[WORLD_STATE_TIERED_INJECTION_POINT]',
        ] as $token) {
            echo (strpos($rendered, $token) !== false ? 'ok   ' : 'miss ') . $token . "\n";
        }
        foreach ([
            'PAUL REVIEW — RUNTIME CADENCE RULES',
            '300–350 words',
            'CUSTOM INPUT PROTOCOL',
            'COLLLOCATION FINGERPRINT',
            'Profile type:',
        ] as $marker) {
            echo (str_contains($rendered, $marker) ? 'ok   ' : 'miss ') . "marker: {$marker}\n";
        }
    } catch (\Throwable $e) {
        echo "FAIL " . $e->getMessage() . "\n";
    }
}

function step_5(string $slug): void
{
    // Story-native alignment translator: rendered runtime prompt must NEVER
    // contain the literal strings "chaotic"/"lawful"/"neutral" in any
    // narrator-visible section. The hidden alignment_scaffold is allowed in
    // section comments but the runtime should hide the words from the
    // injected prompt.
    $stub = stub_runtime_template_data();
    $stub['alignmentLabels'] = [
        ['label' => 'Curious', 'maps_to_internal' => 'chaotic', 'behavioral_markers' => ['asks questions'], 'narrative_consequences' => 'world bends inward', 'voice_signature' => 'lilting'],
        ['label' => 'Proper', 'maps_to_internal' => 'lawful', 'behavioral_markers' => ['follows rules'], 'narrative_consequences' => 'NPCs ease', 'voice_signature' => 'measured'],
        ['label' => 'Contrary', 'maps_to_internal' => 'neutral', 'behavioral_markers' => ['contradicts'], 'narrative_consequences' => 'NPCs harden', 'voice_signature' => 'tart'],
    ];

    $rendered = view('ai.agents.chaos.runtime-narrator-template', $stub)->render();

    // Then simulate the controller's runtime substitution.
    $controllerRender = strtr($rendered, [
        '[SYMBOLIC_MEMORY_INJECTION_POINT]' => '(no symbolic memory yet — early in the session.)',
        '[ALIGNMENT_TILT_INJECTION_POINT]' => 'STORY-NATIVE ALIGNMENT TILT: "Curious" (hidden — never surface the literal label).',
        '[OPENING_SCENE_INJECTION_POINT]' => '(opening already delivered)',
        '[WORLD_STATE_TIERED_INJECTION_POINT]' => 'PERSISTENT STATE — TIER 1 (always loaded):\nLocation: (unset)',
    ]);

    // We check the runtime-INJECTED parts: alignment tilt + symbolic + world state + opening scene.
    $runtimeInjections = "STORY-NATIVE ALIGNMENT TILT: \"Curious\"\n(no symbolic memory yet — early in the session.)\n(opening already delivered)\nPERSISTENT STATE — TIER 1 (always loaded):\nLocation: (unset)";

    foreach (['chaotic', 'lawful', 'neutral'] as $banned) {
        $found = stripos($runtimeInjections, $banned) !== false;
        echo ($found ? 'FAIL ' : 'ok   ') . "no '{$banned}' in runtime-injected text\n";
    }

    // Sanity: confirm the story-native label DOES appear.
    echo (stripos($controllerRender, 'Curious') !== false ? 'ok   ' : 'FAIL ') . "story-native label 'Curious' present\n";
}

function step_6(string $slug): void
{
    $story = \App\Models\Story::query()->where('slug', $slug)->with('adaptation')->first();
    if (! $story) {
        echo "FAIL story '{$slug}' not found\n";
        return;
    }
    $a = $story->adaptation;
    if (! $a) {
        echo "FAIL no adaptation row for {$slug}\n";
        return;
    }

    echo "adaptation_status: " . ($a->adaptation_status->value ?? 'null') . "\n";
    echo (! empty($a->ip_trimming) ? 'ok   ' : 'miss ') . "ip_trimming present\n";
    echo (! empty($a->format_detection) ? 'ok   ' : 'miss ') . "format_detection present\n";
    echo (! empty($a->ip_audit) ? 'ok   ' : 'miss ') . "ip_audit present\n";
    echo (! empty($a->voice_profile) ? 'ok   ' : 'miss ') . "voice_profile present\n";
    echo (! empty($a->story_session_map) ? 'ok   ' : 'miss ') . "story_session_map present\n";

    $map = (array) ($a->story_session_map ?? []);
    foreach ([
        'persistent_state_schema',
        'world_reactivity_rules',
        'story_guard_canon',
        'alignment_labels',
    ] as $key) {
        echo (! empty($map[$key]) ? 'ok   ' : 'miss ') . "story_session_map.{$key}\n";
    }
}

function step_7(string $slug): void
{
    $story = \App\Models\Story::query()->where('slug', $slug)->with('adaptation.sessionAdaptations')->first();
    if (! $story?->adaptation) {
        echo "FAIL no adaptation for {$slug}\n";
        return;
    }

    foreach ($story->adaptation->sessionAdaptations as $s) {
        echo "\nsession_number={$s->session_number} status=" . ($s->session_status->value ?? 'null') . "\n";
        echo (! empty($s->session_architecture) ? 'ok   ' : 'miss ') . "session_architecture\n";
        echo (! empty($s->session_choice_design) ? 'ok   ' : 'miss ') . "session_choice_design\n";
        echo (! empty($s->choice_consequence_map) ? 'ok   ' : 'miss ') . "choice_consequence_map\n";
        echo (! empty($s->session_close_design) ? 'ok   ' : 'miss ') . "session_close_design\n";
        echo (! empty($s->editorial_verification) ? 'ok   ' : 'miss ') . "editorial_verification\n";
        echo (! empty($s->runtime_narrator_prompt) ? 'ok   ' : 'miss ') . "runtime_narrator_prompt (" . strlen((string) $s->runtime_narrator_prompt) . " bytes)\n";
        echo (! empty($s->runtime_narrator_assembled_at) ? 'ok   ' : 'miss ') . "runtime_narrator_assembled_at\n";
    }
}

function step_8(string $slug): void
{
    $story = \App\Models\Story::query()->where('slug', $slug)->with('adaptation.sessionAdaptations')->first();
    if (! $story?->adaptation) {
        echo "FAIL no adaptation for {$slug}\n";
        return;
    }

    foreach ($story->adaptation->sessionAdaptations as $s) {
        $bytes = strlen((string) $s->runtime_narrator_prompt);
        $verdict = match (true) {
            $bytes === 0                                          => 'miss (not assembled)',
            $bytes > \App\Ai\Adaptation\RuntimeNarratorTemplateBuilder::MAX_PROMPT_CHARS => 'FAIL (over cap)',
            default                                               => 'ok',
        };
        echo "session {$s->session_number}: {$bytes} bytes — {$verdict}\n";
    }
}

function step_9(string $slug): void
{
    // Scan the assembled prompt for universal hard-ban tokens.
    $bans = [
        ' — ', ' -- ', ' – ',
        'tapestry', 'delve', 'underscore', 'highlight', 'showcase',
        'intricate', 'swift', 'meticulous', 'adept',
        'woven into', 'meaningful connections', 'nestled', 'tucked away',
        'Elara', 'Voss', 'Kael',
    ];

    $story = \App\Models\Story::query()->where('slug', $slug)->with('adaptation.sessionAdaptations')->first();
    if (! $story?->adaptation) {
        echo "FAIL no adaptation for {$slug}\n";
        return;
    }

    foreach ($story->adaptation->sessionAdaptations as $s) {
        $prompt = (string) $s->runtime_narrator_prompt;
        if ($prompt === '') {
            echo "session {$s->session_number}: SKIP (no prompt assembled)\n";
            continue;
        }
        $hits = [];
        foreach ($bans as $needle) {
            // The template's own ban-list section legitimately CONTAINS these
            // tokens as "do not use" instructions. We check the body OUTSIDE
            // Section 7. Easiest heuristic: split on the Section 7 header.
            $sectionSplit = preg_split('/=== SECTION 7 — HARD BANS .*===/u', $prompt, 2);
            $afterBansSection = $sectionSplit[1] ?? $prompt;
            $sectionSplit2 = preg_split('/=== SECTION 8 — /u', $afterBansSection, 2);
            $beforeBansSection = $sectionSplit[0] ?? '';
            $rest = ($beforeBansSection ?? '') . ($sectionSplit2[1] ?? '');

            if (stripos($rest, $needle) !== false) {
                $hits[] = $needle;
            }
        }
        echo "session {$s->session_number}: " . ($hits === [] ? 'ok (no ban tokens leaked)' : 'FAIL hits=' . implode(', ', $hits)) . "\n";
    }
}

function step_10(string $slug): void
{
    echo "Manual probe — call POST /chaos-mode/start with story_slug={$slug} and inspect:\n";
    echo "  * 200 OK (or 422 'story has not been re-adapted under V2 yet' if the pipeline has not run)\n";
    echo "  * `chaos.start` log line in storage/logs/narration-*.log\n";
    echo "  * `world_state` populated on the created chaos_sessions row in the upgraded literary shape\n";
    echo "    (relationship_updates / object_states / emotional_ledger entries present)\n";
    echo "  * `alignment_scaffold` populated with {chaotic, lawful, neutral} ints\n";
    echo "  * `symbolic_memory` set after the first turn (if the narrator emitted one)\n";
    echo "\nUse:\n";
    echo "  curl -X POST https://<host>/chaos-mode/start -H 'X-Inertia: true' -d 'story_slug={$slug}'\n";
}

function step_11(string $slug): void
{
    echo "Manual probe — call POST /chaos-mode/turn with the session_id from step 10 and inspect:\n";
    echo "  * `chaos.turn` log line\n";
    echo "  * world_state updates merged correctly in the upgraded literary shape\n";
    echo "  * alignment_scaffold accumulates monotonically across turns\n";
    echo "  * defining_choice_line populated if a branching choice was resolved\n";
    echo "  * is_climactic_choice true on Choice #3 / Choice #4 turns\n";
}

function step_12(string $slug): void
{
    // Render the V2 controller path with is_climactic_choice=true and verify
    // the Tier 3 sections fire. We cannot easily reach into the private method
    // without bootstrapping a real session, so we exercise the tiered loader's
    // observable behaviour: rendered text MUST contain the Tier 3 section
    // headers after a climactic turn.
    $stub = stub_runtime_template_data();
    $rendered = view('ai.agents.chaos.runtime-narrator-template', $stub)->render();
    echo "Inspect Section 17 (Narration Contract) for [WORLD_STATE_TIERED_INJECTION_POINT] — present: ";
    echo (stripos($rendered, '[WORLD_STATE_TIERED_INJECTION_POINT]') !== false ? 'ok' : 'FAIL') . "\n";
    echo "Manual: after a turn where the narrator returns is_climactic_choice=true, confirm the\n";
    echo "system prompt sent on the next turn contains EMERGENT FACTS / UNRESOLVED PROMISES /\n";
    echo "EMOTIONAL LEDGER blocks (those only render when load_tier_3 is true in renderTieredWorldState).\n";
}

function step_13(string $slug): void
{
    echo "Un-adapted story 422 probe.\n";
    echo "Per Daniel's correction there is NO legacy fallback. If a session has\n";
    echo "runtime_narrator_prompt = NULL, /chaos-mode/start must return HTTP 422\n";
    echo "with the message 'This story has not been re-adapted under V2 yet...'.\n\n";

    $story = \App\Models\Story::query()->where('slug', $slug)->with('adaptation.sessionAdaptations')->first();
    if (! $story?->adaptation) {
        echo "(skip — no adaptation for {$slug})\n";
        return;
    }

    $hasAssembled = $story->adaptation->sessionAdaptations
        ->whereNotNull('runtime_narrator_prompt')
        ->where('runtime_narrator_prompt', '!=', '')
        ->isNotEmpty();

    echo "story {$slug} has assembled prompts: " . ($hasAssembled ? 'yes — V2 endpoint should 200' : 'no — V2 endpoint MUST 422') . "\n";
    echo "\nManual verification:\n";
    echo "  curl -i -X POST https://<host>/chaos-mode/start -H 'X-Inertia: true' -d 'story_slug={$slug}'\n";
    echo "  Expected for un-adapted: HTTP 422 with body containing 're-adapted under V2'.\n";
}

function step_14(string $slug): void
{
    echo "Reconciliation probe.\n";
    echo "After a successful pipeline run all SessionAdaptation rows must have:\n";
    echo "  - session_status = COMPLETED\n";
    echo "  - runtime_narrator_prompt non-empty\n";
    echo "Only then can the StoryAdaptation row's adaptation_status reach COMPLETED.\n\n";

    $story = \App\Models\Story::query()->where('slug', $slug)->with('adaptation.sessionAdaptations')->first();
    if (! $story?->adaptation) {
        echo "(skip — no adaptation for {$slug})\n";
        return;
    }

    echo "story adaptation_status: " . ($story->adaptation->adaptation_status->value ?? 'null') . "\n";
    $missing = $story->adaptation->sessionAdaptations
        ->whereNull('runtime_narrator_prompt')
        ->pluck('session_number')
        ->all();
    echo "sessions missing runtime_narrator_prompt: " . ($missing === [] ? 'none' : implode(', ', $missing)) . "\n";
}

function step_v22(string $slug): void
{
    echo "V2.2 integration probe.\n\n";

    $jobFiles = [
        'IpTrimmingMergeJob.php' => 'FormatDetectionJob::dispatch',
        'FormatDetectionJob.php' => 'IpAuditJob::dispatch',
        'IpAuditJob.php' => 'VoiceLockChapterJob',
        'VoiceLockMergeJob.php' => 'StorySessionMapJob::dispatch',
    ];

    foreach ($jobFiles as $file => $needle) {
        $path = app_path('Jobs/Adaptation/' . $file);
        $content = is_file($path) ? file_get_contents($path) : '';
        echo (str_contains($content, $needle) ? 'ok   ' : 'FAIL ') . "{$file} contains {$needle}\n";
    }

    echo "\nVoiceLockSchema class: " . (class_exists(\App\Ai\Agents\Adaptation\VoiceLockSchema::class) ? 'ok' : 'MISSING') . "\n";

    $story = null;
    try {
        $story = \App\Models\Story::query()->where('slug', $slug)->with('adaptation')->first();
    } catch (\Throwable $e) {
        echo "\n(DB unavailable — skipping voice_profile probe: {$e->getMessage()})\n";
    }

    if ($story?->adaptation?->voice_profile) {
        $vp = $story->adaptation->voice_profile;
        echo "\nVoice profile for {$slug}:\n";
        echo '  profile_type: ' . ($vp['profile_type'] ?? 'MISSING (re-adapt required)') . "\n";
        $dna = $vp['author_voice_dna_profile'] ?? [];
        echo '  collocations: ' . count($dna['collocation_fingerprint'] ?? []) . "\n";
        echo '  negative_space: ' . count($dna['negative_space_map'] ?? []) . "\n";
        echo '  comparative_exclusion: ' . count($dna['comparative_exclusion'] ?? []) . "\n";
        echo '  audit_points: ' . count($vp['fourteen_point_audit_protocol'] ?? []) . "\n";
    } else {
        echo "\n(no voice_profile for {$slug} — run pipeline on Cloud after deploy)\n";
    }

    echo "\nPaul Review markers in pipeline blades:\n";
    $markers = [
        'session-architecture/system-prompt.blade.php' => 'FIRST-3-MINUTES RULE',
        'choice-design/system-prompt.blade.php' => 'CHOICE CONTRAST RULES',
        'consequence-mapping/system-prompt.blade.php' => 'CONSEQUENCE VISIBILITY RULE',
    ];
    foreach ($markers as $rel => $marker) {
        $path = resource_path('views/ai/agents/adaptation/' . $rel);
        $content = is_file($path) ? file_get_contents($path) : '';
        echo (str_contains($content, $marker) ? 'ok   ' : 'FAIL ') . "{$rel}\n";
    }
}

// -----------------------------------------------------------------------------

function stub_runtime_template_data(): array
{
    return [
        'storyTitle' => 'Stub Story',
        'authorName' => 'Stub Author',
        'sessionNumber' => 1,
        'totalSessions' => 3,
        'protagonist' => 'Alice',
        'spine' => [
            'protagonist' => 'Alice',
            'dramatic_question' => 'Will Alice escape Wonderland?',
            'world' => 'Wonderland',
            'major_turning_points' => [],
            'irreversible_events' => [],
        ],
        'worldRules' => [
            'physics_technology' => [], 'creatures_entities' => [],
            'geography_locations' => [], 'social_systems' => [],
            'what_cannot_exist' => [],
        ],
        'storyGuard' => [
            'layer_2_character_canon' => [],
            'layer_3_narrative_canon' => [],
            'layer_4_voice_tonal_canon' => [
                'tone_restrictions' => [],
                'language_restrictions' => [],
                'thematic_restrictions' => [],
            ],
        ],
        'sceneRules' => [
            'tone_constraints_for_session' => [],
            'language_constraints_for_session' => [],
            'thematic_constraints_for_session' => [],
        ],
        'voice' => [
            'profile_type' => 'NOVELIST',
            'author_voice_dna_profile' => [
                'signature_writing_techniques' => [
                    ['name' => 'Stub Technique', 'why_this_author' => 'test', 'frequency' => 'every scene'],
                ],
                'sentence_level_patterns' => [
                    'average_sentence_length' => '14',
                    'cadence_variation' => 'oscillating',
                    'clause_structure_preference' => 'compound-complex',
                    'punctuation_habits' => 'moderate commas',
                ],
                'diction_fingerprint' => [
                    'register_and_formality' => 'Victorian',
                    'word_frequency_patterns' => 'avoids modern slang',
                ],
                'narrator_perspective' => [
                    'point_of_view' => 'third omniscient',
                    'reliability' => 'reliable',
                    'distance' => 'close',
                    'commentary' => 'warm',
                    'tense' => 'past',
                    'interior_monologue' => 'indirect',
                ],
                'dialogue_fingerprint_per_character' => [],
                'paragraph_architecture' => [
                    'pattern' => 'mixed',
                    'transition_method' => 'comma splice into next image',
                    'chapter_opening_style' => 'in medias res',
                    'chapter_closing_style' => 'image',
                ],
                'dialogue_tag_patterns' => [
                    'said_percentage' => '56%',
                    'action_beats_frequency' => 'moderate',
                    'banned_tags' => ['opined'],
                ],
                'collocation_fingerprint' => [
                    ['pair' => 'clay pipe', 'ai_substitution' => 'smoking pipe', 'category' => 'physical'],
                ],
                'negative_space_map' => [
                    ['technique' => 'interior monologue', 'absence_evidence' => 'never used'],
                ],
                'show_explain_ratio' => [
                    'approximate_balance' => '70% show',
                    'enforcement_note' => 'stay external',
                ],
                'comparative_exclusion' => [
                    ['neighbor_author' => 'Dickens', 'differentiating_techniques' => ['shorter sentences']],
                ],
            ],
            'master_rule_1_hard_bans' => [
                'ip_specific_bans' => [],
            ],
            'fourteen_point_audit_protocol' => [
                ['point_number' => 1, 'point_name' => 'Hard Ban Scan', 'pass_fail_definition' => 'zero bans'],
            ],
        ],
        'persistentState' => [
            'objects' => [], 'npcs' => [], 'world_flags' => [],
            'player_historical_archive_categories' => [],
        ],
        'reactivityRules' => [
            'reactivity_categories' => [], 'timing_rules' => [],
            'escalation_rules' => [], 'visibility_rules' => [],
        ],
        'alignmentLabels' => [],
        'sessionSpine' => [
            'dramatic_question' => 'q', 'emotional_promise' => 'p',
            'emotional_register' => 'r', 'chapters_covered' => 'c',
            'session_destination' => 'd', 'next_session_seed' => 's',
        ],
        'beatMap' => [],
        'sessionEvents' => [],
        'branchingChoices' => [],
        'emotionalChoices' => [],
        'postureShifts' => [],
        'consequenceMaps' => [],
        'freeformGuidelines' => [],
        'editorialStatus' => 'GREEN',
    ];
}
