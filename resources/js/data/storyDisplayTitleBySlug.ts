/** Marketing / detail-page titles that differ from the canonical DB title. */
const DISPLAY_TITLES_BY_SLUG: Record<string, string> = {
    'the-adventure-of-the-speckled-band': 'Sherlock Holmes in The Speckled Band',
};

export function resolveStoryDisplayTitle(slug: string, title: string): string {
    return DISPLAY_TITLES_BY_SLUG[slug] ?? title;
}
