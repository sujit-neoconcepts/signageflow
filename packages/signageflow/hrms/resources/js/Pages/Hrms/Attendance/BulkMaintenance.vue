<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import { mdiAllInclusive } from "@mdi/js";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import CardBox from "@/components/CardBox.vue";
import BaseButton from "@/components/BaseButton.vue";
import FlashBanner from "@hrms/Components/FlashBanner.vue";

defineProps({ departments: Array, shifts: Array, workLocations: Array, employees: Array });

const globalForm = useForm({ department_id: "", default_shift_id: "", work_location_id: "", geofence_mode: "" });
const backfillForm = useForm({ from_date: new Date().toISOString().slice(0, 10), to_date: new Date().toISOString().slice(0, 10), department_id: "", employee_id: "" });
const bioForm = useForm({ file: null });
</script>

<template>
    <LayoutAuthenticated>
        <Head title="Attendance Bulk Maintenance" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiAllInclusive" title="Bulk Maintenance & Biometric Import" main />
            <FlashBanner />

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <CardBox is-form @submit.prevent="globalForm.post(route('hrms.attendance.maintenance.globalChange'))">
                    <h3 class="font-bold mb-3">Global Change / Staff Bulk Update</h3>
                    <div class="space-y-2 text-sm">
                        <select v-model="globalForm.department_id" class="w-full rounded border-slate-300 dark:bg-slate-800">
                            <option value="">All Active Staff (or pick Dept)</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                        <select v-model="globalForm.default_shift_id" class="w-full rounded border-slate-300 dark:bg-slate-800">
                            <option value="">-- Keep Shift --</option>
                            <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <select v-model="globalForm.work_location_id" class="w-full rounded border-slate-300 dark:bg-slate-800">
                            <option value="">-- Keep Location --</option>
                            <option v-for="l in workLocations" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                        <BaseButton type="submit" color="info" label="Apply Global Change" />
                    </div>
                </CardBox>

                <CardBox is-form @submit.prevent="backfillForm.post(route('hrms.attendance.maintenance.backfill'))">
                    <h3 class="font-bold mb-3">Create Missing Date (Backfill)</h3>
                    <div class="space-y-2 text-sm">
                        <input v-model="backfillForm.from_date" type="date" required class="w-full rounded border-slate-300 dark:bg-slate-800" />
                        <input v-model="backfillForm.to_date" type="date" required class="w-full rounded border-slate-300 dark:bg-slate-800" />
                        <select v-model="backfillForm.department_id" class="w-full rounded border-slate-300 dark:bg-slate-800">
                            <option value="">All Departments</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                        <BaseButton type="submit" color="warning" label="Generate Missing Dates" />
                    </div>
                </CardBox>

                <CardBox is-form @submit.prevent="bioForm.post(route('hrms.attendance.maintenance.importBiometric'))">
                    <h3 class="font-bold mb-3">Biometric Punch Import</h3>
                    <p class="text-xs text-slate-500 mb-2">Columns: <code>Emp Code | Punched At (Y-m-d H:i:s) | Direction (in/out/auto)</code></p>
                    <input type="file" accept=".xlsx,.xls,.csv" @change="bioForm.file = $event.target.files[0]" class="mb-3 block text-sm" />
                    <BaseButton type="submit" color="success" label="Import Punches" :disabled="!bioForm.file" />
                </CardBox>
            </div>
        </SectionMain>
    </LayoutAuthenticated>
</template>
