<script setup>
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";

import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import { mdiFormatListBulleted } from "@mdi/js";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import CardBox from "@/components/CardBox.vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import NotificationBar from "@/components/NotificationBar.vue";
import FormField from "@/components/FormField.vue";
import FormFields from "@/components/FormFields.vue";
import FormControl from "@/components/FormControl.vue";
import CardBoxModal from "@/components/CardBoxModal.vue";
import axios from "axios";

import Multiselect from "vue-multiselect";
import "../../../css/vue-multiselect.css";
import clone from "lodash-es/clone";
import { getTodayString } from "@/helpers/helpers";
import { computed, onBeforeMount, ref, reactive } from "vue";

const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");

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
    return clone(props.resourceNeo.formInfo);
});

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

const handleFieldChange = (fieldKey) => {
    const fieldConfig = props.resourceNeo.formInfo[fieldKey];
    
    // Check if this field has autoFill configuration
    if (fieldConfig && fieldConfig.autoFill) {
        const selectedValue = form[fieldKey];
        
        // Process each autoFill mapping
        Object.keys(fieldConfig.autoFill).forEach((targetField) => {
            const sourcePath = fieldConfig.autoFill[targetField];
            
            // Extract value from the selected object using dot notation
            // e.g., 'data.unitName' from selectedValue
            const pathParts = sourcePath.split('.');
            let value = selectedValue;
            
            for (const part of pathParts) {
                if (value && typeof value === 'object' && part in value) {
                    value = value[part];
                } else {
                    value = null;
                    break;
                }
            }
            
            // Set the target field value
            if (value !== null && value !== undefined) {
                form[targetField] = value;
            }
        });
    }
};

const isPgroupModalActive = ref(false);
const pgroupForm = reactive({ name: '', sgroup: '' });

const isInternalNameModalActive = ref(false);
const internalNameForm = reactive({ name: '', unitPrice: 0, unitName: '', unitAltName: '', openStockUnit: 0, openStockMarginPercent: 0 });

const addFunction = (pkey, fkey) => {
    if (fkey === 'groupinfo') {
        pgroupForm.name = '';
        pgroupForm.sgroup = form.subgroup || '';
        isPgroupModalActive.value = true;
    } else if (fkey === 'pr_detail_int') {
        internalNameForm.name = '';
        internalNameForm.unitPrice = 0;
        internalNameForm.unitName = '';
        internalNameForm.unitAltName = '';
        internalNameForm.openStockUnit = 0;
        internalNameForm.openStockMarginPercent = 0;
        isInternalNameModalActive.value = true;
    }
};

const submitPgroup = async () => {
    try {
        const response = await axios.post(route('pgroup.store'), pgroupForm, {headers: {'Accept': 'application/json'}});
        props.resourceNeo.formInfo.groupinfo.options = response.data.data;
        const matchingId = response.data.data.find(v => v.label.startsWith(pgroupForm.name));
        if (matchingId) form.groupinfo = matchingId;
        isPgroupModalActive.value = false;
    } catch (e) {
        alert("Error creating Product Group: " + (e.response?.data?.message || e.message));
    }
};

const submitInternalName = async () => {
    try {
        const response = await axios.post(route('consumableInternalName.store'), internalNameForm, {headers: {'Accept': 'application/json'}});
        const newItem = response.data.data;
        props.resourceNeo.formInfo.pr_detail_int.options.push(newItem);
        form.pr_detail_int = newItem;
        handleFieldChange('pr_detail_int');
        isInternalNameModalActive.value = false;
    } catch (e) {
        alert("Error creating Internal Name: " + (e.response?.data?.message || e.message));
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
                            :addAndRefresh="!!formField.canAdd"
                            :fkey="key"
                            :addFunction="addFunction"
                            class="!mb-0"
                        >
                            <FormFields
                                :form-field="formField"
                                :form="form"
                                :fkey="key"
                                :on-change-func="handleFieldChange"
                            />
                        </FormField>
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

            <CardBoxModal v-model="isPgroupModalActive" title="Add Product Group" hasCancel @confirm="submitPgroup">
                <FormField label="Group Name">
                    <FormControl v-model="pgroupForm.name" placeholder="Enter Group Name" />
                </FormField>
                <FormField label="Sub Group">
                    <!-- Dropdown using normal select or FormControl for simplicity, keeping generic subgroups -->
                    <select v-model="pgroupForm.sgroup" class="rounded w-full border-gray-300 dark:border-gray-700 dark:bg-slate-800">
                        <option value="Capex">Capex</option>
                        <option value="Consumable Item">Consumable Item</option>
                        <option value="Indirect Expense/Purchase">Indirect Expense/Purchase</option>
                        <option value="Opex">Opex</option>
                        <option value="Plant & Machinery Item">Plant & Machinery Item</option>
                        <option value="Services Purchase">Services Purchase</option>
                        <option value="Services Sale">Services Sale</option>
                        <option value="Stock Item">Stock Item</option>
                        <option value="Tools">Tools</option>
                    </select>
                </FormField>
            </CardBoxModal>

            <CardBoxModal v-model="isInternalNameModalActive" title="Add Internal Name" hasCancel @confirm="submitInternalName">
                <FormField label="Name">
                    <FormControl v-model="internalNameForm.name" placeholder="Enter Internal Name" />
                </FormField>
                <FormField label="Unit Price">
                    <FormControl v-model="internalNameForm.unitPrice" type="number" />
                </FormField>
                <FormField label="Primary Unit">
                    <select v-model="internalNameForm.unitName" class="rounded w-full border-gray-300 dark:border-gray-700 dark:bg-slate-800">
                        <option value="">Select Primary Unit</option>
                        <option v-for="unit in props.resourceNeo.formInfo.pr_pur_unit.options" :key="unit" :value="unit">
                            {{ unit }}
                        </option>
                    </select>
                </FormField>
                <FormField label="Alt Unit">
                    <select v-model="internalNameForm.unitAltName" class="rounded w-full border-gray-300 dark:border-gray-700 dark:bg-slate-800">
                        <option value="">Select Alt Unit</option>
                        <option v-for="unit in props.resourceNeo.formInfo.pr_pur_unit.options" :key="unit" :value="unit">
                            {{ unit }}
                        </option>
                    </select>
                </FormField>
                <FormField label="Open Stock Unit (1 for Alt, 0 for Primary)">
                    <FormControl v-model="internalNameForm.openStockUnit" type="number" />
                </FormField>
                <FormField label="Open Stock Margin %">
                    <FormControl v-model="internalNameForm.openStockMarginPercent" type="number" />
                </FormField>
            </CardBoxModal>
        </SectionMain>
    </LayoutAuthenticated>
</template>
