<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Feedback\Pages;

use App\Filament\Manager\Resources\Feedback\FeedbackResource;
use Filament\Resources\Pages\ListRecords;

final class ListFeedbacks extends ListRecords
{
    protected static string $resource = FeedbackResource::class;
}
