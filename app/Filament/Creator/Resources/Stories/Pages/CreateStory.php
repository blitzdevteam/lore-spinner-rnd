<?php

declare(strict_types=1);

namespace App\Filament\Creator\Resources\Stories\Pages;

use App\Enums\Story\StoryStatusEnum;
use App\Filament\Creator\Resources\Stories\StoryResource;
use App\Jobs\Adaptation\RunAdaptationPipelineJob;
use App\Jobs\Chapter\ChapterExtractorJob;
use App\Jobs\Story\StoryCoverGeneratorJob;
use App\Jobs\Story\StoryOpeningGeneratorJob;
use App\Jobs\Story\SystemPromptGeneratorJob;
use App\Models\Story;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateStory extends CreateRecord
{
    protected static string $resource = StoryResource::class;

    protected static bool $canCreateAnother = false;

    #[\Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['creator_id'] = auth()->id();

        return parent::mutateFormDataBeforeCreate($data);
    }

    #[\Override]
    protected function handleRecordCreation(array $data): Model
    {
        /** @var Story $story */
        $story = parent::handleRecordCreation($data);

        if ($data['use_script_upload']) {
            $story->update([
                'status' => StoryStatusEnum::AWAITING_EXTRACTING_CHAPTERS_REQUEST,
            ]);

            ChapterExtractorJob::dispatch($story);
            SystemPromptGeneratorJob::dispatch($story);
            StoryCoverGeneratorJob::dispatch($story);
            StoryOpeningGeneratorJob::dispatch($story)->delay(now()->addMinutes(5));

            RunAdaptationPipelineJob::dispatch($story)->delay(now()->addMinutes(2));
        } else {
            $story->update([
                'status' => StoryStatusEnum::DRAFT,
            ]);

            StoryCoverGeneratorJob::dispatch($story);
        }

        return $story;
    }
}
