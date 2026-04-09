<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Inertia\Inertia;
use App\Models\LogActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;

class LogActivityController extends Controller
{
    protected $resourceNeo = ['resourceName' => 'activityLog', 'resourceTitle' => 'Activity Logs', 'iconPath' => 'M21 7H3V3H21V7M9.14 19.75C9.32 20.19 9.54 20.61 9.78 21H4V8H20V13.55C19.06 13.19 18.05 13 17 13C13.5 13 10.43 15.06 9.14 18.25L8.85 19L9.14 19.75M9 13H15V11.5C15 11.22 14.78 11 14.5 11H9.5C9.22 11 9 11.22 9 11.5V13M17 18C16.44 18 16 18.44 16 19S16.44 20 17 20 18 19.56 18 19 17.56 18 17 18M23 19C22.06 21.34 19.73 23 17 23S11.94 21.34 11 19C11.94 16.66 14.27 15 17 15S22.06 16.66 23 19M19.5 19C19.5 17.62 18.38 16.5 17 16.5S14.5 17.62 14.5 19 15.62 21.5 17 21.5 19.5 20.38 19.5 19Z', 'actions' => ['d', 'ex']];

    public function __construct()
    {
        $this->middleware('can:activitylog_list', ['only' => ['index', 'show']]);
        $this->middleware('can:activitylog_delete', ['only' => ['destroy', 'bulkDestroy']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                Collection::wrap($value)->each(function ($value) use ($query) {
                    $query
                        ->orWhere('module', 'LIKE', "%{$value}%")
                        ->orWhere('action', 'LIKE', "%{$value}%");
                });
            });
        });
        $perPage = request()->query('perPage') ?? 10;
        $signinlogs = QueryBuilder::for(LogActivity::class)
            ->with('user')
            ->defaultSort('-created_at')
            ->allowedSorts([AllowedSort::field('formatted_date', 'created_at'), 'action', 'module', 'data_key',  AllowedSort::field('user_name', 'user_id')])
            ->allowedFilters([AllowedFilter::exact('module'), AllowedFilter::exact('action'), AllowedFilter::exact('user_id'), AllowedFilter::scope('activity_start_date'), AllowedFilter::scope('activity_end_date'), $globalSearch])
            ->paginate($perPage)
            ->withQueryString();

        if (Auth::user()->can('activitylog_delete')) {
            $this->resourceNeo['bulkActions']['bulk_delete'] = [];
        }
        if (Auth::user()->can('activitylog_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }

        return Inertia::render('Admin/IndexView', ['resourceData' => $signinlogs, 'resourceNeo' => $this->resourceNeo])->table(function (InertiaTable $table) {
            $allusers = User::select('name', 'id')->get();
            $userOption = [];
            foreach ($allusers as $usr) {
                $userOption[$usr->id] = $usr->name;
            }
            $table->withGlobalSearch()
                ->column('formatted_date', 'Date', searchable: false, sortable: true)
                ->column('user_name', 'User', searchable: false, sortable: true)
                ->column('action', 'Action', searchable: false, sortable: true)
                ->column('module', 'Module', searchable: false, sortable: true)
                ->column('data_key', 'Details', searchable: false, sortable: true)
                ->column('ip', 'IP', searchable: false, sortable: false)
                ->column('user_agent', 'Device', searchable: false, sortable: false)
                ->column(label: 'Actions')
                ->perPageOptions([10, 15, 30, 50, 100, 10000])
                ->selectFilter(key: 'user_id', label: 'User', options: $userOption)
                ->selectFilter(key: 'action', label: 'Action', options: config('app.actions'))
                ->selectFilter(key: 'module', label: 'Module', options: config('app.modules'))
                ->dateFilter(key: 'activity_start_date', label: 'Date From')
                ->dateFilter(key: 'activity_end_date', label: 'Date To');
        });
    }

    public function fieldUpdate(Request $request)
    {
        $Logactivity = LogActivity::find($request->id);
        $Logactivity->{$request->key} = $request->value;
        $Logactivity->save();

        $uname = $request->key . '->' . $request->value;
        \ActivityLog::add(['action' => 'updated', 'module' => 'activitylog', 'data_key' => $uname]);
        return response()->json(['message' => 'Settings Updated Successfully.', 'msg_type' => 'success']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LogActivity $logactivity)
    {
        $uname = $logactivity->id;
        $logactivity->delete();
        \ActivityLog::add(['action' => 'deleted', 'module' => 'activitylog', 'data_key' => $uname]);
        return redirect()->back()->with('message', 'Logs Deleted !!');
    }

    /**
     * bulk delete.
     */
    public function bulkDestroy()
    {
        LogActivity::whereIn('id', request('ids'))->delete();
        $uname = (count(request('ids')) > 50) ? 'Many' : $uname = implode(',', request('ids'));
        \ActivityLog::add(['action' => 'deleted', 'module' => 'activitylog', 'data_key' => $uname]);
        return redirect()->back()->with('message', 'Selected Logs Deleted !!');
    }
}
