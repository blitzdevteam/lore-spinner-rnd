import animaCover from '@/assets/featured/anima.jpg';
import janeEyreCover from '@/assets/featured/janeEyre.png';
import redDeathCover from '@/assets/featured/redDeath.png';
import sherlockCover from '@/assets/featured/sherlock.png';
import tellTaleCover from '@/assets/featured/tale-tale.png';
import wizardOzCover from '@/assets/featured/wizardoz.jpg';

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
