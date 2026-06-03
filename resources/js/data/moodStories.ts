export type MoodId = 'heartfelt' | 'adventurous' | 'mysterious' | 'epic' | 'whimsical';

export const MOOD_IDS = ['heartfelt', 'adventurous', 'mysterious', 'epic', 'whimsical'] as const satisfies readonly MoodId[];

export interface MoodStoryCatalog {
    top: string[];
    secondary: string[];
}

/** Canonical slugs for mood picks (aligned with story covers / library). */
export const MOOD_STORY_CATALOG: Record<MoodId, MoodStoryCatalog> = {
    heartfelt: {
        top: ['pride-and-prejudice', 'jane-eyre', 'romeo-and-juliet', 'pjs'],
        secondary: ['wasteland', 'frankenstein', 'the-wonderful-wizard-of-oz'],
    },
    adventurous: {
        top: [
            'the-wonderful-wizard-of-oz',
            'treasure-island',
            'leagues',
            'wasteland',
            'anima-machina',
        ],
        secondary: ['alice-in-wonderland', 'dracula', 'the-adventure-of-the-speckled-band'],
    },
    mysterious: {
        top: [
            'the-adventure-of-the-speckled-band',
            'nocturne',
            'dracula',
            'the-tell-tale-heart',
            'dr-jekyll-and-mr-hyde',
            'frankenstein',
        ],
        secondary: ['jane-eyre', 'anima-machina'],
    },
    epic: {
        top: ['anima-machina', 'dracula', 'frankenstein', 'pjs'],
        secondary: ['the-wonderful-wizard-of-oz', 'treasure-island', 'leagues', 'wasteland'],
    },
    whimsical: {
        top: ['alice-in-wonderland', 'the-wonderful-wizard-of-oz'],
        secondary: ['leagues', 'anima-machina'],
    },
};

/** API / legacy slug spellings → canonical mood slug. */
export const MOOD_STORY_SLUG_ALIASES: Record<string, string> = {
    '20000-leagues-under-the-sea': 'leagues',
    'alices-adventures-in-wonderland': 'alice-in-wonderland',
    'jekyll-and-hyde': 'dr-jekyll-and-mr-hyde',
    'the-strange-case-of-dr-jekyll-and-mr-hyde': 'dr-jekyll-and-mr-hyde',
};

export const MOOD_TOP_PICK_SLUGS: Record<MoodId, string[]> = Object.fromEntries(
    MOOD_IDS.map((id) => [id, MOOD_STORY_CATALOG[id].top]),
) as Record<MoodId, string[]>;

export const MOOD_SECONDARY_PICK_SLUGS: Record<MoodId, string[]> = Object.fromEntries(
    MOOD_IDS.map((id) => [id, MOOD_STORY_CATALOG[id].secondary]),
) as Record<MoodId, string[]>;

/** Slugs that belong to each mood (top + secondary), keyed by canonical slug. */
export const STORY_MOODS_BY_SLUG: Record<string, MoodId[]> = (() => {
    const bySlug: Record<string, MoodId[]> = {};

    for (const mood of MOOD_IDS) {
        const slugs = [...MOOD_STORY_CATALOG[mood].top, ...MOOD_STORY_CATALOG[mood].secondary];
        for (const slug of slugs) {
            if (!bySlug[slug]) {
                bySlug[slug] = [];
            }
            if (!bySlug[slug].includes(mood)) {
                bySlug[slug].push(mood);
            }
        }
    }

    return bySlug;
})();

export function canonicalMoodStorySlug(slug: string): string {
    return MOOD_STORY_SLUG_ALIASES[slug] ?? slug;
}

export function getMoodTopPickSlugs(mood: MoodId): string[] {
    return MOOD_TOP_PICK_SLUGS[mood] ?? [];
}

export function getMoodSecondaryPickSlugs(mood: MoodId): string[] {
    return MOOD_SECONDARY_PICK_SLUGS[mood] ?? [];
}

export function getMoodStorySlugs(mood: MoodId): string[] {
    return [...MOOD_STORY_CATALOG[mood].top, ...MOOD_STORY_CATALOG[mood].secondary];
}

export function storyMatchesMood(storySlug: string, mood: MoodId): boolean {
    const canonical = canonicalMoodStorySlug(storySlug);
    const moods = STORY_MOODS_BY_SLUG[canonical];
    return moods?.includes(mood) ?? false;
}
