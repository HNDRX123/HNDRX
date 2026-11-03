const usuarios = [
    { email: 'admin@gmail.com', clave: '123', rol: 'admin', nombre: 'Juan Perez' },
    { email: 'asociado@gmail.com', clave: '456', rol: 'asociado', nombre: 'Esteban Lesmes' }
];

document.getElementById('formLogin').addEventListener('submit', function (e) {
    e.preventDefault();

    const emailInput = document.getElementById('email').value.trim();
    const claveInput = document.getElementById('contrasena').value;

    const usuarioEncontrado = usuarios.find(
        u => u.email === emailInput && u.clave === claveInput
    );

    if (usuarioEncontrado) {
        localStorage.setItem('logeado', 'true');
        localStorage.setItem('rol', usuarioEncontrado.rol);
        localStorage.setItem('nombre', usuarioEncontrado.nombre);
        window.location.href = 'dashboard.html';
    } else {
        alert('Usuario o contraseña incorrectos');
    }
});