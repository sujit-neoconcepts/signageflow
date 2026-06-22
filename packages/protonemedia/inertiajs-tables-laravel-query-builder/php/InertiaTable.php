<?php

namespace ProtoneMedia\LaravelQueryBuilderInertiaJs;

use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Response;

class InertiaTable
{
    private string $name = 'default';

    private string $pageName = 'page';

    private array $perPageOptions = [10, 15, 30, 50, 100];

    private Request $request;

    private Collection $columns;

    private Collection $searchInputs;

    private Collection $filters;

    private Collection $rangFilters;

    private string $defaultSort = '';

    private static bool|string $defaultGlobalSearch = false;

    private static array $defaultQueryBuilderConfig = [];

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->columns = new Collection;
        $this->searchInputs = new Collection;
        $this->filters = new Collection;

        if (static::$defaultGlobalSearch !== false) {
            $this->withGlobalSearch(static::$defaultGlobalSearch);
        }
    }

    /**
     * Set a default for global search.
     *
     * @return void
     */
    public static function defaultGlobalSearch(bool|string $label = 'Search...')
    {
        static::$defaultGlobalSearch = $label !== false ? __($label) : false;
    }

    /**
     * Retrieve a query string item from the request.
     *
     * @param  mixed|null  $default
     * @return mixed
     */
    private function query(string $key, $default = null)
    {
        return $this->request->query(
            $this->name === 'default' ? $key : "{$this->name}_{$key}",
            $default
        );
    }

    /**
     * Helper method to update the Spatie Query Builder parameter config.
     *
     * @return void
     */
    public static function updateQueryBuilderParameters(string $name)
    {
        if (empty(static::$defaultQueryBuilderConfig)) {
            static::$defaultQueryBuilderConfig = config('query-builder.parameters');
        }

        $newConfig = collect(static::$defaultQueryBuilderConfig)->map(function ($value) use ($name) {
            return "{$name}_{$value}";
        })->all();

        config(['query-builder.parameters' => $newConfig]);
    }

    /**
     * Name for this table.
     */
    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Page name for this table.
     */
    public function pageName(string $pageName): self
    {
        $this->pageName = $pageName;

        return $this;
    }

    /**
     * Per Page options for this table.
     *
     * @param  array  $pageName
     */
    public function perPageOptions(array $perPageOptions): self
    {
        $this->perPageOptions = $perPageOptions;

        return $this;
    }

    /**
     * Default sort for this table.
     */
    public function defaultSort(string $defaultSort): self
    {
        $this->defaultSort = $defaultSort;

        return $this;
    }

    /**
     * Collects all properties and sets the default
     * values from the request query.
     */
    protected function getQueryBuilderProps(): array
    {
        return [
            'defaultVisibleToggleableColumns' => $this->columns->reject->hidden->map->key->sort()->values(),
            'columns' => $this->transformColumns(),
            'hasHiddenColumns' => $this->columns->filter->hidden->isNotEmpty(),
            'hasToggleableColumns' => $this->columns->filter->canBeHidden->isNotEmpty(),

            'filters' => $this->transformFilters(),
            'hasFilters' => $this->filters->isNotEmpty(),
            'hasEnabledFilters' => $this->filters->filter->value->isNotEmpty(),

            'searchInputs' => $searchInputs = $this->transformSearchInputs(),
            'searchInputsWithoutGlobal' => $searchInputsWithoutGlobal = $searchInputs->where('key', '!=', 'global'),
            'hasSearchInputs' => $searchInputsWithoutGlobal->isNotEmpty(),
            'hasSearchInputsWithValue' => $searchInputsWithoutGlobal->whereNotNull('value')->isNotEmpty(),
            'hasSearchInputsWithoutValue' => $searchInputsWithoutGlobal->whereNull('value')->isNotEmpty(),

            'globalSearch' => $this->searchInputs->firstWhere('key', 'global'),

            'cursor' => $this->query('cursor'),
            'sort' => $this->query('sort', $this->defaultSort) ?: null,
            'defaultSort' => $this->defaultSort,
            'page' => Paginator::resolveCurrentPage($this->pageName),
            'pageName' => $this->pageName,
            'perPageOptions' => $this->perPageOptions,
        ];
    }

    /**
     * Transform the columns collection so it can be used in the Inertia front-end.
     */
    protected function transformColumns(): Collection
    {
        $columns = $this->query('columns', []);

        $sort = $this->query('sort', $this->defaultSort);
        $sort_array = explode(',', $sort);

        return $this->columns->map(function (Column $column) use ($columns, $sort_array) {
            $key = $column->key;

            if (! empty($columns)) {
                $column->hidden = ! in_array($key, $columns);
            }
            foreach ($sort_array as $svalue) {
                if ($svalue === $key) {
                    $column->sorted = 'asc';
                } elseif ($svalue === "-{$key}") {
                    $column->sorted = 'desc';
                }
            }

            return $column;
        });
    }

    /**
     * Transform the search collection so it can be used in the Inertia front-end.
     */
    protected function transformFilters(): Collection
    {
        $filters = $this->filters;

        $queryFilters = $this->query('filter', []);

        if (empty($queryFilters)) {
            return $filters;
        }

        return $filters->map(function (Filter $filter) use ($queryFilters) {
            if (array_key_exists($filter->key, $queryFilters)) {
                $filter->value = $queryFilters[$filter->key];
            }

            return $filter;
        });
    }

    /**
     * Transform the filters collection so it can be used in the Inertia front-end.
     */
    protected function transformSearchInputs(): Collection
    {
        $filters = $this->query('filter', []);

        if (empty($filters)) {
            return $this->searchInputs;
        }

        return $this->searchInputs->map(function (SearchInput $searchInput) use ($filters) {
            if (array_key_exists($searchInput->key, $filters)) {
                $searchInput->value = $filters[$searchInput->key];
            }

            return $searchInput;
        });
    }

    /**
     * Add a column to the query builder.
     */
    public function column(?string $key = null, ?string $label = null, bool $canBeHidden = true, bool $hidden = false, bool $sortable = false, bool $searchable = false, array $extra = []): self
    {
        $key = $key ?: Str::kebab($label);
        $label = $label ?: Str::headline($key);
        $this->columns = $this->columns->reject(function (Column $column) use ($key) {
            return $column->key === $key;
        })->push($column = new Column(
            key: $key,
            label: $label,
            extra: $extra,
            canBeHidden: $canBeHidden,
            hidden: $hidden,
            sortable: $sortable,
            sorted: false
        ))->values();

        if ($searchable) {
            $this->searchInput($column->key, $column->label);
        }

        return $this;
    }

    /**
     * Helper method to add a global search input.
     */
    public function withGlobalSearch(?string $label = null): self
    {
        return $this->searchInput('global', $label ?: __('Search...'));
    }

    /**
     * Add a search input to query builder.
     */
    public function searchInput(string $key, ?string $label = null, ?string $defaultValue = null): self
    {
        $this->searchInputs = $this->searchInputs->reject(function (SearchInput $searchInput) use ($key) {
            return $searchInput->key === $key;
        })->push(new SearchInput(
            key: $key,
            label: $label ?: Str::headline($key),
            value: $defaultValue
        ))->values();

        return $this;
    }

    /**
     * Add a select filter to the query builder.
     */
    public function selectFilter(string $key, array $options, ?string $label = null, ?string $defaultValue = null, bool $noFilterOption = true, ?string $noFilterOptionLabel = null): self
    {
        $this->filters = $this->filters->reject(function (Filter $filter) use ($key) {
            return $filter->key === $key;
        })->push(new Filter(
            key: $key,
            label: $label ?: Str::headline($key),
            options: $options,
            value: $defaultValue,
            noFilterOption: $noFilterOption,
            noFilterOptionLabel: $noFilterOptionLabel ?: '-',
            type: 'select'
        ))->values();

        return $this;
    }

    /**
     * Add a Date Range filter to the query builder.
     */
    public function dateFilter(string $key, array $options = [], ?string $label = null, ?string $defaultValue = null, bool $noFilterOption = true, ?string $noFilterOptionLabel = null): self
    {
        $this->filters = $this->filters->reject(function (Filter $filter) use ($key) {
            return $filter->key === $key;
        })->push(new Filter(
            key: $key,
            label: $label ?: Str::headline($key),
            options: $options,
            value: $defaultValue,
            noFilterOption: $noFilterOption,
            noFilterOptionLabel: $noFilterOptionLabel ?: '-',
            type: 'datePicker'
        ))->values();

        return $this;
    }

    /**
     * Give the query builder props to the given Inertia response.
     */
    public function applyTo(Response $response): Response
    {
        $props = array_merge($response->getQueryBuilderProps(), [
            $this->name => $this->getQueryBuilderProps(),
        ]);

        return $response->with('queryBuilderProps', $props);
    }
}
