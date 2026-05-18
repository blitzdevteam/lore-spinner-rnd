<?php

declare(strict_types=1);

namespace App\ChaosMode;

/**
 * Whitelist + creative metadata for chaos-mode-enabled stories.
 *
 * Each entry binds:
 *   - the story `slug` (matches `stories.slug` in the DB)
 *   - the human-readable `title` for the start screen
 *   - the `protagonist` first name (used by the AGENCY HANDOFF prompt so the
 *     narrator says "What does Watson do?" not "What does Alice do?")
 *   - the `voice_partial` view name under `ai.agents.chaos.partials.*` — this
 *     is the world-building block that gives each story its own narrator voice
 *   - a short `tagline` for the story selector UI
 *
 * Per-session and per-event data is loaded dynamically from the DB
 * (StoryAdaptation + SessionAdaptation + Event tables). Nothing about the
 * dramatic spine, beat map, or events lives here.
 */
final class ChaosStoryConfig
{
    /**
     * @return array<int, array{slug:string, title:string, protagonist:string, voice_partial:string, tagline:string}>
     */
    public static function all(): array
    {
        return [
            [
                'slug'          => 'alices-adventures-in-wonderland',
                'title'         => "Alice's Adventures in Wonderland",
                'protagonist'   => 'Alice',
                'voice_partial' => 'ai.agents.chaos.partials.alice',
                'tagline'       => 'Carroll — full agency through Wonderland.',
            ],
            [
                'slug'          => 'the-adventure-of-the-speckled-band',
                'title'         => 'The Adventure of the Speckled Band',
                'protagonist'   => 'Watson',
                'voice_partial' => 'ai.agents.chaos.partials.sherlock',
                'tagline'       => 'Doyle — investigate beside Holmes.',
            ],
            [
                'slug'          => 'the-tell-tale-heart',
                'title'         => 'The Tell-Tale Heart',
                'protagonist'   => 'the Narrator',
                'voice_partial' => 'ai.agents.chaos.partials.telltale',
                'tagline'       => 'Poe — descend into the cracked mind.',
            ],
            [
                'slug'          => 'nocturne',
                'title'         => 'Nocturne',
                'protagonist'   => 'Akira',
                'voice_partial' => 'ai.agents.chaos.partials.nocturne',
                'tagline'       => 'Wittmer — vanish into Tokyo\'s shadow-house.',
            ],
            [
                'slug'          => 'anima-machina',
                'title'         => 'Anima Machina',
                'protagonist'   => 'Nora',
                'voice_partial' => 'ai.agents.chaos.partials.anima-machina',
                'tagline'       => 'Wittmer — dive grief in the neon archive.',
            ],
            [
                'slug'          => 'driftheart',
                'title'         => 'Driftheart',
                'protagonist'   => 'Kataria',
                'voice_partial' => 'ai.agents.chaos.partials.driftheart',
                'tagline'       => 'Wittmer — fall from the sky-villa into the Drift.',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function slugs(): array
    {
        return array_map(fn (array $row) => $row['slug'], self::all());
    }

    /**
     * @return array{slug:string, title:string, protagonist:string, voice_partial:string, tagline:string}|null
     */
    public static function find(string $slug): ?array
    {
        foreach (self::all() as $row) {
            if ($row['slug'] === $slug) {
                return $row;
            }
        }
        return null;
    }
}
