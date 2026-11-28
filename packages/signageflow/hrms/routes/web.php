<?php

use Illuminate\Support\Facades\Route;
use SignageFlow\Hrms\Http\Controllers\Web\Attendance\AttendanceAdjustmentController;
use SignageFlow\Hrms\Http\Controllers\Web\Attendance\AttendanceDashboardController;
use SignageFlow\Hrms\Http\Controllers\Web\Attendance\AttendanceMaintenanceController;
use SignageFlow\Hrms\Http\Controllers\Web\Attendance\AttendanceReportController;
use SignageFlow\Hrms\Http\Controllers\Web\Attendance\AttendanceSetupController;
use SignageFlow\Hrms\Http\Controllers\Web\Core\EmployeeController;
use SignageFlow\Hrms\Http\Controllers\Web\Core\HrmsAdminController;
use SignageFlow\Hrms\Http\Controllers\Web\Core\HrmsDocsController;
use SignageFlow\Hrms\Http\Controllers\Web\Core\OrgHierarchyController;
use SignageFlow\Hrms\Http\Controllers\Web\Leave\LeaveActionController;
use SignageFlow\Hrms\Http\Controllers\Web\Leave\LeaveApprovalController;
use SignageFlow\Hrms\Http\Controllers\Web\Leave\LeaveDashboardController;
use SignageFlow\Hrms\Http\Controllers\Web\Leave\LeavePolicyController;
use SignageFlow\Hrms\Http\Controllers\Web\Leave\LeaveReportController;
use SignageFlow\Hrms\Http\Controllers\Web\Payroll\BankTallyController;
use SignageFlow\Hrms\Http\Controllers\Web\Payroll\ClaimController;
use SignageFlow\Hrms\Http\Controllers\Web\Payroll\LoanController;
use SignageFlow\Hrms\Http\Controllers\Web\Payroll\PayrollReportController;
use SignageFlow\Hrms\Http\Controllers\Web\Payroll\PayrollSetupController;
use SignageFlow\Hrms\Http\Controllers\Web\Payroll\PayRunController;

// Shared Core & Org Hierarchy
Route::get('org-hierarchy', [OrgHierarchyController::class, 'index'])->name('org.index');
Route::post('org-hierarchy/{type}', [OrgHierarchyController::class, 'store'])->name('org.store');
Route::put('org-hierarchy/{type}/{id}', [OrgHierarchyController::class, 'update'])->name('org.update');
Route::delete('org-hierarchy/{type}/{id}', [OrgHierarchyController::class, 'destroy'])->name('org.destroy');

// Employee Master
Route::get('employees/validation', [EmployeeController::class, 'validation'])->name('employees.validation');
Route::get('employees/import', [EmployeeController::class, 'importView'])->name('employees.import');
Route::post('employees/import', [EmployeeController::class, 'import'])->name('employees.import.store');
Route::get('employees/export', [EmployeeController::class, 'export'])->name('employees.export');
Route::post('employees/bulk-update', [EmployeeController::class, 'bulkUpdate'])->name('employees.bulkUpdate');
Route::resource('employees', EmployeeController::class)->except(['show']);

// HRMS Administration
Route::get('admin', [HrmsAdminController::class, 'index'])->name('admin.index');
Route::put('admin/templates/{template}', [HrmsAdminController::class, 'updateTemplate'])->name('admin.templates.update');
Route::post('admin/delegations', [HrmsAdminController::class, 'storeDelegation'])->name('admin.delegations.store');
Route::post('admin/settings', [HrmsAdminController::class, 'saveSetting'])->name('admin.settings.save');

// Attendance Management
Route::get('dashboard', [AttendanceDashboardController::class, 'index'])->name('dashboard');
Route::get('attendance/dashboard', [AttendanceDashboardController::class, 'index'])->name('attendance.dashboard');
Route::get('attendance/dashboard/export', [AttendanceDashboardController::class, 'exportDaily'])->name('attendance.dashboard.export');

Route::get('attendance/setup', [AttendanceSetupController::class, 'index'])->name('attendance.setup');
Route::post('attendance/shifts', [AttendanceSetupController::class, 'storeShift'])->name('attendance.shifts.store');
Route::put('attendance/shifts/{shift}', [AttendanceSetupController::class, 'updateShift'])->name('attendance.shifts.update');
Route::delete('attendance/shifts/{shift}', [AttendanceSetupController::class, 'destroyShift'])->name('attendance.shifts.destroy');
Route::post('attendance/work-locations', [AttendanceSetupController::class, 'storeWorkLocation'])->name('attendance.workLocations.store');
Route::put('attendance/work-locations/{workLocation}', [AttendanceSetupController::class, 'updateWorkLocation'])->name('attendance.workLocations.update');
Route::delete('attendance/work-locations/{workLocation}', [AttendanceSetupController::class, 'destroyWorkLocation'])->name('attendance.workLocations.destroy');
Route::post('attendance/kiosks', [AttendanceSetupController::class, 'storeKiosk'])->name('attendance.kiosks.store');
Route::put('attendance/kiosks/{kiosk}', [AttendanceSetupController::class, 'updateKiosk'])->name('attendance.kiosks.update');
Route::post('attendance/kiosks/{kiosk}/toggle', [AttendanceSetupController::class, 'toggleKiosk'])->name('attendance.kiosks.toggle');
Route::delete('attendance/kiosks/{kiosk}', [AttendanceSetupController::class, 'destroyKiosk'])->name('attendance.kiosks.destroy');
Route::post('attendance/rosters', [AttendanceSetupController::class, 'storeRoster'])->name('attendance.rosters.store');
Route::put('attendance/rosters/{roster}', [AttendanceSetupController::class, 'updateRoster'])->name('attendance.rosters.update');
Route::delete('attendance/rosters/{roster}', [AttendanceSetupController::class, 'destroyRoster'])->name('attendance.rosters.destroy');
Route::post('attendance/allowances', [AttendanceSetupController::class, 'storeAllowance'])->name('attendance.allowances.store');
Route::put('attendance/allowances/{allowance}', [AttendanceSetupController::class, 'updateAllowance'])->name('attendance.allowances.update');
Route::delete('attendance/allowances/{allowance}', [AttendanceSetupController::class, 'destroyAllowance'])->name('attendance.allowances.destroy');

Route::get('attendance/maintenance', [AttendanceMaintenanceController::class, 'index'])->name('attendance.maintenance');
Route::post('attendance/maintenance/global-change', [AttendanceMaintenanceController::class, 'globalChange'])->name('attendance.maintenance.globalChange');
Route::post('attendance/maintenance/backfill', [AttendanceMaintenanceController::class, 'backfillMissingDates'])->name('attendance.maintenance.backfill');
Route::post('attendance/maintenance/import-biometric', [AttendanceMaintenanceController::class, 'importBiometric'])->name('attendance.maintenance.importBiometric');

Route::get('attendance/adjustments', [AttendanceAdjustmentController::class, 'index'])->name('attendance.adjustments');
Route::post('attendance/adjustments/apply', [AttendanceAdjustmentController::class, 'applyAdjustment'])->name('attendance.adjustments.apply');
Route::post('attendance/requests/{attendanceRequest}/act', [AttendanceAdjustmentController::class, 'actOnRequest'])->name('attendance.requests.act');
Route::post('attendance/ot/{otApproval}/act', [AttendanceAdjustmentController::class, 'actOnOt'])->name('attendance.ot.act');
Route::post('attendance/days/{attendanceDay}/break', [AttendanceAdjustmentController::class, 'adjustBreak'])->name('attendance.days.break');
Route::post('attendance/allowances/compute', [AttendanceAdjustmentController::class, 'computeAllowances'])->name('attendance.allowances.compute');
Route::post('attendance/allowances/{entry}/approve', [AttendanceAdjustmentController::class, 'approveAllowance'])->name('attendance.allowances.approve');

Route::get('attendance/reports', [AttendanceReportController::class, 'index'])->name('attendance.reports');
Route::get('attendance/reports/export', [AttendanceReportController::class, 'export'])->name('attendance.reports.export');

// Leave Management
Route::get('leave/dashboard', [LeaveDashboardController::class, 'index'])->name('leave.dashboard');
Route::get('leave/setup', [LeavePolicyController::class, 'index'])->name('leave.setup');
Route::post('leave/types', [LeavePolicyController::class, 'storeLeaveType'])->name('leave.types.store');
Route::put('leave/types/{leaveType}', [LeavePolicyController::class, 'updateLeaveType'])->name('leave.types.update');
Route::delete('leave/types/{leaveType}', [LeavePolicyController::class, 'destroyLeaveType'])->name('leave.types.destroy');
Route::post('leave/fwa-policies', [LeavePolicyController::class, 'storeFwaPolicy'])->name('leave.fwaPolicies.store');
Route::put('leave/fwa-policies/{fwaPolicy}', [LeavePolicyController::class, 'updateFwaPolicy'])->name('leave.fwaPolicies.update');
Route::delete('leave/fwa-policies/{fwaPolicy}', [LeavePolicyController::class, 'destroyFwaPolicy'])->name('leave.fwaPolicies.destroy');
Route::post('leave/holidays', [LeavePolicyController::class, 'storeHoliday'])->name('leave.holidays.store');
Route::put('leave/holidays/{holiday}', [LeavePolicyController::class, 'updateHoliday'])->name('leave.holidays.update');
Route::delete('leave/holidays/{holiday}', [LeavePolicyController::class, 'destroyHoliday'])->name('leave.holidays.destroy');

Route::get('leave/approvals', [LeaveApprovalController::class, 'index'])->name('leave.approvals');
Route::post('leave/requests/{leaveRequest}/act', [LeaveApprovalController::class, 'act'])->name('leave.requests.act');
Route::post('leave/fwa/{fwaRequest}/act', [LeaveApprovalController::class, 'actFwa'])->name('leave.fwa.act');

Route::get('leave/actions', [LeaveActionController::class, 'index'])->name('leave.actions');
Route::post('leave/actions/apply-on-behalf', [LeaveActionController::class, 'applyOnBehalf'])->name('leave.actions.applyOnBehalf');
Route::post('leave/actions/batch', [LeaveActionController::class, 'batchApply'])->name('leave.actions.batch');
Route::post('leave/actions/comp-off', [LeaveActionController::class, 'addCompOff'])->name('leave.actions.compOff');
Route::post('leave/actions/statutory', [LeaveActionController::class, 'activateStatutory'])->name('leave.actions.statutory');
Route::post('leave/actions/adjust', [LeaveActionController::class, 'adjustBalance'])->name('leave.actions.adjust');
Route::post('leave/actions/recalculate', [LeaveActionController::class, 'recalculateBalance'])->name('leave.actions.recalculate');
Route::post('leave/actions/import', [LeaveActionController::class, 'importLeave'])->name('leave.actions.import');
Route::post('leave/actions/year-end', [LeaveActionController::class, 'runYearEnd'])->name('leave.actions.yearEnd');

Route::get('leave/reports', [LeaveReportController::class, 'index'])->name('leave.reports');
Route::get('leave/reports/export', [LeaveReportController::class, 'export'])->name('leave.reports.export');

// In-App Documentation
Route::get('docs/user-guide', [HrmsDocsController::class, 'userGuide'])->name('docs.userGuide');
Route::get('docs/developer-guide', [HrmsDocsController::class, 'developerGuide'])->name('docs.developerGuide');

// Phase 2: Payroll Setup & Revisions
Route::prefix('payroll')->name('payroll.')->group(function () {
    Route::get('setup', [PayrollSetupController::class, 'index'])->name('setup');
    Route::post('setup/components', [PayrollSetupController::class, 'storeComponent'])->name('setup.components.store');
    Route::put('setup/components/{component}', [PayrollSetupController::class, 'updateComponent'])->name('setup.components.update');
    Route::delete('setup/components/{component}', [PayrollSetupController::class, 'destroyComponent'])->name('setup.components.destroy');
    Route::post('setup/pay-groups', [PayrollSetupController::class, 'storePayGroup'])->name('setup.payGroups.store');
    Route::put('setup/pay-groups/{payGroup}', [PayrollSetupController::class, 'updatePayGroup'])->name('setup.payGroups.update');
    Route::delete('setup/pay-groups/{payGroup}', [PayrollSetupController::class, 'destroyPayGroup'])->name('setup.payGroups.destroy');
    Route::post('setup/statutory', [PayrollSetupController::class, 'saveStatutory'])->name('setup.statutory.save');
    Route::post('setup/structures', [PayrollSetupController::class, 'storeStructure'])->name('setup.structures.store');
    Route::post('setup/revisions', [PayrollSetupController::class, 'storeRevision'])->name('setup.revisions.store');
    Route::post('setup/revisions/import', [PayrollSetupController::class, 'importRevisions'])->name('setup.revisions.import');

    // 3-Stage PayRun Processing Engine
    Route::get('payruns', [PayRunController::class, 'index'])->name('payruns');
    Route::post('payruns', [PayRunController::class, 'store'])->name('payruns.store');
    Route::get('payruns/{payRun}', [PayRunController::class, 'show'])->name('payruns.show');
    Route::post('payruns/{payRun}/initialize', [PayRunController::class, 'initialize'])->name('payruns.initialize');
    Route::put('payruns/items/{payRunItem}', [PayRunController::class, 'updateItem'])->name('payruns.items.update');
    Route::delete('payruns/items/{payRunItem}', [PayRunController::class, 'destroyItem'])->name('payruns.items.destroy');
    Route::post('payruns/{payRun}/import-overrides', [PayRunController::class, 'importOverrides'])->name('payruns.importOverrides');
    Route::post('payruns/{payRun}/submit', [PayRunController::class, 'submit'])->name('payruns.submit');
    Route::post('payruns/{payRun}/approve', [PayRunController::class, 'approve'])->name('payruns.approve');
    Route::post('payruns/{payRun}/publish', [PayRunController::class, 'publish'])->name('payruns.publish');
    Route::get('payruns/items/{payRunItem}/payslip', [PayRunController::class, 'downloadPayslip'])->name('payruns.items.payslip');
    Route::get('payruns/{payRun}/export-bank', [PayRunController::class, 'exportBank'])->name('payruns.exportBank');
    Route::post('payruns/{payRun}/create-gl', [PayRunController::class, 'createGl'])->name('payruns.createGl');
    Route::get('payruns/{payRun}/export-tally-xml', [PayRunController::class, 'exportTallyXml'])->name('payruns.exportTallyXml');
    Route::get('payruns/{payRun}/export-gl-excel', [PayRunController::class, 'exportGlExcel'])->name('payruns.exportGlExcel');

    // Loans
    Route::get('loans', [LoanController::class, 'index'])->name('loans');
    Route::post('loans', [LoanController::class, 'store'])->name('loans.store');
    Route::post('loans/{loan}/toggle-status', [LoanController::class, 'toggleStatus'])->name('loans.toggleStatus');
    Route::post('loans/{loan}/preclose', [LoanController::class, 'preclose'])->name('loans.preclose');

    // E-Claims
    Route::get('claims', [ClaimController::class, 'index'])->name('claims');
    Route::post('claims', [ClaimController::class, 'store'])->name('claims.store');
    Route::post('claims/{claim}/act', [ClaimController::class, 'act'])->name('claims.act');
    Route::get('claims/{claim}/receipt', [ClaimController::class, 'downloadReceipt'])->name('claims.receipt');

    // Bank Submission & Tally GL
    Route::get('bank-tally', [BankTallyController::class, 'index'])->name('bankTally');
    Route::get('bank-tally/{payRun}/bank-file', [BankTallyController::class, 'downloadBankFile'])->name('bankTally.bankFile');
    Route::post('bank-tally/{payRun}/gl-batch', [BankTallyController::class, 'createGlBatch'])->name('bankTally.createGl');
    Route::get('bank-tally/gl/{glBatch}/tally-xml', [BankTallyController::class, 'downloadTallyXml'])->name('bankTally.tallyXml');
    Route::get('bank-tally/gl/{glBatch}/excel', [BankTallyController::class, 'downloadGlExcel'])->name('bankTally.glExcel');

    // Core Payroll Reports
    Route::get('reports', [PayrollReportController::class, 'index'])->name('reports');
    Route::get('reports/export', [PayrollReportController::class, 'export'])->name('reports.export');
});
