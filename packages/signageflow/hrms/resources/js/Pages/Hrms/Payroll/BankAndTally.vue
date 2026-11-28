<script setup>
import { computed, ref } from "vue";
import { Head, useForm, usePage, router } from "@inertiajs/vue3";
import {
    mdiBankTransfer,
    mdiFileXmlBox,
    mdiFileExcel,
    mdiBank,
    mdiPlus,
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
import Table from "@/components/DataTable/Table.vue";
import { can } from "@/utils/permissions";

const props = defineProps({
    payRuns: Object,
    bankTemplates: Array,
    resourceNeo: Object,
});

const message = computed(() => usePage().props.flash.message);
const msgType = computed(() => usePage().props.flash.msg_type ?? "info");

const isGlModalActive = ref(false);
const activePayRun = ref(null);

const glForm = useForm({
    voucher_date: new Date().toISOString().substring(0, 10),
    narration: "",
});

const openGlModal = (payRun) => {
    activePayRun.value = payRun;
    glForm.voucher_date = payRun.period_end ?? new Date().toISOString().substring(0, 10);
    glForm.narration = `Payroll disbursement for ${payRun.run_code}`;
    isGlModalActive.value = true;
};

const submitGl = () => {
    glForm.post(route("hrms.payroll.bankTally.createGl", activePayRun.value.id), {
        onSuccess: () => (isGlModalActive.value = false),
    });
};
</script>

<template>
    <LayoutAuthenticated>
        <Head title="HRMS - Bank & Tally GL" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiBankTransfer" title="Bank Submission & Tally GL Export" main />

            <NotificationBar v-if="message" :color="msgType" class="mb-4">
                {{ message }}
            </NotificationBar>

            <CardBox class="mb-6" has-table>
                <Table :resource="props.payRuns">
                    <template #cell(gl_batch_code)="{ item }">
                        <span v-if="item.gl_batch_code" class="text-xs font-semibold text-purple-700 bg-purple-100 px-2 py-0.5 rounded-full">
                            {{ item.gl_batch_code }}
                        </span>
                        <span v-else class="text-xs text-gray-400">Not Generated</span>
                    </template>
                    <template #cell(Actions)="{ item }">
                        <BaseButtons type="justify-start" no-wrap>
                            <!-- Bank Download -->
                            <BaseButton
                                :icon="mdiBank"
                                color="info"
                                small
                                rounded-full
                                title="Download NEFT File"
                                :href="route('hrms.payroll.bankTally.bankFile', item.id)"
                            />

                            <!-- GL Generate -->
                            <BaseButton
                                v-if="!item.gl_batch_id && can('hrmsPayroll_create')"
                                :icon="mdiPlus"
                                color="contrast"
                                small
                                rounded-full
                                title="Generate Tally GL Batch"
                                @click="openGlModal(item)"
                            />

                            <!-- Tally Prime XML -->
                            <BaseButton
                                v-if="item.gl_batch_id"
                                :icon="mdiFileXmlBox"
                                color="warning"
                                small
                                rounded-full
                                title="Download Tally Prime XML"
                                :href="route('hrms.payroll.bankTally.tallyXml', item.gl_batch_id)"
                            />

                            <!-- GL Excel -->
                            <BaseButton
                                v-if="item.gl_batch_id"
                                :icon="mdiFileExcel"
                                color="success"
                                small
                                rounded-full
                                title="Download GL Report Excel"
                                :href="route('hrms.payroll.bankTally.glExcel', item.gl_batch_id)"
                            />
                        </BaseButtons>
                    </template>
                </Table>
            </CardBox>

            <!-- Generate GL Batch Modal -->
            <CardBoxModal
                v-model="isGlModalActive"
                :title="`Generate Tally GL Batch - ${activePayRun?.run_code}`"
                button-label="Generate Batch"
                has-cancel
                @confirm="submitGl"
            >
                <div class="space-y-4">
                    <FormField label="Voucher Date">
                        <FormControl v-model="glForm.voucher_date" type="date" required />
                    </FormField>
                    <FormField label="Narration">
                        <FormControl v-model="glForm.narration" placeholder="Payroll journal entry narration" />
                    </FormField>
                </div>
            </CardBoxModal>
        </SectionMain>
    </LayoutAuthenticated>
</template>
