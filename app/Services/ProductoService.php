<?php

namespace App\Services;

use App\Events\ProductoActualizado;
use App\Models\Producto;
use Illuminate\Support\Facades\Validator;

class ProductoService
{
    public function listar()
    {
        return Producto::orderBy('id')->get();
    }

    public function buscar(int $id): Producto
    {
        return Producto::findOrFail($id);
    }

    public function crear(array $datos): Producto
    {
        $datos = Validator::make($datos, [
            'nombre' => 'required|string|max:100',
            'precio' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ])->validate();

        $producto = Producto::create($datos);
        event(new ProductoActualizado($producto, 'creado'));

        return $producto;
    }

    public function actualizar(Producto $producto, array $datos): Producto
    {
        $datos = Validator::make($datos, [
            'nombre' => 'sometimes|required|string|max:100',
            'precio' => 'sometimes|required|numeric|min:0',
            'stock' => 'sometimes|required|integer|min:0',
        ])->validate();

        $producto->update($datos);
        $producto->refresh();
        event(new ProductoActualizado($producto, 'actualizado'));

        return $producto;
    }

    public function cambiarStock(Producto $producto, int $stock): Producto
    {
        return $this->actualizar($producto, ['stock' => $stock]);
    }

    public function eliminar(Producto $producto): void
    {
        // El evento copia los datos a un array, así que puede emitirse
        // aunque el registro ya no exista cuando la cola lo procese.
        $evento = new ProductoActualizado($producto, 'eliminado');
        $producto->delete();
        event($evento);
    }
}
