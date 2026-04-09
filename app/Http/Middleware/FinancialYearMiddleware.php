<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class FinancialYearMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Default financial year if not set
        if (!session()->has('financial_year')) {
            $currentYear = date('Y');
            $month = date('n');

            // If current month is Jan-March, take previous year as start
            $startYear = ($month <= 3) ? $currentYear - 1 : $currentYear;

            session([
                'financial_year_start' => $startYear . '-04-01',
                'financial_year_end' => ($startYear + 1) . '-03-31',
                'financial_year' => $startYear . '-' . ($startYear + 1)
            ]);
        }

        return $next($request);
    }
}
