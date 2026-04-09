<script setup>
import { computed, useSlots } from "vue";
import { mdiPlusCircle, mdiRefreshCircle } from "@mdi/js";
import IconRounded from "@/components/IconRounded.vue";
defineProps({
    label: {
        type: String,
        default: null,
    },
    labelFor: {
        type: String,
        default: null,
    },
    help: {
        type: String,
        default: null,
    },
    error: {
        type: String,
        default: null,
    },
    addAndRefresh: {
        type: Boolean,
        default: false,
    },
    addFunction: {
        type: Function,
        default: () => {},
        required: false,
    },
    refreshFunction: {
        type: Function,
        default: () => {},
        required: false,
    },
    fkey: {
        type: String,
        default: "",
    },
    pkey: {
        type: Number,
        default: 0,
    },
});

const slots = useSlots();

const wrapperClass = computed(() => {
    const base = [];
    const slotsLength = slots.default().length;

    if (slotsLength > 1) {
        base.push("grid grid-cols-1 gap-3");
    }

    if (slotsLength === 2) {
        base.push("md:grid-cols-2");
    }

    return base;
});
</script>

<template>
    <div class="mb-6 last:mb-0">
        <label v-if="label" :for="labelFor" class="block font-bold mb-2"
            >{{ label
            }}<span v-if="addAndRefresh" class="float-right mr-1"
                ><IconRounded
                    :icon="mdiPlusCircle"
                    color="light"
                    class="cursor-pointer"
                    bg
                    @click="addFunction(pkey, fkey)" />
                &nbsp
                <IconRounded
                    :icon="mdiRefreshCircle"
                    color="light"
                    class="cursor-pointer"
                    bg
                    @click="refreshFunction(pkey, fkey)" /></span
        ></label>
        <div :class="wrapperClass">
            <slot />
        </div>
        <div v-if="help" class="text-xs text-gray-500 dark:text-slate-400 mt-1">
            {{ help }}
        </div>
        <div v-if="error" class="text-sm text-red-600 mt-1">
            {{ error }}
        </div>
    </div>
</template>
