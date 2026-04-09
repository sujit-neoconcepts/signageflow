<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from "vue";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import {
  mdiViewList,
  mdiAccountBoxMultiple,
  mdiEyeOutline, mdiEyeOffOutline
} from "@mdi/js";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import CardBox from "@/components/CardBox.vue";
import FormField from "@/components/FormField.vue";
import FormControl from "@/components/FormControl.vue";
import FormCheckRadio from "@/components/FormCheckRadio.vue";

const props = defineProps({
  formdata: {
    type: Object,
    default: () => ({}),
  },
  roles: {
    type: Object,
    default: () => ({}),
  },
});

const selectOptions = props.roles;

const formData = {
  name: props.formdata.name,
  email: props.formdata.email,
  twofa: props.formdata.twofa == 1 ? true : false,
  role: props.formdata.role ? props.formdata.role[0] : selectOptions[0],
  password: ''
};


const form = useForm(formData);

const submitform = () => {
  if (props.formdata.id) {
    form.put(route('user.update', props.formdata.id));
  }
  else {
    form.post(route('user.store'));
  }
};


</script>
<template>
  <LayoutAuthenticated>

    <Head title="Users" />
    <SectionMain>
      <SectionTitleLineWithButton :icon="mdiAccountBoxMultiple" title="Users" main>
        <div class="flex">
          <Link :href="route('user.index')">
          <BaseButton class="m-2" :icon="mdiViewList" color="success" rounded-full small label="List Users" />
          </Link>

        </div>
      </SectionTitleLineWithButton>
      <form @submit.prevent="submitform" autocomplete="off">
        <CardBox>
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <FormField label="Name" help="" :error="form.errors.name">
              <FormControl name="name" v-model="form.name" required />
            </FormField>
            <FormField label="Email" help="" :error="form.errors.email">
              <FormControl name="eemail" v-model="form.email" autocomplete="new-email" required />
            </FormField>
            <FormField v-if="props.formdata.id" label="Password" help="leave blank if not to change"
              :error="form.errors.password">
              <FormControl name="ppassword" v-model="form.password" type="password" autocomplete="off" />

            </FormField>
            <FormField v-else label="Password" help="" :error="form.errors.password">
              <FormControl name="ppassword" v-model="form.password" type="password" autocomplete="new-password"
                required />
            </FormField>

            <FormField label="Role" :error="form.errors.role">
              <FormControl v-model="form.role" :options="selectOptions" required />
            </FormField>
            <FormField label="2FA">
              <FormCheckRadio type="switch" v-model="form.twofa" name="twofa" input-value="0" label='ON' prelabel='OFF' />
            </FormField>
          </div>
        </CardBox>

        <div class="mt-4 flex">
          <BaseButton class="mr-2" type="submit" small color="info" :label="props.formdata.id ? 'Update' : 'Save'" />
          <Link :href="route('user.index')">
          <BaseButton type="reset" small color="info" outline label="Cancel" />
          </Link>
        </div>

      </form>
    </SectionMain>
  </LayoutAuthenticated>
</template>
