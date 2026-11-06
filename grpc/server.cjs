const grpc = require('@grpc/grpc-js');
const loader = require('@grpc/proto-loader');
const path = require('path');

const LARAVEL_URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8000';

const def = loader.loadSync(path.join(__dirname, 'producto.proto'));
const api = grpc.loadPackageDefinition(def).inventario;

const server = new grpc.Server();

server.addService(api.ProductoService.service, {
    async ObtenerProducto(call, callback) {
        try {
            const url = `${LARAVEL_URL}/api/interno/productos/${call.request.id}`;
            const r = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Internal-Key': process.env.GRPC_INTERNAL_KEY || ''
                }
            });

            if (r.status === 404) {
                return callback({ code: grpc.status.NOT_FOUND, message: 'Producto inexistente' });
            }
            if (r.status === 403) {
                return callback({ code: grpc.status.PERMISSION_DENIED, message: 'Clave interna inválida' });
            }
            if (!r.ok) {
                return callback({ code: grpc.status.INTERNAL, message: 'Fallo del backend Laravel: ' + r.status });
            }

            const p = await r.json();
            callback(null, {
                id: p.id,
                nombre: p.nombre,
                precio: Number(p.precio),
                stock: p.stock
            });
        } catch (err) {
            callback({ code: grpc.status.UNAVAILABLE, message: err.message });
        }
    }
});

server.bindAsync('127.0.0.1:50051', grpc.ServerCredentials.createInsecure(), (err, port) => {
    if (err) throw err;
    console.log('Servidor gRPC en puerto', port);
});
