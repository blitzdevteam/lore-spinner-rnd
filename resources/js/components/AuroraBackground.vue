<script setup lang="ts">
/**
 * AuroraBackground — an elegant, low-key animated field.
 *
 * A single soft light source pools into a continuously flowing (domain-warped
 * fbm) field over a deep base, ramping deep -> mid -> accent -> highlight.
 * Colours are fully themeable so each story can tint the field with its own
 * palette while keeping the brand's warm highlight.
 *
 * Pure WebGL fragment shader: no images, no seams, respects reduced motion.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        /** Deepest base colour (hex). */
        deep?: string;
        /** Mid tone (hex). */
        mid?: string;
        /**
         * Optional spectrum (hex list) the *dominant* mid tone cycles through —
         * use this to drift the major field colour subtly (story <-> brand).
         * When provided it overrides `mid`.
         */
        mids?: string[];
        /** Bright accent the light blooms into (hex). */
        accent?: string;
        /**
         * Optional spectrum (hex list) the bright accent cycles through.
         * When provided it overrides `accent`.
         */
        accents?: string[];
        /** Seconds spent transitioning between each spectrum colour. */
        secondsPerColor?: number;
        /** Warm highlight at the very brightest core (hex). */
        highlight?: string;
        /** 0..1 overall light strength; lower = calmer/darker for readability. */
        intensity?: number;
    }>(),
    {
        deep: '#04101f',
        mid: '#0a3a52',
        mids: undefined,
        accent: '#08cee6',
        accents: undefined,
        secondsPerColor: 5,
        highlight: '#fdf5e4',
        intensity: 1,
    },
);

// Spectrums cycled over time, as normalised RGB triplets.
const accentSpectrum = computed<[number, number, number][]>(() =>
    (props.accents && props.accents.length > 0 ? props.accents : [props.accent]).map(hexToRgb),
);
const midSpectrum = computed<[number, number, number][]>(() =>
    (props.mids && props.mids.length > 0 ? props.mids : [props.mid]).map(hexToRgb),
);

/** Smoothly sample a colour spectrum at a point in time. */
function sampleSpectrum(palette: [number, number, number][], time: number): [number, number, number] {
    if (palette.length === 1) return palette[0];
    const f = time / Math.max(0.5, props.secondsPerColor);
    const n = palette.length;
    const i = Math.floor(f) % n;
    const j = (i + 1) % n;
    const raw = f - Math.floor(f);
    const k = raw * raw * (3 - 2 * raw);
    const a = palette[i];
    const b = palette[j];
    return [a[0] + (b[0] - a[0]) * k, a[1] + (b[1] - a[1]) * k, a[2] + (b[2] - a[2]) * k];
}

const canvas = ref<HTMLCanvasElement | null>(null);

let gl: WebGLRenderingContext | null = null;
let program: WebGLProgram | null = null;
let raf = 0;
let startTime = 0;
let reducedMotion = false;
let motionMQL: MediaQueryList | null = null;

// Gentle per-instance crop so two fields never look identical.
const seed = Math.random() * 50;
const zoom = 0.98 + Math.random() * 0.08;
const panX = (Math.random() - 0.5) * 0.08;
const panY = (Math.random() - 0.5) * 0.06;

const uniforms: Record<string, WebGLUniformLocation | null> = {};

function hexToRgb(hex: string): [number, number, number] {
    const n = hex.replace('#', '');
    const v = n.length === 3 ? n.split('').map((c) => c + c).join('') : n;
    const num = Number.parseInt(v, 16);
    return [((num >> 16) & 255) / 255, ((num >> 8) & 255) / 255, (num & 255) / 255];
}

const vertSrc = /* glsl */ `
attribute vec2 aPos;
void main() { gl_Position = vec4(aPos, 0.0, 1.0); }
`;

const fragSrc = /* glsl */ `
precision highp float;

uniform vec2  uRes;
uniform float uTime;
uniform float uSeed;
uniform float uZoom;
uniform vec2  uPan;
uniform float uIntensity;
uniform vec3  uDeep;
uniform vec3  uMid;
uniform vec3  uAccent;
uniform vec3  uHighlight;

float hash21(vec2 p) {
    p = fract(p * vec2(123.34, 456.21));
    p += dot(p, p + 45.32);
    return fract(p.x * p.y);
}
float noise2(vec2 p) {
    vec2 i = floor(p);
    vec2 f = fract(p);
    f = f * f * (3.0 - 2.0 * f);
    return mix(
        mix(hash21(i),                  hash21(i + vec2(1.0, 0.0)), f.x),
        mix(hash21(i + vec2(0.0, 1.0)), hash21(i + vec2(1.0, 1.0)), f.x),
        f.y);
}
float fbm(vec2 p) {
    float v = 0.0, a = 0.5;
    for (int i = 0; i < 5; i++) {
        v += a * noise2(p);
        p = p * 2.03 + vec2(1.7, 9.2);
        a *= 0.5;
    }
    return v;
}

// Continuously flowing scalar field — silky folds, never circles.
float flow(vec2 p) {
    float t = uTime * 0.04;
    vec2 q = vec2(
        fbm(p + vec2(0.0, t)),
        fbm(p + vec2(5.2, 1.3) - t));
    vec2 r = vec2(
        fbm(p + 1.5 * q + vec2(1.7, 9.2) + 0.3 * t),
        fbm(p + 1.5 * q + vec2(8.3, 2.8) - 0.3 * t));
    float f = fbm(p + 1.8 * r);
    return f * 0.62 + r.x * 0.38;
}

void main() {
    vec2 fc = gl_FragCoord.xy;
    vec2 uv = fc / uRes;

    float aspect = uRes.x / uRes.y;
    vec2 cuv = (uv - 0.5) * uZoom + 0.5 + uPan;

    vec2 fp = vec2((cuv.x - 0.5) * aspect + 0.5, cuv.y) * 2.1 + vec2(uSeed, 0.0);
    float f = flow(fp);

    // Single soft light source, parked off-centre in the upper third.
    vec2 lightC = vec2(0.40 + 0.03 * sin(uTime * 0.05), 0.74 + 0.02 * cos(uTime * 0.04));
    float d = length((cuv - lightC) * vec2(aspect * 0.78, 1.0));
    float glow = smoothstep(1.05, 0.0, d);

    float L = glow * (0.34 + 0.92 * f);
    L *= mix(0.62, 1.06, smoothstep(-0.1, 1.0, cuv.y));
    L *= uIntensity;
    L = clamp(L, 0.0, 1.25);

    vec3 col = uDeep;
    col = mix(col, uMid,       smoothstep(0.12, 0.52, L));
    col = mix(col, uAccent,    smoothstep(0.50, 0.82, L));
    col = mix(col, uHighlight, smoothstep(0.84, 1.08, L));

    // Cool accent breath in the mid-shadows so the deep field stays alive.
    col = mix(col, uAccent * 0.5, smoothstep(0.04, 0.26, L) * (1.0 - smoothstep(0.4, 0.7, L)) * 0.16);

    float vign = smoothstep(1.35, 0.25, length((uv - 0.5) * vec2(aspect, 1.0)));
    col *= mix(0.82, 1.0, vign);

    col = col / (1.0 + col * 0.06);
    col += (hash21(fc + uTime * 60.0) - 0.5) * 0.010;

    gl_FragColor = vec4(col, 1.0);
}
`;

function compile(glCtx: WebGLRenderingContext, type: number, src: string): WebGLShader {
    const sh = glCtx.createShader(type)!;
    glCtx.shaderSource(sh, src);
    glCtx.compileShader(sh);
    if (!glCtx.getShaderParameter(sh, glCtx.COMPILE_STATUS)) {
        const log = glCtx.getShaderInfoLog(sh);
        glCtx.deleteShader(sh);
        throw new Error(`Shader compile error: ${log}`);
    }
    return sh;
}

function link(glCtx: WebGLRenderingContext, vs: WebGLShader, fs: WebGLShader): WebGLProgram {
    const p = glCtx.createProgram()!;
    glCtx.attachShader(p, vs);
    glCtx.attachShader(p, fs);
    glCtx.linkProgram(p);
    if (!glCtx.getProgramParameter(p, glCtx.LINK_STATUS)) {
        const log = glCtx.getProgramInfoLog(p);
        glCtx.deleteProgram(p);
        throw new Error(`Program link error: ${log}`);
    }
    return p;
}

function resize() {
    if (!canvas.value || !gl) return;
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const w = Math.floor(canvas.value.clientWidth * dpr);
    const h = Math.floor(canvas.value.clientHeight * dpr);
    if (w === 0 || h === 0) return;
    if (canvas.value.width !== w || canvas.value.height !== h) {
        canvas.value.width = w;
        canvas.value.height = h;
    }
    gl.viewport(0, 0, w, h);
}

function applyColors() {
    if (!gl || !program) return;
    gl.useProgram(program);
    gl.uniform3fv(uniforms.uDeep, hexToRgb(props.deep));
    gl.uniform3fv(uniforms.uMid, hexToRgb(props.mid));
    gl.uniform3fv(uniforms.uAccent, hexToRgb(props.accent));
    gl.uniform3fv(uniforms.uHighlight, hexToRgb(props.highlight));
    gl.uniform1f(uniforms.uIntensity, props.intensity);
}

function renderFrame(timestamp: number) {
    if (!gl || !program) return;
    if (!startTime) startTime = timestamp;
    resize();

    const time = reducedMotion ? 12.0 : (timestamp - startTime) * 0.001;

    gl.useProgram(program);
    gl.uniform2f(uniforms.uRes, gl.drawingBufferWidth, gl.drawingBufferHeight);
    gl.uniform1f(uniforms.uTime, time);
    gl.uniform1f(uniforms.uSeed, seed);
    gl.uniform1f(uniforms.uZoom, zoom);
    gl.uniform2f(uniforms.uPan, panX, panY);

    // Drift the dominant mid tone and/or the bright accent through their
    // spectrums so the field's major colour breathes between the story's
    // signature and the brand palette over time.
    if (midSpectrum.value.length > 1) {
        gl.uniform3fv(uniforms.uMid, sampleSpectrum(midSpectrum.value, time));
    }
    if (accentSpectrum.value.length > 1) {
        gl.uniform3fv(uniforms.uAccent, sampleSpectrum(accentSpectrum.value, time));
    }

    gl.drawArrays(gl.TRIANGLES, 0, 6);

    if (!reducedMotion) {
        raf = requestAnimationFrame(renderFrame);
    }
}

function handleMotionPreferenceChange(event: MediaQueryListEvent) {
    reducedMotion = event.matches;
    if (!reducedMotion && raf === 0 && gl && program) {
        raf = requestAnimationFrame(renderFrame);
    }
}

watch(
    () => [props.deep, props.mid, props.mids, props.accent, props.accents, props.highlight, props.intensity],
    () => {
        applyColors();
        if (reducedMotion && gl && program) renderFrame(performance.now());
    },
    { deep: true },
);

onMounted(() => {
    if (typeof window !== 'undefined' && window.matchMedia) {
        motionMQL = window.matchMedia('(prefers-reduced-motion: reduce)');
        reducedMotion = motionMQL.matches;
        motionMQL.addEventListener('change', handleMotionPreferenceChange);
    }

    if (!canvas.value) return;

    const ctx = canvas.value.getContext('webgl', {
        antialias: true,
        premultipliedAlpha: false,
        powerPreference: 'high-performance',
    });
    if (!ctx) return;
    gl = ctx;

    try {
        const vs = compile(gl, gl.VERTEX_SHADER, vertSrc);
        const fs = compile(gl, gl.FRAGMENT_SHADER, fragSrc);
        program = link(gl, vs, fs);
        gl.useProgram(program);

        const buf = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, buf);
        gl.bufferData(
            gl.ARRAY_BUFFER,
            new Float32Array([-1, -1, 1, -1, -1, 1, -1, 1, 1, -1, 1, 1]),
            gl.STATIC_DRAW,
        );
        const loc = gl.getAttribLocation(program, 'aPos');
        gl.enableVertexAttribArray(loc);
        gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);

        uniforms.uRes = gl.getUniformLocation(program, 'uRes');
        uniforms.uTime = gl.getUniformLocation(program, 'uTime');
        uniforms.uSeed = gl.getUniformLocation(program, 'uSeed');
        uniforms.uZoom = gl.getUniformLocation(program, 'uZoom');
        uniforms.uPan = gl.getUniformLocation(program, 'uPan');
        uniforms.uIntensity = gl.getUniformLocation(program, 'uIntensity');
        uniforms.uDeep = gl.getUniformLocation(program, 'uDeep');
        uniforms.uMid = gl.getUniformLocation(program, 'uMid');
        uniforms.uAccent = gl.getUniformLocation(program, 'uAccent');
        uniforms.uHighlight = gl.getUniformLocation(program, 'uHighlight');

        applyColors();
        raf = requestAnimationFrame(renderFrame);
    } catch (e) {
        console.error('AuroraBackground failed to initialise', e);
    }
});

onBeforeUnmount(() => {
    if (raf) cancelAnimationFrame(raf);
    raf = 0;
    if (motionMQL) motionMQL.removeEventListener('change', handleMotionPreferenceChange);
    if (gl && program) gl.deleteProgram(program);
    gl = null;
    program = null;
});
</script>

<template>
    <canvas ref="canvas" class="aurora-canvas" aria-hidden="true" />
</template>

<style scoped>
.aurora-canvas {
    display: block;
    width: 100%;
    height: 100%;
}
</style>
