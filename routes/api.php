<?php

use App\Http\Controllers\Api\ProductoController;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// REST: GET/POST /api/productos, GET/PUT/PATCH/DELETE /api/productos/{producto}
Route::apiResource('productos', ProductoController::class);

// Endpoint interno que consume el servidor gRPC (Node.js).
// Protegido con la cabecera X-Internal-Key = GRPC_INTERNAL_KEY del .env.
Route::get('/interno/productos/{producto}', function (Request $request, Producto $producto) {
    $clave = (string) config('services.grpc.internal_key');

    abort_if(
        $clave === '' || ! hash_equals($clave, (string) $request->header('X-Internal-Key')),
        403,
        'Clave interna inválida'
    );

    return response()->json($producto);
});
