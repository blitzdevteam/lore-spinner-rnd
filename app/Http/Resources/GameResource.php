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
            'id'                       => $this->id,
            'user_id'                  => $this->user_id,
            'story_id'                 => $this->story_id,
            'current_session_number'   => $this->current_session_number,
            'current_session_complete' => (bool) $this->current_session_complete,
            'model'                    => $this->model,
            'created_at'               => $this->created_at,
            'updated_at'               => $this->updated_at,

            // Relations
            'story'   => StoryResource::make($this->whenLoaded('story')),
            'user'    => UserResource::make($this->whenLoaded('user')),
            'prompts' => PromptResource::collection($this->whenLoaded('prompts')),

            // Counts
            'prompts_count' => $this->whenCounted('prompts'),
        ];
    }
}
