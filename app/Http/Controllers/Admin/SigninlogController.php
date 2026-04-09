<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\SigninLog;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class SigninlogController extends Controller
{
    protected $resourceNeo = ['resourceName' => 'signinlog', 'resourceTitle' => 'Signin Logs', 'iconPath' => 'M6 8C6 5.79 7.79 4 10 4S14 5.79 14 8 12.21 12 10 12 6 10.21 6 8M9.14 19.75L8.85 19L9.14 18.25C9.84 16.5 11.08 15.14 12.61 14.22C11.79 14.08 10.92 14 10 14C5.58 14 2 15.79 2 18V20H9.27C9.23 19.91 9.18 19.83 9.14 19.75M17 18C16.44 18 16 18.44 16 19S16.44 20 17 20 18 19.56 18 19 17.56 18 17 18M23 19C22.06 21.34 19.73 23 17 23S11.94 21.34 11 19C11.94 16.66 14.27 15 17 15S22.06 16.66 23 19M19.5 19C19.5 17.62 18.38 16.5 17 16.5S14.5 17.62 14.5 19 15.62 21.5 17 21.5 19.5 20.38 19.5 19Z', 'actions' => ['d', 'ex']];

    public function __construct()
    {
        $this->middleware('can:signinlog_list', ['only' => ['index', 'show']]);
        $this->middleware('can:signinlog_delete', ['only' => ['destroy', 'bulkDestroy']]);
        $this->middleware('can:signinlog_view', ['only' => ['show']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //  $signinlogs = SigninLog::orderBy('created_at', 'desc')->get();
        //  return Inertia::render('Admin/SigninLogIndexView', compact('signinlogs'));

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                Collection::wrap($value)->each(function ($value) use ($query) {
                    $query
                        ->orWhere('email', 'LIKE', "%{$value}%")
                        ->orWhere('ip', 'LIKE', "%{$value}%");
                });
            });
        });
        $perPage = request()->query('perPage') ?? 10;

        $signinlogs = QueryBuilder::for(SigninLog::class)
            ->defaultSort('-created_at')
            ->allowedSorts([AllowedSort::field('formatted_date', 'created_at'), 'email'])
            ->allowedFilters(['email', 'msg', AllowedFilter::scope('signedin_start_date'), AllowedFilter::scope('signedin_end_date'), $globalSearch])
            ->paginate($perPage)
            ->withQueryString();


        if (Auth::user()->can('signinlog_delete')) {
            $this->resourceNeo['bulkActions'] = ['bulk_delete' => []];
        }
        if (Auth::user()->can('signinlog_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }

        return Inertia::render('Admin/IndexView', ['resourceData' => $signinlogs, 'resourceNeo' => $this->resourceNeo])->table(function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('formatted_date', 'Date', searchable: false, sortable: true,)
                ->column('email', 'Email', searchable: true, sortable: true)
                ->column('ip', 'IP', searchable: false, sortable: false)
                ->column('msg', 'Log', searchable: false, sortable: false)
                ->column('userAgent', 'Device', searchable: false, sortable: false)
                ->column(label: 'Actions')
                ->perPageOptions([10, 15, 30, 50, 100, 10000])
                ->selectFilter(key: 'msg', label: 'Log', options: [
                    'Login Success' => 'Login Success',
                    'Login Failed' => 'Login Failed',
                    'Otp Success' => 'Otp Success',
                    'Otp Failed' => 'Otp Failed',
                ])
                ->dateFilter(key: 'signedin_start_date', label: 'Date From')
                ->dateFilter(key: 'signedin_end_date', label: 'Date To');
        });
    }



    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SigninLog $signinlog)
    {
        $uname = $signinlog->id;
        $signinlog->delete();
        \ActivityLog::add(['action' => 'deleted', 'module' => 'signinlog', 'data_key' => $uname]);
        return redirect()->back()->with('message', 'Logs Deleted !!');
    }


    /**
     * bulk delete.
     */
    public function bulkDestroy()
    {
        SigninLog::whereIn('id', request('ids'))->delete();
        $uname = (count(request('ids')) > 50) ? 'Many' : $uname = implode(',', request('ids'));
        \ActivityLog::add(['action' => 'deleted', 'module' => 'signinlog', 'data_key' => $uname]);
        return redirect()->back()->with('message', 'Selected Logs Deleted !!');
    }
}
