/**
 * Aurora background palettes for the main gameplay screen.
 *
 * Each playable story has a hand-picked colour identity. The four launch
 * titles get their own palettes; any other slug falls back to the brand
 * amber default so aurora is always on regardless of story.
 *
 * buildAuroraProps(slug) returns ready-to-spread props for <AuroraBackground>.
 */

const BRAND_TIFFANY = '#08cee6';
const BRAND_AMBER   = '#e5ad53';
const BRAND_BLACK   = '#050409';

const PALETTES: Record<string, { from: string; via: string; accent: string }> = {
    // ── Four launch title palettes ─────────────────────────────────────────
    'the-adventure-of-the-speckled-band': { from: '#7a1a1a', via: '#0d0505', accent: '#f87171' }, // Sherlock → Tell-Tale crimson
    'the-wonderful-wizard-of-oz':         { from: '#1a3a6a', via: '#050d15', accent: '#67e8f9' }, // Oz → Driftheart blue-teal
    'the-tell-tale-heart':                { from: '#3b4a8f', via: '#1a1a2e', accent: '#a78bfa' }, // Tell-Tale → Alice indigo/violet
    'the-masque-of-the-red-death':        { from: '#5c3a22', via: '#0f0a06', accent: '#c4784a' }, // Masque → deep earthy umber
    // ── Brand amber fallback for all other stories ─────────────────────────
    __default__:                          { from: '#3a2800', via: '#0f0b00', accent: '#e5ad53' },
};

function mixHex(a: string, b: string, t: number): string {
    const parse = (h: string) => {
        const n = h.replace('#', '');
        const v = n.length === 3 ? n.split('').map((c) => c + c).join('') : n;
        const num = Number.parseInt(v, 16);
        return [(num >> 16) & 255, (num >> 8) & 255, num & 255];
    };
    const [r1, g1, b1] = parse(a);
    const [r2, g2, b2] = parse(b);
    const ch = (x: number, y: number) => Math.round(x + (y - x) * t).toString(16).padStart(2, '0');
    return `#${ch(r1, r2)}${ch(g1, g2)}${ch(b1, b2)}`;
}

export interface AuroraProps {
    deep: string;
    mids: string[];
    accent: string;
    highlight: string;
    secondsPerColor: number;
    intensity: number;
}

/**
 * Returns props ready to spread onto <AuroraBackground> for the given story
 * slug. Falls back to the brand amber palette for unknown slugs so the aurora
 * is always present on the game page.
 */
export function buildAuroraProps(slug: string | null | undefined): AuroraProps {
    const p = PALETTES[slug ?? ''] ?? PALETTES.__default__;
    // Tuned on /bg-rnd: brighter folds, stronger story/brand separation vs dark base.
    const base = mixHex(p.via, BRAND_BLACK, 0.30);
    return {
        deep:            base,
        mids: [
            mixHex(p.from,        base, 0.40),
            mixHex(BRAND_TIFFANY, base, 0.50),
            mixHex(p.from,        base, 0.40),
            mixHex(BRAND_AMBER,   base, 0.54),
        ],
        accent:          mixHex(p.accent, base, 0.18),
        highlight:       mixHex('#fdf5e4', base, 0.18),
        secondsPerColor: 14,
        intensity:       0.75,
    };
}
