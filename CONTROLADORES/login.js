const formulario = document.getElementById('loginForm');
formulario.addEventListener('submit', async function (e) {
    e.preventDefault();

    const boton = formulario.querySelector('button[type="submit"]');
    alert('Iniciando sesión...');
    const mensaje = formulario.getElementById('mensajeLogin');

    boton.disabled = true;

    try {
        const respuesta = await fetch(formulario.action, {
            method: 'POST',
            headers: { "content-type": "application/json" },
            body: JSON.stringify(Object.fromEntries(new FormData(formulario).entries()))
        });

        const resultado = await respuesta.json();

        if (!resultado.success) {
            mensaje.txtContent = "NO fue posible iniciar sesión. Por favor, verifique sus credenciales.";
            boton.disabled = false;
            return;
        }

        localStorage.setItem('logueado', 'true');
        localStorage.setItem('rol', resultado.rol);
        localStorage.setItem('nombre', resultado.nombre || usuarioInput);
        window.location.href = 'dashboard.html';

    } catch(error) {
        console.error("error al iniciar sesion", error);
        mensaje.txtContent=
        "Ocurrio un error al intentar iniciar sesion. Por favor, intente nuevamente.";

    }finally {
        boton.disabled = false;
    }
});