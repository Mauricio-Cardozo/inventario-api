<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventario en vivo</title>
    @vite(['resources/js/app.js'])
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; }
        table { border-collapse: collapse; }
        td, th { border: 1px solid #ccc; padding: 8px; }
        #logs { background: #f4f4f4; padding: 1rem; max-height: 240px; overflow: auto; }
    </style>
</head>
<body>
    <h1>Inventario en tiempo real</h1>
    <p>Abra esta página en dos pestañas y cambie el stock.</p>

    <table>
        <thead>
            <tr>
                <th>ID</th><th>Nombre</th><th>Precio</th><th>Stock</th><th>Acción</th>
            </tr>
        </thead>
        <tbody id="productos"></tbody>
    </table>

    <h3>Eventos recibidos por Reverb</h3>
    <pre id="logs"></pre>

    <script>
        // Se crean nodos con textContent para no interpolar datos en innerHTML.
        async function cargar() {
            const res = await fetch('/api/productos', { headers: { 'Accept': 'application/json' } });
            const productos = await res.json();
            const tbody = document.getElementById('productos');
            tbody.replaceChildren();

            for (const p of productos) {
                const tr = document.createElement('tr');
                for (const valor of [p.id, p.nombre, p.precio, p.stock]) {
                    const td = document.createElement('td');
                    td.textContent = valor;
                    tr.appendChild(td);
                }
                const td = document.createElement('td');
                const boton = document.createElement('button');
                boton.textContent = '+1 stock';
                boton.dataset.id = p.id;
                boton.dataset.stock = p.stock;
                td.appendChild(boton);
                tr.appendChild(td);
                tbody.appendChild(tr);
            }
        }

        document.addEventListener('click', async e => {
            if (!e.target.matches('button[data-id]')) return;
            await fetch('/api/productos/' + e.target.dataset.id, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ stock: Number(e.target.dataset.stock) + 1 })
            });
            await cargar();
        });

        window.addEventListener('load', cargar);

        window.addEventListener('inventario-cambio', e => {
            const logs = document.getElementById('logs');
            logs.textContent = JSON.stringify(e.detail) + '\n' + logs.textContent;
            cargar();
        });
    </script>
</body>
</html>
