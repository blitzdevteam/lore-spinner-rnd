<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Writers\Pages;

use App\Filament\Manager\Resources\Writers\WriterResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateWriter extends CreateRecord
{
    protected static string $resource = WriterResource::class;
}
