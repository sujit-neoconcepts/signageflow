<script setup>
import FormControl from "@/components/FormControl.vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import Multiselect from "vue-multiselect";
import "../../css/vue-multiselect.css";
const props = defineProps({
    formField: {
        type: Object,
        default: {},
    },
    multiDatasModel: {
        type: Object,
        default: {},
    },
    fkey: {
        type: String,
        default: "",
    },
    pkey: {
        type: Number,
        default: 0,
    },
    onChangeFunc: {
        type: Function,
        default: () => {},
        required: false,
    },
});
const filteredOptionMulti = (source, sKey, sFetch, compVal, key, pkey) => {
    let redata = [];
    source.forEach((element) => {
        if (element[sKey] == compVal) {
            redata.push(element[sFetch]);
        }
    });
    props.multiDatasModel[pkey][key] = redata.includes(
        props.multiDatasModel[pkey][key]
    )
        ? props.multiDatasModel[pkey][key]
        : null;
    return redata;
};
</script>
<template>
    <Multiselect
        v-if="formField.type == 'multiselect'"
        :multiple="true"
        track-by="id"
        label="label"
        :disabled="formField.disabled"
        select-label=""
        v-model="multiDatasModel[pkey][fkey]"
        :options="formField.options"
        @select="onChangeFunc(pkey, fkey)"
    >
    </Multiselect>
    <Multiselect
        v-else-if="
            formField.type == 'select' && formField.optionType == 'array'
        "
        v-model="multiDatasModel[pkey][fkey]"
        select-label=""
        :disabled="formField.disabled"
        @select="onChangeFunc(pkey, fkey)"
        :options="
            formField.filter
                ? filteredOptionMulti(
                      formField.options,
                      formField.filter['comp'],
                      formField.filter['fetch'],
                      multiDatasModel[pkey][formField.filter['on']],
                      fkey,
                      pkey
                  )
                : formField.options
        "
    >
    </Multiselect>
    <Multiselect
        v-else-if="formField.type == 'select'"
        track-by="id"
        label="label"
        select-label=""
        :disabled="formField.disabled"
        v-model="multiDatasModel[pkey][fkey]"
        :options="formField.options"
        @select="onChangeFunc(pkey, fkey)"
    >
    </Multiselect>
    <FormControl
        v-else-if="formField.type == 'number'"
        type="number"
        :name="fkey"
        :readonly="formField.readonly"
        :color="formField.color"
        v-model="multiDatasModel[pkey][fkey]"
        @update:modelValue="onChangeFunc(pkey, fkey)"
    />
    <FormControl
        v-else-if="formField.type == 'password'"
        type="password"
        :name="fkey"
        :color="formField.color"
        v-model="multiDatasModel[pkey][fkey]"
        @update:modelValue="onChangeFunc(pkey, fkey)"
    />
    <VueDatePicker
        v-else-if="formField.type == 'datepicker'"
        input-class-name="text-gray-500 dark:!text-white shadow-sm text-sm !bg-white dark:!bg-slate-800 !border-gray-700 "
        :month-change-on-scroll="false"
        :range="false"
        :enable-time-picker="false"
        arrow-navigation
        format="dd-MM-yyyy"
        model-type="yyyy-MM-dd"
        auto-apply
        v-model="multiDatasModel[pkey][fkey]"
    >
    </VueDatePicker>
    <FormControl
        v-else
        :name="fkey"
        v-model="multiDatasModel[pkey][fkey]"
        :disabled="formField.disabled"
        :readonly="formField.readonly"
        :color="formField.color"
        @update:modelValue="onChangeFunc(pkey, fkey)"
    />
</template>
