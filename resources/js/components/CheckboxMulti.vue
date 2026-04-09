<template>
    <div class="grid grid-cols-2">
        <div v-for="option in options">
            <Checkbox :checked="modelValue && modelValue.includes(option.id)" @update:checked="check(option.id, $event)"
                :value="option.id" :label="option.label" :key="option" />&nbsp;
        </div>
    </div>
</template>
  
<script setup>
import Checkbox from './Checkbox.vue';

const props = defineProps({
    modelValue: {
        type: Array,
        required: true,
    },
    options: {
        type: Array,
        required: true,
        validator: (value) => {
            const hasNameKey = value.every((option) =>
                Object.keys(option).includes("label")
            );
            const hasIdKey = value.every((option) =>
                Object.keys(option).includes("id")
            );
            return hasNameKey && hasIdKey;
        },
    },
},
);
const emit = defineEmits(["update:modelValue"]);

const check = (optionId, checked) => {
    let updatedValue = [...props.modelValue];
    if (checked) {
        updatedValue.push(optionId);
    } else {
        updatedValue.splice(updatedValue.indexOf(optionId), 1);
    }
    emit("update:modelValue", updatedValue);
};
</script>