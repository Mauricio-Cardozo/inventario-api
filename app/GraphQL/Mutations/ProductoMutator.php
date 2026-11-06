<?php

namespace App\GraphQL\Mutations;

use App\Services\ProductoService;
use GraphQL\Error\UserError;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProductoMutator
{
    public function __construct(private ProductoService $servicio) {}

    public function crear($_, array $args)
    {
        return $this->servicio->crear($args);
    }

    public function stock($_, array $args)
    {
        try {
            $producto = $this->servicio->buscar((int) $args['id']);
        } catch (ModelNotFoundException) {
            // UserError es "client safe": el mensaje llega al cliente tal cual.
            throw new UserError('Producto no encontrado');
        }

        return $this->servicio->cambiarStock($producto, (int) $args['stock']);
    }
}
