<?php

namespace SignageFlow\Hrms\Http\Controllers\Web\Core;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use SignageFlow\Hrms\Models\ApprovalDelegation;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\HrmsSetting;
use SignageFlow\Hrms\Models\NotificationLog;
use SignageFlow\Hrms\Models\NotificationTemplate;

class HrmsAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:hrmsAdmin_list');
    }

    public function index()
    {
        $userClass = config('hrms.models.user');
        $users = $userClass::with('roles')->orderBy('name')->get()->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'roles' => $u->getRoleNames(),
            'hrms_permissions' => $u->getAllPermissions()->pluck('name')->filter(fn ($p) => str_starts_with($p, 'hrms'))->values(),
        ]);

        $auditLogs = class_exists(\App\Models\LogActivity::class)
            ? \App\Models\LogActivity::with('user:id,name')->where('module', 'like', 'hrms%')->latest()->take(50)->get()
            : collect();

        return Inertia::render('Hrms/Core/HrmsAdmin', [
            'userAccessReport' => $users,
            'templates' => NotificationTemplate::orderBy('event_key')->get(),
            'notificationLogs' => NotificationLog::latest()->take(50)->get(),
            'delegations' => ApprovalDelegation::with(['delegator:id,emp_code,full_name', 'backup:id,emp_code,full_name'])->latest()->get(),
            'employees' => Employee::active()->orderBy('full_name')->get(['id', 'emp_code', 'full_name']),
            'settings' => HrmsSetting::orderBy('group')->orderBy('key')->get(),
            'auditLogs' => $auditLogs,
        ]);
    }

    public function updateTemplate(Request $request, NotificationTemplate $template)
    {
        $data = $request->validate([
            'email_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'in_app_enabled' => 'boolean',
            'subject_template' => 'required|string|max:255',
            'body_template' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $template->update($data);

        return back()->with(['message' => 'Notification template updated.', 'msg_type' => 'success']);
    }

    public function storeDelegation(Request $request)
    {
        $data = $request->validate([
            'delegator_employee_id' => 'required|exists:hrms_employees,id',
            'backup_employee_id' => 'required|exists:hrms_employees,id|different:delegator_employee_id',
            'module' => 'required|in:all,attendance,leave,payroll',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:255',
        ]);

        ApprovalDelegation::create($data + ['is_active' => true]);

        return back()->with(['message' => 'Backup Officer delegation saved.', 'msg_type' => 'success']);
    }

    public function saveSetting(Request $request)
    {
        $data = $request->validate([
            'group' => 'required|string|max:50',
            'key' => 'required|string|max:100',
            'value' => 'nullable|string',
            'type' => 'nullable|string|max:20',
        ]);

        HrmsSetting::updateOrCreate(['key' => $data['key']], $data);

        return back()->with(['message' => 'HRMS setting saved.', 'msg_type' => 'success']);
    }
}
