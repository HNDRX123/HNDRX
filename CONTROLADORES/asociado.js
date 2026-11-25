const formulario = document.getElementById("formRegistro");

if (formulario) {
    formulario.addEventListener("submit", async function (event) {
        event.preventDefault();

        const botonGuardar = formulario.querySelector("button[type='submit']");
        const mensajeEl = document.getElementById("mensajeRegistro");
        const asociado = Object.fromEntries(new FormData(formulario).entries());

        const mostrarEstado = (color, html) => {
            if (!mensajeEl) return;
            mensajeEl.style.color = color;
            mensajeEl.innerHTML = html;
        };

        if (!asociado.password || asociado.password.length < 8) {
            mostrarEstado("#dc3545", "La contraseña debe tener al menos 8 caracteres.");
            return;
        }

        if (asociado.confirmarPassword && asociado.password !== asociado.confirmarPassword) {
            mostrarEstado("#dc3545", "Las contraseñas no coinciden.");
            return;
        }

        if (botonGuardar) botonGuardar.disabled = true;
        mostrarEstado("#0b4e69", "Guardando datos...");

        try {
            const url = formulario.action || "../modelo/guardarAsociado.php";
            const respuesta = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(asociado)
            });

            const resultado = await respuesta.json();

            if (respuesta.ok && resultado.success) {
                mostrarEstado(
                    "#198754",
                    (resultado.message || "Asociado registrado con éxito.") +
                    '<br><a href="Login.html" class="btn btn-sm btn-outline-success mt-2">Ir a Iniciar Sesión &rarr;</a>'
                );
                formulario.reset();
            } else {
                mostrarEstado("#dc3545", resultado.message || "Error al procesar el registro.");
            }
        } catch (error) {
            console.error("Error al enviar el formulario:", error);
            mostrarEstado("#dc3545", "Ocurrió un error al enviar el formulario.");
        } finally {
            if (botonGuardar) botonGuardar.disabled = false;
        }
    });
}