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
 * Cinematic signature scene — one background, one orb.
 *
 * BACKGROUND  : dark undersea view with a wide, detailed caustic band across the
 *               upper half (looking up toward the surface), crushed to near-black
 *               in the lower half.
 * ORB         : a self-illuminated teal-cyan energy sphere. Its interior is a
 *               crackling ridged-noise pattern mapped onto the 3D surface of a
 *               sphere (so curvature is readable), lit from a bright hotspot at
 *               the bottom-centre. A shattered water rim breaks the boundary.
 * AMBIENCE    : rising bubbles near the orb, soft teal light pool on the seafloor
 *               beneath it, marine-snow particles, film grain.
 */
const fragSrc = /* glsl */ `
precision highp float;

uniform vec2  uRes;
uniform float uTime;

// ----- hashing -----
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

// ----- value noise 3D -----
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

// Soft fbm (clouds / caustics body)
float fbm3(vec3 p) {
    float v = 0.0, a = 0.5;
    for (int i = 0; i < 4; i++) {
        v += a * noise3(p);
        p = p * 2.03 + vec3(1.7, 9.2, 3.1);
        a *= 0.5;
    }
    return v;
}

// Ridged fbm — produces sharp vein / crackle patterns used for the orb interior
// and the high-frequency ripple detail on the ocean surface.
float ridged(vec3 p) {
    float v = 0.0, a = 0.5, w = 1.0;
    for (int i = 0; i < 5; i++) {
        float n = 1.0 - abs(2.0 * noise3(p) - 1.0); // ridge
        n = n * n;                                   // sharpen
        v += a * n * w;
        w = clamp(n * 1.4, 0.0, 1.0);               // amplitude feedback for natural veins
        p = p * 2.08 + vec3(1.7, 9.2, 3.1);
        a *= 0.55;
    }
    return v;
}

// Domain-warped fbm — richer caustic structure.
float warped(vec3 p) {
    vec3 q = vec3(
        fbm3(p),
        fbm3(p + vec3(5.2, 1.3, 4.1)),
        fbm3(p + vec3(1.7, 9.2, 8.4))
    );
    return fbm3(p + 2.2 * q);
}

// ----- BACKGROUND : undersea ----------------------------------------------------
// Look is driven by a *radial* light source at upper-centre (a distant surface
// punch-through), tight high-freq ridged ripples for fabric-like water detail,
// and aggressive darkness crushing in from every direction outside that pool.
vec3 background(vec2 uv) {
    float y = uv.y;

    // Virtual "sun on the surface" — upper-centre.
    vec2  sunPos = vec2(0.0, 0.55);
    float sd     = length(uv - sunPos);

    // Soft central glow shape.
    float glowNear = exp(-sd * 3.2);
    float glowFar  = exp(-sd * 1.15);
    float glow     = glowNear * 0.55 + glowFar * 0.70;

    // Base: near-pure black.
    vec3 col = vec3(0.0010, 0.0030, 0.0060);

    // Ripple visibility mask: ripples only exist where the distant light reaches,
    // biased toward the upper half.
    float rippleMask = smoothstep(0.0, 1.0, glow * 2.2)
                     * smoothstep(-0.45, 0.85, y);
    rippleMask = clamp(rippleMask, 0.0, 1.0);

    // Slow large-scale flow warp — the slow body motion.
    float w1 = fbm3(vec3(uv * 0.80,                uTime * 0.022));
    float w2 = fbm3(vec3(uv * 0.80 + vec2(5.2, 1.3), uTime * 0.018));
    vec2  warp = vec2(w1, w2) - 0.5;

    // Three ripple layers stacked for that "velvet / fabric" water detail.
    // Mid-freq ridged ripple (the dominant pattern).
    float ripple  = ridged(vec3(uv * 7.5 + warp * 1.9, uTime * 0.065));
    ripple = pow(clamp(ripple, 0.0, 1.0), 1.05);

    // High-freq micro ripple — fine grain sitting on the ridges.
    float micro   = ridged(vec3(uv * 16.0 + warp * 3.0, uTime * 0.110));
    micro = pow(clamp(micro, 0.0, 1.0), 1.35);

    // Very low-freq swell giving volumetric body under the ripples.
    float swell   = fbm3(vec3(uv * 1.7 + warp * 0.6, uTime * 0.032));
    swell = pow(clamp(swell, 0.0, 1.0), 2.0);

    float wave = ripple * 0.55 + micro * 0.25 + swell * 0.35;

    // Wave body colour — subdued teal wash, applied only within the glow pool.
    col += vec3(0.022, 0.110, 0.150) * wave * rippleMask * 1.05;

    // Sharp crest highlights — the bright veins in the surface texture.
    float crest = smoothstep(0.58, 0.82, ripple) * rippleMask;
    col += vec3(0.080, 0.300, 0.400) * crest * 0.85;

    // Micro sparkle riding on the crests.
    float microHi = smoothstep(0.65, 0.86, micro) * crest;
    col += vec3(0.160, 0.500, 0.600) * microHi * 0.65;

    // Central ambient glow — the "sun through the surface".
    col += vec3(0.028, 0.118, 0.165) * exp(-sd * 2.0) * 0.75;
    col += vec3(0.090, 0.260, 0.340) * exp(-sd * 5.2) * 0.35;

    // RADIAL darkness: outside the glow pool, crush hard toward black. This is
    // what gives the reference its dome-like feeling of light concentrated in
    // one spot and the rest of the water swallowed by depth.
    float radialDark = smoothstep(0.25, 1.40, sd);
    col *= mix(1.00, 0.16, radialDark);

    // Bottom crush — absolute darkness at the seafloor.
    col *= mix(0.12, 1.00, smoothstep(-1.15, 0.05, y));

    // Marine-snow particles — sparse, biased toward the upper / mid water.
    float snow = smoothstep(0.996, 1.0, hash21(floor(uv * 300.0))) * 0.40;
    snow *= smoothstep(0.95, -0.4, y);
    col += vec3(snow);

    return col;
}

// Rising-bubbles field — tiled motion with time flow upward.
float bubbles(vec2 uv) {
    float s = 0.0;
    for (int k = 0; k < 2; k++) {
        float scale = 8.0 + float(k) * 6.0;
        float speed = 0.10 + float(k) * 0.05;

        vec2 g = uv * scale + vec2(float(k) * 2.7, -uTime * speed);
        vec2 id = floor(g);
        vec2 f = fract(g) - 0.5;

        float rnd = hash21(id + float(k) * 11.13);
        if (rnd > 0.78) {
            vec2 off = vec2(hash21(id + 1.3) - 0.5, hash21(id + 2.7) - 0.5) * 0.55;
            float r  = 0.025 + (rnd - 0.78) * 0.12;
            float d  = length(f - off);
            float b  = smoothstep(r, r * 0.55, d);
            // fade in over cell life so bubbles don't pop
            float life = fract(g.y);
            b *= smoothstep(0.0, 0.25, life) * smoothstep(1.0, 0.75, life);
            s += b * (0.35 + rnd * 0.65);
        }
    }
    return s;
}

void main() {
    vec2 fc = gl_FragCoord.xy;
    vec2 uv = (fc - 0.5 * uRes) / min(uRes.x, uRes.y); // y-up, origin centre

    // ----- SCENE BACKGROUND -----
    vec3 color = background(uv);

    // ----- ORB -----
    vec2  orbC = vec2(0.0, -0.06);  // slightly below centre for cinematic framing
    float orbR = 0.22;               // ~22% of min viewport dimension

    vec2  o = (uv - orbC) / orbR;    // orb-local coords, rim at d=1
    float d = length(o);
    float theta = atan(o.y, o.x);

    // --- 3D sphere surface coordinates ---
    // Fake the depth z for each pixel inside the sphere so texture can be sampled
    // on the true 3D surface. This is what gives the orb its spherical readable
    // form instead of looking like a flat disc.
    float dClamp = min(d, 1.0);
    float z = sqrt(max(1.0 - dClamp * dClamp, 0.0));
    vec3 sphereP = vec3(o.x, o.y, z);

    // --- Rippling, fractured rim ---
    vec3 rimPos = vec3(cos(theta) * 2.1, sin(theta) * 2.1, uTime * 0.09);
    float rimFbm = warped(rimPos);
    float rimAmp = 0.050;
    float edge = 1.0 + (rimFbm - 0.5) * rimAmp;

    // AA'd orb mask
    float aa = fwidth(d) * 1.2;
    float orbMask = 1.0 - smoothstep(edge - aa, edge + aa, d);

    vec3 orbColor = vec3(0.0);

    if (orbMask > 0.001) {
        // === Crackling interior mapped onto the sphere surface ===
        // Sampling at sphereP means the pattern bends around the sphere's curvature,
        // exactly like the reference's visible "latitude" striations.
        vec3 texP = sphereP;
        texP.z += uTime * 0.04;                      // slow drift along the z axis
        float crackA = ridged(texP * 2.4);            // broad veins
        float crackB = ridged(texP * 5.8 + vec3(9.3, 2.1, 4.7)); // fine fractures
        float crack  = crackA * 0.60 + crackB * 0.55;

        // === Internal hotspot at the bottom ===
        vec2  L  = vec2(0.0, -0.78);
        float Ld = length(o - L);
        float beam = exp(-Ld * 1.45);
        float hot  = exp(-Ld * 22.0);
        float pin  = exp(-Ld * 75.0);

        // Energy = crackle lit by the internal beam.
        float energy = crack * (0.35 + beam * 1.70);

        // === Teal / cyan palette ===
        vec3 cDark  = vec3(0.003, 0.030, 0.040);     // near-black teal
        vec3 cMid   = vec3(0.030, 0.380, 0.400);     // deep teal
        vec3 cHigh  = vec3(0.220, 0.930, 0.900);     // bright cyan veins
        vec3 cWhite = vec3(0.900, 1.000, 0.990);     // luminous core

        orbColor = mix(cDark,   cMid,   smoothstep(0.00, 0.40, energy));
        orbColor = mix(orbColor, cHigh, smoothstep(0.40, 1.20, energy));
        orbColor = mix(orbColor, cWhite, smoothstep(1.30, 2.40, energy));

        // Hotspot + pinpoint.
        orbColor += vec3(0.70, 0.98, 1.00) * hot * 1.70;
        orbColor += cWhite                 * pin * 3.20;

        // Spherical shading cue — ever so slight darkening toward the rim so the
        // sphere feels voluminous (the crackle already carries most of the form).
        orbColor *= mix(1.00, 0.72, smoothstep(0.70, 1.00, d));

        // Fresnel-style rim brightening — bright cyan ring where the glass is
        // "catching" light tangentially. This plus the rim-band below is what
        // reads as the visible sphere shell.
        float fresnel = pow(clamp(d, 0.0, 1.0), 3.0);
        orbColor += vec3(0.20, 0.78, 0.80) * fresnel * 0.55;
    }

    // ----- Shattered water rim band (on top of everything) -----
    float rimInner = edge - 0.038;
    float rimOuter = edge + 0.009;
    float rimBand  = smoothstep(rimInner, edge - 0.009, d)
                   - smoothstep(edge - 0.009, rimOuter, d);
    float rimTex   = pow(clamp(ridged(vec3(o * 6.5, uTime * 0.17)), 0.0, 1.0), 1.2);
    vec3  rimCol   = vec3(0.22, 0.88, 0.85) * rimBand * (0.32 + rimTex * 1.55) * 1.05;

    // ----- Debris flakes just outside the rim -----
    float flakeMask = smoothstep(edge + 0.14, edge + 0.00, d)
                    * smoothstep(edge - 0.002, edge + 0.008, d);
    float flakeN    = ridged(vec3(o * 11.0, uTime * 0.22));
    float flakes    = smoothstep(0.62, 0.80, flakeN) * flakeMask;
    vec3  flakeCol  = vec3(0.20, 0.78, 0.82) * flakes * 1.40;

    // ----- Ambient teal aura around the orb -----
    float aura = smoothstep(0.55, 0.0, abs(d - 1.0));
    vec3  auraCol = vec3(0.05, 0.40, 0.42) * aura * 0.18;

    // ----- Wide radial light spill (illuminates bubbles / water around orb) -----
    vec2  hotWorld = orbC + vec2(0.0, -orbR * 0.78);
    float hotDist  = length(uv - hotWorld);
    float spill    = exp(-hotDist * 3.0) * 0.55
                   + exp(-hotDist * 8.0) * 0.55;

    // ----- Light pool on the "seafloor" beneath the orb -----
    float floorY      = orbC.y - orbR * 1.05;
    float floorMask   = smoothstep(floorY + 0.10, floorY - 0.25, uv.y);
    float floorRadial = exp(-pow((uv.x - orbC.x) * 2.2, 2.0));
    vec3  floorPool   = vec3(0.05, 0.42, 0.44) * floorMask * floorRadial * 0.45;

    // ----- Rising bubbles, mostly near the orb column -----
    float bubbleField = bubbles(uv);
    float bubbleZone  = exp(-pow((uv.x - orbC.x) * 1.6, 2.0)) *
                        smoothstep(-0.8, 0.2, uv.y);
    vec3  bubbleCol   = vec3(0.30, 0.90, 0.92) * bubbleField * bubbleZone * 0.85;

    // ----- COMPOSITE -----
    float outside = 1.0 - orbMask;

    color += auraCol       * outside;
    color += floorPool     * outside;
    color += vec3(0.10, 0.55, 0.58) * spill * outside * 0.35;
    color += bubbleCol     * outside;
    color += flakeCol      * outside;

    color = mix(color, orbColor, orbMask);

    // Rim sits on top — water surface catches light.
    color += rimCol;

    // ----- CINEMATIC POST -----
    // Gentle scene vignette.
    float vign = smoothstep(1.30, 0.25, length(uv));
    color *= mix(0.42, 1.00, vign);

    // Filmic-ish tonemap.
    color = color / (1.0 + color);
    color = pow(color, vec3(0.88));
    color = max(color - 0.004, 0.0);

    // Subtle film grain.
    float grain = (hash21(fc + uTime * 57.0) - 0.5) * 0.013;
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
    gl.getExtension('OES_standard_derivatives');

    const vs = compile(gl, gl.VERTEX_SHADER, vertSrc);
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
