<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Feedback;

use App\Filament\Manager\Resources\Feedback\Pages\ListFeedbacks;
use App\Filament\Manager\Resources\Feedback\Pages\ViewFeedback;
use App\Filament\Manager\Resources\Feedback\Schemas\FeedbackForm;
use App\Filament\Manager\Resources\Feedback\Schemas\FeedbackInfolist;
use App\Filament\Manager\Resources\Feedback\Tables\FeedbacksTable;
use App\Models\Feedback;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;
use UnitEnum;

final class FeedbackResource extends Resource
{
    protected static ?string $model = Feedback::class;

    protected static ?string $navigationLabel = 'Beta feedback';

    protected static ?string $modelLabel = 'Feedback submission';

    protected static ?string $pluralModelLabel = 'Feedback submissions';

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleOvalLeftEllipsis;

    protected static ?int $navigationSort = 50;

    public static function getNavigationBadge(): string
    {
        return (string) self::getEloquentQuery()->count();
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return FeedbackForm::configure($schema);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return FeedbackInfolist::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return FeedbacksTable::configure($table);
    }

    #[Override]
    public static function getRelations(): array
    {
        return [];
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListFeedbacks::route('/'),
            'view' => ViewFeedback::route('/{record}'),
        ];
    }

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }
}
