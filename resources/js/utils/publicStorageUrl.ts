/** Default profile mark committed under storage/app/public/profile.svg */
export const DEFAULT_PROFILE_AVATAR = '/storage/profile.svg';

/**
 * Normalize avatar/cover URLs to same-origin /storage/ paths so they keep working
 * when APP_URL differs from the browser host (common in local dev and deploys).
 */
export function resolvePublicStorageUrl(url?: string | null): string {
    const trimmed = url?.trim();
    if (!trimmed) {
        return DEFAULT_PROFILE_AVATAR;
    }

    if (trimmed.startsWith('/storage/')) {
        return trimmed;
    }

    try {
        const path = new URL(trimmed, window.location.origin).pathname;
        if (path.startsWith('/storage/')) {
            return path;
        }
    } catch {
        // ignore invalid URLs
    }

    return trimmed.startsWith('/') ? trimmed : DEFAULT_PROFILE_AVATAR;
}
