<script setup lang="ts">
/** Ambient blurred gradients — matches Figma story play frame (cyan / warm accents). */
</script>

<template>
    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <div
            class="absolute left-[-515px] top-[-139px] h-[1025px] w-[603px] blur-[250px]"
            style="background-image: linear-gradient(126.146deg, rgb(0, 198, 222) 1.9682%, rgb(0, 0, 0) 70.153%)"
        />
        <div
            class="absolute left-[calc(75%+62px)] top-[188px] flex h-[1025px] w-[603px] items-center justify-center"
        >
            <div class="flex-none rotate-180">
                <div
                    class="relative h-[1025px] w-[603px] blur-[250px]"
                    style="
                        background-image:
                            linear-gradient(126.146deg, rgba(255, 190, 88, 0.08) 1.9682%, rgb(0, 0, 0) 70.153%),
                            linear-gradient(90deg, rgba(0, 198, 222, 0.09) 0%, rgba(0, 198, 222, 0.09) 100%);
                    "
                />
            </div>
        </div>
    </div>
</template>
