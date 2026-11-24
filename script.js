/**
 * script.js
 * Captura eventos, arma FormData, envía con fetch a registrar.php
 * y procesa la respuesta JSON con SweetAlert2.
 */
const form        = document.getElementById('formProducto');
const inputAccion = document.getElementById('Accion');
const inputId     = document.getElementById('id');
const inputCant   = document.getElementById('Cantidad');
const btnGuardar  = document.getElementById('btnGuardar');
const btnCancelar = document.getElementById('btnCancelar');
const tituloForm  = document.getElementById('tituloForm');
const tbody       = document.getElementById('tablaProductos');
const txtBuscar   = document.getElementById('txtBuscar');

let productosCache = []; // última lista cargada (para editar sin otra petición)

/* ---------- Utilidades ---------- */
function esc(texto) { // evita inyección HTML (XSS) al pintar la tabla
  const d = document.createElement('div');
  d.textContent = String(texto ?? '');
  return d.innerHTML;
}

function mostrarAlerta(mensaje, lista = []) {
  const items = lista.length ? '<ul class="mb-0">' + lista.map(m => `<li>${esc(m)}</li>`).join('') + '</ul>' : '';
  document.getElementById('alertaTexto').innerHTML = `<strong>${esc(mensaje)}</strong>${items}`;
  document.getElementById('alertaBootstrap').classList.remove('d-none');
}
function ocultarAlerta() {
  document.getElementById('alertaBootstrap').classList.add('d-none');
}

function limpiarErrores() {
  form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
  form.querySelectorAll('.invalid-feedback').forEach(el => (el.textContent = ''));
  ocultarAlerta();
}

function mostrarErrores(errores) {
  Object.entries(errores).forEach(([campo, msg]) => {
    const input = document.getElementById(campo);
    const fb = document.getElementById('err-' + campo);
    if (input) input.classList.add('is-invalid');
    if (fb) fb.textContent = msg;
  });
}

/* ---------- Modo del formulario (switch) ---------- */
function setModo(modo) {
  switch (modo) {
    case 'Modificar':
      inputAccion.value = 'Modificar';
      btnGuardar.textContent = 'Actualizar';
      btnGuardar.className = 'btn btn-warning';
      tituloForm.textContent = 'Editar producto';
      inputCant.min = '0';              // un producto agotado debe poder editarse
      btnCancelar.classList.remove('d-none');
      break;
    case 'Guardar':
    default:
      inputAccion.value = 'Guardar';
      btnGuardar.textContent = 'Registrar';
      btnGuardar.className = 'btn btn-success';
      tituloForm.textContent = 'Registrar producto';
      inputCant.min = '1';              // producto nuevo nace con existencia
      btnCancelar.classList.add('d-none');
      inputId.value = '';
      break;
  }
}

function reiniciarFormulario() {
  form.reset();
  limpiarErrores();
  setModo('Guardar');
}

/* ---------- Validación en cliente ---------- */
function validarCliente(modo) {
  const e = {};
  const codigo   = form.Codigo.value.trim();
  const producto = form.Producto.value.trim();
  const precio   = form.Precio.value.trim();
  const cantidad = form.Cantidad.value.trim();
  const minCant  = modo === 'Modificar' ? 0 : 1;

  if (!codigo) e.Codigo = 'El código es obligatorio.';
  else if (codigo.length > 20) e.Codigo = 'Máximo 20 caracteres.';

  if (!producto) e.Producto = 'El producto es obligatorio.';
  else if (producto.length > 100) e.Producto = 'Máximo 100 caracteres.';

  if (!precio) e.Precio = 'El precio es obligatorio.';
  else if (isNaN(precio) || parseFloat(precio) <= 0) e.Precio = 'Debe ser un número mayor que 0.';

  if (!cantidad) e.Cantidad = 'La cantidad es obligatoria.';
  else if (!/^-?\d+$/.test(cantidad)) e.Cantidad = 'Debe ser un número entero.';
  else if (parseInt(cantidad, 10) < minCant) e.Cantidad = `La cantidad mínima es ${minCant}.`;

  return e;
}

/* ---------- Comunicación con el servidor ---------- */
async function enviar(formData) {
  const resp = await fetch('registrar.php', { method: 'POST', body: formData });
  const texto = await resp.text();
  let data;
  try {
    data = JSON.parse(texto);
  } catch (err) {
    console.error('Respuesta no válida del servidor:', texto);
    throw new Error('El servidor no devolvió un JSON válido.');
  }
  if (!resp.ok && !data.message) throw new Error('Error HTTP ' + resp.status);
  return data;
}

/* ---------- Procesar respuesta (switch por acción) ---------- */
function procesarRespuesta(data) {
  switch (data.accion) {
    case 'Guardar':
    case 'Modificar':
      if (data.success) {
        Swal.fire({ icon: 'success', title: '¡Éxito!', text: data.message });
        reiniciarFormulario();
        ListarProductos();
      } else {
        mostrarErrores(data.errors || {});
        mostrarAlerta(data.message, Object.values(data.errors || {}));
        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
      }
      break;

    case 'Listar':
    case 'Buscar':
      if (data.success) {
        pintarTabla(data.data || []);
      } else {
        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
      }
      break;

    default:
      Swal.fire({ icon: 'warning', title: 'Atención', text: data.message || 'Respuesta desconocida.' });
  }
}

/* ---------- Listar / Buscar ---------- */
async function ListarProductos() {
  const fd = new FormData();
  fd.append('Accion', 'Listar');
  try {
    procesarRespuesta(await enviar(fd));
  } catch (err) {
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">No se pudo cargar el listado.</td></tr>';
    Swal.fire({ icon: 'error', title: 'Error de comunicación', text: err.message });
  }
}

async function buscarProductos() {
  const fd = new FormData();
  fd.append('Accion', 'Buscar');
  fd.append('Termino', txtBuscar.value.trim());
  try {
    const data = await enviar(fd);
    procesarRespuesta(data);
    if (data.success && (data.data || []).length === 0) {
      Swal.fire({ icon: 'info', title: 'Sin resultados', text: 'No se encontraron productos.' });
    }
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Error de comunicación', text: err.message });
  }
}

function pintarTabla(lista) {
  productosCache = lista;
  if (!lista.length) {
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No hay productos.</td></tr>';
    return;
  }
  tbody.innerHTML = lista.map(p => `
    <tr>
      <td>${esc(p.id)}</td>
      <td>${esc(p.codigo)}</td>
      <td>${esc(p.producto)}</td>
      <td class="text-end">$${Number(p.precio).toFixed(2)}</td>
      <td class="text-end">${Number(p.cantidad) === 0
        ? '<span class="badge bg-danger">Agotado</span>' : esc(p.cantidad)}</td>
      <td class="text-center">
        <button class="btn btn-sm btn-outline-warning" onclick="cargarEdicion(${Number(p.id)})">Editar</button>
      </td>
    </tr>`).join('');
}

function cargarEdicion(id) {
  const p = productosCache.find(x => Number(x.id) === id);
  if (!p) return;
  limpiarErrores();
  inputId.value = p.id;
  form.Codigo.value = p.codigo;
  form.Producto.value = p.producto;
  form.Precio.value = p.precio;
  form.Cantidad.value = p.cantidad;
  setModo('Modificar');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ---------- Eventos ---------- */
form.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  limpiarErrores();

  const modo = inputAccion.value; // 'Guardar' | 'Modificar'
  const errores = validarCliente(modo);
  if (Object.keys(errores).length) {
    mostrarErrores(errores);
    mostrarAlerta('Revise los campos del formulario.', Object.values(errores));
    Swal.fire({ icon: 'warning', title: 'Datos incompletos', text: 'Corrija los campos marcados.' });
    return;
  }

  try {
    procesarRespuesta(await enviar(new FormData(form)));
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Error de comunicación', text: err.message });
  }
});

btnCancelar.addEventListener('click', reiniciarFormulario);
document.getElementById('btnBuscar').addEventListener('click', buscarProductos);
document.getElementById('btnLimpiar').addEventListener('click', () => { txtBuscar.value = ''; ListarProductos(); });
txtBuscar.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); buscarProductos(); } });

document.addEventListener('DOMContentLoaded', () => { setModo('Guardar'); ListarProductos(); });
