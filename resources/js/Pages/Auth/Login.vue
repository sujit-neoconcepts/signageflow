<script setup>
import Checkbox from '@/components/Checkbox.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import BaseIcon from "@/components/BaseIcon.vue";
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { onMounted, computed, } from 'vue';
import { ref } from 'vue';

import { mdiEyeOutline, mdiEyeOffOutline } from '@mdi/js';

import Timer from '@/components/Timer.vue';
import { watch } from 'vue';

const props = defineProps({
    canResetPassword: Boolean,
    status: String,
    sitekey: String,
    captcha_en: Boolean,

});
const form = useForm({
    email: '',
    password: '',
    remember: false,
    captcha_token: "",
});

const emailErrors = computed(() => usePage().props.errors.email);
const disableAction = ref(false);
const timerValue = ref(0);

watch(emailErrors, (newv) => {
    if (newv.includes("Too many")) {
        timerValue.value = Number(newv.match(/\d+/)[0]);
        disableAction.value = true;
    }
    else {
        disableAction.value = false;
    }
})

const submit = () => {
    if (props.captcha_en) {
        let submitForm = (token) => {
            form.captcha_token = token;
            form.post(route('login'), {
                replace: true,
                onSuccess: () => {
                    form.reset('password');
                    window.history.replaceState([], null, route('dashboard'));
                }
            });
        };

        grecaptcha
            .execute(props.sitekey, { action: "submit" })
            .then(function (token) {
                submitForm(token);
            });
    }
    else {
        form.post(route('login'), {
            replace: true,
            onSuccess: () => {
                form.reset('password');
                window.history.replaceState([], null, route('dashboard'));
            }
        });
    }
};


onMounted(() => {
    window.history.pushState([], null, window.location);
})

const showHidepassword = ref(true);
const togglePassword = () => {
    showHidepassword.value = !showHidepassword.value;
}

</script>

<template>
    <GuestLayout title="Please sign in to your account" :sitekey="sitekey" :captcha_en="captcha_en">

        <Head title="Log in" />
        <div v-if="status" class="mb-4 font-medium text-sm text-green-600">
            {{ status }}
        </div>

        <div v-if="form.errors.captcha_token" class="mb-4 font-medium text-sm text-red-600">
            {{ form.errors.captcha_token }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />
                <TextInput id="email" type="email" class="mt-1 block w-full" v-model="form.email" required autofocus
                    autocomplete="username" />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" />
                <div class="relative">
                    <TextInput id="password" :type="showHidepassword ? 'password' : 'text'" class="mt-1 block w-full"
                        v-model="form.password" required autocomplete="current-password" />
                    <span @click="togglePassword" class="cursor-pointer">
                        <BaseIcon :path="showHidepassword ? mdiEyeOutline : mdiEyeOffOutline" w="w-10" h="h-10" size="24"
                            class="absolute top-0 right-0 z-100  text-gray-500 dark:text-slate-400" />
                    </span>

                </div>
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="block mt-4">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Remember me</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-4">

                <Link v-if="canResetPassword" :href="route('password.request')"
                    class="underline text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200">
                Forgot your password?
                </Link>
                <PrimaryButton class="ml-4" :class="{ 'opacity-25': form.processing || disableAction }"
                    :disabled="form.processing || disableAction">
                    Log in
                </PrimaryButton>
            </div>
            <div v-if="disableAction" class="border-t border-gray-700 mt-5 pt-3 text-center">
                You are locked for
                <Timer v-if="disableAction" :startNumber="timerValue"
                    @stopped="disableAction = false; form.errors.email = ''" class="" />
                seconds
            </div>
        </form>
    </GuestLayout>
</template>
