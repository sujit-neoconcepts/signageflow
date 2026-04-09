<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import {
  mdiViewList,
  mdiAccountGroup,
} from "@mdi/js";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import CardBox from "@/components/CardBox.vue";
import FormField from "@/components/FormField.vue";
import FormControl from "@/components/FormControl.vue";
import FormCheckRadio from "@/components/FormCheckRadio.vue";
import menuAside from "@/menuAside.js";
import { computed } from 'vue';

const props = defineProps({
  formdata: {
    type: Object,
    default: () => ({}),
  },
  allpermissions: {
    type: Object,
    default: () => ({}),
  },
});

const form = useForm({
  name: props.formdata.name,
  permission: props.allpermissions,
});

const updatechild = (param) => {
  for (let i = 0; i < props.allpermissions[param].child.length; i++) {
    props.allpermissions[param].child[i][2] = props.allpermissions[param].sts
  }
}

// Organize permissions based on menuAside structure
const organizedPermissions = computed(() => {
  const usedKeys = new Set();
  const groups = [];

  // Helper to extract resources from menu items recursively
  const extractResources = (items) => {
    const resources = [];
    for (const item of items) {
      if (item.resource && props.allpermissions[item.resource]) {
        resources.push({
          key: item.resource,
          label: item.label,
          data: props.allpermissions[item.resource]
        });
        usedKeys.add(item.resource);
      }
      if (item.menu) {
        resources.push(...extractResources(item.menu));
      }
    }
    return resources;
  };

  // Process each top-level menu item
  for (const menuItem of menuAside) {
    // Top-level item with resource (like Dashboard, Expense)
    if (menuItem.resource && props.allpermissions[menuItem.resource]) {
      groups.push({
        label: menuItem.label,
        items: [{
          key: menuItem.resource,
          label: menuItem.label,
          data: props.allpermissions[menuItem.resource]
        }]
      });
      usedKeys.add(menuItem.resource);
    }
    // Top-level menu with submenu (like Masters, Sales, etc.)
    else if (menuItem.menu) {
      const items = extractResources(menuItem.menu);
      if (items.length > 0) {
        groups.push({
          label: menuItem.label,
          items: items
        });
      }
    }
  }

  // Group remaining permissions by prefix patterns
  const logsItems = [];
  const consumableItems = [];
  const others = [];

  for (const key in props.allpermissions) {
    if (usedKeys.has(key)) continue;
    
    const item = {
      key: key,
      label: props.allpermissions[key].name || key,
      data: props.allpermissions[key]
    };

    // Group by prefix
    if (key === 'signinlog' || key === 'activitylog') {
      logsItems.push(item);
    } else if (key.startsWith('consumable')) {
      consumableItems.push(item);
    } else {
      others.push(item);
    }
  }

  // Add special groups (but merge into existing groups if they exist)
  if (logsItems.length > 0) {
    const existingLogs = groups.find(g => g.label === 'Logs');
    if (existingLogs) {
      existingLogs.items.push(...logsItems);
    } else {
      groups.push({ label: 'Logs', items: logsItems });
    }
  }
  if (consumableItems.length > 0) {
    // Merge into existing Consumables group if it exists
    const existingConsumables = groups.find(g => g.label === 'Consumables');
    if (existingConsumables) {
      existingConsumables.items.push(...consumableItems);
    } else {
      groups.push({ label: 'Consumables', items: consumableItems });
    }
  }
  if (others.length > 0) {
    groups.push({ label: 'Others', items: others });
  }

  return groups;
});

const submitform = () => {
  form.permission = props.allpermissions;
  if (props.formdata.id) {
    form.put(route('role.update', props.formdata.id));
  }
  else {
    form.post(route('role.store'));
  }
};
</script>
<template>
  <LayoutAuthenticated>

    <Head title="Roles" />
    <SectionMain>
      <SectionTitleLineWithButton :icon="mdiAccountGroup" title="Roles" main>
        <div class="flex">
          <Link :href="route('role.index')">
          <BaseButton class="m-2" :icon="mdiViewList" color="success" rounded-full small label="List Roles" />
          </Link>
        </div>
      </SectionTitleLineWithButton>
      <form @submit.prevent="submitform">
        <CardBox>
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <FormField label="Name" help="" :error="form.errors.name">
              <FormControl name="name" v-model="form.name" required />
            </FormField>
          </div>
        </CardBox>
        
        <!-- Organized by menu structure -->
        <div v-for="(group, gIndex) in organizedPermissions" :key="gIndex">
          <h2 class="m-3 text-lg font-semibold mt-6 mb-2 border-b pb-2">{{ group.label }}</h2>
          <CardBox class="mb-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <div v-for="item in group.items" :key="item.key" class="capitalize">
                <div class="flex justify-between mb-1">
                  <div>{{ item.data.name }}</div>
                  <div>
                    <FormCheckRadio type="switch" v-model="item.data.sts" :name="item.key" 
                      @update:modelValue="updatechild(item.key)" prelabel="" input-value="0" />
                  </div>
                </div>
                <div class="rounded bg-gray-50 dark:bg-slate-800 dark:text-slate-100 p-2 grid grid-cols-1 lg:grid-cols-2 gap-3">
                  <div v-for="perm in item.data.child" :key="perm[0]">
                    <FormCheckRadio :name="perm[1] + '_' + perm[0]" v-model="perm[2]" input-value="0" :label="perm[1]" />
                  </div>
                </div>
              </div>
            </div>
          </CardBox>
        </div>

        <div class="mt-4 flex">
          <BaseButton class="mr-2" type="submit" small color="info" :label="props.formdata.id ? 'Update' : 'Save'" />
          <Link :href="route('role.index')">
          <BaseButton type="reset" small color="info" outline label="Cancel" />
          </Link>
        </div>

      </form>
    </SectionMain>
  </LayoutAuthenticated>
</template>

