<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Analytics start date
    |--------------------------------------------------------------------------
    |
    | All public analytics dashboards exclude data before this date.
    | Legacy gameplay, visits, and signups are not included in metrics.
    |
    */
    'start_date' => '2026-06-01',

    /*
    |--------------------------------------------------------------------------
    | Abandoned story inactivity threshold (days)
    |--------------------------------------------------------------------------
    |
    | A game is "abandoned" when it is incomplete and has had no gameplay
    | activity for this many days. Gameplay activity sources:
    | games.created_at, prompts.created_at, game_session_completions,
    | game_resets.created_at.
    |
    */
    'abandoned_inactivity_days' => 14,

];
