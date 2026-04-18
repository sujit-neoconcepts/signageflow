<template>
  <div v-for="(filter, key) in    filters   " :key="key">
    <FormControl v-if="getColumn(filter.key).type === 'select'" v-model="computedValue[filter.key]"
      :options="getColumn(filter.key).options" @keydown.enter.prevent="keydownHandler" />
    <div v-else-if="getColumn(filter.key).type === 'datePicker'" class="grid grid-cols-1 lg:grid-cols-2 gap-1">
      <VueDatePicker
        input-class-name="!border-gray-700 text-gray-400 dark:text-slate-200 dark:placeholder-gray-400 shadow-sm text-sm bg-gray-100 dark:bg-slate-800"
        model-type="yyyy-MM-dd"
        @update:model-value="setFilterValue(`${filter.key}_start`, $event)" :month-change-on-scroll="false" :range="false"
        :enable-time-picker="false" format="dd-MM-yyyy" auto-apply :model-value="computedValue[`${filter.key}_start`]"
        :name="`${filter.key}_start`" :placeholder="`${filter.label}  From`"
        v-if="getColumn(filter.key).type === 'datePicker'">
      </VueDatePicker>
      <VueDatePicker
        input-class-name="!border-gray-700 text-gray-400 dark:text-slate-200 dark:placeholder-gray-400 shadow-sm text-sm bg-gray-100 dark:bg-slate-800"
        model-type="yyyy-MM-dd"
        @update:model-value="setFilterValue(`${filter.key}_end`, $event)" :month-change-on-scroll="false" :range="false"
        :enable-time-picker="false" format="dd-MM-yyyy" auto-apply :model-value="computedValue[`${filter.key}_end`]"
        :name="`${filter.key}_end`" :placeholder="`${filter.label}  To`"
        v-if="getColumn(filter.key).type === 'datePicker'">
      </VueDatePicker>
    </div>
    <FormControl v-else :name="filter.key" v-model="computedValue[filter.key]" :placeholder="filter.label"
      @keydown.enter.prevent="keydownHandler" />
  </div>
</template>

<script setup>
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css'
import FormControl from "@/components/FormControl.vue";
import { ref, onMounted, computed } from 'vue';

const props = defineProps({
  filters: {
    type: Object,
    required: true,
  },
  columns: {
    type: Object,
    required: true,
  },
  modelValue: {
    type: [String, Number, Boolean, Array, Object],
    default: [],
  },

});

const emit = defineEmits(["update:modelValue", 'formSubmit']);

const computedValue = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

const keydownHandler = (e) => {
  emit("formSubmit")
}
function getColumn(key) {
  let foundcoulmn = null;
  props.columns.forEach(element => {
    if (element.key == key) {
      foundcoulmn = element.extra;
      return;
    }
  });
  return foundcoulmn;
}

function setFilterValue(key, val) {
  var d = new Date(val),
    month = (d.getMonth() + 1),
    day = d.getDate(),
    year = d.getFullYear();
  computedValue.value[key] = val ? year + '-' + month + '-' + day : '';
}
</script>

