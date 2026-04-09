<?php

namespace App\Http\Controllers\Admin;

class CabinetCostSheetController extends BaseCostSheetController
{
    protected function resourceName(): string
    {
        return 'cabinetCostSheet';
    }

    protected function resourceTitle(): string
    {
        return 'Cabinet Cost Sheet';
    }

    protected function permissionKey(): string
    {
        return 'cabinetCostSheet';
    }

    protected function prodType(): string
    {
        return 'cabinet';
    }
}
