<?php

namespace Database\Seeders;

use App\Models\Producto;
use Illuminate\Database\Seeder;

class ProductoSeeder extends Seeder
{
    public function run(): void
    {
        $productos = [
            ['nombre' => 'Teclado', 'precio' => 25000, 'stock' => 12],
            ['nombre' => 'Mouse', 'precio' => 18000, 'stock' => 20],
            ['nombre' => 'Monitor 24"', 'precio' => 320000, 'stock' => 5],
            ['nombre' => 'Auriculares', 'precio' => 45000, 'stock' => 8],
        ];

        foreach ($productos as $producto) {
            Producto::create($producto);
        }
    }
}
