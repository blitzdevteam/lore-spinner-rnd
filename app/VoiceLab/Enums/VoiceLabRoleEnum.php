<?php

declare(strict_types=1);

namespace App\VoiceLab\Enums;

enum VoiceLabRoleEnum: string
{
    case PLAYER = 'player';
    case NARRATOR = 'narrator';
}
