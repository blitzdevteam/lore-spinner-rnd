# Multi-Provider Image Gallery

## Problem

Currently each story/chapter/creator can only have one cover/banner/avatar at a time (`singleFile()` constraint). When switching between image generation providers (OpenAI, Gemini, etc.), the old images get replaced. There's no way to compare outputs or keep favorites from different providers.

## Idea

Let creators keep multiple AI-generated image variants from different providers and pick the active one from the Filament dashboard.

## Approach

1. **Remove `singleFile()` constraint** on cover/banner/gallery media collections
2. **Tag each media item** with the provider that generated it (e.g. custom property `provider: openai`, `provider: gemini`) using Spatie's `withCustomProperties()`
3. **Add an "active" flag** — either a `is_active` custom property on the media item, or a `active_cover_id` / `active_banner_id` column on the Story model
4. **Update `getFirstMediaUrl()` calls** site-wide to prefer the active image, falling back to the most recent
5. **Filament dashboard UI** — add an image gallery panel on the Story/Chapter edit pages where creators can:
   - See all generated variants side-by-side with provider labels
   - Click to set one as active
   - Trigger regeneration with a specific provider
   - Delete variants they don't want

## Benefits

- Compare quality across providers without losing work
- Creators have full control over their story's visual identity
- Easy A/B testing of different AI art styles
