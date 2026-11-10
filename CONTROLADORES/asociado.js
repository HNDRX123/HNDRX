const formulario = document.getElementById("formRegistro");

if (formulario) {
    formulario.addEventListener("submit", async function (event) {
        event.preventDefault();

        const botonGuardar = formulario.querySelector("button[type='submit']");
        const mensajeEl = document.getElementById("mensajeRegistro");

        const asociado = Object.fromEntries(new FormData(formulario).entries());

        // Validar que la contraseña tenga mínimo 8 caracteres
        if (asociado.password && asociado.password.length < 8) {
            if (mensajeEl) {
                mensajeEl.textContent = "La contraseña debe tener al menos 8 caracteres.";
                mensajeEl.style.color = "#ffcccc";
            }
            return;
        }

        // Validar que las contraseñas coincidan
        if (asociado.password && asociado.confirmarPassword && asociado.password !== asociado.confirmarPassword) {
            if (mensajeEl) {
                mensajeEl.textContent = "Las contraseñas no coinciden.";
                mensajeEl.style.color = "#ffcccc";
            }
            return;
        }

        if (botonGuardar) botonGuardar.disabled = true;
        if (mensajeEl) {
            mensajeEl.textContent = "Guardando...";
            mensajeEl.style.color = "#ffffff";
        }

        try {
            const respuesta = await fetch(formulario.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(asociado)
            });

            const resultado = await respuesta.json();

            if (mensajeEl) {
                mensajeEl.textContent = resultado.mensaje || resultado.message || "Operación procesada.";
                mensajeEl.style.color = (respuesta.ok && resultado.success) ? "#d4edda" : "#ffcccc";
            }

            if (respuesta.ok && resultado.success) {
                formulario.reset();
            }
        } catch (error) {
            console.error("Error al enviar el formulario:", error);
            if (mensajeEl) {
                mensajeEl.textContent = "Ocurrió un error al enviar el formulario. Por favor, inténtalo de nuevo.";
                mensajeEl.style.color = "#ffcccc";
            }
        } finally {
            if (botonGuardar) botonGuardar.disabled = false;
        }
    });
}
