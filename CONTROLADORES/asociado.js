const formulario = document.getElementById("formRegistro");

if (formulario) {
    formulario.addEventListener("submit", async function (event) {
        event.preventDefault();

        const botonGuardar = formulario.querySelector("button[type='submit']");
        const mensajeEl = document.getElementById("mensajeRegistro");

        const asociado = Object.fromEntries(new FormData(formulario).entries());
        
        // Validar que la contraseña tenga mínimo 8 caracteres
        if (!asociado.password || asociado.password.length < 8) {
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
                if (respuesta.ok && resultado.success) {
                    mensajeEl.style.color = "#d4edda";
                    mensajeEl.innerHTML = (resultado.mensaje || resultado.message || "Asociado registrado con éxito.") +
                        '<br><a href="Login.html" style="color: #ffffff; text-decoration: underline; font-size: 14px; display: inline-block; margin-top: 8px;">Ir a Iniciar Sesión &rarr;</a>';
                    formulario.reset();
                } else {
                    mensajeEl.style.color = "#ffcccc";
                    mensajeEl.textContent = resultado.mensaje || resultado.message || "Error al procesar el registro.";
                }
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
