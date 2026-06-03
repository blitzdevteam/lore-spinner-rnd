/** Published stories excluded from library grids and public story counts. */
export const HIDDEN_LIBRARY_STORY_SLUGS = ['driftheart', 'the-snow-queen'] as const;

export function isHiddenLibraryStory(slug: string): boolean {
    return (HIDDEN_LIBRARY_STORY_SLUGS as readonly string[]).includes(slug);
}

export function filterVisibleLibraryStories<T extends { slug: string }>(stories: T[]): T[] {
    return stories.filter((story) => !isHiddenLibraryStory(story.slug));
}
