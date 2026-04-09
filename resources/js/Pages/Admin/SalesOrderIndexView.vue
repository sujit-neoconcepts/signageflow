<script setup>
import { Head, Link, usePage, router } from "@inertiajs/vue3";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import {
    mdiPackageVariantPlus,
    mdiAlert,
    mdiEye,
    mdiPrinter,
    mdiFileEdit,
    mdiTrashCan,
} from "@mdi/js";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import CardBoxModal from "@/components/CardBoxModal.vue";
import CardBox from "@/components/CardBox.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import Table from "@/components/DataTable/Table.vue";
import { useToast } from "vue-toast-notification";
import "vue-toast-notification/dist/theme-sugar.css";
import { computed, onMounted, ref, onUnmounted, watch } from "vue";
import ActionMenu from "@/components/ActionMenu.vue";
import { can } from '@/utils/permissions';
import axios from "axios";

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

const delselect = ref(0);
const isModalDangerActive = ref(false);
const isModalDetailActive = ref(false);
const detailData = ref(null);

const deleteRecord = () => {
    if (delselect.value != 0) {
        router.delete(
            route(props.resourceNeo.resourceName + ".destroy", delselect.value),
            {
                preserveScroll: true,
                resetOnSuccess: false,
                onFinish: () => {
                    delselect.value = 0;
                },
            }
        );
    }
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
    window.addEventListener("click", handleWindowClick);
});

onUnmounted(() => {
    window.removeEventListener("click", handleWindowClick);
});

const openMenuIds = ref(new Set());

const toggleMenu = (id) => {
    if (openMenuIds.value.has(id)) {
        openMenuIds.value.delete(id);
    } else {
        openMenuIds.value.clear(); // Close other menus
        openMenuIds.value.add(id);
    }
};

const closeAllMenus = () => {
    openMenuIds.value.clear();
};

const handleWindowClick = (event) => {
    // Check if click target is inside any menu or menu button
    const isMenuClick = event.target.closest(".menu-container");
    if (!isMenuClick) {
        closeAllMenus();
    }
};

const actionClasses = {
    menuItem: "block hover:bg-gray-100 dark:hover:bg-gray-700",
    button: "w-full text-left",
    dropdown:
        "absolute right-0 z-50 mt-2 bg-white border rounded-md shadow-lg dark:bg-gray-800 dark:border-gray-700",
};

const checkConditions = (item, conditions) => {
    if (!conditions) return true;
    return conditions.every((rule) => {
        if (rule.cond === "==") return item[rule.key] == rule.compvl;
        if (rule.cond === "!=") return item[rule.key] != rule.compvl;
        if (rule.cond === ">") return item[rule.key] > rule.compvl;
        if (rule.cond === "<") return item[rule.key] < rule.compvl;
        if (rule.cond === "*") return true;
        return false;
    });
};

const showDetail = async (row) => {
    isModalDetailActive.value = true;
    detailData.value = null;
    const response = await axios.post(route("salesOrder.detail"), { id: row.id });
    detailData.value = response.data;
};

const detailItemsTotal = computed(() => Number(detailData.value?.items_taxable_total || 0).toFixed(2));
const detailItemsGstTotal = computed(() => Number(detailData.value?.items_gst_total || 0).toFixed(2));
const detailTransport = computed(() => Number(detailData.value?.transport_charge || 0).toFixed(2));
const detailTransportGst = computed(() => Number(detailData.value?.transport_gst || 0).toFixed(2));
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
                                :icon="link.icon"
                                small
                                :label="link.label"
                                :title="link.label"
                        /></Link>
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
                    /></Link>
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
                    <template #cell(order_no)="{ item: dItem }">
                        <span class="cursor-pointer hover:underline" @click="showDetail(dItem)">
                            {{ dItem.order_no }}
                        </span>
                    </template>
                    <template #cell(actions)="{ item: dItem }">
                        <div class="relative menu-container">
                            <ActionMenu
                                :item="dItem"
                                :extra-links="props.resourceNeo.extraLinks"
                                :action-expand="props.resourceNeo.actionExpand"
                                :is-open="openMenuIds.has(dItem.id)"
                                :menu-classes="actionClasses"
                                @toggle="toggleMenu(dItem.id)"
                            >
                                <button
                                    :class="[
                                        actionClasses.button,
                                        {
                                            'p-2': props.resourceNeo.actionExpand,
                                        },
                                    ]"
                                    @click="showDetail(dItem)"
                                >
                                    <BaseButton
                                        :class="[actionClasses.button, 'w-auto']"
                                        color="success"
                                        :icon="mdiEye"
                                        small
                                        :label="props.resourceNeo.actionExpand ? 'View' : ''"
                                        title="View"
                                    />
                                </button>
                                <a
                                    :href="route('salesOrder.print', dItem.id)"
                                    target="_blank"
                                    rel="noopener"
                                    :class="[
                                        actionClasses.menuItem,
                                        {
                                            'p-2': props.resourceNeo.actionExpand,
                                        },
                                    ]"
                                >
                                    <BaseButton
                                        :class="[actionClasses.button, 'w-auto']"
                                        color="warning"
                                        :icon="mdiPrinter"
                                        small
                                        :label="props.resourceNeo.actionExpand ? 'Print' : ''"
                                        title="Print"
                                    />
                                </a>
                                <Link
                                    v-if="
                                        checkConditions(
                                            dItem,
                                            props.resourceNeo.editRule
                                        ) &&
                                        props.resourceNeo.actions.includes(
                                            'u'
                                        ) &&
                                        (can(props.resourceNeo.resourceName +
                                                    '_edit'
                                            ))
                                    "
                                    :href="
                                        route(
                                            props.resourceNeo.resourceName +
                                                '.edit',
                                            dItem.id
                                        )
                                    "
                                    :class="[
                                        actionClasses.menuItem,
                                        {
                                            'p-2': props.resourceNeo
                                                .actionExpand,
                                        },
                                    ]"
                                >
                                    <BaseButton
                                        :class="[
                                            actionClasses.button,
                                            'w-auto',
                                        ]"
                                        color="info"
                                        :icon="mdiFileEdit"
                                        small
                                        :label="
                                            props.resourceNeo.actionExpand
                                                ? 'Edit'
                                                : ''
                                        "
                                        title="Edit"
                                    />
                                </Link>
                                <button
                                    v-if="
                                        checkConditions(
                                            dItem,
                                            props.resourceNeo.deleteRule
                                        ) &&
                                        props.resourceNeo.actions.includes(
                                            'd'
                                        ) &&
                                        (can(props.resourceNeo.resourceName +
                                                    '_delete'
                                            ))
                                    "
                                    :class="[
                                        actionClasses.button,
                                        {
                                            'p-2': props.resourceNeo
                                                .actionExpand,
                                        },
                                    ]"
                                    @click="
                                        delselect = dItem.id;
                                        isModalDangerActive = true;
                                    "
                                >
                                    <BaseButton
                                        :class="[
                                            actionClasses.button,
                                            'w-auto',
                                        ]"
                                        color="danger"
                                        :icon="mdiTrashCan"
                                        small
                                        :label="
                                            props.resourceNeo.actionExpand
                                                ? 'Delete'
                                                : ''
                                        "
                                        title="Delete"
                                    />
                                </button>
                            </ActionMenu>
                        </div>
                    </template>
                </Table>
            </CardBox>
        </SectionMain>
        <CardBoxModal
            v-model="isModalDangerActive"
            buttonLabel="Confirm"
            title="Please confirm"
            button="danger"
            has-cancel
            @confirm="deleteRecord"
        >
            <p>Are you sure to delete?</p>
        </CardBoxModal>
        <CardBoxModal
            v-model="isModalDetailActive"
            buttonLabel="Close"
            :title="`Sales Order: ${detailData?.order_no ?? ''}`"
            full-width
            has-cancel
        >
            <div v-if="detailData" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                    <div><b>Date:</b> {{ detailData.order_date }}</div>
                    <div><b>Client:</b> {{ detailData.client }}</div>
                    <div><b>Type:</b> {{ detailData.product_type }}</div>
                    <div><b>Order GST %:</b> {{ detailData.gst_percent }}</div>
                    <div><b>Transport:</b> {{ detailTransport }}</div>
                    <div><b>Transport GST:</b> {{ detailTransportGst }}</div>
                    <div class="md:col-span-3"><b>Remark:</b> {{ detailData.remark || "-" }}</div>
                </div>

                <div>
                    <div class="font-semibold mb-2">Items</div>
                    <div class="overflow-auto">
                        <table class="w-full text-sm border">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="border p-2 text-left">Item</th>
                                    <th class="border p-2 text-left">Unit</th>
                                    <th class="border p-2 text-right">Qty</th>
                                    <th class="border p-2 text-right">Rate</th>
                                    <th class="border p-2 text-right">Taxable</th>
                                    <th class="border p-2 text-right">GST %</th>
                                    <th class="border p-2 text-right">GST Amt</th>
                                    <th class="border p-2 text-right">Line Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, idx) in detailData.items" :key="`item-${idx}`">
                                    <td class="border p-2">{{ item.item_name }}</td>
                                    <td class="border p-2">{{ item.qty_unit || "-" }}</td>
                                    <td class="border p-2 text-right">
                                        <div>{{ item.qty }}</div>
                                        <div
                                            v-if="item.qty_mode === 'dimension'"
                                            class="text-xs text-gray-500"
                                        >
                                            L×W×Q: {{ item.length ?? 0 }} × {{ item.width ?? 0 }} × {{ item.pieces ?? 0 }}
                                        </div>
                                    </td>
                                    <td class="border p-2 text-right">{{ item.rate }}</td>
                                    <td class="border p-2 text-right">{{ item.taxable_amount }}</td>
                                    <td class="border p-2 text-right">{{ item.gst_percent }}</td>
                                    <td class="border p-2 text-right">{{ item.gst_amount }}</td>
                                    <td class="border p-2 text-right">{{ item.line_total }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm font-semibold">
                    <div>Items Taxable: {{ detailItemsTotal }}</div>
                    <div>Items GST: {{ detailItemsGstTotal }}</div>
                    <div>Transport: {{ detailTransport }}</div>
                    <div>Transport GST: {{ detailTransportGst }}</div>
                    <div>Total Amount: {{ detailData.total_amount }}</div>
                </div>
            </div>
            <div v-else class="text-sm">Loading...</div>
        </CardBoxModal>
    </LayoutAuthenticated>
</template>

<style scoped>
/* Add click-away listener to close dropdown when clicking outside */
:deep(body) {
    @apply cursor-pointer;
}
</style>
