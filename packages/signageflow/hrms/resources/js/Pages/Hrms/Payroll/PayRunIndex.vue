<script setup>
import { computed, ref } from "vue";
import { Head, useForm, usePage, router } from "@inertiajs/vue3";
import {
    mdiCalendarSync,
    mdiPlus,
    mdiEye,
    mdiCheckCircle,
} from "@mdi/js";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import CardBox from "@/components/CardBox.vue";
import CardBoxModal from "@/components/CardBoxModal.vue";
import BaseButton from "@/components/BaseButton.vue";
import BaseButtons from "@/components/BaseButtons.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import FormField from "@/components/FormField.vue";
import FormControl from "@/components/FormControl.vue";
import FormCheckRadio from "@/components/FormCheckRadio.vue";
import Table from "@/components/DataTable/Table.vue";
import { can } from "@/utils/permissions";

const props = defineProps({
    payRuns: Object,
    payGroups: Array,
    resourceNeo: Object,
});

const message = computed(() => usePage().props.flash.message);
const msgType = computed(() => usePage().props.flash.msg_type ?? "info");

const isCreateModalActive = ref(false);

const now = new Date();
const currentYear = now.getFullYear();
const currentMonth = now.getMonth() + 1;

const firstDay = new Date(currentYear, currentMonth - 1, 1).toISOString().substring(0, 10);
const lastDay = new Date(currentYear, currentMonth, 0).toISOString().substring(0, 10);

const form = useForm({
    pay_group_id: props.payGroups?.[0]?.id ?? null,
    period_year: currentYear,
    period_month: currentMonth,
    week_number: null,
    period_start: firstDay,
    period_end: lastDay,
    sync_options: {
        absent: true,
        leave: true,
        late: true,
        early_out: false,
        ot: true,
        break_hours: false,
    },
});

const selectedPayGroup = computed(() =>
    props.payGroups.find((p) => p.id === (form.pay_group_id?.id ?? form.pay_group_id))
);

const unwrap = (val) => (val && typeof val === "object" ? val.id : val) || null;

const submitCreate = () => {
    form.transform((data) => ({
        ...data,
        pay_group_id: unwrap(data.pay_group_id),
    })).post(route("hrms.payroll.payruns.store"), {
        onSuccess: () => (isCreateModalActive.value = false),
    });
};
</script>

<template>
    <LayoutAuthenticated>
        <Head title="HRMS - PayRuns" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiCalendarSync" title="3-Stage PayRun Engine" main>
                <BaseButton
                    v-if="can('hrmsPayroll_create')"
                    :icon="mdiPlus"
                    label="Initialize PayRun"
                    color="contrast"
                    rounded-full
                    small
                    @click="isCreateModalActive = true"
                />
            </SectionTitleLineWithButton>

            <NotificationBar v-if="message" :color="msgType" class="mb-4">
                {{ message }}
            </NotificationBar>

            <CardBox class="mb-6" has-table>
                <Table :resource="props.payRuns">
                    <template #cell(stage)="{ item }">
                        <span
                            class="px-2 py-0.5 text-xs font-semibold rounded-full"
                            :class="{
                                'bg-yellow-100 text-yellow-800': item.stage === 'initialized',
                                'bg-blue-100 text-blue-800': item.stage.startsWith('pending_'),
                                'bg-purple-100 text-purple-800': item.stage === 'approved',
                                'bg-green-100 text-green-800': item.stage === 'published',
                            }"
                        >
                            {{ item.stage.toUpperCase() }}
                        </span>
                    </template>
                    <template #cell(Actions)="{ item }">
                        <BaseButtons type="justify-start" no-wrap>
                            <BaseButton
                                :icon="mdiEye"
                                color="info"
                                small
                                rounded-full
                                :href="route('hrms.payroll.payruns.show', item.id)"
                            />
                        </BaseButtons>
                    </template>
                </Table>
            </CardBox>

            <!-- Initialize PayRun Modal -->
            <CardBoxModal
                v-model="isCreateModalActive"
                title="Initialize New PayRun (Stage 1)"
                button-label="Create & Initialize"
                has-cancel
                @confirm="submitCreate"
            >
                <div class="space-y-4">
                    <FormField label="Pay Group">
                        <FormControl
                            v-model="form.pay_group_id"
                            :options="props.payGroups.map(p => ({ id: p.id, label: `${p.name} (${p.pay_frequency})` }))"
                            type="select"
                            required
                        />
                    </FormField>

                    <div class="grid grid-cols-2 gap-4">
                        <FormField label="Year">
                            <FormControl v-model="form.period_year" type="number" required />
                        </FormField>
                        <FormField label="Month">
                            <FormControl v-model="form.period_month" type="number" min="1" max="12" required />
                        </FormField>
                    </div>

                    <FormField
                        v-if="selectedPayGroup?.pay_frequency === 'weekly'"
                        label="Week Number (1-53)"
                    >
                        <FormControl v-model="form.week_number" type="number" min="1" max="53" required />
                    </FormField>

                    <div class="grid grid-cols-2 gap-4">
                        <FormField label="Period Start">
                            <FormControl v-model="form.period_start" type="date" required />
                        </FormField>
                        <FormField label="Period End">
                            <FormControl v-model="form.period_end" type="date" required />
                        </FormField>
                    </div>

                    <div class="border-t pt-3">
                        <div class="font-semibold text-sm mb-2 text-gray-700 dark:text-gray-300">
                            Live Attendance & Leave Sync Checkboxes:
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <FormCheckRadio v-model="form.sync_options.absent" label="Deduct Absent Days" type="checkbox" />
                            <FormCheckRadio v-model="form.sync_options.leave" label="Sync Paid/Unpaid Leave" type="checkbox" />
                            <FormCheckRadio v-model="form.sync_options.late" label="Apply Late Deductions" type="checkbox" />
                            <FormCheckRadio v-model="form.sync_options.ot" label="Include Overtime Hours" type="checkbox" />
                            <FormCheckRadio v-model="form.sync_options.early_out" label="Apply Early-Out Rule" type="checkbox" />
                            <FormCheckRadio v-model="form.sync_options.break_hours" label="Sync Break Violations" type="checkbox" />
                        </div>
                    </div>
                </div>
            </CardBoxModal>
        </SectionMain>
    </LayoutAuthenticated>
</template>
