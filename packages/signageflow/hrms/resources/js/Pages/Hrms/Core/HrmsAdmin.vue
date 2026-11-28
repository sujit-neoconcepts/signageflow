<script setup>
import { ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import { mdiShieldAccount } from "@mdi/js";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import CardBox from "@/components/CardBox.vue";
import BaseButton from "@/components/BaseButton.vue";
import FlashBanner from "@hrms/Components/FlashBanner.vue";

defineProps({
    userAccessReport: Array,
    templates: Array,
    notificationLogs: Array,
    delegations: Array,
    employees: Array,
    settings: Array,
    auditLogs: Array,
});

const tab = ref("delegations");
const delegationForm = useForm({
    delegator_employee_id: "",
    backup_employee_id: "",
    module: "all",
    start_date: new Date().toISOString().slice(0, 10),
    end_date: new Date().toISOString().slice(0, 10),
    reason: "",
});
</script>

<template>
    <LayoutAuthenticated>
        <Head title="HRMS Administration" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiShieldAccount" title="HRMS Security, Notifications & Audit" main />
            <FlashBanner />

            <div class="flex flex-wrap gap-2 mb-4">
                <BaseButton
                    v-for="t in ['delegations', 'access', 'templates', 'tracker', 'audit']"
                    :key="t"
                    :label="t.charAt(0).toUpperCase() + t.slice(1)"
                    :color="tab === t ? 'info' : 'white'"
                    :outline="tab !== t"
                    small
                    rounded-full
                    @click="tab = t"
                />
            </div>

            <div v-if="tab === 'delegations'" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <CardBox is-form @submit.prevent="delegationForm.post(route('hrms.admin.delegations.store'))">
                    <h3 class="font-bold mb-3">Assign Backup Officer Delegation</h3>
                    <div class="space-y-3">
                        <select v-model="delegationForm.delegator_employee_id" required class="w-full rounded border-slate-300 dark:bg-slate-800 text-sm">
                            <option value="">-- Approver (Delegator) --</option>
                            <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.emp_code }} - {{ e.full_name }}</option>
                        </select>
                        <select v-model="delegationForm.backup_employee_id" required class="w-full rounded border-slate-300 dark:bg-slate-800 text-sm">
                            <option value="">-- Backup Officer --</option>
                            <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.emp_code }} - {{ e.full_name }}</option>
                        </select>
                        <select v-model="delegationForm.module" class="w-full rounded border-slate-300 dark:bg-slate-800 text-sm">
                            <option value="all">All Modules</option>
                            <option value="attendance">Attendance</option>
                            <option value="leave">Leave</option>
                        </select>
                        <input v-model="delegationForm.start_date" type="date" class="w-full rounded border-slate-300 dark:bg-slate-800 text-sm" />
                        <input v-model="delegationForm.end_date" type="date" class="w-full rounded border-slate-300 dark:bg-slate-800 text-sm" />
                        <input v-model="delegationForm.reason" placeholder="Reason" class="w-full rounded border-slate-300 dark:bg-slate-800 text-sm" />
                        <BaseButton type="submit" color="info" label="Save Delegation" />
                    </div>
                </CardBox>
                <CardBox class="lg:col-span-2" has-table>
                    <table class="w-full text-sm">
                        <thead><tr class="border-b dark:border-slate-700 text-left"><th class="p-3">Delegator</th><th class="p-3">Backup Officer</th><th class="p-3">Module</th><th class="p-3">Dates</th></tr></thead>
                        <tbody>
                            <tr v-for="d in delegations" :key="d.id" class="border-b dark:border-slate-800">
                                <td class="p-3">{{ d.delegator?.full_name }}</td>
                                <td class="p-3 font-semibold">{{ d.backup?.full_name }}</td>
                                <td class="p-3 uppercase">{{ d.module }}</td>
                                <td class="p-3">{{ d.start_date?.slice(0, 10) }} → {{ d.end_date?.slice(0, 10) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </CardBox>
            </div>

            <CardBox v-if="tab === 'access'" has-table>
                <table class="w-full text-sm">
                    <thead><tr class="border-b dark:border-slate-700 text-left"><th class="p-3">User</th><th class="p-3">Roles</th><th class="p-3">HRMS Permissions</th></tr></thead>
                    <tbody>
                        <tr v-for="u in userAccessReport" :key="u.id" class="border-b dark:border-slate-800">
                            <td class="p-3 font-medium">{{ u.name }} ({{ u.email }})</td>
                            <td class="p-3">{{ u.roles.join(', ') }}</td>
                            <td class="p-3 text-xs">{{ u.hrms_permissions.join(', ') || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </CardBox>

            <CardBox v-if="tab === 'templates'" has-table>
                <table class="w-full text-sm">
                    <thead><tr class="border-b dark:border-slate-700 text-left"><th class="p-3">Event</th><th class="p-3">Subject</th><th class="p-3">Channels</th></tr></thead>
                    <tbody>
                        <tr v-for="t in templates" :key="t.id" class="border-b dark:border-slate-800">
                            <td class="p-3 font-mono">{{ t.event_key }}</td>
                            <td class="p-3">{{ t.subject_template }}</td>
                            <td class="p-3 text-xs">Email: {{ t.email_enabled ? 'On' : 'Off' }} | Push: {{ t.push_enabled ? 'On' : 'Off' }} | In-App: {{ t.in_app_enabled ? 'On' : 'Off' }}</td>
                        </tr>
                    </tbody>
                </table>
            </CardBox>

            <CardBox v-if="tab === 'tracker'" has-table>
                <table class="w-full text-sm">
                    <thead><tr class="border-b dark:border-slate-700 text-left"><th class="p-3">Event</th><th class="p-3">Channel</th><th class="p-3">Status</th><th class="p-3">Subject</th></tr></thead>
                    <tbody>
                        <tr v-for="l in notificationLogs" :key="l.id" class="border-b dark:border-slate-800">
                            <td class="p-3 font-mono">{{ l.event_key }}</td>
                            <td class="p-3 uppercase">{{ l.channel }}</td>
                            <td class="p-3">{{ l.status }}</td>
                            <td class="p-3">{{ l.subject }}</td>
                        </tr>
                    </tbody>
                </table>
            </CardBox>

            <CardBox v-if="tab === 'audit'" has-table>
                <table class="w-full text-sm">
                    <thead><tr class="border-b dark:border-slate-700 text-left"><th class="p-3">Time</th><th class="p-3">User</th><th class="p-3">Module</th><th class="p-3">Action</th><th class="p-3">Key</th></tr></thead>
                    <tbody>
                        <tr v-for="a in auditLogs" :key="a.id" class="border-b dark:border-slate-800">
                            <td class="p-3">{{ a.formatted_date }}</td>
                            <td class="p-3">{{ a.user?.name }}</td>
                            <td class="p-3 font-mono">{{ a.module }}</td>
                            <td class="p-3">{{ a.action }}</td>
                            <td class="p-3">{{ a.data_key }}</td>
                        </tr>
                    </tbody>
                </table>
            </CardBox>
        </SectionMain>
    </LayoutAuthenticated>
</template>
