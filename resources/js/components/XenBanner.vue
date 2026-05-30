<template>
    <!-- Narrow vertical band between carousels; keeps section rhythm without doubling 60px gaps. -->
    <section class="py-9 md:py-10">
        <div class="container">
            <div class="container-content">
                <div
                    class="xen-banner flex w-full items-center justify-center overflow-hidden rounded-[14px] p-[20px]"
                >
                    <p
                        class="xen-banner__text text-center font-[Inter] text-[18px] font-normal leading-[26px] tracking-[2.52px] text-white"
                    >
                        Written by humans. Powered by your imagination. Brought to life by
                        <span class="text-[#00c6de]">Xen</span>
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
/* Gold on left, Tiffany cyan on right — CSS angles = Figma export − 180° */
.xen-banner {
    background-image:
        linear-gradient(13.83665037926775deg, rgba(0, 0, 0, 0.2) 65.149%, rgba(0, 198, 222, 0.2) 99.392%),
        linear-gradient(207.07668522969923deg, rgb(22, 22, 22) 73.393%, rgb(255, 149, 0) 170.11%);
}

.xen-banner__text {
    text-shadow: 0 4px 24.8px rgba(0, 0, 0, 0.25);
}

</style>
