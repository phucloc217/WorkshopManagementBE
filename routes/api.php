<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::apiResource('workshops', \App\Http\Controllers\WorkshopController::class);
Route::apiResource('warehouses', \App\Http\Controllers\WarehouseController::class);
Route::apiResource('vehicle-types', \App\Http\Controllers\VehicleTypeController::class);


Route::apiResource('users', \App\Http\Controllers\UserController::class);
Route::apiResource('services', \App\Http\Controllers\ServiceController::class);
Route::apiResource('parts', \App\Http\Controllers\PartController::class);
Route::get(
    '/job-orders/{jobOrderId}/tasks',
    [\App\Http\Controllers\JobTaskController::class, 'getTasksByOrderId']
);
Route::get(
    '/job-orders/{jobOrderId}/parts',
    [\App\Http\Controllers\JobPartController::class, 'getPartsByOrderId']
);
Route::post('/job-orders/{id}/parts/import', [\App\Http\Controllers\JobPartController::class, 'import']);

Route::post('users/{user}/change-password', [\App\Http\Controllers\UserController::class, 'changePassword']);
Route::post('users/{user}/change-status', [\App\Http\Controllers\UserController::class, 'changeStatus']);
Route::get('/customer/by-phone/{phone}', [\App\Http\Controllers\CustomerController::class, 'findByPhone']);
Route::get('/vehicle/by-motor-number/{motor_number}', [\App\Http\Controllers\VehicleController::class, 'findByMotorNumber']);
Route::post('login', [\App\Http\Controllers\AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('logout', [\App\Http\Controllers\AuthController::class, 'logout']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('inventory/history', [\App\Http\Controllers\WarehouseInventoryController::class, 'importExportHistory']);
    Route::apiResource('stock-issues', \App\Http\Controllers\StockIssueController::class);
    Route::post('stock-issues/{stockIssue}/confirm', [\App\Http\Controllers\StockIssueController::class, 'confirm']);
    Route::get('job-order-parts', [\App\Http\Controllers\StockIssueController::class, 'getJobOrderParts']);
    Route::apiResource('stock-receipts', \App\Http\Controllers\StockReceiptController::class);
    Route::post('stock-receipts/{stockReceipt}/confirm', [\App\Http\Controllers\StockReceiptController::class, 'confirm']);
    Route::apiResource('job-orders', \App\Http\Controllers\JobOrderController::class);
    Route::apiResource('job-tasks', \App\Http\Controllers\JobTaskController::class);
    Route::post('job-tasks/{jobTask}/start', [\App\Http\Controllers\JobTaskController::class, 'startTask']);
    Route::post('job-tasks/{jobTask}/complete', [\App\Http\Controllers\JobTaskController::class, 'completeTask']);
    Route::delete('job-tasks/{jobTask}/delete', [\App\Http\Controllers\JobTaskController::class, 'destroy']);
    Route::delete('job-parts/{jobPart}/delete', [\App\Http\Controllers\JobPartController::class, 'destroy']);
    Route::apiResource('job-parts', \App\Http\Controllers\JobPartController::class);
    Route::post(
        'job-orders/{jobOrder}/finish',
        [\App\Http\Controllers\JobOrderController::class, 'finish']
    );
    Route::get('inventory/parts-to-import', [\App\Http\Controllers\WarehouseInventoryController::class, 'partsToImport']);
    Route::apiResource('stock-transfers', \App\Http\Controllers\StockTransferController::class);
    Route::post('stock-transfers/{stockTransfer}/transfer', [\App\Http\Controllers\StockTransferController::class, 'transfer']);
    Route::post('stock-transfers/{stockTransfer}/receive', [\App\Http\Controllers\StockTransferController::class, 'receive']);
});

//Kho
Route::get('inventory', [\App\Http\Controllers\WarehouseInventoryController::class, 'index']);
