<script setup lang="ts">
import mainLogo from '@/assets/logo/main-logo.svg';
import { index } from '@/wayfinder/routes';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { index as dashboardIndex } from '@/wayfinder/routes/user/dashboard';
import { Link } from '@inertiajs/vue3';

interface FooterLink {
    label: string;
    href: string;
    accent?: boolean;
}

interface FooterColumn {
    title: string;
    links: FooterLink[];
    /** Figma: Navigate & Moods 110px; Get in touch 160px */
    wide?: boolean;
}

const navigate: FooterColumn = {
    title: 'Navigate',
    links: [
        { label: 'Home', href: index().url, accent: true },
        { label: 'Library', href: storiesIndex().url },
        { label: 'Featured Worlds', href: storiesIndex().url },
        { label: 'Profile', href: dashboardIndex().url },
    ],
};

const moods: FooterColumn = {
    title: 'Moods',
    links: [
        { label: 'Heartfelt', href: index().url },
        { label: 'Adventurous', href: index().url },
        { label: 'Mysterious', href: index().url },
        { label: 'Epic', href: index().url },
        { label: 'Whimsical', href: index().url },
    ],
};

const contactEmail = 'Contact@lorespinner.com';

const getInTouch: FooterColumn = {
    title: 'Get in touch',
    wide: true,
    links: [{ label: contactEmail, href: `mailto:${contactEmail}` }],
};

const columns: FooterColumn[] = [navigate, moods, getInTouch];

function isNativeAnchor(href: string): boolean {
    return (
        href.startsWith('http://') ||
        href.startsWith('https://') ||
        href.startsWith('mailto:') ||
        href === '#'
    );
}

const linkRowClass =
    'flex flex-col gap-[10px] font-light text-[12px] leading-[15px] tracking-[-0.48px] text-white';
const headingClass =
    'text-[16px] font-semibold leading-normal tracking-[-0.64px] text-white';
</script>

<template>
    <footer class="relative bg-[#0f0f0f] text-white">
        <div class="container">
            <div
                class="container-content pt-[56px] pb-[64px] sm:pt-[64px] sm:pb-[72px] lg:pt-[74px] lg:pb-[93px]"
            >
                <div class="flex flex-col gap-10 lg:flex-row lg:items-start lg:justify-between">
                    <div class="w-full max-w-[445px] shrink-0">
                        <img
                            :src="mainLogo"
                            alt="LoreSpinner"
                            class="block h-[56px] w-auto max-w-[min(100%,260px)] object-contain object-left md:h-[64px] md:max-w-[min(100%,297px)] lg:h-[72px] lg:max-w-[317px]"
                            width="800"
                            height="180"
                            decoding="async"
                        />
                        <p
                            class="mt-2.5 text-[13px] font-light leading-[100%] tracking-[-0.52px] text-white lg:mt-[11px]"
                        >
                            For dreamers, creators, and wanderers ; Lorespinner brings your stories to life.
                        </p>
                    </div>

                    <nav
                        aria-label="Footer"
                        class="flex flex-col gap-y-10 sm:flex-row sm:flex-wrap sm:gap-x-6 sm:gap-y-10 lg:w-auto lg:flex-nowrap lg:justify-end lg:gap-x-6 lg:gap-y-0"
                    >
                        <div
                            v-for="col in columns"
                            :key="col.title"
                            class="flex w-full shrink-0 flex-col gap-[17px]"
                            :class="col.wide ? 'sm:w-[160px] lg:w-[160px]' : 'sm:w-[110px] lg:w-[110px]'"
                        >
                            <p :class="headingClass">
                                {{ col.title }}
                            </p>
                            <ul :class="linkRowClass">
                                <li v-for="link in col.links" :key="link.label">
                                    <a
                                        v-if="isNativeAnchor(link.href)"
                                        :href="link.href"
                                        class="block transition-colors hover:text-primary"
                                        :class="link.accent ? 'text-primary' : 'text-white'"
                                        :rel="
                                            link.href.startsWith('http') ? 'noopener noreferrer' : undefined
                                        "
                                        :target="link.href.startsWith('http') ? '_blank' : undefined"
                                    >
                                        {{ link.label }}
                                    </a>
                                    <Link
                                        v-else
                                        :href="link.href"
                                        class="block transition-colors hover:text-primary"
                                        :class="link.accent ? 'text-primary' : 'text-white'"
                                    >
                                        {{ link.label }}
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </nav>
                </div>

                <p
                    class="mt-16 text-right text-[14px] font-normal leading-normal tracking-[-0.56px] text-primary sm:mt-20 lg:mt-[128px]"
                >
                    © Copyright 2026. Lorespinner Inc. All rights reserved.
                </p>
            </div>
        </div>
    </footer>
</template>
