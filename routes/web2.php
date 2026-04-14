<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Vendor\VendorController;
use App\Http\Controllers\Sourcing\SourcingController;
use App\Http\Controllers\Logistics\LogisticsController;
use App\Http\Controllers\Cataloguing\CatalogueController;
use App\Http\Controllers\Sales\SalesController;
use App\Http\Controllers\Finance\FinanceController;
use App\Http\Controllers\Hod\HodController;

// Authentication (Email OTP)
Route::prefix('auth')->name('auth.')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('request-otp', [AuthController::class, 'requestOtp'])->name('request-otp');
    Route::get('verify-otp', [AuthController::class, 'showVerifyOtp'])->name('verify-otp');
    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->name('verify-otp.submit');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
});

Route::get('/', fn() => redirect()->route('auth.login'));

Route::middleware(['auth'])->group(function () {

    // ADMIN
    Route::prefix('admin')->name('admin.')->middleware('user.type:admin')->group(function () {
        Route::get('dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('users', [AdminController::class, 'users'])->name('users');
        Route::get('users/create', [AdminController::class, 'createUser'])->name('users.create');
        
Route::get('users/export', [AdminController::class, 'exportUsers'])->name('users.export');

Route::post('users/bulk-action', [AdminController::class, 'bulkUserAction'])->name('users.bulk-action');

Route::get('users/{user}', [AdminController::class, 'showUser'])->name('users.show');


Route::post('users/{user}/status/{status}', [AdminController::class, 'toggleUserStatus'])->name('users.toggle-status');
Route::delete('users/{user}', [AdminController::class, 'deleteUser'])->name('users.delete');
Route::post('users/{userId}/restore', [AdminController::class, 'restoreUser'])->name('users.restore');
Route::delete('users/{userId}/force-delete', [AdminController::class, 'forceDeleteUser'])->name('users.force-delete');

 
        Route::post('users', [AdminController::class, 'storeUser'])->name('users.store');
        Route::get('users/{user}/edit', [AdminController::class, 'editUser'])->name('users.edit');
        Route::put('users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::get('vendors/pending', [AdminController::class, 'pendingVendors'])->name('vendors.pending');
        Route::post('vendors/{vendor}/approve', [AdminController::class, 'approveVendor'])->name('vendors.approve');
        Route::post('vendors/{vendor}/waive-membership', [AdminController::class, 'waiveMembership'])->name('vendors.waive-membership');
        Route::get('roles', [AdminController::class, 'roles'])->name('roles');
        Route::post('roles', [AdminController::class, 'storeRole'])->name('roles.store');
        Route::get('categories', [AdminController::class, 'categories'])->name('categories');
        Route::post('categories', [AdminController::class, 'storeCategory'])->name('categories.store');
        Route::get('sales-channels', [AdminController::class, 'salesChannels'])->name('sales-channels');
        Route::post('sales-channels', [AdminController::class, 'storeSalesChannel'])->name('sales-channels.store');
        Route::get('warehouses', [AdminController::class, 'warehouses'])->name('warehouses');
        Route::post('warehouses', [AdminController::class, 'storeWarehouse'])->name('warehouses.store');
        Route::post('live-sheets/{liveSheet}/unlock', [AdminController::class, 'unlockLiveSheet'])->name('live-sheets.unlock');
        Route::get('activity-log', [AdminController::class, 'activityLog'])->name('activity-log');
    });

    // VENDOR (External)
    Route::prefix('vendor')->name('vendor.')->middleware('user.type:external')->group(function () {
        Route::get('dashboard', [VendorController::class, 'dashboard'])->name('dashboard');
        Route::get('kyc', [VendorController::class, 'kycForm'])->name('kyc');
        Route::post('kyc', [VendorController::class, 'submitKyc'])->name('kyc.submit');
        Route::get('offer-sheets', [VendorController::class, 'offerSheets'])->name('offer-sheets');
        Route::post('offer-sheets', [VendorController::class, 'storeOfferSheet'])->name('offer-sheets.store');
        Route::post('live-sheets/{liveSheet}', [VendorController::class, 'submitLiveSheet'])->name('live-sheets.submit');
        Route::get('consignments', [VendorController::class, 'consignments'])->name('consignments');
        Route::post('consignments/{consignment}/inspection', [VendorController::class, 'uploadInspection'])->name('inspections.upload');
        Route::get('sales', [VendorController::class, 'salesReport'])->name('sales');
        Route::get('payouts', [VendorController::class, 'payouts'])->name('payouts');
        Route::post('payouts/{payout}/invoice', [VendorController::class, 'uploadInvoice'])->name('payouts.invoice');
    });

    // SOURCING
    Route::prefix('sourcing')->name('sourcing.')->middleware('user.type:internal,admin', 'department:sourcing')->group(function () {
        Route::get('dashboard', [SourcingController::class, 'dashboard'])->name('dashboard');
        Route::get('vendors', [SourcingController::class, 'vendors'])->name('vendors');
        Route::get('vendors/create', [SourcingController::class, 'createVendor'])->name('vendors.create');
        Route::post('vendors', [SourcingController::class, 'storeVendor'])->name('vendors.store');
        Route::get('offer-sheets', [SourcingController::class, 'offerSheets'])->name('offer-sheets');
        Route::get('offer-sheets/{offerSheet}/review', [SourcingController::class, 'reviewOfferSheet'])->name('offer-sheets.review');
        Route::post('offer-sheets/{offerSheet}/select', [SourcingController::class, 'selectProducts'])->name('offer-sheets.select');
        Route::post('offer-sheets/{offerSheet}/convert', [SourcingController::class, 'convertToConsignment'])->name('offer-sheets.convert');
        Route::get('consignments', [SourcingController::class, 'consignments'])->name('consignments');
        Route::get('live-sheets', [SourcingController::class, 'liveSheets'])->name('live-sheets');
        Route::post('live-sheets/{liveSheet}/approve', [SourcingController::class, 'approveLiveSheet'])->name('live-sheets.approve');
        Route::get('chargebacks', [SourcingController::class, 'pendingChargebacks'])->name('chargebacks');
        Route::post('chargebacks/{chargeback}/confirm', [SourcingController::class, 'confirmChargeback'])->name('chargebacks.confirm');
    });

    // LOGISTICS
    Route::prefix('logistics')->name('logistics.')->middleware('user.type:internal,admin', 'department:logistics')->group(function () {
        Route::get('dashboard', [LogisticsController::class, 'dashboard'])->name('dashboard');
        Route::get('container-planning', [LogisticsController::class, 'containerPlanning'])->name('container-planning');
        Route::post('shipments/create', [LogisticsController::class, 'createShipment'])->name('shipments.create');
        Route::get('shipments', [LogisticsController::class, 'shipments'])->name('shipments');
        Route::get('shipments/{shipment}', [LogisticsController::class, 'showShipment'])->name('shipments.show');
        Route::post('shipments/{shipment}/lock', [LogisticsController::class, 'lockShipment'])->name('shipments.lock');
        Route::get('grn', [LogisticsController::class, 'grnList'])->name('grn');
        Route::get('grn/{shipment}/upload', [LogisticsController::class, 'uploadGrn'])->name('grn.upload');
        Route::post('grn/{shipment}', [LogisticsController::class, 'storeGrn'])->name('grn.store');
        Route::get('asn/{asn}/download', [LogisticsController::class, 'downloadAsn'])->name('asn.download');
        Route::get('inventory', [LogisticsController::class, 'inventory'])->name('inventory');
        Route::post('inventory/transfer', [LogisticsController::class, 'transferInventory'])->name('inventory.transfer');
        Route::get('warehouse-charges', [LogisticsController::class, 'warehouseCharges'])->name('warehouse-charges');
        Route::post('warehouse-charges/{charge}/receipt', [LogisticsController::class, 'uploadChargeReceipt'])->name('warehouse-charges.receipt');
        Route::post('warehouse-charges/calculate', [LogisticsController::class, 'calculateCharges'])->name('warehouse-charges.calculate');
    });

    // CATALOGUING
    Route::prefix('cataloguing')->name('cataloguing.')->middleware('user.type:internal,admin', 'department:cataloguing')->group(function () {
        Route::get('dashboard', [CatalogueController::class, 'dashboard'])->name('dashboard');
        Route::get('pricing-sheets', [CatalogueController::class, 'pricingSheets'])->name('pricing-sheets');
        Route::get('listing-panel', [CatalogueController::class, 'listingPanel'])->name('listing-panel');
        Route::post('listings/update', [CatalogueController::class, 'updateListings'])->name('listings.update');
        Route::get('sku-dashboard', [CatalogueController::class, 'skuDashboard'])->name('sku-dashboard');
    });

    // SALES
    Route::prefix('sales')->name('sales.')->middleware('user.type:internal,admin', 'department:sales')->group(function () {
        Route::get('dashboard', [SalesController::class, 'dashboard'])->name('dashboard');
        Route::get('orders', [SalesController::class, 'orders'])->name('orders');
        Route::get('upload', [SalesController::class, 'uploadSales'])->name('upload');
        Route::post('upload', [SalesController::class, 'storeSales'])->name('upload.store');
        Route::post('orders/{order}/tracking', [SalesController::class, 'updateTracking'])->name('orders.tracking');
    });

    // FINANCE
    Route::prefix('finance')->name('finance.')->middleware('user.type:internal,admin', 'department:finance')->group(function () {
        Route::get('dashboard', [FinanceController::class, 'dashboard'])->name('dashboard');
        Route::get('kyc', [FinanceController::class, 'pendingKyc'])->name('kyc');
        Route::post('kyc/{vendor}/approve', [FinanceController::class, 'approveKyc'])->name('kyc.approve');
        Route::post('kyc/{vendor}/reject', [FinanceController::class, 'rejectKyc'])->name('kyc.reject');
        Route::get('receivables', [FinanceController::class, 'receivables'])->name('receivables');
        Route::post('receivables/{receivable}/deductions', [FinanceController::class, 'updateDeductions'])->name('receivables.deductions');
        Route::post('receivables/{receivable}/payment', [FinanceController::class, 'recordPayment'])->name('receivables.payment');
        Route::get('chargebacks', [FinanceController::class, 'chargebacks'])->name('chargebacks');
        Route::post('orders/{order}/chargeback', [FinanceController::class, 'raiseChargeback'])->name('chargebacks.raise');
        Route::get('payouts', [FinanceController::class, 'payouts'])->name('payouts');
        Route::post('payouts/calculate', [FinanceController::class, 'calculatePayout'])->name('payouts.calculate');
        Route::post('payouts/{payout}/process', [FinanceController::class, 'processPayment'])->name('payouts.process');
        Route::get('pricing-review', [FinanceController::class, 'pricingReview'])->name('pricing-review');
        Route::post('pricing/{asn}/approve', [FinanceController::class, 'approvePricing'])->name('pricing.approve');
    });

    // HOD / MANAGEMENT
    Route::prefix('hod')->name('hod.')->middleware('user.type:internal,admin', 'department:hod')->group(function () {
        Route::get('dashboard', [HodController::class, 'dashboard'])->name('dashboard');
        Route::get('asn', [HodController::class, 'asnList'])->name('asn-list');
        Route::get('pricing/{asn}/prepare', [HodController::class, 'preparePricing'])->name('pricing.prepare');
        Route::post('pricing/{asn}', [HodController::class, 'storePricing'])->name('pricing.store');
        Route::post('pricing/{asn}/finalize', [HodController::class, 'finalizePricing'])->name('pricing.finalize');
    });

    // NOTIFICATIONS (shared)
    Route::get('notifications', fn() => view('components.notifications', ['notifications' => auth()->user()->unreadNotifications()->take(50)->get()]))->name('notifications');
    Route::post('notifications/{id}/read', fn($id) => tap(back(), fn() => auth()->user()->notifications()->where('id', $id)->update(['read_at' => now()])))->name('notifications.read');
    Route::post('notifications/mark-all-read', fn() => tap(back(), fn() => auth()->user()->unreadNotifications->markAsRead()))->name('notifications.mark-all-read');
});
