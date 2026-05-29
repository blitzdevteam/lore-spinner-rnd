<script setup lang="ts">
/**
 * Background R&D — programmatic recreation of the chatgpt.com/codex/ hero.
 *
 * The Codex hero is a macro shot of a backlit translucent petal: a deep
 * saturated field, large soft folds with a bright back-lit rim, a glowing
 * bloom near the top, fine fibre striations, and heavy depth-of-field, all
 * drifting slowly.
 *
 * Here that look is rebuilt entirely in a fragment shader (no video, no
 * frames) — petal lobes are metaball fields, the rim is their gradient, the
 * bloom is a soft gaussian, fibres are stretched noise — and recoloured into
 * the LoreSpinner palette: deep Tiffany teal field, cream / honey petal,
 * warm-white bloom, Tiffany cool accents.
 */
import { Head } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineOptions({ layout: null });

const partners = ['virgin atlantic', 'miro', 'Rakuten', 'WHOOP', 'cisco'];

const canvas = ref<HTMLCanvasElement | null>(null);

let gl: WebGLRenderingContext | null = null;
let program: WebGLProgram | null = null;
let raf = 0;
let startTime = 0;
let reducedMotion = false;
let motionMQL: MediaQueryList | null = null;

// Per-load crop into the larger field so each visit is a fresh framing.
// Kept gentle so the intended petal composition stays well framed.
const seed = Math.random() * 50;
const zoom = 0.96 + Math.random() * 0.12;
const panX = (Math.random() - 0.5) * 0.1;
const panY = (Math.random() - 0.5) * 0.08;

const uniforms: Record<string, WebGLUniformLocation | null> = {};

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
vec2 rot(vec2 p, float a) {
    float s = sin(a), c = cos(a);
    return mat2(c, -s, s, c) * p;
}

// A smooth, continuously flowing scalar field (domain-warped fbm). This is
// what gives silky aurora folds instead of visible circular blobs.
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

    // Continuous silky flow field — sampled at a comfortable macro scale so we
    // see broad folds, not fine noise.
    vec2 fp = vec2((cuv.x - 0.5) * aspect + 0.5, cuv.y) * 2.1 + vec2(uSeed, 0.0);
    float f = flow(fp);

    // A SINGLE soft light source, parked off-centre in the upper third. Its
    // smooth falloff is what creates depth and a focal point; everywhere else
    // settles into the deep brand field (lots of calm negative space).
    vec2 lightC = vec2(0.40 + 0.03 * sin(uTime * 0.05), 0.74 + 0.02 * cos(uTime * 0.04));
    float d = length((cuv - lightC) * vec2(aspect * 0.78, 1.0));
    float glow = smoothstep(1.05, 0.0, d);

    // Luminance: the light pools into the flowing folds, and the bottom of the
    // frame stays deeper so foreground text reads cleanly.
    float L = glow * (0.34 + 0.92 * f);
    L *= mix(0.62, 1.06, smoothstep(-0.1, 1.0, cuv.y));
    L = clamp(L, 0.0, 1.25);

    // ---- LoreSpinner palette (clean, saturated, used with restraint) ----
    vec3 deepNavy = vec3(0.016, 0.070, 0.150);   // deep blue-teal base
    vec3 teal     = vec3(0.028, 0.330, 0.450);   // mid Tiffany
    vec3 tiffany  = vec3(0.063, 0.730, 0.835);   // #08cee6 vivid
    vec3 cream    = vec3(0.984, 0.953, 0.882);   // #fdf5e4
    vec3 honey    = vec3(0.929, 0.729, 0.408);   // #edba68

    vec3 col = deepNavy;
    col = mix(col, teal,    smoothstep(0.12, 0.52, L));
    col = mix(col, tiffany, smoothstep(0.50, 0.82, L));
    col = mix(col, cream,   smoothstep(0.84, 1.08, L));

    // A whisper of honey only at the very brightest core keeps it on-brand and
    // stops the highlight reading as clinical white.
    col = mix(col, honey, smoothstep(0.92, 1.12, L) * 0.30);

    // Cool Tiffany breath in the mid shadows so the deep field is alive, not flat.
    col = mix(col, tiffany * 0.4, smoothstep(0.04, 0.26, L) * (1.0 - smoothstep(0.4, 0.7, L)) * 0.18);

    // Gentle vignette — settles the corners without crushing to black.
    float vign = smoothstep(1.35, 0.25, length((uv - 0.5) * vec2(aspect, 1.0)));
    col *= mix(0.82, 1.0, vign);

    // Soft filmic tonemap for a smooth, gallery-clean falloff.
    col = col / (1.0 + col * 0.06);

    // Very fine grain so large flats never band.
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
    if (canvas.value.width !== w || canvas.value.height !== h) {
        canvas.value.width = w;
        canvas.value.height = h;
    }
    gl.viewport(0, 0, w, h);
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

        raf = requestAnimationFrame(renderFrame);
    } catch (e) {
        console.error('bg-rnd scene failed to initialise', e);
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
    <Head title="Background R&D" />

    <main class="codex-rnd-page">
        <canvas ref="canvas" class="codex-canvas" aria-hidden="true" />

        <header class="codex-nav">
            <a class="codex-brand" href="/" aria-label="Home">
                <span class="codex-brand__dot" aria-hidden="true" />
                <span>LoreSpinner</span>
            </a>

            <div class="codex-nav__actions">
                <a class="codex-cloud-button" href="/chaos-mode">Enter Chaos</a>
                <button class="codex-icon-button" type="button" aria-label="Toggle view">
                    <span />
                    <span />
                </button>
            </div>
        </header>

        <section class="codex-hero">
            <div class="codex-app-icon" aria-hidden="true">
                <span class="codex-app-icon__core">
                    <svg viewBox="0 0 36 36">
                        <path d="M13.2 9.6 19 18l-5.8 8.4" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M19.5 24h7" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" />
                    </svg>
                </span>
            </div>

            <h1>LoreSpinner</h1>
            <p>Stories that live through you.</p>
            <a class="codex-download" href="/chaos-mode">Begin a tale</a>
        </section>

        <section class="codex-trust" aria-label="Trusted by top teams">
            <p>Trusted by top teams</p>
            <div class="codex-logos">
                <span v-for="partner in partners" :key="partner">{{ partner }}</span>
            </div>
        </section>
    </main>
</template>

<style scoped>
.codex-rnd-page {
    position: relative;
    min-height: 100dvh;
    overflow: hidden;
    color: #f3ede0;
    background: #04101f;
    font-family:
        ui-sans-serif,
        -apple-system,
        BlinkMacSystemFont,
        'SF Pro Display',
        'Helvetica Neue',
        Arial,
        sans-serif;
}

.codex-canvas {
    position: fixed;
    inset: 0;
    z-index: 0;
    display: block;
    width: 100%;
    height: 100%;
}

.codex-nav {
    position: relative;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: clamp(18px, 3vw, 30px) clamp(22px, 4vw, 58px);
}

.codex-brand,
.codex-nav__actions {
    display: inline-flex;
    align-items: center;
}

.codex-brand {
    gap: 10px;
    color: rgba(243, 237, 224, 0.92);
    text-decoration: none;
    font-size: clamp(17px, 2vw, 21px);
    font-weight: 720;
    letter-spacing: -0.035em;
}

.codex-brand__dot {
    width: 22px;
    height: 22px;
    border-radius: 30%;
    background: linear-gradient(135deg, #08cee6, #e5ad53);
    box-shadow: 0 6px 18px rgba(8, 206, 230, 0.35);
}

.codex-nav__actions {
    gap: 10px;
}

.codex-cloud-button,
.codex-download {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: #fdf5e4;
    color: #06222b;
    text-decoration: none;
    font-size: 14px;
    font-weight: 650;
    letter-spacing: -0.02em;
    box-shadow: 0 14px 40px rgba(3, 14, 26, 0.45);
}

.codex-cloud-button {
    height: 42px;
    padding: 0 20px;
}

.codex-icon-button {
    display: grid;
    width: 43px;
    height: 43px;
    place-items: center;
    border: 0;
    border-radius: 999px;
    background: rgba(253, 245, 228, 0.82);
    box-shadow: inset 0 0 0 1px rgba(6, 33, 42, 0.08), 0 12px 35px rgba(6, 33, 42, 0.12);
}

.codex-icon-button span {
    grid-area: 1 / 1;
    width: 13px;
    height: 18px;
    border: 1.8px solid rgba(6, 33, 42, 0.72);
    border-radius: 999px;
}

.codex-icon-button span:first-child {
    transform: translateX(-4px);
}

.codex-icon-button span:last-child {
    transform: translateX(4px);
}

.codex-hero {
    position: relative;
    z-index: 8;
    display: grid;
    min-height: min(55dvh, 520px);
    place-items: center;
    padding: clamp(24px, 5vw, 64px) 24px 0;
    text-align: center;
}

.codex-app-icon {
    display: grid;
    width: clamp(78px, 8vw, 104px);
    height: clamp(78px, 8vw, 104px);
    margin-bottom: 34px;
    place-items: center;
    border-radius: 26%;
    background: rgba(253, 245, 228, 0.6);
    box-shadow:
        inset 0 1px 1px rgba(255, 255, 255, 0.7),
        0 18px 50px rgba(8, 206, 230, 0.26),
        0 2px 10px rgba(6, 33, 42, 0.12);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
}

.codex-app-icon__core {
    display: grid;
    width: 68%;
    height: 68%;
    place-items: center;
    border-radius: 28%;
    color: #fdf5e4;
    background:
        radial-gradient(circle at 70% 26%, #43e0f2, transparent 34%),
        radial-gradient(circle at 34% 76%, #e5ad53, transparent 40%),
        linear-gradient(135deg, #0a8ea3, #06636f);
    box-shadow:
        inset 0 1px 6px rgba(255, 255, 255, 0.3),
        0 10px 28px rgba(6, 99, 111, 0.4);
}

.codex-app-icon svg {
    width: 62%;
    height: 62%;
}

.codex-hero h1 {
    margin: 0;
    color: #fbf6ec;
    font-size: clamp(56px, 7vw, 82px);
    font-weight: 650;
    letter-spacing: -0.06em;
    line-height: 0.9;
    text-shadow: 0 2px 30px rgba(3, 14, 26, 0.45);
}

.codex-hero p {
    margin: 30px 0 34px;
    color: rgba(243, 237, 224, 0.82);
    font-size: clamp(19px, 2.1vw, 25px);
    font-weight: 470;
    letter-spacing: -0.03em;
    text-shadow: 0 1px 18px rgba(3, 14, 26, 0.4);
}

.codex-download {
    height: 52px;
    padding: 0 25px;
}

.codex-trust {
    position: relative;
    z-index: 8;
    margin-top: clamp(34px, 7vh, 78px);
    padding: 0 6vw 38px;
    text-align: center;
}

.codex-trust p {
    margin: 0 0 48px;
    color: rgba(243, 237, 224, 0.6);
    font-size: 18px;
    font-weight: 520;
    letter-spacing: -0.025em;
}

.codex-logos {
    display: grid;
    max-width: 1120px;
    margin: 0 auto;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    align-items: center;
    gap: clamp(20px, 5vw, 66px);
}

.codex-logos span {
    color: rgba(243, 237, 224, 0.5);
    font-size: clamp(22px, 3vw, 36px);
    font-weight: 760;
    letter-spacing: -0.08em;
    line-height: 1;
}

.codex-logos span:first-child {
    font-weight: 430;
    letter-spacing: -0.07em;
}

.codex-logos span:nth-child(4) {
    font-weight: 360;
    letter-spacing: 0.06em;
}

.codex-logos span:last-child {
    font-size: clamp(20px, 2.8vw, 30px);
    letter-spacing: 0.12em;
}

@media (max-width: 780px) {
    .codex-nav {
        padding: 18px;
    }

    .codex-cloud-button {
        display: none;
    }

    .codex-hero {
        min-height: 58dvh;
    }

    .codex-logos {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 28px 36px;
    }

    .codex-logos span:last-child {
        grid-column: 1 / -1;
    }
}
</style>
