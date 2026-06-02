import janeComingCover from '@/assets/commingSoon/jane-comming.png';
import frankensteinComingCover from '@/assets/commingSoon/frankstein-comming.png';
import drjComingCover from '@/assets/commingSoon/drj-comming.png';
import underseaComingCover from '@/assets/commingSoon/undersea-comming.JPG';
import wastelandComingCover from '@/assets/commingSoon/wasteland-comming.JPG';
import romeoComingCover from '@/assets/commingSoon/romeo-comming.png';
import pjComingCover from '@/assets/commingSoon/pj-comming.JPG';
import aliceCover from '@/assets/featured/alice.png';
import animaCover from '@/assets/featured/anima.png';
import draculaCover from '@/assets/featured/dracula.png';
import jekyllCover from '@/assets/featured/jekyll.png';
import nocturneCover from '@/assets/featured/nocturne.png';
import pridePrejudiceCover from '@/assets/featured/Pride-prejudice.png';
import redDeathCover from '@/assets/featured/redDeath.png';
import treasureCover from '@/assets/featured/treasure.png';
import sherlockCover from '@/assets/featured/sherlock.png';
import tellTaleCover from '@/assets/newStories/Tell Tale 5_3 landscape.png';
import wizardOzCover from '@/assets/featured/wizardoz.jpg';

/** Portrait / card covers aligned with home — override API media when present. */
export const STORY_COVER_BY_SLUG: Record<string, string> = {
    // Featured Worlds
    nocturne: nocturneCover,
    'the-masque-of-the-red-death': redDeathCover,
    'anima-machina': animaCover,
    'alice-in-wonderland': aliceCover,
    dracula: draculaCover,
    'pride-and-prejudice': pridePrejudiceCover,
    'treasure-island': treasureCover,
    // New Stories
    'the-adventure-of-the-speckled-band': sherlockCover,
    'the-wonderful-wizard-of-oz': wizardOzCover,
    'the-tell-tale-heart': tellTaleCover,
    // Explore by Mood / mood library picks
    'jekyll-and-hyde': jekyllCover,
    // Coming Soon
    'jane-eyre': janeComingCover,
    frankenstein: frankensteinComingCover,
    'dr-jekyll-and-mr-hyde': drjComingCover,
    leagues: underseaComingCover,
    wasteland: wastelandComingCover,
    'romeo-and-juliet': romeoComingCover,
    pjs: pjComingCover,
};

export function resolveStoryCover(slug: string, cover?: string | null): string {
    const override = STORY_COVER_BY_SLUG[slug];
    if (override) return override;

    const trimmed = cover?.trim();
    if (trimmed) return trimmed;

    return '';
}
