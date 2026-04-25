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

const uniforms: Record<string, WebGLUniformLocation | null> = {};

const vertSrc = /* glsl */ `
attribute vec2 aPos;
void main() { gl_Position = vec4(aPos, 0.0, 1.0); }
`;

/**
 * Design R&D — signature scene. Two real PNG textures, brought alive with
 * layered procedural motion that reads as a slow, deep ocean.
 *
 *  BACKGROUND (ocean-texture.png)
 *    • aspect-correct "cover" UV so it fills any viewport
 *    • three overlapping drift channels (two sine waves + vertical current) so
 *      the motion never loops obviously
 *    • two fbm flow fields at different scales/speeds warp the sample UV —
 *      the fixed ripples in the photo visibly *flow* under this
 *    • gentle breathing zoom (two harmonics)
 *    • subtle cyan brand tint via screen-blend radial
 *
 *  ORB (orb-texture.png)
 *    • slow figure-eight float + breathing scale
 *    • very subtle UV shimmer so the interior churns rather than sits
 *    • soft brightness pulse
 *    • screen-blended over the water so the PNG's dark border is naturally
 *      transparent and its starfield reads as marine-snow particles
 *
 *  FINISH
 *    • gentle vignette, Reinhard tonemap, light gamma lift, 1-unit grain
 *
 * Orb position / scale are uniforms so motion can be driven from Vue later.
 */
const fragSrc = /* glsl */ `
precision highp float;

uniform vec2      uRes;
uniform float     uTime;
uniform sampler2D uBgTex;
uniform sampler2D uOrbTex;
uniform vec2      uBgSize;
uniform vec2      uOrbCenter;
uniform float     uOrbScale;

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

// Aspect-correct "cover" UV for a source texture.
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
    vec2 fc   = gl_FragCoord.xy;
    vec2 uv01 = fc / uRes;
    vec2 uv   = (fc - 0.5 * uRes) / min(uRes.x, uRes.y);  // aspect-neutral centred

    float t = uTime;

    // =====================================================================
    //  BACKGROUND — slow ocean, alive.
    // =====================================================================
    // Three non-commensurate drift channels so the loop never obviously repeats.
    vec2 bgDrift = vec2(
        sin(t * 0.043) * 0.0045 + sin(t * 0.071) * 0.0020,
        t * 0.0025            + sin(t * 0.037) * 0.0060
    );

    // Breathing zoom with two harmonics (keeps the "breath" from feeling
    // mechanical). Amplitude deliberately small — ocean swells, not pump.
    float zoom = 1.0
               + 0.018 * sin(t * 0.055)
               + 0.009 * sin(t * 0.133 + 1.2);

    // Flow field — two fbm layers at different scales moving opposite directions.
    // This is the single biggest driver of "alive water" because every pixel's
    // sample position is constantly being nudged by a slow, organic vector field.
    vec2 flowLo = vec2(
        fbm2(uv * 1.4 + vec2(0.0,  t * 0.060)),
        fbm2(uv * 1.4 + vec2(5.2,  t * 0.060) + vec2(1.3, 0.0))
    ) - 0.5;

    vec2 flowHi = vec2(
        fbm2(uv * 3.6 + vec2(0.0, -t * 0.090)),
        fbm2(uv * 3.6 + vec2(7.1, -t * 0.090) + vec2(3.2, 0.0))
    ) - 0.5;

    vec2 flow = flowLo * 0.0140 + flowHi * 0.0065;

    // Final background sample.
    vec2 bgUV = coverUV(uv01, uBgSize, uRes);
    bgUV = (bgUV - 0.5) / zoom + 0.5 + bgDrift + flow;
    bgUV = clamp(bgUV, 0.0, 1.0);
    vec3 bg = texture2D(uBgTex, bgUV).rgb;

    // Brand cyan (#54f4da) — very soft radial screen-tint centred in the
    // upper third, modulated by a slow breath so the atmosphere itself shifts.
    float tintBreath = 0.85 + 0.15 * sin(t * 0.09);
    vec2  tintC      = vec2(0.0, 0.28);
    float tintRadial = smoothstep(1.30, 0.05, length(uv - tintC));
    vec3  brandTint  = vec3(0.329, 0.957, 0.855) * tintRadial * 0.17 * tintBreath;
    bg = 1.0 - (1.0 - bg) * (1.0 - brandTint);

    // =====================================================================
    //  ORB — floats, breathes, pulses.
    // =====================================================================
    // Figure-eight float: two sinusoids on x and y at non-commensurate rates.
    vec2 orbFloat = vec2(
        sin(t * 0.11)  * 0.0085,
        sin(t * 0.155) * 0.0130 + cos(t * 0.083) * 0.0055
    );
    vec2  orbC     = uOrbCenter + orbFloat;
    float orbScale = uOrbScale * (1.0 + 0.016 * sin(t * 0.23));

    vec2  orbLocal = (uv - orbC) / orbScale;
    float d        = length(orbLocal);

    // Subtle fluid shimmer so the orb interior churns gently.
    vec2 shim = vec2(
        fbm2(orbLocal * 2.6 + t * 0.13),
        fbm2(orbLocal * 2.6 + vec2(9.1, 2.3) + t * 0.13)
    ) - 0.5;

    // Clamp texture read; use a rectangular mask to ignore anything outside
    // the orb PNG bounds so we don't bleed stretched edge pixels.
    vec2 orbUV = orbLocal * 0.5 + 0.5 + shim * 0.009;
    float inRect = step(0.0, orbUV.x) * step(orbUV.x, 1.0)
                 * step(0.0, orbUV.y) * step(orbUV.y, 1.0);

    vec3 orbSample = texture2D(uOrbTex, clamp(orbUV, 0.0, 1.0)).rgb;

    // Soft luminance pulse — 8% amplitude over ~20s.
    float pulse    = 0.94 + 0.08 * sin(t * 0.32);
    vec3  orbColor = orbSample * pulse * inRect;

    // Screen blend: the PNG's dark border is effectively neutral, only the
    // luminous orb body and its embedded stars add to the water.
    vec3 scene = 1.0 - (1.0 - bg) * (1.0 - orbColor);

    // Gentle ambient light spill around the orb — ties it to the water.
    float spill = exp(-d * 2.8) * 0.35 + exp(-d * 6.5) * 0.50;
    scene += vec3(0.14, 0.85, 0.95) * spill * 0.18;

    // Seafloor light pool beneath the orb, breathing with the pulse.
    float floorY      = orbC.y - orbScale * 1.08;
    float floorMask   = 1.0 - smoothstep(floorY - 0.32, floorY + 0.08, uv.y);
    float floorRadial = exp(-pow((uv.x - orbC.x) * 2.1, 2.0));
    scene += vec3(0.06, 0.38, 0.44) * floorMask * floorRadial * 0.32 * pulse;

    // =====================================================================
    //  FINISH
    // =====================================================================
    float vign = smoothstep(1.35, 0.30, length(uv));
    scene *= mix(0.55, 1.00, vign);

    scene = scene / (1.0 + scene);              // Reinhard tonemap
    scene = pow(scene, vec3(0.90));              // gentle gamma lift
    scene = max(scene - 0.002, 0.0);             // slight black crush

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
        img.onerror = reject;
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
    gl.uniform2f(uniforms.uOrbCenter, 0.0, -0.08);
    gl.uniform1f(uniforms.uOrbScale, 0.30);
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
