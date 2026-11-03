document.addEventListener('DOMContentLoaded', () => {
    const logeado = localStorage.getItem('logeado');

    // CORREGIDO: si NO está logeado, lo mandamos al login
    if (logeado !== 'true') {
        window.location.href = 'login.html';
        return;
    }

    const nombre = localStorage.getItem('nombre');
    const rol = localStorage.getItem('rol');

    document.getElementById('nombreUsuario').textContent = nombre;

    if (rol === 'asociado') {
        document.getElementById('tituloAdministrador').classList.add('d-none');
        document.getElementById('tarjetasAdministrador').classList.add('d-none');

        document.getElementById('tituloAsociado').classList.remove('d-none');
        document.getElementById('tarjetasAsociado').classList.remove('d-none');
    } else {
        document.getElementById('tituloAdministrador').classList.remove('d-none');
        document.getElementById('tarjetasAdministrador').classList.remove('d-none');

        document.getElementById('tituloAsociado').classList.add('d-none');
        document.getElementById('tarjetasAsociado').classList.add('d-none');
    }

    document.getElementById('btnSalir').addEventListener('click', () => {
        localStorage.clear();
        window.location.href = 'login.html';
    });
});
                
function cerrarSesion() {
    localStorage.clear();
    window.location.href = '../VISTA/Login.html';
}