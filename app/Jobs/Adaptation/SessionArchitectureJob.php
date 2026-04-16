<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Agents\Adaptation\SessionArchitectureAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class SessionArchitectureJob implements ShouldQueue
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
            $session->update(['session_status' => SessionAdaptationStatusEnum::SESSION_ARCHITECTURE]);

            $scriptContent = $this->story->getScriptContent();
            $sessionSourcePages = mb_substr($scriptContent, 0, 16000);

            $response = (new SessionArchitectureAgent)->prompt(
                view('ai.agents.adaptation.session-architecture.prompt', [
                    'storySessionMap' => $adaptation->story_session_map,
                    'entryPointDiagnosis' => $session->entry_point_diagnosis,
                    'sessionNumber' => $this->sessionNumber,
                    'sessionSourcePages' => $sessionSourcePages,
                ])->render()
            );

            $session->update(['session_architecture' => $response->toArray()]);
        } catch (Throwable $throwable) {
            $session->update(['session_status' => SessionAdaptationStatusEnum::FAILED]);
            throw $throwable;
        }
    }
}
