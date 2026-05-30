<script setup>
import bannerStoryChange from '@/assets/banner-storyChange.svg';
import iconYouChoose from '@/assets/story/left.svg';
import iconStoryResponds from '@/assets/story/center.svg';
import iconXen from '@/assets/story/right .svg';
</script>

<template>
    <section class="home-section-y">
        <div class="container">
            <div class="container-content home-section-gap">
                <div class="w-full">
                    <h2 class="flex h-10 items-center text-[26px] font-bold uppercase leading-[33px] text-white">
                        The Story Changes With You
                    </h2>
                    <p class="text-[16px] font-normal leading-[26px] text-[#b6b6b6]">Speak, choose, or write your own path.</p>
                </div>

                <div
                    class="story-changes-card relative flex min-h-[220px] flex-col overflow-hidden rounded-[15px] border border-white/12 p-5 md:h-[220px] md:p-5"
                    :style="{ backgroundImage: `url(${bannerStoryChange})` }"
                >
                    <!-- Neutral accent lines (no primary/teal spill onto page background) -->
                    <div class="pointer-events-none absolute inset-0 overflow-hidden opacity-[0.12]">
                        <div class="absolute inset-0 flex flex-col justify-center gap-6">
                            <div class="h-px w-full rotate-[-6.5deg] transform bg-gradient-to-r from-transparent via-white/50 to-transparent blur-sm" />
                            <div class="h-px w-full rotate-[-6.5deg] transform bg-gradient-to-r from-transparent via-white/35 to-transparent blur-sm" />
                        </div>
                    </div>

                    <div class="relative flex w-full flex-col items-center md:flex-row md:justify-center md:gap-[35px]">
                        <!-- You Choose -->
                        <div
                            class="story-changes-col story-changes-col--choose flex w-full max-w-[280px] flex-col items-center gap-[14px] text-center md:w-[280px]"
                        >
                            <div
                                class="icon-circle you-choose-icon relative flex size-[90px] shrink-0 items-center justify-center overflow-hidden rounded-full"
                            >
                                <div class="absolute inset-0 rounded-full bg-[rgba(255,255,255,0.02)]" aria-hidden="true" />
                                <div class="absolute inset-0 rounded-full backdrop-blur-[2.25px] mix-blend-plus-lighter" aria-hidden="true" />
                                <img
                                    :src="iconYouChoose"
                                    alt=""
                                    class="relative z-[2] h-[35px] w-[34px] shrink-0"
                                    width="34"
                                    height="35"
                                />
                                <div class="you-choose-glow absolute inset-0 pointer-events-none rounded-full" aria-hidden="true" />
                            </div>
                            <div class="flex flex-col gap-[10px]">
                                <h3 class="text-center text-[18px] font-semibold leading-normal text-primary">You Choose</h3>
                                <p
                                    class="max-w-[270px] text-center text-[14px] font-light leading-normal text-white [text-shadow:0px_0px_26.9px_#0f0f0f]"
                                >
                                    Choose how the story unfolds and shape what's next.
                                </p>
                            </div>
                        </div>

                        <div class="story-changes-rule mt-10 hidden shrink-0 md:mt-0 md:block" aria-hidden="true" />

                        <!-- The Story Responds -->
                        <div
                            class="story-changes-col story-changes-col--responds mt-10 flex w-full max-w-[280px] flex-col items-center gap-[14px] text-center md:mt-0 md:w-[280px]"
                        > relative flex size-[90px] shrink-0 items-center justify-center overflow-hidden rounded-full"
                            >
                                <div class="absolute inset-0 rounded-full bg-[rgba(255,255,255,0.02)]" aria-hidden="true" />
                                <div class="absolute inset-0 rounded-full backdrop-blur-[2.25px] mix-blend-plus-lighter" aria-hidden="true" />
                                <img
                                    :src="iconStoryResponds"
                                    alt=""
                                    class="relative z-[2] h-[28px] w-[38px] shrink-0"
                                    width="38"
                                    height="28"
                                />
                                <div class="story-responds-glow absolute inset-0 pointer-events-none rounded-full" aria-hidden="true" />
                            </div>
                            <div class="flex flex-col gap-[10px]">
                                <h3 class="text-center text-[18px] font-semibold leading-normal text-primary">The Story Responds</h3>
                                <p
                                    class="max-w-[270px] text-center text-[14px] font-light leading-normal text-white [text-shadow:0px_0px_26.9px_#0f0f0f]"
                                >
                                    Every choice opens new branches and changes the story in real time.
                                </p>
                            </div>
                        </div>

                        <div class="story-changes-rule mt-10 hidden shrink-0 md:mt-0 md:block" aria-hidden="true" />

                        <!-- Xen Guides You -->
                        <div
                            class="story-changes-col story-changes-col--xen mt-10 flex w-full max-w-[280px] flex-col items-center gap-[14px] text-center md:mt-0 md:w-[280px]"
                        > relative flex size-[90px] shrink-0 items-center justify-center overflow-hidden rounded-full"
                            >
                                <div class="absolute inset-0 rounded-full bg-[rgba(255,255,255,0.02)]" aria-hidden="true" />
                                <div class="absolute inset-0 rounded-full backdrop-blur-[2.25px] mix-blend-plus-lighter" aria-hidden="true" />
                                <img
                                    :src="iconXen"
                                    alt=""
                                    class="relative z-[2] h-[17px] w-[52px] shrink-0"
                                    width="52"
                                    height="17"
                                />
                                <div class="xen-glow absolute inset-0 pointer-events-none rounded-full" aria-hidden="true" />
                            </div>
                            <div class="flex flex-col gap-[10px]">
                                <h3 class="text-center text-[18px] font-semibold leading-normal text-primary">Xen Guides You</h3>
                                <p
                                    class="max-w-[270px] text-center text-[14px] font-light leading-normal text-white [text-shadow:0px_0px_26.9px_#0f0f0f]"
                                >
                                    Xen listens, adapts, and brings the story to life.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.story-changes-card {
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
}

.story-changes-rule {
    width: 1px;
    height: 171px;
    align-self: center;
    background: rgba(255, 255, 255, 0.14);
}

/* Glass base for all icon circles -- Figma: subtle 2.25px blur + near-transparent fill */
.icon-circle {
    background: rgba(255, 255, 255, 0.02);
    backdrop-filter: blur(2.25px);
    -webkit-backdrop-filter: blur(2.25px);
    border: 1px solid rgba(255, 255, 255, 0.18);
    transition:
        box-shadow 0.3s ease,
        border-color 0.3s ease;
}

/* Default: all three circles match (no gold until Xen column hover) */
.you-choose-icon,
.story-responds-icon,
.xen-icon {
    border-color: #131313;
    box-shadow: 0px 3px 60px 0px rgba(0, 0, 0, 0.2);
}

/* Tiffany border when hovering first or second column (icon or copy) */
.story-changes-col--choose:hover .you-choose-icon,
.story-changes-col--responds:hover .story-responds-icon {
    border-color: color-mix(in srgb, var(--color-primary-400) 92%, transparent);
    box-shadow:
        0px 3px 60px 0px rgba(0, 0, 0, 0.35),
        0 0 22px 3px color-mix(in srgb, var(--color-primary-400) 28%, transparent);
}

/* Gold border + glow when hovering third column — former default Xen treatment */
.story-changes-col--xen:hover .xen-icon {
    border-color: rgba(255, 190, 88, 0.96);
    box-shadow:
        0px 3px 60px 0px rgba(0, 0, 0, 0.2),
        0 0 22px 4px rgba(255, 190, 88, 0.5),
        0 0 48px 10px rgba(255, 190, 88, 0.15);
}

/* Inset glow overlays rendered as the topmost layer inside each circle */
.you-choose-glow,
.story-responds-glow {
    box-shadow:
        inset 0px 0px 13.9px 0px rgba(255, 255, 255, 0.06),
        inset 0.188px 0.375px 0.375px 0.188px rgba(255, 255, 255, 0.22),
        inset -0.15px -0.375px 0.113px 0.375px rgba(255, 255, 255, 0.05);
}

/* Xen: neutral inset at rest so the circle matches the other two */
.xen-glow {
    box-shadow:
        inset 0px 0px 13.9px 0px rgba(255, 255, 255, 0.06),
        inset 0.188px 0.375px 0.375px 0.188px rgba(255, 255, 255, 0.22),
        inset -0.15px -0.375px 0.113px 0.375px rgba(255, 255, 255, 0.05);
}

.story-changes-col--xen:hover .xen-glow {
    box-shadow:
        inset 0px 0px 9.4px 0px rgba(255, 190, 88, 0.1),
        inset 0.188px 0.375px 0.375px 0.188px rgba(255, 255, 255, 0.22),
        inset -0.15px -0.375px 0.113px 0.375px rgba(255, 255, 255, 0.05);
}
</style>
