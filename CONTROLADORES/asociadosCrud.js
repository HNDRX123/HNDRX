/**
 * ===================================================
 * CONTROLADOR - CRUD DE ASOCIADOS (asociadosCrud.js)
 * ===================================================
 * Gestiona el listado, creación, edición y eliminación
 * de asociados desde RegistroAsociados.html.
 *
 * Funciones principales:
 *   - solicitarApiAsociados()  → Comunicación con el backend
 *   - cargarAsociados()        → Obtener lista del servidor
 *   - mostrarAsociados()       → Renderizar tabla en el DOM
 *   - abrirEdicionAsociado()   → Llenar modal de edición
 *   - guardarEdicionAsociado() → Enviar actualización (PUT)
 *   - eliminarAsociado()       → Confirmar y eliminar (DELETE)
 */

let esAdministrador = false;
let asociados = [];
let modalEditarAsociadoInstance = null;

// ─── COMUNICACIÓN CON EL BACKEND ─────────────────────

/**
 * Realiza una petición HTTP al endpoint de asociados.
 * Envía automáticamente el rol y documento del usuario desde localStorage.
 * Redirige al login si la sesión expiró (401).
 *
 * @param {string} metodo - Método HTTP (GET, POST, PUT, DELETE)
 * @param {object} datos - Cuerpo de la petición (opcional)
 * @returns {object} Respuesta JSON del servidor
 */
async function solicitarApiAsociados(metodo, datos) {
    const opciones = {
        method: metodo,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-User-Role': localStorage.getItem('rol') || 'asociado',
            'X-User-Document': localStorage.getItem('documento') || ''
        }
    };

    if (datos && (metodo === 'POST' || metodo === 'PUT' || metodo === 'DELETE')) {
        opciones.body = JSON.stringify(datos);
    }

    const respuesta = await fetch("../MODELO/gestionarAsociados.php", opciones);

    // Sesión expirada → redirigir al login
    if (respuesta.status === 401) {
        window.location.href = "Login.html";
        return;
    }

    let resultado;
    try {
        resultado = await respuesta.json();
    } catch (e) {
        throw new Error("Respuesta inválida del servidor.");
    }

    if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || "Error en la operación.");
    }

    return resultado;
}

// ─── CARGA DE DATOS ──────────────────────────────────

/**
 * Obtiene la lista de asociados del servidor y la renderiza.
 * También detecta el rol del usuario para configurar la vista.
 */
async function cargarAsociados() {
    const lista = document.getElementById("listaAsociados");
    try {
        const resultado = await solicitarApiAsociados("GET");
        const rolUsuario = (resultado.rol || localStorage.getItem('rol') || 'asociado').toLowerCase();
        esAdministrador = (rolUsuario === 'admin');

        configurarVistaAsociados();
        asociados = resultado.asociados || [];
        mostrarAsociados();
    } catch (error) {
        console.error("Error al cargar asociados:", error);
        if (lista) {
            lista.innerHTML = `<tr><td colspan="${esAdministrador ? 7 : 6}" class="text-center text-danger py-4">
                <i class="bi bi-exclamation-circle me-2"></i>No se pudieron cargar los asociados: ${error.message}
            </td></tr>`;
        }
    }
}

// ─── CONFIGURACIÓN DE LA VISTA ───────────────────────

/**
 * Muestra u oculta elementos de la interfaz según el rol del usuario.
 * Admin: ve botones de acción, formulario de registro.
 * Asociado: ve solo el listado en modo lectura.
 */
function configurarVistaAsociados() {
    // Columna de acciones en la tabla
    const thAcciones = document.getElementById("thAccionesAsociados");
    if (thAcciones) thAcciones.style.display = esAdministrador ? "" : "none";

    // Botón para mostrar formulario de registro
    const btnToggle = document.getElementById("btnToggleFormulario");
    if (btnToggle) btnToggle.style.display = esAdministrador ? "" : "none";

    // Secciones del formulario y banner informativo
    const seccionForm    = document.getElementById("seccionFormularioAsociado");
    const bannerAsociado = document.getElementById("bannerAsociadoLectura");

    if (!esAdministrador) {
        if (seccionForm) seccionForm.classList.add("d-none");
        if (bannerAsociado) bannerAsociado.classList.remove("d-none");
    } else {
        if (bannerAsociado) bannerAsociado.classList.add("d-none");
    }
}

/**
 * Alterna la visibilidad del formulario de registro (solo Admin).
 */
function toggleFormulario() {
    if (!esAdministrador) return;
    const seccionForm = document.getElementById("seccionFormularioAsociado");
    if (seccionForm) {
        seccionForm.classList.toggle("d-none");
        if (!seccionForm.classList.contains("d-none")) {
            seccionForm.scrollIntoView({ behavior: "smooth", block: "start" });
        }
    }
}

// ─── RENDERIZADO DE LA TABLA ─────────────────────────

/**
 * Renderiza la lista de asociados en la tabla HTML.
 * Excluye a los administradores del listado.
 * Si el usuario es Admin, agrega botones de editar/eliminar.
 */
function mostrarAsociados() {
    const lista = document.getElementById("listaAsociados");
    if (!lista) return;
    lista.replaceChildren();

    // Mensaje cuando no hay registros
    if (asociados.length === 0) {
        const fila = document.createElement("tr");
        const celda = document.createElement("td");
        celda.colSpan = esAdministrador ? 7 : 6;
        celda.className = "text-center py-4 text-muted";
        celda.innerHTML = '<i class="bi bi-people fs-4 d-block mb-1"></i>No hay asociados registrados.';
        fila.append(celda);
        lista.append(fila);
        return;
    }

    // Recorrer asociados y crear filas
    asociados.forEach((socio) => {
        const esAdmin = (socio.rol && socio.rol.toLowerCase() === 'admin');
        // No mostrar administradores en el listado de asociados
        if (esAdmin) return;

        const fila = document.createElement("tr");

        // Crear celdas con datos del asociado
        const celdaDoc = document.createElement("td");
        celdaDoc.className = "fw-semibold text-primary";
        celdaDoc.textContent = socio.documento || '-';

        const celdaNombre = document.createElement("td");
        celdaNombre.textContent = `${socio.nombres || ''} ${socio.apellidos || ''}`.trim() || 'Sin Nombre';

        const celdaCiudad = document.createElement("td");
        celdaCiudad.textContent = socio.ciudad || '-';

        const celdaTel = document.createElement("td");
        celdaTel.textContent = socio.telefono || '-';

        const celdaEmail = document.createElement("td");
        celdaEmail.textContent = socio.email || '-';

        const celdaRol = document.createElement("td");
        celdaRol.innerHTML = '<span class="badge bg-info text-dark"><i class="bi bi-person me-1"></i>Asociado</span>';

        fila.append(celdaDoc, celdaNombre, celdaCiudad, celdaTel, celdaEmail, celdaRol);

        // Botones de acción (solo para Admin)
        if (esAdministrador) {
            const acciones = document.createElement("td");
            acciones.className = "text-nowrap text-center";

            const btnEditar = document.createElement("button");
            btnEditar.type = "button";
            btnEditar.className = "btn btn-sm btn-outline-primary me-2";
            btnEditar.title = "Editar asociado";
            btnEditar.innerHTML = '<i class="bi bi-pencil-square"></i> Editar';
            btnEditar.addEventListener("click", () => abrirEdicionAsociado(socio));

            const btnEliminar = document.createElement("button");
            btnEliminar.type = "button";
            btnEliminar.className = "btn btn-sm btn-outline-danger";
            btnEliminar.title = "Eliminar asociado";
            btnEliminar.innerHTML = '<i class="bi bi-trash"></i> Eliminar';
            btnEliminar.addEventListener("click", () => eliminarAsociado(socio));

            acciones.append(btnEditar, btnEliminar);
            fila.append(acciones);
        }

        lista.append(fila);
    });
}

// ─── EDICIÓN DE ASOCIADO ─────────────────────────────

/**
 * Abre el modal de edición y lo llena con los datos del asociado seleccionado.
 * @param {object} socio - Objeto con los datos del asociado
 */
function abrirEdicionAsociado(socio) {
    if (!esAdministrador) return;

    document.getElementById("editIdAsociado").value  = socio.idAsociado;
    document.getElementById("editDocAsociado").value  = socio.documento || '';
    document.getElementById("editNombres").value      = socio.nombres || '';
    document.getElementById("editApellidos").value    = socio.apellidos || '';
    document.getElementById("editCiudad").value       = socio.ciudad || '';
    document.getElementById("editDireccion").value    = socio.direccionResidencia || '';
    document.getElementById("editTelefono").value     = socio.telefono || '';
    document.getElementById("editEmail").value        = socio.email || '';
    document.getElementById("editRol").value          = (socio.rol && socio.rol.toLowerCase() === 'admin') ? 'admin' : 'asociado';

    // Inicializar el modal de Bootstrap (una sola vez)
    const modalEl = document.getElementById("modalEditarAsociado");
    if (!modalEditarAsociadoInstance && window.bootstrap) {
        modalEditarAsociadoInstance = new bootstrap.Modal(modalEl);
    }
    if (modalEditarAsociadoInstance) modalEditarAsociadoInstance.show();
}

/**
 * Envía los datos actualizados del asociado al servidor (PUT).
 * @param {Event} e - Evento submit del formulario modal
 */
async function guardarEdicionAsociado(e) {
    e.preventDefault();

    const idAsociado = document.getElementById("editIdAsociado").value;
    const nombres    = document.getElementById("editNombres").value.trim();
    const apellidos  = document.getElementById("editApellidos").value.trim();
    const ciudad     = document.getElementById("editCiudad").value.trim();
    const direccion  = document.getElementById("editDireccion").value.trim();
    const telefono   = document.getElementById("editTelefono").value.trim();
    const email      = document.getElementById("editEmail").value.trim();
    const rol        = document.getElementById("editRol").value;

    if (!idAsociado || !nombres || !apellidos || !email) {
        alert("Nombres, apellidos y correo electrónico son obligatorios.");
        return;
    }

    try {
        await solicitarApiAsociados("PUT", {
            idAsociado: parseInt(idAsociado, 10),
            nombres, apellidos, ciudad, direccionResidencia: direccion,
            telefono, email, rol
        });

        if (modalEditarAsociadoInstance) modalEditarAsociadoInstance.hide();
        alert("Asociado actualizado exitosamente.");
        cargarAsociados();
    } catch (error) {
        alert("Error al actualizar el asociado: " + error.message);
    }
}

// ─── ELIMINACIÓN DE ASOCIADO ─────────────────────────

/**
 * Solicita confirmación y elimina un asociado (DELETE).
 * @param {object} socio - Objeto con los datos del asociado a eliminar
 */
async function eliminarAsociado(socio) {
    if (!esAdministrador) return;

    const nombre = `${socio.nombres || ''} ${socio.apellidos || ''}`.trim() || socio.documento;
    const confirmar = confirm(`¿Está seguro de eliminar al asociado "${nombre}" (Doc: ${socio.documento})?`);
    if (!confirmar) return;

    try {
        await solicitarApiAsociados("DELETE", { idAsociado: socio.idAsociado });
        alert("Asociado eliminado correctamente.");
        cargarAsociados();
    } catch (error) {
        alert("Error al eliminar el asociado: " + error.message);
    }
}

// ─── INICIALIZACIÓN ──────────────────────────────────

document.addEventListener("DOMContentLoaded", () => {
    // Verificar autenticación
    if (localStorage.getItem("logeado") !== "true") {
        window.location.href = "Login.html";
        return;
    }

    // Configurar rol y cargar datos
    const rol = (localStorage.getItem('rol') || 'asociado').toLowerCase();
    esAdministrador = (rol === 'admin');
    configurarVistaAsociados();
    cargarAsociados();

    // Fecha por defecto en el formulario
    const hoy = new Date().toISOString().split('T')[0];
    const fechaAfil = document.getElementById('fechaAfiliacion');
    if (fechaAfil) fechaAfil.value = hoy;

    // ─── Formulario de registro de asociado (POST) ───
    const formulario = document.getElementById("formRegistroAsociados");
    if (formulario) {
        formulario.addEventListener("submit", async function (e) {
            e.preventDefault();

            // Validar contraseñas si se proporcionaron
            const pwd = document.getElementById('password')?.value;
            const confirmPwd = document.getElementById('confirmarPassword')?.value;
            if (pwd && confirmPwd && pwd !== confirmPwd) {
                alert('Las contraseñas no coinciden.');
                return;
            }

            if (!this.checkValidity()) {
                e.stopPropagation();
                this.classList.add("was-validated");
                return;
            }

            const btnSubmit = this.querySelector('button[type="submit"]');
            if (btnSubmit) btnSubmit.disabled = true;

            const datos = Object.fromEntries(new FormData(this).entries());

            try {
                await solicitarApiAsociados("POST", datos);

                // Mostrar alerta de éxito
                const alerta = document.getElementById("alertaExito");
                if (alerta) {
                    alerta.classList.remove("d-none");
                    alerta.classList.add("show");
                    alerta.scrollIntoView({ behavior: "smooth", block: "center" });
                }

                this.reset();
                this.classList.remove("was-validated");
                if (fechaAfil) fechaAfil.value = hoy;
                cargarAsociados();
            } catch (err) {
                alert("Error al registrar el asociado: " + err.message);
            } finally {
                if (btnSubmit) btnSubmit.disabled = false;
            }
        });
    }

    // ─── Formulario de edición en modal ───
    const formEdicion = document.getElementById("formEditarAsociadoModal");
    if (formEdicion) {
        formEdicion.addEventListener("submit", guardarEdicionAsociado);
    }
});
