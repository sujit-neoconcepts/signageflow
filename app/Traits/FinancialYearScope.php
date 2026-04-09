<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait FinancialYearScope
{
    public function scopeInFinancialYear(Builder $query)
    {
        //return $query;
        return $query->whereBetween($this->getDateColumn(), [
            session('financial_year_start'),
            session('financial_year_end')
        ]);
    }

    protected function getDateColumn()
    {
        return defined('static::DATE_COLUMN') ? static::DATE_COLUMN : 'created_at';
    }
}
