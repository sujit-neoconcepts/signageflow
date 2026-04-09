<script setup>
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import { mdiFormatListBulleted, mdiAlert } from "@mdi/js";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import CardBox from "@/components/CardBox.vue";
import CardBoxModal from "@/components/CardBoxModal.vue";
import FormField from "@/components/FormField.vue";
import FormControl from "@/components/FormControl.vue";
import { ref, computed } from "vue";
import Table from "@/components/DataTable/Table.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import { useToast } from "vue-toast-notification";
import "vue-toast-notification/dist/theme-sugar.css";

const props = defineProps({
    resourceData: {
        type: Object,
        default: () => ({}),
    },
    resourceNeo: {
        type: Object,
        default: () => ({}),
    },
});

const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");

const isModalActive = ref(false);
const selectedProduct = ref(null);

const form = useForm({
    pr_detail_int: "",
    threshold_qty: 0,
});

const openThresholdModal = (product) => {
    selectedProduct.value = product;
    form.pr_detail_int = product.pr_detail_int;
    form.threshold_qty = product.threshold_qty;
    isModalActive.value = true;
};

const updateThreshold = () => {
    form.post(route("stocks.threshold"), {
        onSuccess: () => {
            isModalActive.value = false;
            if (message.value) {
                useToast().info(message.value, { duration: 7000 });
            }
        },
    });
};
</script>

<template>
    <LayoutAuthenticated>
        <Head :title="resourceNeo.resourceTitle" />
        <SectionMain>
            <SectionTitleLineWithButton
                :icon="resourceNeo.iconPath"
                :title="resourceNeo.resourceTitle"
                main
            >
                <Link :href="route('stocks.index')">
                    <BaseButton
                        class="m-2"
                        :icon="mdiFormatListBulleted"
                        color="success"
                        label="All Stocks"
                        rounded-full
                        small
                    />
                </Link>
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

            <CardBox has-table>
                <Table
                    :resource="resourceData"
                    :resourceNeo="resourceNeo"
                    :stickyHeader="!0"
                >
                    <template #cell(actions)="{ item: product }">
                        <BaseButton
                            color="info"
                            small
                            @click="openThresholdModal(product)"
                            label="Update Threshold"
                        />
                    </template>
                    <template #cell(status)="{ item: product }">
                        <span
                            :class="{
                                'text-red-500':
                                    product.status === 'Below Threshold',
                                'text-green-500': product.status === 'Normal',
                            }"
                        >
                            {{ product.status }}
                        </span>
                    </template>
                </Table>
            </CardBox>
        </SectionMain>

        <!-- Threshold Edit Modal -->
        <CardBoxModal
            v-model="isModalActive"
            title="Set Stock Threshold"
            button="info"
            has-cancel
            @confirm="updateThreshold"
        >
            <div v-if="selectedProduct">
                <p class="mb-4">
                    Set threshold level for: {{ selectedProduct.pr_detail_int }}
                </p>
                <FormField
                    label="Threshold Quantity"
                    help="Minimum stock level before alert"
                >
                    <FormControl
                        v-model="form.threshold_qty"
                        type="number"
                        min="0"
                        step="0.01"
                    />
                </FormField>
            </div>
        </CardBoxModal>
    </LayoutAuthenticated>
</template>

<style scoped>
.text-red-500 {
    color: rgb(239, 68, 68);
}

.text-green-500 {
    color: rgb(34, 197, 94);
}
</style>
