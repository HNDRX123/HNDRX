/**
 * ===================================================
 * CONTROLADOR - INICIO DE SESIÓN (login.js)
 * ===================================================
 * Maneja el envío del formulario de login.
 * Envía credenciales al backend via AJAX (JSON),
 * guarda la sesión en localStorage y redirige al dashboard.
 */

function inicializarLogin() {
    const formulario = document.getElementById('formLogin') || document.getElementById('loginForm');
    const mensaje = document.getElementById('mensajeLogin');

    if (!formulario) {
        console.error('No se encontró el formulario de inicio de sesión');
        return;
    }

    // Evitar doble inicialización
    if (formulario.dataset.iniciado === 'true') return;
    formulario.dataset.iniciado = 'true';

    formulario.addEventListener('submit', async function (e) {
        e.preventDefault();

        const boton = formulario.querySelector('button[type="submit"]');
        if (boton) boton.disabled = true;

        // Mostrar estado de carga
        if (mensaje) {
            mensaje.className = 'text-info mt-3 mb-0';
            mensaje.textContent = 'Verificando credenciales...';
        }

        try {
            // Recoger datos del formulario y enviar como JSON
            const datos = Object.fromEntries(new FormData(formulario).entries());
            const url = formulario.action || '../MODELO/inicioSesion.php';

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

            // Verificar si el login fue exitoso
            if (!respuesta.ok || !resultado.success) {
                if (mensaje) {
                    mensaje.className = 'text-danger mt-3 mb-0';
                    mensaje.textContent = resultado.message || "No fue posible iniciar sesión.";
                }
                return;
            }

            // Guardar datos de sesión en localStorage
            localStorage.setItem('logeado', 'true');
            localStorage.setItem('logueado', 'true');
            localStorage.setItem('rol', (resultado.rol || 'asociado').toLowerCase());
            localStorage.setItem('nombre', resultado.nombre || '');
            localStorage.setItem('email', resultado.email || datos.email || '');
            localStorage.setItem('documento', resultado.documento || '');

            // Mostrar éxito y redirigir
            if (mensaje) {
                mensaje.className = 'text-success mt-3 mb-0';
                mensaje.textContent = '¡Inicio de sesión exitoso! Redirigiendo...';
            }
            window.location.href = 'dashboard.html';

        } catch (error) {
            console.error("Error al iniciar sesión:", error);
            if (mensaje) {
                mensaje.className = 'text-danger mt-3 mb-0';
                mensaje.textContent = "Ocurrió un error al intentar iniciar sesión.";
            }
        } finally {
            if (boton) boton.disabled = false;
        }
    });
}

// Inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializarLogin);
} else {
    inicializarLogin();
}
