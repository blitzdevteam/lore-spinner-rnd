<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Adaptation\VoiceProfilePromptSlice;
use App\Ai\Agents\Adaptation\ConsequenceMappingAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Batchable;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class ConsequenceMappingJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;
    public int $timeout = 420;
    public int $backoff = 60;

    public function __construct(
        private Story $story,
        private int $sessionNumber,
    ) {
        $this->onQueue('adaptation');
    }

    /** @throws Throwable */
    public function handle(): void
    {
        $adaptation = $this->story->adaptation;
        $session = $adaptation->sessionAdaptations()->where('session_number', $this->sessionNumber)->firstOrFail();

        if (empty($adaptation->voice_profile)) {
            throw new \RuntimeException(
                'voice_profile missing — Voice Lock must complete before Phase 6 (ConsequenceMapping)'
            );
        }

        try {
            $session->update(['session_status' => SessionAdaptationStatusEnum::CONSEQUENCE_MAPPING]);

            $choiceDesign = $session->session_choice_design;

            $response = (new ConsequenceMappingAgent)->prompt(
                view('ai.agents.adaptation.consequence-mapping.prompt', [
                    // V2 shape: pass through the full branching_choices array
                    // (4 entries) plus the persistent state schema + reactivity
                    // rules so the consequence map can name specific NPCs / flags.
                    'branchingChoices' => (array) ($choiceDesign['branching_choices'] ?? []),
                    'storySessionMap' => $adaptation->story_session_map,
                    'persistentStateSchema' => $adaptation->story_session_map['persistent_state_schema'] ?? [],
                    'worldReactivityRules' => $adaptation->story_session_map['world_reactivity_rules'] ?? [],
                    'protagonistCoreTrait' => $adaptation->ip_audit['bounded_agency']['evidence'] ?? '',
                    'sessionNumber' => $this->sessionNumber,
                    'voiceProfile' => VoiceProfilePromptSlice::dnaAndBans((array) ($adaptation->voice_profile ?? [])),
                ])->render()
            );

            $session->update(['choice_consequence_map' => $response->toArray()]);
        } catch (Throwable $throwable) {
            $session->update(['session_status' => SessionAdaptationStatusEnum::FAILED]);
            throw $throwable;
        }
    }
}
