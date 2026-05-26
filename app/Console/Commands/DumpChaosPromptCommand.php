<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\ChaosMode\ChaosStoryConfig;
use App\Http\Controllers\ChaosMode\ChaosModeController;
use App\Models\Story;
use Illuminate\Console\Command;
use ReflectionMethod;

/**
 * Renders and prints the full Chaos Mode system prompt for a given story and
 * session number, using live data from the DB.
 *
 * Delegates entirely to ChaosModeController's own private methods via
 * reflection — so this command is always in sync with the real prompt pipeline.
 * No logic is duplicated here.
 *
 * Usage:
 *   php artisan chaos:dump-prompt alices-adventures-in-wonderland
 *   php artisan chaos:dump-prompt alices-adventures-in-wonderland --session=2
 */
final class DumpChaosPromptCommand extends Command
{
    protected $signature = 'chaos:dump-prompt
        {story : Story slug (e.g. alices-adventures-in-wonderland)}
        {--session=1 : Session number to render (default: 1)}';

    protected $description = 'Print the rendered Chaos Mode system prompt for a story/session using live DB data';

    public function handle(): int
    {
        $slug    = (string) $this->argument('story');
        $session = (int) $this->option('session');

        $storyConfig = ChaosStoryConfig::find($slug);

        if ($storyConfig === null) {
            $this->error("Story slug \"{$slug}\" is not registered in ChaosStoryConfig.");
            $this->line('Available slugs:');
            foreach (ChaosStoryConfig::slugs() as $s) {
                $this->line("  {$s}");
            }
            return self::FAILURE;
        }

        $story = Story::where('slug', $slug)->first();

        if ($story === null) {
            $this->error("Story with slug \"{$slug}\" not found in the database.");
            return self::FAILURE;
        }

        $controller = app(ChaosModeController::class);

        $loadSessionContext = new ReflectionMethod($controller, 'loadSessionContext');
        $loadSessionContext->setAccessible(true);
        $sessionContext = $loadSessionContext->invoke($controller, $story, $session, null);

        $emptyWorldState = new ReflectionMethod($controller, 'emptyWorldState');
        $emptyWorldState->setAccessible(true);
        $worldState = $emptyWorldState->invoke($controller);

        $emptyAlignmentScaffold = new ReflectionMethod($controller, 'emptyAlignmentScaffold');
        $emptyAlignmentScaffold->setAccessible(true);
        $alignmentScaffold = $emptyAlignmentScaffold->invoke($controller);

        $openingScene = ($sessionContext['opening_scene'] ?? '') ?: null;

        $renderSystemPrompt = new ReflectionMethod($controller, 'renderSystemPrompt');
        $renderSystemPrompt->setAccessible(true);
        $rendered = $renderSystemPrompt->invoke(
            $controller,
            $sessionContext,
            $worldState,
            $alignmentScaffold,
            null,
            $openingScene,
            false,
        );

        $this->line('');
        $this->line('════════════════════════════════════════════════════════════════');
        $this->line("  CHAOS SYSTEM PROMPT — {$storyConfig['title']} — Session {$session}");
        $this->line('════════════════════════════════════════════════════════════════');
        $this->line('');
        $this->line($rendered);
        $this->line('');
        $this->line('════════════════════════════════════════════════════════════════');
        $this->line('  END OF PROMPT  (' . mb_strlen($rendered) . ' chars)');
        $this->line('════════════════════════════════════════════════════════════════');

        return self::SUCCESS;
    }
}
