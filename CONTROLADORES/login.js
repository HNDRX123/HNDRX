function inicializarLogin() {
    const formulario = document.getElementById('formLogin') || document.getElementById('loginForm');
    const mensaje = document.getElementById('mensajeLogin');

    if (!formulario) {
        console.error('No se encontró el formulario de inicio de sesión');
        return;
    }

    if (formulario.dataset.iniciado === 'true') return;
    formulario.dataset.iniciado = 'true';

    const mostrarMensaje = (clase, texto) => {
        if (!mensaje) return;
        mensaje.className = `${clase} mt-3 mb-0`;
        mensaje.textContent = texto;
    };

    formulario.addEventListener('submit', async function (e) {
        e.preventDefault();

        const boton = formulario.querySelector('button[type="submit"]');
        if (boton) boton.disabled = true;

        mostrarMensaje('text-info', 'Verificando credenciales...');

        try {
            const datos = Object.fromEntries(new FormData(formulario).entries());
            const url = formulario.action || '../modelo/inicioSesion.php';

            const respuesta = await fetch(url, {
                method: 'POST',
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: JSON.stringify(datos)
            });

            const resultado = await respuesta.json();

            if (!respuesta.ok || !resultado.success) {
                mostrarMensaje('text-danger', resultado.message || "No fue posible iniciar sesión.");
                return;
            }

            localStorage.setItem('logueado', 'true');
            localStorage.setItem('rol', (resultado.rol || 'asociado').toLowerCase());
            localStorage.setItem('nombre', resultado.nombre || '');
            localStorage.setItem('email', resultado.email || datos.email || '');
            localStorage.setItem('documento', resultado.documento || '');

            mostrarMensaje('text-success', '¡Inicio de sesión exitoso! Redirigiendo...');
            window.location.href = 'dashboard.html';
        } catch (error) {
            console.error("Error al iniciar sesión:", error);
            mostrarMensaje('text-danger', "Ocurrió un error al intentar iniciar sesión.");
        } finally {
            if (boton) boton.disabled = false;
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializarLogin);
} else {
    inicializarLogin();
}