<?php

namespace App\Http\Controllers\Admin;

class SignageCostSheetController extends BaseCostSheetController
{
    protected function resourceName(): string
    {
        return 'signageCostSheet';
    }

    protected function resourceTitle(): string
    {
        return 'Signage Cost Sheet';
    }

    protected function permissionKey(): string
    {
        return 'signageCostSheet';
    }

    protected function prodType(): string
    {
        return 'signage';
    }
}
