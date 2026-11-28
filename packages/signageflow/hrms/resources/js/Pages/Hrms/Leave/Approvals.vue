<script setup>
import { computed, ref } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { mdiClipboardCheckOutline, mdiAlert, mdiCheck, mdiClose } from "@mdi/js";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import CardBox from "@/components/CardBox.vue";
import BaseButton from "@/components/BaseButton.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import Table from "@/components/DataTable/Table.vue";
import { can } from "@/utils/permissions";

const message = computed(() => usePage().props.flash.message);
const msgType = computed(() => usePage().props.flash.msg_type ?? "warning");

const props = defineProps({
    tab: String,
    filters: Object,
    leaveRequests: Object,
    fwaRequests: Array,
    resourceNeo: Object,
});

const tabs = [
    { key: "waiting", label: "Waiting for Approval" },
    { key: "acted", label: "My Approved/Rejected Leaves" },
    { key: "previous_level", label: "Pending for Previous Level Approver" },
    { key: "backup_officer", label: "Pending for Backup Officer" },
];

const dateState = ref({
    tab: props.tab,
    start_date: props.filters?.start_date ?? "",
    end_date: props.filters?.end_date ?? "",
});

const switchTab = (tabKey) => {
    dateState.value.tab = tabKey;
    router.get(route("hrms.leave.approvals"), dateState.value, { preserveState: true, preserveScroll: true });
};

const applyDates = () => {
    router.get(route("hrms.leave.approvals"), dateState.value, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <LayoutAuthenticated>
        <Head title="Leave Approvals" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiClipboardCheckOutline" title="Leave & FWA Approval Workflows" main />

            <NotificationBar
                v-if="message"
                @closed="usePage().props.flash.message = ''"
                :color="msgType"
                :icon="mdiAlert"
                :outline="true"
            >
                {{ message }}
            </NotificationBar>

            <div class="flex flex-wrap gap-2 mb-4">
                <BaseButton
                    v-for="t in tabs"
                    :key="t.key"
                    :label="t.label"
                    :color="tab === t.key ? 'info' : 'white'"
                    :outline="tab !== t.key"
                    small
                    rounded-full
                    @click="switchTab(t.key)"
                />
            </div>

            <CardBox class="mb-4">
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <label class="font-semibold">Start Date:</label>
                    <input v-model="dateState.start_date" type="date" class="rounded border-slate-300 dark:bg-slate-800 text-sm" />
                    <label class="font-semibold">End Date:</label>
                    <input v-model="dateState.end_date" type="date" class="rounded border-slate-300 dark:bg-slate-800 text-sm" />
                    <BaseButton label="Apply Date Range" color="info" small @click="applyDates" />
                </div>
            </CardBox>

            <CardBox has-table class="mb-6">
                <Table :resource="leaveRequests" :resourceNeo="resourceNeo">
                    <template #cell(start_date)="{ item: r }">
                        <span>{{ r.start_date?.slice(0, 10) }}</span>
                    </template>
                    <template #cell(end_date)="{ item: r }">
                        <span>{{ r.end_date?.slice(0, 10) }}</span>
                    </template>
                    <template #cell(total_days)="{ item: r }">
                        <span class="font-bold">{{ r.total_days }} ({{ r.day_mode }})</span>
                    </template>
                    <template #cell(approval_status)="{ item: r }">
                        <span class="uppercase text-xs font-bold">{{ r.approval_status }}</span>
                    </template>
                    <template #cell(actions)="{ item: r }">
                        <div class="flex gap-2" v-if="r.approval_status?.startsWith('pending') && can('hrmsLeave_approve')">
                            <BaseButton
                                color="success"
                                :icon="mdiCheck"
                                small
                                @click="router.post(route('hrms.leave.requests.act', r.id), { action: 'approve' })"
                            />
                            <BaseButton
                                color="danger"
                                :icon="mdiClose"
                                small
                                @click="router.post(route('hrms.leave.requests.act', r.id), { action: 'reject' })"
                            />
                        </div>
                    </template>
                </Table>
            </CardBox>

            <CardBox has-table>
                <div class="p-3 font-bold border-b dark:border-slate-700">FWA Approve / Reject Queue</div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-slate-700 text-left">
                            <th class="p-2">Employee</th>
                            <th class="p-2">Policy</th>
                            <th class="p-2">Dates</th>
                            <th class="p-2">Status</th>
                            <th class="p-2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="f in fwaRequests" :key="f.id" class="border-b dark:border-slate-800">
                            <td class="p-2">{{ f.employee?.full_name }}</td>
                            <td class="p-2">{{ f.policy?.name }}</td>
                            <td class="p-2">{{ f.start_date?.slice(0, 10) }} → {{ f.end_date?.slice(0, 10) }}</td>
                            <td class="p-2 uppercase text-xs font-bold">{{ f.approval_status }}</td>
                            <td class="p-2 flex gap-2" v-if="f.approval_status.startsWith('pending') && can('hrmsLeave_approve')">
                                <BaseButton label="Approve" color="success" small @click="router.post(route('hrms.leave.fwa.act', f.id), { action: 'approve' })" />
                                <BaseButton label="Reject" color="danger" small @click="router.post(route('hrms.leave.fwa.act', f.id), { action: 'reject' })" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardBox>
        </SectionMain>
    </LayoutAuthenticated>
</template>
