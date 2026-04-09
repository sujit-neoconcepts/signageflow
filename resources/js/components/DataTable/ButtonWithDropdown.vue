<template>
  <OnClickOutside :do="hide">
    <div class="relative">
      <button ref="button" type="button" :dusk="dusk" :disabled="disabled"
        class="w-full border border-gray-200 dark:border-slate-700 bg-gray-200 dark:bg-slate-700 text-opacity rounded-md shadow-sm px-4 py-2 inline-flex justify-center text-sm font-medium  hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
        :class="{ 'border-green-300': active, 'border-gray-300': !active, 'cursor-not-allowed': disabled }"
        aria-haspopup="true" @click.prevent="toggle">
        <slot name="button" />
      </button>

      <div v-show="opened" ref="tooltip" class="absolute z-50">
        <div class="p-2 mt-2 rounded shadow-lg dark:bg-slate-900 bg-white text-white ring-1 ring-black ring-opacity-5">
          <slot />
        </div>
      </div>
    </div>
  </OnClickOutside>
</template>

<script setup>
import OnClickOutside from "./OnClickOutside.vue";
import { createPopper } from "@popperjs/core/lib/popper-lite";
import preventOverflow from "@popperjs/core/lib/modifiers/preventOverflow";
import flip from "@popperjs/core/lib/modifiers/flip";
import { ref, watch, onMounted } from "vue";

const props = defineProps({
  placement: {
    type: String,
    default: "bottom-end",
    required: false,
  },

  active: {
    type: Boolean,
    default: false,
    required: false,
  },

  dusk: {
    type: String,
    default: null,
    required: false,
  },

  disabled: {
    type: Boolean,
    default: false,
    required: false,
  },
});

const opened = ref(false);
const popper = ref(null);

function toggle() {
  opened.value = !opened.value;
}

function hide() {
  opened.value = false;
}

watch(opened, () => {
  popper.value.update();
});

const button = ref(null);
const tooltip = ref(null);

onMounted(() => {
  popper.value = createPopper(button.value, tooltip.value, {
    placement: props.placement,
    modifiers: [flip, preventOverflow],
  });
});

defineExpose({ hide });
</script>
