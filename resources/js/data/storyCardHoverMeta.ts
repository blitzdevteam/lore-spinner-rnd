/**
 * Themes + branch copy for Library / featured hover popups (matches home Featured Worlds).
 */
export const STORY_HOVER_META_BY_SLUG: Record<string, { themes: string[]; branches: string | null }> = {
    'anima-machina': {
        themes: ['Destiny', 'Courage', 'Control'],
        branches: '8,347',
    },
    'alice-in-wonderland': {
        themes: ['Wonder', 'Identity', 'Logic'],
        branches: null,
    },
    nocturne: {
        themes: ['Mystery', 'Music', 'Sacrifice'],
        branches: null,
    },
    'jekyll-and-hyde': {
        themes: ['Duality', 'Power', 'Morality'],
        branches: null,
    },
    'the-wonderful-wizard-of-oz': {
        themes: ['Courage', 'Home', 'Illusion'],
        branches: null,
    },
};
