<?php

namespace App\Http\Controllers\Admin;

use App\Models\ConsumableInternalName;
use Inertia\Inertia;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ConsumableInternalNameReportController extends Controller
{
    protected $resourceNeo = [
        'resourceName' => 'consumableInternalNameReport',
        'resourceTitle' => 'Product Internal Name Report',
        'iconPath' => 'M11.5 9C11.5 7.62 12.62 6.5 14 6.5C15.1 6.5 16.03 7.21 16.37 8.19C16.45 8.45 16.5 8.72 16.5 9C16.5 10.38 15.38 11.5 14 11.5C12.91 11.5 12 10.81 11.64 9.84C11.55 9.58 11.5 9.29 11.5 9M5 9C5 13.5 10.08 19.66 11 20.81L10 22C10 22 3 14.25 3 9C3 5.83 5.11 3.15 8 2.29C6.16 3.94 5 6.33 5 9M14 2C17.86 2 21 5.13 21 9C21 14.25 14 22 14 22C14 22 7 14.25 7 9C7 5.13 10.14 2 14 2M14 4C11.24 4 9 6.24 9 9C9 10 9 12 14 18.71C19 12 19 10 19 9C19 6.24 16.76 4 14 4Z',
        'actions' => ['r'] // Read-only
    ];

    public function __construct()
    {
        $this->middleware('can:consumableInternalNameReport_list', ['only' => ['index']]);
    }

    public function index()
    {
        $formInfo = ConsumableInternalName::formInfo();
        
        // Remove Open Stock columns from the view
        unset($formInfo['openStockUnit']);
        unset($formInfo['openStockMarginPercent']);
        
        $formInfo['unitPriceWithMargin'] = [
            'label' => 'Unit Price', 
            'sortable' => true, 
            'searchable' => true,
            'align' => 'right'
        ];
        
        // Let's hide the original unitPrice to replace it.
        $formInfo['unitPrice']['hidden'] = true;

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) use ($formInfo) {
            $query->where(function ($query) use ($value, $formInfo) {
                Collection::wrap($value)->each(function ($value) use ($query, $formInfo) {
                    foreach (array_keys($formInfo) as $key) {
                        if($key === 'unitPriceWithMargin' || $key === 'unitPrice') continue;
                        $query->orWhere($key, 'LIKE', "%{$value}%");
                    }
                });
            });
        });

        $perPage = request()->query('perPage') ?? 10;
        
        $query = ConsumableInternalName::select('*', DB::raw('(unitPrice + (unitPrice * openStockMarginPercent / 100)) as unitPriceWithMargin'));

        $resourceData = QueryBuilder::for($query)
            ->defaultSort('name')
            ->allowedSorts(array_merge(array_keys($formInfo), ['unitPriceWithMargin']))
            ->allowedFilters(array_merge(array_keys($formInfo), [$globalSearch]))
            ->paginate($perPage)
            ->withQueryString();

        $this->resourceNeo['showall'] = true;
        
        // Since it's view only, no bulk actions or extra links
        
        return Inertia::render('Admin/IndexView', ['resourceData' => $resourceData, 'resourceNeo' => $this->resourceNeo])->table(function (InertiaTable $table) use ($formInfo) {
            $table->withGlobalSearch();
            foreach (array_keys($formInfo) as $key) {
                if(isset($formInfo[$key]['hidden']) && $formInfo[$key]['hidden']) continue;
                $table->column(
                    $key, 
                    $formInfo[$key]['label'], 
                    searchable: $formInfo[$key]['searchable'] ?? false, 
                    sortable: $formInfo[$key]['sortable'] ?? false, 
                    hidden: $formInfo[$key]['hidden'] ?? false,
                    extra: ['align' => $formInfo[$key]['align'] ?? 'left']
                );
            }
            $table->perPageOptions([10, 15, 30, 50, 100]);
        });
    }
}
