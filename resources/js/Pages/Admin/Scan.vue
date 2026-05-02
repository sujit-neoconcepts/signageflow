<script setup>
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";

import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import { mdiFormatListBulleted, mdiBarcode, mdiQrcode } from "@mdi/js";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import CardBox from "@/components/CardBox.vue";
import "@vuepic/vue-datepicker/dist/main.css";
import NotificationBar from "@/components/NotificationBar.vue";
import FormField from "@/components/FormField.vue";
import FormFields from "@/components/FormFields.vue";
import FormFieldsMulti from "@/components/FormFieldsMulti.vue";
import BarcodeScanner from "@/components/BarcodeScanner.vue";
import CardBoxModal from "@/components/CardBoxModal.vue";
import axios from "axios";
import { useToast } from "vue-toast-notification";
import "vue-toast-notification/dist/theme-sugar.css";

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
const addMoreMulti = () => {
    let tepm = {};
    let tepm2 = {};
    for (const key in props.resourceNeo.formInfoMulti) {
        tepm[key] = props.resourceNeo.formInfoMulti[key]["default"] ?? "";
        tepm2[key] = clone(props.resourceNeo.formInfoMulti[key]);
    }
    multiDatas.value.push(tepm2);
    form["multi"].push(tepm);
    const newIndex = multiDatas.value.length - 1;
    
    // Initialize empty options for dependent fields (products are already passed from controller)
    multiDatas.value[newIndex]["out_incharge"]["options"] = [];
    multiDatas.value[newIndex]["out_loc"]["options"] = [];
    multiDatas.value[newIndex]["out_product_group"]["options"] = [];
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

            // Load product from options (products already passed from controller)
            if (props.formdata["multi"][index]["out_product_id"]) {
                const productOptions = multiDatas.value[index]["out_product"].options;
                for (const opkey in productOptions) {
                    if (productOptions[opkey].id == props.formdata["multi"][index]["out_product_id"]) {
                        form["multi"][index]["out_product"] = productOptions[opkey];
                    }
                }
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
        // Products are already loaded from controller, no AJAX needed
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

const onChangeFunc = async (index, fkey) => {
    if (fkey == "out_product") {
        // When product changes, fetch incharge (which will auto-fetch location), and product group
        await fetchInchargeForProduct(index);
        await fetchProductGroupForProduct(index);
        
        // Set units and unit price
        if (form["multi"][index].out_product?.data) {
            form["multi"][index].out_qty_unit =
                form["multi"][index].out_product.data.pur_unint_int;
            form["multi"][index].out_qty_unit_alt =
                form["multi"][index].out_product.data.pur_unint_int_alt;
            form["multi"][index].unitPrice =
                form["multi"][index].out_product.data.pur_unit_price || "";
            
            // Set qty_alt to 1 and trigger qty calculation
            form["multi"][index].out_qty_alt = 1;
            const conversionRatio = parseFloat(form["multi"][index].out_product.data.pur_qty_int_alt) / parseFloat(form["multi"][index].out_product.data.pur_qty_int);
            // Calculate qty based on qty_alt = 1
            form["multi"][index].out_qty = parseFloat((parseFloat(form["multi"][index].out_qty_alt) / conversionRatio).toFixed(3));
            
            // Fetch and set balance after all fields are populated
            await fetchBalance(index);
        }
    } else if (fkey == "out_incharge") {
        // When incharge changes after product selection, refetch locations
        if (form["multi"][index]["out_product"]) {
            await fetchLocationForProduct(index);
            // Re-fetch balance since it depends on incharge
            await fetchBalance(index);
        }
    } else if (fkey == "out_loc") {
        // When location changes, re-fetch balance since it depends on location
        if (form["multi"][index]["out_product"]) {
            await fetchBalance(index);
        }
    } else if (fkey == "out_qty") {
        if (form["multi"][index].out_product?.data) {
            const conversionRatio = parseFloat(form["multi"][index].out_product.data.pur_qty_int_alt) / parseFloat(form["multi"][index].out_product.data.pur_qty_int);
            form["multi"][index].out_qty_alt = parseFloat((parseFloat(form["multi"][index].out_qty) * conversionRatio).toFixed(3));
        }
    } else if (fkey == "out_qty_alt") {
        if (form["multi"][index].out_product?.data) {
            const conversionRatio = parseFloat(form["multi"][index].out_product.data.pur_qty_int_alt) / parseFloat(form["multi"][index].out_product.data.pur_qty_int);
            form["multi"][index].out_qty = parseFloat((parseFloat(form["multi"][index].out_qty_alt) / conversionRatio).toFixed(3));
        }
    }
};

const valdateform = () => {
    return true;
};

// Auto-select if only one option exists
const autoSelectIfOnlyOne = (index, fieldName, options) => {
    if (Array.isArray(options) && options.length === 1) {
        form["multi"][index][fieldName] = options[0];
        return true;
    }
    return false;
};

// Fetch incharge options for selected product
const fetchInchargeForProduct = async (index) => {
    if (form["multi"][index]["out_product"]) {
        await axios
            .post(route("outward.inchargeForProduct"), {
                out_product: form["multi"][index]["out_product"].label,
            })
            .then(async (response) => {
                multiDatas.value[index]["out_incharge"]["options"] = response.data;
                
                // Check if incharge field is readonly (for supervisors)
                const isInchargeReadonly = multiDatas.value[index]["out_incharge"]["readonly"];
                
                if (!isInchargeReadonly) {
                    // Auto-select the first option if any options exist (only if not readonly)
                    if (response.data.length >= 1) {
                        form["multi"][index]["out_incharge"] = response.data[0];
                    } else {
                        form["multi"][index]["out_incharge"] = "";
                    }
                }
                // If readonly, keep the existing value (supervisor's name)
                
                // After handling incharge, fetch and select first location
                await fetchLocationForProduct(index);
            });
    }
};

// Fetch location options for selected product (and optionally incharge)
const fetchLocationForProduct = async (index) => {
    if (form["multi"][index]["out_product"]) {
        await axios
            .post(route("outward.locationForProduct"), {
                out_product: form["multi"][index]["out_product"].label,
                out_incharge: form["multi"][index]["out_incharge"] || null,
            })
            .then((response) => {
                multiDatas.value[index]["out_loc"]["options"] = response.data;
                // Auto-select the first option if any options exist
                if (response.data.length >= 1) {
                    form["multi"][index]["out_loc"] = response.data[0];
                } else {
                    form["multi"][index]["out_loc"] = "";
                }
            });
    }
};

// Fetch product group for selected product
const fetchProductGroupForProduct = async (index) => {
    if (form["multi"][index]["out_product"]) {
        await axios
            .post(route("outward.productGroupForProduct"), {
                out_product: form["multi"][index]["out_product"].label,
                out_incharge: form["multi"][index]["out_incharge"] || null,
                out_loc: form["multi"][index]["out_loc"] || null,
            })
            .then((response) => {
                multiDatas.value[index]["out_product_group"]["options"] = response.data;
                // Auto-select if only one option
                if (response.data.length === 1) {
                    form["multi"][index]["out_product_group"] = response.data[0];
                } else {
                    form["multi"][index]["out_product_group"] = "";
                }
            });
    }
};

// Fetch balance (current stock) for selected product
const fetchBalance = async (index) => {
    if (
        form["multi"][index]["out_product"] &&
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
                // Find the current product in the response
                const productData = response.data.find(
                    (p) => p.label === form["multi"][index]["out_product"].label
                );
                if (productData && productData.data) {
                    form["multi"][index].balance =
                        productData.data.current_stock +
                        " " +
                        productData.data.pur_unint_int;
                }
            });
    }
};

// Keep old fetch functions for backwards compatibility if needed
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

const isModalActive = ref(false);
const currentIndex = ref(null);
const scanMode = ref("qrcode");

const openscanner = (index, mode = "qrcode") => {
    currentIndex.value = index;
    scanMode.value = mode;
    isModalActive.value = true;
};
const resetIndex = () => {
    currentIndex.value = null;
};

const onBarcodeDetected = (barcode) => {
    // Handle the scanned barcode here
    console.log("Barcode detected:", barcode);
    // You can update the form with the scanned barcode
    if (form.multi[currentIndex.value]) {
        const options =
            multiDatas.value[currentIndex.value]["out_product"]["options"];
        let found = false;
        for (const opkey in options) {
            if (options[opkey].label == barcode) {
                form["multi"][currentIndex.value]["out_product"] =
                    options[opkey];
                onChangeFunc(currentIndex.value, "out_product");
                form["multi"][currentIndex.value]["out_qty_alt"] = 1;
                onChangeFunc(currentIndex.value, "out_qty_alt");
                found = true;
                break;
            }
        }

        isModalActive.value = false;
        currentIndex.value = null;

        if (!found) {
            const $toast = useToast();
            $toast.open({
                message: "Code Detected but not in list",
                type: "error",
                position: "top-right",
            });
        }
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
                        <FormField
                            v-for="(formField, key) in multiData"
                            :label="formField.label"
                            :help="formField.tooltip"
                            :error="form.errors['multi.' + pkey + '.' + key]"
                            class="!mb-0"
                            :class="['lg:col-span-' + formField.colspan ?? 1]"
                        >
                            <div class="flex">
                                <FormFieldsMulti
                                    :form-field="formField"
                                    :multiDatasModel="form['multi']"
                                    :form="form"
                                    :fkey="key"
                                    :pkey="pkey"
                                    :onChangeFunc="onChangeFunc"
                                ></FormFieldsMulti>
                                <div class="ml-2" v-if="key == 'out_product'">
                                    <!--<BaseButton
                                        :icon="mdiBarcode"
                                        label="Scan Barcode"
                                        color="success"
                                        class="mr-2"
                                        @click="openscanner(pkey, 'barcode')"
                                    />-->
                                    <BaseButton
                                        :icon="mdiQrcode"
                                        label="Scan QR"
                                        color="info"
                                        @click="openscanner(pkey, 'qrcode')"
                                    />
                                </div>
                            </div>
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

        <CardBoxModal
            v-model="isModalActive"
            :title="scanMode === 'barcode' ? 'Scan Barcode' : 'Scan QR Code'"
            button="info"
            has-cancel
            @cancel="resetIndex"
            @confirm="resetIndex"
        >
            <div class="w-full">
                <BarcodeScanner
                    @detected="onBarcodeDetected"
                    :target="'#scanner-container'"
                    :mode="scanMode"
                    :currentIndex="currentIndex"
                />
                <div
                    id="scanner-container"
                    class="w-full h-64 bg-gray-100"
                ></div>
            </div>
        </CardBoxModal>
    </LayoutAuthenticated>
</template>

<style scoped>
#scanner-container {
    position: relative;
    overflow: hidden;
}
</style>
