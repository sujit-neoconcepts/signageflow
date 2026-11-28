<?php

namespace SignageFlow\Hrms\Services;

use Carbon\Carbon;

class FinancialYearHelper
{
    /**
     * Return financial year label (Apr 1 - Mar 31), e.g., "2026-2027".
     */
    public static function forDate(Carbon|string|null $date = null): string
    {
        $carbon = $date instanceof Carbon ? $date->copy() : Carbon::parse($date ?? now());
        $startMonth = (int) config('hrms.financial_year_start_month', 4);
        $startYear = $carbon->month < $startMonth ? $carbon->year - 1 : $carbon->year;

        return $startYear.'-'.($startYear + 1);
    }

    /**
     * Return ['start' => Carbon, 'end' => Carbon] for an FY label like "2026-2027".
     */
    public static function bounds(string $financialYear): array
    {
        [$startYear] = explode('-', $financialYear);
        $startMonth = (int) config('hrms.financial_year_start_month', 4);
        $start = Carbon::create((int) $startYear, $startMonth, 1)->startOfDay();
        $end = $start->copy()->addYear()->subDay()->endOfDay();

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    public static function current(): string
    {
        if (function_exists('session') && session()->has('financial_year')) {
            return (string) session('financial_year');
        }

        return self::forDate(now());
    }

    public static function next(string $financialYear): string
    {
        [$startYear] = explode('-', $financialYear);
        $nextStart = (int) $startYear + 1;

        return $nextStart.'-'.($nextStart + 1);
    }
}
