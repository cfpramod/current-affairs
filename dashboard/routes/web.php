<?php

declare(strict_types=1);

use App\Http\Controllers\BatchController;
use App\Http\Controllers\BatchDecisionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShipmentDecisionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('batches', [BatchController::class, 'index'])->name('batches.index');
    Route::get('batches/{batch}', [BatchController::class, 'show'])->name('batches.show')->where('batch', '[A-Za-z0-9_-]+');
    Route::post('batches/{batch}/decisions', [BatchDecisionController::class, 'store'])->name('batches.decisions.store')->where('batch', '[A-Za-z0-9_-]+');
    Route::delete('batches/{batch}/decisions', [BatchDecisionController::class, 'destroy'])->name('batches.decisions.destroy')->where('batch', '[A-Za-z0-9_-]+');
    Route::post('batch-decisions/{batchDecision}/review', [BatchDecisionController::class, 'review'])->name('batch-decisions.review');
    Route::get('integration', IntegrationController::class)->name('integration');
    Route::get('integration/batches', [IntegrationController::class, 'batches'])->name('integration.batches');
    Route::get('shipments', [ShipmentController::class, 'index'])->name('shipments.index');
    Route::get('shipments/{shipment}', [ShipmentController::class, 'show'])->name('shipments.show');
    Route::post('shipments/{shipment}/decisions', [ShipmentDecisionController::class, 'store'])->name('shipments.decisions.store');
    Route::delete('shipments/{shipment}/decisions', [ShipmentDecisionController::class, 'destroy'])->name('shipments.decisions.destroy');
});

require __DIR__.'/settings.php';
