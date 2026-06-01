/**
 * Themes + branch copy for Library / featured hover popups (matches home Top Stories).
 */
export const STORY_HOVER_META_BY_SLUG: Record<string, { themes: string[]; branches: string | null }> = {
    'the-tell-tale-heart': {
        themes: ['Madness', 'Guilt', 'Obsession'],
        branches: '123,456',
    },
    'the-adventure-of-the-speckled-band': {
        themes: ['Mystery', 'Deduction', 'Betrayal'],
        branches: '142,857',
    },
    'the-masque-of-the-red-death': {
        themes: ['Mortality', 'Isolation', 'Decay'],
        branches: '98,765',
    },
    'the-wonderful-wizard-of-oz': {
        themes: ['Courage', 'Home', 'Illusion'],
        branches: '156,789',
    },
    'anima-machina': {
        themes: ['Destiny', 'Courage', 'Control'],
        branches: null,
    },
    'jane-eyre': {
        themes: ['Love', 'Duty', 'Secrets'],
        branches: null,
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
};
