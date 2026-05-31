import type { CachedStoryPalette, StoryPalette } from '@/types/story-atmosphere';

export const LORE_SPINNER_DEFAULT_PALETTE: StoryPalette = {
    primary: '#1e5058',
    secondary: '#00c6de',
    accent: '#ffbe58',
};

const STORAGE_PREFIX = 'lore-story-palette:';
const memoryCache = new Map<string, StoryPalette>();

interface Rgb {
    r: number;
    g: number;
    b: number;
}

interface WeightedColor extends Rgb {
    weight: number;
    hue: number;
    sat: number;
    light: number;
}

function rgbToHex(r: number, g: number, b: number): string {
    const toHex = (value: number) => value.toString(16).padStart(2, '0');
    return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
}

function hexToRgb(hex: string): Rgb {
    const normalized = hex.replace('#', '');
    const value = normalized.length === 3
        ? normalized.split('').map((c) => c + c).join('')
        : normalized;

    return {
        r: parseInt(value.slice(0, 2), 16),
        g: parseInt(value.slice(2, 4), 16),
        b: parseInt(value.slice(4, 6), 16),
    };
}

export function interpolateHex(from: string, to: string, t: number): string {
    const a = hexToRgb(from);
    const b = hexToRgb(to);
    const mix = (start: number, end: number) => Math.round(start + (end - start) * t);

    return rgbToHex(mix(a.r, b.r), mix(a.g, b.g), mix(a.b, b.b));
}

function rgbToHsl(r: number, g: number, b: number): [number, number, number] {
    const rn = r / 255;
    const gn = g / 255;
    const bn = b / 255;
    const max = Math.max(rn, gn, bn);
    const min = Math.min(rn, gn, bn);
    const delta = max - min;
    let hue = 0;
    const light = (max + min) / 2;
    let sat = 0;

    if (delta !== 0) {
        sat = delta / (1 - Math.abs(2 * light - 1));

        switch (max) {
            case rn:
                hue = ((gn - bn) / delta) % 6;
                break;
            case gn:
                hue = (bn - rn) / delta + 2;
                break;
            default:
                hue = (rn - gn) / delta + 4;
                break;
        }

        hue *= 60;
        if (hue < 0) {
            hue += 360;
        }
    }

    return [hue, sat, light];
}

function hueDistance(a: number, b: number): number {
    const diff = Math.abs(a - b) % 360;
    return diff > 180 ? 360 - diff : diff;
}

function loadImage(url: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.crossOrigin = 'anonymous';
        image.decoding = 'async';
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error(`Failed to load cover image: ${url}`));
        image.src = url;
    });
}

function collectWeightedColors(data: Uint8ClampedArray): WeightedColor[] {
    const buckets = new Map<string, WeightedColor>();

    for (let index = 0; index < data.length; index += 4) {
        const r = data[index];
        const g = data[index + 1];
        const b = data[index + 2];
        const alpha = data[index + 3];

        if (alpha < 128) {
            continue;
        }

        const [hue, sat, light] = rgbToHsl(r, g, b);

        if (light < 0.05 || light > 0.95) {
            continue;
        }

        if (sat < 0.06 && light < 0.22) {
            continue;
        }

        const qr = Math.round(r / 20) * 20;
        const qg = Math.round(g / 20) * 20;
        const qb = Math.round(b / 20) * 20;
        const key = `${qr}:${qg}:${qb}`;
        const weight = sat * 1.4 + (1 - Math.abs(light - 0.42)) * 0.6;

        const existing = buckets.get(key);
        if (existing) {
            existing.weight += weight;
            continue;
        }

        buckets.set(key, {
            r: qr,
            g: qg,
            b: qb,
            weight,
            hue,
            sat,
            light,
        });
    }

    return [...buckets.values()].sort((left, right) => right.weight - left.weight);
}

function pickPrimary(colors: WeightedColor[]): WeightedColor {
    const moody = colors.filter((color) => color.light >= 0.12 && color.light <= 0.58);
    return moody[0] ?? colors[0];
}

function pickSecondary(colors: WeightedColor[], primary: WeightedColor): WeightedColor {
    const candidate = colors.find(
        (color) => color !== primary && hueDistance(color.hue, primary.hue) >= 32,
    );

    return candidate ?? colors.find((color) => color !== primary) ?? primary;
}

function pickAccent(colors: WeightedColor[], primary: WeightedColor, secondary: WeightedColor): WeightedColor {
    const candidate = colors.find((color) => {
        if (color === primary || color === secondary) {
            return false;
        }

        return color.sat >= 0.25 && color.light >= 0.28 && color.light <= 0.78;
    });

    return candidate ?? secondary;
}

function normalizePalette(colors: WeightedColor[]): StoryPalette {
    const primary = pickPrimary(colors);
    const secondary = pickSecondary(colors, primary);
    const accent = pickAccent(colors, primary, secondary);

    return {
        primary: rgbToHex(primary.r, primary.g, primary.b),
        secondary: rgbToHex(secondary.r, secondary.g, secondary.b),
        accent: rgbToHex(accent.r, accent.g, accent.b),
    };
}

export async function extractStoryPalette(coverUrl: string): Promise<StoryPalette> {
    if (!coverUrl.trim()) {
        return { ...LORE_SPINNER_DEFAULT_PALETTE };
    }

    try {
        const image = await loadImage(coverUrl);
        const canvas = document.createElement('canvas');
        const sampleSize = 72;

        canvas.width = sampleSize;
        canvas.height = sampleSize;

        const context = canvas.getContext('2d', { willReadFrequently: true });
        if (!context) {
            return { ...LORE_SPINNER_DEFAULT_PALETTE };
        }

        context.drawImage(image, 0, 0, sampleSize, sampleSize);
        const { data } = context.getImageData(0, 0, sampleSize, sampleSize);
        const colors = collectWeightedColors(data);

        if (!colors.length) {
            return { ...LORE_SPINNER_DEFAULT_PALETTE };
        }

        return normalizePalette(colors);
    } catch {
        return { ...LORE_SPINNER_DEFAULT_PALETTE };
    }
}

function readCachedPalette(storyId: number, coverUrl: string): StoryPalette | null {
    try {
        const raw = localStorage.getItem(`${STORAGE_PREFIX}${storyId}`);
        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw) as CachedStoryPalette;
        if (parsed.coverUrl !== coverUrl) {
            return null;
        }

        return parsed.palette;
    } catch {
        return null;
    }
}

function writeCachedPalette(storyId: number, coverUrl: string, palette: StoryPalette): void {
    try {
        const payload: CachedStoryPalette = { coverUrl, palette };
        localStorage.setItem(`${STORAGE_PREFIX}${storyId}`, JSON.stringify(payload));
    } catch {
        // ignore quota / private mode errors
    }
}

export async function getStoryPalette(storyId: number, coverUrl: string): Promise<StoryPalette> {
    const normalizedCover = coverUrl.trim();
    const cacheKey = `${storyId}:${normalizedCover}`;

    if (memoryCache.has(cacheKey)) {
        return memoryCache.get(cacheKey)!;
    }

    const cached = readCachedPalette(storyId, normalizedCover);
    if (cached) {
        memoryCache.set(cacheKey, cached);
        return cached;
    }

    const palette = normalizedCover
        ? await extractStoryPalette(normalizedCover)
        : { ...LORE_SPINNER_DEFAULT_PALETTE };

    memoryCache.set(cacheKey, palette);
    writeCachedPalette(storyId, normalizedCover, palette);

    return palette;
}
