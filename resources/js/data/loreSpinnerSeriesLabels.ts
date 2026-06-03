import loreSpinnerClassicAvatar from '@/assets/avatars/lore-spinner-classic.svg';
import type { CreatorInterface } from '@/types';

export const LORE_SPINNER_CLASSIC = 'A LoreSpinner classic adaptation';
export const LORE_SPINNER_CLASSIC_AVATAR = loreSpinnerClassicAvatar;
export const LORE_SPINNER_ORIGINAL = 'A LoreSpinner Original';

const CLASSICS_CREATOR_EMAIL = 'classics@lorespinner.com';
const CLASSICS_CREATOR_USERNAME = 'theclassicsunbound';

const CLASSICS_LABEL_VARIANTS = new Set([
    'the classics, unbound',
    'the classics unbound',
]);

function normalizeCreatorLabel(value: string): string {
    return value.trim().toLowerCase().replace(/\s+/g, ' ');
}

type CreatorLike = Pick<CreatorInterface, 'first_name' | 'last_name' | 'full_name' | 'username' | 'email'> | null | undefined;

export function isClassicsUnboundCreator(creator: CreatorLike): boolean {
    if (!creator) {
        return false;
    }

    if (creator.email?.toLowerCase() === CLASSICS_CREATOR_EMAIL) {
        return true;
    }

    if (creator.username?.toLowerCase() === CLASSICS_CREATOR_USERNAME) {
        return true;
    }

    const candidates = [
        creator.full_name,
        creator.first_name,
        [creator.first_name, creator.last_name].filter(Boolean).join(' '),
    ].filter((value): value is string => Boolean(value?.trim()));

    return candidates.some((name) => CLASSICS_LABEL_VARIANTS.has(normalizeCreatorLabel(name)));
}

/** Display label for story detail author row and cover credit. */
export function formatCreatorDisplayName(rawName: string, creator?: CreatorLike): string {
    if (isClassicsUnboundCreator(creator) || CLASSICS_LABEL_VARIANTS.has(normalizeCreatorLabel(rawName))) {
        return LORE_SPINNER_CLASSIC;
    }

    return rawName;
}

/** Avatar for classics series row — simple brand mark, readable at small sizes. */
export function resolveClassicsCreatorAvatar(creator?: CreatorLike, avatarUrl?: string | null): string | null {
    if (isClassicsUnboundCreator(creator)) {
        return LORE_SPINNER_CLASSIC_AVATAR;
    }

    const trimmed = avatarUrl?.trim();
    return trimmed || null;
}
