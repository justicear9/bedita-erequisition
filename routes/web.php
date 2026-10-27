<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DepartmentController as AdminDepartmentController;
use App\Http\Controllers\Admin\SignatureController as AdminSignatureController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DelegationController;
use App\Http\Controllers\Inventory\ReportController as InventoryReportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductSearchController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RequisitionController;
use App\Http\Controllers\RequisitionPrintController;
use App\Http\Controllers\SignatureController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/products/search', ProductSearchController::class)->name('products.search');

    Route::resource('requisitions', RequisitionController::class)->except(['destroy', 'index']);

    Route::post('/requisitions/{requisition}/submit', [RequisitionController::class, 'submit'])
        ->name('requisitions.submit');

    Route::post('/requisitions/{requisition}/manager/approve', [RequisitionController::class, 'managerApprove'])
        ->name('requisitions.manager.approve');
    Route::post('/requisitions/{requisition}/manager/send-back', [RequisitionController::class, 'managerSendBack'])
        ->name('requisitions.manager.send-back');
    Route::post('/requisitions/{requisition}/manager/reject', [RequisitionController::class, 'managerReject'])
        ->name('requisitions.manager.reject');

    Route::post('/requisitions/{requisition}/inventory/approve', [RequisitionController::class, 'inventoryApprove'])
        ->name('requisitions.inventory.approve');
    Route::post('/requisitions/{requisition}/inventory/send-back', [RequisitionController::class, 'inventorySendBack'])
        ->name('requisitions.inventory.send-back');
    Route::post('/requisitions/{requisition}/inventory/reject', [RequisitionController::class, 'inventoryReject'])
        ->name('requisitions.inventory.reject');
    Route::post('/requisitions/{requisition}/inventory/approve-changes', [RequisitionController::class, 'inventoryApproveChanges'])
        ->name('requisitions.inventory.approve-changes');
    Route::post('/requisitions/{requisition}/inventory/send-changes-back', [RequisitionController::class, 'inventorySendChangesBack'])
        ->name('requisitions.inventory.send-changes-back');

    Route::post('/requisitions/{requisition}/warehouse/release', [RequisitionController::class, 'warehouseRelease'])
        ->name('requisitions.warehouse.release');

    Route::get('/requisitions/{requisition}/print/pick-list', [RequisitionPrintController::class, 'pickList'])
        ->name('requisitions.print.pick-list');
    Route::get('/requisitions/{requisition}/print/final', [RequisitionPrintController::class, 'final'])
        ->name('requisitions.print.final');

    Route::post('/profile/signature', [SignatureController::class, 'update'])->name('profile.signature.update');
    Route::delete('/profile/signature', [SignatureController::class, 'destroy'])->name('profile.signature.destroy');

    Route::post('/delegations', [DelegationController::class, 'store'])->name('delegations.store');
    Route::delete('/delegations/{delegation}', [DelegationController::class, 'destroy'])->name('delegations.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');

    Route::middleware('inventory')->prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/reports', [InventoryReportController::class, 'index'])->name('reports');
        Route::get('/reports/export', [InventoryReportController::class, 'export'])->name('reports.export');
    });

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/signature', [AdminUserController::class, 'updateSignature'])->name('users.signature.update');
        Route::delete('/users/{user}/signature', [AdminUserController::class, 'clearSignature'])->name('users.signature.destroy');

        Route::get('/departments', [AdminDepartmentController::class, 'index'])->name('departments.index');
        Route::post('/departments', [AdminDepartmentController::class, 'store'])->name('departments.store');
        Route::put('/departments/{department}', [AdminDepartmentController::class, 'update'])->name('departments.update');
        Route::delete('/departments/{department}', [AdminDepartmentController::class, 'destroy'])->name('departments.destroy');

        Route::get('/signatures', [AdminSignatureController::class, 'index'])->name('signatures.index');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
