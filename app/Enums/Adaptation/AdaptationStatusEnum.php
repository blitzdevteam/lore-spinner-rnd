<?php

declare(strict_types=1);

namespace App\Enums\Adaptation;

use App\Traits\EnumToArray;
use Filament\Support\Contracts\HasLabel;

enum AdaptationStatusEnum: string implements HasLabel
{
    use EnumToArray;

    case PENDING = 'pending';
    case FORMAT_DETECTION = 'format-detection';
    case IP_AUDIT = 'ip-audit';
    case STORY_SESSION_MAP = 'story-session-map';
    case ADAPTING_SESSIONS = 'adapting-sessions';
    case COMPLETED = 'completed';
    case PARTIAL_COMPLETION = 'partial-completion';
    case FAILED = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::FORMAT_DETECTION => 'Detecting Format',
            self::IP_AUDIT => 'Running IP Audit',
            self::STORY_SESSION_MAP => 'Building Session Map',
            self::ADAPTING_SESSIONS => 'Adapting Sessions',
            self::COMPLETED => 'Completed',
            self::PARTIAL_COMPLETION => 'Partial Completion',
            self::FAILED => 'Failed',
        };
    }

    public function getSeverity(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::FORMAT_DETECTION,
            self::IP_AUDIT,
            self::STORY_SESSION_MAP,
            self::ADAPTING_SESSIONS => 'info',
            self::COMPLETED => 'success',
            self::PARTIAL_COMPLETION => 'warning',
            self::FAILED => 'danger',
        };
    }
}
