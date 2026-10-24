// Confirmación antes de acciones sensibles: <form data-confirmar="¿Seguro?">
document.addEventListener('submit', (ev) => {
  const msg = ev.target.dataset.confirmar;
  if (msg && !confirm(msg)) ev.preventDefault();
});
