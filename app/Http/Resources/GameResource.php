<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Game;
use Illuminate\Http\Request;

/**
 * @mixin Game
 */
class GameResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'story_id' => $this->story_id,
            'current_event_id' => $this->current_event_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relations
            'story' => StoryResource::make($this->whenLoaded('story')),
            'user' => UserResource::make($this->whenLoaded('user')),
            'currentEvent' => EventResource::make($this->whenLoaded('currentEvent')),
            'prompts' => PromptResource::collection($this->whenLoaded('prompts')),

            // Counts
            'prompts_count' => $this->whenCounted('prompts'),
        ];
    }
}
