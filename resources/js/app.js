// "php artisan install:broadcasting --reverb" crea resources/js/echo.js,
// que configura Laravel Echo con Reverb y define window.Echo.
import './echo';

// Canal público "inventario": cada cambio de un producto llega como
// "producto.actualizado" (broadcastAs del evento ProductoActualizado).
window.Echo.channel('inventario')
    .listen('.producto.actualizado', (evento) => {
        console.log('Cambio recibido:', evento);
        window.dispatchEvent(new CustomEvent('inventario-cambio', { detail: evento }));
    });
