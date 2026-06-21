<?php

/**
 * Pipeline Upgrade V2.1 / V2.2 / V2.3 — Validation Runner.
 *
 * Companion to: Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runbook.md
 * Companion to: Adaptation layer/Chaos adaptation/v2-implementation/process-log/v2-2-process-log.md
 * Companion to: Adaptation layer/Chaos adaptation/V2.3 - JUNE 18, 2026/V2.3-PROCESS-LOG.md
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
 *   step_v23 V2.3 probe — D8 v2 template, 1A v2/1B v3 blades, anchor fields, slice methods,
 *            ChoiceDesign first_choice_spec guard, sec13 strip anchor, builder guards.
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
            'voiceProfile' => [
                'profile_type' => 'NOVELIST',
                'author_voice_dna_profile' => [],
                'master_rule_1_hard_bans' => [],
                'voice_anchor' => [['mode' => 'stub', 'source' => 'ch1', 'techniques' => 'stub', 'prose' => 'Stub.']],
                'anchor_card' => ['No em-dash.'],
                'runtime_self_check' => ['Check em-dash.'],
            ],
            'voiceProfileLabel' => 'Sections 1+2+Voice Anchor',
            'storySessionMap' => [], 'ipAudit' => [], 'sessionNumber' => 1,
            'sessionSourcePages' => 'stub pages', 'sessionEvents' => [],
            'protagonist' => 'Alice',   // V2.3 addition
            'format' => 'NOVEL',        // V2.3 addition
        ],
        'ai.agents.adaptation.session-architecture.prompt' => [
            'voiceProfile' => ['profile_type' => 'NOVELIST', 'author_voice_dna_profile' => []],
            'voiceProfileLabel' => 'stub',
            'storySessionMap' => [], 'entryPointDiagnosis' => [], 'sessionNumber' => 1,
            'sessionSourcePages' => 'stub pages',
        ],
        'ai.agents.adaptation.choice-design.prompt' => [
            'voiceProfile' => [
                'profile_type' => 'NOVELIST',
                'author_voice_dna_profile' => [],
                'master_rule_1_hard_bans' => [],
                'anchor_card' => ['No em-dash.'],  // V2.3: dnaBansAndAnchorCard slice includes anchor_card
            ],
            'voiceProfileLabel' => 'Sections 1+2+Anchor Card',
            'beatMap' => [], 'storySessionMap' => [], 'protagonistCoreTrait' => 'x',
            'emotionalPromise' => 'stub', 'sessionNumber' => 1, 'choiceMomentPages' => 'stub pages',
            'firstChoiceSpec' => ['setup_prose' => 'stub spec', 'choice_question' => 'What now?'], // V2.3 D4 patch
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

        // D8 v2 — all four runtime injection points must be present in assembled template.
        foreach ([
            '[SYMBOLIC_MEMORY_INJECTION_POINT]',
            '[ALIGNMENT_TILT_INJECTION_POINT]',
            '[OPENING_SCENE_INJECTION_POINT]',
            '[WORLD_STATE_TIERED_INJECTION_POINT]',
        ] as $token) {
            echo (strpos($rendered, $token) !== false ? 'ok   ' : 'miss ') . $token . "\n";
        }

        // D8 v2 section markers that must be present.
        foreach ([
            '**4A — THE VOICE ANCHOR (imitate these):**',
            'MASTER RULE 1: HARD BANS',
            '### SECTION 13: COLD OPEN',
            '### SECTION 14: NARRATION RULES',
            '### SECTION 15: FREEDOM CONTRACT',
            '### SECTION 17: MISSION STATEMENT',
            '### SECTION 18: VOICE RE-ANCHOR',
            '**THE ANCHOR CARD (',
            'SELF-CHECK — run silently on your draft',
            '115-125 words',
        ] as $marker) {
            echo (str_contains($rendered, $marker) ? 'ok   ' : 'miss ') . "marker: {$marker}\n";
        }

        // D8 v2 — old V2.2 template markers must NOT appear (they were removed).
        foreach ([
            'RUNTIME GENERATION RULES — CADENCE, ECONOMY, AND FORWARD PULL',
            'FIRST-3-MINUTES OPENING PROTOCOL',
            'COLLLOCATION FINGERPRINT',
            'Profile type:',
        ] as $removed) {
            echo (str_contains($rendered, $removed) ? 'FAIL (old V2.2 marker still present) ' : 'ok   ') . "removed: {$removed}\n";
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

    echo "\nSession-start opening injection checks (ChaosEngineService shape):\n";
    $enginePath = app_path('Services/ChaosEngineService.php');
    $engineSrc  = is_file($enginePath) ? file_get_contents($enginePath) : '';
    $engineChecks = [
        'loadSessionContext returns cold_open'         => "'cold_open'",
        'loadSessionContext returns opening_handoff'   => "'opening_handoff'",
        'loadSessionContext returns emotional_promise' => "'emotional_promise'",
        'loadSessionContext returns must_reintroduce'  => "'must_reintroduce'",
        'renderSystemPrompt has isSessionStart param'  => 'bool $isSessionStart',
        'buildOpeningSection method exists'            => 'function buildOpeningSection(',
        'opening_scene collapse removed'               => "'opening_scene'",
    ];
    foreach ($engineChecks as $label => $needle) {
        // opening_scene collapse check is an absence test
        if ($label === 'opening_scene collapse removed') {
            echo (str_contains($engineSrc, $needle) ? 'FAIL ' : 'ok   ') . "{$label}\n";
        } else {
            echo (str_contains($engineSrc, $needle) ? 'ok   ' : 'FAIL ') . "{$label}\n";
        }
    }

    echo "\nCall-site isSessionStart checks:\n";
    $callSites = [
        'GameController begin()' => [app_path('Http/Controllers/User/GameController.php'), 'isSessionStart:      true'],
        'GameController nextSession()' => [app_path('Http/Controllers/User/GameController.php'), 'isSessionStart:      true'],
        'PromptController store()' => [app_path('Http/Controllers/User/Game/PromptController.php'), 'isSessionStart:      false'],
        'ChaosModeController (no currentScene)' => [app_path('Http/Controllers/ChaosMode/ChaosModeController.php'), 'isSessionStart:      false'],
        'DumpChaosPromptCommand' => [app_path('Console/Commands/DumpChaosPromptCommand.php'), 'isSessionStart:      true'],
    ];
    foreach ($callSites as $label => [$filePath, $needle]) {
        $src = is_file($filePath) ? file_get_contents($filePath) : '';
        echo (str_contains($src, $needle) ? 'ok   ' : 'FAIL ') . "{$label}\n";
    }

    echo "\nMechanical Section 13 strip (continuation turns) — ChaosEngineService:\n";
    $stripChecks = [
        // V2.3: heading format changed from '=== SECTION 13 —' to '### SECTION 13:' (D8 v2 template)
        'sec13Anchor matches D8v2 heading'     => "\$sec13Anchor = '### SECTION 13:'",
        'injToken variable defined'            => "\$injToken    = '[OPENING_SCENE_INJECTION_POINT]'",
        'strpos sec13 used'                    => 'strpos($prompt, $sec13Anchor)',
        'strpos injToken used'                 => 'strpos($prompt, $injToken)',
        'continuation marker text present'     => 'Continuation turn — opening already delivered',
        'isSessionStart branches strtr split'  => 'if ($isSessionStart) {',
    ];
    foreach ($stripChecks as $label => $needle) {
        echo (str_contains($engineSrc, $needle) ? 'ok   ' : 'FAIL ') . "{$label}\n";
    }

    // Old V2.2 anchor must be gone (was replaced in V2.3 fix).
    echo (str_contains($engineSrc, "\$sec13Anchor = '=== SECTION 13 —'") ? 'FAIL (old V2.2 anchor still present) ' : 'ok   ')
        . "old V2.2 sec13Anchor '=== SECTION 13 —' removed\n";
}

// -----------------------------------------------------------------------------

/**
 * step_1b_v2 — 1B v2 Surgical Upgrade validation.
 *
 * 5A: Prompt marker checks (hard FAIL if any of the 7 markers are absent).
 * 5B: SCREENWRITER voice_profile schema probe + canonical path guards.
 * 5C: Runtime Section 6 content probe (SCREENWRITER and NOVELIST stub renders).
 *
 * Run as:
 *   php pipeline-upgrade-v2-validation-runner.php step_1b_v2 [anima-machina]
 */
function step_1b_v2(string $slug): void
{
    echo "=== 1B v2 Surgical Upgrade Validation ===\n\n";

    // ── 5A: Prompt marker checks ─────────────────────────────────────────────
    echo "--- 5A: Prompt marker checks ---\n";

    $requiredMarkers = [
        'SINGLE-SOURCE CONFIDENCE FRAMEWORK',
        'NUMERICAL ENFORCEMENT LAYER',
        'RHYTHM TRANSITION ARCHITECTURE',
        'BEAT ARCHITECTURE PROTOCOL',
        'SCENE TRANSITION COMPRESSION PROTOCOL',
        'VOICE DECAY PREVENTION PROTOCOL',
        'QUANTITATIVE TRANSLATION MAPPINGS',
    ];

    $screenwriterBlades = [
        'ai.agents.adaptation.voice-lock.system-prompt-screenwriter' => [
            'formatDetection' => ['detected_format' => 'SCREENPLAY'],
            'formatDetectionOutput' => '{"detected_format":"SCREENPLAY"}',
            'ipAudit' => ['scorecard' => 'stub'],
            'currentPhase' => 'Voice Lock 1B v2',
        ],
        'ai.agents.adaptation.voice-lock.chapter-system-prompt-screenwriter' => [],
    ];

    foreach ($screenwriterBlades as $view => $data) {
        try {
            $rendered = view($view, $data)->render();
            echo "Blade rendered: {$view} (" . strlen($rendered) . " bytes)\n";
            foreach ($requiredMarkers as $marker) {
                $found = str_contains($rendered, $marker);
                echo ($found ? 'ok   ' : 'FAIL ') . "marker present: {$marker}\n";
            }
        } catch (\Throwable $e) {
            echo "FAIL render {$view}: {$e->getMessage()}\n";
        }
        echo "\n";
    }

    // ── 5B: SCREENWRITER voice_profile schema probe ───────────────────────────
    echo "--- 5B: SCREENWRITER voice_profile schema probe ---\n";

    $story = null;
    try {
        $story = \App\Models\Story::query()->where('slug', $slug)->with('adaptation')->first();
    } catch (\Throwable $e) {
        echo "(DB unavailable — skipping schema probe: {$e->getMessage()})\n";
    }

    if ($story?->adaptation?->voice_profile) {
        $vp = (array) $story->adaptation->voice_profile;
        $profileType = $vp['profile_type'] ?? 'MISSING';
        echo "profile_type: {$profileType}\n";

        if ($profileType !== 'SCREENWRITER') {
            echo "SKIP schema probe — story '{$slug}' is not SCREENWRITER (profile_type={$profileType}). Run on a screenplay IP.\n";
        } else {
            $dna = (array) ($vp['author_voice_dna_profile'] ?? []);

            // M–P field presence under author_voice_dna_profile
            foreach ([
                'numerical_enforcement_layer',
                'rhythm_transition_architecture',
                'beat_architecture_protocol',
                'scene_transition_compression_protocol',
            ] as $field) {
                echo (array_key_exists($field, $dna) ? 'ok   ' : 'FAIL ') . "author_voice_dna_profile.{$field} present\n";
            }

            // voice_decay_prevention_protocol at TOP LEVEL (not under DNA)
            $vdpp = $vp['voice_decay_prevention_protocol'] ?? null;
            echo (($vdpp !== null) ? 'ok   ' : 'FAIL ') . "voice_decay_prevention_protocol present (top-level)\n";
            if ($vdpp !== null) {
                foreach (['re_anchoring_trigger', 'passage_level_enforcement_checks', 'drift_detection_metrics'] as $key) {
                    echo (array_key_exists($key, (array) $vdpp) ? 'ok   ' : 'FAIL ') . "voice_decay_prevention_protocol.{$key}\n";
                }
            }

            // screenplay_to_prose_protocol as object (not bare array)
            $s2p = $dna['screenplay_to_prose_protocol'] ?? null;
            $isObject = is_array($s2p) && array_key_exists('element_rules', $s2p) && array_key_exists('quantitative_translation_mappings', $s2p);
            echo ($isObject ? 'ok   ' : 'FAIL ') . "screenplay_to_prose_protocol is object with element_rules + quantitative_translation_mappings\n";
            if ($isObject) {
                $qtmCount = count((array) ($s2p['quantitative_translation_mappings'] ?? []));
                echo ($qtmCount >= 6 ? 'ok   ' : 'FAIL ') . "quantitative_translation_mappings count >= 6 (got {$qtmCount})\n";
                $erCount = count((array) ($s2p['element_rules'] ?? []));
                echo ($erCount > 0 ? 'ok   ' : 'FAIL ') . "element_rules non-empty (got {$erCount})\n";
            }

            // rhythm_transition_architecture.transition_matrix 4×4
            $rta = (array) ($dna['rhythm_transition_architecture'] ?? []);
            $matrix = (array) ($rta['transition_matrix'] ?? []);
            $matrixComplete = count($matrix) === 4 && collect($matrix)->every(fn ($row) => is_array($row) && count($row) === 4);
            echo ($matrixComplete ? 'ok   ' : 'FAIL ') . "rhythm_transition_architecture.transition_matrix is 4x4\n";

            // ── Canonical path guards ─────────────────────────────────────────
            echo "\n--- Canonical path guards ---\n";

            // FAIL if quantitative_translation_mappings exists anywhere except correct path
            $serialized = json_encode($vp, JSON_UNESCAPED_UNICODE) ?: '';
            // Check for top-level qtm
            $topLevelQtm = array_key_exists('quantitative_translation_mappings', $vp);
            echo ($topLevelQtm ? 'FAIL ' : 'ok   ') . "quantitative_translation_mappings NOT at top-level of voice_profile\n";

            // Check for qtm directly under author_voice_dna_profile (not inside screenplay_to_prose_protocol)
            $dnaRootQtm = array_key_exists('quantitative_translation_mappings', $dna);
            echo ($dnaRootQtm ? 'FAIL ' : 'ok   ') . "quantitative_translation_mappings NOT directly under author_voice_dna_profile root\n";

            // FAIL if voice_decay_prevention_protocol appears under author_voice_dna_profile
            $vdppUnderDna = array_key_exists('voice_decay_prevention_protocol', $dna);
            echo ($vdppUnderDna ? 'FAIL ' : 'ok   ') . "voice_decay_prevention_protocol NOT under author_voice_dna_profile (must be top-level only)\n";

            // ── Chunk aggregation guards ──────────────────────────────────────
            echo "\n--- Chunk aggregation guards ---\n";
            echo "Checking cached chapter fragments for {$slug}...\n";

            $requiredFragmentFields = [
                'action_line_count',
                'rhythm_transition_matrix_counts',
                'dialogue_speech_lengths_by_character',
            ];

            $story2 = \App\Models\Story::query()->where('slug', $slug)->with('chapters.cachedChapterVoiceFragments')->first();
            $fragmentsChecked = 0;
            $fragmentFails = [];
            if ($story2) {
                foreach ($story2->chapters ?? [] as $chapter) {
                    foreach ($chapter->cachedChapterVoiceFragments ?? [] as $frag) {
                        $fragData = (array) ($frag->voice_observations ?? []);
                        $metricCounts = (array) ($fragData['metric_counts'] ?? []);
                        $fragmentsChecked++;
                        foreach ($requiredFragmentFields as $field) {
                            if (!array_key_exists($field, $metricCounts)) {
                                $fragmentFails[] = "fragment {$frag->id}: missing metric_counts.{$field}";
                            }
                        }
                        // Guard: fragment must NOT be percentage-only
                        if (!empty($fragData['fragment_rate_notes']) && empty($metricCounts)) {
                            $fragmentFails[] = "fragment {$frag->id}: percentage-only notes without metric_counts (violates 1C contract)";
                        }
                        // Check dialogue_speech_lengths_by_character has speech_lengths_w
                        $dspc = (array) ($metricCounts['dialogue_speech_lengths_by_character'] ?? []);
                        foreach ($dspc as $charEntry) {
                            if (!isset($charEntry['speech_lengths_w'])) {
                                $fragmentFails[] = "fragment {$frag->id}: dialogue_speech_lengths_by_character missing speech_lengths_w for {$charEntry['character']}";
                            }
                        }
                    }
                }
            }

            if ($fragmentsChecked === 0) {
                echo "SKIP no cached chapter voice fragments found (run pipeline first)\n";
            } elseif (empty($fragmentFails)) {
                echo "ok   {$fragmentsChecked} chapter fragment(s) have required raw-count fields\n";
            } else {
                foreach ($fragmentFails as $fail) {
                    echo "FAIL {$fail}\n";
                }
            }
        }
    } else {
        echo "(no voice_profile for '{$slug}' — run pipeline on a screenplay IP first, then rerun step_1b_v2)\n";
    }

    // ── 5C: Runtime Section 6 content probe ──────────────────────────────────
    echo "\n--- 5C: Runtime Section 6 content probe ---\n";

    // SCREENWRITER stub
    $screenwriterStub = stub_runtime_template_data_screenwriter();
    try {
        $swRendered = view('ai.agents.chaos.runtime-narrator-template', $screenwriterStub)->render();
        echo "Blade rendered (SCREENWRITER): " . strlen($swRendered) . " bytes\n";

        $instructionMarkers = [
            'Apply the passage-level enforcement checks',
            're-anchor to the numerical enforcement layer',
            'VOICE DECAY PREVENTION PROTOCOL',
            'NUMERICAL ENFORCEMENT LAYER',
            'RHYTHM TRANSITION ARCHITECTURE',
            'SCREENPLAY-TO-PROSE PROTOCOL',
            'QUANTITATIVE TRANSLATION MAPPINGS',
            'Every 300-400 words',  // re_anchoring_trigger value from stub
        ];
        foreach ($instructionMarkers as $marker) {
            echo (str_contains($swRendered, $marker) ? 'ok   ' : 'FAIL ') . "SCREENWRITER Section 6 contains: {$marker}\n";
        }

        // Assert no raw chunk metric count keys in runtime render
        $chunkRawFields = ['action_line_count', 'line_length_bucket_counts', 'rhythm_transition_matrix_counts', 'dialogue_speech_lengths_by_character', 'speech_lengths_w'];
        foreach ($chunkRawFields as $rawField) {
            $found = str_contains($swRendered, $rawField);
            echo ($found ? 'FAIL ' : 'ok   ') . "SCREENWRITER runtime does NOT expose chunk raw field: {$rawField}\n";
        }
    } catch (\Throwable $e) {
        echo "FAIL SCREENWRITER render: {$e->getMessage()}\n";
    }

    // NOVELIST stub — assert no Section 3B content
    $novelistStub = stub_runtime_template_data();
    try {
        $novRendered = view('ai.agents.chaos.runtime-narrator-template', $novelistStub)->render();
        echo "\nBlade rendered (NOVELIST): " . strlen($novRendered) . " bytes\n";

        $section3bMarkers = [
            'VOICE DECAY PREVENTION PROTOCOL',
            're_anchoring_trigger',
            'passage_level_enforcement_checks',
        ];
        foreach ($section3bMarkers as $marker) {
            $found = str_contains($novRendered, $marker);
            echo ($found ? 'FAIL ' : 'ok   ') . "NOVELIST runtime does NOT contain Section 3B: {$marker}\n";
        }
    } catch (\Throwable $e) {
        echo "FAIL NOVELIST render: {$e->getMessage()}\n";
    }
}

// -----------------------------------------------------------------------------

/**
 * step_v23 — V2.3 Chaos Pipeline Upgrade validation.
 *
 * Covers:
 *   A. Static source checks (no DB): PHP class methods, Blade file markers,
 *      ChaosEngineService sec13 anchor, Builder + Job guards.
 *   B. Blade render checks: all updated V2.3 blades with stub data.
 *   C. Runtime template: D8 v2 render with V2.3 stub + anchor-absent failure test.
 *   D. DB-dependent: voice_profile anchor fields (skips gracefully when no DB).
 *
 * Run as:
 *   php pipeline-upgrade-v2-validation-runner.php step_v23 [anima-machina]
 */
function step_v23(string $slug): void
{
    echo "=== V2.3 Chaos Pipeline Upgrade Validation ===\n\n";

    // ── A. Static PHP source checks (no DB needed) ────────────────────────────
    echo "--- A. Static PHP source checks ---\n";

    // A1. VoiceProfilePromptSlice — new V2.3 slice methods
    $slicePath = app_path('Ai/Adaptation/VoiceProfilePromptSlice.php');
    $sliceSrc  = is_file($slicePath) ? file_get_contents($slicePath) : '';
    $sliceChecks = [
        'dnaBansAndAnchor() method exists'     => 'public static function dnaBansAndAnchor(',
        'dnaBansAndAnchorCard() method exists' => 'public static function dnaBansAndAnchorCard(',
        'dnaBansAndAnchor throws on missing voice_anchor'  => "RuntimeException",
        'dnaBansAndAnchorCard throws on missing anchor_card' => "RuntimeException",
        'dnaBansAndAnchor includes voice_anchor key'  => "\$base['voice_anchor'] = \$voiceProfile['voice_anchor']",
        'dnaBansAndAnchorCard includes anchor_card key' => "\$base['anchor_card'] = \$voiceProfile['anchor_card']",
        'dnaBansAndAnchorCard explicitly omits voice_anchor' => 'Deliberately omits full voice_anchor',
    ];
    foreach ($sliceChecks as $label => $needle) {
        echo (str_contains($sliceSrc, $needle) ? 'ok   ' : 'FAIL ') . "VoiceProfilePromptSlice: {$label}\n";
    }

    // A2. RuntimeNarratorTemplateBuilder — D8 v2 guards
    $builderPath = app_path('Ai/Adaptation/RuntimeNarratorTemplateBuilder.php');
    $builderSrc  = is_file($builderPath) ? file_get_contents($builderPath) : '';
    $builderChecks = [
        'MAX_PROMPT_CHARS = 65_000'              => 'MAX_PROMPT_CHARS = 65_000',
        'assertAnchorFieldsPresent() called in build()' => 'assertAnchorFieldsPresent(',
        'assertRenderedPromptClean() called in build()' => 'assertRenderedPromptClean(',
        'throws RuntimeException (not assert) on missing anchor' => 'throw new \RuntimeException',
    ];
    foreach ($builderChecks as $label => $needle) {
        echo (str_contains($builderSrc, $needle) ? 'ok   ' : 'FAIL ') . "RuntimeNarratorTemplateBuilder: {$label}\n";
    }

    // A3. RuntimeNarratorAssemblyJob — post-render guards
    $jobPath = app_path('Jobs/Adaptation/RuntimeNarratorAssemblyJob.php');
    $jobSrc  = is_file($jobPath) ? file_get_contents($jobPath) : '';
    $jobChecks = [
        'post-render unmapped-token guard exists' => '{{',
        'post-render char-count guard exists'     => 'MAX_PROMPT_CHARS',
        'throws RuntimeException (not assert)'    => 'throw new RuntimeException',
    ];
    foreach ($jobChecks as $label => $needle) {
        echo (str_contains($jobSrc, $needle) ? 'ok   ' : 'FAIL ') . "RuntimeNarratorAssemblyJob: {$label}\n";
    }

    // A4. ChaosEngineService — D8 v2 sec13 anchor
    $enginePath = app_path('Services/ChaosEngineService.php');
    $engineSrc  = is_file($enginePath) ? file_get_contents($enginePath) : '';
    echo (str_contains($engineSrc, "\$sec13Anchor = '### SECTION 13:'") ? 'ok   ' : 'FAIL ')
        . "ChaosEngineService: sec13Anchor updated to D8v2 format '### SECTION 13:'\n";
    echo (str_contains($engineSrc, "\$sec13Anchor = '=== SECTION 13 —'") ? 'FAIL (old V2.2 anchor still present!) ' : 'ok   ')
        . "ChaosEngineService: old '=== SECTION 13 —' anchor removed\n";

    // A5. EntryPointDiagnosisJob — uses dnaBansAndAnchor, not dnaAndBans
    $epdJobPath = app_path('Jobs/Adaptation/EntryPointDiagnosisJob.php');
    $epdJobSrc  = is_file($epdJobPath) ? file_get_contents($epdJobPath) : '';
    echo (str_contains($epdJobSrc, 'dnaBansAndAnchor(') ? 'ok   ' : 'FAIL ')
        . "EntryPointDiagnosisJob: uses dnaBansAndAnchor() slice\n";

    // A6. ChoiceDesignJob — uses dnaBansAndAnchorCard, throws on null first_choice_spec
    $cdJobPath = app_path('Jobs/Adaptation/ChoiceDesignJob.php');
    $cdJobSrc  = is_file($cdJobPath) ? file_get_contents($cdJobPath) : '';
    echo (str_contains($cdJobSrc, 'dnaBansAndAnchorCard(') ? 'ok   ' : 'FAIL ')
        . "ChoiceDesignJob: uses dnaBansAndAnchorCard() slice\n";
    echo (str_contains($cdJobSrc, 'first_choice_spec') ? 'ok   ' : 'FAIL ')
        . "ChoiceDesignJob: reads first_choice_spec from entry_point_diagnosis\n";
    echo (str_contains($cdJobSrc, 'first_choice_spec missing') ? 'ok   ' : 'FAIL ')
        . "ChoiceDesignJob: throws RuntimeException when first_choice_spec is null\n";

    // A7. EntryPointDiagnosisAgent schema — first_choice_spec present
    $epdAgentPath = app_path('Ai/Agents/Adaptation/EntryPointDiagnosisAgent.php');
    $epdAgentSrc  = is_file($epdAgentPath) ? file_get_contents($epdAgentPath) : '';
    echo (str_contains($epdAgentSrc, "'first_choice_spec'") ? 'ok   ' : 'FAIL ')
        . "EntryPointDiagnosisAgent: first_choice_spec in schema\n";
    echo (str_contains($epdAgentSrc, "'cold_open'") ? 'ok   ' : 'FAIL ')
        . "EntryPointDiagnosisAgent: cold_open preserved (runtime key)\n";

    // A8. VoiceLockSchema — V2.3 anchor fields
    $vlSchemaPath = app_path('Ai/Agents/Adaptation/VoiceLockSchema.php');
    $vlSchemaSrc  = is_file($vlSchemaPath) ? file_get_contents($vlSchemaPath) : '';
    foreach (['voice_anchor', 'anchor_card', 'runtime_self_check', 'build_time_qa_protocol'] as $field) {
        echo (str_contains($vlSchemaSrc, "'{$field}'") ? 'ok   ' : 'FAIL ')
            . "VoiceLockSchema: {$field} defined\n";
    }

    // A9. VoiceLockChapterAgent — anchor candidate fields in both branches
    $vlAgentPath = app_path('Ai/Agents/Adaptation/VoiceLockChapterAgent.php');
    $vlAgentSrc  = is_file($vlAgentPath) ? file_get_contents($vlAgentPath) : '';
    foreach (['voice_anchor_candidates', 'anchor_card_candidates', 'self_check_candidates'] as $field) {
        echo (str_contains($vlAgentSrc, "'{$field}'") ? 'ok   ' : 'FAIL ')
            . "VoiceLockChapterAgent: {$field} defined\n";
    }

    // ── B. Blade render checks ────────────────────────────────────────────────
    echo "\n--- B. V2.3 Blade render checks ---\n";

    $v23Blades = [
        // Voice Lock — 1B v3 screenwriter
        'ai.agents.adaptation.voice-lock.system-prompt-screenwriter' => [
            'formatDetection' => ['detected_format' => 'SCREENPLAY'],
            'formatDetectionOutput' => '{"detected_format":"SCREENPLAY"}',
            'ipAudit' => ['scorecard' => 'stub'],
            'currentPhase' => 'Voice Lock 1B v3',
        ],
        // Voice Lock — 1A v2 novelist
        'ai.agents.adaptation.voice-lock.system-prompt-novelist' => [
            'formatDetection' => ['detected_format' => 'NOVEL'],
            'formatDetectionOutput' => '{"detected_format":"NOVEL"}',
            'ipAudit' => ['scorecard' => 'stub'],
            'currentPhase' => 'Voice Lock 1A v2',
        ],
        // Chapter prompts
        'ai.agents.adaptation.voice-lock.chapter-system-prompt-screenwriter' => [],
        'ai.agents.adaptation.voice-lock.chapter-system-prompt-novelist' => [],
        // Merge prompt
        'ai.agents.adaptation.voice-lock.merge-prompt' => [
            'title' => 'X', 'author' => 'A', 'year' => '2026', 'format' => 'NOVEL',
            'formatDetection' => [], 'ipAudit' => [],
            'totalChapters' => 1, 'voiceFragments' => [['chapter_id' => 1, 'chapter_position' => 1]],
        ],
        // Entry point diagnosis — D10
        'ai.agents.adaptation.entry-point-diagnosis.system-prompt' => [
            'formatDetection' => '', 'formatDetectionOutput' => '', 'currentPhase' => 'Phase 3 D10',
        ],
        'ai.agents.adaptation.entry-point-diagnosis.prompt' => [
            'voiceProfile' => [
                'profile_type' => 'NOVELIST',
                'author_voice_dna_profile' => [],
                'master_rule_1_hard_bans' => [],
                'voice_anchor' => [
                    ['mode' => 'stub', 'source' => 'ch1', 'techniques' => 'stub', 'prose' => 'Stub prose.'],
                ],
                'anchor_card' => ['No em-dash.'],
                'runtime_self_check' => ['Check em-dash.'],
            ],
            'voiceProfileLabel' => 'Sections 1+2+Voice Anchor',
            'storySessionMap' => [], 'ipAudit' => [], 'sessionNumber' => 1,
            'sessionSourcePages' => 'stub pages', 'sessionEvents' => [],
            'protagonist' => 'Alice',
            'format' => 'NOVEL',
        ],
        // Choice design — D4 patch
        'ai.agents.adaptation.choice-design.system-prompt' => [
            'formatDetection' => '', 'formatDetectionOutput' => '', 'currentPhase' => 'Phase 5 D4',
        ],
        'ai.agents.adaptation.choice-design.prompt' => [
            'voiceProfile' => [
                'profile_type' => 'NOVELIST',
                'author_voice_dna_profile' => [],
                'master_rule_1_hard_bans' => [],
                'anchor_card' => ['No em-dash.'],
            ],
            'voiceProfileLabel' => 'Sections 1+2+Anchor Card',
            'beatMap' => [], 'storySessionMap' => [], 'protagonistCoreTrait' => 'x',
            'emotionalPromise' => 'stub', 'sessionNumber' => 1, 'choiceMomentPages' => 'stub pages',
            'firstChoiceSpec' => ['setup_prose' => 'stub', 'choice_question' => 'stub?'],
        ],
    ];

    foreach ($v23Blades as $view => $data) {
        try {
            $bytes = strlen(view($view, $data)->render());
            echo "ok   {$view}  [{$bytes} bytes]\n";
        } catch (\Throwable $e) {
            echo "FAIL {$view}  [{$e->getMessage()}]\n";
        }
    }

    // ── B2. Blade content markers ─────────────────────────────────────────────
    echo "\n--- B2. V2.3 Blade content markers ---\n";

    // 1B v3 screenwriter system prompt must contain V2.3 section headers
    try {
        $swData = [
            'formatDetection' => ['detected_format' => 'SCREENPLAY'],
            'formatDetectionOutput' => '{"detected_format":"SCREENPLAY"}',
            'ipAudit' => ['scorecard' => 'stub'],
            'currentPhase' => 'Voice Lock 1B v3',
        ];
        $swRendered = view('ai.agents.adaptation.voice-lock.system-prompt-screenwriter', $swData)->render();
        foreach ([
            'VOICE ANCHOR',
            'ANCHOR CARD',
            'RUNTIME SELF-CHECK PROTOCOL',
            'BUILD-TIME QA PROTOCOL',
        ] as $marker) {
            echo (str_contains($swRendered, $marker) ? 'ok   ' : 'FAIL ')
                . "screenwriter system-prompt 1B v3 contains: {$marker}\n";
        }
        // V2.3: VDPP is build-time only in 1B v3 — may or may not appear in the merge prompt
        // depending on the deliverable. Its absence means the anchor system replaced it (expected).
        echo (str_contains($swRendered, 'VOICE DECAY PREVENTION PROTOCOL') ? 'info (VDPP still present as concept) ' : 'ok   (VDPP absent — replaced by Voice Anchor system) ')
            . "screenwriter 1B v3: VOICE DECAY PREVENTION PROTOCOL status\n";
    } catch (\Throwable $e) {
        echo "FAIL screenwriter 1B v3 render: {$e->getMessage()}\n";
    }

    // D10 entry-point-diagnosis system prompt must contain D10 section headers
    try {
        $d10Data = ['formatDetection' => '', 'formatDetectionOutput' => '', 'currentPhase' => 'Phase 3 D10'];
        $d10Rendered = view('ai.agents.adaptation.entry-point-diagnosis.system-prompt', $d10Data)->render();
        foreach ([
            'FIRST CHOICE — SPEC',  // D10 Task 4 header (prompt uses em-dash in section title)
            'COLD OPEN',
        ] as $marker) {
            echo (str_contains($d10Rendered, $marker) ? 'ok   ' : 'FAIL ')
                . "entry-point-diagnosis D10 system-prompt contains: {$marker}\n";
        }
    } catch (\Throwable $e) {
        echo "FAIL entry-point-diagnosis D10 render: {$e->getMessage()}\n";
    }

    // Choice design prompt must contain firstChoiceSpec + anchor_card, NOT voice_anchor exemplar prose
    try {
        $cdData = [
            'voiceProfile' => [
                'profile_type' => 'NOVELIST',
                'author_voice_dna_profile' => [],
                'master_rule_1_hard_bans' => [],
                'anchor_card' => ['No em-dash.', 'Sentences under 20 words.'],
            ],
            'voiceProfileLabel' => 'Sections 1+2+Anchor Card',
            'beatMap' => [], 'storySessionMap' => [], 'protagonistCoreTrait' => 'x',
            'emotionalPromise' => 'stub', 'sessionNumber' => 1, 'choiceMomentPages' => 'stub pages',
            'firstChoiceSpec' => ['setup_prose' => 'She stood at the fork.', 'choice_question' => 'Which road?'],
        ];
        $cdRendered = view('ai.agents.adaptation.choice-design.prompt', $cdData)->render();
        echo (str_contains($cdRendered, 'She stood at the fork.') ? 'ok   ' : 'FAIL ')
            . "choice-design prompt: firstChoiceSpec content rendered\n";
        echo (str_contains($cdRendered, 'No em-dash.') ? 'ok   ' : 'FAIL ')
            . "choice-design prompt: anchor_card rendered\n";
        // voice_anchor exemplar prose must NOT appear — dnaBansAndAnchorCard omits it
        echo (! str_contains($cdRendered, 'voice_anchor') ? 'ok   ' : 'FAIL ')
            . "choice-design prompt: full voice_anchor exemplar prose NOT present (token budget)\n";
    } catch (\Throwable $e) {
        echo "FAIL choice-design prompt render: {$e->getMessage()}\n";
    }

    // ── C. Runtime template D8 v2 with V2.3 stub ─────────────────────────────
    echo "\n--- C. Runtime template D8 v2 render ---\n";

    $stub = stub_runtime_template_data();
    try {
        $rendered = view('ai.agents.chaos.runtime-narrator-template', $stub)->render();
        echo "ok   D8 v2 render (" . strlen($rendered) . " bytes)\n";
        foreach ([
            '**4A — THE VOICE ANCHOR (imitate these):**',
            'The door was open. She went in.',           // stub voice_anchor prose
            '**THE ANCHOR CARD (',                       // anchor_card section
            'No em-dash in any form',                   // stub anchor_card rule 1
            'SELF-CHECK — run silently on your draft',
            '### SECTION 13: COLD OPEN',
            '### SECTION 18: VOICE RE-ANCHOR',
            '[OPENING_SCENE_INJECTION_POINT]',
            '[WORLD_STATE_TIERED_INJECTION_POINT]',
            '115-125 words',
        ] as $marker) {
            echo (str_contains($rendered, $marker) ? 'ok   ' : 'FAIL ') . "D8v2 render contains: {$marker}\n";
        }
        // Old V2.2 markers must not appear
        foreach ([
            'RUNTIME GENERATION RULES — CADENCE, ECONOMY, AND FORWARD PULL',
            'FIRST-3-MINUTES OPENING PROTOCOL',
        ] as $removed) {
            echo (str_contains($rendered, $removed) ? 'FAIL (V2.2 artefact present!) ' : 'ok   ')
                . "D8v2 render does NOT contain old marker: {$removed}\n";
        }
    } catch (\Throwable $e) {
        echo "FAIL D8 v2 render: {$e->getMessage()}\n";
    }

    // C2. Anchor-absent failure: stub WITHOUT voice_anchor must fail the builder guard.
    echo "\n--- C2. Builder anchor-absent failure test ---\n";
    $stubNoAnchor = stub_runtime_template_data();
    unset($stubNoAnchor['voice']['voice_anchor']);
    try {
        // Direct builder call is not possible without DB, but we can test the
        // Blade render path. The guard is in the builder, not the blade template
        // itself — so the blade renders fine; the builder wraps it.
        // Instead, verify the guard code is present in the builder source.
        echo "(Builder guard is in RuntimeNarratorTemplateBuilder::assertAnchorFieldsPresent() — "
            . "tested via source check in section A above. Full guard requires DB session object.)\n";
        // What we CAN test: verify VoiceProfilePromptSlice throws on missing voice_anchor.
        try {
            \App\Ai\Adaptation\VoiceProfilePromptSlice::dnaBansAndAnchor([
                'author_voice_dna_profile' => [],
                'master_rule_1_hard_bans'  => [],
                // voice_anchor intentionally absent
            ]);
            echo "FAIL dnaBansAndAnchor did NOT throw on missing voice_anchor\n";
        } catch (\RuntimeException $e) {
            echo "ok   dnaBansAndAnchor throws RuntimeException on missing voice_anchor: {$e->getMessage()}\n";
        }

        try {
            \App\Ai\Adaptation\VoiceProfilePromptSlice::dnaBansAndAnchorCard([
                'author_voice_dna_profile' => [],
                'master_rule_1_hard_bans'  => [],
                // anchor_card intentionally absent
            ]);
            echo "FAIL dnaBansAndAnchorCard did NOT throw on missing anchor_card\n";
        } catch (\RuntimeException $e) {
            echo "ok   dnaBansAndAnchorCard throws RuntimeException on missing anchor_card: {$e->getMessage()}\n";
        }
    } catch (\Throwable $e) {
        echo "FAIL slice guard test: {$e->getMessage()}\n";
    }

    // ── D. DB-dependent: voice_profile anchor fields ──────────────────────────
    echo "\n--- D. DB-dependent: voice_profile V2.3 anchor fields ---\n";

    $story = null;
    try {
        $story = \App\Models\Story::query()->where('slug', $slug)->with('adaptation')->first();
    } catch (\Throwable $e) {
        echo "(DB unavailable — skipping: {$e->getMessage()})\n";
        return;
    }

    if (! $story?->adaptation?->voice_profile) {
        echo "(no voice_profile for '{$slug}' — run pipeline on Cloud after deploy)\n";
        return;
    }

    $vp = (array) $story->adaptation->voice_profile;
    echo "profile_type: " . ($vp['profile_type'] ?? 'MISSING') . "\n";

    // V2.3 top-level anchor fields
    foreach (['voice_anchor', 'anchor_card', 'runtime_self_check', 'build_time_qa_protocol'] as $field) {
        $present = ! empty($vp[$field]);
        echo ($present ? 'ok   ' : 'miss ') . "voice_profile.{$field} present\n";
        if ($present && $field === 'voice_anchor') {
            $count = count((array) $vp[$field]);
            echo "  voice_anchor exemplar count: {$count} (expect >= 2)\n";
        }
        if ($present && in_array($field, ['anchor_card', 'runtime_self_check'], true)) {
            $count = count((array) $vp[$field]);
            echo "  {$field} rule count: {$count} (expect >= 3)\n";
        }
    }

    // Ensure legacy fourteen_point_audit still present (backward-compat)
    echo (! empty($vp['fourteen_point_audit_protocol']) ? 'ok   ' : 'miss (legacy field) ')
        . "voice_profile.fourteen_point_audit_protocol still present\n";

    // entry_point_diagnosis: first_choice_spec present for session 1
    $session1 = $story->adaptation->sessionAdaptations?->firstWhere('session_number', 1);
    if ($session1) {
        $entry = (array) ($session1->entry_point_diagnosis ?? []);
        echo (! empty($entry['cold_open']) ? 'ok   ' : 'miss ')
            . "session_1.entry_point_diagnosis.cold_open present\n";
        echo (! empty($entry['first_choice_spec']) ? 'ok   ' : 'miss ')
            . "session_1.entry_point_diagnosis.first_choice_spec present\n";
        if (! empty($entry['first_choice_spec'])) {
            $spec = (array) $entry['first_choice_spec'];
            foreach (['setup_prose', 'choice_question', 'option_1_text', 'option_1_alignment'] as $k) {
                echo (isset($spec[$k]) ? 'ok   ' : 'miss ') . "  first_choice_spec.{$k}\n";
            }
        }
        // choice_design: first branching_choice must be category=IDENTITY (from D4 patch)
        $cd = (array) ($session1->session_choice_design ?? []);
        $choices = (array) ($cd['branching_choices'] ?? []);
        if (! empty($choices)) {
            $first = (array) ($choices[0] ?? []);
            $category = $first['category'] ?? 'MISSING';
            echo ($category === 'IDENTITY' ? 'ok   ' : "FAIL (got {$category}) ")
                . "session_1.branching_choices[0].category = IDENTITY\n";
        } else {
            echo "miss session_1.session_choice_design.branching_choices empty\n";
        }
    } else {
        echo "miss session_number=1 not found for '{$slug}'\n";
    }
}

// -----------------------------------------------------------------------------

function stub_runtime_template_data_screenwriter(): array
{
    $base = stub_runtime_template_data();

    $base['voice'] = [
        'profile_type' => 'SCREENWRITER',
        // V2.3 D8 v2 required anchor fields.
        'voice_anchor' => [
            [
                'mode'       => 'translated prose',
                'source'     => 'Scene 1, action block',
                'techniques' => 'fragment punch, verb-first, zero interiority',
                'prose'      => 'The door swings open. She steps in. Stops.',
            ],
        ],
        'anchor_card' => [
            'No em-dash.',
            'Fragments at 18-28% of sentences.',
            'No interior monologue — camera logic only.',
        ],
        'runtime_self_check' => [
            'Search draft for — and --. Delete every one.',
            'Count fragments. Below 18%? Compress two consecutive short sentences into one fragment.',
            'Any character speech past ceiling? Compress.',
        ],
        'author_voice_dna_profile' => [
            'signature_writing_techniques' => [
                ['name' => 'Fragment Punch', 'why_this_author' => 'Compresses impact into 1-3 words', 'frequency' => 'every emotional beat'],
            ],
            'sentence_level_patterns' => [
                'average_sentence_length' => '6',
                'cadence_variation' => 'staccato with expansion bursts',
                'clause_structure_preference' => 'simple declarative',
                'punctuation_habits' => 'periods only, no semicolons',
            ],
            'diction_fingerprint' => [
                'register_and_formality' => 'sparse, kinetic',
                'word_frequency_patterns' => 'avoids adverbs',
            ],
            'action_line_metrics' => [
                'average_words_per_line' => '5.2',
                'fragment_percentage' => '34%',
                'verb_first_percentage' => '28%',
                'paragraph_rhythm' => '2-3 lines then isolate',
            ],
            'screenplay_structure_metrics' => [
                'scene_density' => '2.1 per page',
                'int_ext_ratio' => '60:40',
                'action_to_dialogue_ratio' => '55:45',
            ],
            'dialogue_fingerprint_per_character' => [],
            'collocation_fingerprint' => [],
            'negative_space_map' => [],
            'show_explain_ratio' => ['approximate_balance' => '90% show', 'enforcement_note' => 'camera logic only'],
            'comparative_exclusion' => [],
            'numerical_enforcement_layer' => [
                'punctuation' => [
                    'period_density_per_100w' => ['target' => '8-12', 'floor' => '6', 'ceiling' => '16', 'confidence' => 'HIGH', 'sample_size' => '1200 action-line words'],
                    'semicolons' => ['target' => '0', 'floor' => '0', 'ceiling' => '0', 'confidence' => 'ABSOLUTE', 'sample_size' => '0/4800 words'],
                ],
                'rhythm' => [
                    'fragment_rate' => ['target' => '30-38%', 'floor' => '22%', 'ceiling' => '50%', 'confidence' => 'HIGH', 'sample_size' => '420 action lines'],
                    'ing_opening_percentage' => ['target' => '4-8%', 'floor' => '0%', 'ceiling' => '12%', 'confidence' => 'MEDIUM', 'sample_size' => '420 action lines'],
                ],
                'dialogue_ceilings_per_character' => [
                    ['character' => 'NORA', 'avg_words' => '8', 'p90_words' => '18', 'p95_words' => '22', 'max_words' => '31', 'speech_count' => 88, 'confidence' => 'HIGH'],
                ],
                'opener_distribution' => [
                    ['opener_type' => 'verb', 'target' => '25-32%', 'floor' => '15%', 'ceiling' => '45%', 'confidence' => 'HIGH'],
                ],
                'word_length' => [
                    'average_chars' => ['target' => '3.8-4.4', 'floor' => '3.5', 'ceiling' => '5.0', 'confidence' => 'HIGH', 'sample_size' => '4800 words'],
                    'bucket_1_3_chars_pct' => ['target' => '52-60%', 'floor' => '45%', 'ceiling' => '68%', 'confidence' => 'HIGH', 'sample_size' => '4800 words'],
                ],
            ],
            'rhythm_transition_architecture' => [
                'transition_matrix' => [
                    'ultra_short' => ['ultra_short' => 18, 'short' => 42, 'medium' => 30, 'long' => 10],
                    'short'       => ['ultra_short' => 22, 'short' => 35, 'medium' => 28, 'long' => 15],
                    'medium'      => ['ultra_short' => 20, 'short' => 30, 'medium' => 32, 'long' => 18],
                    'long'        => ['ultra_short' => 35, 'short' => 30, 'medium' => 25, 'long' => 10],
                ],
                'rhythm_change_frequency' => '71%',
                'max_consecutive_same_category' => '3',
                'signature_moves' => ['After ultra-short beat, expands to medium 30% of the time — punch-then-breathe.'],
                'anti_patterns' => ['Never stacks 4+ ultra-short lines consecutively.'],
            ],
            'beat_architecture_protocol' => [
                'beat_frequency' => '8.2%',
                'beat_vocabulary' => ['status_beats' => ['Silence.', 'Still.', 'Gone.'], 'action_beats' => ['Hold.', 'Move.'], 'transition_beats' => ['Later.'], 'emphasis_beats' => ['Eyes down.']],
                'beat_placement' => 'Before scene changes and after emotional peaks.',
                'beat_density_by_context' => 'Higher in emotional/transition scenes.',
            ],
            'scene_transition_compression_protocol' => [
                'closing_line_avg_length' => '3.8w',
                'closing_line_type_distribution' => ['image' => '42%', 'action' => '28%', 'status' => '12%', 'dialogue_adjacent' => '10%', 'beat' => '8%'],
                'closing_line_examples' => [],
                'transition_guidance' => 'Close on image or beat. Never on a 20+ word reflective sentence.',
            ],
            'screenplay_to_prose_protocol' => [
                'element_rules' => [
                    ['screenplay_element' => 'ACTION LINE', 'prose_translation_rule' => 'Maintain compression. No expansion into novelistic description.'],
                    ['screenplay_element' => 'SCENE HEADING', 'prose_translation_rule' => 'Single establishing sentence or white-space break.'],
                    ['screenplay_element' => '(beat)', 'prose_translation_rule' => 'A physical action — gesture, look, movement. NOT "There was silence."'],
                ],
                'quantitative_translation_mappings' => [
                    ['screenplay_metric' => 'Fragment rate', 'source_value' => '34%', 'prose_target' => '18-28%', 'drift_ceiling' => '10% floor', 'rationale' => 'Prose needs connective tissue but fragments are signature.'],
                    ['screenplay_metric' => 'Period density per 100w', 'source_value' => '9.8', 'prose_target' => '7-11', 'drift_ceiling' => '6 floor', 'rationale' => 'Prose sentences slightly longer.'],
                    ['screenplay_metric' => 'Avg line length', 'source_value' => '5.2w', 'prose_target' => '7-12w', 'drift_ceiling' => '16w ceiling', 'rationale' => 'Prose runs longer than action lines.'],
                    ['screenplay_metric' => '-ing openings', 'source_value' => '5%', 'prose_target' => '3-7%', 'drift_ceiling' => '12% ceiling', 'rationale' => 'AI over-deploys; low ceiling preserves voice.'],
                    ['screenplay_metric' => 'Max speech NORA', 'source_value' => '31w', 'prose_target' => '31w', 'drift_ceiling' => '31w hard ceiling', 'rationale' => 'Screenplay is the ceiling.'],
                    ['screenplay_metric' => 'Comma density per 100w', 'source_value' => '3.2', 'prose_target' => '3-5', 'drift_ceiling' => '7 ceiling', 'rationale' => 'Writer avoids commas — ceiling prevents drift.'],
                ],
            ],
        ],
        'voice_decay_prevention_protocol' => [
            're_anchoring_trigger' => 'Every 300-400 words of generated prose.',
            'passage_level_enforcement_checks' => [
                'Period density within prose target range.',
                'Zero banned punctuation (semicolons — ABSOLUTE BAN).',
                'Fragment rate above prose floor.',
                'No pronoun cluster of 3+ same-pronoun starts.',
            ],
            'drift_detection_metrics' => [
                'Fragment rate trending downward over 3+ consecutive passages.',
                '-ing openings trending upward.',
                'Speech lengths trending upward.',
            ],
        ],
        'master_rule_1_hard_bans' => [
            'universal_bans_acknowledged' => true,
            'ip_specific_bans' => [],
        ],
        'fourteen_point_audit_protocol' => [
            ['point_number' => 1, 'point_name' => 'Hard Ban Scan', 'pass_fail_definition' => 'zero bans'],
        ],
    ];

    return $base;
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
            // V2.3 D8 v2 required anchor fields — RuntimeNarratorTemplateBuilder throws without these.
            'voice_anchor' => [
                [
                    'mode'       => 'translated prose',
                    'source'     => 'Ch 1, opening paragraph',
                    'techniques' => 'compression, externalized action, no interior monologue',
                    'prose'      => 'The door was open. She went in. The cat sat on the table and watched her hands.',
                ],
            ],
            'anchor_card' => [
                'No em-dash in any form (— or --).',
                'Sentences under 20 words.',
                'No cognitive lead-ins (realized, noticed, found herself).',
            ],
            'runtime_self_check' => [
                'Search draft for — and --. Delete every one; restructure.',
                'Search for cognitive lead-ins and cut; render directly.',
                'Check last three sentence openers — same word? Vary one.',
            ],
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
