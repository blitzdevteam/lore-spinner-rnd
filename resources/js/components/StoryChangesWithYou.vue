<script setup>
import bannerStoryChange from '@/assets/banner-storyChange.svg';
</script>

<template>
    <section class="py-14 md:py-[60px]">
        <div class="container">
            <div class="mx-auto flex w-full max-w-[1018px] flex-col gap-[14px]">
                <div class="w-full">
                    <h2 class="flex h-10 items-center text-[26px] font-bold uppercase leading-[33px] text-white">
                        The Story Changes With You
                    </h2>
                    <p class="text-[16px] font-normal leading-[26px] text-[#b6b6b6]">Speak, choose, or write your own path.</p>
                </div>

                <div
                    class="story-changes-card relative flex min-h-[220px] flex-col overflow-hidden rounded-[15px] border border-primary p-5 md:h-[220px] md:p-5"
                    :style="{ backgroundImage: `url(${bannerStoryChange})` }"
                >
                    <!-- Decorative horizontal glowing lines (background) -->
                    <div class="pointer-events-none absolute inset-0 overflow-hidden opacity-20">
                        <div class="absolute inset-0 flex flex-col justify-center gap-6">
                            <div class="h-px w-full rotate-[-6.5deg] transform bg-gradient-to-r from-transparent via-primary/60 to-transparent blur-sm" />
                            <div class="h-px w-full rotate-[-6.5deg] transform bg-gradient-to-r from-transparent via-primary/40 to-transparent blur-sm" />
                        </div>
                    </div>

                    <div class="relative flex w-full flex-col items-center md:flex-row md:justify-center md:gap-[35px]">
                        <!-- You Choose -->
                        <div class="flex w-full max-w-[280px] flex-col items-center gap-[14px] text-center md:w-[280px]">
                            <div class="icon-circle you-choose-icon flex size-[90px] items-center justify-center rounded-full border-[#131313] shadow-[0px_3px_60px_0px_rgba(0,0,0,0.2)]">
                                <!-- Branching paths icon -->
                                <svg width="34" height="35" viewBox="0 0 34 35" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="17" cy="12" r="4" stroke="#00c6de" stroke-width="1.5" fill="none" />
                                    <circle cx="7" cy="27" r="4" stroke="#00c6de" stroke-width="1.5" fill="none" />
                                    <circle cx="17" cy="27" r="4" stroke="#00c6de" stroke-width="1.5" fill="none" />
                                    <circle cx="27" cy="27" r="4" stroke="#00c6de" stroke-width="1.5" fill="none" />
                                    <line x1="17" y1="16" x2="7" y2="23" stroke="#00c6de" stroke-width="1" opacity="0.7" />
                                    <line x1="17" y1="16" x2="17" y2="23" stroke="#00c6de" stroke-width="1" opacity="0.7" />
                                    <line x1="17" y1="16" x2="27" y2="23" stroke="#00c6de" stroke-width="1" opacity="0.7" />
                                </svg>
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
                        <div class="mt-10 flex w-full max-w-[280px] flex-col items-center gap-[14px] text-center md:mt-0 md:w-[280px]">
                            <div class="icon-circle story-responds-icon flex size-[90px] items-center justify-center rounded-full border-[#131313] shadow-[0px_3px_60px_0px_rgba(0,0,0,0.2)]">
                                <!-- Adaptive branching icon -->
                                <svg width="34" height="34" viewBox="0 0 34 34" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="17" cy="17" r="4.5" stroke="#00c6de" stroke-width="1.2" fill="none" />
                                    <line x1="17" y1="6" x2="11" y2="12" stroke="#00c6de" stroke-width="1" opacity="0.7" stroke-linecap="round" />
                                    <line x1="17" y1="6" x2="23" y2="12" stroke="#00c6de" stroke-width="1" opacity="0.7" stroke-linecap="round" />
                                    <line x1="17" y1="28" x2="11" y2="22" stroke="#00c6de" stroke-width="1" opacity="0.7" stroke-linecap="round" />
                                    <line x1="17" y1="28" x2="23" y2="22" stroke="#00c6de" stroke-width="1" opacity="0.7" stroke-linecap="round" />
                                    <circle cx="17" cy="5.5" r="2" fill="#00c6de" opacity="0.5" />
                                    <circle cx="10.5" cy="17" r="2" fill="#00c6de" opacity="0.5" />
                                    <circle cx="23.5" cy="17" r="2" fill="#00c6de" opacity="0.5" />
                                    <circle cx="17" cy="28.5" r="2" fill="#00c6de" opacity="0.5" />
                                </svg>
                            </div>
                            <div class="flex flex-col gap-[10px]">
                                <h3 class="text-center text-[18px] font-semibold leading-normal text-primary">The Story Responds</h3>
                                <p class="max-w-[270px] text-center text-[14px] font-light leading-normal text-white">
                                    Every choice opens new branches and changes the story in real time.
                                </p>
                            </div>
                        </div>

                        <div class="story-changes-rule mt-10 hidden shrink-0 md:mt-0 md:block" aria-hidden="true" />

                        <!-- Xen Guides You -->
                        <div class="mt-10 flex w-full max-w-[280px] flex-col items-center gap-[14px] text-center md:mt-0 md:w-[280px]">
                            <div class="icon-circle xen-icon flex size-[90px] items-center justify-center rounded-full border-[#131313] shadow-[0px_3px_60px_0px_rgba(0,0,0,0.2)]">
                                <!-- AI / neural network icon -->
                                <svg width="54" height="34" viewBox="0 0 54 34" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="10" cy="17" r="3" fill="#ffbe58" opacity="0.6" />
                                    <circle cx="22" cy="9" r="3" fill="#ffbe58" opacity="0.6" />
                                    <circle cx="22" cy="25" r="3" fill="#ffbe58" opacity="0.6" />
                                    <circle cx="34" cy="9" r="3" fill="#ffbe58" opacity="0.6" />
                                    <circle cx="34" cy="25" r="3" fill="#ffbe58" opacity="0.6" />
                                    <circle cx="44" cy="17" r="3" fill="#ffbe58" opacity="0.6" />
                                    <line x1="13" y1="17" x2="19" y2="10" stroke="#ffbe58" stroke-width="1" opacity="0.5" />
                                    <line x1="13" y1="17" x2="19" y2="24" stroke="#ffbe58" stroke-width="1" opacity="0.5" />
                                    <line x1="25" y1="9" x2="31" y2="9" stroke="#ffbe58" stroke-width="1" opacity="0.5" />
                                    <line x1="25" y1="25" x2="31" y2="25" stroke="#ffbe58" stroke-width="1" opacity="0.5" />
                                    <line x1="25" y1="10" x2="31" y2="24" stroke="#ffbe58" stroke-width="1" opacity="0.3" />
                                    <line x1="25" y1="24" x2="31" y2="10" stroke="#ffbe58" stroke-width="1" opacity="0.3" />
                                    <line x1="37" y1="10" x2="41" y2="16" stroke="#ffbe58" stroke-width="1" opacity="0.5" />
                                    <line x1="37" y1="24" x2="41" y2="18" stroke="#ffbe58" stroke-width="1" opacity="0.5" />
                                </svg>
                            </div>
                            <div class="flex flex-col gap-[10px]">
                                <h3 class="text-center text-[18px] font-semibold leading-normal text-[#ffbe58]">Xen Guides You</h3>
                                <p class="max-w-[270px] text-center text-[14px] font-light leading-normal text-white">
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

/* Glass base for all icon circles */
.icon-circle {
    background: linear-gradient(
        160deg,
        rgba(255, 255, 255, 0.14) 0%,
        rgba(255, 255, 255, 0.04) 60%,
        rgba(0, 0, 0, 0.15) 100%
    );
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.18);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.2),
        inset 0 -1px 0 rgba(0, 0, 0, 0.15);
    transition:
        box-shadow 0.3s ease,
        border-color 0.3s ease,
        background 0.3s ease;
}

/* Cyan circles — teal glow on hover */
.you-choose-icon,
.story-responds-icon {
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.2),
        inset 0 -1px 0 rgba(0, 0, 0, 0.15),
        0 0 16px 0 rgba(0, 198, 222, 0.2);
}
.you-choose-icon:hover,
.story-responds-icon:hover {
    background: linear-gradient(
        160deg,
        rgba(0, 198, 222, 0.18) 0%,
        rgba(255, 255, 255, 0.06) 50%,
        rgba(0, 0, 0, 0.1) 100%
    );
    border-color: rgba(0, 198, 222, 0.45);
    box-shadow:
        inset 0 1px 0 rgba(0, 198, 222, 0.25),
        inset 0 -1px 0 rgba(0, 0, 0, 0.2),
        0 0 22px 4px rgba(0, 198, 222, 0.5),
        0 0 48px 10px rgba(0, 198, 222, 0.15);
}

/* Xen circle — amber glow on hover */
.xen-icon {
    border-color: rgba(255, 190, 88, 0.28);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.18),
        inset 0 -1px 0 rgba(0, 0, 0, 0.15),
        0 0 14px 0 rgba(255, 190, 88, 0.18);
}
.xen-icon:hover {
    background: linear-gradient(
        160deg,
        rgba(255, 190, 88, 0.18) 0%,
        rgba(255, 255, 255, 0.06) 50%,
        rgba(0, 0, 0, 0.1) 100%
    );
    border-color: rgba(255, 190, 88, 0.55);
    box-shadow:
        inset 0 1px 0 rgba(255, 190, 88, 0.25),
        inset 0 -1px 0 rgba(0, 0, 0, 0.2),
        0 0 22px 4px rgba(255, 190, 88, 0.5),
        0 0 48px 10px rgba(255, 190, 88, 0.15);
}
</style>
