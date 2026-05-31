import heartImg from '@/assets/mood/Heart.svg';
import mountainsImg from '@/assets/mood/Mountains.svg';
import eyeImg from '@/assets/mood/Eye.svg';
import swordImg from '@/assets/mood/Sword.svg';
import spiralImg from '@/assets/mood/Spiral.svg';
import { MOOD_BANNER_CONFIGS, MOOD_IDS, type MoodId } from '@/data/moodBanners';

export interface MoodCardConfig {
    id: MoodId;
    label: string;
    icon: string;
    labelColor: string;
    /** CSS class suffix for mood-specific glow styles, e.g. `heartfelt`. */
    variant: MoodId;
    rotateIcon?: boolean;
}

export const MOOD_CARD_CONFIGS: MoodCardConfig[] = [
    {
        id: 'heartfelt',
        label: MOOD_BANNER_CONFIGS.heartfelt.label,
        icon: heartImg,
        labelColor: '#c93434',
        variant: 'heartfelt',
    },
    {
        id: 'adventurous',
        label: MOOD_BANNER_CONFIGS.adventurous.label,
        icon: mountainsImg,
        labelColor: '#ecc863',
        variant: 'adventurous',
    },
    {
        id: 'mysterious',
        label: MOOD_BANNER_CONFIGS.mysterious.label,
        icon: eyeImg,
        labelColor: '#62e8db',
        variant: 'mysterious',
    },
    {
        id: 'epic',
        label: MOOD_BANNER_CONFIGS.epic.label,
        icon: swordImg,
        labelColor: '#58d9a1',
        variant: 'epic',
        rotateIcon: true,
    },
    {
        id: 'whimsical',
        label: MOOD_BANNER_CONFIGS.whimsical.label,
        icon: spiralImg,
        labelColor: '#a979c2',
        variant: 'whimsical',
    },
];

/** Curated top picks per mood — slugs must exist in the library pool. */
export const MOOD_TOP_PICK_SLUGS: Record<MoodId, string[]> = {
    heartfelt: ['anima-machina', 'jekyll-and-hyde', 'the-wonderful-wizard-of-oz'],
    adventurous: ['alice-in-wonderland', 'the-wonderful-wizard-of-oz', 'anima-machina'],
    mysterious: ['nocturne', 'jekyll-and-hyde', 'anima-machina'],
    epic: ['anima-machina', 'jekyll-and-hyde', 'the-wonderful-wizard-of-oz'],
    whimsical: ['alice-in-wonderland', 'nocturne', 'the-wonderful-wizard-of-oz'],
};

export function getMoodTopPickSlugs(mood: MoodId): string[] {
    return MOOD_TOP_PICK_SLUGS[mood] ?? [];
}

export { MOOD_IDS };
