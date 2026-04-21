<script setup>
import { Head, Link, useForm, usePage, router } from "@inertiajs/vue3";

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
import axios from "axios";
import CardBoxModal from "@/components/CardBoxModal.vue";
import FormControl from "@/components/FormControl.vue";
import Multiselect from "vue-multiselect";
import "../../../css/vue-multiselect.css";

const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");

import clone from "lodash-es/clone";
import { computed, onBeforeMount, watch, ref, reactive } from "vue";
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

const roundHalfUp = (num) => {
    return Number(Math.round(parseFloat(num || 0) + "e+2") + "e-2");
};

const calSumTotal = computed(() => {
    let sum = 0;
    for (const key in form["multi"]) {
        if (form["multi"][key].pur_amnt_total) {
            sum += parseFloat(form["multi"][key].pur_amnt_total);
        }
    }
    const roundoff = parseFloat(form.roundoff) || 0;
    return roundHalfUp(sum + roundoff).toFixed(2);
});
const multiDatas = ref([props.resourceNeo.formInfoMulti]);
const addMoreMulti = () => {
    multiDatas.value.push(props.resourceNeo.formInfoMulti);
    let tepm = {};
    for (const key in props.resourceNeo.formInfoMulti) {
        tepm[key] = "";
    }
    tepm["noofpsc"] = 1;
    tepm["stripbatch"] = "";
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
            for (const opkey in props.resourceNeo.formInfoMulti.pur_pr_detail
                .options) {
                if (
                    props.resourceNeo.formInfoMulti.pur_pr_detail.options[opkey]
                        .id == (props.formdata["multi"][index]["pur_pr_id"] ?? props.formdata["pur_pr_id"])
                ) {
                    tepm["pur_pr_detail"] =
                        props.resourceNeo.formInfoMulti.pur_pr_detail.options[
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
const isProductModalActive = ref(false);
const currentMultiIndex = ref(0);
const productForm = reactive({
    subgroup: '',
    groupinfo: null,
    pr_detail: '',
    pr_detail_int: null,
    pr_hsn: '',
    pr_gst_rate: '',
    pr_pur_unit: '',
    pr_int_unit: '',
    pr_pur_unit_alt: '',
    pr_int_unit_alt: '',
    pr_min_unit: 1
});

const addFunction = (index, key) => {
    if (key == 'pur_pr_detail') {
        currentMultiIndex.value = index;
        productForm.subgroup = '';
        productForm.groupinfo = null;
        productForm.pr_detail = '';
        productForm.pr_detail_int = null;
        productForm.pr_hsn = '';
        productForm.pr_gst_rate = '';
        productForm.pr_pur_unit = '';
        productForm.pr_int_unit = '';
        productForm.pr_pur_unit_alt = '';
        productForm.pr_int_unit_alt = '';
        productForm.pr_min_unit = 1;

        isProductModalActive.value = true;
    } else {
        window.open(route("product.create"), "_blank");
    }
};

const submitProduct = async () => {
    try {
        const response = await axios.post(route('product.store'), productForm, {
            headers: { 'Accept': 'application/json' }
        });
        
        // Refresh all options in all line items
        const newOptions = response.data.data;
        props.resourceNeo.formInfoMulti.pur_pr_detail.options = newOptions;
        
        // Find the newly created product in the options
        const matchingProduct = newOptions.find(v => v.label === productForm.pr_detail);
        if (matchingProduct) {
            form.multi[currentMultiIndex.value].pur_pr_detail = matchingProduct;
            onChangeFunc(currentMultiIndex.value, 'pur_pr_detail');
        }
        
        isProductModalActive.value = false;
    } catch (e) {
        alert("Error creating Product: " + (e.response?.data?.message || e.message));
    }
};

const handleInternalNameChange = (val) => {
    if (val && val.data) {
        productForm.pr_int_unit = val.data.unitName;
        productForm.pr_int_unit_alt = val.data.unitAltName;
    }
};
const refreshFunction = async (index, key) => {
    await axios.get(route("product.options")).then((response) => {
        props.resourceNeo.formInfoMulti[key]["options"] = response.data;
    });
};
const onChangeFunc = (index, fkey) => {
    if (fkey == "pur_pr_detail") {
        fetchProd(index);
    } else if (fkey == "pur_qty") {
        calintqty(index);
    } else if (fkey == "pur_qty_int") {
        calintqty_pur(index);
    } else if (fkey == "pur_rate") {
        adj_rate_int(index);
    } else if (fkey == "pur_rate_int") {
        adj_rate(index);
        /*} else if (fkey == "pur_qty_alt") {
        alt_int_qty_adj(index);*/
    } else if (fkey == "pur_unit_conv_rate") {
        calintqty(index);
        adj_rate_int(index);
    }
};

const calintqty_pur = (index) => {
    if (
        parseFloat(form["multi"][index].pur_qty_int) > 0 &&
        form["multi"][index].pur_pr_detail.data
    ) {
        form["multi"][index].pur_qty =
            parseFloat(form["multi"][index].pur_qty_int) /
            parseFloat(form["multi"][index].pur_unit_conv_rate);
    }
    caltotal(index);
};

const calintqty = (index) => {
    if (
        parseFloat(form["multi"][index].pur_qty) > 0 &&
        form["multi"][index].pur_pr_detail.data
    ) {
        form["multi"][index].pur_qty_int =
            parseFloat(form["multi"][index].pur_qty) *
            parseFloat(form["multi"][index].pur_unit_conv_rate);
    }
    caltotal(index);
};
const alt_int_qty_adj = (index) => {
    if (parseFloat(form["multi"][index].pur_qty_alt) > 0) {
        form["multi"][index].pur_qty_int_alt = parseFloat(
            form["multi"][index].pur_qty_alt
        );
    }
};
const adj_rate = (index) => {
    if (
        parseFloat(form["multi"][index].pur_rate_int) > 0 &&
        form["multi"][index].pur_pr_detail.data
    ) {
        form["multi"][index].pur_rate =
            parseFloat(form["multi"][index].pur_rate_int) *
            parseFloat(form["multi"][index].pur_unit_conv_rate);
    }
    caltotal(index);
};

const adj_rate_int = (index) => {
    if (
        parseFloat(form["multi"][index].pur_rate) > 0 &&
        form["multi"][index].pur_pr_detail.data
    ) {
        form["multi"][index].pur_rate_int =
            parseFloat(form["multi"][index].pur_rate) /
            parseFloat(form["multi"][index].pur_unit_conv_rate);
    }
    caltotal(index);
};

const caltotal = (index) => {
    if (
        parseFloat(form["multi"][index].pur_qty) > 0 &&
        parseFloat(form["multi"][index].pur_rate) > 0 &&
        form["multi"][index].pur_pr_detail.data
    ) {
        form["multi"][index].pur_amnt = roundHalfUp(
            parseFloat(form["multi"][index].pur_qty) *
            parseFloat(form["multi"][index].pur_rate)
        );
        form["multi"][index].pur_gst_amnt = roundHalfUp(
            (parseFloat(form["multi"][index].pur_amnt) *
                parseFloat(
                    form["multi"][index].pur_pr_detail.data.pr_gst_rate
                )) /
            100
        );
        form["multi"][index].pur_amnt_total = roundHalfUp(
            parseFloat(form["multi"][index].pur_gst_amnt) +
            parseFloat(form["multi"][index].pur_amnt)
        );
    }
};
const valdateform = () => {
    return true;
};

const fetchProd = (index) => {
    if (form["multi"][index].pur_pr_detail.data) {
        form["multi"][index].pur_pr_detail_int =
            form["multi"][index].pur_pr_detail.data.pr_detail_int;
        form["multi"][index].pur_pr_hsn =
            form["multi"][index].pur_pr_detail.data.pr_hsn;
        form["multi"][index].pur_unit =
            form["multi"][index].pur_pr_detail.data.pr_pur_unit;
        form["multi"][index].pur_unint_int =
            form["multi"][index].pur_pr_detail.data.pr_int_unit;

        form["multi"][index].pur_unit_alt =
            form["multi"][index].pur_pr_detail.data.pr_pur_unit_alt;
        form["multi"][index].pur_unint_int_alt =
            form["multi"][index].pur_pr_detail.data.pr_int_unit_alt;

        form["multi"][index].pur_unit_conv_rate =
            form["multi"][index].pur_pr_detail.data.pr_min_unit;
        
        form["multi"][index].available_qty =
            form["multi"][index].pur_pr_detail.data.available_qty ?? 0;
        form["multi"][index].last_rate =
            form["multi"][index].pur_pr_detail.data.last_rate ?? 0;
        form["multi"][index].unit_rate =
            form["multi"][index].pur_pr_detail.data.unit_rate ?? 0;
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
                    <div class="sticky top-10 z-20 bg-white dark:bg-slate-900 border-b dark:border-slate-700 shadow-sm -mx-6 -mt-6 p-6 pb-2 mb-4">
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
                        <h1 class="font-bold text-xl mb-2 mt-4">
                            {{ props.resourceNeo.Multilabel }}
                            <div class="float-right bg-green-100 dark:bg-green-900 px-3 py-1 rounded text-green-800 dark:text-green-100 text-lg">
                                Sum Total: {{ calSumTotal }}
                            </div>
                        </h1>
                        <!-- Clear the float so the layout doesn't break underneath it -->
                        <div class="clear-both"></div>
                    </div>
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
                            :addAndRefresh="formField.addAndRefresh"
                            :label="formField.label"
                            :help="formField.tooltip"
                            :error="form.errors['multi.' + pkey + '.' + key]"
                            class="!mb-0"
                            :class="['lg:col-span-' + (formField.colspan || 1), formField.newlineClass || '']"
                            :addFunction="addFunction"
                            :fkey="key"
                            :pkey="pkey"
                            :refreshFunction="refreshFunction"
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
    <CardBoxModal v-model="isProductModalActive" title="Add Product" hasCancel @confirm="submitProduct">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <FormField label="Sub Group">
                <select v-model="productForm.subgroup" class="rounded w-full border-gray-300 dark:border-gray-700 dark:bg-slate-800">
                    <option value="">Select Sub Group</option>
                    <option v-for="sg in props.resourceNeo.productSubgroups" :key="sg" :value="sg">{{ sg }}</option>
                </select>
            </FormField>
            <FormField label="Product Group">
                <Multiselect
                    v-model="productForm.groupinfo"
                    :options="props.resourceNeo.productGroups.filter(pg => pg.sgroup === productForm.subgroup)"
                    track-by="id"
                    label="label"
                    placeholder="Select Product Group"
                />
            </FormField>
            <FormField label="Name As Per Invoice" class="md:col-span-2">
                <FormControl v-model="productForm.pr_detail" placeholder="Enter Name As Per Invoice" />
            </FormField>
            <FormField label="Internal Name">
                <Multiselect
                    v-model="productForm.pr_detail_int"
                    :options="props.resourceNeo.internalNames"
                    track-by="id"
                    label="label"
                    placeholder="Select Internal Name"
                    @select="handleInternalNameChange"
                />
            </FormField>
            <FormField label="HSN Code">
                <FormControl v-model="productForm.pr_hsn" type="number" placeholder="Enter HSN Code" />
            </FormField>
            <FormField label="GST Rate">
                <FormControl v-model="productForm.pr_gst_rate" type="number" placeholder="Enter GST Rate" />
            </FormField>
            <FormField label="Billed Unit">
                <select v-model="productForm.pr_pur_unit" class="rounded w-full border-gray-300 dark:border-gray-700 dark:bg-slate-800">
                    <option value="">Select Billed Unit</option>
                    <option v-for="unit in props.resourceNeo.units" :key="unit" :value="unit">{{ unit }}</option>
                </select>
            </FormField>
            <FormField label="Internal Unit">
                <select v-model="productForm.pr_int_unit" class="rounded w-full border-gray-300 dark:border-gray-700 dark:bg-slate-800">
                    <option value="">Select Internal Unit</option>
                    <option v-for="unit in props.resourceNeo.units" :key="unit" :value="unit">{{ unit }}</option>
                </select>
            </FormField>
            <FormField label="Conversion Value">
                <FormControl v-model="productForm.pr_min_unit" type="number" placeholder="Enter Conversion Value" />
            </FormField>
        </div>
    </CardBoxModal>
    <div
        class="grid lg:grid-cols-6 lg:grid-cols-7 lg:grid-cols-8 lg:col-span-2 lg:col-span-4 lg:col-span-3"
    ></div>
</template>
