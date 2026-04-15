<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Agents\Adaptation\EntryPointDiagnosisAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Models\SessionAdaptation;
use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class EntryPointDiagnosisJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 300;
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
            $session->update(['session_status' => SessionAdaptationStatusEnum::ENTRY_POINT_DIAGNOSIS]);

            $scriptContent = file_get_contents($this->story->getFirstMediaPath('script'));
            $sessionSourcePages = mb_substr($scriptContent, 0, 16000);

            $response = (new EntryPointDiagnosisAgent)->prompt(
                view('ai.agents.adaptation.entry-point-diagnosis.prompt', [
                    'storySessionMap' => $adaptation->story_session_map,
                    'ipAudit' => $adaptation->ip_audit,
                    'sessionNumber' => $this->sessionNumber,
                    'sessionSourcePages' => $sessionSourcePages,
                ])->render()
            );

            $session->update(['entry_point_diagnosis' => $response->toArray()]);
        } catch (Throwable $throwable) {
            $session->update(['session_status' => SessionAdaptationStatusEnum::FAILED]);
            throw $throwable;
        }
    }
}
