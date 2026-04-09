<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import {
  mdiViewList,
  mdiAccountGroup,
  mdiAlert,
} from "@mdi/js";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import CardBox from "@/components/CardBox.vue";
import NotificationBar from "@/components/NotificationBar.vue";

import FormCheckRadio from "@/components/FormCheckRadio.vue";

const message = computed(() => usePage().props.flash.message)
const msg_type = computed(() => usePage().props.flash.msg_type ?? 'warning')

const props = defineProps({

  user: {
    type: Object,
    default: () => ({}),
  },
  allpermissions: {
    type: Object,
    default: () => ({}),
  },
});



const form = useForm({
  userid: props.user.id,
  permission: props.allpermissions,
});

const updatechild = (param) => {
  for (let i = 0; i < props.allpermissions[param].child.length; i++) {
    props.allpermissions[param].child[i][2] = props.allpermissions[param].sts
  }

}


const submitform = () => {
  form.permission = props.allpermissions;
  if (props.user.id) {
    form.put(route('user.permissionsUpdate'));
  }
};
</script>
<template>
  <LayoutAuthenticated>

    <Head title="User's Permissions" />
    <SectionMain>
      <SectionTitleLineWithButton :icon="mdiAccountGroup" title="User's Permissions" main>
        <div class="flex">
          <Link :href="route('role.index')">
          <BaseButton class="m-2" :icon="mdiViewList" color="success" rounded-full small label="List Roles" />
          </Link>
        </div>
      </SectionTitleLineWithButton>
      <NotificationBar v-if="message" @closed="usePage().props.flash.message = ''" :color="msg_type" :icon="mdiAlert"
        :outline="true">
        {{ message }}
      </NotificationBar>
      <form @submit.prevent="submitform">
        <CardBox>
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <h3>{{ user.name }}</h3>
          </div>
        </CardBox>
        <h2 class="m-3">Permissions</h2>
        <CardBox>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="(item, index) in allpermissions" class="capitalize">
              <div class="flex justify-between mb-1">
                <div>{{ item.name }}</div>
                <div>
                  <FormCheckRadio type="switch" v-model="item.sts" name="index" @update:modelValue="updatechild(index)"
                    prelabel="All Permissions" input-value="0" />
                </div>
              </div>
              <div
                class="rounded bg-gray-50 dark:bg-slate-800 dark:text-slate-100 p-2 grid grid-cols-1 lg:grid-cols-2 gap-3">
                <div v-for="perm in item.child">
                  <FormCheckRadio :name="perm[1]" v-model="perm[2]" input-value="0" :label="perm[1]" />
                </div>
              </div>
            </div>


          </div>
        </CardBox>
        <div class="mt-4 flex">
          <BaseButton class="mr-2" type="submit" small color="info" :label="props.user.id ? 'Update' : 'Save'" />
          <Link :href="route('role.index')">
          <BaseButton type="reset" small color="info" outline label="Cancel" />
          </Link>
        </div>

      </form>
    </SectionMain>
  </LayoutAuthenticated>
</template>
