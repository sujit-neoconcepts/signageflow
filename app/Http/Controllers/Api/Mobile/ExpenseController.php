<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Expcate;
use App\Models\Expense;
use App\Models\Expuser;
use App\Models\MobileDeviceToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeExpenseAccess($request, 'expense_list');

        $request->validate([
            'search' => 'nullable|string',
            'type' => 'nullable|in:Expense,Deposit',
            'category' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $startDate = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : now()->startOfDay();
        $endDate = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfDay();

        $baseBefore = $this->visibleExpenseQuery($request)
            ->where('exp_date', '<', $startDate);
        $baseDuring = $this->visibleExpenseQuery($request)
            ->whereBetween('exp_date', [$startDate, $endDate]);

        if ($request->filled('category')) {
            $baseBefore->where('exp_cate', $request->category);
            $baseDuring->where('exp_cate', $request->category);
        }

        $opening = (clone $baseBefore)->where('amt_type', 'Deposit')->sum('amount')
            - (clone $baseBefore)->where('amt_type', 'Expense')->sum('amount');
        $deposit = (clone $baseDuring)->where('amt_type', 'Deposit')->sum('amount');
        $expense = (clone $baseDuring)->where('amt_type', 'Expense')->sum('amount');

        $listQuery = $this->visibleExpenseQuery($request)
            ->with(['job:id,title', 'task:id,title'])
            ->when($request->filled('type'), fn ($query) => $query->where('amt_type', $request->type))
            ->when($request->filled('category'), fn ($query) => $query->where('exp_cate', $request->category))
            ->whereBetween('exp_date', [$startDate, $endDate])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($q) use ($search) {
                    $q->where('exp_cate', 'like', "%{$search}%")
                        ->orWhere('details', 'like', "%{$search}%")
                        ->orWhere('job_details', 'like', "%{$search}%")
                        ->orWhere('job_no', 'like', "%{$search}%")
                        ->orWhere('doneby', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('exp_date')
            ->orderByDesc('id');

        return response()->json([
            'summary' => [
                'opening' => round((float) $opening, 2),
                'deposit' => round((float) $deposit, 2),
                'expense' => round((float) $expense, 2),
                'closing' => round((float) ($opening + $deposit - $expense), 2),
            ],
            'expenses' => $listQuery
                ->paginate((int) $request->query('per_page', 25))
                ->through(fn (Expense $expense) => $this->formatExpense($expense)),
        ]);
    }

    public function meta(Request $request)
    {
        $this->authorizeExpenseAccess($request, 'expense_list');

        return response()->json([
            'categories' => Expcate::select('name')->orderBy('name')->pluck('name'),
            'done_by' => Expuser::getAllOption(),
            'incharges' => User::select('name')->orderBy('name')->pluck('name'),
            'can_add_for_all' => $request->user()->can('all') || $request->user()->can('expense_add_for_all'),
            'can_list_for_all' => $request->user()->can('all') || $request->user()->can('expense_list_for_all'),
            'can_back_date_entry' => $request->user()->can('expense_back_date_entry'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeExpenseAccess($request, 'expense_list');

        $request->validate([
            'exp_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'amt_type' => 'required|in:Expense,Deposit',
            'exp_cate' => 'required|string',
            'job_details' => 'required|string',
            'details' => 'nullable|string',
            'job_no' => 'nullable|string',
            'doneby' => 'nullable|array',
            'incharge' => 'nullable|string',
        ]);

        if (strtotime($request->exp_date) < strtotime('-2 days') && ! $request->user()->can('expense_back_date_entry')) {
            throw ValidationException::withMessages(['exp_date' => 'The Expense Date cannot be older than 2 days.']);
        }

        $doneBy = collect($request->input('doneby', []))->map(function ($item) {
            return is_array($item) ? ($item['id'] ?? null) : $item;
        })->filter()->implode(',');

        $canAddForAll = $request->user()->can('all') || $request->user()->can('expense_add_for_all');
        $expense = Expense::create([
            'exp_date' => date('Y-m-d', strtotime($request->exp_date)),
            'amount' => $request->amount,
            'amt_type' => $request->amt_type,
            'exp_cate' => $request->exp_cate,
            'details' => $request->details,
            'job_details' => $request->job_details,
            'incharge' => $canAddForAll && $request->filled('incharge') ? $request->incharge : $request->user()->name,
            'doneby' => $doneBy,
            'job_no' => $request->job_no,
        ]);

        \ActivityLog::add([
            'action' => 'added',
            'module' => 'expense',
            'data_key' => $expense->exp_cate.' (Mobile)',
        ]);

        return response()->json([
            'message' => 'Expense created successfully.',
            'expense' => $this->formatExpense($expense),
        ], 201);
    }

    public function storeFcmToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'platform' => 'nullable|string|max:50',
        ]);

        MobileDeviceToken::updateOrCreate([
            'user_id' => $request->user()->id,
            'token' => $request->token,
        ], [
            'platform' => $request->platform,
            'last_seen_at' => now(),
        ]);

        return response()->json(['message' => 'Notification token synced.']);
    }

    private function authorizeExpenseAccess(Request $request, string $permission): void
    {
        if (! $request->user()->tokenCan('mobile:tasks') || ! $request->user()->can($permission)) {
            abort(403, 'Unauthorized mobile expense access.');
        }
    }

    private function visibleExpenseQuery(Request $request)
    {
        $query = Expense::query();

        if (! ($request->user()->can('all') || $request->user()->can('expense_list_for_all'))) {
            $query->where('incharge', $request->user()->name);
        }

        if (! $request->user()->hasRole(['super-admin', 'admin'])) {
            $query->where(function ($q) {
                $q->whereNull('doneby')
                    ->orWhere('doneby', 'NOT LIKE', '%Head Office%');
            });
        }

        return $query;
    }

    private function formatExpense(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'exp_date' => date('d-m-Y', strtotime($expense->exp_date)),
            'amount' => number_format((float) $expense->amount, 2),
            'signed_amount' => $expense->amt_type === 'Expense' ? -1 * (float) $expense->amount : (float) $expense->amount,
            'amt_type' => $expense->amt_type,
            'exp_cate' => $expense->exp_cate,
            'doneby' => $expense->doneby,
            'details' => $expense->details,
            'job_details' => $expense->job_details,
            'job_no' => $expense->job_no,
            'incharge' => $expense->incharge,
            'job_task' => trim(($expense->job?->title ?? '').($expense->task ? ' / '.$expense->task->title : '')),
            'is_task_linked' => (bool) $expense->task_id,
        ];
    }
}
