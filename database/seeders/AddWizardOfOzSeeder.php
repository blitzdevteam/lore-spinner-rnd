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
            'teaser'  => 'A Kansas girl is swept by a cyclone to the magical land of Oz. To find her way home she must follow the yellow brick road — but the Wizard who can help her may not be what he seems.',
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
