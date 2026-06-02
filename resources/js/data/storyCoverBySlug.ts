import animaCover from '@/assets/featured/anima.png';
import janeEyreCover from '@/assets/featured/janeEyre.png';
import redDeathCover from '@/assets/featured/redDeath.png';
import sherlockCover from '@/assets/newStories/sherlock-new.png';
import tellTaleCover from '@/assets/newStories/Tell Tale 5_3 landscape.png';
import wizardOzCover from '@/assets/newStories/Oz landscape titled.png';

/** Portrait covers aligned with home Top Stories — used when API media is missing. */
export const STORY_COVER_BY_SLUG: Record<string, string> = {
    'the-tell-tale-heart': tellTaleCover,
    'the-adventure-of-the-speckled-band': sherlockCover,
    'the-masque-of-the-red-death': redDeathCover,
    'the-wonderful-wizard-of-oz': wizardOzCover,
    'anima-machina': animaCover,
    'jane-eyre': janeEyreCover,
};

export function resolveStoryCover(slug: string, cover?: string | null): string {
    const trimmed = cover?.trim();
    if (trimmed) return trimmed;
    return STORY_COVER_BY_SLUG[slug] ?? '';
}
