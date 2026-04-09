<template>
  <div class="flex flex-row justify-between mb-1">
    <div class="pt-1"> Sort By</div>
    <div class="w-48">
      <select :name="`rsl_${index}`" :value="column.f"
        class="block focus:border-transparent focus:ring-0  text-gray-500 dark:text-gray-100 w-full shadow-sm text-sm bg-gray-100 dark:bg-slate-600 rounded-md"
        @change="emit('sortfield', [index, $event.target.value])">
        <option v-for="option in getOptions" :key="option.id ?? option" :value="option.id"
          :disabled="getSelected.includes(option.id)">
          {{ option.label ?? option }}
        </option>
      </select>

    </div>
    <div class="pt-1"> <input type="radio" :name="`r_${index}`" @change="emit('sortorder', [index, 1])"
        :checked="(column.o == 1)"> A -> Z
      &nbsp;&nbsp;&nbsp;<input type="radio" :name="`r_${index}`" @change="emit('sortorder', [index, 2])"
        :checked="(column.o == 2)"> Z -> A </div>
    <div class="pt-1 w-10">
      <div v-show="index != 0" class="cursor-pointer" @click="emit('delrow', index)"><svg
          xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
          <path fill=" currentColor"
            d="M9,3V4H4V6H5V19A2,2 0 0,0 7,21H17A2,2 0 0,0 19,19V6H20V4H15V3H9M9,8H11V17H9V8M13,8H15V17H13V8Z"></path>
        </svg>
      </div>
    </div>
  </div>
</template>

<script setup>

import { computed } from 'vue';

const props = defineProps({
  index: {
    type: Number,
    required: true,
  },
  column: {
    type: Object,
    required: true,
  },
  advSortFields: {
    type: Array,
  },
  allcolumns: {
    type: Object,
    required: true,
  },
});
const emit = defineEmits(["delrow", "sortorder", "sortfield"]);
const getOptions = computed(() => {
  let temp = [];
  props.allcolumns.forEach(element => {
    if (element.sortable) {
      temp.push({ id: element.key, label: element.label })
    }
  });
  return temp;
});

const getSelected = computed(() => {
  let temp = [];
  props.advSortFields.forEach(element => {
    if (element.f != '') {
      temp.push(element.f)
    }
  });
  return temp;
});
</script>

