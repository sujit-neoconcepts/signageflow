<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Job;
use App\Models\Outward;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\SalesOrder;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = \Auth::user();
        if (! $user->can('dashboard_view')) {
            abort(403, 'Unauthorized access to dashboard.');
        }

        // Parse date range from request or use current month defaults
        $financialYearStart = Carbon::create(Carbon::now()->year, 4, 1);
        $financialYearEnd = Carbon::create(Carbon::now()->year + 1, 3, 31);

        // If current month is before April, use previous year's financial year
        if (Carbon::now()->month < 4) {
            $financialYearStart = Carbon::create(Carbon::now()->year - 1, 4, 1);
            $financialYearEnd = Carbon::create(Carbon::now()->year, 3, 31);
        }

        // Default to 1st of current month to last day of current month
        $defaultStartDate = Carbon::now()->startOfMonth();
        $defaultEndDate = Carbon::now()->endOfMonth();

        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : $defaultStartDate->copy();
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : $defaultEndDate->copy();

        // Consumables Stock Overview
        $stockOverview = [
            'low_stock_items' => Product::leftJoin('stock_thresholds as st', 'products.pr_detail_int', '=', 'st.pr_detail_int')
                ->whereRaw('(SELECT COALESCE(SUM(pur_qty_int), 0) FROM purchases WHERE pur_pr_detail_int = products.pr_detail_int) - (SELECT COALESCE(SUM(out_qty), 0) FROM outwards WHERE out_product = products.pr_detail_int) < COALESCE(st.threshold_qty, 0)')
                ->count(DB::raw('DISTINCT products.pr_detail_int')),
        ];

        // Expenses Data & Sales Data - filtered by date range
        $totalPurchaseValue = (float) Purchase::whereBetween('pur_date', [$startDate, $endDate])->sum('pur_amnt_total');
        $totalOutwardValue = (float) Outward::whereBetween('out_date', [$startDate, $endDate])->sum(DB::raw('out_qty * unitPrice'));
        $totalSaleValue = (float) SalesOrder::whereBetween('order_date', [$startDate, $endDate])->sum('total_amount');

        // Build expense query with Head Office filter for non-admin users
        $expenseQuery = Expense::whereBetween('exp_date', [$startDate, $endDate])->where('amt_type', 'Expense');
        $depositQuery = Expense::whereBetween('exp_date', [$startDate, $endDate])->where('amt_type', 'Deposit');

        if (! \Auth::user()->hasRole(['super-admin', 'admin'])) {
            $headOfficeFilter = function ($q) {
                $q->whereNull('doneby')
                    ->orWhere('doneby', 'NOT LIKE', '%Head Office%');
            };
            $expenseQuery->where($headOfficeFilter);
            $depositQuery->where($headOfficeFilter);
        }

        $totalExpense = (float) $expenseQuery->sum('amount');
        $totalDeposit = (float) $depositQuery->sum('amount');

        // Fetch expenses by category for the new graph
        $expensesByCategoryQuery = clone $expenseQuery; // Use the same base query with date and role filters
        $expensesByCategory = $expensesByCategoryQuery
            ->select('exp_cate', DB::raw('SUM(amount) as total_value'))
            ->groupBy('exp_cate')
            ->orderByDesc('total_value')
            ->get()
            ->map(function ($item) {
                return [
                    'exp_cate' => $item->exp_cate ?: 'Uncategorized',
                    'total_value' => (float) $item->total_value,
                ];
            });

        $expensesData = [
            'total_purchase_value' => $totalPurchaseValue,
            'total_outward_value' => $totalOutwardValue,
            'total_expense' => $totalExpense,
            'total_deposit' => $totalDeposit,
            'total_sale_value' => $totalSaleValue,
        ];

        // Monthly Purchase/Outward Trend - always last 12 months including current month (ignores date filter)
        $monthlyTrend = [];
        $trendStart = Carbon::now()->startOfMonth()->subMonths(11);
        for ($i = 0; $i < 12; $i++) {
            $loopMonth = $trendStart->copy()->addMonths($i);
            $monthStart = $loopMonth->copy()->startOfMonth();
            $monthEnd = $loopMonth->copy()->endOfMonth();

            $monthlyTrend[] = [
                'month' => $loopMonth->format('M Y'),
                'purchases' => (float) Purchase::whereBetween('pur_date', [$monthStart, $monthEnd])->sum('pur_amnt_total'),
                'outwards' => (float) Outward::whereBetween('out_date', [$monthStart, $monthEnd])->sum(DB::raw('out_qty * IFNULL(unitPrice, 0)')),
                'sales_cabinet' => (float) DB::table('sales_orders')->where('product_type', 'cabinet')->whereBetween('order_date', [$monthStart, $monthEnd])->sum('total_amount'),
                'sales_letters' => (float) DB::table('sales_orders')->where('product_type', 'letters')->whereBetween('order_date', [$monthStart, $monthEnd])->sum('total_amount'),
                'sales_signage' => (float) DB::table('sales_orders')->where('product_type', 'signage')->whereBetween('order_date', [$monthStart, $monthEnd])->sum('total_amount'),
            ];
        }

        // Top 10 Suppliers by Purchase Value - filtered by date range
        $topSuppliers = Purchase::select('pur_supplier as supplier_name', DB::raw('SUM(pur_amnt_total) as total_value'))
            ->whereNotNull('pur_supplier')
            ->whereBetween('pur_date', [$startDate, $endDate])
            ->groupBy('pur_supplier')
            ->orderByDesc('total_value')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->supplier_name,
                    'total_value' => (float) $item->total_value,
                ];
            });

        // Task & Workflow Dashboard Info
        $authId = \Auth::id();

        // 1. Metric Counts
        $activeJobsCount = Job::whereIn('status', ['not_started', 'in_progress'])->count();

        $myActiveTasksCount = Task::whereHas('assignees', function ($q) use ($authId) {
            $q->where('users.id', $authId)->whereIn('task_assignees.status', ['pending', 'accepted', 'in_progress']);
        })->count();

        $tasksPendingVerificationCount = Task::where('status', 'completed')->count();

        $overdueTasksCount = Task::whereNotIn('status', ['completed', 'verified', 'closed'])
            ->where('due_date', '<', Carbon::now())
            ->count();

        // 2. My Active Tasks List (Limit 5)
        $myActiveTasks = Task::whereHas('assignees', function ($q) use ($authId) {
            $q->where('users.id', $authId)->whereIn('task_assignees.status', ['pending', 'accepted', 'in_progress']);
        })
            ->with(['job', 'creator'])
            ->orderBy('due_date', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($task) use ($authId) {
                $pivot = $task->assignees->firstWhere('id', $authId)->pivot;

                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'job_name' => $task->job ? $task->job->title : null,
                    'status' => $pivot ? $pivot->status : $task->status,
                    'due_date' => $task->due_date->format('d-m-Y H:i'),
                    'start_date' => $task->start_date ? $task->start_date->format('d-m-Y H:i') : null,
                ];
            });

        // 3. Tasks Pending Verification List (Limit 5)
        $pendingVerificationTasks = [];
        if (\Auth::user()->hasRole(['super-admin', 'admin', 'supervisor']) || \Auth::user()->can('task_list')) {
            $pendingVerificationTasks = Task::where('status', 'completed')
                ->with(['job', 'assignees'])
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'job_name' => $task->job ? $task->job->title : null,
                        'assignee_names' => $task->assignees->pluck('name')->join(', '),
                        'due_date' => $task->due_date->format('d-m-Y H:i'),
                    ];
                });
        }

        // 4. Active Jobs List (Limit 5)
        $activeJobsList = Job::whereIn('status', ['not_started', 'in_progress'])
            ->with(['tasks'])
            ->orderBy('due_date', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($job) {
                $totalTasks = $job->tasks->count();
                $completedTasks = $job->tasks->whereIn('status', ['completed', 'verified', 'closed'])->count();
                $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

                return [
                    'id' => $job->id,
                    'title' => $job->title,
                    'status' => $job->status,
                    'progress' => (int) $progress,
                    'due_date' => $job->due_date->format('d-m-Y H:i'),
                ];
            });

        $can = [
            'view_stock_metrics' => $user->can('dashboard_viewStockMetrics'),
            'view_purchase_metrics' => $user->can('dashboard_viewPurchaseMetrics'),
            'view_expense_metrics' => $user->can('dashboard_viewExpenseMetrics'),
            'view_outward_metrics' => $user->can('dashboard_viewOutwardMetrics'),
            'view_job_metrics' => $user->can('dashboard_viewJobMetrics'),
            'view_task_metrics' => $user->can('dashboard_viewTaskMetrics'),
            'view_my_tasks' => $user->can('dashboard_viewMyTasks'),
            'view_sales_metrics' => $user->can('salesOrder_list'),
        ];

        return Inertia::render('Admin/DashboardView', [
            'stockOverview' => $stockOverview,
            'financialYear' => [
                'start' => $financialYearStart->format('Y-m-d'),
                'end' => $financialYearEnd->format('Y-m-d'),
            ],
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'expensesData' => $expensesData,
            'monthlyTrend' => $monthlyTrend,
            'topSuppliers' => $topSuppliers,
            'expensesByCategory' => $expensesByCategory,
            'taskStats' => [
                'active_jobs_count' => $activeJobsCount,
                'my_active_tasks_count' => $myActiveTasksCount,
                'tasks_pending_verification_count' => $tasksPendingVerificationCount,
                'overdue_tasks_count' => $overdueTasksCount,
            ],
            'myActiveTasks' => $myActiveTasks,
            'pendingVerificationTasks' => $pendingVerificationTasks,
            'activeJobsList' => $activeJobsList,
            'can' => $can,
        ]);
    }

    public function expensesDetails(Request $request)
    {
        $user = \Auth::user();
        if (! $user->can('dashboard_viewExpenseMetrics')) {
            abort(403, 'Unauthorized access to dashboard expense details.');
        }

        // Parse date range from request or use financial year defaults
        $financialYearStart = Carbon::create(Carbon::now()->year, 4, 1);
        $financialYearEnd = Carbon::create(Carbon::now()->year + 1, 3, 31);

        if (Carbon::now()->month < 4) {
            $financialYearStart = Carbon::create(Carbon::now()->year - 1, 4, 1);
            $financialYearEnd = Carbon::create(Carbon::now()->year, 3, 31);
        }

        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : $financialYearStart->copy()->startOfDay();
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : $financialYearEnd->copy()->endOfDay();

        // Purchase Expenses by Sub Group - filtered by date range
        $purchaseExpenses = Purchase::join('products', 'purchases.pur_pr_id', '=', 'products.id')
            ->join('pgroups', 'products.groupinfo', '=', 'pgroups.id')
            ->whereBetween('purchases.pur_date', [$startDate, $endDate])
            ->select('pgroups.sgroup', DB::raw('SUM(purchases.pur_amnt_total) as total_value'))
            ->groupBy('pgroups.sgroup')
            ->get()
            ->map(function ($item) {
                return [
                    'sgroup' => $item->sgroup,
                    'total_value' => (float) $item->total_value,
                ];
            });

        // Outward Expenses by Sub Group - filtered by date range
        $outwardExpenses = Outward::join('pgroups', 'outwards.out_product_group', '=', 'pgroups.name')
            ->whereBetween('outwards.out_date', [$startDate, $endDate])
            ->select('pgroups.sgroup', DB::raw('SUM(outwards.out_qty * outwards.unitPrice) as total_value'))
            ->groupBy('pgroups.sgroup')
            ->get()
            ->map(function ($item) {
                return [
                    'sgroup' => $item->sgroup,
                    'total_value' => (float) $item->total_value,
                ];
            });

        // Expenses by Category - filtered by date range
        $expensesByCategoryQuery = Expense::whereBetween('exp_date', [$startDate, $endDate])
            ->where('amt_type', 'Expense');

        if (! \Auth::user()->hasRole(['super-admin', 'admin'])) {
            $expensesByCategoryQuery->where(function ($q) {
                $q->whereNull('doneby')
                    ->orWhere('doneby', 'NOT LIKE', '%Head Office%');
            });
        }

        $expensesByCategory = $expensesByCategoryQuery
            ->select('exp_cate', DB::raw('SUM(amount) as total_value'))
            ->groupBy('exp_cate')
            ->get()
            ->map(function ($item) {
                return [
                    'exp_cate' => $item->exp_cate,
                    'total_value' => (float) $item->total_value,
                ];
            });

        // Deposits by Category - filtered by date range
        $depositsByCategoryQuery = Expense::whereBetween('exp_date', [$startDate, $endDate])
            ->where('amt_type', 'Deposit');

        if (! \Auth::user()->hasRole(['super-admin', 'admin'])) {
            $depositsByCategoryQuery->where(function ($q) {
                $q->whereNull('doneby')
                    ->orWhere('doneby', 'NOT LIKE', '%Head Office%');
            });
        }

        $depositsByCategory = $depositsByCategoryQuery
            ->select('exp_cate', DB::raw('SUM(amount) as total_value'))
            ->groupBy('exp_cate')
            ->get()
            ->map(function ($item) {
                return [
                    'exp_cate' => $item->exp_cate,
                    'total_value' => (float) $item->total_value,
                ];
            });

        return Inertia::render('Admin/ExpensesDetailsView', [
            'purchaseExpenses' => $purchaseExpenses,
            'outwardExpenses' => $outwardExpenses,
            'expensesByCategory' => $expensesByCategory,
            'depositsByCategory' => $depositsByCategory,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
        ]);
    }
}
