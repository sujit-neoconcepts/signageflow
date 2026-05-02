<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\Supplier;
use App\Models\User;
use App\Models\LogActivity;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\Purchase;
use App\Models\Outward;
use App\Models\Pgroup;
use App\Models\Product;
use App\Models\Expense;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
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
            'total_products' => Product::count(),
            'low_stock_items' => Product::leftJoin('stock_thresholds as st', 'products.pr_detail_int', '=', 'st.pr_detail_int')
                ->whereRaw('(SELECT COALESCE(SUM(pur_qty_int), 0) FROM purchases WHERE pur_pr_detail_int = products.pr_detail_int) - (SELECT COALESCE(SUM(out_qty), 0) FROM outwards WHERE out_product = products.pr_detail_int) < COALESCE(st.threshold_qty, 0)')
                ->count(DB::raw('DISTINCT products.pr_detail_int')),
        ];

        // Expenses Data - filtered by date range
        $totalPurchaseValue = (float) Purchase::whereBetween('pur_date', [$startDate, $endDate])->sum('pur_amnt_total');
        $totalOutwardValue = (float) Outward::whereBetween('out_date', [$startDate, $endDate])->sum(DB::raw('out_qty * unitPrice'));
        
        // Build expense query with Head Office filter for non-admin users
        $expenseQuery = Expense::whereBetween('exp_date', [$startDate, $endDate])->where('amt_type', 'Expense');
        $depositQuery = Expense::whereBetween('exp_date', [$startDate, $endDate])->where('amt_type', 'Deposit');
        
        if (!\Auth::user()->hasRole(['super-admin', 'admin'])) {
            $headOfficeFilter = function($q) {
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
        ];

        // Monthly Purchase/Outward Trend - always last 12 months including current month (ignores date filter)
        $monthlyTrend = [];
        $trendStart = Carbon::now()->startOfMonth()->subMonths(11);
        for ($i = 0; $i < 12; $i++) {
            $loopMonth = $trendStart->copy()->addMonths($i);
            $monthStart = $loopMonth->copy()->startOfMonth();
            $monthEnd   = $loopMonth->copy()->endOfMonth();

            $monthlyTrend[] = [
                'month'     => $loopMonth->format('M Y'),
                'purchases' => (float) Purchase::whereBetween('pur_date', [$monthStart, $monthEnd])->sum('pur_amnt_total'),
                'outwards'  => (float) Outward::whereBetween('out_date', [$monthStart, $monthEnd])->sum(DB::raw('out_qty * IFNULL(unitPrice, 0)')),
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
        ]);
    }

    public function expensesDetails(Request $request)
    {
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
        
        if (!\Auth::user()->hasRole(['super-admin', 'admin'])) {
            $expensesByCategoryQuery->where(function($q) {
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
        
        if (!\Auth::user()->hasRole(['super-admin', 'admin'])) {
            $depositsByCategoryQuery->where(function($q) {
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
