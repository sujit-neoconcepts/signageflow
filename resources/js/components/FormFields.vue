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
    form: {
        type: Object,
        default: {},
    },
    fkey: {
        type: String,
        default: "",
    },
    onChangeFunc: {
        type: Function,
        default: () => {},
        required: false,
    },
});
const filteredOption = (source, sKey, sFetch, compVal, key) => {
    let redata = [];
    source.forEach((element) => {
        if (element[sKey] == compVal) {
            redata.push(element[sFetch]);
        }
    });
    props.form[key] = redata.includes(props.form[key]) ? props.form[key] : null;
    return redata;
};

const filteredOptionObj = (source, sKey, compVal, key) => {
    let redata = [];
    source.forEach((element) => {
        if (element[sKey] == compVal) {
            redata.push(element);
        }
    });
    const exists = redata.find(v => v.id == props.form[key]?.id);
    if (!exists) {
        props.form[key] = null;
    }
    return redata;
};
</script>
<template>
    <div v-if="formField.type == 'dummy'"></div>
    <Multiselect
        v-else-if="formField.type == 'multiselect'"
        :multiple="true"
        :disabled="formField.disabled"
        track-by="id"
        label="label"
        select-label=""
        v-model="form[fkey]"
        :options="formField.options"
        @select="onChangeFunc(fkey)"
    >
    </Multiselect>
    <Multiselect
        v-else-if="
            formField.type == 'select' && formField.optionType == 'array'
        "
        v-model="form[fkey]"
        select-label=""
        :disabled="formField.disabled"
        :options="
            formField.filter
                ? filteredOption(
                      formField.options,
                      formField.filter['comp'],
                      formField.filter['fetch'],
                      form[formField.filter['on']],
                      fkey
                  )
                : formField.options
        "
        @select="onChangeFunc(fkey)"
    >
    </Multiselect>
    <Multiselect
        v-else-if="formField.type == 'select'"
        track-by="id"
        label="label"
        :disabled="formField.disabled"
        select-label=""
        v-model="form[fkey]"
        :options="
            formField.filter
                ? filteredOptionObj(
                      formField.options,
                      formField.filter['comp'],
                      form[formField.filter['on']],
                      fkey
                  )
                : formField.options
        "
        @select="onChangeFunc(fkey)"
    >
    </Multiselect>
    <FormControl
        v-else-if="formField.type == 'number'"
        type="number"
        :disabled="formField.disabled"
        :readonly="formField.readonly"
        :step="formField.step || 'any'"
        :name="fkey"
        v-model="form[fkey]"
        @update:modelValue="onChangeFunc(fkey)"
    />
    <FormControl
        v-else-if="formField.type == 'password'"
        type="password"
        :name="fkey"
        v-model="form[fkey]"
        @update:modelValue="onChangeFunc(fkey)"
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
        v-model="form[fkey]"
    >
    </VueDatePicker>
    <FormControl
        v-else-if="formField.type == 'file'"
        type="file"
        :disabled="formField.disabled"
        :readonly="formField.readonly"
        :name="fkey"
        @input="form[fkey] = $event.target.files[0]"
        @update:modelValue="onChangeFunc(fkey)"
    />
    <FormControl
        v-else
        :disabled="formField.disabled"
        :readonly="formField.readonly"
        :placeholder="formField.placeholder"
        :name="fkey"
        v-model="form[fkey]"
        @update:modelValue="onChangeFunc(fkey)"
    />
</template>
