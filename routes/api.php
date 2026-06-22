<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::apiResource('workshops', \App\Http\Controllers\WorkshopController::class);
Route::apiResource('job-orders', \App\Http\Controllers\JobOrderController::class);

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
Route::post('users/{user}/change-password', [\App\Http\Controllers\UserController::class, 'changePassword']);
Route::post('users/{user}/change-status', [\App\Http\Controllers\UserController::class, 'changeStatus']);
Route::get('/customer/by-phone/{phone}', [\App\Http\Controllers\CustomerController::class, 'findByPhone']);
Route::get('/vehicle/by-motor-number/{motor_number}', [\App\Http\Controllers\VehicleController::class, 'findByMotorNumber']);
Route::post('login', [\App\Http\Controllers\AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('logout', [\App\Http\Controllers\AuthController::class, 'logout']);
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('job-tasks', \App\Http\Controllers\JobTaskController::class);
    Route::apiResource('job-parts', \App\Http\Controllers\JobPartController::class);

});
