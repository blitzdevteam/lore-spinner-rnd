<script setup lang="ts">
import oceanUrl from '@/assets/design-rnd/ocean-texture.png';
import orbUrl from '@/assets/design-rnd/orb-texture.png';
import { Head } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const canvas = ref<HTMLCanvasElement | null>(null);

let gl: WebGLRenderingContext | null = null;
let program: WebGLProgram | null = null;
let raf = 0;
let startTime = 0;
let bgTexSize: [number, number] = [1, 1];
let orbTexSize: [number, number] = [1, 1];

const uniforms: Record<string, WebGLUniformLocation | null> = {};

const vertSrc = /* glsl */ `
attribute vec2 aPos;
void main() { gl_Position = vec4(aPos, 0.0, 1.0); }
`;

/**
 * Hybrid scene: the photoreal references in /resources/js/assets/design-rnd
 * (ocean-texture.png + orb-texture.png) are sampled as WebGL textures and
 * brought to life by layering animated procedural effects on top:
 *
 *  - slow drift + breathing zoom on the ocean background
 *  - 2D fbm flow-field warping the background UVs (the water "moves")
 *  - brand-cyan radial screen overlay (from the JSX, #54f4da)
 *  - orb texture sampled with fluid shimmer + chromatic dispersion at the rim
 *  - breathing pulse on the orb luminance
 *  - procedural rising bubbles around the orb
 *  - wide radial light spill + seafloor light pool under the orb
 *  - filmic tonemap, vignette, film grain
 *
 * Orb position and size are uniforms so they can be animated/transitioned
 * from the Vue side later (scroll, route change, pointer follow, etc.).
 */
const fragSrc = /* glsl */ `
precision highp float;

uniform vec2      uRes;
uniform float     uTime;
uniform sampler2D uBgTex;
uniform sampler2D uOrbTex;
uniform vec2      uBgSize;      // source bg texture pixels
uniform vec2      uOrbCenter;   // orb centre in aspect-neutral UV
uniform float     uOrbScale;    // orb radius in aspect-neutral UV

// ----- 2D value noise -----
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
        mix(hash21(i),                    hash21(i + vec2(1.0, 0.0)), f.x),
        mix(hash21(i + vec2(0.0, 1.0)),   hash21(i + vec2(1.0, 1.0)), f.x),
        f.y
    );
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

// ----- "Cover" UV for a source texture in an arbitrary-aspect viewport -----
vec2 coverUV(vec2 uv01, vec2 srcSize, vec2 dstSize) {
    float srcA = srcSize.x / srcSize.y;
    float dstA = dstSize.x / dstSize.y;
    vec2 r = uv01;
    if (dstA > srcA) {
        float s = srcA / dstA;
        r.y = 0.5 + (uv01.y - 0.5) * s;
    } else {
        float s = dstA / srcA;
        r.x = 0.5 + (uv01.x - 0.5) * s;
    }
    return r;
}

void main() {
    vec2 fc = gl_FragCoord.xy;
    vec2 uv01 = fc / uRes;
    vec2 uv   = (fc - 0.5 * uRes) / min(uRes.x, uRes.y); // y-up, origin centre, aspect-neutral

    // ============ BACKGROUND (texture + animation) ============
    // Slow breathing zoom and drift so the still PNG feels alive.
    float bgZoom = 1.0 + 0.025 * sin(uTime * 0.08);
    vec2  bgBase = coverUV(uv01, uBgSize, uRes);
    bgBase = (bgBase - 0.5) / bgZoom + 0.5;
    bgBase.y += uTime * 0.0012;
    bgBase.x += sin(uTime * 0.04) * 0.004;

    // Animated refraction flow — 2D fbm offset nudges the sampled water,
    // so static ripples in the PNG subtly swim as if lit from shifting caustics.
    vec2 flow = vec2(
        fbm2(uv * 2.8 + uTime * 0.06),
        fbm2(uv * 2.8 + vec2(5.2, 1.3) + uTime * 0.06)
    ) - 0.5;

    vec2 bgUV = clamp(bgBase + flow * 0.006, 0.0, 1.0);
    vec3 bg   = texture2D(uBgTex, bgUV).rgb;

    // Brand cyan (#54f4da ≈ vec3(0.329, 0.957, 0.855)) — radial screen overlay.
    // Echoes the OceanTexture.jsx spec.
    vec2  tintCentre = vec2(0.0, 0.30);
    float tintRadial = smoothstep(1.35, 0.00, length(uv - tintCentre));
    vec3  brandTint  = vec3(0.329, 0.957, 0.855) * tintRadial * 0.22;
    bg = 1.0 - (1.0 - bg) * (1.0 - brandTint);

    // ============ ORB (texture + animation) ============
    vec2  orbLocal = (uv - uOrbCenter) / uOrbScale;     // -1..1 orb-space
    float d        = length(orbLocal);

    // Fluid shimmer — animated 2D flow offset for the orb texture sample.
    vec2 orbShim = vec2(
        fbm2(orbLocal * 3.2 + uTime * 0.14),
        fbm2(orbLocal * 3.2 + vec2(7.3, 1.1) + uTime * 0.14)
    ) - 0.5;

    // Orb tex UV: orbLocal (-1..1) → (0..1), plus shimmer.
    vec2 orbTexUV = orbLocal * 0.5 + 0.5 + orbShim * 0.013;

    // Mask so contributions only occur where we're actually in orb texture space.
    float inOrb = step(0.0, orbTexUV.x) * step(orbTexUV.x, 1.0)
                * step(0.0, orbTexUV.y) * step(orbTexUV.y, 1.0);
    vec2  sampleUV = clamp(orbTexUV, 0.0, 1.0);

    // Chromatic dispersion near the rim — the "glass lens" tell.
    float disp    = 0.0065 * smoothstep(0.40, 1.00, d);
    vec2  dispDir = normalize(orbLocal + 1e-4);

    vec3 orbSample;
    orbSample.r = texture2D(uOrbTex, clamp(sampleUV + dispDir * disp, 0.0, 1.0)).r;
    orbSample.g = texture2D(uOrbTex, sampleUV).g;
    orbSample.b = texture2D(uOrbTex, clamp(sampleUV - dispDir * disp, 0.0, 1.0)).b;

    // Breathing pulse + gentle lift.
    float pulse = 0.93 + 0.10 * sin(uTime * 0.55);
    vec3  orbColor = orbSample * pulse * 1.08 * inOrb;

    // Screen-blend the orb over the water — black pixels of the PNG are neutral,
    // luminous veins/hotspot add on top exactly like a light source.
    vec3 sceneColor = 1.0 - (1.0 - bg) * (1.0 - orbColor);

    // Wide radial light spill: the orb's light leaks into the surrounding water.
    float spill = exp(-d * 2.5) * 0.40 + exp(-d * 6.0) * 0.50;
    sceneColor += vec3(0.14, 0.93, 0.85) * spill * 0.22;

    // Seafloor light pool beneath the orb (inverted smoothstep is 1 below, 0 above).
    float floorY      = uOrbCenter.y - uOrbScale * 1.05;
    float floorMask   = 1.0 - smoothstep(floorY - 0.30, floorY + 0.10, uv.y);
    float floorRadial = exp(-pow((uv.x - uOrbCenter.x) * 2.0, 2.0));
    sceneColor += vec3(0.08, 0.48, 0.48) * floorMask * floorRadial * 0.32;

    // ============ RISING BUBBLES (procedural) ============
    for (int k = 0; k < 2; k++) {
        float scale = 8.0 + float(k) * 6.0;
        float speed = 0.10 + float(k) * 0.05;
        vec2 g = uv * scale + vec2(float(k) * 2.7, -uTime * speed);
        vec2 id = floor(g);
        vec2 f = fract(g) - 0.5;
        float rnd = hash21(id + float(k) * 11.13);
        if (rnd > 0.78) {
            vec2 off = vec2(hash21(id + 1.3) - 0.5, hash21(id + 2.7) - 0.5) * 0.55;
            float r = 0.025 + (rnd - 0.78) * 0.12;
            float dd = length(f - off);
            float b = smoothstep(r, r * 0.55, dd);
            float life = fract(g.y);
            b *= smoothstep(0.0, 0.25, life) * smoothstep(1.0, 0.75, life);
            // Concentrate bubbles near the orb + in the visible upper water.
            float zone = exp(-pow(length(uv - uOrbCenter) * 1.4, 2.0))
                       * smoothstep(-0.8, 0.2, uv.y);
            sceneColor += vec3(0.30, 0.90, 0.92) * b * zone * 0.65 * (0.35 + rnd * 0.65);
        }
    }

    // ============ CINEMATIC POST ============
    float vign = smoothstep(1.30, 0.25, length(uv));
    sceneColor *= mix(0.45, 1.00, vign);

    sceneColor = sceneColor / (1.0 + sceneColor);       // Reinhard tonemap
    sceneColor = pow(sceneColor, vec3(0.88));           // gentle gamma lift
    sceneColor = max(sceneColor - 0.004, 0.0);          // gentle black crush

    float grain = (hash21(fc + uTime * 57.0) - 0.5) * 0.012;
    sceneColor += grain;

    gl_FragColor = vec4(sceneColor, 1.0);
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
        img.onerror = reject;
        img.src = url;
    });
}

function createTex(glCtx: WebGLRenderingContext, img: HTMLImageElement): WebGLTexture {
    const tex = glCtx.createTexture()!;
    glCtx.bindTexture(glCtx.TEXTURE_2D, tex);
    glCtx.pixelStorei(glCtx.UNPACK_FLIP_Y_WEBGL, true);
    glCtx.texImage2D(glCtx.TEXTURE_2D, 0, glCtx.RGBA, glCtx.RGBA, glCtx.UNSIGNED_BYTE, img);
    // Non-power-of-two friendly settings.
    glCtx.texParameteri(glCtx.TEXTURE_2D, glCtx.TEXTURE_MIN_FILTER, glCtx.LINEAR);
    glCtx.texParameteri(glCtx.TEXTURE_2D, glCtx.TEXTURE_MAG_FILTER, glCtx.LINEAR);
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

function frame(t: number) {
    if (!gl || !program) return;
    if (!startTime) startTime = t;
    resize();

    const time = (t - startTime) * 0.001;

    gl.useProgram(program);
    gl.uniform2f(uniforms.uRes, gl.drawingBufferWidth, gl.drawingBufferHeight);
    gl.uniform1f(uniforms.uTime, time);
    gl.uniform2f(uniforms.uBgSize, bgTexSize[0], bgTexSize[1]);
    // Default orb framing — will become animatable later.
    gl.uniform2f(uniforms.uOrbCenter, 0.0, -0.06);
    gl.uniform1f(uniforms.uOrbScale, 0.26);
    gl.drawArrays(gl.TRIANGLES, 0, 6);

    raf = requestAnimationFrame(frame);
}

onMounted(async () => {
    if (!canvas.value) return;
    const ctx = canvas.value.getContext('webgl', {
        antialias: true,
        premultipliedAlpha: false,
        powerPreference: 'high-performance',
    });
    if (!ctx) {
        console.error('WebGL not supported');
        return;
    }
    gl = ctx;

    const vs = compile(gl, gl.VERTEX_SHADER, vertSrc);
    const fs = compile(gl, gl.FRAGMENT_SHADER, fragSrc);
    program = link(gl, vs, fs);
    gl.useProgram(program);

    // Fullscreen triangle pair.
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
    uniforms.uBgSize = gl.getUniformLocation(program, 'uBgSize');
    uniforms.uOrbCenter = gl.getUniformLocation(program, 'uOrbCenter');
    uniforms.uOrbScale = gl.getUniformLocation(program, 'uOrbScale');

    try {
        const [bgImg, orbImg] = await Promise.all([loadImage(oceanUrl), loadImage(orbUrl)]);
        const bgTex = createTex(gl, bgImg);
        const orbTex = createTex(gl, orbImg);
        bgTexSize = [bgImg.naturalWidth, bgImg.naturalHeight];
        orbTexSize = [orbImg.naturalWidth, orbImg.naturalHeight];

        gl.activeTexture(gl.TEXTURE0);
        gl.bindTexture(gl.TEXTURE_2D, bgTex);
        gl.uniform1i(uniforms.uBgTex, 0);

        gl.activeTexture(gl.TEXTURE1);
        gl.bindTexture(gl.TEXTURE_2D, orbTex);
        gl.uniform1i(uniforms.uOrbTex, 1);
    } catch (e) {
        console.error('Failed to load design-rnd textures', e);
        return;
    }

    raf = requestAnimationFrame(frame);
});

onBeforeUnmount(() => {
    if (raf) cancelAnimationFrame(raf);
    if (gl && program) gl.deleteProgram(program);
    gl = null;
    program = null;
});
</script>

<template>
    <Head title="Design R&D — Orb" />

    <main class="scene">
        <canvas ref="canvas" class="orb-canvas" />
    </main>
</template>

<style scoped>
.scene {
    position: fixed;
    inset: 0;
    background: #000103;
    overflow: hidden;
}

.orb-canvas {
    display: block;
    width: 100%;
    height: 100%;
}
</style>
