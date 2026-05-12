<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Writers\Pages;

use App\Filament\Manager\Resources\Writers\WriterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListWriters extends ListRecords
{
    protected static string $resource = WriterResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
