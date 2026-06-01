<script setup lang="ts">
import CreatorsBannerImage from '@/assets/creators-banner.png';
import BaseContentTitle from '@/components/BaseContentTitle.vue';
import BaseCreatorCard from '@/components/BaseCreatorCard.vue';
import BaseLogo from '@/components/BaseLogo.vue';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { CreatorInterface } from '@/types';

withDefaults(
    defineProps<{
        creators?: CreatorInterface[];
    }>(),
    {
        creators: () => [],
    },
);
</script>

<template>
    <HomeLayout>
        <div
            class="grid h-108 place-items-center bg-cover"
            :style="{ background: `url(${CreatorsBannerImage}) center center no-repeat`, backgroundSize: 'cover' }"
        >
            <div class="container">
                <div class="-ms-20 flex w-86 flex-col items-center gap-4">
                    <BaseLogo class="w-full" />
                    <h3 class="font-gill-sans text-2xl font-light text-primary">Stories That Live Through You</h3>
                </div>
            </div>
        </div>

        <div class="py-18">
            <div class="container">
                <div class="flex flex-col gap-12">
                    <BaseContentTitle title="Creators">
                        <template #description>
                            Meet the minds behind the worlds you love and explore the worlds they are actively
                            <span class="text-primary">bringing to life</span>
                        </template>
                    </BaseContentTitle>
                    <div class="grid grid-cols-3 gap-6">
                        <BaseCreatorCard v-for="creator in creators" :key="creator.username" :creator />
                    </div>
                </div>
            </div>
        </div>
    </HomeLayout>
</template>

<style scoped></style>
