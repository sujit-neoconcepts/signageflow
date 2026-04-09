<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import { computed, ref, onMounted } from "vue";
import { mdiFileEdit, mdiTrashCan } from "@mdi/js";
import CardBoxModal from "@/components/CardBoxModal.vue";
import BaseLevel from "@/components/BaseLevel.vue";
import BaseButtons from "@/components/BaseButtons.vue";
import BaseButton from "@/components/BaseButton.vue";
import { router } from "@inertiajs/vue3";
import { can } from '@/utils/permissions';
const props = defineProps({
  listData: {
    type: Array,
    required: true,
  },
  actions: {
    type: Array,
    required: true,
  },

  resource: {
    type: String,
    required: true,
  },
  fields: {
    type: Array,
    required: true,
  }
});


const items = computed(() => props.listData);

const delselect = ref(0);
const isModalDangerActive = ref(false);

const deletePage = () => {
  if (delselect.value != 0) {
    router.delete(route(props.resource + ".destroy", delselect.value), {
      preserveScroll: true,
      resetOnSuccess: false,
      onFinish: () => {
        delselect.value = 0;
      }
    });
  }
}


const perPage = ref(10);
const currentPage = ref(0);
const itemsPaginated = computed(() =>
  items.value.slice(
    perPage.value * currentPage.value,
    perPage.value * (currentPage.value + 1)
  )
);

const numPages = computed(() => Math.ceil(items.value.length / perPage.value));
const currentPageHuman = computed(() => currentPage.value + 1);
const pagesList = computed(() => {
  const pagesList = [];
  for (let i = 0; i < numPages.value; i++) {
    pagesList.push(i);
  }
  return pagesList;
});



</script>

<template>
  <CardBoxModal v-model="isModalDangerActive" buttonLabel="Confirm" title="Please confirm" button="danger" has-cancel
    @confirm="deletePage">
    <p>Are you sure to delete?</p>
  </CardBoxModal>

  <table>
    <thead>
      <tr>
        <th v-for="field in props.fields">{{ field[1] }}</th>
        <th />
      </tr>
    </thead>
    <tbody>
      <tr v-for="client in itemsPaginated" :key="client.id">
        <td v-for="field in props.fields" :data-label="field[1]">
          <span v-html="client[field[0]]"></span>
        </td>

        <td class="before:hidden lg:w-16 whitespace-nowrap flex justify-between">
          <Link :href="route(props.resource + '.edit', client.id)" class="-mb-3 mr-2" v-if="(props.actions.indexOf('u') !== -1) &&
            can(props.resource + '_edit')">
          <BaseButton color="info" :icon="mdiFileEdit" small />
          </Link>
          <BaseButton color="danger" :icon="mdiTrashCan" small @click="delselect = client.id; isModalDangerActive = true"
            v-if="(props.actions.indexOf('d') !== -1) && can(props.resource + '_delete')" />
        </td>
      </tr>
    </tbody>
  </table>
  <div class="p-3 lg:px-6 border-t border-gray-100 dark:border-slate-800">
    <BaseLevel>
      <BaseButtons>
        <BaseButton v-for="page in pagesList" :key="page" :active="page === currentPage" :label="page + 1"
          :color="page === currentPage ? 'lightDark' : 'whiteDark'" small @click="currentPage = page" />
      </BaseButtons>
      <small>Page {{ currentPageHuman }} of {{ numPages }}</small>
    </BaseLevel>
  </div>
</template>
