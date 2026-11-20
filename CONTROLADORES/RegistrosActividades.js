const listaActividades = document.getElementById("listaActividades");
let esAdministrador = false;
let actividades = [];
let modalEditarActividadInstance = null;
const formularioActividades=document.getElementById("formActividad");
const modalActividad = new bootstrap.modal(
    document.getElementById("modalActividad")
)
async function solicitarApi(metodo, datos) {
    const rolStorage = localStorage.getItem('rol') || 'asociado';
    const docStorage = localStorage.getItem('documento') || '';

    const opciones = {
        method: metodo,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-User-Role': rolStorage,
            'X-User-Document': docStorage
        }
    };
    if (datos && (metodo === 'POST' || metodo === 'PUT' || metodo === 'DELETE')) {
        opciones.body = JSON.stringify(datos);
    }
    const respuesta = await fetch("../MODELO/gestionarActividades.php", opciones);
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
    if (!respuesta.ok || (!resultado.success && !resultado.succes)) {
        throw new Error(resultado.mensaje || resultado.message || "Error en la solicitud");
    }
    return resultado;
}
async function cargarActividades() {
    try {
        const resultado = await solicitarApi("GET");
        const rolUsuario = (resultado.rol || localStorage.getItem('rol') || 'asociado').toLowerCase();
        esAdministrador = (rolUsuario === 'admin');
        configurarAcciones();
        actividades = resultado.actividades || [];
        mostrarActividades();
    } catch (error) {
        console.error("Error al cargar actividades:", error);
        if (listaActividades) {
            listaActividades.innerHTML = `<tr><td colspan="${esAdministrador ? 5 : 4}" class="text-center text-danger py-4">
                No se pudieron cargar las actividades: ${error.message}
            </td></tr>`;
        }
    }
}

function toggleFormulario() {
    if (!esAdministrador) return;
    const seccionForm = document.getElementById("seccionFormularioActividad");
    if (seccionForm) {
        seccionForm.classList.toggle("d-none");
        if (!seccionForm.classList.contains("d-none")) {
            seccionForm.scrollIntoView({ behavior: "smooth", block: "start" });
        }
    }
}

function configurarAcciones() {
    const thAcciones = document.getElementById("thAcciones");
    if (thAcciones) {
        thAcciones.style.display = esAdministrador ? "" : "none";
    }

    const btnToggle = document.getElementById("btnToggleFormulario");
    if (btnToggle) {
        btnToggle.style.display = esAdministrador ? "" : "none";
    }

    const seccionForm = document.getElementById("seccionFormularioActividad");
    const bannerAsociado = document.getElementById("bannerAsociadoLectura");
    if (!esAdministrador) {
        if (seccionForm) seccionForm.classList.add("d-none");
        if (bannerAsociado) bannerAsociado.classList.remove("d-none");
    } else {
        if (bannerAsociado) bannerAsociado.classList.add("d-none");
    }
}

function mostrarActividades() {
    if (!listaActividades) return;
    listaActividades.replaceChildren();

    if (actividades.length === 0) {
        const fila = document.createElement("tr");
        const celda = document.createElement("td");
        celda.colSpan = esAdministrador ? 5 : 4;
        celda.className = "text-center py-4 text-muted";
        celda.textContent = "No hay actividades registradas.";
        fila.append(celda);
        listaActividades.append(fila);
        return;
    }

    actividades.forEach((actividad) => {
        const fila = document.createElement("tr");

        const recaudo = actividad.totalRecaudado !== undefined ? actividad.totalRecaudado : actividad.totalRecaudo;
        const textoRecaudo = (recaudo === null || recaudo === undefined || recaudo === "" || Number(recaudo) === 0) 
            ? "Gratuito" 
            : Number(recaudo).toLocaleString("es-CO", { style: "currency", currency: "COP", maximumFractionDigits: 0 });

        const celdaNombre = crearCelda(actividad.nombreActividad);
        celdaNombre.className = "fw-semibold text-primary";

        fila.append(
            celdaNombre,
            crearCelda(actividad.fechaEvento),
            crearCelda(actividad.municipio),
            crearCelda(textoRecaudo)
        );

        if (esAdministrador) {
            const acciones = document.createElement("td");
            acciones.className = "text-nowrap text-center";

            const botonEditar = document.createElement("button");
            botonEditar.type = "button";
            botonEditar.className = "btn btn-sm btn-outline-primary me-2";
            botonEditar.title = "Editar actividad";
            botonEditar.innerHTML = '<i class="bi bi-pencil-square"></i> Editar';
            botonEditar.addEventListener("click", () => abrirEdicion(actividad));

            const botonEliminar = document.createElement("button");
            botonEliminar.type = "button";
            botonEliminar.className = "btn btn-sm btn-outline-danger";
            botonEliminar.title = "Eliminar actividad";
            botonEliminar.innerHTML = '<i class="bi bi-trash"></i> Eliminar';
            botonEliminar.addEventListener("click", () => eliminarActividad(actividad));

            acciones.append(botonEditar, botonEliminar);
            fila.append(acciones);
        }
        listaActividades.append(fila);
    });
}

function crearCelda(texto) {
    const celda = document.createElement("td");
    celda.textContent = texto || '-';
    return celda;
}

function abrirEdicion(actividad) {
    if (!esAdministrador) return;
    document.getElementById("editIdActividad").value = actividad.idActividad;
    document.getElementById("editNombreActividad").value = actividad.nombreActividad || '';
    document.getElementById("editFechaActividad").value = actividad.fechaEvento || '';
    document.getElementById("editLugarActividad").value = actividad.municipio || '';
    
    const recaudo = actividad.totalRecaudado !== undefined ? actividad.totalRecaudado : actividad.totalRecaudo;
    document.getElementById("editCostoActividad").value = recaudo ?? 0;

    const modalEl = document.getElementById("modalEditarActividad");
    if (!modalEditarActividadInstance && window.bootstrap) {
        modalEditarActividadInstance = new bootstrap.Modal(modalEl);
    }
    if (modalEditarActividadInstance) {
        modalEditarActividadInstance.show();
    }
}

async function guardarEdicionActividad(e) {
    e.preventDefault();
    const idActividad = document.getElementById("editIdActividad").value;
    const nombre = document.getElementById("editNombreActividad").value.trim();
    const fecha = document.getElementById("editFechaActividad").value;
    const lugar = document.getElementById("editLugarActividad").value.trim();
    const costo = parseFloat(document.getElementById("editCostoActividad").value) || 0;

    if (!idActividad || !nombre || !fecha || !lugar) {
        alert("Por favor complete todos los campos obligatorios.");
        return;
    }

    try {
        await solicitarApi("PUT", {
            idActividad: parseInt(idActividad, 10),
            nombreActividad: nombre,
            fechaEvento: fecha,
            municipio: lugar,
            totalRecaudado: costo
        });

        if (modalEditarActividadInstance) modalEditarActividadInstance.hide();
        alert("Actividad actualizada correctamente.");
        cargarActividades();
    } catch (error) {
        alert("Error al actualizar la actividad: " + error.message);
    }
}

async function eliminarActividad(actividad) {
    if (!esAdministrador) return;
    const confirmar = confirm(`¿Está seguro de eliminar la actividad "${actividad.nombreActividad}"?`);
    if (!confirmar) return;

    try {
        await solicitarApi("DELETE", { idActividad: actividad.idActividad });
        alert("Actividad eliminada correctamente.");
        cargarActividades();
    } catch (error) {
        alert("Error al eliminar la actividad: " + error.message);
    }
}

document.addEventListener("DOMContentLoaded", () => {
    if (localStorage.getItem("logeado") !== "true") {
        window.location.href = "Login.html";
        return;
    }

    cargarActividades();

    const formulario = document.getElementById("formRegistroActividades");
    if (formulario) {
        formulario.addEventListener("submit", async function (e) {
            e.preventDefault();
            if (!this.checkValidity()) {
                e.stopPropagation();
                this.classList.add("was-validated");
                return;
            }

            const btnSubmit = this.querySelector('button[type="submit"]');
            if (btnSubmit) btnSubmit.disabled = true;

            const datos = {
                nombreActividad: document.getElementById("nombreActividad").value.trim(),
                fechaEvento: document.getElementById("fechaActividad").value,
                municipio: document.getElementById("lugarActividad").value,
                totalRecaudado: parseFloat(document.getElementById("costoInscripcion").value) || 0
            };

            try {
                await solicitarApi("POST", datos);
                const alerta = document.getElementById("alertaExito");
                if (alerta) {
                    alerta.classList.remove("d-none");
                    alerta.classList.add("show");
                    alerta.scrollIntoView({ behavior: "smooth", block: "center" });
                }
                this.reset();
                this.classList.remove("was-validated");
                cargarActividades();
            } catch (err) {
                alert("Error al registrar la actividad: " + err.message);
            } finally {
                if (btnSubmit) btnSubmit.disabled = false;
            }
        });
    }

    const formEdicion = document.getElementById("formEditarActividadModal");
    if (formEdicion) {
        formEdicion.addEventListener("submit", guardarEdicionActividad);
    }
});
