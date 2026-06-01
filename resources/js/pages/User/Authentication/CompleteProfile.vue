<script setup lang="ts">
import BaseBackgroundGradient from '@/components/BaseBackgroundGradient.vue';
import BaseButton from '@/components/BaseButton.vue';
import BaseInputFormat from '@/components/BaseInputFormat.vue';
import BaseLogo from '@/components/BaseLogo.vue';
import StickyFooterLayout from '@/layouts/StickyFooterLayout.vue';
import { GenderEnum } from '@/types/enum';
import { update } from '@/wayfinder/routes/user/authentication/complete-profile';
import { Form } from '@inertiajs/vue3';
import { UserRoundPen } from 'lucide-vue-next';

const genderLabels: Record<string, string> = {
    [GenderEnum.MALE]: 'Male',
    [GenderEnum.FEMALE]: 'Female',
    [GenderEnum.NON_BINARY]: 'Non-binary',
    [GenderEnum.PREFER_NOT_TO_SAY]: 'Prefer not to say',
};

const genderEnumOptions = Object.values(GenderEnum).map((value) => ({
    label: genderLabels[value],
    value: value,
}));
</script>

<template>
    <BaseBackgroundGradient />
    <StickyFooterLayout class="mx-auto max-w-102">
        <template #body>
            <div class="flex flex-col items-center gap-6">
                <BaseLogo class="w-68" />
                <UserRoundPen class="text-primary-400" :size="48" :strokeWidth="1.5" />
                <div class="flex flex-col gap-2 text-center">
                    <p class="text-lg text-white">Complete your profile to get started</p>
                    <p class="text-sm text-gray-400">Please provide the necessary information to complete your profile and access all features.</p>
                </div>
            </div>
        </template>
        <template #footer>
            <Form :action="update()" #default="{ errors, processing }">
                <div class="flex flex-col gap-8">
                    <div class="grid w-full grid-cols-2 gap-4">
                        <div class="col-span-full">
                            <BaseInputFormat label="Username" :error="errors.username">
                                <PrimeInputText name="username" placeholder="Enter your username" />
                            </BaseInputFormat>
                        </div>
                        <BaseInputFormat label="First name" :error="errors.first_name">
                            <PrimeInputText name="first_name" placeholder="Enter your first name" />
                        </BaseInputFormat>
                        <BaseInputFormat label="Last name" :error="errors.last_name">
                            <PrimeInputText name="last_name" placeholder="Enter your last name" />
                        </BaseInputFormat>
                        <div class="col-span-full">
                            <BaseInputFormat label="Gender" :error="errors.gender">
                                <PrimeSelect
                                    name="gender"
                                    placeholder="Select your gender"
                                    option-value="value"
                                    option-label="label"
                                    :options="genderEnumOptions"
                                />
                            </BaseInputFormat>
                        </div>
                    </div>
                    <BaseButton :processing severity="primary">Submit</BaseButton>
                </div>
            </Form>
        </template>
    </StickyFooterLayout>
</template>

<style scoped></style>
