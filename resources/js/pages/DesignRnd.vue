<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const canvas = ref<HTMLCanvasElement | null>(null);

let gl: WebGLRenderingContext | null = null;
let program: WebGLProgram | null = null;
let raf = 0;
let startTime = 0;

const uniforms: Record<string, WebGLUniformLocation | null> = {};

const vertSrc = /* glsl */ `
attribute vec2 aPos;
void main() {
    gl_Position = vec4(aPos, 0.0, 1.0);
}
`;

/**
 * Signature page. One background, one orb.
 * Background:  deep ocean seen from below, slow caustic surface shimmer far above,
 *              marine-snow particles, strong bottom vignette.
 * Orb:         fluid-glass lens that refracts the ocean behind it, with an internal
 *              bottom-centered light source, a shattered rippling water rim, and
 *              debris flakes drifting around the boundary.
 *
 * Everything is procedural — volumetric-feeling 3D fbm with domain warping for the
 * ocean texture and orb interior, spherical refraction for the lens, animated fracture
 * noise for the rim. No external assets. Movement is deliberately slow & cinematic.
 */
const fragSrc = /* glsl */ `
precision highp float;

uniform vec2  uRes;
uniform float uTime;

// ----- hashing & noise -----
float hash31(vec3 p) {
    p = fract(p * 0.3183099 + vec3(0.71, 0.113, 0.419));
    p *= 17.0;
    return fract(p.x * p.y * p.z * (p.x + p.y + p.z));
}
float hash21(vec2 p) {
    p = fract(p * vec2(123.34, 456.21));
    p += dot(p, p + 45.32);
    return fract(p.x * p.y);
}

float noise3(vec3 p) {
    vec3 i = floor(p);
    vec3 f = fract(p);
    f = f * f * (3.0 - 2.0 * f);
    return mix(
        mix(mix(hash31(i + vec3(0.0, 0.0, 0.0)), hash31(i + vec3(1.0, 0.0, 0.0)), f.x),
            mix(hash31(i + vec3(0.0, 1.0, 0.0)), hash31(i + vec3(1.0, 1.0, 0.0)), f.x), f.y),
        mix(mix(hash31(i + vec3(0.0, 0.0, 1.0)), hash31(i + vec3(1.0, 0.0, 1.0)), f.x),
            mix(hash31(i + vec3(0.0, 1.0, 1.0)), hash31(i + vec3(1.0, 1.0, 1.0)), f.x), f.y),
        f.z);
}

float fbm3(vec3 p) {
    float v = 0.0, a = 0.5;
    for (int i = 0; i < 4; i++) {
        v += a * noise3(p);
        p = p * 2.03 + vec3(1.7, 9.2, 3.1);
        a *= 0.5;
    }
    return v;
}

float fbm3Hi(vec3 p) {
    float v = 0.0, a = 0.5;
    for (int i = 0; i < 5; i++) {
        v += a * noise3(p);
        p = p * 2.03 + vec3(1.7, 9.2, 3.1);
        a *= 0.5;
    }
    return v;
}

// Domain-warped fbm: richer, more "painterly" cloud / caustic structure.
float warped(vec3 p) {
    vec3 q = vec3(
        fbm3(p),
        fbm3(p + vec3(5.2, 1.3, 4.1)),
        fbm3(p + vec3(1.7, 9.2, 8.4))
    );
    return fbm3(p + 2.2 * q);
}

// ----- The background: underwater looking up -----
vec3 background(vec2 uv) {
    // Vertical colour ramp: ink-black seafloor below → rich midnight-navy middle →
    // a breath of ocean-blue depth near the top. The whole image stays dark and
    // moody; the surface shimmer doesn't override the darkness, it lives inside it.
    vec3 cBottom = vec3(0.001, 0.005, 0.013);
    vec3 cMid    = vec3(0.004, 0.024, 0.058);
    vec3 cTop    = vec3(0.016, 0.072, 0.142);

    float y = uv.y;
    vec3 col = mix(cBottom, cMid, smoothstep(-0.95, 0.15, y));
    col = mix(col, cTop, smoothstep(0.05, 1.05, y));

    // Slow domain-warped caustics. The warp is sampled at a different time rate
    // than the caustic itself, producing that drifting, layered surface motion.
    float causticMask = smoothstep(-0.20, 0.75, y);

    float w1 = fbm3(vec3(uv * 1.1,               uTime * 0.030));
    float w2 = fbm3(vec3(uv * 1.1 + vec2(5.2,1.3), uTime * 0.025));
    vec2  warp = vec2(w1, w2) - 0.5;

    float caustic = warped(vec3(uv * 2.4 + warp * 1.1, uTime * 0.055));
    caustic = pow(clamp(caustic, 0.0, 1.0), 1.7);

    // Body of the shimmer: a muted blue wash.
    col += vec3(0.045, 0.190, 0.385) * caustic * causticMask * 1.20;

    // Wave crests where the caustic peaks — the bright veins in the reference.
    float crest = smoothstep(0.58, 0.86, caustic) * causticMask;
    col += vec3(0.18, 0.50, 0.78) * crest * 0.85;

    // Finer crest highlights riding on top of the big waves.
    float micro = fbm3(vec3(uv * 7.5 + warp * 2.0, uTime * 0.08));
    float microHi = smoothstep(0.60, 0.82, micro) * causticMask * crest;
    col += vec3(0.30, 0.70, 0.95) * microHi * 0.6;

    // Marine snow — very faint far-field particles, mostly in the mid/lower water.
    float snowMask = smoothstep(0.85, -0.20, y);
    float snow = smoothstep(0.996, 1.0, hash21(floor(uv * 260.0))) * 0.55 * snowMask;
    col += vec3(snow);

    // Bottom crush — the seafloor side fades almost completely to black.
    col *= mix(0.25, 1.0, smoothstep(-1.20, 0.05, y));

    return col;
}

void main() {
    vec2 fc = gl_FragCoord.xy;
    vec2 uv = (fc - 0.5 * uRes) / min(uRes.x, uRes.y); // y-up, origin center, aspect-neutral

    // ---------- BACKGROUND ----------
    vec3 color = background(uv);

    // ---------- ORB ----------
    // Composition: centered, slightly below the optical middle so the caustics
    // read as being *above* and the orb as being *in front*.
    vec2  orbC = vec2(0.0, -0.03);
    float orbR = 0.30;

    vec2  o = (uv - orbC) / orbR;     // normalized orb coords, 1.0 at rim
    float d = length(o);
    float theta = atan(o.y, o.x);

    // Rippling, irregular rim: domain-warped 3D fbm over a polar strip.
    vec3 rimPos = vec3(cos(theta) * 2.3, sin(theta) * 2.3, uTime * 0.10);
    float rimFbm = warped(rimPos);
    float rimAmp = 0.055;
    float edge = 1.0 + (rimFbm - 0.5) * rimAmp;

    // Anti-aliased mask using screen-space edge derivative.
    float aa = fwidth(d) * 1.2;
    float orbMask = 1.0 - smoothstep(edge - aa, edge + aa, d);

    // ---- Inside the orb: fluid-glass lens ----
    if (orbMask > 0.001) {
        // Fake spherical depth — how much this pixel "bulges" toward us on the sphere.
        float z = sqrt(max(1.0 - min(d, 1.0) * min(d, 1.0), 0.0));

        // Lens refraction: compress the sampled background toward the orb center,
        // stronger near the rim. This magnifies and bends the ocean behind it.
        float t = smoothstep(0.0, 1.0, d);
        vec2 refractedUV = orbC + (uv - orbC) * (1.0 - t * 0.30);

        // Fluid shimmer: offset the refracted sample with a slow 2D noise flow.
        vec2 shim = vec2(
            fbm3(vec3(o * 3.2,                uTime * 0.14)),
            fbm3(vec3(o * 3.2 + vec3(7.3,1.1,4.0), uTime * 0.14))
        ) - 0.5;
        refractedUV += shim * 0.030 * orbR;

        // Tiny chromatic dispersion around the lens edges — the "glass" tell.
        float disp  = 0.0050 * (1.0 - z);
        vec2  dispDir = normalize(o + 1e-4);
        vec3 refracted;
        refracted.r = background(refractedUV + dispDir * disp).r;
        refracted.g = background(refractedUV).g;
        refracted.b = background(refractedUV - dispDir * disp).b;

        // Glass focuses light — slight gain so the refracted image is luminous.
        refracted *= 1.22;

        // Internal bottom-centered light source.
        vec2 L   = vec2(0.0, -0.78);
        float Ld = length(o - L);
        float beam = exp(-Ld * 1.55);      // soft upward lift from the hotspot
        float hot  = exp(-Ld * 20.0);      // bright glow core
        float pin  = exp(-Ld * 65.0);      // near-white specular pinpoint

        // Volumetric haze modulated by the beam so the light has something to catch in.
        float haze = warped(vec3(o * 1.9, uTime * 0.075)) * beam;

        vec3 orbColor = refracted;
        orbColor += vec3(0.35, 0.78, 1.00) * haze * 0.95;
        orbColor += vec3(0.75, 0.92, 1.00) * hot  * 1.55;
        orbColor += vec3(1.00, 1.00, 1.00) * pin  * 2.70;

        // Inner edge darkening — the "total internal reflection" look of a glass sphere.
        orbColor *= mix(1.0, 0.55, smoothstep(0.72, 1.00, d));

        // Fresnel-like rim brightening on the *inside* of the lens.
        float fresnel = pow(clamp(d, 0.0, 1.0), 3.5);
        orbColor += vec3(0.25, 0.60, 0.95) * fresnel * 0.35;

        color = mix(color, orbColor, orbMask);
    }

    // ---- Shattered water rim band ----
    float rimInner = edge - 0.042;
    float rimOuter = edge + 0.010;
    float rimBand  = smoothstep(rimInner, edge - 0.010, d)
                   - smoothstep(edge - 0.010, rimOuter, d);
    float rimTex   = pow(clamp(warped(vec3(o * 7.0, uTime * 0.18)), 0.0, 1.0), 1.3);
    color += vec3(0.32, 0.72, 1.00) * rimBand * (0.28 + rimTex * 1.55) * 1.05;

    // ---- Outer flakes / debris (broken-glass chunks just beyond the rim) ----
    float flakeMask = smoothstep(edge + 0.14, edge + 0.00, d)
                    * smoothstep(edge - 0.002, edge + 0.008, d);
    float flakeN    = warped(vec3(o * 11.0, uTime * 0.24));
    float flakes    = smoothstep(0.60, 0.72, flakeN) * flakeMask;
    color += vec3(0.25, 0.60, 0.95) * flakes * 1.35;

    // ---- Soft aura around the orb ----
    float aura = smoothstep(0.55, 0.0, abs(d - 1.0));
    color += vec3(0.10, 0.35, 0.60) * aura * 0.09;

    // ---------- CINEMATIC POST ----------
    // Scene vignette.
    float vign = smoothstep(1.35, 0.30, length(uv));
    color *= mix(0.40, 1.00, vign);

    // Filmic-ish tonemap.
    color = color / (1.0 + color);
    color = pow(color, vec3(0.88));
    color = max(color - 0.004, 0.0);

    // Very subtle film grain — reads as celluloid rather than digital noise.
    float grain = (hash21(fc + uTime * 55.0) - 0.5) * 0.012;
    color += grain;

    gl_FragColor = vec4(color, 1.0);
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

function frame(t: number) {
    if (!gl || !program) return;
    if (!startTime) startTime = t;
    resize();
    const time = (t - startTime) * 0.001;

    gl.useProgram(program);
    gl.uniform2f(uniforms.uRes, gl.drawingBufferWidth, gl.drawingBufferHeight);
    gl.uniform1f(uniforms.uTime, time);
    gl.drawArrays(gl.TRIANGLES, 0, 6);

    raf = requestAnimationFrame(frame);
}

onMounted(() => {
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
    // `fwidth()` in the fragment shader needs OES_standard_derivatives in WebGL1.
    gl.getExtension('OES_standard_derivatives');

    const vs = compile(gl, gl.VERTEX_SHADER, vertSrc);
    // Prepend the derivatives pragma to the fragment source.
    const fsSrc = '#extension GL_OES_standard_derivatives : enable\n' + fragSrc;
    const fs = compile(gl, gl.FRAGMENT_SHADER, fsSrc);
    program = link(gl, vs, fs);

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
    <Head title="Design R&D — Lens" />

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
