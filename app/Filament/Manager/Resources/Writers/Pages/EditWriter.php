<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Writers\Pages;

use App\Filament\Manager\Resources\Writers\WriterResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditWriter extends EditRecord
{
    protected static string $resource = WriterResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
