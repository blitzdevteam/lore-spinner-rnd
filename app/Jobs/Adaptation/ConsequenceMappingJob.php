<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

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

        try {
            $session->update(['session_status' => SessionAdaptationStatusEnum::CONSEQUENCE_MAPPING]);

            $choiceDesign = $session->session_choice_design;

            $response = (new ConsequenceMappingAgent)->prompt(
                view('ai.agents.adaptation.consequence-mapping.prompt', [
                    'branchingChoices' => [
                        'branching_choice_1' => $choiceDesign['branching_choice_1'] ?? null,
                        'branching_choice_2' => $choiceDesign['branching_choice_2'] ?? null,
                        'branching_choice_3' => $choiceDesign['branching_choice_3'] ?? null,
                    ],
                    'storySessionMap' => $adaptation->story_session_map,
                    'protagonistCoreTrait' => $adaptation->ip_audit['bounded_agency']['evidence'] ?? '',
                    'sessionNumber' => $this->sessionNumber,
                ])->render()
            );

            $session->update(['choice_consequence_map' => $response->toArray()]);
        } catch (Throwable $throwable) {
            $session->update(['session_status' => SessionAdaptationStatusEnum::FAILED]);
            throw $throwable;
        }
    }
}
