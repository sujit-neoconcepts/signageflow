<script setup>
import {
  mdiAccount,
  mdiMail,
  mdiFormTextboxPassword,
  mdiAlert,
} from "@mdi/js";
import SectionMain from "@/components/SectionMain.vue";
import CardBox from "@/components/CardBox.vue";
import FormField from "@/components/FormField.vue";
import FormControl from "@/components/FormControl.vue";
import BaseButton from "@/components/BaseButton.vue";
import BaseButtons from "@/components/BaseButtons.vue";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";

import FormCheckRadio from "@/components/FormCheckRadio.vue";

import NotificationBar from "@/components/NotificationBar.vue";
import { Head, usePage, useForm } from '@inertiajs/vue3';
import { computed } from "vue";
import { can } from '@/utils/permissions';


const props = defineProps({
  can: {
    type: Object,
    default: () => ({}),
  },
  formdata: {
    type: Object,
    default: () => ({}),
  },
});

const form = useForm({
  name: props.formdata.name,
  email: props.formdata.email,
  password: '',
  current_password: '',
  twofa: props.formdata.twofa == 1 ? true : false,
});

const submitProfile = () => {
  form.put(route('profile.updateProfile'));
};
const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? 'warning')

</script>

<template>
  <LayoutAuthenticated>

    <Head title="My Profile" />
    <SectionMain>
      <SectionTitleLineWithButton :icon="mdiAccount" title="My Profile" main>&nbsp;
      </SectionTitleLineWithButton>
      <NotificationBar v-if="message" @closed="usePage().props.flash.message = ''" :color="msg_type" :icon="mdiAlert"
        :outline="true">
        {{ message }}
      </NotificationBar>
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <form @submit.prevent="submitform" autocomplete="off">
          <CardBox is-form @submit.prevent="submitProfile">
            <FormField label="Name" help="" :error="form.errors.name">
              <FormControl name="name" v-model="form.name" required />
            </FormField>
            <FormField v-if="(can('user_edit'))" label="Email" help="" :error="form.errors.email">
              <FormControl name="email" v-model="form.email" required />
            </FormField>
            <FormField label="Current password" help="Current password is required to do any changes"
              :error="form.errors.current_password">
              <FormControl v-model="form.current_password" name="current_password" type="password" autocomplete="off" />
            </FormField>
            <FormField label="New password" help="New password, Leave Blank if not to change"
              :error="form.errors.password">
              <FormControl v-model="form.password" name="password" type="password" autocomplete="off" />
            </FormField>
            <FormField label="2FA" v-if="(can('user_edit'))">
              <FormCheckRadio type="switch" v-model="form.twofa" name="twofa" input-value="0" label='ON' prelabel='OFF' />
            </FormField>
            <template #footer>
              <BaseButtons>
                <BaseButton color="info" type="submit" small label="Update" />
              </BaseButtons>
            </template>
          </CardBox>
        </form>
      </div>
    </SectionMain>
  </LayoutAuthenticated>
</template>
