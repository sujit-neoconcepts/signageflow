<script setup>
import { Head, Link, usePage } from "@inertiajs/vue3";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import { mdiPackageVariantPlus, mdiAlert } from "@mdi/js";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import CardBoxModal from "@/components/CardBoxModal.vue";
import CardBox from "@/components/CardBox.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import Table from "@/components/DataTable/Table.vue";
import { useToast } from "vue-toast-notification";
import "vue-toast-notification/dist/theme-sugar.css";
import { computed, onMounted, ref } from "vue";
import axios from "axios";
import { can } from "@/utils/permissions";

const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");

const props = defineProps({
    resourceData: {
        type: Object,
        default: () => ({}),
    },
    can: {
        type: Object,
        default: () => ({}),
    },
    resourceNeo: {
        type: Object,
        default: () => ({}),
    },
});

const isModalTranActive = ref(false);
const transaDetailsLive = ref([]);
const detailTitle = ref("");

const showDetail = async (row) => {
    isModalTranActive.value = true;
    transaDetailsLive.value = [];
    detailTitle.value = `${row.internal_name} | ${row.location} | ${row.incharge}`;

    await axios
        .post(route("openStock.detail"), { id: row.id })
        .then((response) => {
            transaDetailsLive.value = response.data ?? [];
        });
};

onMounted(() => {
    if (message.value) {
        if (msg_type.value == "info") {
            useToast().info(message.value, { duration: 7000 });
        } else if (msg_type.value == "success") {
            useToast().success(message.value, { duration: 7000 });
        } else if (msg_type.value == "danger") {
            useToast().error(message.value, { duration: 7000 });
        } else {
            useToast().warning(message.value, { duration: 7000 });
        }
    }
});
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
                <div class="flex">
                    <template v-if="props.resourceNeo.extraMainLinks">
                        <Link
                            v-for="link in props.resourceNeo.extraMainLinks"
                            :key="link.link"
                            :href="route(link.link)"
                            class="text-gray-600"
                        >
                            <BaseButton
                                class="m-2"
                                color="success"
                                rounded-full
                                :icon="link.icon || mdiPackageVariantPlus"
                                small
                                :label="link.label"
                                :title="link.label"
                            />
                        </Link>
                    </template>
                    <Link
                        v-if="
                            resourceNeo.actions.includes('c') &&
                            can(props.resourceNeo.resourceName + '_create')
                        "
                        :href="route(resourceNeo.resourceName + '.create')"
                        class="text-gray-600"
                    >
                        <BaseButton
                            class="m-2"
                            color="success"
                            rounded-full
                            :icon="mdiPackageVariantPlus"
                            small
                            label="Add New"
                            title="Add New"
                        />
                    </Link>
                </div>
            </SectionTitleLineWithButton>

            <NotificationBar
                v-if="message"
                class="mb-4"
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
                    <template #cell(internal_name)="{ item: row }">
                        <span
                            class="cursor-pointer hover:underline"
                            @click="showDetail(row)"
                        >
                            {{ row.internal_name }}
                        </span>
                    </template>
                </Table>
            </CardBox>
        </SectionMain>

        <CardBoxModal
            v-model="isModalTranActive"
            buttonLabel="Ok"
            :title="'Transactions: ' + detailTitle"
            full-width
            has-cancel
        >
            <div class="lg:max-h-[calc(100vh-280px)] overflow-y-auto relative">
                <table>
                    <thead>
                        <tr>
                            <td>Date</td>
                            <td>Type</td>
                            <td>Qty</td>
                            <td>Unit</td>
                            <td>Base Rate</td>
                            <td>Margin %</td>
                            <td>Effective Rate</td>
                            <td>Amount</td>
                            <td>Source</td>
                            <td>Remark</td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(data, index) in transaDetailsLive" :key="index">
                            <td>{{ data.txn_date }}</td>
                            <td>{{ data.transaction_type }}</td>
                            <td>{{ data.qty }}</td>
                            <td>{{ data.open_stock_unit }}</td>
                            <td>{{ data.base_unit_price }}</td>
                            <td>{{ data.margin_percent }}</td>
                            <td>{{ data.effective_unit_price }}</td>
                            <td>{{ data.line_amount }}</td>
                            <td>
                                {{
                                    data.source_type
                                        ? data.source_type + (data.source_id ? " #" + data.source_id : "")
                                        : ""
                                }}
                            </td>
                            <td>{{ data.remark }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CardBoxModal>
    </LayoutAuthenticated>
</template>
