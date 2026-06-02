<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Chapter\ChapterStatusEnum;
use App\Enums\Story\StoryRatingEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Jobs\Chapter\ChapterExtractorJob;
use App\Jobs\Event\EventExtractorJob;
use App\Jobs\Story\StoryOpeningGeneratorJob;
use App\Jobs\Story\SystemPromptGeneratorJob;
use App\Models\Category;
use App\Models\Creator;
use App\Models\Story;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;
use Throwable;

final class StorySeeder extends Seeder
{
    private const int MAX_RETRIES = 3;

    private const int RETRY_DELAY_SECONDS = 10;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $previousQueue = config('queue.default');
        config(['queue.default' => 'sync']);

        try {
            foreach ($this->getStories() as $storyData) {
                $scriptPath = database_path('stories/'.$storyData['script']);

                if (! File::exists($scriptPath) && isset($storyData['source_pdf'])) {
                    $pdfPath = database_path('stories/'.$storyData['source_pdf']);

                    if (File::exists($pdfPath)) {
                        $this->command->info("  -> Converting PDF: {$storyData['source_pdf']}");
                        $this->convertPdf($pdfPath, $scriptPath);
                    }
                }

                if (! File::exists($scriptPath)) {
                    $this->command->warn("Script not found for \"{$storyData['title']}\": {$storyData['script']} — skipping.");

                    continue;
                }

                $creator = Creator::where('email', $storyData['creator_email'])->first();
                $category = Category::where('title', $storyData['category'])->first();

                if (! $creator || ! $category) {
                    $this->command->warn("Creator or category not found for \"{$storyData['title']}\" — skipping.");

                    continue;
                }

                $this->command->info("Processing: {$storyData['title']}");

                $story = Story::create([
                    'category_id' => $category->id,
                    'creator_id' => $creator->id,
                    'title' => $storyData['title'],
                    'slug' => Str::slug($storyData['title']),
                    'teaser' => $storyData['teaser'],
                    'opening' => $storyData['opening'] ?? null,
                    'status' => StoryStatusEnum::AWAITING_EXTRACTING_CHAPTERS_REQUEST->value,
                    'rating' => $storyData['rating'],
                    'published_at' => now()->subDays(random_int(1, 60)),
                ]);

                $story->addMedia($scriptPath)
                    ->preservingOriginal()
                    ->toMediaCollection('script');

                $this->command->info('  -> Extracting chapters...');
                $this->withRetry(fn () => ChapterExtractorJob::dispatchSync($story->fresh()));

                $story->refresh();
                $this->command->info("  -> {$story->chapters()->count()} chapters extracted.");

                foreach ($story->chapters()->orderBy('position')->get() as $chapter) {
                    $this->command->info("  -> Extracting events for: {$chapter->title}");
                    $this->withRetry(function () use ($chapter): void {
                        $chapter->events()->delete();
                        EventExtractorJob::dispatchSync($chapter->fresh());
                    });
                    $chapter->refresh();

                    if ($chapter->events()->count() === 0 && $chapter->status !== ChapterStatusEnum::READY_TO_PLAY) {
                        $chapter->update(['status' => ChapterStatusEnum::READY_TO_PLAY]);
                    }

                    $this->command->info("     {$chapter->events()->count()} events extracted.");
                }

                $this->command->info('  -> Generating system prompt...');
                $this->withRetry(fn () => SystemPromptGeneratorJob::dispatchSync($story));

                $this->command->info('  -> Generating cinematic opening...');
                $this->withRetry(fn () => StoryOpeningGeneratorJob::dispatchSync($story->fresh()));

                $story->update([
                    'status' => StoryStatusEnum::PUBLISHED->value,
                ]);

                $this->command->info('  -> Published.');
                $this->command->newLine();
            }
        } finally {
            config(['queue.default' => $previousQueue]);
        }
    }

    /**
     * Convert a PDF screenplay to a cleaned .txt file.
     */
    private function convertPdf(string $pdfPath, string $txtPath): void
    {
        $parser = new Parser;
        $pdf = $parser->parseFile($pdfPath);
        $text = $pdf->getText();

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/^\d+\.?\s*$/m', '', $text);
        $text = preg_replace("/\n{4,}/", "\n\n\n", $text);
        $text = implode("\n", array_map('rtrim', explode("\n", $text)));
        $text = mb_trim($text)."\n";

        File::put($txtPath, $text);

        $this->command->info('     -> Saved: '.basename($txtPath).' ('.mb_strlen($text).' bytes)');
    }

    /**
     * Retry a closure with exponential backoff for transient API failures.
     */
    private function withRetry(callable $callback): void
    {
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $callback();

                return;
            } catch (Throwable $e) {
                if ($attempt === self::MAX_RETRIES) {
                    throw $e;
                }

                $delay = self::RETRY_DELAY_SECONDS * $attempt;
                $this->command->warn("     Attempt {$attempt} failed: {$e->getMessage()}");
                $this->command->warn("     Retrying in {$delay}s...");
                sleep($delay);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getStories(): array
    {
        return [
            // ── Thomas Wittmer ──────────────────────────────────────────
            [
                'creator_email' => 'thomas@lorespinner.com',
                'category' => 'Action Thriller',
                'title' => 'Shatterfall',
                'script' => 'SHATTERFALL_script.txt',
                'teaser' => 'In a post-collapse city ruled by brutal dogma and industrial ruin, a war-scarred assassin returns from exile to exact ritual vengeance on the six betrayers who destroyed the only person who ever made him human.',
                'opening' => 'A city in ruin. Smoke weaves through hollow towers. The air hums with distant gunfire and the moan of broken wind. Ash falls. Slow. Relentless. You are Ravi "Razor" Shan — built from scar tissue and silence. Eyes forward. Movements surgical. A blade disguised as a man. In the near distance, Ash Covenant Enforcers shake down a family in a crumbling alley. Screams muffled. Boots striking bone. A dog barks from a rooftop. You lock eyes with it. The dog goes silent. What do you do?',
                'rating' => StoryRatingEnum::MATURE->value,
            ],
            [
                'creator_email' => 'thomas@lorespinner.com',
                'category' => 'Science Fiction',
                'title' => 'Anima Machina',
                'script' => 'ANIMA MACHINA VER 5_script.txt',
                'teaser' => 'When a sentient AI threatens to overwrite all human grief with synthetic perfection, a haunted memory diver races against the clock to stop the digital reset.',
                'opening' => 'Rain stitches neon into the dark. The city of Neo-Vault is a cathedral of glass and wire, every tower a sermon to order. Holo-billboards pulse: "ONE PARTNER. ONE MIND. NO MORE PAIN." The NEURAL RESET countdown hovers in the sky — 49 hours remain. Below, faces glow with false joy as citizens replay their happiest memories in loops. You are a Memory Diver. Your wrist is looped with a child\'s worn ballet slipper — your anchor. Your HUD bleeds glyph-static: ARCHIVE FAILURE. MEMORY OVERWRITE IN 47:59:00. What do you do?',
                'rating' => StoryRatingEnum::MATURE->value,
            ],
            [
                'creator_email' => 'thomas@lorespinner.com',
                'category' => 'Fantasy Adventure',
                'title' => 'Driftheart',
                'script' => 'DRIFTHEART_script.txt',
                'teaser' => 'After stealing a mystical shard and fleeing her privileged life, a rebellious young woman must navigate dangerous alliances and ancient Vault mysteries before powerful forces use the shards to reshape the universe.',
                'opening' => 'A vast emptiness. Light fractures ripple outward, each wave bending the black sea of matter beneath. You are Kataria Darana, 17, sharp-eyed with fire beneath the polish. You lean against the observation balcony of your family\'s sky-villa high above Absalom Station. Below: a thousand meters of vacuum and silence. Behind you: imported flora, auto-tuned symphonics, floating crystal light fixtures. They say the void humbles you. It never did. It just reminds you how much everyone else is pretending. What do you do?',
                'rating' => StoryRatingEnum::TEEN->value,
            ],
            [
                'creator_email' => 'thomas@lorespinner.com',
                'category' => 'Action Thriller',
                'title' => 'Backchannel: Dead Air',
                'script' => 'BACKCHANNEL DEAD AIR_script.txt',
                'teaser' => "When a livestream justice crew's latest sting is hijacked and turned into a real-time public bounty hunt, their brilliant leader must keep her team alive long enough to expose the architect behind it.",
                'opening' => null,
                'rating' => StoryRatingEnum::MATURE->value,
            ],
            [
                'creator_email' => 'thomas@lorespinner.com',
                'category' => 'Dark Fantasy',
                'title' => 'Bound & Broken',
                'script' => 'BOUND & BROKEN_script.txt',
                'teaser' => 'When three grieving siblings are pulled into a sentient book hidden inside a mysterious carnival, they are scattered across mythic realms and must resist the roles written for them before the story erases who they are forever.',
                'opening' => 'Ink drifts through the air like ash. Letters fall apart before they land. The ground shifts, unfinished, as if the world forgot what it was meant to be. You are Leo London, 13, haunted before his time. You blink. Your hands are smudged — not dirty, blurred. The edges of your fingers soften, as if the world is losing interest in keeping you whole. A shape forms ahead — tall, draped in stitched pages. Its face never settles. It speaks: "Say your name." You open your mouth. Nothing comes out. What do you do?',
                'rating' => StoryRatingEnum::TEEN->value,
            ],

            // ── Hilton Williams ─────────────────────────────────────────
            [
                'creator_email' => 'hilton@lorespinner.com',
                'category' => 'Horror',
                'title' => 'The Hollowing',
                'script' => 'THE HOLLOWING_script.txt',
                'teaser' => "When a group of teens disturb a sacred burial mound during their small town's myth-based festival, they awaken an ancient entity that feeds on blood memory and threatens to hollow their community from within.",
                'opening' => 'Razor-sharp wind slices through dark pines. A weathered trailer hunches at the forest\'s edge, a single window glowing with fragile candlelight. Inside, you are Kai Blackdeer, 7, Ho-Chunk, huddled in a thick blanket with your sketchbook pressed against your knees. Your grandfather Luther carves a walking stick with surgical precision. "Some places exist outside time," he says. "You feel them before seeing them. When your hair lifts and your chest tightens... that\'s not fear. That\'s this place remembering what happened here — and your body hearing it." He looks at you. "And it doesn\'t forget the ones who woke it." What do you do?',
                'rating' => StoryRatingEnum::YOUNG_ADULT->value,
            ],
            [
                'creator_email' => 'hilton@lorespinner.com',
                'category' => 'Thriller',
                'title' => 'Nocturne',
                'script' => 'NOCTURNE_script.txt',
                'teaser' => 'Beyond the rain-soaked glass walls of Nocturne, Akira finds herself trapped inside a system where identities are rewritten and nothing is quite as voluntary as it seems.',
                'opening' => 'A sparse, modern Tokyo high-rise. Concrete, teak, and tension. A single table set for two. You are Akira, 26. Your lipstick is too dark for the room. Across from you sits Professor Shin — married, respected, calm in the way men are when they\'ve never been truly cornered. He refills your sake without asking. "You should learn to keep secrets if you want to stay important," he says with a smile like advice, not a blade. But you\'ve already sent the files. There\'s no going back. What do you do?',
                'rating' => StoryRatingEnum::MATURE->value,
            ],
            [
                'creator_email' => 'hilton@lorespinner.com',
                'category' => 'Horror',
                'title' => 'Session Zero',
                'script' => 'SESSION ZERO_script.txt',
                'teaser' => 'When a group of friends gather for one final D&D livestream in an allegedly haunted house, they become trapped in a deadly supernatural game that feeds on trauma, confession, and truth.',
                'opening' => 'An old hunting lodge looms in the tangled California forest. Wind slices through overgrown brush. A rusted gate blocks the gravel drive, a "NO TRESPASSING" sign flapping against iron bars. A black crow perches on the gatepost, watching. You are Billy Cruz, 29 — hoodie over a paranormal YouTube tee, long hair tied back. You\'ve rigged the house with cameras, fog machines, EMF meters, and a satellite uplink for Wesley\'s livestream. Everything is set. You tap "GO LIVE." A low hum stirs. Lights flicker — off-color, wrong somehow. What do you do?',
                'rating' => StoryRatingEnum::YOUNG_ADULT->value,
            ],

            // ── Rand Soares ─────────────────────────────────────────────
            [
                'creator_email' => 'rand@lorespinner.com',
                'category' => 'Historical Adventure',
                'title' => "Hemingway's War",
                'script' => 'HEMINGWAYS WAR_script.txt',
                'source_pdf' => "Rand Soares - STORIES/HEMINGWAY'S WAR/Hemingway's War 5-22-2025.pdf",
                'teaser' => 'During World War II, Ernest Hemingway defies his role as a war correspondent, builds his own band of irregular fighters, and charges toward Paris in a reckless bid to liberate the Ritz before the Allied Army.',
                'opening' => null,
                'rating' => StoryRatingEnum::MATURE->value,
            ],
            [
                'creator_email' => 'rand@lorespinner.com',
                'category' => 'Fantasy Adventure',
                'title' => 'High Stakes',
                'script' => 'HIGH STAKES_script.txt',
                'source_pdf' => 'Rand Soares - STORIES/HIGH STAKES/High Stakes.pdf',
                'teaser' => 'A fearless Wall Street thrill seeker enters the secret interdimensional game he has spent years trying to conquer, only to discover his supposedly dead best friend is alive inside a deadly world no one was ever meant to escape.',
                'opening' => null,
                'rating' => StoryRatingEnum::YOUNG_ADULT->value,
            ],
            [
                'creator_email' => 'rand@lorespinner.com',
                'category' => 'Adventure',
                'title' => 'Pieces of Eight',
                'script' => 'PIECES OF EIGHT_script.txt',
                'source_pdf' => 'Rand Soares - STORIES/PIECES OF EIGHT/Pieces of Eight.pdf',
                'teaser' => 'A debt-ridden Florida Keys dive-shop owner and his son finally locate a legendary treasure ship, only to become targets of a brutal modern pirate king determined to steal the fortune for himself.',
                'opening' => null,
                'rating' => StoryRatingEnum::YOUNG_ADULT->value,
            ],
            [
                'creator_email' => 'rand@lorespinner.com',
                'category' => 'Science Fiction',
                'title' => 'Time Machine',
                'script' => 'TIME MACHINE_script.txt',
                'source_pdf' => 'Rand Soares - STORIES/TIME MACHINE/Time Machine.pdf',
                'teaser' => 'After accidentally creating a working time machine for his college thesis, a disgraced young physicist is recruited by a visionary billionaire to build a full-scale version before powerful forces seize control of the past.',
                'opening' => null,
                'rating' => StoryRatingEnum::TEEN->value,
            ],

            // ── FREEP1 ─────────────────────────────────────────────────
            [
                'creator_email' => 'freep@lorespinner.com',
                'category' => 'Techno-Thriller',
                'title' => 'B.U.G.S.',
                'script' => 'BUGS_script.txt',
                'source_pdf' => 'FREEP1 - STORIES/BUGS/BUGS.pdf',
                'teaser' => 'After taking a Homeland Security job tracing an attack on the national power grid, a renegade team of underground operatives uncovers a nuclear smuggling plot tied to a far larger shadow conspiracy.',
                'opening' => null,
                'rating' => StoryRatingEnum::MATURE->value,
            ],
            [
                'creator_email' => 'freep@lorespinner.com',
                'category' => 'Supernatural Thriller',
                'title' => 'Dream Police',
                'script' => 'DREAM POLICE_script.txt',
                'source_pdf' => 'FREEP1 - STORIES/CROSSOVERS : DREAM POLICE/Dream Police-2.pdf',
                'teaser' => 'When a black-ops agent who polices the dream world loses his partner to an impossible killer, he must hunt a rogue scientist weaponizing dreams before the boundary between sleep and reality collapses.',
                'opening' => null,
                'rating' => StoryRatingEnum::MATURE->value,
            ],
            [
                'creator_email' => 'freep@lorespinner.com',
                'category' => 'Supernatural Thriller',
                'title' => 'Necropolis',
                'script' => 'NECROPOLIS_script.txt',
                'source_pdf' => 'FREEP1 - STORIES/NECROPOLIS/Necropolis.pdf',
                'teaser' => 'After dying in a catastrophic train bombing, a skeptical federal investigator awakens as a legendary Shadow Walker and is thrust into a hidden war between angels and demons over the gates of Hell.',
                'opening' => null,
                'rating' => StoryRatingEnum::MATURE->value,
            ],
            [
                'creator_email' => 'freep@lorespinner.com',
                'category' => 'Military Drama',
                'title' => "PJ's",
                'script' => 'PJS_script.txt',
                'source_pdf' => "FREEP1 - STORIES/PJ'S/PJ's.pdf",
                'teaser' => "A team of elite Air Force PJs discover that the hardest battlefield may be the one where there's no enemy to shoot, only lives to save and ghosts to outrun.",
                'opening' => null,
                'rating' => StoryRatingEnum::MATURE->value,
            ],
            [
                'creator_email' => 'freep@lorespinner.com',
                'category' => 'Dystopian',
                'title' => 'Wasteland',
                'script' => 'WASTELAND_script.txt',
                'source_pdf' => 'FREEP1 - STORIES/WASTELAND/Wasteland.pdf',
                'teaser' => "Abandoned in a desert built from humanity's castoffs, an engineer must decide whether to escape or help the people that the world chose to forget.",
                'opening' => null,
                'rating' => StoryRatingEnum::YOUNG_ADULT->value,
            ],
        ];
    }
}
