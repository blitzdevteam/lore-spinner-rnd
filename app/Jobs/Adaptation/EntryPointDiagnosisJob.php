<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Agents\Adaptation\EntryPointDiagnosisAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Models\Event;
use App\Models\SessionAdaptation;
use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Batchable;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

final class EntryPointDiagnosisJob implements ShouldQueue
{
    use Batchable, Queueable;

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

            $sessionEvents = $this->story->events()
                ->where('events.session_number', $this->sessionNumber)
                ->orderBy('events.position')
                ->get(['events.id', 'events.position', 'events.title', 'events.objectives']);

            if ($sessionEvents->isEmpty()) {
                throw new RuntimeException(
                    "No events found for story {$this->story->id} session {$this->sessionNumber}"
                );
            }

            $scriptContent = $this->story->getScriptContent();
            $sessionSourcePages = mb_substr($scriptContent, 0, 16000);

            $response = (new EntryPointDiagnosisAgent)->prompt(
                view('ai.agents.adaptation.entry-point-diagnosis.prompt', [
                    'storySessionMap' => $adaptation->story_session_map,
                    'ipAudit' => $adaptation->ip_audit,
                    'sessionNumber' => $this->sessionNumber,
                    'sessionSourcePages' => $sessionSourcePages,
                    'sessionEvents' => $sessionEvents->map(fn (Event $ev) => [
                        'position' => $ev->position,
                        'title' => $ev->title,
                        'objectives' => $ev->objectives,
                    ])->all(),
                ])->render()
            );

            $result = $response->toArray();

            $startPos = $result['start_event_position'] ?? null;
            $startEvent = $sessionEvents->firstWhere('position', $startPos)
                ?? $sessionEvents->first();

            $result['start_event_id'] = $startEvent->id;
            $result['start_event_position'] = $startEvent->position;

            $session->update(['entry_point_diagnosis' => $result]);
        } catch (Throwable $throwable) {
            $session->update(['session_status' => SessionAdaptationStatusEnum::FAILED]);
            throw $throwable;
        }
    }
}
