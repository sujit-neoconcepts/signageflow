<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import {
    mdiAlert,
    mdiCog,
} from "@mdi/js";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import CardBox from "@/components/CardBox.vue";

import NotificationBar from "@/components/NotificationBar.vue";

import FormField from "@/components/FormField.vue";
import FormControl from "@/components/FormControl.vue";

import { computed } from 'vue';

const message = computed(() => usePage().props.flash.message)
const msg_type = computed(() => usePage().props.flash.msg_type ?? 'warning')

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({}),
    },
});

const form = useForm({
    settings: props.settings,
});

const submitform = () => {
    form.put(route('setting.bulkUpdate'));
};
</script>
<template>
    <LayoutAuthenticated>

        <Head title="Settings" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiCog" title="Settings" main>
                <div class="flex">
                </div>
            </SectionTitleLineWithButton>
            <NotificationBar v-if="message" @closed="usePage().props.flash.message = ''" :color="msg_type" :icon="mdiAlert"
                :outline="true">
                {{ message }}
            </NotificationBar>
            <form @submit.prevent="submitform">
                <CardBox>
                    <div v-for="(item, index) in settings">
                        <h1 class="bg-zinc-50 mb-4 p-2 text-black font-bold rounded"
                            v-if="index == 0 || settings[index].group != settings[index - 1].group">{{
                                settings[index].group
                            }}</h1>
                        <FormField :label="item.label" help="" class="last:mb-6">
                            <FormControl :type="form.settings[index].vtype" name="value[]"
                                v-model="form.settings[index].value" required />
                        </FormField>
                    </div>
                </CardBox>

                <div class="mt-4 flex">
                    <BaseButton class="mr-2" type="submit" small color="info" label="Update" />
                    <Link :href="route('dashboard')">
                    <BaseButton type="reset" small color="info" outline label="Cancel" />
                    </Link>
                </div>

            </form>
        </SectionMain>
    </LayoutAuthenticated>
</template>
