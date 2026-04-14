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

use Illuminate\Support\Facades\Auth;

// Authentication (Email OTP)
Route::prefix('auth')->name('auth.')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('request-otp', [AuthController::class, 'requestOtp'])->name('request-otp');
    Route::get('verify-otp', [AuthController::class, 'showVerifyOtp'])->name('verify-otp');
    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->name('verify-otp.submit');
    Route::match(['get', 'post'], 'logout', [AuthController::class, 'logout'])->name('logout');
});

Route::get('/', fn() => redirect()->route('auth.login'));

Route::get('auth/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect()->route('auth.login');
})->name('auth.logout');

Route::middleware(['auth'])->group(function () {

    // ADMIN
    Route::prefix('admin')->name('admin.')->middleware('user.type:admin')->group(function () {
        Route::get('dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

        // ── User Management ──
        Route::get('users', [AdminController::class, 'users'])->name('users');
        Route::get('users/create', [AdminController::class, 'createUser'])->name('users.create');
        Route::get('users/export', [AdminController::class, 'exportUsers'])->name('users.export');
        Route::post('users', [AdminController::class, 'storeUser'])->name('users.store');
        Route::post('users/bulk-action', [AdminController::class, 'bulkUserAction'])->name('users.bulk-action');
        Route::get('users/{user}', [AdminController::class, 'showUser'])->name('users.show');
        Route::get('users/{user}/edit', [AdminController::class, 'editUser'])->name('users.edit');
        Route::put('users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::post('users/{user}/status/{status}', [AdminController::class, 'toggleUserStatus'])->name('users.toggle-status');
        Route::delete('users/{user}', [AdminController::class, 'deleteUser'])->name('users.delete');
        Route::post('users/{userId}/restore', [AdminController::class, 'restoreUser'])->name('users.restore');
        Route::delete('users/{userId}/force-delete', [AdminController::class, 'forceDeleteUser'])->name('users.force-delete');

        // ── User Permissions (NEW) ──
        Route::get('users/{user}/permissions', [AdminController::class, 'manageUserPermissions'])->name('users.permissions');
        Route::put('users/{user}/permissions', [AdminController::class, 'updateUserPermissions'])->name('users.permissions.update');

        // ── Vendors ──
        Route::get('vendors/pending', [AdminController::class, 'pendingVendors'])->name('vendors.pending');
        Route::get('vendors/{vendor}', [AdminController::class, 'showVendor'])->name('vendors.show');
        Route::post('vendors/{vendor}/approve', [AdminController::class, 'approveVendor'])->name('vendors.approve');
        Route::post('vendors/{vendor}/waive-membership', [AdminController::class, 'waiveMembership'])->name('vendors.waive-membership');

        // ── Roles & Permissions ──
        Route::get('roles', [AdminController::class, 'roles'])->name('roles');
        Route::post('roles', [AdminController::class, 'storeRole'])->name('roles.store');
        Route::get('roles/{role}/edit', [AdminController::class, 'editRole'])->name('roles.edit');
        Route::put('roles/{role}', [AdminController::class, 'updateRole'])->name('roles.update');
        Route::delete('roles/{role}', [AdminController::class, 'deleteRole'])->name('roles.delete');

        // ── Masters ──
        Route::get('categories', [AdminController::class, 'categories'])->name('categories');
        Route::post('categories', [AdminController::class, 'storeCategory'])->name('categories.store');
        Route::get('sales-channels', [AdminController::class, 'salesChannels'])->name('sales-channels');
        Route::post('sales-channels', [AdminController::class, 'storeSalesChannel'])->name('sales-channels.store');
        Route::get('warehouses', [AdminController::class, 'warehouses'])->name('warehouses');
        Route::post('warehouses', [AdminController::class, 'storeWarehouse'])->name('warehouses.store');

        // ── System ──
        Route::post('live-sheets/{liveSheet}/unlock', [AdminController::class, 'unlockLiveSheet'])->name('live-sheets.unlock');
        Route::get('activity-log', [AdminController::class, 'activityLog'])->name('activity-log');
    });

    // VENDOR (External)
    Route::prefix('vendor')->name('vendor.')->middleware('user.type:external')->group(function () {
        // Always accessible (before KYC approval)
        Route::get('dashboard', [VendorController::class, 'dashboard'])->name('dashboard');
        Route::get('kyc', [VendorController::class, 'kycForm'])->name('kyc');
        Route::post('kyc', [VendorController::class, 'submitKyc'])->name('kyc.submit');
        Route::get('contract/download', [VendorController::class, 'downloadContract'])->name('contract.download');

        // Protected — requires KYC approval
        Route::middleware('vendor.kyc.approved')->group(function () {
            Route::get('offer-sheets', [VendorController::class, 'offerSheets'])->name('offer-sheets');
            Route::get('offer-sheets/template', [VendorController::class, 'downloadOfferSheetTemplate'])->name('offer-sheets.template');
            Route::get('offer-sheets/create', [VendorController::class, 'createOfferSheet'])->name('offer-sheets.create');
            Route::post('offer-sheets', [VendorController::class, 'storeOfferSheet'])->name('offer-sheets.store');
            Route::get('offer-sheets/{offerSheet}', [VendorController::class, 'showOfferSheet'])->name('offer-sheets.show');
            Route::get('offer-sheets/{offerSheet}/download', [VendorController::class, 'downloadOfferSheet'])->name('offer-sheets.download');
            Route::post('offer-sheet/upload-image', [VendorController::class, 'uploadImage'])->name('offer-sheet.upload-image');
            Route::get('consignments', [VendorController::class, 'consignments'])->name('consignments');
            Route::post('consignments/{consignment}/inspection', [VendorController::class, 'uploadInspection'])->name('inspections.upload');
            Route::post('consignments/{consignment}/commercial-invoice', [VendorController::class, 'uploadCommercialInvoice'])->name('commercial-invoices.upload');

            Route::get('inspections', [VendorController::class, 'inspectionReports'])->name('inspections.index');
            Route::get('live-sheets', [VendorController::class, 'liveSheets'])->name('live-sheets');
            Route::get('live-sheets/blank-template', [VendorController::class, 'downloadLiveSheetBlankTemplate'])->name('live-sheets.blank-template');
            Route::get('live-sheets/{liveSheet}/edit', [VendorController::class, 'editLiveSheet'])->name('live-sheets.edit');
            Route::get('live-sheets/{liveSheet}/history', [VendorController::class, 'liveSheetHistory'])->name('live-sheets.history');
            Route::get('live-sheets/{liveSheet}/download', [VendorController::class, 'downloadLiveSheetTemplate'])->name('live-sheets.download');
            Route::post('live-sheets/{liveSheet}/upload', [VendorController::class, 'uploadLiveSheet'])->name('live-sheets.upload');
            Route::post('live-sheets/{liveSheet}', [VendorController::class, 'submitLiveSheet'])->name('live-sheets.submit');
            Route::post('live-sheets/{liveSheet}/create-consignment', [VendorController::class, 'createConsignment'])->name('live-sheets.create-consignment');
            Route::post('live-sheets/{liveSheet}/dates', [VendorController::class, 'saveLiveSheetDates'])->name('vendor.live-sheets.dates');
            Route::get('sales', [VendorController::class, 'salesReport'])->name('sales');
            Route::get('chargebacks', [VendorController::class, 'chargebacks'])->name('chargebacks');
            Route::get('payouts', [VendorController::class, 'payouts'])->name('payouts');
            Route::post('payouts/{payout}/invoice', [VendorController::class, 'uploadInvoice'])->name('payouts.invoice');
        });
    });

    // SOURCING
    Route::prefix('sourcing')->name('sourcing.')->middleware('user.type:internal,admin', 'department:sourcing')->group(function () {
        Route::get('dashboard', [SourcingController::class, 'dashboard'])->name('dashboard');
        Route::get('vendors', [SourcingController::class, 'vendors'])->name('vendors');
        Route::get('vendors/create', [SourcingController::class, 'createVendor'])->name('vendors.create');
        Route::get('vendors/{vendor}', [SourcingController::class, 'showVendor'])->name('vendors.show');
        Route::post('vendors', [SourcingController::class, 'storeVendor'])->name('vendors.store');

        // Step 1: Offer Sheet — Review & Select Products
        Route::get('offer-sheets', [SourcingController::class, 'offerSheets'])->name('offer-sheets');
        Route::get('offer-sheets/{offerSheet}/review', [SourcingController::class, 'reviewOfferSheet'])->name('offer-sheets.review');
        Route::post('offer-sheets/{offerSheet}/select', [SourcingController::class, 'selectProducts'])->name('offer-sheets.select');

        // Step 2: Create Live Sheet (from selected offer sheet)
        Route::post('offer-sheets/{offerSheet}/create-live-sheet', [SourcingController::class, 'createLiveSheet'])->name('offer-sheets.create-live-sheet');

        // Step 3: Live Sheet — Review, Approve & Lock
        Route::get('live-sheets', [SourcingController::class, 'liveSheets'])->name('live-sheets');
        Route::get('live-sheets/{liveSheet}', [SourcingController::class, 'showLiveSheet'])->name('live-sheets.show');
        Route::post('live-sheets/{liveSheet}/approve', [SourcingController::class, 'approveLiveSheet'])->name('live-sheets.approve');
        Route::post('live-sheets/{liveSheet}/update-sourcing', [SourcingController::class, 'updateSourcingFields'])->name('live-sheets.update-sourcing');
        Route::get('live-sheets/{liveSheet}/history', [SourcingController::class, 'liveSheetHistory'])->name('live-sheets.history');

        // Step 4: Create Consignment (from approved live sheet)
        Route::post('live-sheets/{liveSheet}/create-consignment', [SourcingController::class, 'createConsignment'])->name('live-sheets.create-consignment');

        // Consignments
        Route::get('consignments', [SourcingController::class, 'consignments'])->name('consignments');
        Route::get('consignments/{consignment}', [SourcingController::class, 'showConsignment'])->name('consignments.show');

        // Quality Inspections
        Route::get('inspections', [SourcingController::class, 'inspections'])->name('inspections');
        Route::get('inspections/{consignment}/upload', [SourcingController::class, 'uploadInspection'])->name('inspections.upload');
        Route::post('inspections/{consignment}', [SourcingController::class, 'storeInspection'])->name('inspections.store');
        Route::get('inspections/{inspection}', [SourcingController::class, 'showInspection'])->name('inspections.show');
        Route::delete('inspections/{inspection}', [SourcingController::class, 'deleteInspection'])->name('inspections.delete');

        // Chargebacks
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
        Route::get('grn/{grn}/show', [LogisticsController::class, 'showGrn'])->name('grn.show');
        Route::get('grn/{shipment}/upload', [LogisticsController::class, 'uploadGrn'])->name('grn.upload');
        Route::post('grn/{shipment}', [LogisticsController::class, 'storeGrn'])->name('grn.store');
        Route::get('asn/{asn}/download', [LogisticsController::class, 'downloadAsn'])->name('asn.download');
        Route::get('inventory', [LogisticsController::class, 'inventory'])->name('inventory');
        Route::get('inventory/download', [LogisticsController::class, 'downloadInventory'])->name('inventory.download');
        Route::get('inventory/ageing', [LogisticsController::class, 'inventoryAgeing'])->name('inventory.ageing');
        Route::get('inventory/allocation', [LogisticsController::class, 'warehouseAllocation'])->name('inventory.allocation');
        Route::post('inventory/transfer', [LogisticsController::class, 'transferInventory'])->name('inventory.transfer');
        Route::get('warehouse-charges', [LogisticsController::class, 'warehouseCharges'])->name('warehouse-charges');
        Route::get('warehouse-charges/vendor-allocation', [LogisticsController::class, 'vendorChargeAllocation'])->name('warehouse-charges.vendor-allocation');
        Route::post('warehouse-charges/{charge}/receipt', [LogisticsController::class, 'uploadChargeReceipt'])->name('warehouse-charges.receipt');
        Route::post('warehouse-charges/calculate', [LogisticsController::class, 'calculateCharges'])->name('warehouse-charges.calculate');
        Route::post('warehouse-charges/bulk-calculate', [LogisticsController::class, 'bulkCalculateCharges'])->name('warehouse-charges.bulk-calculate');
        Route::get('rate-cards', [LogisticsController::class, 'rateCards'])->name('rate-cards');
        Route::put('rate-cards/{warehouse}', [LogisticsController::class, 'updateRateCard'])->name('rate-cards.update');
    });

    // CATALOGUING
    Route::prefix('cataloguing')->name('cataloguing.')->middleware('user.type:internal,admin', 'department:cataloguing')->group(function () {
        Route::get('dashboard', [CatalogueController::class, 'dashboard'])->name('dashboard');
        Route::get('pricing-sheets', [CatalogueController::class, 'pricingSheets'])->name('pricing-sheets');
        Route::get('pricing-sheets/download', [CatalogueController::class, 'downloadPricingSheet'])->name('pricing-sheets.download');
        Route::post('catalogue/upload', [CatalogueController::class, 'uploadCatalogue'])->name('catalogue.upload');
        Route::get('listing-panel', [CatalogueController::class, 'listingPanel'])->name('listing-panel');
        Route::post('listings/update', [CatalogueController::class, 'updateListings'])->name('listings.update');
        Route::get('sku-dashboard', [CatalogueController::class, 'skuDashboard'])->name('sku-dashboard');
    });


    // SALES
    Route::prefix('sales')->name('sales.')->middleware('user.type:internal,admin', 'department:sales')->group(function () {
        Route::get('dashboard', [SalesController::class, 'dashboard'])->name('dashboard');
        Route::get('orders', [SalesController::class, 'orders'])->name('orders');
        Route::get('orders/{order}', [SalesController::class, 'showOrder'])->name('orders.show');
        Route::get('upload', [SalesController::class, 'uploadSales'])->name('upload');
        Route::post('upload', [SalesController::class, 'storeSales'])->name('upload.store');
        Route::get('download-template', [SalesController::class, 'downloadTemplate'])->name('download-template');
        Route::post('orders/{order}/tracking', [SalesController::class, 'updateTracking'])->name('orders.tracking');
        Route::post('orders/bulk-tracking', [SalesController::class, 'bulkUpdateTracking'])->name('orders.bulk-tracking');
    });


    // FINANCE
    Route::prefix('finance')->name('finance.')->middleware('user.type:internal,admin', 'department:finance')->group(function () {
        Route::get('dashboard', [FinanceController::class, 'dashboard'])->name('dashboard');
        Route::get('kyc', [FinanceController::class, 'pendingKyc'])->name('kyc');
        Route::post('kyc/{vendor}/approve', [FinanceController::class, 'approveKyc'])->name('kyc.approve');
        Route::post('kyc/{vendor}/reject', [FinanceController::class, 'rejectKyc'])->name('kyc.reject');
        Route::get('receivables', [FinanceController::class, 'receivables'])->name('receivables');
        Route::get('receivables/download', [FinanceController::class, 'downloadReceivablesTemplate'])->name('receivables.download');
        Route::post('receivables/{receivable}/deductions', [FinanceController::class, 'updateDeductions'])->name('receivables.deductions');
        Route::post('receivables/{receivable}/payment', [FinanceController::class, 'recordPayment'])->name('receivables.payment');
        Route::get('chargebacks', [FinanceController::class, 'chargebacks'])->name('chargebacks');

        Route::post('orders/{orderNumber}/chargeback', [FinanceController::class, 'raiseChargeback'])->name('orders.chargeback');
        //        Route::post('orders/{order}/chargeback', [FinanceController::class, 'raiseChargeback'])->name('chargebacks.raise');
        Route::get('payouts', [FinanceController::class, 'payouts'])->name('payouts');
        Route::get('payouts/{payout}', [FinanceController::class, 'showPayout'])->name('payouts.show');
        Route::post('payouts/calculate', [FinanceController::class, 'calculatePayout'])->name('payouts.calculate');
        Route::post('payouts/{payout}/process', [FinanceController::class, 'processPayment'])->name('payouts.process');
        Route::get('payouts/{payout}/advice', [FinanceController::class, 'downloadPaymentAdvice'])->name('payouts.advice');
        Route::post('payouts/{payout}/invoice', [FinanceController::class, 'uploadVendorInvoice'])->name('payouts.invoice');
        Route::get('pricing-review', [FinanceController::class, 'pricingReview'])->name('pricing-review');
        Route::post('pricing/{asn}/approve', [FinanceController::class, 'approvePricing'])->name('pricing.approve');

        // Live Sheets — SAP Code Update
        Route::get('live-sheets', [FinanceController::class, 'liveSheets'])->name('live-sheets');
        Route::get('live-sheets/{liveSheet}', [FinanceController::class, 'showLiveSheet'])->name('live-sheets.show');
        Route::post('live-sheets/{liveSheet}/sap', [FinanceController::class, 'updateSapCodes'])->name('live-sheets.sap');

        // NEW — Download pre-filled SAP Excel template
        Route::get('/live-sheets/{liveSheet}/sap-download', [FinanceController::class, 'downloadSapTemplate'])
            ->name('live-sheets.sap-download');   // → finance.live-sheets.sap-download

        // NEW — Upload filled SAP Excel and apply codes
        Route::post('/live-sheets/{liveSheet}/sap-upload', [FinanceController::class, 'uploadSapCodes'])
            ->name('live-sheets.sap-upload');     // → finance.live-sheets.sap-upload

    });


    // HOD / MANAGEMENT
    Route::prefix('hod')->name('hod.')->middleware('user.type:internal,admin', 'department:hod')->group(function () {
        Route::get('dashboard', [HodController::class, 'dashboard'])->name('dashboard');
        Route::get('asn', [HodController::class, 'asnList'])->name('asn-list');
        Route::get('pricing/{asn}/prepare', [HodController::class, 'preparePricing'])->name('pricing.prepare');
        Route::get('pricing/{asn}/status', [HodController::class, 'pricingStatus'])->name('pricing.status');
        Route::post('pricing/{asn}', [HodController::class, 'storePricing'])->name('pricing.store');
        Route::post('pricing/{asn}/finalize', [HodController::class, 'finalizePricing'])->name('pricing.finalize');
    });

    // NOTIFICATIONS
    Route::get('notifications', fn() => view('components.notifications', ['notifications' => auth()->user()->unreadNotifications()->take(50)->get()]))->name('notifications');
    Route::post('notifications/{id}/read', fn($id) => tap(back(), fn() => auth()->user()->notifications()->where('id', $id)->update(['read_at' => now()])))->name('notifications.read');
    Route::post('notifications/mark-all-read', fn() => tap(back(), fn() => auth()->user()->unreadNotifications->markAsRead()))->name('notifications.mark-all-read');
});
