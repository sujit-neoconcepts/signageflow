<script setup>
import { computed, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import { mdiBookOpenPageVariant } from "@mdi/js";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import CardBox from "@/components/CardBox.vue";
import BaseButton from "@/components/BaseButton.vue";

defineProps({ currentFy: String });

const search = ref("");
const activeSection = ref("all");

const sections = [
    {
        id: "overview",
        title: "1. HRMS Overview & Financial Year Cycle",
        summary: "Unified HR platform inside SignageFlow operating on the April 1 – March 31 Financial Year.",
        items: [
            "All Attendance, Leave accruals, Leave carry-forward, and upcoming Payroll/TDS modules align with SignageFlow's April 1 – March 31 Financial Year.",
            "Every dashboard and report supports Organization Hierarchy filters: Company, Department, Section, Designation, Category, and Extra Classification.",
            "Access is controlled via Spatie Permissions (hrms*_*), with Super Admin bypass and configurable L1 / L2 / Backup Officer approval chains.",
        ],
    },
    {
        id: "core",
        title: "2. Organization Hierarchy & Employee Master",
        summary: "Maintain multi-level org structure, staff profiles, approvals, and clocking credentials.",
        items: [
            "Org Hierarchy (/admin/hrms/org-hierarchy): Create, edit, and delete Companies, Departments, Sections, Designations, Categories, and Extra Classifications.",
            "Employee Master (/admin/hrms/employees): Add/Edit profiles with Emp Code, optional SignageFlow User Login link (leave blank for Kiosk-only staff), L1/L2/Backup Officer, Default Shift, Work Location, Geofence Mode, Kiosk PIN, and Bank/PAN/UAN details.",
            "Bulk Tools: Import staff from Excel/CSV, run Employee Validation to detect missing shifts/locations/approvers/bank info, and export rosters to Excel.",
            "HRMS Admin & Audit (/admin/hrms/admin): Manage temporary Backup Officer delegations, inspect User Access Reports, customize Notification Templates, view Notification Tracker logs, and review Audit Logs.",
        ],
    },
    {
        id: "attendance",
        title: "3. Attendance Management & Clocking Channels",
        summary: "Real-time analytics, shift/geofence rules, kiosk terminal, adjustments, and 16 reports.",
        items: [
            "Attendance Dashboard (/admin/hrms/attendance/dashboard): View Present/Absent/On Leave/Late/Early Out/Pending Approvals KPIs, Absent Today roster, Shift stacked bar chart, Present vs Absent donut, 30-day Late Comers with trend arrows, Gender Headcount, and one-click Excel export.",
            "Attendance Setup (/admin/hrms/attendance/setup): Configure Shifts (grace in/out, break, half/full day, overnight, OT multiplier), Geo-fenced Work Locations (GPS lat/lng, radius, strict block vs allow-and-flag, optional selfie), Tablet Kiosks, Shift Rosters, and Attendance Allowance rules.",
            "Tablet Kiosk (/hrms/kiosk): Dedicated lockable screen for shop-floor tablets using Kiosk Code + Device Token and Employee Code + PIN (or QR badge).",
            "Bulk Maintenance (/admin/hrms/attendance/maintenance): Apply Global Changes by department, Backfill Missing Dates, and import Biometric punches via Excel/CSV.",
            "Adjustments & Approvals (/admin/hrms/attendance/adjustments): Perform Manual Adjustments (Individual, Department, Filters, Bulk), adjust Break minutes, Approve/Reject Attendance Regularizations & OT, and compute/approve Attendance Allowances.",
            "Attendance Reports (/admin/hrms/attendance/reports): Generate & export 16 reports including Daily Attendance, Muster, Consolidated Monthly, Working Hours, Day/Month OT, Clocking Data, Mobile Attendance, and Attendance Problem.",
        ],
    },
    {
        id: "leave",
        title: "4. Leave Management & Approvals (FY Apr–Mar)",
        summary: "Accruals, FWA, calendar controls, 4-tab approval workflow, special leaves, and year-end.",
        items: [
            "Leave Dashboard (/admin/hrms/leave/dashboard): Track Pending Approvals, Leaves for the Month daily bar chart, Employees on Leave Today by Type, and Weekly/Monthly/Yearly Leave Trends.",
            "Leave & Calendar Setup (/admin/hrms/leave/setup): Configure Leave Types (paid/unpaid, half-day, monthly/quarterly/annual accrual, carry-forward cap, gender/tenure rules), FWA Policies (WFH/Flexi), and Calendar Controls (Public Holidays, Restricted Days, Blocked Annual Leave, Rest Days).",
            "Leave Approvals (/admin/hrms/leave/approvals): Process requests across 4 workflow tabs — Waiting for Approval, My Approved/Rejected Leaves, Pending for Previous Level Approver, and Pending for Backup Officer — plus FWA approvals.",
            "Leave Admin & Year-End (/admin/hrms/leave/actions): Apply On Behalf (Manager & Super Admin modes), Batch Leave entry, credit Comp-Off, activate Maternity/Paternity leave, adjust or recalculate balances, and run FY Year-End carry-forward/lapse.",
            "Leave Reports (/admin/hrms/leave/reports): View & export Daily Leave, Monthly Summary, Leave Consolidate (FY), Leave History (transaction ledger), Comp-Off, Permission, and FWA Status reports.",
        ],
    },
    {
        id: "mobile",
        title: "5. Mobile App Self-Service (SignageFlow Mobile)",
        summary: "Employee self-service inside the Android/iOS Flutter app.",
        items: [
            "Sign-In: Any active SignageFlow user linked to an active HRMS Employee profile can sign in to the Mobile app.",
            "HRMS > Attendance Tab: View today's shift and punches, Punch In / Punch Out using live GPS (with optional camera selfie when required by location/profile), browse monthly attendance logs, and submit Regularization Requests.",
            "HRMS > Leave & FWA Tab: Check current Financial Year leave balances, view FY holidays, apply for Full/Half-day Leave with optional document attachment, and cancel pending requests.",
            "Note: All L1/L2/Backup Officer approvals remain on the web admin portal.",
        ],
    },
];

const filteredSections = computed(() => {
    const q = search.value.trim().toLowerCase();
    return sections.filter((s) => {
        if (activeSection.value !== "all" && s.id !== activeSection.value) return false;
        if (!q) return true;
        return (
            s.title.toLowerCase().includes(q) ||
            s.summary.toLowerCase().includes(q) ||
            s.items.some((i) => i.toLowerCase().includes(q))
        );
    });
});
</script>

<template>
    <LayoutAuthenticated>
        <Head title="HRMS User Guide" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiBookOpenPageVariant" :title="`HRMS User Guide (FY ${currentFy})`" main />

            <CardBox class="mb-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap gap-2">
                        <BaseButton label="All Topics" :color="activeSection === 'all' ? 'info' : 'white'" :outline="activeSection !== 'all'" small rounded-full @click="activeSection = 'all'" />
                        <BaseButton v-for="s in sections" :key="s.id" :label="s.title.split('.')[1]?.trim() || s.title" :color="activeSection === s.id ? 'info' : 'white'" :outline="activeSection !== s.id" small rounded-full @click="activeSection = s.id" />
                    </div>
                    <input v-model="search" type="text" placeholder="Search user guide..." class="rounded border-slate-300 dark:bg-slate-800 dark:border-slate-700 text-sm w-64" />
                </div>
            </CardBox>

            <div class="space-y-4">
                <CardBox v-for="sec in filteredSections" :key="sec.id">
                    <h2 class="text-lg font-bold text-sky-700 dark:text-sky-400 mb-1">{{ sec.title }}</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mb-3">{{ sec.summary }}</p>
                    <ul class="list-disc pl-5 space-y-2 text-sm">
                        <li v-for="(item, idx) in sec.items" :key="idx">{{ item }}</li>
                    </ul>
                </CardBox>
            </div>
        </SectionMain>
    </LayoutAuthenticated>
</template>
