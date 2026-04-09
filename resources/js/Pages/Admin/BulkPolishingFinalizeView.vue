<script setup>
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import { computed, watch } from "vue";
import axios from "axios";
import { useToast } from "vue-toast-notification";
import "vue-toast-notification/dist/theme-sugar.css";

import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import { mdiFormatListBulleted } from "@mdi/js";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import CardBox from "@/components/CardBox.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import FormField from "@/components/FormField.vue";
import FormFields from "@/components/FormFields.vue";
import FormControl from "@/components/FormControl.vue";

const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");

const props = defineProps({
    resourceNeo: {
        type: Object,
        default: () => ({}),
    },
});

const form = useForm(() => {
    const temp = {
        total_tubes_given: 0,
        total_weight_given: 0,
    };
    for (const key in props.resourceNeo.formInfo) {
        temp[key] = props.resourceNeo.formInfo[key]["default"] ?? "";
    }
    return temp;
});

const fetchPendingData = () => {
    if (form.grade && form.thickness && form.tubesize && form.tubelength) {
        axios
            .post(route("polishing.getPending"), {
                grade: form.grade.label,
                thickness: form.thickness.label,
                tubesize: form.tubesize.label,
                tubelength: form.tubelength.label,
            })
            .then((response) => {
                form.total_tubes_given = response.data.total_tubes_pending;
                form.total_weight_given = response.data.total_weight_pending;
            })
            .catch((error) => {
                console.error("Error fetching pending data:", error);
                form.total_tubes_given = 0;
                form.total_weight_given = 0;
            });
    } else {
        form.total_tubes_given = 0;
        form.total_weight_given = 0;
    }
};

watch(
    () => [form.grade, form.thickness, form.tubesize, form.tubelength],
    fetchPendingData
);

watch(
    () => [form.total_tubes_polished],
    () => {
        form.total_weight_polished =
            ((form.total_tubes_polished * 1) *(form.total_weight_given/form.total_tubes_given)).toFixed(3);
    }
);

const submitform = (action = 'finalize') => {
   if (form.total_tubes_polished ==0 && form.total_weight_polished ==0 && form.short_length_piece ==0 && form.short_length_weight ==0 ) {
        const $toast = useToast();
        $toast.open({
            message: "Please enter at least either polished or shortlength piece and weight.",
            type: "error",
        });
        return;
    } 
    if (form.total_tubes_polished > 0 && form.total_weight_polished ==0 ) {
        const $toast = useToast();
        $toast.open({
            message: "Please enter polished weight.",
            type: "error",
        });
        return;
    }
    if (form.total_weight_polished > 0 && form.total_tubes_polished ==0 ) {
        const $toast = useToast();
        $toast.open({
            message: "Please enter polished tubes.",
            type: "error",
        });
        return;
    }
    if (form.short_length_piece > 0 && form.short_length_weight ==0 ) {
        const $toast = useToast();
        $toast.open({
            message: "Please enter short length weight.",
            type: "error",
        });
        return;
    }
    if (form.short_length_weight > 0 && form.short_length_piece ==0 ) {
        const $toast = useToast();
        $toast.open({
            message: "Please enter short length weight.",
            type: "error",
        });
        return;
    }
    /*
    if (
        form.total_weight_polished * 1 +
            form.short_length_weight * 1 +
            form.scrap_weight * 1 >
        form.total_weight_given * 1
    ) {
        const $toast = useToast();
        $toast.open({
            message:
                "sum of Polished weight, short length weight and scrap weight cannot be more than pending weight.",
            type: "error",
        });
        return;
    }*/
    form.transform((data) => ({
        ...data,
        action: action
    })).post(route("polishing.bulkFinalize"));
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
                            label="Back"
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
                    <div
                        class="grid grid-cols-1 gap-6 lg:grid-cols-4 mt-6 border-t pt-6"
                    >
                        <FormField label="Total Tubes Pending">
                            <FormControl
                                :model-value="form.total_tubes_given"
                                readonly
                            />
                        </FormField>
                        <FormField label="Total Weight Pending (kg)">
                            <FormControl
                                :model-value="form.total_weight_given"
                                readonly
                            />
                        </FormField>
                    </div>
                </CardBox>

                <div class="mt-4 flex gap-2">
                    <BaseButton
                        class="mr-2"
                        type="submit"
                        small
                        :disabled="form.processing"
                        color="info"
                        label="Finalize"
                        @click.prevent="submitform('finalize')"
                    />
                    <BaseButton
                        class="mr-2"
                        type="button"
                        small
                        :disabled="form.processing"
                        color="success"
                        label="Finalize and Push to Packing"
                        @click.prevent="submitform('push_to_packing')"
                    />
                    <BaseButton
                        class="mr-2"
                        type="button"
                        small
                        :disabled="form.processing"
                        color="warning"
                        label="Finalize and Push to FG"
                        @click.prevent="submitform('push_to_fg')"
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
</template>
