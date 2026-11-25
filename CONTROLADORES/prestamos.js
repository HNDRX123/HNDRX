let esAdministrador = false;
let prestamos = [];
let modalEditarPrestamoInstance = null;

function formatearMonedaCop(valor) {
    return Number(valor || 0).toLocaleString("es-CO", {
        style: "currency",
        currency: "COP",
        maximumFractionDigits: 0
    });
}

async function solicitarApiPrestamos(metodo, datos) {
    const opciones = {
        method: metodo,
        headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "X-Requested-With": "XMLHttpRequest",
            "X-User-Role": localStorage.getItem("rol") || "asociado",
            "X-User-Document": localStorage.getItem("documento") || ""
        }
    };

    if (datos && (metodo === "POST" || metodo === "PUT" || metodo === "DELETE")) {
        opciones.body = JSON.stringify(datos);
    }

    const respuesta = await fetch("../modelo/gestionarPrestamos.php", opciones);

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

function configurarVistaPrestamos() {
    const thAcciones = document.getElementById("thAccionesPrestamos");
    if (thAcciones) thAcciones.style.display = esAdministrador ? "" : "none";

    const btnToggle = document.getElementById("btnToggleFormulario");
    if (btnToggle) btnToggle.style.display = esAdministrador ? "" : "none";

    const seccionForm = document.getElementById("seccionFormularioPrestamo");
    const bannerAsociado = document.getElementById("bannerAsociadoLectura");

    if (!esAdministrador) {
        if (seccionForm) seccionForm.classList.add("d-none");
        if (bannerAsociado) bannerAsociado.classList.remove("d-none");
    } else if (bannerAsociado) {
        bannerAsociado.classList.add("d-none");
    }
}

function toggleFormulario() {
    if (!esAdministrador) return;
    const seccionForm = document.getElementById("seccionFormularioPrestamo");
    if (!seccionForm) return;

    seccionForm.classList.toggle("d-none");
    if (!seccionForm.classList.contains("d-none")) {
        seccionForm.scrollIntoView({ behavior: "smooth", block: "start" });
    }
}

function mostrarPrestamos() {
    const lista = document.getElementById("listaPrestamos");
    if (!lista) return;
    lista.replaceChildren();

    if (prestamos.length === 0) {
        const fila = document.createElement("tr");
        const celda = document.createElement("td");
        celda.colSpan = esAdministrador ? 8 : 7;
        celda.className = "text-center py-4 text-muted";
        celda.innerHTML = '<i class="bi bi-inbox fs-4 d-block mb-1"></i>No hay préstamos registrados.';
        fila.append(celda);
        lista.append(fila);
        return;
    }

    prestamos.forEach((prestamo) => {
        const fila = document.createElement("tr");

        const idStr = `#PREST-${String(prestamo.idPrestamo).padStart(4, "0")}`;
        const socioStr = (prestamo.nombres || prestamo.apellidos)
            ? `${prestamo.nombres || ""} ${prestamo.apellidos || ""}`.trim()
            : "Asociado";

        const celdaId = document.createElement("td");
        celdaId.className = "fw-semibold text-primary";
        celdaId.textContent = idStr;

        const celdaDoc = document.createElement("td");
        celdaDoc.textContent = prestamo.documento || "-";

        const celdaSocio = document.createElement("td");
        celdaSocio.textContent = socioStr;

        const celdaMonto = document.createElement("td");
        celdaMonto.className = "fw-bold text-success";
        celdaMonto.textContent = formatearMonedaCop(prestamo.valorPrestamo);

        const celdaTasa = document.createElement("td");
        celdaTasa.textContent = `${prestamo.tasaInteres || 1.2}%`;

        const celdaCuotas = document.createElement("td");
        celdaCuotas.textContent = `${prestamo.numeroCuotas || 12} cuotas`;

        const celdaFecha = document.createElement("td");
        celdaFecha.textContent = prestamo.fechaPrestamo || "-";

        fila.append(celdaId, celdaDoc, celdaSocio, celdaMonto, celdaTasa, celdaCuotas, celdaFecha);

        if (esAdministrador) {
            const acciones = document.createElement("td");
            acciones.className = "text-nowrap text-center";

            const btnEditar = document.createElement("button");
            btnEditar.type = "button";
            btnEditar.className = "btn btn-sm btn-outline-primary me-2";
            btnEditar.innerHTML = '<i class="bi bi-pencil-square"></i> Editar';
            btnEditar.addEventListener("click", () => abrirEdicionPrestamo(prestamo));

            const btnEliminar = document.createElement("button");
            btnEliminar.type = "button";
            btnEliminar.className = "btn btn-sm btn-outline-danger";
            btnEliminar.innerHTML = '<i class="bi bi-trash"></i> Eliminar';
            btnEliminar.addEventListener("click", () => eliminarPrestamo(prestamo));

            acciones.append(btnEditar, btnEliminar);
            fila.append(acciones);
        }

        lista.append(fila);
    });
}

async function cargarPrestamos() {
    const lista = document.getElementById("listaPrestamos");
    try {
        const resultado = await solicitarApiPrestamos("GET");
        const rolUsuario = (resultado.rol || localStorage.getItem("rol") || "asociado").toLowerCase();
        esAdministrador = rolUsuario === "admin";

        configurarVistaPrestamos();
        prestamos = resultado.prestamos || [];
        mostrarPrestamos();
    } catch (error) {
        console.error("Error al cargar préstamos:", error);
        if (lista) {
            lista.innerHTML = `<tr><td colspan="${esAdministrador ? 8 : 7}" class="text-center text-danger py-4">
                <i class="bi bi-exclamation-circle me-2"></i>No se pudieron cargar los préstamos: ${error.message}
            </td></tr>`;
        }
    }
}

function abrirEdicionPrestamo(prestamo) {
    if (!esAdministrador) return;

    document.getElementById("editIdPrestamo").value = prestamo.idPrestamo;
    document.getElementById("editValorPrestamo").value = prestamo.valorPrestamo || "";
    document.getElementById("editTasaInteres").value = prestamo.tasaInteres || "1.2";
    document.getElementById("editNumeroCuotas").value = prestamo.numeroCuotas || "12";
    document.getElementById("editFechaPrestamo").value = prestamo.fechaPrestamo || "";
    document.getElementById("editDetalleSocioPrestamo").textContent =
        `${prestamo.nombres || ""} ${prestamo.apellidos || ""} (Doc: ${prestamo.documento || "-"})`;

    const modalEl = document.getElementById("modalEditarPrestamo");
    if (!modalEditarPrestamoInstance && window.bootstrap && modalEl) {
        modalEditarPrestamoInstance = new bootstrap.Modal(modalEl);
    }
    if (modalEditarPrestamoInstance) {
        modalEditarPrestamoInstance.show();
    }
}

async function guardarEdicionPrestamo(e) {
    e.preventDefault();

    const idPrestamo = document.getElementById("editIdPrestamo").value;
    const valor = parseFloat(document.getElementById("editValorPrestamo").value);
    const tasa = parseFloat(document.getElementById("editTasaInteres").value);
    const cuotas = parseInt(document.getElementById("editNumeroCuotas").value, 10);
    const fecha = document.getElementById("editFechaPrestamo").value;

    if (!idPrestamo || Number.isNaN(valor) || valor <= 0 || Number.isNaN(tasa) || Number.isNaN(cuotas) || cuotas <= 0 || !fecha) {
        alert("Por favor revise los campos del préstamo.");
        return;
    }

    try {
        await solicitarApiPrestamos("PUT", {
            idPrestamo: parseInt(idPrestamo, 10),
            valorPrestamo: valor,
            tasaInteres: tasa,
            numeroCuotas: cuotas,
            fechaPrestamo: fecha
        });

        if (modalEditarPrestamoInstance) modalEditarPrestamoInstance.hide();
        alert("Préstamo actualizado exitosamente.");
        cargarPrestamos();
    } catch (error) {
        alert("Error al actualizar el préstamo: " + error.message);
    }
}

async function eliminarPrestamo(prestamo) {
    if (!esAdministrador) return;

    const idStr = `#PREST-${String(prestamo.idPrestamo).padStart(4, "0")}`;
    if (!confirm(`¿Está seguro de eliminar el crédito ${idStr} de ${prestamo.nombres || "Asociado"}? Esta acción no se puede deshacer.`)) {
        return;
    }

    try {
        await solicitarApiPrestamos("DELETE", { idPrestamo: prestamo.idPrestamo });
        alert("Préstamo eliminado correctamente.");
        cargarPrestamos();
    } catch (error) {
        alert("Error al eliminar el préstamo: " + error.message);
    }
}

function calcularCuotaEstimada() {
    const monto = parseFloat(document.getElementById("montoSolicitado")?.value) || 0;
    const meses = parseInt(document.getElementById("plazoMeses")?.value, 10) || 12;
    const tasaMensual = (parseFloat(document.getElementById("tasaInteres")?.value) || 1.2) / 100;
    const resultadoEl = document.getElementById("valorCuotaEstimada");

    if (!resultadoEl) return;

    if (monto > 0 && meses > 0) {
        const cuota = tasaMensual > 0
            ? (monto * tasaMensual) / (1 - Math.pow(1 + tasaMensual, -meses))
            : monto / meses;
        resultadoEl.textContent = `${formatearMonedaCop(Math.round(cuota))} / mes`;
    } else {
        resultadoEl.textContent = "$ 0 COP";
    }
}

document.addEventListener("DOMContentLoaded", () => {
    const sesionActiva = localStorage.getItem("logueado") === "true" || localStorage.getItem("logeado") === "true";
    if (!sesionActiva) {
        window.location.href = "Login.html";
        return;
    }

    const rol = (localStorage.getItem("rol") || "asociado").toLowerCase();
    esAdministrador = rol === "admin";
    configurarVistaPrestamos();
    cargarPrestamos();

    const hoy = new Date().toISOString().split("T")[0];
    const fechaInput = document.getElementById("fechaSolicitud");
    if (fechaInput) fechaInput.value = hoy;

    document.getElementById("montoSolicitado")?.addEventListener("input", calcularCuotaEstimada);
    document.getElementById("plazoMeses")?.addEventListener("change", calcularCuotaEstimada);
    document.getElementById("tasaInteres")?.addEventListener("input", calcularCuotaEstimada);
    calcularCuotaEstimada();

    const formulario = document.getElementById("formRegistroPrestamos");
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
                documentoAsociado: document.getElementById("documentoAsociado").value.trim(),
                valorPrestamo: parseFloat(document.getElementById("montoSolicitado").value),
                tasaInteres: parseFloat(document.getElementById("tasaInteres").value) || 1.2,
                numeroCuotas: parseInt(document.getElementById("plazoMeses").value, 10) || 12,
                fechaPrestamo: document.getElementById("fechaSolicitud").value || hoy
            };

            try {
                await solicitarApiPrestamos("POST", datos);
                const alerta = document.getElementById("alertaExito");
                if (alerta) {
                    alerta.classList.remove("d-none");
                    alerta.classList.add("show");
                    alerta.scrollIntoView({ behavior: "smooth", block: "center" });
                }
                this.reset();
                this.classList.remove("was-validated");
                if (fechaInput) fechaInput.value = hoy;
                calcularCuotaEstimada();
                cargarPrestamos();
            } catch (err) {
                alert("Error al registrar el préstamo: " + err.message);
            } finally {
                if (btnSubmit) btnSubmit.disabled = false;
            }
        });
    }

    const formEdicion = document.getElementById("formEditarPrestamoModal");
    if (formEdicion) {
        formEdicion.addEventListener("submit", guardarEdicionPrestamo);
    }
});