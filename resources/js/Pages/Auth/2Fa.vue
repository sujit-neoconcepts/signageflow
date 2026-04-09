<script setup>
import {
    mdiAlert,
} from "@mdi/js";
import Checkbox from '@/components/Checkbox.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import Timer from '@/components/Timer.vue';

import NotificationBar from "@/components/NotificationBar.vue";

const message = computed(() => usePage().props.flash.message)
const msg_type = computed(() => usePage().props.flash.msg_type ?? 'warning')

const form = useForm({
    code: ''
});

const props = defineProps({
    lockPeriod: Number
});

const submit = () => {
    form.post(route('2fa.store'), {
        onFinish: () => form.reset('password'),
    });
};

const disableAction = ref(true);
const timerValue = ref(props.lockPeriod);



</script>

<template>
    <GuestLayout title="We have sent you OTP on your email. Please verify.">
        <NotificationBar v-if="message" @closed="usePage().props.flash.message = ''" :color="msg_type" :icon="mdiAlert"
            :outline="true">
            {{ message }}
        </NotificationBar>

        <Head title="2FA Verifications" />
        <form @submit.prevent="submit">
            <div>
                <InputLabel for="code" value="OTP" />
                <TextInput id="code" type="text" class="mt-1 block w-full" v-model="form.code" required autofocus />
                <InputError class="mt-2" :message="form.errors.code" />
            </div>

            <div class="flex items-center justify-end mt-4">

                <span v-if="disableAction">Re-send Code in </span>
                <Timer v-if="disableAction" :startNumber="timerValue" @stopped="disableAction = false" />
                <Link v-if="!disableAction" :href="route('2fa.resend')"
                    class="underline text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200">
                Re-send OTP.
                </Link>

                <PrimaryButton class="ml-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Verify
                </PrimaryButton>
            </div>
            <div class="flex items-center justify-center mt-4 border-t pt-2">
                <Link href="#" @click='router.post(route("logout"))'
                    class="underline text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200">
                Back To Login
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
