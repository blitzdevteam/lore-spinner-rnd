<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Story\StoryRatingEnum;

/**
 * Seed "The Wonderful Wizard of Oz" (Baum).
 *
 * Usage: php artisan db:seed --class=AddWizardOfOzSeeder --force
 */
final class AddWizardOfOzSeeder extends AddSingleStorySeeder
{
    protected function getStoryConfig(): array
    {
        return [
            'title'   => 'The Wonderful Wizard of Oz',
            'slug'    => 'the-wonderful-wizard-of-oz',
            'category' => 'Fantasy Adventure',
            'script'  => 'THE WONDERFUL WIZARD OF OZ_script.txt',
            'teaser'  => 'A storm carries you into the magical land of Oz, where witches whisper, lions tremble, and every step down the Yellow Brick Road changes who you are becoming.',
            'rating'  => StoryRatingEnum::EVERYONE->value,
            'opening' => null,
            'creator' => [
                'first_name' => 'The Classics, Unbound',
                'last_name'  => '',
                'username'   => 'theclassicsunbound',
                'email'      => 'classics@lorespinner.com',
                'bio'        => "Enter the world's most iconic classic stories—now immersive, interactive adventures where your choices reshape timeless legends.",
                'avatar'     => 'THE CLASSICS, UNBOUND - PROFILE PIC.jpg',
            ],
        ];
    }
}
