<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DnsController;
use App\Http\Controllers\Api\DomainController;
use App\Http\Controllers\Api\SettingController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/domains', [DomainController::class, 'index']);
    Route::post('/domains/sync', [DomainController::class, 'sync']);
    Route::get('/domains/{domain}', [DomainController::class, 'show']);
    Route::put('/domains/{domain}', [DomainController::class, 'update']);
    Route::post('/domains/{domain}/whois', [DomainController::class, 'whois']);

    Route::get('/domains/{domain}/dns', [DnsController::class, 'index']);
    Route::post('/domains/{domain}/dns', [DnsController::class, 'store']);
    Route::put('/domains/{domain}/dns/{recordId}', [DnsController::class, 'update']);
    Route::delete('/domains/{domain}/dns/{recordId}', [DnsController::class, 'destroy']);

    Route::get('/settings', [SettingController::class, 'show']);
    Route::put('/settings', [SettingController::class, 'update']);
});
