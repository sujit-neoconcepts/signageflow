<script setup>
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import { computed, watch } from "vue";
import axios from "axios";
import { useToast } from "vue-toast-notification";
import "vue-toast-notification/dist/theme-sugar.css";

import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import { mdiFormatListBulleted, mdiAlert } from "@mdi/js";
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
        total_tubes_packed: "",
        total_weight_packed: "",
    };
    for (const key in props.resourceNeo.formInfo) {
        temp[key] = props.resourceNeo.formInfo[key]["default"] ?? "";
    }
    return temp;
});

const fetchPendingData = () => {
    if (form.grade && form.thickness && form.tubesize && form.tubelength) {
        axios
            .post(route("packing.getPending"), {
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

const submitform = () => {
    if (form.total_tubes_packed > form.total_tubes_given) {
        const $toast = useToast();
        $toast.open({
            message: "Packed tubes cannot be more than pending tubes.",
            type: "error",
        });
        return;
    }

    const total_output_weight = parseFloat(form.total_weight_packed) || 0;

    if (total_output_weight > form.total_weight_given) {
        const $toast = useToast();
        $toast.open({
            message: "Sum of packed weight cannot be more than pending weight.",
            type: "error",
        });
        return;
    }
    form.post(route("packing.bulkFinalize"));
};

const onChangeFunc = (fkey) => {
    if (fkey == "total_tubes_packed") {
        cal_tube_weight();
    }
};

const cal_tube_weight = () => {
    if (form.total_tubes_packed && form.total_tubes_given) {
        const weight =
            (form.total_tubes_packed / form.total_tubes_given) *
            form.total_weight_given;
        form.total_weight_packed = weight.toFixed(2);
    } else {
        form.total_weight_packed = "";
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
                                :onChangeFunc="onChangeFunc"
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

                <div class="mt-4 flex">
                    <BaseButton
                        class="mr-2"
                        type="submit"
                        small
                        :disabled="form.processing"
                        color="info"
                        label="Finalize"
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
