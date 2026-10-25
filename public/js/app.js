// Confirmación antes de acciones sensibles: <form data-confirmar="¿Seguro?">
document.addEventListener('submit', (ev) => {
  const msg = ev.target.dataset.confirmar;
  if (msg && !confirm(msg)) ev.preventDefault();
});

// Menú lateral en celular y tablet: el botón ☰ lo abre; el fondo oscuro, Esc o elegir una opción lo cierran
function cambiarMenu(abrir) {
  const abierto = document.body.classList.toggle('menu-abierto', abrir);
  const boton = document.querySelector('.btn-menu');
  if (boton) boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
}
document.addEventListener('click', (ev) => {
  if (ev.target.closest('.btn-menu')) return cambiarMenu();
  if (ev.target.closest('[data-cerrar-menu]') || ev.target.closest('.menu a.nav')) cambiarMenu(false);

  // Mostrar / ocultar contraseña: <button data-ver-clave> junto al input
  const ojo = ev.target.closest('[data-ver-clave]');
  if (ojo) {
    const campo = ojo.parentElement.querySelector('input');
    if (campo) campo.type = campo.type === 'password' ? 'text' : 'password';
  }
});
document.addEventListener('keydown', (ev) => {
  if (ev.key === 'Escape') cambiarMenu(false);
});
