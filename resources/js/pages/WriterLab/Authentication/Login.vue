<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import BaseInputFormat from '@/components/BaseInputFormat.vue';
import WriterLabAuthLayout from '@/layouts/WriterLabAuthLayout.vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
});

const submit = () => {
    form.post('/writer/authentication/login');
};
</script>

<template>
    <WriterLabAuthLayout>
        <template #body>
            <form class="flex w-full flex-col gap-4" @submit.prevent="submit">
                <BaseInputFormat label="Email" :error="form.errors.email">
                    <PrimeInputText
                        v-model="form.email"
                        name="email"
                        placeholder="Enter your email address"
                        class="w-full"
                    />
                </BaseInputFormat>
                <BaseInputFormat label="Password" :error="form.errors.password">
                    <PrimePassword
                        v-model="form.password"
                        name="password"
                        placeholder="Enter your password"
                        :feedback="false"
                        toggle-mask
                        class="w-full"
                    />
                </BaseInputFormat>
            </form>
        </template>
        <template #footer>
            <div class="flex w-full flex-col gap-4">
                <BaseButton
                    severity="primary"
                    class="text-lg"
                    :processing="form.processing"
                    @click="submit"
                >
                    Log In to Writer Lab
                </BaseButton>
            </div>
        </template>
    </WriterLabAuthLayout>
</template>

<style scoped></style>
