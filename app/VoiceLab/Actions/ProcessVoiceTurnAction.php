<?php

declare(strict_types=1);

namespace App\VoiceLab\Actions;

use App\Models\Story;
use App\VoiceLab\Agents\VoiceChatAgent;
use App\VoiceLab\Enums\VoiceLabRoleEnum;
use App\VoiceLab\Models\VoiceLabSession;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

final readonly class ProcessVoiceTurnAction
{
    /**
     * @return array{response: string, choices: string[]}
     */
    public function handle(VoiceLabSession $session, string $playerAction): array
    {
        $conversationHistory = $this->buildConversationHistory($session);

        $session->prompts()->create([
            'role' => VoiceLabRoleEnum::PLAYER,
            'text' => $playerAction,
        ]);

        $result = $this->generateReply(
            systemPrompt: $this->renderSystemPrompt($session),
            conversationHistory: $conversationHistory,
            playerAction: $playerAction,
        );

        $session->prompts()->create([
            'role' => VoiceLabRoleEnum::NARRATOR,
            'text' => $result['response'],
            'choices' => $result['choices'],
        ]);

        return $result;
    }

    /**
     * @return array{response: string, choices: string[]}
     */
    public function generateOpening(VoiceLabSession $session): array
    {
        $intro = config('voice-lab.intro', []);

        if (($intro['enabled'] ?? false) && is_string($intro['text'] ?? null) && trim($intro['text']) !== '') {
            $result = [
                'response' => $intro['text'],
                'choices' => array_values((array) ($intro['choices'] ?? [])),
            ];
        } else {
            $result = $this->generateReply(
                systemPrompt: $this->renderSystemPrompt($session),
                conversationHistory: [],
                playerAction: '',
            );
        }

        $session->prompts()->create([
            'role' => VoiceLabRoleEnum::NARRATOR,
            'text' => $result['response'],
            'choices' => $result['choices'],
        ]);

        return $result;
    }

    private function renderSystemPrompt(VoiceLabSession $session): string
    {
        $story = $session->story instanceof Story ? $session->story : null;
        $storyData = $story?->system_prompt ?? [];

        return view('voice-lab.agents.voice-chat.system-prompt', [
            'storyTitle' => $story?->title ?? 'an imaginative voice demo',
            'characterName' => $storyData['character_name'] ?? null,
            'worldRules' => $storyData['world_rules'] ?? [],
            'toneAndStyle' => $storyData['tone_and_style'] ?? null,
        ])->render();
    }

    /**
     * @return array<int, array{role: string, text: string}>
     */
    private function buildConversationHistory(VoiceLabSession $session): array
    {
        $limit = (int) config('voice-lab.history_size', 6);

        $prompts = $session->prompts()
            ->latest()
            ->limit($limit)
            ->get()
            ->reverse();

        $history = [];

        foreach ($prompts as $prompt) {
            $history[] = [
                'role' => $prompt->role->value,
                'text' => $prompt->role === VoiceLabRoleEnum::NARRATOR
                    ? strip_tags($prompt->text)
                    : $prompt->text,
            ];
        }

        return $history;
    }

    /**
     * @param  array<int, array{role: string, text: string}>  $conversationHistory
     * @return array{response: string, choices: string[]}
     */
    private function generateReply(
        string $systemPrompt,
        array $conversationHistory,
        string $playerAction,
    ): array {
        try {
            /** @var StructuredAgentResponse $response */
            $response = VoiceChatAgent::make(customInstructions: $systemPrompt)
                ->prompt(
                    view('voice-lab.agents.voice-chat.prompt', [
                        'conversationHistory' => $conversationHistory,
                        'playerAction' => $playerAction,
                    ])->render()
                );

            return [
                'response' => $response['response'] ?? '<p>The world waits for you to speak...</p>',
                'choices' => $response['choices'] ?? ['Continue forward', 'Look around', 'Speak again'],
            ];
        } catch (Throwable $e) {
            logger()->error('VoiceLab: LLM turn failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
                'player_action' => mb_substr($playerAction, 0, 500),
                'history_size' => count($conversationHistory),
                'model' => (string) env('VOICELAB_LLM_MODEL', 'gpt-5.2'),
            ]);

            return [
                'response' => '<p>The world stirs gently around you, as if waiting for another word.</p>',
                'choices' => ['Continue forward', 'Look around', 'Speak again'],
            ];
        }
    }
}
