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

// CU25 Asistente virtual: muestra siempre el último mensaje del chat
const chat = document.querySelector('.chat');
if (chat) chat.scrollTop = chat.scrollHeight;

// CU25: botón 🎤 del chat = DICTADO (convierte la voz en texto dentro de la caja; no navega)
(function () {
  const boton = document.querySelector('[data-dictado]');
  const Reconocimiento = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!boton || !Reconocimiento || !window.isSecureContext) return;
  boton.hidden = false;
  const caja = boton.form.querySelector('[name=texto]');
  const rec = new Reconocimiento();
  rec.lang = 'es-BO';
  boton.addEventListener('click', () => { try { rec.start(); } catch (e) { rec.stop(); } });
  rec.onstart = () => boton.classList.add('escuchando');
  rec.onend = () => boton.classList.remove('escuchando');
  rec.onresult = (e) => { caja.value = e.results[0][0].transcript; caja.focus(); };
})();

// =====================================================================================
// Comando de voz (Web Speech API, Chrome / Edge / Safari, solo con HTTPS o localhost).
// Botón «Voz» de la barra superior. Reconoce:
//   - «agenda de hoy | mañana | pasado mañana»          -> CU15 con esa fecha
//   - «buscar paciente Juan Pérez»                       -> CU05 filtrado
//   - «reporte de ingresos | ausentismo | carga ... de hoy | esta semana | este mes»  -> CU18
//   - «abrir / ir a <pantalla del menú>»                 -> la pantalla más parecida que el rol puede abrir
//   - «preguntar <consulta>»  o cualquier otra frase     -> se la envía al asistente virtual (CU25)
//   - «atrás», «ayuda»
// El servidor sigue validando permisos: la voz solo navega a pantallas del menú del usuario.
// =====================================================================================
(function () {
  const boton = document.querySelector('[data-comando-voz]');
  const Reconocimiento = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!boton || !Reconocimiento || !window.isSecureContext) return;   // sin soporte: el botón queda oculto
  boton.hidden = false;

  const comandos = JSON.parse(document.getElementById('comandos-voz')?.textContent || '[]');
  const estado = document.querySelector('.voz-estado');
  const rec = new Reconocimiento();
  rec.lang = 'es-BO';
  rec.interimResults = false;
  rec.maxAlternatives = 3;

  const normalizar = (t) => t.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9 ]/g, ' ').replace(/\s+/g, ' ').trim();
  const puede = (ruta) => comandos.some((c) => c.ruta === ruta);
  const ir = (consulta) => { window.location.href = '?' + consulta; };
  const fecha = (dias) => { const d = new Date(); d.setDate(d.getDate() + dias); return d.toLocaleDateString('en-CA'); };
  function avisar(texto, segundos = 4) {
    estado.textContent = texto;
    estado.hidden = false;
    clearTimeout(avisar.t);
    avisar.t = setTimeout(() => { estado.hidden = true; }, segundos * 1000);
  }

  function periodo(t) {
    const hoy = new Date();
    if (t.includes('semana')) {
      const lunes = new Date(hoy); lunes.setDate(hoy.getDate() - ((hoy.getDay() + 6) % 7));
      const domingo = new Date(lunes); domingo.setDate(lunes.getDate() + 6);
      return [lunes.toLocaleDateString('en-CA'), domingo.toLocaleDateString('en-CA')];
    }
    if (t.includes('mes')) {
      return [new Date(hoy.getFullYear(), hoy.getMonth(), 1).toLocaleDateString('en-CA'),
              new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0).toLocaleDateString('en-CA')];
    }
    if (t.includes('hoy')) return [fecha(0), fecha(0)];
    return null;
  }

  /** Devuelve la acción para la frase reconocida, o null si no la entiende. */
  function interpretar(frase) {
    const t = normalizar(frase);
    if (/^(ayuda|que puedo decir|comandos)/.test(t)) {
      return () => avisar('Diga por ejemplo: «agenda de mañana», «buscar paciente Pérez», «reporte de ingresos de esta semana», «abrir mis citas» o «preguntar horario de atención».', 10);
    }
    if (/^(atras|volver)$/.test(t)) return () => history.back();
    if (t.includes('agenda') && puede('citas/agenda')) {
      const dias = t.includes('pasado manana') ? 2 : (t.includes('manana') ? 1 : 0);
      return () => ir('r=citas/agenda&fecha=' + fecha(dias));
    }
    let m = t.match(/^buscar (?:al |a )?paciente (.+)$/);
    if (m && puede('pacientes')) return () => ir('r=pacientes&criterio=' + encodeURIComponent(m[1]));
    if (/reporte|estadistic/.test(t) && puede('reportes')) {
      const tipo = t.includes('ingreso') ? 'ingresos' : t.includes('ausent') || t.includes('inasist') ? 'ausentismo'
                 : t.includes('carga') || t.includes('ausencia') ? 'carga' : 'productividad';
      const p = periodo(t);
      return () => ir('r=reportes&tipo=' + tipo + (p ? '&desde=' + p[0] + '&hasta=' + p[1] : ''));
    }
    m = t.match(/^(?:preguntar|pregunta|asistente) (.+)$/);
    if (m && puede('asistente')) return () => ir('r=asistente&texto=' + encodeURIComponent(m[1]));

    // Pantalla del menú con más palabras en común (ignorando «abrir», «ir a», «la», «de»...)
    const palabras = t.replace(/^(abrir|abre|ir a|ir al|ve a|mostrar|ver|quiero)\s+/, '').split(' ').filter((p) => p.length > 2);
    let mejor = null, puntaje = 0;
    for (const c of comandos) {
      const base = normalizar(c.texto + ' ' + c.ruta.replace(/[\/-]/g, ' '));
      const p = palabras.filter((w) => base.includes(w) || base.includes(w.slice(0, 5))).length;
      if (p > puntaje) { mejor = c; puntaje = p; }
    }
    if (mejor) return () => { avisar('Abriendo «' + mejor.texto + '»…'); ir('r=' + mejor.ruta); };
    if (puede('asistente')) return () => ir('r=asistente&texto=' + encodeURIComponent(frase));
    return null;
  }

  let escuchando = false;
  boton.addEventListener('click', () => {
    if (escuchando) { rec.stop(); return; }
    try { rec.start(); } catch (e) { return; }
  });
  rec.onstart = () => { escuchando = true; boton.classList.add('escuchando'); avisar('Escuchando… diga un comando o «ayuda».', 8); };
  rec.onend = () => { escuchando = false; boton.classList.remove('escuchando'); };
  rec.onerror = (e) => {
    avisar(e.error === 'not-allowed' ? 'Permita el uso del micrófono en el navegador para usar la voz.' : 'No se escuchó bien. Intente de nuevo.');
  };
  rec.onresult = (e) => {
    const alternativas = Array.from(e.results[0]).map((a) => a.transcript);
    avisar('«' + alternativas[0] + '»');
    for (const frase of alternativas) {
      const accion = interpretar(frase);
      if (accion) { accion(); return; }
    }
    avisar('No reconocí el comando «' + alternativas[0] + '». Diga «ayuda» para ver ejemplos.', 6);
  };
})();

// CU25: si se llegó con un comando de voz («preguntar …»), el mensaje se envía solo
const formularioChat = document.querySelector('form[data-enviar-solo]');
if (formularioChat && formularioChat.querySelector('[name=texto]').value.trim() !== '') formularioChat.requestSubmit();
