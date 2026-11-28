<?php

namespace SignageFlow\Hrms\Services;

use Carbon\Carbon;
use SignageFlow\Hrms\Models\ApprovalDelegation;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\LeaveRequest;

class ApprovalRoutingService
{
    /**
     * Resolve effective approver for a given employee & approval level,
     * falling back to active Backup Officer if the primary approver is delegated or on approved leave.
     *
     * @return array{approver: ?Employee, via_backup: bool}
     */
    public function resolveApprover(Employee $subject, int $level = 1, string $module = 'all', ?Carbon $onDate = null): array
    {
        $date = ($onDate ?? now())->toDateString();
        $primary = $level === 1 ? $subject->l1Approver : $subject->l2Approver;

        if (! $primary) {
            return ['approver' => null, 'via_backup' => false];
        }

        // Check active explicit delegation
        $delegation = ApprovalDelegation::query()
            ->where('delegator_employee_id', $primary->id)
            ->where('is_active', true)
            ->whereIn('module', ['all', $module])
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->with('backup')
            ->first();

        if ($delegation && $delegation->backup) {
            return ['approver' => $delegation->backup, 'via_backup' => true];
        }

        // Check if primary approver is on approved leave today -> route to Backup Officer
        $onLeave = LeaveRequest::query()
            ->where('employee_id', $primary->id)
            ->where('approval_status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();

        if ($onLeave) {
            $backup = $primary->backupApprover ?? $subject->backupApprover;
            if ($backup) {
                return ['approver' => $backup, 'via_backup' => true];
            }
        }

        return ['approver' => $primary, 'via_backup' => false];
    }

    /**
     * Check if an actor (User / Employee) is authorized to act on a request assigned to $currentApprover.
     *
     * @return array{authorized: bool, via_backup: bool}
     */
    public function canActOn($user, ?Employee $currentApprover, ?Employee $subject = null, string $module = 'all'): array
    {
        if (! $user) {
            return ['authorized' => false, 'via_backup' => false];
        }

        $superAdminRole = env('APP_SUPER_ADMIN', 'super-admin');
        if (method_exists($user, 'hasRole') && $user->hasRole($superAdminRole)) {
            return ['authorized' => true, 'via_backup' => false];
        }

        $actorEmployee = Employee::where('user_id', $user->id)->first();
        if (! $actorEmployee) {
            return ['authorized' => false, 'via_backup' => false];
        }

        if ($currentApprover && $actorEmployee->id === $currentApprover->id) {
            return ['authorized' => true, 'via_backup' => false];
        }

        // Backup officer check
        if ($currentApprover) {
            $today = now()->toDateString();
            $delegated = ApprovalDelegation::query()
                ->where('delegator_employee_id', $currentApprover->id)
                ->where('backup_employee_id', $actorEmployee->id)
                ->where('is_active', true)
                ->whereIn('module', ['all', $module])
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->exists();

            if ($delegated || $currentApprover->backup_approver_id === $actorEmployee->id) {
                return ['authorized' => true, 'via_backup' => true];
            }
        }

        if ($subject && $subject->backup_approver_id === $actorEmployee->id) {
            return ['authorized' => true, 'via_backup' => true];
        }

        return ['authorized' => false, 'via_backup' => false];
    }
}
