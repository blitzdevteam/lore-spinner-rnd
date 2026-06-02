export function hexToRgba(hex: string, alpha: number): string {
    const n = hex.replace('#', '');
    const v = n.length === 3 ? n.split('').map((c) => c + c).join('') : n;
    const num = Number.parseInt(v, 16);
    const r = (num >> 16) & 255;
    const g = (num >> 8) & 255;
    const b = num & 255;
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

/** CSS custom properties for glass panel tint from a hex color (or none). */
export function glassTintVars(color: string): Record<string, string> {
    if (!color) {
        return {
            '--glass-tint': 'transparent',
            '--glass-tint-solid': 'transparent',
            '--glass-tint-strong': 'transparent',
        };
    }

    return {
        '--glass-tint': hexToRgba(color, 0.55),
        '--glass-tint-solid': hexToRgba(color, 0.38),
        '--glass-tint-strong': hexToRgba(color, 0.72),
    };
}
