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

import axios from "axios";

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


const multiDatas = ref([]);

// Computed property to reorder fields: move out_product after out_product_group
const reorderedMultiDatas = computed(() => {
    return multiDatas.value.map(multiData => {
        const fieldOrder = ['out_incharge', 'out_loc', 'out_product_group', 'out_product', 'out_qty', 'out_qty_unit', 'out_qty_alt', 'out_qty_unit_alt', 'unitPrice', 'balance', 'out_remark'];
        const reordered = {};
        
        // Add fields in the specified order
        fieldOrder.forEach(key => {
            if (multiData[key]) {
                reordered[key] = multiData[key];
            }
        });
        
        // Add any remaining fields that weren't in the order list
        Object.keys(multiData).forEach(key => {
            if (!reordered[key]) {
                reordered[key] = multiData[key];
            }
        });
        
        return reordered;
    });
});

const addMoreMulti = () => {
    let tepm = {};
    let tepm2 = {};
    for (const key in props.resourceNeo.formInfoMulti) {
        tepm[key] = props.resourceNeo.formInfoMulti[key]["default"] ?? "";
        tepm2[key] = clone(props.resourceNeo.formInfoMulti[key]);
    }
    multiDatas.value.push(tepm2);
    form["multi"].push(tepm);
    multiDatas.value[multiDatas.value.length - 1]["out_product_group"][
        "options"
    ] = [];
    multiDatas.value[multiDatas.value.length - 1]["out_product"]["options"] =
        [];
};
const delMulti = (el) => {
    multiDatas.value.splice(el * 1, 1);
    form["multi"].splice(el * 1, 1);
};

onBeforeMount(async () => {
    let tepm2 = {};
    for (const key in props.resourceNeo.formInfoMulti) {
        tepm2[key] = clone(props.resourceNeo.formInfoMulti[key]);
    }
    multiDatas.value.push(tepm2);

    for (const key in props.resourceNeo.formInfo) {
        form[key] =
            props.formdata[key] ??
            props.resourceNeo.formInfo[key]["default"] ??
            "";
    }
    form["out_date"] = form["out_date"] != "" ? form["out_date"] : getTodayString();

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
            if (
                props.formdata["multi"][index]["out_product_group"] &&
                props.formdata["multi"][index]["out_product_group_id"]
            ) {
                tepm["out_product_group"] = {
                    id: props.formdata["multi"][index]["out_product_group_id"],
                    label: props.formdata["multi"][index]["out_product_group"],
                };
            }

            form["multi"].push(tepm);

            if (props.formdata["multi"][index]["out_product_id"]) {
                await fetchProd(index).then(() => {
                    for (const opkey in multiDatas.value[index]["out_product"]
                        .options) {
                        if (
                            multiDatas.value[index]["out_product"].options[
                                opkey
                            ].id ==
                            props.formdata["multi"][index]["out_product_id"]
                        ) {
                            form["multi"][index]["out_product"] =
                                multiDatas.value[index]["out_product"].options[
                                    opkey
                                ];
                            form["multi"][index].balance =
                                form["multi"][index].out_product.data
                                    .current_stock +
                                " " +
                                form["multi"][index].out_product.data
                                    .pur_unint_int;
                        }
                    }
                });
            }
        }
    } else {
        let tepm = {};
        for (const key in props.resourceNeo.formInfoMulti) {
            tepm[key] =
                props.formdata[key] ??
                props.resourceNeo.formInfoMulti[key]["default"] ??
                "";
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
    if (fkey == "out_incharge") {
        fetchProdLoc(index);
    }
    if (fkey == "out_incharge" || fkey == "out_loc") {
        fetchProdGroup(index);
    }
    if (
        fkey == "out_incharge" ||
        fkey == "out_loc" ||
        fkey == "out_product_group"
    ) {
        fetchProd(index);
    }

    if (fkey == "out_product") {

        form["multi"][index].out_qty_unit =
            form["multi"][index].out_product.data.pur_unint_int;
        form["multi"][index].unitPrice =
            form["multi"][index].out_product.data.unitPrice;
        form["multi"][index].out_qty_unit_alt =
            form["multi"][index].out_product.data.pur_unint_int_alt;

        form["multi"][index].balance =
            form["multi"][index].out_product.data.current_stock +
            " " +
            form["multi"][index].out_product.data.pur_unint_int;

        form["multi"][index].out_qty_alt = 1;
        form["multi"][index].out_qty =
            parseFloat(form["multi"][index].out_qty_alt) /
            (parseFloat(form["multi"][index].out_product.data.pur_qty_int_alt) /
                parseFloat(form["multi"][index].out_product.data.pur_qty_int));
    } else if (fkey == "out_qty") {
        form["multi"][index].out_qty_alt =
            parseFloat(form["multi"][index].out_qty) *
            (parseFloat(form["multi"][index].out_product.data.pur_qty_int_alt) /
                parseFloat(form["multi"][index].out_product.data.pur_qty_int));
    } else if (fkey == "out_qty_alt") {
        form["multi"][index].out_qty =
            parseFloat(form["multi"][index].out_qty_alt) /
            (parseFloat(form["multi"][index].out_product.data.pur_qty_int_alt) /
                parseFloat(form["multi"][index].out_product.data.pur_qty_int));
    }
};

const valdateform = () => {
    return true;
};

const fetchProdLoc = async (index) => {
    if (form["multi"][index]["out_incharge"]) {
        await axios
            .post(route("outward.productsloc"), {
                out_incharge: form["multi"][index]["out_incharge"],
            })
            .then((response) => {
                form["multi"][index]["out_loc"] = "";
                multiDatas.value[index]["out_loc"]["options"] = response.data;
            });
    }
};

const fetchProdGroup = async (index) => {
    if (
        form["multi"][index]["out_incharge"] &&
        form["multi"][index]["out_loc"]
    ) {
        await axios
            .post(route("outward.productsgroup"), {
                out_incharge: form["multi"][index]["out_incharge"],
                out_loc: form["multi"][index]["out_loc"],
            })
            .then((response) => {
                form["multi"][index]["out_product_group"] = "";
                multiDatas.value[index]["out_product_group"]["options"] =
                    response.data;
            });
    }
};

const fetchProd = async (index) => {
    if (
        form["multi"][index]["out_incharge"] &&
        form["multi"][index]["out_loc"] &&
        form["multi"][index]["out_product_group"]
    ) {
        await axios
            .post(route("outward.products"), {
                out_incharge: form["multi"][index]["out_incharge"],
                out_loc: form["multi"][index]["out_loc"],
                out_product_group: form["multi"][index]["out_product_group"].id,
            })
            .then((response) => {
                form["multi"][index]["out_product"] = "";
                multiDatas.value[index]["out_product"]["options"] =
                    response.data;
                // props.resourceNeo.formInfo["out_product"]["options"] = response.data;
            });
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
                        v-for="(multiData, pkey) in reorderedMultiDatas"
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
                            :class="['lg:col-span-' + formField.colspan ?? 1]"
                        >
                            <FormFieldsMulti
                                :form-field="formField"
                                :multiDatasModel="form['multi']"
                                :form="form"
                                :fkey="key"
                                :pkey="pkey"
                                :onChangeFunc="onChangeFunc"
                                :class="[
                                    {
                                        'border-2 border-green-500':
                                            key === 'balance' &&
                                            parseFloat(form.multi[pkey][key]) >
                                                0,
                                        'border-2 border-red-500':
                                            key === 'balance' &&
                                            parseFloat(form.multi[pkey][key]) <=
                                                0,
                                    },
                                ]"
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
