export interface StoryPalette {
    primary: string;
    secondary: string;
    accent: string;
}

export interface CachedStoryPalette {
    coverUrl: string;
    palette: StoryPalette;
}
