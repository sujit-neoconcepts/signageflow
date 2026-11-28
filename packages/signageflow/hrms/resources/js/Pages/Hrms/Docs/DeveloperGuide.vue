<script setup>
import { computed, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import { mdiCodeBraces } from "@mdi/js";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import CardBox from "@/components/CardBox.vue";
import BaseButton from "@/components/BaseButton.vue";

defineProps({
    currentFy: String,
    configSummary: Object,
});

const activeTab = ref("architecture");
const tabs = [
    { key: "architecture", label: "Package & Vite Architecture" },
    { key: "schema", label: "Database Schema" },
    { key: "services", label: "Domain Services" },
    { key: "api", label: "Routes & Mobile API" },
    { key: "cli", label: "Commands, Seeders & Tests" },
];
</script>

<template>
    <LayoutAuthenticated>
        <Head title="HRMS Developer Guide" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiCodeBraces" title="HRMS Developer Guide" main />

            <div class="flex flex-wrap gap-2 mb-4">
                <BaseButton
                    v-for="t in tabs"
                    :key="t.key"
                    :label="t.label"
                    :color="activeTab === t.key ? 'info' : 'white'"
                    :outline="activeTab !== t.key"
                    small
                    rounded-full
                    @click="activeTab = t.key"
                />
            </div>

            <CardBox v-if="activeTab === 'architecture'" class="space-y-3 text-sm">
                <h2 class="text-lg font-bold">Full-Stack Local Composer Package (<code>signageflow/hrms</code>)</h2>
                <p>Package root: <code>packages/signageflow/hrms</code> (PSR-4 namespace <code>SignageFlow\Hrms\</code>, service provider <code>HrmsServiceProvider</code>).</p>
                <ul class="list-disc pl-5 space-y-1.5">
                    <li><strong>CI/CD Vite Rule</strong>: Deployment workflows run <code>pnpm build</code> before <code>composer install</code>. Therefore <code>vite.config.js</code> (<code>@hrms</code>), <code>resources/css/main.css</code> (<code>@source</code>), <code>resources/js/app.js</code>, and <code>resources/views/app.blade.php</code> resolve <code>packages/signageflow/hrms/resources/js/...</code> directly without referencing <code>vendor/</code>.</li>
                    <li><strong>Host Bridges</strong>: Configurable in <code>config/hrms.php</code> — <code>user</code> (<code>App\Models\User</code>), <code>location</code> (<code>App\Models\Location</code>), <code>job</code>, <code>task</code>, <code>expense</code>.</li>
                    <li><strong>Route Caching</strong>: All web (<code>{{ configSummary.routePrefix }}</code>), kiosk (<code>{{ configSummary.kioskPrefix }}</code>), and mobile API (<code>{{ configSummary.mobileApiPrefix }}</code>) routes use controller actions (zero closures) so <code>php artisan route:cache</code> works cleanly.</li>
                    <li><strong>UI Standard</strong>: List views use Spatie <code>QueryBuilder</code> + <code>InertiaTable</code> + <code>@/components/DataTable/Table.vue</code>, <code>CardBoxModal</code>, <code>NotificationBar</code>, and <code>BaseButton</code>.</li>
                </ul>
            </CardBox>

            <CardBox v-if="activeTab === 'schema'" class="space-y-3 text-sm">
                <h2 class="text-lg font-bold">Database Schema (<code>hrms_*</code>)</h2>
                <ul class="list-disc pl-5 space-y-1.5">
                    <li><strong>Core</strong>: <code>hrms_companies</code>, <code>hrms_departments</code>, <code>hrms_sections</code>, <code>hrms_designations</code>, <code>hrms_categories</code>, <code>hrms_extra_classifications</code>, <code>hrms_shifts</code>, <code>hrms_work_locations</code> (nullable <code>host_location_id</code> -> <code>locations.id</code>), <code>hrms_employees</code> (nullable unique <code>user_id</code> -> <code>users.id</code>), <code>hrms_employee_work_locations</code>, <code>hrms_approval_delegations</code>, <code>hrms_notification_templates</code>, <code>hrms_notification_logs</code>, <code>hrms_settings</code>.</li>
                    <li><strong>Attendance</strong>: <code>hrms_employee_shifts</code>, <code>hrms_kiosk_devices</code>, <code>hrms_attendance_punches</code>, <code>hrms_attendance_days</code>, <code>hrms_attendance_requests</code>, <code>hrms_overtime_approvals</code>, <code>hrms_break_adjustments</code>, <code>hrms_attendance_allowances</code>, <code>hrms_attendance_allowance_entries</code>.</li>
                    <li><strong>Leave (FY {{ currentFy }})</strong>: <code>hrms_leave_types</code>, <code>hrms_fwa_policies</code>, <code>hrms_holidays</code>, <code>hrms_leave_balances</code>, <code>hrms_leave_transactions</code> (immutable ledger), <code>hrms_leave_requests</code>, <code>hrms_fwa_requests</code>, <code>hrms_permission_requests</code>, <code>hrms_comp_offs</code>, <code>hrms_leave_year_end_runs</code>.</li>
                    <li><strong>MySQL Safety</strong>: Explicit short index/FK names (&lt;64 chars) are used on long table names such as <code>hrms_att_allow_ent_rule_fk</code>.</li>
                </ul>
            </CardBox>

            <CardBox v-if="activeTab === 'services'" class="space-y-3 text-sm">
                <h2 class="text-lg font-bold">Domain Services & Cross-Module Pipeline</h2>
                <ul class="list-disc pl-5 space-y-1.5">
                    <li><code>GeofenceService</code>: Haversine distance evaluation with precedence <code>employee.geofence_mode</code> &gt; <code>work_location.geofence_mode</code> (<code>strict</code>, <code>flag</code>, <code>bypass</code>).</li>
                    <li><code>AttendanceCalculationService</code>: Idempotent transaction-wrapped daily rollup resolving roster shifts, overnight shifts, holidays, week-offs, approved leaves/FWA, late/early-out/break/OT minutes, and creating <code>OvertimeApproval</code> records.</li>
                    <li><code>ApprovalRoutingService</code>: Resolves L1 -&gt; optional L2 approvers and automatically routes to active <code>ApprovalDelegation</code> or employee <code>backup_approver_id</code> when primary approver is delegated or on approved leave.</li>
                    <li><code>LeaveBalanceService</code>: Uses <code>lockForUpdate()</code> inside <code>DB::transaction()</code> to prevent negative balances, writes <code>hrms_leave_transactions</code>, runs scheduled accruals, and executes Apr–Mar FY year-end carry-forward/lapse.</li>
                    <li><code>LeaveAttendanceSyncService</code>: Updates <code>hrms_attendance_days</code> when leaves/FWA are approved or cancelled.</li>
                    <li><code>HrmsNotificationService</code>: Dispatches Email, SignageFlow <code>UserNotification</code>, and <code>FirebaseService</code> FCM push alerts and records <code>hrms_notification_logs</code>.</li>
                </ul>
            </CardBox>

            <CardBox v-if="activeTab === 'api'" class="space-y-3 text-sm">
                <h2 class="text-lg font-bold">Mobile REST API (<code>/api/mobile/hrms/*</code>, Sanctum)</h2>
                <ul class="list-disc pl-5 space-y-1.5 font-mono text-xs">
                    <li>POST /api/mobile/login — Extended for active HRMS employees even without task/expense permissions</li>
                    <li>GET  /api/mobile/hrms/attendance/today — Shift, work locations, today summary &amp; punches</li>
                    <li>POST /api/mobile/hrms/attendance/punch — Multipart GPS lat/lng + optional selfie image</li>
                    <li>GET  /api/mobile/hrms/attendance/history — Monthly attendance days</li>
                    <li>GET  /api/mobile/hrms/attendance/requests | POST /api/mobile/hrms/attendance/regularize</li>
                    <li>GET  /api/mobile/hrms/leave/summary — FY balances, holidays, FWA policies</li>
                    <li>GET  /api/mobile/hrms/leave/requests | POST /api/mobile/hrms/leave/requests (multipart)</li>
                    <li>POST /api/mobile/hrms/leave/requests/{id}/cancel | POST /api/mobile/hrms/fwa/requests</li>
                    <li>POST /hrms/kiosk/punch &amp; POST /hrms/kiosk/biometric-webhook — Device-token authenticated</li>
                </ul>
            </CardBox>

            <CardBox v-if="activeTab === 'cli'" class="space-y-3 text-sm">
                <h2 class="text-lg font-bold">Artisan Commands, Seeders & Isolated Tests</h2>
                <pre class="p-3 rounded bg-slate-900 text-slate-100 text-xs overflow-x-auto">
# Seed permissions, presets, and sample test dataset
php artisan db:seed --class="SignageFlow\Hrms\Database\Seeders\HrmsPermissionSeeder"
php artisan db:seed --class="SignageFlow\Hrms\Database\Seeders\HrmsDefaultPresetsSeeder"
php artisan db:seed --class="SignageFlow\Hrms\Database\Seeders\HrmsSampleDataSeeder"

# Scheduled / maintenance commands
php artisan hrms:daily-attendance-rollup --date=2026-11-27
php artisan hrms:accrue-leaves
php artisan hrms:backfill-attendance 2026-11-01 2026-11-27

# Isolated SQLite :memory: test suite &amp; Pint
./vendor/bin/phpunit -c packages/signageflow/hrms/phpunit.xml
./vendor/bin/pint --test packages/signageflow/hrms
cd Mobile &amp;&amp; flutter analyze &amp;&amp; flutter test
                </pre>
            </CardBox>
        </SectionMain>
    </LayoutAuthenticated>
</template>
