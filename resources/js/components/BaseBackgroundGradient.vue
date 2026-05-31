<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';

const props = defineProps<{
    coverUrl?: string | null;
}>();

const DEFAULT_COLORS = [
    'rgb(93, 139, 167)',
    'rgb(142, 70, 145)',
    'rgb(174, 62, 62)',
];

const smokeColors = ref<string[]>([...DEFAULT_COLORS]);

async function extractColors(url: string): Promise<string[]> {
    return new Promise((resolve) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => {
            const canvas = document.createElement('canvas');
            const size = 150;
            canvas.width = size;
            canvas.height = size;
            const ctx = canvas.getContext('2d');
            if (!ctx) {
                resolve([]);
                return;
            }
            ctx.drawImage(img, 0, 0, size, size);
            const { data } = ctx.getImageData(0, 0, size, size);

            const buckets = new Map<string, number>();
            for (let i = 0; i < data.length; i += 4) {
                const r = data[i], g = data[i + 1], b = data[i + 2];
                // Skip near-black and near-white (too neutral)
                const brightness = (r * 299 + g * 587 + b * 114) / 1000;
                if (brightness < 30 || brightness > 230) continue;
                // Quantize into buckets to group similar colors (finer step for better variety)
                const qr = Math.round(r / 16) * 16;
                const qg = Math.round(g / 16) * 16;
                const qb = Math.round(b / 16) * 16;
                const key = `${qr},${qg},${qb}`;
                buckets.set(key, (buckets.get(key) ?? 0) + 1);
            }

            const sorted = [...buckets.entries()].sort((a, b) => b[1] - a[1]);
            const picked: [number, number, number][] = [];
            for (const [key] of sorted) {
                if (picked.length >= 3) break;
                const [r, g, b] = key.split(',').map(Number);
                // Ensure picked colors are visually distinct from each other
                const tooClose = picked.some(
                    ([pr, pg, pb]) => Math.abs(pr - r) + Math.abs(pg - g) + Math.abs(pb - b) < 60,
                );
                if (!tooClose) picked.push([r, g, b]);
            }

            resolve(picked.map(([r, g, b]) => `rgb(${r}, ${g}, ${b})`));
        };
        img.onerror = () => resolve([]);
        img.src = url;
    });
}

async function updateColors(url?: string | null): Promise<void> {
    if (!url) {
        smokeColors.value = [...DEFAULT_COLORS];
        return;
    }
    const colors = await extractColors(url);
    smokeColors.value = [
        colors[0] ?? DEFAULT_COLORS[0],
        colors[1] ?? DEFAULT_COLORS[1],
        colors[2] ?? DEFAULT_COLORS[2],
    ];
}

onMounted(() => updateColors(props.coverUrl));
watch(() => props.coverUrl, updateColors);
</script>

<template>
    <div aria-hidden="true" class="bg-gradient-overlay pointer-events-none fixed inset-0 -z-10 overflow-hidden bg-black">
        <!-- Smoke effect 1 — top-left corner -->
        <div class="smoke-blob smoke-blob--1 absolute -top-1/3 -left-1/3 h-[70vh] w-[70vw]">
            <div
                class="breathe breathe--1 h-full w-full"
                :style="`background: linear-gradient(126.15deg, ${smokeColors[0]} 1.97%, rgb(0, 0, 0) 70.15%); filter: blur(250px);`"
            />
        </div>
        <!-- Smoke effect 2 — top-right corner -->
        <div class="smoke-blob smoke-blob--2 absolute -top-1/3 -right-1/3 h-[70vh] w-[70vw]">
            <div
                class="breathe breathe--2 h-full w-full"
                :style="`background: linear-gradient(135.84deg, ${smokeColors[1]} 1.97%, rgb(0, 0, 0) 70.15%); filter: blur(250px);`"
            />
        </div>
        <!-- Smoke effect 3 — bottom-left corner -->
        <div class="smoke-blob smoke-blob--3 absolute -bottom-1/3 -left-1/3 h-[70vh] w-[70vw]">
            <div
                class="breathe breathe--3 h-full w-full"
                :style="`background: linear-gradient(126.15deg, ${smokeColors[2]} 1.97%, rgb(0, 0, 0) 70.15%); filter: blur(250px);`"
            />
        </div>
    </div>
</template>

<style scoped>
.bg-gradient-overlay::after {
    content: '';
    position: absolute;
    inset: 0;
    opacity: 0.035;
    pointer-events: none;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
    background-repeat: repeat;
    background-size: 16rem 16rem;
}

.smoke-blob {
    pointer-events: none;
    will-change: transform, opacity;
}

.smoke-blob--1 {
    animation: drift-1 18s ease-in-out infinite alternate;
}

.smoke-blob--2 {
    animation: drift-2 22s ease-in-out infinite alternate;
}

.smoke-blob--3 {
    animation: drift-3 26s ease-in-out infinite alternate;
}

.breathe {
    will-change: transform, opacity;
}

.breathe--1 {
    animation: breathe-1 7s ease-in-out infinite alternate;
}

.breathe--2 {
    animation: breathe-2 9s ease-in-out infinite alternate;
}

.breathe--3 {
    animation: breathe-3 11s ease-in-out infinite alternate;
}

@keyframes drift-1 {
    0%   { transform: translate(0, 0) scale(1); opacity: 0.85; }
    25%  { transform: translate(20%, 15%) scale(1.15); opacity: 0.65; }
    50%  { transform: translate(10%, 30%) scale(0.9); opacity: 0.78; }
    75%  { transform: translate(25%, 10%) scale(1.2); opacity: 0.6; }
    100% { transform: translate(15%, 35%) scale(1.1); opacity: 0.72; }
}

@keyframes drift-2 {
    0%   { transform: translate(0, 0) scale(1); opacity: 0.72; }
    30%  { transform: translate(-18%, 20%) scale(1.18); opacity: 0.88; }
    60%  { transform: translate(-30%, 10%) scale(0.88); opacity: 0.62; }
    80%  { transform: translate(-12%, 28%) scale(1.22); opacity: 0.8; }
    100% { transform: translate(-25%, 18%) scale(1.05); opacity: 0.68; }
}

@keyframes drift-3 {
    0%   { transform: translate(0, 0) scale(1); opacity: 0.7; }
    20%  { transform: translate(15%, -20%) scale(1.2); opacity: 0.88; }
    50%  { transform: translate(-12%, -30%) scale(0.88); opacity: 0.73; }
    70%  { transform: translate(22%, -15%) scale(1.15); opacity: 0.58; }
    100% { transform: translate(-18%, -25%) scale(1.25); opacity: 0.78; }
}

@keyframes breathe-1 {
    0%   { transform: scale(1);    opacity: 1; }
    50%  { transform: scale(1.12); opacity: 0.75; }
    100% { transform: scale(0.9);  opacity: 1; }
}

@keyframes breathe-2 {
    0%   { transform: scale(1);    opacity: 0.9; }
    50%  { transform: scale(0.88); opacity: 1; }
    100% { transform: scale(1.1);  opacity: 0.8; }
}

@keyframes breathe-3 {
    0%   { transform: scale(1);    opacity: 1; }
    40%  { transform: scale(1.15); opacity: 0.7; }
    100% { transform: scale(0.92); opacity: 0.95; }
}
</style>
