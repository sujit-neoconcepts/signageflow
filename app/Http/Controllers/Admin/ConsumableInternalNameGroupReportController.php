<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConsumableInternalNameGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ConsumableInternalNameGroupReportController extends Controller
{
    protected $resourceNeo = [
        'resourceName' => 'consumableInternalNameGroupReport',
        'resourceTitle' => 'Product Internal Name Group Report',
        'iconPath' => 'M11.5 9C11.5 7.62 12.62 6.5 14 6.5C15.1 6.5 16.03 7.21 16.37 8.19C16.45 8.45 16.5 8.72 16.5 9C16.5 10.38 15.38 11.5 14 11.5C12.91 11.5 12 10.81 11.64 9.84C11.55 9.58 11.5 9.29 11.5 9M5 9C5 13.5 10.08 19.66 11 20.81L10 22C10 22 3 14.25 3 9C3 5.83 5.11 3.15 8 2.29C6.16 3.94 5 6.33 5 9M14 2C17.86 2 21 5.13 21 9C21 14.25 14 22 14 22C14 22 7 14.25 7 9C7 5.13 10.14 2 14 2M14 4C11.24 4 9 6.24 9 9C9 10 9 12 14 18.71C19 12 19 10 19 9C19 6.24 16.76 4 14 4Z',
        'actions' => ['r'], // Read-only
    ];

    public function __construct()
    {
        $this->middleware('can:consumableInternalNameGroupReport_list', ['only' => ['index']]);
    }

    public function index()
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                Collection::wrap($value)->each(function ($value) use ($query) {
                    $query->orWhere('consumable_internal_name_groups.name', 'LIKE', "%{$value}%");
                });
            });
        });

        $perPage = request()->query('perPage') ?? 10;

        $query = ConsumableInternalNameGroup::query()
            ->leftJoin('consumable_internal_names', 'consumable_internal_names.consumable_internal_name_group_id', '=', 'consumable_internal_name_groups.id')
            ->select(
                'consumable_internal_name_groups.id',
                'consumable_internal_name_groups.name as group_name',
                DB::raw('MIN(consumable_internal_names.unitName) as unitName'),
                DB::raw('MIN(consumable_internal_names.unitAltName) as unitAltName'),
                DB::raw('ROUND(AVG(consumable_internal_names.unitPrice * (1 + consumable_internal_names.openStockMarginPercent / 100)), 2) as avg_unit_price')
            )
            ->groupBy('consumable_internal_name_groups.id', 'consumable_internal_name_groups.name');

        $allowedSorts = [
            \Spatie\QueryBuilder\AllowedSort::field('group_name', 'consumable_internal_name_groups.name'),
            \Spatie\QueryBuilder\AllowedSort::field('unitName', 'unitName'),
            \Spatie\QueryBuilder\AllowedSort::field('unitAltName', 'unitAltName'),
            \Spatie\QueryBuilder\AllowedSort::field('avg_unit_price', 'avg_unit_price'),
        ];

        $resourceData = QueryBuilder::for($query)
            ->defaultSort('consumable_internal_name_groups.name')
            ->allowedSorts($allowedSorts)
            ->allowedFilters([$globalSearch])
            ->paginate($perPage)
            ->withQueryString();

        $pageGroupIds = $resourceData->getCollection()->pluck('id')->filter()->toArray();
        $pageGroups = collect();
        $weightedPrices = [];
        if (! empty($pageGroupIds)) {
            $pageGroups = ConsumableInternalNameGroup::whereIn('id', $pageGroupIds)->with('items')->get();
            $weightedPrices = ConsumableInternalNameGroup::getWeightedUnitPrices($pageGroups);
        }

        $resourceData->getCollection()->transform(function ($item) use ($weightedPrices, $pageGroups) {
            // Disable Eloquent's dynamic appends to prevent N+1 queries during serialization
            $item->setAppends([]);

            $g = $pageGroups->firstWhere('id', $item->id);
            $first = $g ? $g->items->first() : null;
            $margin = $first ? (float) $first->openStockMarginPercent : 0.00;
            $weightedPrice = $weightedPrices[$item->id] ?? 0.00;
            $avgWithMargin = $weightedPrice * (1 + $margin / 100);

            $item->avg_unit_price = number_format((float) $avgWithMargin, 2, '.', '');

            return $item;
        });

        $this->resourceNeo['showall'] = true;

        return Inertia::render('Admin/IndexView', ['resourceData' => $resourceData, 'resourceNeo' => $this->resourceNeo])->table(function (InertiaTable $table) {
            $table->withGlobalSearch();

            $table->column('group_name', 'Group Name', sortable: true);
            $table->column('unitName', 'Unit Name', sortable: true);
            $table->column('unitAltName', 'Unit Alt Name', sortable: true);
            $table->column('avg_unit_price', 'Average Unit Price', sortable: true, extra: ['align' => 'right']);

            $table->perPageOptions([10, 15, 30, 50, 100]);
        });
    }
}
