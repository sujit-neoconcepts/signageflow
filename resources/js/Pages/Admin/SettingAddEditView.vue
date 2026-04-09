<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from "vue";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import {
  mdiImage,
  mdiPackage,
} from "@mdi/js";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import CardBox from "@/components/CardBox.vue";
import FormField from "@/components/FormField.vue";
import FormControl from "@/components/FormControl.vue";

const props = defineProps({
  formdata: {
    type: Object,
    default: () => ({}),
  },
});

const form = useForm({
  slug: props.formdata.slug,
  label: props.formdata.label,
  value: props.formdata.value,
  vtype: props.formdata.vtype,
  group: props.formdata.group,
  access_roles: props.formdata.access_roles,
});

const submitform = () => {
  if (props.formdata.id) {
    form.put(route('setting.update', props.formdata.id));
  }
  else {
    form.post(route('setting.store'));
  }
};
</script>
<template>
  <LayoutAuthenticated>

    <Head title="Settings" />
    <SectionMain>
      <SectionTitleLineWithButton :icon="mdiPackage" title="Settings" main>
        <div class="flex">
          <Link :href="route('setting.index')">
          <BaseButton class="m-2" :icon="mdiImage" color="success" rounded-full small label="List Settings" />
          </Link>

        </div>
      </SectionTitleLineWithButton>
      <form @submit.prevent="submitform">
        <CardBox>
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <FormField label="Slug" help="" :error="form.errors.slug">
              <FormControl name="slug" v-model="form.slug" required />
            </FormField>
            <FormField label="Label" help="" :error="form.errors.label">
              <FormControl name="label" v-model="form.label" required />
            </FormField>
            <FormField label="Value" help="" :error="form.errors.value">
              <FormControl name="value" v-model="form.value" required />
            </FormField>
            <FormField label="Type" help="" :error="form.errors.vtype">
              <FormControl name="vtype" v-model="form.vtype" required />
            </FormField>
            <FormField label="Group" help="" :error="form.errors.group">
              <FormControl name="group" v-model="form.group" required />
            </FormField>
            <FormField label="Access Roles" help="" :error="form.errors.access_roles">
              <FormControl name="access_roles" v-model="form.access_roles" required />
            </FormField>
          </div>
        </CardBox>

        <div class="mt-4 flex">
          <BaseButton class="mr-2" type="submit" small color="info" :label="props.formdata.id ? 'Update' : 'Save'" />
          <Link :href="route('setting.index')">
          <BaseButton type="reset" small color="info" outline label="Cancel" />
          </Link>
        </div>

      </form>
    </SectionMain>
  </LayoutAuthenticated>
</template>
