<?php

use App\Http\Controllers\Api\ProductoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// REST: GET/POST /api/productos, GET/PUT/PATCH/DELETE /api/productos/{producto}
Route::apiResource('productos', ProductoController::class);
