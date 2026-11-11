document.addEventListener('DOMContentLoaded', () => {
    const logeado = localStorage.getItem('logeado');

    // Si NO está logeado, redirigir al login
    if (logeado !== 'true') {
        window.location.href = 'Login.html';
        return;
    }

    const nombre = localStorage.getItem('nombre');
    const rol = (localStorage.getItem('rol') || 'asociado').toLowerCase();
    const email = localStorage.getItem('email');
    const documento = localStorage.getItem('documento');

    const nombreUsuarioEl = document.getElementById('nombreUsuario');
    if (nombreUsuarioEl) {
        nombreUsuarioEl.textContent = nombre || 'Usuario';
    }

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

    const seccionAdmin = document.getElementById('seccionAdministrador');
    const seccionAsociado = document.getElementById('seccionAsociado');

    if (rol === 'asociado') {
        if (seccionAdmin) seccionAdmin.classList.add('d-none');
        if (seccionAsociado) seccionAsociado.classList.remove('d-none');
    } else {
        if (seccionAdmin) seccionAdmin.classList.remove('d-none');
        if (seccionAsociado) seccionAsociado.classList.add('d-none');
    }

    const perfilDocEl = document.getElementById('perfilDoc');
    if (perfilDocEl && documento) {
        perfilDocEl.textContent = 'Documento: ' + documento;
    }

    const perfilEmailEl = document.getElementById('perfilEmail');
    if (perfilEmailEl && email) {
        perfilEmailEl.textContent = 'Correo: ' + email;
    }

    const btnSalir = document.getElementById('btnSalir');
    if (btnSalir) {
        btnSalir.addEventListener('click', cerrarSesion);
    }
});
                
function cerrarSesion() {
    localStorage.clear();
    window.location.href = 'Login.html';
}
