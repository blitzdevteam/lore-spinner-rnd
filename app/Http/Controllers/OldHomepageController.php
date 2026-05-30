<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Response;

final class OldHomepageController extends Controller
{
    public function __invoke(): Response
    {
        return inertia('OldHomepage', IndexController::homepageProps());
    }
}
