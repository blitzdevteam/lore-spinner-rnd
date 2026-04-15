<?php

declare(strict_types=1);

namespace App\Filament\Creator\Resources\Stories\Pages;

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Filament\Creator\Resources\Stories\StoryResource;
use App\Jobs\Adaptation\RunAdaptationPipelineJob;
use App\Models\Story;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Filament\Support\Colors\Color;

final class ViewStory extends ViewRecord
{
    protected static string $resource = StoryResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('mark-as-published')
                ->label('Mark as Published')
                ->modalWidth('3xl')
                ->modalDescription('This action will mark the story as published and make it visible to the public. This action cannot be undone.')
                ->action(function (array $data, Story $story): void {
                    $story->update([
                        'opening' => $data['opening'],
                        'status' => StoryStatusEnum::PUBLISHED,
                    ]);
                })
                ->color(Color::Green)
                ->icon(Heroicon::ExclamationTriangle)
                ->requiresConfirmation()
                ->schema([
                    RichEditor::make('opening')
                        ->required()
                        ->helperText('This will be used as the opening of the story when it is published')
                        ->toolbarButtons([
                            'redo', 'undo', 'underline', 'italic', 'bold',
                        ])
                        ->columnSpan(2),
                ])
                ->visible(fn (Story $story): bool => $story->canMarkAsPublished()),

            Action::make('run-adaptation')
                ->label('Run Adaptation')
                ->action(function (Story $story): void {
                    RunAdaptationPipelineJob::dispatch($story);
                })
                ->color(Color::Blue)
                ->icon(Heroicon::Sparkles)
                ->requiresConfirmation()
                ->modalDescription('This will run the full adaptation pipeline for this story. The process runs in the background.')
                ->visible(fn (Story $story): bool => $story->chapters()->exists()
                    && (! $story->adaptation || $story->adaptation->adaptation_status === AdaptationStatusEnum::FAILED)),

            Action::make('rerun-adaptation')
                ->label('Re-run Adaptation')
                ->action(function (Story $story): void {
                    RunAdaptationPipelineJob::dispatch($story, force: true);
                })
                ->color(Color::Orange)
                ->icon(Heroicon::ArrowPath)
                ->requiresConfirmation()
                ->modalDescription('This will reset and re-run the full adaptation pipeline. All existing adaptation data will be replaced.')
                ->visible(fn (Story $story): bool => $story->adaptation
                    && in_array($story->adaptation->adaptation_status, [
                        AdaptationStatusEnum::COMPLETED,
                        AdaptationStatusEnum::PARTIAL_COMPLETION,
                    ])),

            EditAction::make(),
        ];
    }
}
