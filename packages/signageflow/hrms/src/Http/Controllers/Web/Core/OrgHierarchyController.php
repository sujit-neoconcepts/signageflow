<?php

namespace SignageFlow\Hrms\Http\Controllers\Web\Core;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use SignageFlow\Hrms\Models\Category;
use SignageFlow\Hrms\Models\Company;
use SignageFlow\Hrms\Models\Department;
use SignageFlow\Hrms\Models\Designation;
use SignageFlow\Hrms\Models\ExtraClassification;
use SignageFlow\Hrms\Models\Section;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class OrgHierarchyController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:hrmsOrg_list')->only(['index']);
        $this->middleware('can:hrmsOrg_create')->only(['store']);
        $this->middleware('can:hrmsOrg_edit')->only(['update']);
        $this->middleware('can:hrmsOrg_delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'department');
        $modelClass = $this->resolveModel($tab);
        $tableName = (new $modelClass)->getTable();
        $perPage = (int) ($request->query('perPage') ?? 10);

        $query = $modelClass::query()->select(["{$tableName}.*"]);

        if ($tab === 'department') {
            $query->leftJoin('hrms_companies', 'hrms_departments.company_id', '=', 'hrms_companies.id')
                ->addSelect('hrms_companies.name as parent_name');
        } elseif ($tab === 'section') {
            $query->leftJoin('hrms_departments', 'hrms_sections.department_id', '=', 'hrms_departments.id')
                ->addSelect('hrms_departments.name as parent_name');
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) use ($tableName) {
            $query->where(function ($query) use ($value, $tableName) {
                Collection::wrap($value)->each(function ($value) use ($query, $tableName) {
                    $query->orWhere("{$tableName}.code", 'LIKE', "%{$value}%")
                        ->orWhere("{$tableName}.name", 'LIKE', "%{$value}%");
                });
            });
        });

        $allowedSorts = ['code', 'name', 'is_active'];
        if ($tab === 'designation') {
            $allowedSorts[] = 'level';
        } elseif ($tab === 'department') {
            $allowedSorts[] = AllowedSort::field('parent_name', 'hrms_companies.name');
        } elseif ($tab === 'section') {
            $allowedSorts[] = AllowedSort::field('parent_name', 'hrms_departments.name');
        }

        $records = QueryBuilder::for($query)
            ->defaultSort("{$tableName}.name")
            ->allowedSorts($allowedSorts)
            ->allowedFilters(['code', 'name', $globalSearch])
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Hrms/Core/OrgHierarchy', [
            'tab' => $tab,
            'records' => $records,
            'companies' => Company::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'resourceNeo' => [
                'resourceName' => 'hrms.org',
                'resourceTitle' => 'Organization Hierarchy',
                'actions' => ['c', 'r', 'u', 'd'],
            ],
        ])->table(function (InertiaTable $table) use ($tab) {
            $table->withGlobalSearch()
                ->column('code', 'Code', searchable: true, sortable: true)
                ->column('name', 'Name', searchable: true, sortable: true);

            if ($tab === 'department') {
                $table->column('parent_name', 'Company', searchable: false, sortable: true);
            } elseif ($tab === 'section') {
                $table->column('parent_name', 'Department', searchable: false, sortable: true);
            } elseif ($tab === 'designation') {
                $table->column('level', 'Level', searchable: false, sortable: true);
            } elseif ($tab === 'company') {
                $table->column('gstin', 'GSTIN', searchable: false, sortable: false);
            }

            $table->column('is_active', 'Status', searchable: false, sortable: true)
                ->column(label: 'Actions')
                ->perPageOptions([10, 15, 30, 50, 100]);
        });
    }

    public function store(Request $request, string $type)
    {
        $modelClass = $this->resolveModel($type);
        $data = $this->validatePayload($request, $type);
        $record = $modelClass::create($data);

        if (class_exists(\ActivityLog::class)) {
            \ActivityLog::add(['action' => 'added', 'module' => 'hrms_org_'.$type, 'data_key' => $record->name]);
        }

        return back()->with(['message' => ucfirst(str_replace('_', ' ', $type)).' created successfully.', 'msg_type' => 'success']);
    }

    public function update(Request $request, string $type, int $id)
    {
        $modelClass = $this->resolveModel($type);
        $record = $modelClass::findOrFail($id);
        $data = $this->validatePayload($request, $type, $id);
        $record->update($data);

        if (class_exists(\ActivityLog::class)) {
            \ActivityLog::add(['action' => 'updated', 'module' => 'hrms_org_'.$type, 'data_key' => $record->name]);
        }

        return back()->with(['message' => ucfirst(str_replace('_', ' ', $type)).' updated successfully.', 'msg_type' => 'success']);
    }

    public function destroy(string $type, int $id)
    {
        $modelClass = $this->resolveModel($type);
        $record = $modelClass::findOrFail($id);
        $name = $record->name;
        $record->delete();

        if (class_exists(\ActivityLog::class)) {
            \ActivityLog::add(['action' => 'deleted', 'module' => 'hrms_org_'.$type, 'data_key' => $name]);
        }

        return back()->with(['message' => ucfirst(str_replace('_', ' ', $type)).' deleted.', 'msg_type' => 'success']);
    }

    protected function resolveModel(string $type): string
    {
        return match ($type) {
            'company' => Company::class,
            'department' => Department::class,
            'section' => Section::class,
            'designation' => Designation::class,
            'category' => Category::class,
            'extra_classification' => ExtraClassification::class,
            default => abort(404, 'Unknown organization entity type.'),
        };
    }

    protected function validatePayload(Request $request, string $type, ?int $ignoreId = null): array
    {
        $table = (new ($this->resolveModel($type)))->getTable();
        $uniqueRule = 'required|string|max:30|unique:'.$table.',code'.($ignoreId ? ','.$ignoreId : '');

        $rules = [
            'code' => $uniqueRule,
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ];

        if ($type === 'company') {
            $rules += ['legal_name' => 'nullable|string|max:255', 'gstin' => 'nullable|string|max:30', 'pan' => 'nullable|string|max:20', 'tan' => 'nullable|string|max:20', 'address' => 'nullable|string'];
        } elseif ($type === 'department') {
            $rules['company_id'] = 'nullable|exists:hrms_companies,id';
        } elseif ($type === 'section') {
            $rules['department_id'] = 'nullable|exists:hrms_departments,id';
        } elseif ($type === 'designation') {
            $rules['level'] = 'nullable|integer|min:1|max:50';
        }

        return $request->validate($rules);
    }
}
