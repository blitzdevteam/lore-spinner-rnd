<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Feedback\Schemas;

use Filament\Schemas\Schema;

final class FeedbackForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([]);
    }
}
