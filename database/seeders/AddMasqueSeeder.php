<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Story\StoryRatingEnum;

/**
 * Seed "The Masque of the Red Death" (Poe).
 *
 * Usage: php artisan db:seed --class=AddMasqueSeeder --force
 */
final class AddMasqueSeeder extends AddSingleStorySeeder
{
    protected function getStoryConfig(): array
    {
        return [
            'title'      => 'The Masque of the Red Death',
            'slug'       => 'the-masque-of-the-red-death',
            'category'   => 'Horror',
            'script'     => 'The Masque of the Red Death_script.txt',
            'source_pdf' => 'RnD/The Masque of the Red Death copy.pdf',
            'teaser'     => 'A prince seals his revellers inside a great abbey to escape a plague. But at the height of the masquerade, a masked stranger moves through every room — and no mortal hand can stop what walks beneath the mask.',
            'rating'     => StoryRatingEnum::MATURE->value,
            'opening'    => null,
            'creator'    => [
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
