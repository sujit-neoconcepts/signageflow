<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FrontEndController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\SigninlogController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\LogActivityController;
// Consumables Module Controllers
use App\Http\Controllers\Admin\MunitController;
use App\Http\Controllers\Admin\PgroupController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\ExpuserController;
use App\Http\Controllers\Admin\ExpcateController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\OutwardController;
use App\Http\Controllers\Admin\OpeningController;
use App\Http\Controllers\Admin\StocksController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\ConsumableInternalNameController;
use App\Http\Controllers\Admin\OpenStockController;
use App\Http\Controllers\Admin\SalesOrderController;
use App\Http\Controllers\Admin\SignageCostSheetController;
use App\Http\Controllers\Admin\CabinetCostSheetController;
use App\Http\Controllers\Admin\LettersCostSheetController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [FrontEndController::class, 'home'])->name('home');

Route::prefix('admin')->middleware(['auth', '2fa'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/expenses', [\App\Http\Controllers\Admin\DashboardController::class, 'expensesDetails'])->name('dashboard.expenses');

    Route::post('/admin/settings/change-financial-year', [SettingController::class, 'changeFinancialYear'])
        ->name('settings.change-financial-year');

        // consumableInternalName Route
        Route::get('consumableInternalName/options', [ConsumableInternalNameController::class, 'options'])->name('consumableInternalName.options');
        Route::get('consumableInternalName-import', [ConsumableInternalNameController::class, 'importView'])->name('consumableInternalName.import');
        Route::post('consumableInternalName-import', [ConsumableInternalNameController::class, 'import']);
        Route::get('consumableInternalName/sync', [ConsumableInternalNameController::class, 'sync'])->name('consumableInternalName.sync');
        Route::resource('consumableInternalName', ConsumableInternalNameController::class);
        Route::delete('consumableInternalName-bulk-destroy', [ConsumableInternalNameController::class, 'bulkDestroy'])->name('consumableInternalName.bulkDestroy');
        
        // consumableInternalNameReport Route
        Route::get('consumableInternalNameReport', [\App\Http\Controllers\Admin\ConsumableInternalNameReportController::class, 'index'])->name('consumableInternalNameReport.index');

        // CostSheet Compositions
        Route::get('costSheet/{costSheet}/compositions', [\App\Http\Controllers\Admin\CostSheetCompositionController::class, 'index'])->name('costSheetCompositions.index');
        Route::post('costSheet/{costSheet}/compositions', [\App\Http\Controllers\Admin\CostSheetCompositionController::class, 'store'])->name('costSheetCompositions.store');

    Route::get('munit-import', [MunitController::class, 'importView'])->name('munit.import');
    Route::post('munit-import', [MunitController::class, 'import']);
    Route::resource('munit', MunitController::class);
    Route::delete('munit-bulk-destroy', [MunitController::class, 'bulkDestroy'])->name('munit.bulkDestroy');

    Route::get('pgroup-import', [PgroupController::class, 'importView'])->name('pgroup.import');
    Route::post('pgroup-import', [PgroupController::class, 'import']);
    Route::resource('pgroup', PgroupController::class);
    Route::delete('pgroup-bulk-destroy', [PgroupController::class, 'bulkDestroy'])->name('pgroup.bulkDestroy');

    Route::get('location-import', [LocationController::class, 'importView'])->name('location.import');
    Route::post('location-import', [LocationController::class, 'import']);
    Route::resource('location', LocationController::class);
    Route::delete('location-bulk-destroy', [LocationController::class, 'bulkDestroy'])->name('location.bulkDestroy');

    Route::get('expuser-import', [ExpuserController::class, 'importView'])->name('expuser.import');
    Route::post('expuser-import', [ExpuserController::class, 'import']);
    Route::resource('expuser', ExpuserController::class);
    Route::delete('expuser-bulk-destroy', [ExpuserController::class, 'bulkDestroy'])->name('expuser.bulkDestroy');

    Route::get('expcate-import', [ExpcateController::class, 'importView'])->name('expcate.import');
    Route::post('expcate-import', [ExpcateController::class, 'import']);
    Route::resource('expcate', ExpcateController::class);
    Route::delete('expcate-bulk-destroy', [ExpcateController::class, 'bulkDestroy'])->name('expcate.bulkDestroy');

    // Consumables Operational Modules
    Route::get('product-import', [ProductController::class, 'importView'])->name('product.import');
    Route::post('product-import', [ProductController::class, 'import']);
    Route::resource('product', ProductController::class);
    Route::delete('product-bulk-destroy', [ProductController::class, 'bulkDestroy'])->name('product.bulkDestroy');
    Route::get('product-sync_name', [ProductController::class, 'sync_name'])->name('product.sync_name');
    Route::get('product-options', [ProductController::class, 'productOptions'])->name('product.options');

    Route::resource('purchase', PurchaseController::class);
    Route::get('purchase-itemwise', [PurchaseController::class, 'itemwiseIndex'])->name('purchase.itemwise');
    Route::get('purchase/{purchase}/detail-view', [PurchaseController::class, 'detailView'])->name('purchase.detailView');
    Route::delete('purchase-bulk-destroy', [PurchaseController::class, 'bulkDestroy'])->name('purchase.bulkDestroy');
    Route::get('/purchase/{purchase}/barcode', [PurchaseController::class, 'generateBarcode'])->name('purchase.barcode');

    Route::get('opening-import', [OpeningController::class, 'importView'])->name('opening.import');
    Route::post('opening-import', [OpeningController::class, 'import']);
    Route::resource('opening', OpeningController::class);
    Route::delete('opening-bulk-destroy', [OpeningController::class, 'bulkDestroy'])->name('opening.bulkDestroy');

    Route::resource('outward', OutwardController::class);
    Route::delete('outward-bulk-destroy', [OutwardController::class, 'bulkDestroy'])->name('outward.bulkDestroy');
    Route::post('/outward-products', [OutwardController::class, 'products'])->name('outward.products');
    Route::post('/outward-productsgroup', [OutwardController::class, 'productsgroup'])->name('outward.productsgroup');
    Route::post('/outward-productsgroup2', [OutwardController::class, 'productsgroup2'])->name('outward.productsgroup2');
    Route::post('/outward-productsloc', [OutwardController::class, 'productsloc'])->name('outward.productsloc');
    Route::get('/outward-scan', [OutwardController::class, 'scan'])->name('outward.scan');
    // New routes for reversed dependency flow
    Route::post('/outward-all-products', [OutwardController::class, 'getAllProducts'])->name('outward.allProducts');
    Route::post('/outward-incharge-for-product', [OutwardController::class, 'getInchargeForProduct'])->name('outward.inchargeForProduct');
    Route::post('/outward-location-for-product', [OutwardController::class, 'getLocationForProduct'])->name('outward.locationForProduct');
    Route::post('/outward-productgroup-for-product', [OutwardController::class, 'getProductGroupForProduct'])->name('outward.productGroupForProduct');

    // Consumables Stock Routes
    Route::get('/stocks', [StocksController::class, 'index'])->name('stocks.index');
    Route::get('/stocks/owner', [StocksController::class, 'locationIncharge'])->name('stocks.owner');
    Route::get('/stocks/level', [StocksController::class, 'stockLevels'])->name('stocks.level');
    Route::post('/stocks/threshold', [StocksController::class, 'updateThreshold'])->name('stocks.threshold');
    Route::post('/stocks/detail', [StocksController::class, 'StockDetail'])->name('stocks.detail');
    Route::post('/stocks/transfer', [StocksController::class, 'transferStock'])->name('stocks.transfer_stock');

    // Open Stock + Sales Order
    Route::resource('openStock', OpenStockController::class)->only(['index', 'create', 'store']);
    Route::post('/openStock/detail', [OpenStockController::class, 'detail'])->name('openStock.detail');
    Route::get('signageCostSheet-import', [SignageCostSheetController::class, 'importView'])->name('signageCostSheet.import');
    Route::post('signageCostSheet-import', [SignageCostSheetController::class, 'import']);
    Route::post('signageCostSheet-quick-store', [SignageCostSheetController::class, 'quickStore'])->name('signageCostSheet.quickStore');
    Route::resource('signageCostSheet', SignageCostSheetController::class)
        ->parameters(['signageCostSheet' => 'costSheet']);
    Route::delete('signageCostSheet-bulk-destroy', [SignageCostSheetController::class, 'bulkDestroy'])->name('signageCostSheet.bulkDestroy');
    Route::get('cabinetCostSheet-import', [CabinetCostSheetController::class, 'importView'])->name('cabinetCostSheet.import');
    Route::post('cabinetCostSheet-import', [CabinetCostSheetController::class, 'import']);
    Route::post('cabinetCostSheet-quick-store', [CabinetCostSheetController::class, 'quickStore'])->name('cabinetCostSheet.quickStore');
    Route::resource('cabinetCostSheet', CabinetCostSheetController::class)
        ->parameters(['cabinetCostSheet' => 'costSheet']);
    Route::delete('cabinetCostSheet-bulk-destroy', [CabinetCostSheetController::class, 'bulkDestroy'])->name('cabinetCostSheet.bulkDestroy');
    Route::get('lettersCostSheet-import', [LettersCostSheetController::class, 'importView'])->name('lettersCostSheet.import');
    Route::post('lettersCostSheet-import', [LettersCostSheetController::class, 'import']);
    Route::post('lettersCostSheet-quick-store', [LettersCostSheetController::class, 'quickStore'])->name('lettersCostSheet.quickStore');
    Route::resource('lettersCostSheet', LettersCostSheetController::class)
        ->parameters(['lettersCostSheet' => 'costSheet']);
    Route::delete('lettersCostSheet-bulk-destroy', [LettersCostSheetController::class, 'bulkDestroy'])->name('lettersCostSheet.bulkDestroy');
    Route::resource('salesOrder', SalesOrderController::class);
    Route::post('/salesOrder/detail', [SalesOrderController::class, 'detail'])->name('salesOrder.detail');
    Route::get('/salesOrder/{salesOrder}/print', [SalesOrderController::class, 'print'])->name('salesOrder.print');
    Route::delete('salesOrder-bulk-destroy', [SalesOrderController::class, 'bulkDestroy'])->name('salesOrder.bulkDestroy');

    Route::resource('enquiry', \App\Http\Controllers\Admin\EnquiryController::class);
    Route::post('/enquiry/detail', [\App\Http\Controllers\Admin\EnquiryController::class, 'detail'])->name('enquiry.detail');
    Route::get('/enquiry/{enquiry}/print', [\App\Http\Controllers\Admin\EnquiryController::class, 'print'])->name('enquiry.print');
    Route::delete('enquiry-bulk-destroy', [\App\Http\Controllers\Admin\EnquiryController::class, 'bulkDestroy'])->name('enquiry.bulkDestroy');
    Route::post('/enquiry/{enquiry}/files', [\App\Http\Controllers\Admin\EnquiryController::class, 'uploadFiles'])->name('enquiry.files.upload');
    Route::post('/enquiry-temp-files', [\App\Http\Controllers\Admin\EnquiryController::class, 'uploadTempFiles'])->name('enquiry.temp.files.upload');
    Route::delete('/enquiry-file/{enquiryFile}', [\App\Http\Controllers\Admin\EnquiryController::class, 'deleteFile'])->name('enquiry.files.delete');
    Route::get('/enquiry-file/{enquiryFile}/download', [\App\Http\Controllers\Admin\EnquiryController::class, 'downloadFile'])->name('enquiry.files.download');
    Route::get('/enquiry/{enquiry}/push-to-sales-order', [\App\Http\Controllers\Admin\EnquiryController::class, 'pushToSalesOrder'])->name('enquiry.pushToSalesOrder');

    // Consumables Expense Routes
    Route::resource('expense', ExpenseController::class);
    Route::delete('expense-bulk-destroy', [ExpenseController::class, 'bulkDestroy'])->name('expense.bulkDestroy');

    // Client and Supplier Management (needed for purchases/outwards)
    Route::get('client/import', [ClientController::class, 'importView'])->name('client.import');
    Route::post('client/import', [ClientController::class, 'import']);
    Route::resource('client', ClientController::class);
    Route::delete('client-bulk-destroy', [ClientController::class, 'bulkDestroy'])->name('client.bulkDestroy');

    Route::resource('supplier', SupplierController::class);
    Route::get('supplier-import', [SupplierController::class, 'importView'])->name('supplier.import');
    Route::post('supplier-import', [SupplierController::class, 'import']);
    Route::delete('supplier-bulk-destroy', [SupplierController::class, 'bulkDestroy'])->name('supplier.bulkDestroy');

    // User Management
    Route::resource('user', UserController::class);
    Route::delete('userauthdestroy', [UserController::class, 'authDestroy'])->name('user.authDestroy');
    Route::get('user/{user}/permissions', [UserController::class, 'permissions'])->name('user.permissions');
    Route::put('/user-permissions-update', [UserController::class, 'permissionsUpdate'])->name('user.permissionsUpdate');

    Route::resource('role', RoleController::class);
    Route::resource('permission', PermissionController::class);

    Route::resource('activityLog', LogActivityController::class);
    Route::delete('activityLog-bulk-destroy', [LogActivityController::class, 'bulkDestroy'])->name('activityLog.bulkDestroy');
    Route::put('activityLog-field-update', [LogActivityController::class, 'fieldUpdate'])->name('activityLog.fieldUpdate');

    Route::resource('setting', SettingController::class);
    Route::get('setting-list', [SettingController::class, 'list'])->name('setting.list');
    Route::delete('setting-auth-destroy', [SettingController::class, 'authDestroy'])->name('setting.authDestroy');
    Route::put('/setting-bulk-update', [SettingController::class, 'bulkUpdate'])->name('setting.bulkUpdate');

    Route::resource('signinLog', SigninlogController::class);
    Route::delete('signinLog-bulk-destroy', [SigninlogController::class, 'bulkDestroy'])->name('signinLog.bulkDestroy');
    Route::get('/profile', [UserController::class, 'profile'])->name('profile.profile');
    Route::put('/update-profile', [UserController::class, 'updateProfile'])->name('profile.updateProfile');
});

Route::get('logs', [\Rap2hpoutre\LaravelLogViewer\LogViewerController::class, 'index']);

require __DIR__ . '/auth.php';
