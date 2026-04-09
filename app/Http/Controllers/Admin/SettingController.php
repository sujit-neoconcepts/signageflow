<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;

class SettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:setting_list', ['only' => ['index', 'show']]);
        $this->middleware('can:setting_create', ['only' => ['create', 'store']]);
        $this->middleware('can:setting_edit', ['only' => ['edit', 'update']]);
        $this->middleware('can:setting_delete', ['only' => ['destroy']]);
    }
    /**
     * Display a listing of the resource.
     */
    public function list()
    {
        $settings = Setting::orderBy('group')->get();
        return Inertia::render('Admin/SettingShowView', compact('settings'));
    }
    public function index()
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                Collection::wrap($value)->each(function ($value) use ($query) {
                    $query
                        ->orWhere('slug', 'LIKE', "%{$value}%")
                        ->orWhere('value', 'LIKE', "%{$value}%");
                });
            });
        });
        $perPage = request()->query('perPage') ?? 10;

        $signinlogs = QueryBuilder::for(Setting::class)
            ->defaultSort('slug')
            ->allowedSorts(['slug', 'value'])
            ->allowedFilters(['slug', 'value', $globalSearch])
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Admin/SettingIndexView', ['settings' => $signinlogs])->table(function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('slug', 'Slug', searchable: true, sortable: true)
                ->column('label', 'Label', searchable: false, sortable: false)
                ->column('value', 'Value', searchable: false, sortable: false)
                ->column('group', 'Group', searchable: false, sortable: false)
                ->column(label: 'Actions')
                ->perPageOptions([10, 15, 30, 50, 100]);
        });
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Admin/SettingAddEditView');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'slug' => 'required|string|max:255',
            'label' => 'required|string|max:255',
            'value' => 'required|string|max:255',
            'vtype' => 'required|string|max:255',
            'group' => 'required|string|max:255',
        ]);

        Setting::create(request(['slug', 'label', 'value', 'vtype', 'group', 'access_roles']));

        return redirect()->route('setting.index')->with('message', 'Setting Created Successfully');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Setting $setting)
    {
        $formdata = $setting;

        return Inertia::render('Admin/SettingAddEditView', compact('formdata'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Setting $setting)
    {
        $request->validate([
            'slug' => 'required|string|max:255',
            'label' => 'required|string|max:255',
            'value' => 'required|string|max:255',
            'vtype' => 'required|string|max:255',
            'group' => 'required|string|max:255',
        ]);

        $setting->slug = $request->slug;
        $setting->label = $request->label;
        $setting->value = $request->value;
        $setting->vtype = $request->vtype;
        $setting->group = $request->group;
        $setting->access_roles = $request->access_roles;
        $setting->save();
        return redirect()->route('setting.index')->with('message', 'Settings Updated Successfully');
    }
    public function bulkUpdate(Request $request)
    {

        $isDirty = false;
        foreach ($request->settings as $setting) {

            $setToBeUpdated = Setting::find($setting['id']);
            $setToBeUpdated->value = $setting['value'];
            if ($setToBeUpdated->isDirty('value')) {
                $setToBeUpdated->save();
                $isDirty = true;
                //$uname=$setToBeUpdated->label.'->'.$setToBeUpdated->value;
                //\ActivityLog::add(['action'=>'updated','module'=>'settings','data_key'=>$uname]);
            }
        }
        if ($isDirty) {
            $uname = '';
            \ActivityLog::add(['action' => 'updated', 'module' => 'settings', 'data_key' => $uname]);
            $res = ['message' => 'Settings Updated Successfully.', 'msg_type' => 'success'];
        } else {
            $res = ['message' => 'No Value  Updated in Settings .', 'msg_type' => 'warning'];
        }

        return redirect()->route('setting.list')->with($res);
    }



    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Setting $setting)
    {
        $setting->delete();
        return redirect()->route('setting.index')->with('message', 'Setting Deleted !!');
    }

    public function authDestroy()
    {
        if ((Hash::check(request('password'), Auth::user()->password))) {
            $setting = Setting::find(request('id'));
            $this->destroy($setting);
        } else {
            return redirect()->route('setting.index')->with('message', 'Athentication Failed!!');
        }
    }
    public function changeFinancialYear(Request $request)
    {
        $request->validate([
            'year' => 'required|string|regex:/^\d{4}-\d{4}$/'
        ]);

        $years = explode('-', $request->year);
        session([
            'financial_year_start' => $years[0] . '-04-01',
            'financial_year_end' => $years[1] . '-03-31',
            'financial_year' => $request->year
        ]);

        return redirect()->back()->with('message', 'Financial year changed successfully');
    }
}
