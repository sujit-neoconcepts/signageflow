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
import BaseButtons from "@/components/BaseButtons.vue";
import axios from "axios";

import Multiselect from "vue-multiselect";
import "../../../css/vue-multiselect.css";
import clone from "lodash-es/clone";
import { computed, onBeforeMount, ref, reactive } from "vue";
import { getTodayString } from "@/helpers/helpers";

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

const isGroupModalActive = ref(false);
const groupForm = reactive({ name: '' });

const addFunction = (pkey, fkey) => {
    if (fkey === 'consumable_internal_name_group_id') {
        groupForm.name = '';
        isGroupModalActive.value = true;
    }
};

const refreshFunction = async (pkey, fkey) => {
    if (fkey === 'consumable_internal_name_group_id') {
        try {
            const response = await axios.get(route("consumableInternalNameGroup.options"));
            props.resourceNeo.formInfo[fkey].options = response.data;
        } catch (e) {
            alert("Error refreshing groups: " + (e.response?.data?.message || e.message));
        }
    }
};

const submitGroup = async () => {
    try {
        const response = await axios.post(route('consumableInternalNameGroup.store'), groupForm, {
            headers: { 'Accept': 'application/json' }
        });
        props.resourceNeo.formInfo.consumable_internal_name_group_id.options = response.data.data;
        const matchingGroup = response.data.data.find(v => v.label.toLowerCase() === groupForm.name.trim().toLowerCase());
        if (matchingGroup) {
            form.consumable_internal_name_group_id = matchingGroup;
        }
        isGroupModalActive.value = false;
    } catch (e) {
        alert("Error creating Internal name Group: " + (e.response?.data?.message || e.message));
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
                            :addAndRefresh="formField.addAndRefresh"
                            :addFunction="addFunction"
                            :refreshFunction="refreshFunction"
                            :fkey="key"
                            class="!mb-0"
                        >
                            <FormFields
                                :form-field="formField"
                                :form="form"
                                :fkey="key"
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

            <CardBoxModal v-model="isGroupModalActive" title="Add Internal name Group" hasCancel @confirm="submitGroup">
                <FormField label="Group Name">
                    <FormControl v-model="groupForm.name" placeholder="Enter Group Name" />
                </FormField>
            </CardBoxModal>
        </SectionMain>
    </LayoutAuthenticated>
</template>
