// Confirmación antes de acciones sensibles: <form data-confirmar="¿Seguro?">
document.addEventListener('submit', (ev) => {
  const msg = ev.target.dataset.confirmar;
  if (msg && !confirm(msg)) ev.preventDefault();
});

// Menú lateral en celular: el botón ☰ lo abre y lo cierra
document.addEventListener('click', (ev) => {
  const boton = ev.target.closest('.btn-menu');
  if (!boton) return;
  const abierto = document.body.classList.toggle('menu-abierto');
  boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
});
