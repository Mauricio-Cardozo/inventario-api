# Inventario multicanal con Laravel

Trabajo práctico de Programación IV, TUP UTN FRRe, cohorte 2026.

Un mismo inventario de productos expuesto por cuatro canales:

| Canal | Tecnología | Para qué |
|---|---|---|
| REST | Laravel (`apiResource`) | CRUD completo de productos |
| GraphQL | Lighthouse | Consultas con los campos justos y mutaciones |
| WebSockets | Laravel Reverb + Echo | Avisar a todos los navegadores cuando cambia un producto |
| gRPC | Node.js (`@grpc/grpc-js`) + clientes Node y Python | Consultar un producto por contrato protobuf |

## Arquitectura

```
 REST (ProductoController) ─┐
                            ├─> ProductoService ─> Eloquent (Producto)
 GraphQL (ProductoMutator) ─┘         │
                                      └─> evento ProductoActualizado
                                              │ (cola database)
                                              v
                                     Reverb ─> canal "inventario" ─> navegadores (/inventario)

 cliente gRPC ─> servidor gRPC (Node.js :50051) ─> GET /api/interno/productos/{id}  (X-Internal-Key)
```

- **REST y GraphQL no duplican lógica:** los dos llaman a `ProductoService`, que valida los datos, guarda y emite el evento.
- **Cualquier cambio** (crear, actualizar o eliminar), venga por REST o por GraphQL, dispara `ProductoActualizado`. Reverb lo transmite por el canal público `inventario` con el nombre `producto.actualizado`.
- **El servidor gRPC** no accede a la base de datos. Consulta un endpoint interno de Laravel protegido con una clave compartida.

### Estructura

| Archivo | Responsabilidad |
|---|---|
| `app/Models/Producto.php` | Modelo Eloquent |
| `database/migrations/2026_01_01_000000_create_productos_table.php` | Tabla `productos` |
| `database/seeders/ProductoSeeder.php` | Productos de ejemplo |
| `app/Services/ProductoService.php` | Validaciones, operaciones y emisión del evento |
| `app/Http/Controllers/Api/ProductoController.php` | API REST |
| `graphql/schema.graphql` | Esquema GraphQL |
| `app/GraphQL/Mutations/ProductoMutator.php` | Resolvers de las mutaciones |
| `app/Events/ProductoActualizado.php` | Evento que se transmite por Reverb |
| `resources/views/inventario.blade.php` | Vista en tiempo real |
| `resources/js/app.js` | Suscripción de Echo al canal `inventario` |
| `routes/api.php` | Rutas REST y endpoint interno para gRPC |
| `grpc/producto.proto` | Contrato gRPC |
| `grpc/server.cjs` | Servidor gRPC (Node.js) |
| `grpc/client.cjs`, `grpc/client.py` | Clientes gRPC |

## Requisitos

- PHP 8.3 o superior, con `pdo_sqlite`
- Composer
- Node.js 18 o superior y npm
- Python 3 (solo para el cliente gRPC en Python)

## Instalación

```bash
git clone <url-del-repo> inventario-api
cd inventario-api

composer install
npm install

cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
```

En el `.env`, cambiar `REVERB_APP_KEY` y `REVERB_APP_SECRET` por textos propios, y completar la clave compartida con el servidor gRPC con cualquier texto largo:

```dotenv
REVERB_APP_KEY=mi-clave-reverb
REVERB_APP_SECRET=mi-secreto-reverb
GRPC_INTERNAL_KEY=una-clave-larga-y-dificil-de-adivinar
```

Crear la base SQLite, migrar y cargar los productos de ejemplo:

```bash
touch database/database.sqlite   # Windows: type nul > database\database.sqlite
php artisan migrate --seed
```

Dependencias de gRPC:

```bash
cd grpc
npm install

python -m venv .venv
# Linux/macOS: source .venv/bin/activate   ·   Windows: .venv\Scripts\activate
pip install grpcio grpcio-tools
python -m grpc_tools.protoc -I. --python_out=. --grpc_python_out=. producto.proto
cd ..
```

## Ejecución

Hacen falta cuatro terminales en la carpeta del proyecto:

| Terminal | Comando | Qué hace |
|---|---|---|
| 1 | `php artisan serve` | Servidor web en http://127.0.0.1:8000 |
| 2 | `php artisan reverb:start` | Servidor WebSocket en el puerto 8080 |
| 3 | `php artisan queue:work` | Procesa la cola: el evento es `ShouldBroadcast` |
| 4 | `npm run dev` | Compila el JavaScript (Echo) |

Para gRPC, dos terminales más dentro de `grpc/`:

```bash
# Terminal 5: servidor gRPC (la clave debe ser la misma que GRPC_INTERNAL_KEY del .env)
GRPC_INTERNAL_KEY=una-clave-larga-y-dificil-de-adivinar node server.cjs
# PowerShell: $env:GRPC_INTERNAL_KEY='una-clave-larga-y-dificil-de-adivinar'; node server.cjs

# Terminal 6: clientes
node client.cjs 1
python client.py 1
```

## Pruebas

### REST

```bash
curl -i http://127.0.0.1:8000/api/productos

curl -i -X POST http://127.0.0.1:8000/api/productos \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"nombre": "Webcam", "precio": 30000, "stock": 3}'

curl -i http://127.0.0.1:8000/api/productos/1

curl -i -X PATCH http://127.0.0.1:8000/api/productos/1 \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"stock": 11}'

curl -i -X DELETE http://127.0.0.1:8000/api/productos/1
```

| Caso | Código |
|---|---|
| Listar, ver o actualizar | 200 |
| Crear | 201 |
| Eliminar | 204, sin cuerpo |
| Producto inexistente | 404 `{"message": "Recurso no encontrado"}` |
| Datos inválidos | 422 con el detalle en `errors` |

### GraphQL

`POST http://127.0.0.1:8000/graphql`. En Postman se puede usar *Body > GraphQL* y pegar la operación directamente.

```graphql
{ productos { id nombre precio stock } }

{ producto(id: 2) { nombre stock } }

mutation { crearProducto(nombre: "Parlante", precio: 22000, stock: 7) { id nombre stock } }

mutation { actualizarStock(id: 1, stock: 8) { id stock } }
```

Los errores de GraphQL llegan con HTTP 200 y un campo `errors`. Por ejemplo, `actualizarStock` con un `id` inexistente devuelve `"Producto no encontrado"`, y con stock negativo devuelve el error de validación.

### WebSockets

1. Abrir http://127.0.0.1:8000/inventario en dos pestañas.
2. En una, tocar **+1 stock**.
3. La otra pestaña recibe `producto.actualizado` por Reverb, actualiza la tabla y muestra el evento en "Eventos recibidos por Reverb".

El evento también llega si el cambio se hace por REST (`curl`) o por GraphQL. En las herramientas de desarrollador (F12 → Red → filtro WS) se ve la conexión abierta con el puerto 8080 y los mensajes que llegan.

### gRPC

```bash
node client.cjs 1       # { id: 1, nombre: 'Teclado', precio: 25000, stock: 8 }
python client.py 2      # id: 2  nombre: "Mouse" ...
node client.cjs 999     # 5 Producto inexistente  (NOT_FOUND)
```

| Respuesta de Laravel | Código gRPC |
|---|---|
| 200 | OK |
| 404 | `NOT_FOUND` |
| 403 (clave incorrecta) | `PERMISSION_DENIED` |
| Otro error | `INTERNAL` |
| Laravel apagado | `UNAVAILABLE` |

## Decisiones y cambios respecto de la guía

- **Eliminar también avisa:** `eliminar()` emite `ProductoActualizado` con la acción `eliminado`, para que las otras pestañas quiten el producto.
- **Datos finales en el evento:** `actualizar()` recarga el modelo antes de emitir el evento, así se transmiten los valores ya guardados.
- **Sin XSS en la vista:** la tabla se arma creando nodos con `textContent` en lugar de interpolar en `innerHTML`.
- **Clave interna leída con `config()`:** la ruta usa `config('services.grpc.internal_key')` en lugar de `env()`. Con `php artisan config:cache`, `env()` devuelve `null` fuera de los archivos de configuración y el endpoint respondería siempre 403. La comparación usa `hash_equals` para evitar ataques de tiempo.
- **404 limpio en la API:** `bootstrap/app.php` devuelve `{"message": "Recurso no encontrado"}` para cualquier recurso inexistente bajo `api/*`, sin exponer la traza.
- **Errores de GraphQL claros:** `actualizarStock` con un id inexistente lanza un `UserError` en lugar de un "Internal server error".
- **gRPC configurable:** el servidor acepta la variable `LARAVEL_URL` (por defecto `http://127.0.0.1:8000`), y los clientes reciben el id como argumento.

## Limitaciones (entorno de laboratorio)

- El canal `inventario` es público y las rutas REST y GraphQL no piden autenticación. En producción se protegerían con Sanctum y canales privados.
- El botón **+1 stock** asigna un valor absoluto, así que dos clics simultáneos pueden pisarse. En producción se usaría una actualización atómica (`increment`) o un bloqueo de fila dentro de una transacción.
- El servidor gRPC usa una conexión sin TLS en `127.0.0.1`. Nunca se debe exponer ese puerto públicamente.

## Problemas frecuentes

| Síntoma | Solución |
|---|---|
| No llegan eventos a la otra pestaña | Verificar que `reverb:start` y `queue:work` estén corriendo, y los valores `REVERB_*` / `VITE_REVERB_*` del `.env`. Después de cambiar el `.env`, reiniciar `npm run dev`. |
| `Vite manifest not found` | Correr `npm run dev` o `npm run build`. |
| gRPC devuelve `PERMISSION_DENIED` | La clave del servidor Node no coincide con `GRPC_INTERNAL_KEY`. Correr `php artisan config:clear` y reiniciar `serve`. |
| gRPC devuelve `UNAVAILABLE` | Laravel no está corriendo en `LARAVEL_URL`. |
| `ModuleNotFoundError: producto_pb2` | Generar los stubs de Python con `grpc_tools.protoc` (ver Instalación). |
