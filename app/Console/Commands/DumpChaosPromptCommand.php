<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\ChaosMode\ChaosStoryConfig;
use App\Models\Story;
use App\Services\ChaosEngineService;
use Illuminate\Console\Command;

/**
 * Renders and prints the full Chaos Mode system prompt for a given story and
 * session number, using live data from the DB.
 *
 * Delegates entirely to ChaosEngineService — the single authoritative source
 * for the prompt pipeline shared by both ChaosMode and production game flows.
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

    public function __construct(private readonly ChaosEngineService $engine)
    {
        parent::__construct();
    }

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

        $story = Story::query()
            ->where('slug', $slug)
            ->with(['adaptation', 'adaptation.sessionAdaptations'])
            ->first();

        if ($story === null) {
            $this->error("Story with slug \"{$slug}\" not found in the database.");

            return self::FAILURE;
        }

        $sessionContext = $this->engine->loadSessionContext($story, $session, null);

        if ($sessionContext === null) {
            $this->error("Session {$session} has not been adapted yet for \"{$slug}\". Run the adaptation pipeline first.");

            return self::FAILURE;
        }

        $worldState      = $this->engine->emptyWorldState();
        $alignmentScaffold = $this->engine->emptyAlignmentScaffold();
        $openingScene    = ($sessionContext['opening_scene'] ?? '') ?: null;

        $rendered = $this->engine->renderSystemPrompt(
            sessionContext:      $sessionContext,
            worldState:          $worldState,
            alignmentScaffold:   $alignmentScaffold,
            symbolicMemory:      null,
            currentScene:        $openingScene,
            isClimacticPrevious: false,
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
