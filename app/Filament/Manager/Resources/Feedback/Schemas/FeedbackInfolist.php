<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Feedback\Schemas;

use App\Models\Feedback;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class FeedbackInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->heading('Submission')
                    ->schema([
                        TextEntry::make('content')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        ImageEntry::make('screenshot_path')
                            ->label('Page screenshot')
                            ->getStateUsing(fn (Feedback $record): ?string => filled($record->screenshot_path)
                                ? route('feedback.screenshot', ['path' => $record->screenshot_path])
                                : null)
                            ->checkFileExistence(false)
                            ->columnSpanFull()
                            ->extraImgAttributes([
                                'class' => 'rounded-lg border border-gray-200 dark:border-gray-700 max-w-full',
                            ])
                            ->visible(fn (Feedback $record): bool => filled($record->screenshot_path)),
                    ]),
                Section::make()
                    ->columnSpanFull()
                    ->heading('Context')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.email')
                            ->label('User email')
                            ->placeholder('Guest (not signed in)'),
                        TextEntry::make('page_url')
                            ->label('Page URL')
                            ->placeholder('-')
                            ->url(fn (?string $state): ?string => filled($state) ? $state : null)
                            ->openUrlInNewTab(),
                        TextEntry::make('user_agent')
                            ->label('User agent')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                Section::make()
                    ->columnSpanFull()
                    ->heading('Timestamps')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
