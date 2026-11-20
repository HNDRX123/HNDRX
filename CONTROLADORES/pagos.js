let esAdministrador = false;
let pagos = [];
let modalEditarPagoInstance = null;

async function solicitarApiPagos(metodo, datos) {
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

    const respuesta = await fetch("../MODELO/gestionarPagos.php", opciones);

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
        throw new Error(resultado.mensaje || resultado.message || "Error en la operación.");
    }

    return resultado;
}

async function cargarPagos() {
    const lista = document.getElementById("listaPagos");
    try {
        const resultado = await solicitarApiPagos("GET");
        const rolUsuario = (resultado.rol || localStorage.getItem('rol') || 'asociado').toLowerCase();
        esAdministrador = (rolUsuario === 'admin');

        configurarVistaPagos();

        pagos = resultado.pagos || [];
        mostrarPagos();
    } catch (error) {
        console.error("Error al cargar pagos:", error);
        if (lista) {
            lista.innerHTML = `<tr><td colspan="${esAdministrador ? 7 : 6}" class="text-center text-danger py-4">
                <i class="bi bi-exclamation-circle me-2"></i>No se pudieron cargar los pagos: ${error.message}
            </td></tr>`;
        }
    }
}

function configurarVistaPagos() {
    const thAcciones = document.getElementById("thAccionesPagos");
    if (thAcciones) {
        thAcciones.style.display = esAdministrador ? "" : "none";
    }

    const btnToggle = document.getElementById("btnToggleFormulario");
    if (btnToggle) {
        btnToggle.style.display = esAdministrador ? "" : "none";
    }

    const seccionForm = document.getElementById("seccionFormularioPago");
    const bannerAsociado = document.getElementById("bannerAsociadoLectura");
    if (!esAdministrador) {
        if (seccionForm) seccionForm.classList.add("d-none");
        if (bannerAsociado) bannerAsociado.classList.remove("d-none");
    } else {
        if (bannerAsociado) bannerAsociado.classList.add("d-none");
    }
}

function toggleFormulario() {
    if (!esAdministrador) return;
    const seccionForm = document.getElementById("seccionFormularioPago");
    if (seccionForm) {
        seccionForm.classList.toggle("d-none");
        if (!seccionForm.classList.contains("d-none")) {
            seccionForm.scrollIntoView({ behavior: "smooth", block: "start" });
        }
    }
}

function mostrarPagos() {
    const lista = document.getElementById("listaPagos");
    if (!lista) return;
    lista.replaceChildren();

    if (pagos.length === 0) {
        const fila = document.createElement("tr");
        const celda = document.createElement("td");
        celda.colSpan = esAdministrador ? 7 : 6;
        celda.className = "text-center py-4 text-muted";
        celda.innerHTML = '<i class="bi bi-inbox fs-4 d-block mb-1"></i>No hay registros de pagos disponibles.';
        fila.append(celda);
        lista.append(fila);
        return;
    }

    pagos.forEach((pago) => {
        const fila = document.createElement("tr");

        const idPagoStr = `#PAG-${String(pago.idPago).padStart(4, '0')}`;
        const docStr = pago.documento || '-';
        const socioStr = (pago.nombres || pago.apellidos) 
            ? `${pago.nombres || ''} ${pago.apellidos || ''}`.trim() 
            : 'Asociado General';
        const fechaStr = pago.fechaPago || '-';
        const valorNum = Number(pago.valorCuota || 0);
        const valorStr = valorNum.toLocaleString("es-CO", { style: "currency", currency: "COP", maximumFractionDigits: 0 });
        const refPrestamo = pago.idPrestamo ? `#PREST-${String(pago.idPrestamo).padStart(4, '0')}` : 'Cuota General';

        const celdaId = document.createElement("td");
        celdaId.className = "fw-semibold text-primary";
        celdaId.textContent = idPagoStr;

        const celdaDoc = document.createElement("td");
        celdaDoc.textContent = docStr;

        const celdaSocio = document.createElement("td");
        celdaSocio.textContent = socioStr;

        const celdaFecha = document.createElement("td");
        celdaFecha.textContent = fechaStr;

        const celdaValor = document.createElement("td");
        celdaValor.className = "fw-bold text-success";
        celdaValor.textContent = valorStr;

        const celdaRef = document.createElement("td");
        celdaRef.innerHTML = `<span class="badge bg-light text-dark border">${refPrestamo}</span>`;

        fila.append(celdaId, celdaDoc, celdaSocio, celdaFecha, celdaValor, celdaRef);

        if (esAdministrador) {
            const acciones = document.createElement("td");
            acciones.className = "text-nowrap text-center";

            const btnEditar = document.createElement("button");
            btnEditar.type = "button";
            btnEditar.className = "btn btn-sm btn-outline-primary me-2";
            btnEditar.title = "Editar pago";
            btnEditar.innerHTML = '<i class="bi bi-pencil-square"></i> Editar';
            btnEditar.addEventListener("click", () => abrirEdicionPago(pago));

            const btnEliminar = document.createElement("button");
            btnEliminar.type = "button";
            btnEliminar.className = "btn btn-sm btn-outline-danger";
            btnEliminar.title = "Eliminar pago";
            btnEliminar.innerHTML = '<i class="bi bi-trash"></i> Eliminar';
            btnEliminar.addEventListener("click", () => eliminarPago(pago));

            acciones.append(btnEditar, btnEliminar);
            fila.append(acciones);
        }

        lista.append(fila);
    });
}

function abrirEdicionPago(pago) {
    if (!esAdministrador) return;
    document.getElementById("editIdPago").value = pago.idPago;
    document.getElementById("editValorCuota").value = pago.valorCuota || '';
    document.getElementById("editFechaPago").value = pago.fechaPago || '';
    document.getElementById("editDetalleAsociado").textContent = 
        `${pago.nombres || ''} ${pago.apellidos || ''} (Doc: ${pago.documento || '-'})`;

    const modalEl = document.getElementById("modalEditarPago");
    if (!modalEditarPagoInstance && window.bootstrap) {
        modalEditarPagoInstance = new bootstrap.Modal(modalEl);
    }
    if (modalEditarPagoInstance) {
        modalEditarPagoInstance.show();
    }
}

async function guardarEdicionPago(e) {
    e.preventDefault();
    const idPago = document.getElementById("editIdPago").value;
    const valorCuota = parseFloat(document.getElementById("editValorCuota").value);
    const fechaPago = document.getElementById("editFechaPago").value;

    if (!idPago || isNaN(valorCuota) || valorCuota <= 0 || !fechaPago) {
        alert("Por favor ingrese un valor y una fecha válidos.");
        return;
    }

    try {
        await solicitarApiPagos("PUT", {
            idPago: parseInt(idPago, 10),
            valorCuota: valorCuota,
            fechaPago: fechaPago
        });

        if (modalEditarPagoInstance) modalEditarPagoInstance.hide();
        alert("Pago actualizado exitosamente.");
        cargarPagos();
    } catch (error) {
        alert("Error al actualizar el pago: " + error.message);
    }
}

async function eliminarPago(pago) {
    if (!esAdministrador) return;
    const idPagoStr = `#PAG-${String(pago.idPago).padStart(4, '0')}`;
    const confirmar = confirm(`¿Está seguro de eliminar el registro de pago ${idPagoStr} por valor de ${Number(pago.valorCuota).toLocaleString('es-CO', { style: 'currency', currency: 'COP' })}?`);
    if (!confirmar) return;

    try {
        await solicitarApiPagos("DELETE", { idPago: pago.idPago });
        alert("Pago eliminado correctamente.");
        cargarPagos();
    } catch (error) {
        alert("Error al eliminar el pago: " + error.message);
    }
}

document.addEventListener("DOMContentLoaded", () => {
    if (localStorage.getItem("logeado") !== "true") {
        window.location.href = "Login.html";
        return;
    }

    const rol = (localStorage.getItem('rol') || 'asociado').toLowerCase();
    esAdministrador = (rol === 'admin');
    configurarVistaPagos();
    cargarPagos();

    // Fecha por defecto en formularios
    const hoy = new Date().toISOString().split('T')[0];
    const fechaInput = document.getElementById('fechaPago');
    if (fechaInput) fechaInput.value = hoy;

    // Manejo de formulario de registro de pago
    const formulario = document.getElementById("formRegistroPago");
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
                monto: parseFloat(document.getElementById("monto").value),
                fechaPago: document.getElementById("fechaPago").value || hoy,
                documentoAsociado: document.getElementById("documentoAsociado").value.trim(),
                numeroPrestamo: document.getElementById("numeroPrestamo") ? parseInt(document.getElementById("numeroPrestamo").value, 10) || null : null,
                conceptoPago: document.getElementById("conceptoPago") ? document.getElementById("conceptoPago").value : "Cuota Ordinaria"
            };

            try {
                await solicitarApiPagos("POST", datos);
                const alerta = document.getElementById("alertaExito");
                if (alerta) {
                    alerta.classList.remove("d-none");
                    alerta.classList.add("show");
                    alerta.scrollIntoView({ behavior: "smooth", block: "center" });
                }
                this.reset();
                this.classList.remove("was-validated");
                if (fechaInput) fechaInput.value = hoy;
                cargarPagos();
            } catch (err) {
                alert("Error al registrar el pago: " + err.message);
            } finally {
                if (btnSubmit) btnSubmit.disabled = false;
            }
        });
    }

    const formEdicion = document.getElementById("formEditarPagoModal");
    if (formEdicion) {
        formEdicion.addEventListener("submit", guardarEdicionPago);
    }
});
