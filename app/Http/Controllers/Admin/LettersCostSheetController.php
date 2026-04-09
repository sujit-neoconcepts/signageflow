<?php

namespace App\Http\Controllers\Admin;

class LettersCostSheetController extends BaseCostSheetController
{
    protected function resourceName(): string
    {
        return 'lettersCostSheet';
    }

    protected function resourceTitle(): string
    {
        return 'Letters Cost Sheet';
    }

    protected function permissionKey(): string
    {
        return 'lettersCostSheet';
    }

    protected function prodType(): string
    {
        return 'letters';
    }
}
