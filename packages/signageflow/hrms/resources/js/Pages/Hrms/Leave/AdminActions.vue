<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import { mdiAccountTie } from "@mdi/js";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import CardBox from "@/components/CardBox.vue";
import BaseButton from "@/components/BaseButton.vue";
import FlashBanner from "@hrms/Components/FlashBanner.vue";

const props = defineProps({
    currentFy: String,
    employees: Array,
    leaveTypes: Array,
    balances: Array,
    yearEndRuns: Array,
});

const behalfForm = useForm({
    employee_id: "", leave_type_id: "", start_date: new Date().toISOString().slice(0, 10),
    end_date: new Date().toISOString().slice(0, 10), day_mode: "full", apply_mode: "manager",
    auto_approve: true, reason: "",
});
const compOffForm = useForm({ employee_id: "", worked_date: new Date().toISOString().slice(0, 10), days_earned: 1, remarks: "" });
const statForm = useForm({ employee_id: "", code: "ML", days: "" });
const adjForm = useForm({ employee_id: "", leave_type_id: "", financial_year: props.currentFy, days: 1, remarks: "" });
const yearEndForm = useForm({ from_financial_year: props.currentFy });
</script>

<template>
    <LayoutAuthenticated>
        <Head title="Leave Admin & Year-End" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiAccountTie" :title="`Leave Admin Actions & FY Year-End (${currentFy})`" main />
            <FlashBanner />

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                <CardBox is-form @submit.prevent="behalfForm.post(route('hrms.leave.actions.applyOnBehalf'))">
                    <h3 class="font-bold mb-3">Apply On Behalf (Manager / Super Admin)</h3>
                    <div class="space-y-2 text-sm">
                        <select v-model="behalfForm.employee_id" required class="w-full rounded border-slate-300 dark:bg-slate-800">
                            <option value="">-- Employee --</option>
                            <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.emp_code }} - {{ e.full_name }}</option>
                        </select>
                        <select v-model="behalfForm.leave_type_id" required class="w-full rounded border-slate-300 dark:bg-slate-800">
                            <option value="">-- Leave Type --</option>
                            <option v-for="lt in leaveTypes" :key="lt.id" :value="lt.id">{{ lt.name }}</option>
                        </select>
                        <div class="grid grid-cols-2 gap-2">
                            <input v-model="behalfForm.start_date" type="date" required class="rounded border-slate-300 dark:bg-slate-800" />
                            <input v-model="behalfForm.end_date" type="date" required class="rounded border-slate-300 dark:bg-slate-800" />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <select v-model="behalfForm.day_mode" class="rounded border-slate-300 dark:bg-slate-800">
                                <option value="full">Full Day</option>
                                <option value="first_half">First Half</option>
                                <option value="second_half">Second Half</option>
                            </select>
                            <select v-model="behalfForm.apply_mode" class="rounded border-slate-300 dark:bg-slate-800">
                                <option value="manager">Manager Mode</option>
                                <option value="super_admin">Super Admin Mode</option>
                            </select>
                        </div>
                        <input v-model="behalfForm.reason" placeholder="Reason" required class="w-full rounded border-slate-300 dark:bg-slate-800" />
                        <label class="flex items-center gap-2"><input v-model="behalfForm.auto_approve" type="checkbox" /> Auto Approve</label>
                        <BaseButton type="submit" color="info" label="Submit Leave" />
                    </div>
                </CardBox>

                <div class="space-y-4">
                    <CardBox is-form @submit.prevent="compOffForm.post(route('hrms.leave.actions.compOff'))">
                        <h3 class="font-bold mb-2">Add Comp-Off & Activate Maternity/Paternity</h3>
                        <div class="grid grid-cols-3 gap-2 text-sm mb-2">
                            <select v-model="compOffForm.employee_id" required class="rounded border-slate-300 dark:bg-slate-800">
                                <option value="">-- Employee --</option>
                                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }}</option>
                            </select>
                            <input v-model="compOffForm.worked_date" type="date" class="rounded border-slate-300 dark:bg-slate-800" />
                            <BaseButton type="submit" color="success" label="+1 Comp-Off" small />
                        </div>
                    </CardBox>

                    <CardBox is-form @submit.prevent="statForm.post(route('hrms.leave.actions.statutory'))">
                        <div class="grid grid-cols-3 gap-2 text-sm">
                            <select v-model="statForm.employee_id" required class="rounded border-slate-300 dark:bg-slate-800">
                                <option value="">-- Employee --</option>
                                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }}</option>
                            </select>
                            <select v-model="statForm.code" class="rounded border-slate-300 dark:bg-slate-800">
                                <option value="ML">Activate Maternity (ML)</option>
                                <option value="PL">Activate Paternity (PL)</option>
                            </select>
                            <BaseButton type="submit" color="info" label="Activate" small />
                        </div>
                    </CardBox>

                    <CardBox is-form @submit.prevent="adjForm.post(route('hrms.leave.actions.adjust'))">
                        <h3 class="font-bold mb-2">Leave Adjustment</h3>
                        <div class="grid grid-cols-4 gap-2 text-sm">
                            <select v-model="adjForm.employee_id" required class="rounded border-slate-300 dark:bg-slate-800">
                                <option value="">-- Emp --</option>
                                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }}</option>
                            </select>
                            <select v-model="adjForm.leave_type_id" required class="rounded border-slate-300 dark:bg-slate-800">
                                <option value="">-- Type --</option>
                                <option v-for="lt in leaveTypes" :key="lt.id" :value="lt.id">{{ lt.code }}</option>
                            </select>
                            <input v-model="adjForm.days" type="number" step="0.5" class="rounded border-slate-300 dark:bg-slate-800" />
                            <BaseButton type="submit" color="warning" label="Adjust" small />
                        </div>
                        <input v-model="adjForm.remarks" placeholder="Adjustment remarks" required class="mt-2 w-full rounded border-slate-300 dark:bg-slate-800 text-sm" />
                    </CardBox>

                    <CardBox is-form @submit.prevent="yearEndForm.post(route('hrms.leave.actions.yearEnd'))">
                        <h3 class="font-bold mb-2">Leave Year-End Process (FY Apr–Mar)</h3>
                        <div class="flex gap-2 text-sm">
                            <input v-model="yearEndForm.from_financial_year" class="rounded border-slate-300 dark:bg-slate-800" />
                            <BaseButton type="submit" color="danger" label="Run FY Carry-Forward / Lapse" small />
                        </div>
                    </CardBox>
                </div>
            </div>
        </SectionMain>
    </LayoutAuthenticated>
</template>
