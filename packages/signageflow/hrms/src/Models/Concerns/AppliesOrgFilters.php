<?php

namespace SignageFlow\Hrms\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait AppliesOrgFilters
{
    public function scopeFilterByOrg(Builder $query, array $filters): Builder
    {
        $map = [
            'company_id',
            'department_id',
            'section_id',
            'designation_id',
            'category_id',
            'extra_classification_id',
        ];

        foreach ($map as $column) {
            if (! empty($filters[$column])) {
                $query->where($this->qualifyColumn($column), $filters[$column]);
            }
        }

        return $query;
    }
}
