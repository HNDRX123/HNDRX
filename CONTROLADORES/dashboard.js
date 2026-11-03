document.addEventListener('DOMContentLoaded',  () => {
    const logeado = localStorage.getItem('logeado');

    if (logeado === 'true') {
        window.location.href = 'login.html';
        return;
    }

    const nombre = localStorage.getItem('nombre');
    const rol = localStorage.getItem('rol');
    
});