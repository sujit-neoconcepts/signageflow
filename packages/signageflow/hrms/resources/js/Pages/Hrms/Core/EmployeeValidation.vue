<script setup>
import { Head, Link } from "@inertiajs/vue3";
import { mdiAlertCircleOutline } from "@mdi/js";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import CardBox from "@/components/CardBox.vue";

defineProps({ issues: Array });
</script>

<template>
    <LayoutAuthenticated>
        <Head title="Employee Validation" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiAlertCircleOutline" title="Employee Master Validation" main />
            <CardBox has-table>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-slate-700 text-left">
                            <th class="p-3">Emp Code</th>
                            <th class="p-3">Employee</th>
                            <th class="p-3">Warnings</th>
                            <th class="p-3 text-right">Fix</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in issues" :key="row.id" class="border-b dark:border-slate-800">
                            <td class="p-3 font-mono">{{ row.emp_code }}</td>
                            <td class="p-3">{{ row.full_name }}</td>
                            <td class="p-3 text-amber-600 font-medium">{{ row.warnings.join(', ') }}</td>
                            <td class="p-3 text-right">
                                <Link :href="route('hrms.employees.edit', row.id)" class="text-sky-600 font-semibold">Edit</Link>
                            </td>
                        </tr>
                        <tr v-if="issues.length === 0">
                            <td colspan="4" class="p-6 text-center text-emerald-600 font-semibold">All active employee records passed validation.</td>
                        </tr>
                    </tbody>
                </table>
            </CardBox>
        </SectionMain>
    </LayoutAuthenticated>
</template>
