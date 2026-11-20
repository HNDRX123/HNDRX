/**
 * ===================================================
 * CONTROLADOR - REGISTRO PÚBLICO (asociado.js)
 * ===================================================
 * Maneja el formulario de auto-registro desde Registro.html.
 * Valida contraseñas en el cliente y envía los datos al
 * endpoint guardarAsociado.php.
 */

const formulario = document.getElementById("formRegistro");

if (formulario) {
    formulario.addEventListener("submit", async function (event) {
        event.preventDefault();

        const botonGuardar = formulario.querySelector("button[type='submit']");
        const mensajeEl = document.getElementById("mensajeRegistro");
        const asociado = Object.fromEntries(new FormData(formulario).entries());

        // ─── Validaciones del lado del cliente ───

        // Contraseña mínimo 8 caracteres
        if (!asociado.password || asociado.password.length < 8) {
            if (mensajeEl) {
                mensajeEl.textContent = "La contraseña debe tener al menos 8 caracteres.";
                mensajeEl.style.color = "#dc3545";
            }
            return;
        }

        // Verificar que las contraseñas coincidan
        if (asociado.confirmarPassword && asociado.password !== asociado.confirmarPassword) {
            if (mensajeEl) {
                mensajeEl.textContent = "Las contraseñas no coinciden.";
                mensajeEl.style.color = "#dc3545";
            }
            return;
        }

        // ─── Enviar datos al servidor ───
        if (botonGuardar) botonGuardar.disabled = true;
        if (mensajeEl) {
            mensajeEl.textContent = "Guardando datos...";
            mensajeEl.style.color = "#0b4e69";
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
                    // Registro exitoso: mostrar enlace al login
                    mensajeEl.style.color = "#198754";
                    mensajeEl.innerHTML = (resultado.message || "Asociado registrado con éxito.") +
                        '<br><a href="Login.html" class="btn btn-sm btn-outline-success mt-2">Ir a Iniciar Sesión &rarr;</a>';
                    formulario.reset();
                } else {
                    // Error del servidor
                    mensajeEl.style.color = "#dc3545";
                    mensajeEl.textContent = resultado.message || "Error al procesar el registro.";
                }
            }
        } catch (error) {
            console.error("Error al enviar el formulario:", error);
            if (mensajeEl) {
                mensajeEl.textContent = "Ocurrió un error al enviar el formulario.";
                mensajeEl.style.color = "#dc3545";
            }
        } finally {
            if (botonGuardar) botonGuardar.disabled = false;
        }
    });
}
