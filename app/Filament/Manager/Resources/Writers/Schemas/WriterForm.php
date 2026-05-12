<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Writers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class WriterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            TextInput::make('password')
                ->password()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? bcrypt($state) : null)
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->label('Password (leave blank to keep current)'),
        ]);
    }
}
