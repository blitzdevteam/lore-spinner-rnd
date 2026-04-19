<?php

declare(strict_types=1);

namespace App\VoiceLab\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Response;

final class IndexController extends Controller
{
    public function __invoke(): Response
    {
        return inertia('VoiceLab/Index');
    }
}
