/**
 * ===================================================
 * CONTROLADOR - DASHBOARD (dashboard.js)
 * ===================================================
 * Controla el panel principal del sistema.
 * Muestra información del usuario y configura la vista
 * según el rol (Administrador o Asociado).
 * Maneja el cierre de sesión.
 */

document.addEventListener('DOMContentLoaded', () => {
    // ─── Verificar autenticación ───
    if (localStorage.getItem('logeado') !== 'true') {
        window.location.href = 'Login.html';
        return;
    }

    // ─── Obtener datos del usuario desde localStorage ───
    const nombre    = localStorage.getItem('nombre');
    const rol       = (localStorage.getItem('rol') || 'asociado').toLowerCase();
    const email     = localStorage.getItem('email');
    const documento = localStorage.getItem('documento');

    // Mostrar nombre del usuario en el encabezado
    const nombreUsuarioEl = document.getElementById('nombreUsuario');
    if (nombreUsuarioEl) {
        nombreUsuarioEl.textContent = nombre || 'Usuario';
    }

    // Configurar badge de rol (Admin = amarillo, Asociado = azul)
    const badgeRolEl = document.getElementById('badgeRol');
    if (badgeRolEl) {
        if (rol === 'admin') {
            badgeRolEl.textContent = 'Administrador';
            badgeRolEl.className = 'badge bg-warning text-dark me-2';
        } else {
            badgeRolEl.textContent = 'Asociado';
            badgeRolEl.className = 'badge bg-info text-dark me-2';
        }
    }

    // ─── Mostrar/ocultar secciones según el rol ───
    const seccionAdmin    = document.getElementById('seccionAdministrador');
    const seccionAsociado = document.getElementById('seccionAsociado');

    if (rol === 'asociado') {
        if (seccionAdmin) seccionAdmin.classList.add('d-none');
        if (seccionAsociado) seccionAsociado.classList.remove('d-none');
    } else {
        if (seccionAdmin) seccionAdmin.classList.remove('d-none');
        if (seccionAsociado) seccionAsociado.classList.add('d-none');
    }

    // Mostrar datos del perfil (sección asociado)
    const perfilDocEl = document.getElementById('perfilDoc');
    if (perfilDocEl && documento) perfilDocEl.textContent = 'Documento: ' + documento;

    const perfilEmailEl = document.getElementById('perfilEmail');
    if (perfilEmailEl && email) perfilEmailEl.textContent = 'Correo: ' + email;

    // ─── Botón cerrar sesión ───
    const btnSalir = document.getElementById('btnSalir');
    if (btnSalir) btnSalir.addEventListener('click', cerrarSesion);
});

/**
 * Cierra la sesión: notifica al backend, limpia localStorage y redirige al login.
 */
async function cerrarSesion() {
    try {
        await fetch("../MODELO/cerrarSesion.php", { method: "POST" });
    } catch (error) {
        console.error("Error al cerrar sesión:", error);
    } finally {
        localStorage.clear();
        window.location.href = 'Login.html';
    }
}
