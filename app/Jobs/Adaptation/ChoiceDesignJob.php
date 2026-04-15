<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Agents\Adaptation\ChoiceDesignAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

final class ChoiceDesignJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 540;
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
            $session->update(['session_status' => SessionAdaptationStatusEnum::CHOICE_DESIGN]);

            $ipAudit = $adaptation->ip_audit;
            $scriptContent = file_get_contents($this->story->getFirstMediaPath('script'));

            $response = (new ChoiceDesignAgent)->prompt(
                view('ai.agents.adaptation.choice-design.prompt', [
                    'beatMap' => $session->session_architecture,
                    'storySessionMap' => $adaptation->story_session_map,
                    'protagonistCoreTrait' => $ipAudit['bounded_agency']['evidence'] ?? '',
                    'emotionalPromise' => $session->entry_point_diagnosis['emotional_promise'] ?? '',
                    'sessionNumber' => $this->sessionNumber,
                    'choiceMomentPages' => mb_substr($scriptContent, 0, 16000),
                ])->render()
            );

            $choiceDesign = $response->toArray();
            $session->update(['session_choice_design' => $choiceDesign]);

            $this->enrichBranchDimensionRegistry($adaptation, $choiceDesign);
        } catch (Throwable $throwable) {
            $session->update(['session_status' => SessionAdaptationStatusEnum::FAILED]);
            throw $throwable;
        }
    }

    private function enrichBranchDimensionRegistry($adaptation, array $choiceDesign): void
    {
        $sessionMap = $adaptation->story_session_map;
        $dimensions = $sessionMap['branch_dimensions'] ?? [];

        foreach (['branching_choice_1', 'branching_choice_2', 'branching_choice_3'] as $key) {
            $choice = $choiceDesign[$key] ?? null;
            if (! $choice || empty($choice['what_this_choice_tracks'])) {
                continue;
            }

            $tracked = Str::snake(Str::lower($choice['what_this_choice_tracks']));
            $existingIndex = null;

            foreach ($dimensions as $i => $dim) {
                if (Str::snake(Str::lower($dim['dimension_name'])) === $tracked) {
                    $existingIndex = $i;
                    break;
                }
            }

            $pathData = [
                'option_a' => $choice['option_a']['text'] ?? $choice['option_a']['downstream_effect'] ?? '',
                'option_b' => $choice['option_b']['text'] ?? $choice['option_b']['downstream_effect'] ?? '',
                'option_c' => $choice['option_c']['text'] ?? $choice['option_c']['downstream_effect'] ?? '',
            ];

            if ($existingIndex !== null) {
                $dimensions[$existingIndex]['possible_paths'] = $pathData;
                $dimensions[$existingIndex]['session_introduced'] ??= $this->sessionNumber;
                $dimensions[$existingIndex]['choice_id'] = $choice['choice_id'] ?? null;
            } else {
                $dimensions[] = [
                    'dimension_name' => $tracked,
                    'description' => $choice['what_this_choice_tracks'],
                    'possible_paths' => $pathData,
                    'origin' => 'phase_5',
                    'session_introduced' => $this->sessionNumber,
                    'choice_id' => $choice['choice_id'] ?? null,
                ];
            }
        }

        $sessionMap['branch_dimensions'] = $dimensions;
        $adaptation->update(['story_session_map' => $sessionMap]);
    }
}
