<script setup>
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";

import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import { mdiFormatListBulleted } from "@mdi/js";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import CardBox from "@/components/CardBox.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import FormField from "@/components/FormField.vue";
import FormFields from "@/components/FormFields.vue";
import FormFieldsMulti from "@/components/FormFieldsMulti.vue";

const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");

import clone from "lodash-es/clone";
import { computed, onBeforeMount, ref } from "vue";
import { getTodayString } from "@/helpers/helpers";
const props = defineProps({
    formdata: {
        type: Object,
        default: () => ({}),
    },
    resourceNeo: {
        type: Object,
        default: () => ({}),
    },
});

const form = useForm(() => {
    const temp = clone(props.resourceNeo.formInfo);
    temp["multi"] = [];
    return temp;
});
const multiDatas = ref([props.resourceNeo.formInfoMulti]);
const addMoreMulti = () => {
    multiDatas.value.push(props.resourceNeo.formInfoMulti);
    let tepm = {};
    for (const key in props.resourceNeo.formInfoMulti) {
        tepm[key] = "";
    }
    form["multi"].push(tepm);
};
const delMulti = (el) => {
    multiDatas.value.splice(el * 1, 1);
    form["multi"].splice(el * 1, 1);
};
onBeforeMount(() => {
    for (const key in props.resourceNeo.formInfo) {
        form[key] =
            props.formdata[key] ??
            props.resourceNeo.formInfo[key]["default"] ??
            "";
        if (props.resourceNeo.formInfo[key]["type"] == "datepicker") {
            form[key] = form[key] != "" ? form[key] : getTodayString();
        }
    }
    if (props.formdata["multi"] && props.formdata["multi"].length > 0) {
        for (const index in props.formdata["multi"]) {
            let tepm = {};
            for (const key in props.resourceNeo.formInfoMulti) {
                tepm[key] = props.formdata["multi"][index][key];
            }
            form["multi"].push(tepm);
        }
    } else {
        let tepm = {};
        for (const key in props.resourceNeo.formInfoMulti) {
            tepm[key] = props.resourceNeo.formInfoMulti[key]["default"] ?? "";

            if (props.resourceNeo.formInfoMulti[key]["type"] == "datepicker") {
                tepm[key] = tepm[key] != "" ? tepm[key] : getTodayString();
            }
        }
        form["multi"].push(tepm);
    }
});

const submitform = () => {
    if (props.formdata.id) {
        form.put(
            route(props.resourceNeo.resourceName + ".update", props.formdata.id)
        );
    } else {
        form.post(route(props.resourceNeo.resourceName + ".store"));
    }
};
const onChangeFunc = (index, fkey) => {};
</script>
<template>
    <LayoutAuthenticated>
        <Head :title="props.resourceNeo.resourceTitle" />
        <SectionMain>
            <SectionTitleLineWithButton
                :icon="props.resourceNeo.iconPath"
                :title="props.resourceNeo.resourceTitle"
                main
            >
                <div class="flex">
                    <Link
                        :href="route(props.resourceNeo.resourceName + '.index')"
                    >
                        <BaseButton
                            class="m-2"
                            :icon="mdiFormatListBulleted"
                            color="success"
                            rounded-full
                            small
                            :label="'List ' + props.resourceNeo.resourceTitle"
                        />
                    </Link>
                </div>
            </SectionTitleLineWithButton>
            <NotificationBar
                v-if="message"
                @closed="usePage().props.flash.message = ''"
                :color="msg_type"
                :icon="mdiAlert"
                :outline="true"
            >
                {{ message }}
            </NotificationBar>
            <form @submit.prevent="submitform">
                <CardBox>
                    <div
                        class="grid grid-cols-1 gap-6"
                        :class="[
                            'lg:grid-cols-' + (props.resourceNeo.fColumn ?? 2),
                        ]"
                    >
                        <FormField
                            v-for="(formField, key) in props.resourceNeo
                                .formInfo"
                            :label="formField.label"
                            :help="formField.tooltip"
                            :error="form.errors[key]"
                            class="!mb-0"
                            :class="'col-span-' + (formField.colspan || 1)"
                        >
                            <FormFields
                                :form-field="formField"
                                :form="form"
                                :fkey="key"
                            />
                        </FormField>
                    </div>
                    <h1 class="font-bold text-xl border-b-2 mb-2 mt-2">
                        Specifications:
                    </h1>
                    <div
                        class="grid grid-cols-1 gap-6 border-b mb-2 pb-2"
                        v-for="(multiData, pkey) in multiDatas"
                        :class="[
                            'lg:grid-cols-' +
                                (props.resourceNeo.fColumnMulti
                                    ? props.resourceNeo.fColumnMulti
                                    : props.resourceNeo.fColumn ?? 2),
                        ]"
                    >
                        <FormField
                            v-for="(formField, key) in multiData"
                            :label="formField.label"
                            :help="formField.tooltip"
                            :error="form.errors['multi.' + pkey + '.' + key]"
                            class="!mb-0"
                            :class="'col-span-' + (formField.colspan || 1)"
                        >
                            <FormFieldsMulti
                                :form-field="formField"
                                :multiDatasModel="form['multi']"
                                :form="form"
                                :fkey="key"
                                :pkey="pkey"
                                :onChangeFunc="onChangeFunc"
                            />
                        </FormField>
                        <div
                            class="col-span-1 text-right flex items-center justify-start"
                            v-if="multiDatas.length > 1"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill=" currentColor"
                                    @click="delMulti(pkey)"
                                    class="cursor-pointer"
                                    d="M9,3V4H4V6H5V19A2,2 0 0,0 7,21H17A2,2 0 0,0 19,19V6H20V4H15V3H9M9,8H11V17H9V8M13,8H15V17H13V8Z"
                                ></path>
                            </svg>
                        </div>
                    </div>
                    <div
                        class="mt-4"
                        v-if="!props.formdata.id || props.resourceNeo.AllowMore"
                    >
                        <BaseButton
                            label="+ Add More"
                            color="success"
                            @click="addMoreMulti"
                        />
                    </div>
                </CardBox>

                <div class="mt-4 flex">
                    <BaseButton
                        class="mr-2"
                        type="submit"
                        small
                        :disabled="form.processing"
                        color="info"
                        :label="props.formdata.id ? 'Update' : 'Save'"
                    />
                    <Link
                        :href="route(props.resourceNeo.resourceName + '.index')"
                    >
                        <BaseButton
                            type="reset"
                            small
                            color="info"
                            outline
                            label="Cancel"
                        />
                    </Link>
                </div>
            </form>
        </SectionMain>
    </LayoutAuthenticated>
    <div class="grid lg:grid-cols-5"></div>
</template>
