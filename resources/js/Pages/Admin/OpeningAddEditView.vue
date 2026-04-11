<script setup>
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";

import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import { mdiFormatListBulleted } from "@mdi/js";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import CardBox from "@/components/CardBox.vue";
import "@vuepic/vue-datepicker/dist/main.css";
import NotificationBar from "@/components/NotificationBar.vue";
import FormField from "@/components/FormField.vue";
import FormFields from "@/components/FormFields.vue";
import FormFieldsMulti from "@/components/FormFieldsMulti.vue";

const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");

import clone from "lodash-es/clone";
import { computed, onBeforeMount, watch, ref } from "vue";
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
    }
    form["pur_date"] = form["pur_date"] != "" ? form["pur_date"] : getTodayString();
    if (props.formdata["multi"]) {
        for (const index in props.formdata["multi"]) {
            let tepm = {};
            for (const key in props.resourceNeo.formInfoMulti) {
                tepm[key] = props.formdata["multi"][index][key];
            }
            tepm["id"] = props.formdata["multi"][index]["id"];
            if (index > 0) {
                multiDatas.value.push(props.resourceNeo.formInfoMulti);
            }
            for (const opkey in props.resourceNeo.formInfoMulti
                .pur_pr_detail_int.options) {
                if (
                    props.resourceNeo.formInfoMulti.pur_pr_detail_int.options[
                        opkey
                    ].id == props.formdata["pur_pr_id"]
                ) {
                    tepm["pur_pr_detail"] =
                        props.resourceNeo.formInfoMulti.pur_pr_detail_int.options[
                            opkey
                        ];
                }
            }
            form["multi"].push(tepm);
        }
    } else {
        let tepm = {};
        for (const key in props.resourceNeo.formInfoMulti) {
            tepm[key] = "";
        }
        form["multi"].push(tepm);
    }
});

const submitform = () => {
    if (valdateform()) {
        if (props.formdata.id) {
            form.put(
                route(
                    props.resourceNeo.resourceName + ".update",
                    props.formdata.id
                )
            );
        } else {
            form.post(route(props.resourceNeo.resourceName + ".store"));
        }
    }
};

const onChangeFunc = (index, fkey) => {
    if (fkey == "pur_pr_detail_int") {
        fetchProd(index);
    } else if (fkey == "pur_rate_int" || fkey == "pur_qty_int") {
        caltotal(index);
    }
};

const caltotal = (index) => {
    if (
        parseFloat(form["multi"][index].pur_qty_int) > 0 &&
        parseFloat(form["multi"][index].pur_rate_int) > 0
    ) {
        form["multi"][index].pur_amnt =
            parseFloat(form["multi"][index].pur_qty_int) *
            parseFloat(form["multi"][index].pur_rate_int);
    } else {
        form["multi"][index].pur_amnt = 0;
    }
};
const valdateform = () => {
    return true;
};

const fetchProd = (index) => {
    if (form["multi"][index].pur_pr_detail_int.data) {
        form["multi"][index].pur_unint_int =
            form["multi"][index].pur_pr_detail_int.data.unitName;
        form["multi"][index].pur_unint_int_alt =
            form["multi"][index].pur_pr_detail_int.data.unitAltName;
        form["multi"][index].pur_rate_int =
            form["multi"][index].pur_pr_detail_int.data.unitPrice;
        
        form["multi"][index].available_qty =
            form["multi"][index].pur_pr_detail_int.data.available_qty ?? 0;
        form["multi"][index].last_rate =
            form["multi"][index].pur_pr_detail_int.data.last_rate ?? 0;
        form["multi"][index].unit_rate =
            form["multi"][index].pur_pr_detail_int.data.unit_rate ?? 0;
    }
};
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
                        >
                            <FormFields
                                :form-field="formField"
                                :form="form"
                                :fkey="key"
                            />
                        </FormField>
                    </div>
                    <h1 class="font-bold text-xl border-b-2 mb-2 mt-2">
                        {{ props.resourceNeo.Multilabel }}
                    </h1>
                    <div
                        class="grid grid-cols-1 gap-2 border-b mb-2 pb-2"
                        v-for="(multiData, pkey) in multiDatas"
                        :class="[
                            'lg:grid-cols-' +
                                (props.resourceNeo.fColumnMulti
                                    ? props.resourceNeo.fColumnMulti
                                    : props.resourceNeo.fColumn ?? 2),
                        ]"
                    >
                        <div
                            class="col-span-7 text-center font-bold"
                            v-if="form['multi'][pkey]['stripbatch']"
                        >
                            {{ form["multi"][pkey]["stripbatch"] }}
                        </div>
                        <FormField
                            v-for="(formField, key) in multiData"
                            :label="formField.label"
                            :help="formField.tooltip"
                            :error="form.errors['multi.' + pkey + '.' + key]"
                            class="!mb-0"
                            :class="['lg:col-span-' + formField.colspan ?? 1]"
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
                            class="col-span-1 pt-9 text-right"
                            v-if="
                                props.resourceNeo.AllowDel &&
                                multiDatas.length > 1
                            "
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
                        class="mt-4 float-right"
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
    <div
        class="grid lg:grid-cols-6 lg:grid-cols-7 lg:grid-cols-8 lg:col-span-2 lg:col-span-4 lg:col-span-3"
    ></div>
</template>
