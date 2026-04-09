<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;

class ExpenseController extends Controller
{
    protected $resourceNeo = ['resourceName' => 'expense', 'resourceTitle' => 'Expense', 'iconPath' => 'M8,3H18L17,5H13.74C14.22,5.58 14.58,6.26 14.79,7H18L17,9H15C14.75,11.57 12.74,13.63 10.2,13.96V14H9.5L15.5,21H13L7,14V12H9.5V12C11.26,12 12.72,10.7 12.96,9H7L8,7H12.66C12.1,5.82 10.9,5 9.5,5H7L8,3Z', 'actions' => ['c', 'r', 'u', 'd']];

    public function __construct()
    {
        $this->middleware('can:expense_list', ['only' => ['index', 'show']]);
        $this->middleware('can:expense_delete', ['only' => ['destroy', 'bulkDestroy']]);
        $this->middleware('can:expense_edit', ['only' => ['edit', 'update']]);
        $this->middleware('can:expense_delete', ['only' => ['destroy']]);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $formInfo = Expense::formInfo();
        $formInfoMulti = Expense::formInfoMulti();
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) use ($formInfo, $formInfoMulti) {
            $query->where(function ($query) use ($value, $formInfo, $formInfoMulti) {
                Collection::wrap($value)->each(function ($value) use ($query, $formInfo, $formInfoMulti) {
                    foreach (array_keys($formInfo) as $key) {
                        $query->orWhere($key, 'LIKE', "%{$value}%");
                    }
                    foreach (array_keys($formInfoMulti) as $key) {
                        $query->orWhere($key, 'LIKE', "%{$value}%");
                    }
                });
            });
        });
        $perPage = request()->query('perPage') ?? 10;
        $filter_array = [];
        foreach (array_merge(array_diff(array_keys($formInfo), []), array_keys($formInfoMulti)) as $fvalue) {
            $filter_array[] = AllowedFilter::exact($fvalue);
        }
        $inc_f = request()->query('filter');
        $query_dep_before = Expense::where('amt_type', 'Deposit');//->inFinancialYear();
        $query_exp_before = Expense::where('amt_type', 'Expense');//->inFinancialYear();

        $query_dep_after = Expense::where('amt_type', 'Deposit');//->inFinancialYear();
        $query_exp_after = Expense::where('amt_type', 'Expense');//->inFinancialYear();

        if (\Auth::user()->can('all') || \Auth::user()->can('expense_list_for_all')) {
            if (isset($inc_f['incharge'])) {
                $query_dep_before->where('incharge', $inc_f['incharge']);
                $query_exp_before->where('incharge', $inc_f['incharge']);

                $query_dep_after->where('incharge', $inc_f['incharge']);
                $query_exp_after->where('incharge', $inc_f['incharge']);
            }
        } else {
            $query_dep_before->where('incharge', \Auth::user()->name);
            $query_exp_before->where('incharge', \Auth::user()->name);

            $query_dep_after->where('incharge', \Auth::user()->name);
            $query_exp_after->where('incharge', \Auth::user()->name);
        }

        // Exclude records where 'doneby' contains 'Head Office' for non-admin users
            if (!\Auth::user()->hasRole(['super-admin', 'admin'])) {
                $headOfficeFilter = function($q) {
                    $q->whereNull('doneby')
                      ->orWhere('doneby', 'NOT LIKE', '%Head Office%');
                };
                
                $query_dep_before->where($headOfficeFilter);
                $query_exp_before->where($headOfficeFilter);
                $query_dep_after->where($headOfficeFilter);
                $query_exp_after->where($headOfficeFilter);
            }

        // Apply exp_cate filter if present
        if (isset($inc_f['exp_cate'])) {
            $query_dep_before->where('exp_cate', $inc_f['exp_cate']);
            $query_exp_before->where('exp_cate', $inc_f['exp_cate']);

            $query_dep_after->where('exp_cate', $inc_f['exp_cate']);
            $query_exp_after->where('exp_cate', $inc_f['exp_cate']);
        }

        if (isset($inc_f['exp_date_start']) && isset($inc_f['exp_date_end'])) {
            $query_dep_before->expDatebefore($inc_f['exp_date_start']);
            $query_exp_before->expDatebefore($inc_f['exp_date_start']);

            $query_dep_after->expDateStart($inc_f['exp_date_start'])->expDateEnd($inc_f['exp_date_end']);
            $query_exp_after->expDateStart($inc_f['exp_date_start'])->expDateEnd($inc_f['exp_date_end']);


            $this->resourceNeo['date_staring'] = $inc_f['exp_date_start'];
            $this->resourceNeo['date_closing'] = $inc_f['exp_date_end'];
            $this->resourceNeo['date_dur'] = 'From: ' . $inc_f['exp_date_start'] . " To: " . $inc_f['exp_date_end'];
        } else {
            $query_dep_before->expDatebefore(date('Y-m-d'));
            $query_exp_before->expDatebefore(date('Y-m-d'));

            $query_dep_after->expDateStart(date('Y-m-d'))->expDateEnd(date('Y-m-d'));
            $query_exp_after->expDateStart(date('Y-m-d'))->expDateEnd(date('Y-m-d'));

            $this->resourceNeo['date_staring'] = 'Today';
            $this->resourceNeo['date_closing'] = 'Today';
            $this->resourceNeo['date_dur'] = 'Today';
        }

        $opening = $query_dep_before->sum('amount') - $query_exp_before->sum('amount');
        $this->resourceNeo['opening'] = number_format($opening, 2);

        $depo_dur = $query_dep_after->sum('amount');
        $this->resourceNeo['depo_dur'] = number_format($depo_dur, 2);

        $exp_dur = $query_exp_after->sum('amount');
        $this->resourceNeo['exp_dur'] = number_format($exp_dur, 2);

        $closing = $opening + $depo_dur - $exp_dur;
        $this->resourceNeo['closing'] = number_format($closing, 2);


        $query = Expense::select('expenses.*')
            ->selectRaw('IF(amt_type="Expense", amount*-1,amount) as amount')
            ->selectRaw('IF(amt_type="Expense", amount, 0) as expense_amount')
            ->selectRaw('IF(amt_type="Deposit", amount, 0) as deposit_amount');


        if (\Auth::user()->can('all') || \Auth::user()->can('expense_list_for_all')) {
        } else {
            $query = $query->where('incharge', \Auth::user()->name);
        }

        if (!\Auth::user()->hasRole(['super-admin', 'admin'])) {
            $query = $query->where(function($q) {
                $q->whereNull('doneby')
                  ->orWhere('doneby', 'NOT LIKE', '%Head Office%');
            });
        }

        //$query->inFinancialYear();

        $resourceData = QueryBuilder::for($query)
            ->defaultSort('-exp_date')
            ->allowedSorts(array_merge(array_diff(array_keys($formInfo), []), array_keys($formInfoMulti), []))
            ->allowedFilters(array_merge($filter_array, [AllowedFilter::scope('exp_date_start'), AllowedFilter::scope('exp_date_end'), $globalSearch]))
            ->paginate($perPage)
            ->withQueryString();

        if (\Auth::user()->can('expense_delete')) {
            $this->resourceNeo['bulkActions'] = ['bulk_delete' => []];
        }

        if (\Auth::user()->can('expense_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }
        $this->resourceNeo['extraLinks'] = [];
        $this->resourceNeo['showTotal'] = true;
        $this->resourceNeo['showall'] = true;

        return Inertia::render('Admin/ExpnsIndexView', ['resourceData' => $resourceData, 'resourceNeo' => $this->resourceNeo])->table(function (InertiaTable $table) use ($formInfo, $formInfoMulti) {
            $table->withGlobalSearch();
            $arrKey = array_diff(array_keys($formInfo), []);
            foreach ($arrKey as $key) {
                $table->column($key, $formInfo[$key]['label'], searchable: $formInfo[$key]['searchable'] ?? false, sortable: $formInfo[$key]['sortable'] ?? false, hidden: $formInfo[$key]['hidden'] ?? false, extra: ['type' => $formInfo[$key]['type'] ?? '', 'options' => [], 'align' => $formInfo[$key]['align'] ?? 'left', 'showTotal' => $formInfo[$key]['showTotal'] ?? false]);
            }
            $table->column(key: 'expense_amount', label: 'Expense', extra: ['align' => 'right', 'showTotal' => true]);
            $table->column(key: 'deposit_amount', label: 'Deposit', extra: ['align' => 'right', 'showTotal' => true]);
            foreach (array_keys($formInfoMulti) as $key) {
                $table->column($key, $formInfoMulti[$key]['label'], searchable: $formInfoMulti[$key]['searchable'] ?? false, sortable: $formInfoMulti[$key]['sortable'] ?? false, hidden: $formInfoMulti[$key]['hidden'] ?? false, extra: ['align' => $formInfoMulti[$key]['align'] ?? 'left', 'showTotal' => $formInfoMulti[$key]['showTotal'] ?? false]);
            }
            


            $fresult2 = [];
            foreach ($formInfo['incharge']['options'] as  $opt) {
                $opt && $fresult2[$opt] = $opt;
            }
            
            // Prepare Type filter options
            $typeOptions = [
                'Expense' => 'Expense',
                'Deposit' => 'Deposit'
            ];
            
            // Prepare Expense Category filter options
            $expCateOptions = [];
            foreach ($formInfoMulti['exp_cate']['options'] as $opt) {
                $opt && $expCateOptions[$opt] = $opt;
            }
            
            $table
                ->column(label: 'Actions')
                ->dateFilter(key: 'exp_date_start', label: 'Date From')
                ->dateFilter(key: 'exp_date_end', label: 'Date To');
            if (\Auth::user()->can('all') || \Auth::user()->can('expense_list_for_all')) {
                $table->selectFilter(key: 'incharge', label: $formInfo['incharge']['label'], options: $fresult2, noFilterOptionLabel: 'All');
            }
            $table
                ->selectFilter(key: 'amt_type', label: 'Type', options: $typeOptions, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'exp_cate', label: 'Exp Cate', options: $expCateOptions, noFilterOptionLabel: 'All')
                ->perPageOptions([10, 15, 30, 50, 100, 10000]);
        });
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $resourceNeo = $this->resourceNeo;
        $resourceNeo['Multilabel'] = 'Line items';
        $resourceNeo['fColumn'] = 3;
        $resourceNeo['fColumnMulti'] = 8;
        $resourceNeo['AllowMore'] = true;
        $resourceNeo['AllowDel'] = true;
        $resourceNeo['formInfo'] = Expense::formInfo();
        if (!(\Auth::user()->can('all') || \Auth::user()->can('expense_add_for_all'))) {
            $resourceNeo['formInfo']['incharge']['type'] = null;
            $resourceNeo['formInfo']['incharge']['default'] = \Auth::user()->name;
        }
        $resourceNeo['formInfoMulti'] = Expense::formInfoMulti();
        return Inertia::render('Admin/ExpenseAddEditView', compact('resourceNeo'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $formInfo = Expense::formInfo();
        $formInfoMulti = Expense::formInfoMulti();
        $attributeNames = [];
        $validateRule = [];
        $savedArray = [];

        if (strtotime($request->exp_date) < strtotime('-2 days') && !(\Auth::user()->can('expense_back_date_entry'))) {
            return redirect()->back()->withErrors(['exp_date' => 'The Expanse Date cannot be older than 2 days.']);
        }

        foreach (array_keys($formInfo) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
            $savedArray[$key] = $request->{$key};
        }
        foreach (array_keys($formInfoMulti) as $key) {
            $attributeNames['multi.*.' . $key] = $formInfoMulti[$key]['label'];
            isset($formInfoMulti[$key]['vRule']) && $validateRule['multi.*.' . $key] = $formInfoMulti[$key]['vRule'];
        }

        $request->validate($validateRule, [], $attributeNames);
        $savedArray['exp_date'] = date('Y-m-d', strtotime($request->exp_date));


        foreach ($request->multi as $ml) {
            $temp = [];
            foreach (array_keys($formInfoMulti) as $key) {
                $savedArray[$key] = $ml[$key];
                if ($key == 'doneby' && $ml[$key]) {
                    foreach ($ml[$key] as $dkey) {
                        $temp[] = $dkey['id'];
                    }
                }
            }
            $savedArray['doneby'] = implode(',', $temp);
            Expense::create($savedArray);
        }

        \ActivityLog::add(['action' => 'added', 'module' => $this->resourceNeo['resourceName'], 'data_key' => $request->{array_keys($formInfo)[1]}]);

        return redirect()->route('expense.index')->with(['message' => $this->resourceNeo['resourceTitle'] . ' Created Successfully !!', 'msg_type' => 'info']);
    }

    /**
     * Display the specified resource.
     */
    public function show(Expense $expense)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Expense $expense)
    {
        $formdata = $expense;
        $temp = [];
        $formInfoMulti = Expense::formInfoMulti();
        foreach (array_keys($formInfoMulti) as $key) {
            $temp[$key] = $expense->{$key};
        }
        $temp1 = explode(',', $expense->doneby);
        $temp2 = [];
        foreach ($temp1 as $t) {
            $temp2[] = ['id' => $t, 'label' => $t];
        }
        $temp['doneby'] = $temp2;

        $formdata->multi = [$temp];
        $resourceNeo = $this->resourceNeo;
        $resourceNeo['Multilabel'] = 'Line items';
        $resourceNeo['fColumn'] = 3;
        $resourceNeo['fColumnMulti'] = 8;
        $resourceNeo['AllowMore'] = false;
        $resourceNeo['AllowDel'] = false;
        $resourceNeo['formInfo'] = Expense::formInfo();
        $resourceNeo['formInfoMulti'] = $formInfoMulti;
        return Inertia::render('Admin/ExpenseAddEditView', compact('formdata', 'resourceNeo'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expense $expense)
    {
        $formInfo = Expense::formInfo();
        $formInfoMulti = Expense::formInfoMulti();
        $attributeNames = [];
        $validateRule = [];
        foreach (array_diff(array_keys($formInfo), ['pur_inv']) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
        }
        foreach (array_keys($formInfoMulti) as $key) {
            $attributeNames['multi.*.' . $key] = $formInfoMulti[$key]['label'];
            isset($formInfoMulti[$key]['vRule']) && $validateRule['multi.*.' . $key] = $formInfoMulti[$key]['vRule'];
        }
        $request->validate($validateRule, [], $attributeNames);
        foreach (array_diff(array_keys($formInfo), []) as $key) {
            $expense->{$key} = $request->{$key};
        }
        $expense->exp_date = date('Y-m-d', strtotime($request->exp_date));
        foreach ($request->multi as $ml) {
            $temp = [];
            foreach (array_keys($formInfoMulti) as $key) {
                $expense->{$key} = $ml[$key];
                if ($key == 'doneby' && $ml[$key]) {
                    foreach ($ml[$key] as $dkey) {
                        $temp[] = $dkey['id'];
                    }
                }
            }
            $expense->doneby =
                implode(',', $temp);
        }


        $expense->save();

        \ActivityLog::add(['action' => 'updated', 'module' => $this->resourceNeo['resourceName'], 'data_key' => $request->{array_keys($formInfo)[1]}]);

        return redirect()->route('expense.index')->with(['message' => 'Expense Updated Successfully !!', 'msg_type' => 'info']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expense $expense)
    {
        $uname = $expense->id;
        $expense->delete();
        \ActivityLog::add(['action' => 'deleted', 'module' => 'expense', 'data_key' => $uname]);
        return redirect()->back()->with('message', 'Expense Deleted !!');
    }

    /**
     * bulk delete.
     */
    public function bulkDestroy()
    {
        Expense::whereIn('id', request('ids'))->delete();
        $uname = (count(request('ids')) > 50) ? 'Many' : $uname = implode(',', request('ids'));
        \ActivityLog::add(['action' => 'deleted', 'module' => 'expense', 'data_key' => $uname]);
        return redirect()->back()->with('message', 'Selected Expense Deleted !!');
    }
}
