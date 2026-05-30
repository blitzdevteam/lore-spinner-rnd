<script setup lang="ts">
import { Search } from 'lucide-vue-next';
</script>

<template>
    <div class="relative">
        <PrimeInputText
            class="header-search h-10 w-64 !border-[#B0B0B0] !bg-white/85 !ps-10 !text-sm !text-gray-800 !shadow-none placeholder:!text-[#757575] lg:w-80"
            placeholder="Search what you want to play"
        />
        <Search class="pointer-events-none absolute inset-x-3 top-1/2 size-4 -translate-y-1/2 text-[#757575]" />
    </div>
</template>

<style scoped>
:deep(.header-search.p-inputtext:enabled:hover),
:deep(.header-search.p-inputtext:enabled:focus) {
    border-color: var(--color-primary);
}
</style>
