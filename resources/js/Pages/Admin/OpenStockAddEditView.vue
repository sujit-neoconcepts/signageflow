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
import { computed, onBeforeMount } from "vue";
import { getTodayString } from "@/helpers/helpers";
import clone from "lodash-es/clone";
import axios from "axios";

const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");

const props = defineProps({
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
        form[key] = props.resourceNeo.formInfo[key]["default"] ?? "";
    }
    form["txn_date"] = form["txn_date"] !== "" ? form["txn_date"] : getTodayString();
});

const submitform = () => {
    form.post(route(props.resourceNeo.resourceName + ".store"));
};

const onChangeFunc = async (fkey) => {
    if (fkey !== "internal_name") {
        return;
    }

    form["incharge"] = "";
    form["location"] = "";
    props.resourceNeo.formInfo["incharge"]["options"] = [];
    props.resourceNeo.formInfo["location"]["options"] = [];

    if (!form["internal_name"]) {
        return;
    }

    const inchargeResp = await axios.post(route("outward.inchargeForProduct"), {
        out_product: form["internal_name"],
    });
    const inchargeOptions = inchargeResp?.data ?? [];
    props.resourceNeo.formInfo["incharge"]["options"] = inchargeOptions;
    if (inchargeOptions.length > 0) {
        form["incharge"] = inchargeOptions[0];
    }

    const locationResp = await axios.post(route("outward.locationForProduct"), {
        out_product: form["internal_name"],
        out_incharge: form["incharge"] || null,
    });
    const locationOptions = locationResp?.data ?? [];
    props.resourceNeo.formInfo["location"]["options"] = locationOptions;
    if (locationOptions.length > 0) {
        form["location"] = locationOptions[0];
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
                    <Link :href="route(props.resourceNeo.resourceName + '.index')">
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
                :outline="true"
            >
                {{ message }}
            </NotificationBar>
            <form @submit.prevent="submitform">
                <CardBox>
                    <div
                        class="grid grid-cols-1 gap-6"
                        :class="['lg:grid-cols-' + (props.resourceNeo.fColumn ?? 2)]"
                    >
                        <FormField
                            v-for="(formField, key) in props.resourceNeo.formInfo"
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
                </CardBox>

                <div class="mt-4 flex">
                    <BaseButton
                        class="mr-2"
                        type="submit"
                        small
                        :disabled="form.processing"
                        color="info"
                        label="Save"
                    />
                    <Link :href="route(props.resourceNeo.resourceName + '.index')">
                        <BaseButton type="reset" small color="info" outline label="Cancel" />
                    </Link>
                </div>
            </form>
        </SectionMain>
    </LayoutAuthenticated>
</template>
