<?php

namespace SignageFlow\Hrms\Http\Controllers\Web\Core;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use SignageFlow\Hrms\Services\FinancialYearHelper;

class HrmsDocsController extends Controller
{
    public function userGuide()
    {
        return Inertia::render('Hrms/Docs/UserGuide', [
            'currentFy' => FinancialYearHelper::current(),
        ]);
    }

    public function developerGuide()
    {
        $this->middleware('can:hrmsAdmin_list');

        return Inertia::render('Hrms/Docs/DeveloperGuide', [
            'currentFy' => FinancialYearHelper::current(),
            'configSummary' => [
                'routePrefix' => config('hrms.route_prefix'),
                'kioskPrefix' => config('hrms.kiosk_prefix'),
                'mobileApiPrefix' => config('hrms.mobile_api_prefix'),
                'fyStartMonth' => config('hrms.financial_year_start_month'),
            ],
        ]);
    }
}
