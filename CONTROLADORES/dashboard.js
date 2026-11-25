async function cerrarSesion() {
    try {
        await fetch("../modelo/cerrarSesion.php", { method: "POST" });
    } catch (error) {
        console.error("Error al cerrar sesión:", error);
    } finally {
        localStorage.clear();
        window.location.href = "Login.html";
    }
}

document.addEventListener("DOMContentLoaded", () => {
    const sesionActiva = localStorage.getItem("logueado") === "true" || localStorage.getItem("logeado") === "true";
    if (!sesionActiva) {
        window.location.href = "Login.html";
        return;
    }

    const nombre = localStorage.getItem("nombre");
    const rol = (localStorage.getItem("rol") || "asociado").toLowerCase();
    const email = localStorage.getItem("email");
    const documento = localStorage.getItem("documento");

    const nombreUsuarioEl = document.getElementById("nombreUsuario");
    if (nombreUsuarioEl) {
        nombreUsuarioEl.textContent = nombre || "Usuario";
    }

    const badgeRolEl = document.getElementById("badgeRol");
    if (badgeRolEl) {
        if (rol === "admin") {
            badgeRolEl.textContent = "Administrador";
            badgeRolEl.className = "badge bg-warning text-dark me-2";
        } else {
            badgeRolEl.textContent = "Asociado";
            badgeRolEl.className = "badge bg-info text-dark me-2";
        }
    }

    const seccionAdmin = document.getElementById("seccionAdministrador");
    const seccionAsociado = document.getElementById("seccionAsociado");
    const esAsociado = rol === "asociado";

    if (seccionAdmin) seccionAdmin.classList.toggle("d-none", esAsociado);
    if (seccionAsociado) seccionAsociado.classList.toggle("d-none", !esAsociado);

    const perfilDocEl = document.getElementById("perfilDoc");
    if (perfilDocEl && documento) perfilDocEl.textContent = "Documento: " + documento;

    const perfilEmailEl = document.getElementById("perfilEmail");
    if (perfilEmailEl && email) perfilEmailEl.textContent = "Correo: " + email;

    const btnSalir = document.getElementById("btnSalir");
    if (btnSalir) btnSalir.addEventListener("click", cerrarSesion);
});