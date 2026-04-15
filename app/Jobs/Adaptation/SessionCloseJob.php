<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Agents\Adaptation\SessionCloseAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class SessionCloseJob implements ShouldQueue
{
    use Queueable;

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
            $session->update(['session_status' => SessionAdaptationStatusEnum::SESSION_CLOSE]);

            $choiceDesign = $session->session_choice_design;
            $consequenceMap = $session->choice_consequence_map;
            $scriptContent = file_get_contents($this->story->getFirstMediaPath('script'));

            $sessionAllocation = collect($adaptation->story_session_map['session_allocation'] ?? [])
                ->firstWhere('session_number', $this->sessionNumber);

            $response = (new SessionCloseAgent)->prompt(
                view('ai.agents.adaptation.session-close.prompt', [
                    'branchingChoice3Design' => $choiceDesign['branching_choice_3'] ?? null,
                    'choice3ConsequenceMap' => $consequenceMap['consequence_map_choice_3'] ?? null,
                    'sessionPrimaryGoal' => $sessionAllocation['primary_dramatic_question'] ?? '',
                    'sessionNumber' => $this->sessionNumber,
                    'resolutionSourcePages' => mb_substr($scriptContent, 0, 16000),
                ])->render()
            );

            $session->update(['session_close_design' => $response->toArray()]);
        } catch (Throwable $throwable) {
            $session->update(['session_status' => SessionAdaptationStatusEnum::FAILED]);
            throw $throwable;
        }
    }
}
