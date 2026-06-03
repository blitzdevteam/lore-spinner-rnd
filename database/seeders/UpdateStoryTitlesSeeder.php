<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Story;
use Illuminate\Database\Seeder;

/**
 * Update story display titles without re-running extraction pipelines.
 *
 * Usage: php artisan db:seed --class=UpdateStoryTitlesSeeder --force
 */
final class UpdateStoryTitlesSeeder extends Seeder
{
    /**
     * @return array<string, string>
     */
    private function titlesBySlug(): array
    {
        return [
            'the-adventure-of-the-speckled-band' => 'Sherlock Holmes in The Speckled Band',
        ];
    }

    public function run(): void
    {
        foreach ($this->titlesBySlug() as $slug => $title) {
            $updated = Story::query()->where('slug', $slug)->update(['title' => $title]);

            if ($updated > 0) {
                $this->command->info("Updated title: {$slug}");
            }
        }

        $this->command->info('Story title update complete.');
    }
}
