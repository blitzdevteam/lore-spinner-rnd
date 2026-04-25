<script setup lang="ts">
/**
 * Design R&D — Scene 01: Living Orb.
 *
 * Applies three skills:
 *  - taste-skill (design-taste-frontend): DESIGN_VARIANCE 8 / MOTION_INTENSITY 6 /
 *    VISUAL_DENSITY 4. Anti-center hero, single accent (#54f4da), non-commensurate
 *    motion, hardware-accelerated animation, no Inter, no pure black.
 *  - redesign-skill: proper loading / error states, prefers-reduced-motion respect,
 *    meta description, semantic landmarks, aria-label on the canvas, skip-to-content.
 *  - output-skill: full file, no placeholders.
 *
 * Visual pipeline (single fragment shader):
 *  - Seamless ocean texture tiled via fract(uv), aspect-corrected so it never
 *    distorts. Layered drift + two opposing fbm flow fields warp the sample UVs.
 *  - Orb PNG sampled with figure-eight float, breathing scale, soft pulse, UV shimmer.
 *  - Brand-cyan radial screen overlay for atmosphere.
 *  - Spill + seafloor pool modulated by the orb pulse so the water responds with it.
 *  - Vignette, Reinhard tonemap, gentle gamma lift, film grain.
 *
 * Uniforms that will animate later from Vue: uOrbCenter, uOrbScale, uTintCenter.
 */

import oceanSeamlessUrl from '@/assets/design-rnd/ocean-seamless.png';
import orbUrl from '@/assets/design-rnd/orb-texture.png';
import { Head } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

type SceneStatus = 'loading' | 'ready' | 'error';

const canvas = ref<HTMLCanvasElement | null>(null);
const status = ref<SceneStatus>('loading');
const errorDetail = ref('');

let gl: WebGLRenderingContext | null = null;
let program: WebGLProgram | null = null;
let raf = 0;
let startTime = 0;
let reducedMotion = false;
let motionMQL: MediaQueryList | null = null;

const uniforms: Record<string, WebGLUniformLocation | null> = {};

const vertSrc = /* glsl */ `
attribute vec2 aPos;
void main() { gl_Position = vec4(aPos, 0.0, 1.0); }
`;

const fragSrc = /* glsl */ `
precision highp float;

uniform vec2      uRes;
uniform float     uTime;
uniform sampler2D uBgTex;
uniform sampler2D uOrbTex;
uniform float     uTileScale;
uniform vec2      uOrbCenter;
uniform float     uOrbScale;
uniform vec2      uTintCenter;

// ----- 2D value noise & fbm -----
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
        mix(hash21(i),                   hash21(i + vec2(1.0, 0.0)), f.x),
        mix(hash21(i + vec2(0.0, 1.0)),  hash21(i + vec2(1.0, 1.0)), f.x),
        f.y);
}
float fbm2(vec2 p) {
    float v = 0.0, a = 0.5;
    for (int i = 0; i < 4; i++) {
        v += a * noise2(p);
        p = p * 2.03 + vec2(1.7, 9.2);
        a *= 0.5;
    }
    return v;
}

void main() {
    vec2 fc   = gl_FragCoord.xy;
    vec2 uv01 = fc / uRes;
    vec2 uv   = (fc - 0.5 * uRes) / min(uRes.x, uRes.y);

    float t = uTime;

    // =====================================================================
    //  BACKGROUND — seamless tile + aspect correction so the texture is
    //  never stretched on wide or tall viewports; fract() handles wrapping
    //  regardless of how far drift accumulates.
    // =====================================================================
    float aspect = uRes.x / uRes.y;
    vec2 tileScales = vec2(max(aspect, 1.0), max(1.0 / aspect, 1.0));

    // Three drift channels — non-commensurate frequencies so the loop is never visible.
    vec2 drift = vec2(
        sin(t * 0.043) * 0.012 + sin(t * 0.071) * 0.005,
        t * 0.008            + sin(t * 0.037) * 0.010
    );

    // Two opposing fbm flow fields at different scales: low-freq drifting down,
    // high-freq drifting up. Net effect: fixed ripples visibly swim.
    vec2 flowLo = vec2(
        fbm2(uv * 1.4 + vec2(0.0,  t * 0.060)),
        fbm2(uv * 1.4 + vec2(5.2,  t * 0.060) + vec2(1.3, 0.0))
    ) - 0.5;
    vec2 flowHi = vec2(
        fbm2(uv * 3.6 + vec2(0.0, -t * 0.090)),
        fbm2(uv * 3.6 + vec2(7.1, -t * 0.090) + vec2(3.2, 0.0))
    ) - 0.5;
    vec2 flow = flowLo * 0.014 + flowHi * 0.006;

    vec2 tUV   = uv01 * tileScales * uTileScale + drift + flow;
    vec3 bg    = texture2D(uBgTex, fract(tUV)).rgb;

    // Brand cyan (#54f4da) — radial screen overlay, modulated by a slow breath.
    float tintBreath = 0.85 + 0.15 * sin(t * 0.09);
    float tintRadial = smoothstep(1.30, 0.05, length(uv - uTintCenter));
    vec3  brandTint  = vec3(0.329, 0.957, 0.855) * tintRadial * 0.17 * tintBreath;
    bg = 1.0 - (1.0 - bg) * (1.0 - brandTint);

    // =====================================================================
    //  ORB — float, breathe, pulse.
    // =====================================================================
    vec2 orbFloat = vec2(
        sin(t * 0.11)  * 0.0085,
        sin(t * 0.155) * 0.0130 + cos(t * 0.083) * 0.0055
    );
    vec2  orbC     = uOrbCenter + orbFloat;
    float orbScale = uOrbScale * (1.0 + 0.016 * sin(t * 0.23));

    vec2  orbLocal = (uv - orbC) / orbScale;
    float d        = length(orbLocal);

    vec2 shim = vec2(
        fbm2(orbLocal * 2.6 + t * 0.13),
        fbm2(orbLocal * 2.6 + vec2(9.1, 2.3) + t * 0.13)
    ) - 0.5;

    vec2  orbUV  = orbLocal * 0.5 + 0.5 + shim * 0.009;
    float inRect = step(0.0, orbUV.x) * step(orbUV.x, 1.0)
                 * step(0.0, orbUV.y) * step(orbUV.y, 1.0);

    vec3 orbSample = texture2D(uOrbTex, clamp(orbUV, 0.0, 1.0)).rgb;

    float pulse    = 0.94 + 0.08 * sin(t * 0.32);
    vec3  orbColor = orbSample * pulse * inRect;

    // Screen-blend over water: the PNG's dark border is neutral; the luminous
    // orb + embedded stars add on top as natural marine light.
    vec3 scene = 1.0 - (1.0 - bg) * (1.0 - orbColor);

    // Soft ambient light spill around the orb ties it to the surrounding water.
    float spill = exp(-d * 2.8) * 0.35 + exp(-d * 6.5) * 0.50;
    scene += vec3(0.14, 0.85, 0.95) * spill * 0.18;

    // Seafloor light pool modulated by the same pulse.
    float floorY      = orbC.y - orbScale * 1.08;
    float floorMask   = 1.0 - smoothstep(floorY - 0.32, floorY + 0.08, uv.y);
    float floorRadial = exp(-pow((uv.x - orbC.x) * 2.1, 2.0));
    scene += vec3(0.06, 0.38, 0.44) * floorMask * floorRadial * 0.32 * pulse;

    // =====================================================================
    //  FINISH — vignette / tonemap / grain.
    // =====================================================================
    float vign = smoothstep(1.35, 0.30, length(uv));
    scene *= mix(0.55, 1.00, vign);

    scene = scene / (1.0 + scene);
    scene = pow(scene, vec3(0.90));
    scene = max(scene - 0.002, 0.0);

    float grain = (hash21(fc + t * 57.0) - 0.5) * 0.010;
    scene += grain;

    gl_FragColor = vec4(scene, 1.0);
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

function loadImage(url: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error(`Failed to load image: ${url}`));
        img.src = url;
    });
}

function createTex(glCtx: WebGLRenderingContext, img: HTMLImageElement): WebGLTexture {
    const tex = glCtx.createTexture()!;
    glCtx.bindTexture(glCtx.TEXTURE_2D, tex);
    glCtx.pixelStorei(glCtx.UNPACK_FLIP_Y_WEBGL, true);
    glCtx.texImage2D(glCtx.TEXTURE_2D, 0, glCtx.RGBA, glCtx.RGBA, glCtx.UNSIGNED_BYTE, img);
    glCtx.texParameteri(glCtx.TEXTURE_2D, glCtx.TEXTURE_MIN_FILTER, glCtx.LINEAR);
    glCtx.texParameteri(glCtx.TEXTURE_2D, glCtx.TEXTURE_MAG_FILTER, glCtx.LINEAR);
    // NPOT-safe: wrapping is handled via fract() in the shader, not by GL.
    glCtx.texParameteri(glCtx.TEXTURE_2D, glCtx.TEXTURE_WRAP_S, glCtx.CLAMP_TO_EDGE);
    glCtx.texParameteri(glCtx.TEXTURE_2D, glCtx.TEXTURE_WRAP_T, glCtx.CLAMP_TO_EDGE);
    return tex;
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

    // Reduced-motion: freeze time at a tasteful offset so the scene still reads
    // as a composed image (orb at rest, water at mid-phase), just not animated.
    const time = reducedMotion ? 0.42 : (timestamp - startTime) * 0.001;

    gl.useProgram(program);
    gl.uniform2f(uniforms.uRes, gl.drawingBufferWidth, gl.drawingBufferHeight);
    gl.uniform1f(uniforms.uTime, time);
    gl.uniform1f(uniforms.uTileScale, 0.95);
    gl.uniform2f(uniforms.uOrbCenter, 0.0, -0.08);
    gl.uniform1f(uniforms.uOrbScale, 0.30);
    gl.uniform2f(uniforms.uTintCenter, 0.0, 0.28);
    gl.drawArrays(gl.TRIANGLES, 0, 6);

    if (!reducedMotion) {
        raf = requestAnimationFrame(renderFrame);
    }
}

function handleMotionPreferenceChange(event: MediaQueryListEvent) {
    reducedMotion = event.matches;
    // Re-kick the loop if the user disables reduced-motion mid-session.
    if (!reducedMotion && raf === 0 && gl && program) {
        raf = requestAnimationFrame(renderFrame);
    }
}

onMounted(async () => {
    if (typeof window !== 'undefined' && window.matchMedia) {
        motionMQL = window.matchMedia('(prefers-reduced-motion: reduce)');
        reducedMotion = motionMQL.matches;
        motionMQL.addEventListener('change', handleMotionPreferenceChange);
    }

    if (!canvas.value) {
        status.value = 'error';
        errorDetail.value = 'Canvas element unavailable.';
        return;
    }

    const ctx = canvas.value.getContext('webgl', {
        antialias: true,
        premultipliedAlpha: false,
        powerPreference: 'high-performance',
    });
    if (!ctx) {
        status.value = 'error';
        errorDetail.value = 'WebGL is not supported in this browser.';
        return;
    }
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
        uniforms.uBgTex = gl.getUniformLocation(program, 'uBgTex');
        uniforms.uOrbTex = gl.getUniformLocation(program, 'uOrbTex');
        uniforms.uTileScale = gl.getUniformLocation(program, 'uTileScale');
        uniforms.uOrbCenter = gl.getUniformLocation(program, 'uOrbCenter');
        uniforms.uOrbScale = gl.getUniformLocation(program, 'uOrbScale');
        uniforms.uTintCenter = gl.getUniformLocation(program, 'uTintCenter');

        const [bgImg, orbImg] = await Promise.all([
            loadImage(oceanSeamlessUrl),
            loadImage(orbUrl),
        ]);

        const bgTex = createTex(gl, bgImg);
        const orbTex = createTex(gl, orbImg);

        gl.activeTexture(gl.TEXTURE0);
        gl.bindTexture(gl.TEXTURE_2D, bgTex);
        gl.uniform1i(uniforms.uBgTex, 0);

        gl.activeTexture(gl.TEXTURE1);
        gl.bindTexture(gl.TEXTURE_2D, orbTex);
        gl.uniform1i(uniforms.uOrbTex, 1);

        status.value = 'ready';
        raf = requestAnimationFrame(renderFrame);
    } catch (e) {
        console.error('Design R&D scene failed to initialise', e);
        status.value = 'error';
        errorDetail.value = e instanceof Error ? e.message : 'Unknown initialisation error.';
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
    <Head title="Lore Spinner — Design R&D · Scene 01">
        <meta
            head-key="description"
            name="description"
            content="Living-orb scene adrift in slow deep water. A cinematic design R&D sandbox for Lore Spinner — light, texture, and patient motion."
        />
    </Head>

    <a class="dr-skip" href="#dr-title">Skip to content</a>

    <main class="dr-scene" aria-labelledby="dr-title">
        <canvas
            ref="canvas"
            class="dr-canvas"
            role="img"
            aria-label="A luminous orb floating in a slowly drifting deep-ocean scene, lit from below by a cyan hotspot."
        />

        <!-- Edge darkening for overlay legibility — corners only, never centre. -->
        <div class="dr-veil" aria-hidden="true" />

        <!-- Loading state: breathing orb silhouette echoing the real scene. -->
        <Transition name="dr-fade">
            <div v-if="status === 'loading'" class="dr-loading" role="status" aria-live="polite">
                <div class="dr-loading__orb" aria-hidden="true" />
                <p class="dr-loading__label">tuning the water</p>
            </div>
        </Transition>

        <!-- Error state: styled, typed, not a dead screen. -->
        <div v-if="status === 'error'" class="dr-error" role="alert">
            <div class="dr-error__mark" aria-hidden="true">
                <svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="10" cy="10" r="8" />
                    <path d="M10 6v5" stroke-linecap="round" />
                    <circle cx="10" cy="14" r="0.75" fill="currentColor" stroke="none" />
                </svg>
            </div>
            <div>
                <p class="dr-error__heading">The light couldn&rsquo;t reach here.</p>
                <p class="dr-error__detail">{{ errorDetail }}</p>
            </div>
        </div>

        <!-- Content overlay — stagger-in when ready. -->
        <Transition name="dr-rise">
            <div v-if="status === 'ready'" class="dr-overlay">
                <header class="dr-brand">
                    <span class="dr-brand__mark" aria-hidden="true">
                        <svg viewBox="0 0 16 16" width="11" height="11" fill="none" stroke="currentColor" stroke-width="1">
                            <circle cx="8" cy="8" r="6" />
                            <line x1="3.5" y1="11" x2="12.5" y2="5" stroke-linecap="round" />
                        </svg>
                    </span>
                    <span class="dr-brand__word">Lore Spinner</span>
                    <span class="dr-brand__divider" aria-hidden="true">/</span>
                    <span class="dr-brand__project">Design R&amp;D</span>
                </header>

                <section class="dr-hero" aria-label="Scene">
                    <p class="dr-hero__eyebrow">Scene 01 · Living Orb</p>
                    <h1 id="dr-title" class="dr-hero__title">
                        <span class="dr-hero__line" style="--i: 0">Stories</span>
                        <span class="dr-hero__line dr-hero__line--accent" style="--i: 1">that live</span>
                        <span class="dr-hero__line" style="--i: 2">through you.</span>
                    </h1>
                    <p class="dr-hero__lead">A sandbox for slow, cinematic interfaces — light, water, and patient motion.</p>
                </section>

                <footer class="dr-meta">
                    <span class="dr-meta__group">
                        <span class="dr-meta__pulse" aria-hidden="true" />
                        <span>live build</span>
                    </span>
                    <span class="dr-meta__group dr-meta__group--data">
                        <span class="dr-meta__label">depth</span>
                        <span class="dr-meta__value">47.2m</span>
                        <span class="dr-meta__divider" aria-hidden="true">·</span>
                        <span class="dr-meta__label">temp</span>
                        <span class="dr-meta__value">8.6&deg;c</span>
                    </span>
                </footer>
            </div>
        </Transition>
    </main>
</template>

<style scoped>
/* Intentionally no Inter anywhere. Display uses the project's Gill Sans + Outfit
   fallback; data rows use JetBrains Mono if available, else SF Mono. */

.dr-scene {
    position: fixed;
    inset: 0;
    min-height: 100dvh;
    overflow: hidden;
    background: #01070c;
    color: #eaf2f3;
    font-family: 'Gill Sans', 'Outfit', 'Source Sans 3', system-ui, sans-serif;
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
}

.dr-canvas {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    display: block;
    z-index: 0;
}

/* Readability veil — only strong at the corners so the scene stays dominant. */
.dr-veil {
    position: absolute;
    inset: 0;
    z-index: 1;
    pointer-events: none;
    background:
        linear-gradient(180deg, rgba(1, 7, 12, 0.55) 0%, transparent 22%, transparent 72%, rgba(1, 7, 12, 0.55) 100%),
        radial-gradient(ellipse 80% 60% at 20% 88%, rgba(1, 7, 12, 0.45) 0%, transparent 60%);
}

.dr-overlay {
    position: absolute;
    inset: 0;
    z-index: 2;
    display: grid;
    grid-template-rows: auto 1fr auto;
    padding: clamp(1.1rem, 2.6vw, 2.2rem) clamp(1.2rem, 3vw, 3rem);
    pointer-events: none;
}

/* ------------- Brand ------------- */
.dr-brand {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    font-family: 'JetBrains Mono', 'SF Mono', ui-monospace, monospace;
    font-size: 0.72rem;
    font-weight: 500;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: rgba(234, 242, 243, 0.72);
}
.dr-brand__mark {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #54f4da;
    filter: drop-shadow(0 0 6px rgba(84, 244, 218, 0.35));
}
.dr-brand__word { color: rgba(234, 242, 243, 0.95); }
.dr-brand__divider { opacity: 0.45; }
.dr-brand__project { color: rgba(234, 242, 243, 0.6); }

/* ------------- Hero (anti-centre, bottom-left) ------------- */
.dr-hero {
    align-self: end;
    max-width: min(56ch, 74vw);
    display: flex;
    flex-direction: column;
    gap: clamp(0.8rem, 1.8vw, 1.4rem);
    padding-bottom: clamp(1.5rem, 4vw, 3rem);
}

.dr-hero__eyebrow {
    margin: 0;
    font-family: 'JetBrains Mono', 'SF Mono', ui-monospace, monospace;
    font-size: 0.72rem;
    font-weight: 500;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: rgba(84, 244, 218, 0.85);
}

.dr-hero__title {
    margin: 0;
    display: flex;
    flex-direction: column;
    font-weight: 300;
    line-height: 0.94;
    letter-spacing: -0.03em;
    font-size: clamp(2.35rem, 7vw, 5.8rem);
    color: rgba(234, 242, 243, 0.94);
    text-wrap: balance;
}

.dr-hero__line {
    display: block;
    opacity: 0;
    transform: translateY(14px);
    animation: dr-rise-line 900ms cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: calc(280ms + var(--i) * 150ms);
}
.dr-hero__line--accent {
    color: #54f4da;
    font-weight: 500;
    font-style: italic;
}

.dr-hero__lead {
    margin: 0;
    max-width: 44ch;
    font-size: clamp(0.98rem, 1.35vw, 1.15rem);
    line-height: 1.55;
    font-weight: 300;
    color: rgba(234, 242, 243, 0.72);
    opacity: 0;
    transform: translateY(10px);
    animation: dr-rise-line 900ms cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: 780ms;
    text-wrap: pretty;
}

@keyframes dr-rise-line {
    to { opacity: 1; transform: translateY(0); }
}

/* ------------- Meta strip (bottom, split) ------------- */
.dr-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.2rem;
    font-family: 'JetBrains Mono', 'SF Mono', ui-monospace, monospace;
    font-size: 0.7rem;
    font-weight: 500;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: rgba(234, 242, 243, 0.6);
}
.dr-meta__group {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    font-variant-numeric: tabular-nums;
}
.dr-meta__group--data {
    flex-wrap: wrap;
    justify-content: flex-end;
}
.dr-meta__label { opacity: 0.7; }
.dr-meta__value {
    color: rgba(234, 242, 243, 0.9);
}
.dr-meta__divider { opacity: 0.3; }

.dr-meta__pulse {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #54f4da;
    box-shadow: 0 0 10px rgba(84, 244, 218, 0.55);
    animation: dr-pulse 3.4s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes dr-pulse {
    0%, 100% { opacity: 0.55; transform: scale(0.85); }
    50%      { opacity: 1.00; transform: scale(1.15); }
}

/* ------------- Loading ------------- */
.dr-loading {
    position: absolute;
    inset: 0;
    z-index: 3;
    display: grid;
    place-items: center;
    gap: 1.2rem;
    background: #01070c;
    grid-auto-flow: row;
    grid-template-rows: auto auto;
    align-content: center;
    justify-items: center;
}

.dr-loading__orb {
    width: clamp(90px, 18vw, 160px);
    height: clamp(90px, 18vw, 160px);
    border-radius: 50%;
    background:
        radial-gradient(circle at 50% 58%, rgba(84, 244, 218, 0.35) 0%, rgba(84, 244, 218, 0.08) 40%, rgba(84, 244, 218, 0) 72%),
        radial-gradient(circle at 50% 50%, rgba(30, 100, 140, 0.25) 0%, rgba(30, 100, 140, 0) 75%);
    filter: blur(0.4px);
    animation: dr-breathe 2.6s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.dr-loading__label {
    margin: 0;
    font-family: 'JetBrains Mono', 'SF Mono', ui-monospace, monospace;
    font-size: 0.7rem;
    letter-spacing: 0.26em;
    text-transform: uppercase;
    color: rgba(234, 242, 243, 0.55);
}

@keyframes dr-breathe {
    0%, 100% { opacity: 0.55; transform: scale(0.90); }
    50%      { opacity: 1.00; transform: scale(1.06); }
}

/* ------------- Error ------------- */
.dr-error {
    position: absolute;
    inset: 0;
    z-index: 4;
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 1rem;
    align-items: start;
    align-content: center;
    justify-content: start;
    padding: clamp(2rem, 6vw, 4rem);
    background: radial-gradient(ellipse at 30% 40%, rgba(12, 24, 32, 0.95) 0%, #01070c 75%);
    color: #eaf2f3;
    max-width: 56ch;
}
.dr-error__mark {
    color: #54f4da;
    margin-top: 0.35rem;
    filter: drop-shadow(0 0 6px rgba(84, 244, 218, 0.3));
}
.dr-error__heading {
    margin: 0 0 0.35rem 0;
    font-size: clamp(1.15rem, 2.4vw, 1.55rem);
    font-weight: 400;
    letter-spacing: -0.02em;
    line-height: 1.25;
}
.dr-error__detail {
    margin: 0;
    font-family: 'JetBrains Mono', 'SF Mono', ui-monospace, monospace;
    font-size: 0.82rem;
    line-height: 1.55;
    color: rgba(234, 242, 243, 0.6);
    max-width: 50ch;
}

/* ------------- Skip-to-content (keyboard users) ------------- */
.dr-skip {
    position: absolute;
    top: 0.6rem;
    left: 0.6rem;
    z-index: 6;
    padding: 0.55rem 0.9rem;
    background: #54f4da;
    color: #01070c;
    font-family: 'JetBrains Mono', 'SF Mono', ui-monospace, monospace;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    text-decoration: none;
    border-radius: 4px;
    transform: translateY(-200%);
    transition: transform 220ms cubic-bezier(0.16, 1, 0.3, 1);
}
.dr-skip:focus-visible {
    transform: translateY(0);
    outline: 2px solid rgba(1, 7, 12, 0.65);
    outline-offset: 2px;
}

/* ------------- Transitions ------------- */
.dr-fade-enter-from, .dr-fade-leave-to { opacity: 0; }
.dr-fade-enter-active, .dr-fade-leave-active {
    transition: opacity 380ms cubic-bezier(0.16, 1, 0.3, 1);
}

.dr-rise-enter-from { opacity: 0; transform: translateY(6px); }
.dr-rise-enter-active {
    transition: opacity 520ms cubic-bezier(0.16, 1, 0.3, 1),
                transform 520ms cubic-bezier(0.16, 1, 0.3, 1);
}

/* ------------- Reduced-motion respect ------------- */
@media (prefers-reduced-motion: reduce) {
    .dr-hero__line,
    .dr-hero__lead,
    .dr-meta__pulse,
    .dr-loading__orb {
        animation: none !important;
    }
    .dr-hero__line,
    .dr-hero__lead {
        opacity: 1;
        transform: none;
    }
    .dr-meta__pulse {
        opacity: 0.85;
        transform: none;
    }
    .dr-fade-enter-active,
    .dr-fade-leave-active,
    .dr-rise-enter-active {
        transition: none !important;
    }
}

/* ------------- Narrow viewports ------------- */
@media (max-width: 640px) {
    .dr-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.6rem;
    }
    .dr-meta__group--data {
        justify-content: flex-start;
    }
    .dr-hero__lead {
        font-size: 1rem;
    }
}
</style>
