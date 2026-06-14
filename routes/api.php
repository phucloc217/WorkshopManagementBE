<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::apiResource('workshops', \App\Http\Controllers\WorkshopController::class);
Route::apiResource('job-orders', \App\Http\Controllers\JobOrderController::class);
Route::apiResource('users', \App\Http\Controllers\UserController::class);
Route::post('users/{user}/change-password', [\App\Http\Controllers\UserController::class, 'changePassword']);
Route::post('users/{user}/change-status', [\App\Http\Controllers\UserController::class, 'changeStatus']);
Route::get('/customer/by-phone/{phone}', [\App\Http\Controllers\CustomerController::class, 'findByPhone']);
Route::get('/vehicle/by-motor-number/{motor_number}', [\App\Http\Controllers\VehicleController::class, 'findByMotorNumber']);
Route::post('login', [\App\Http\Controllers\AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('logout', [\App\Http\Controllers\AuthController::class, 'logout']);
