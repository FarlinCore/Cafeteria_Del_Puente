/*
 * session-nav.js
 * Detecta sesion activa y actualiza el navbar dinamicamente en todas las paginas.
 * Se incluye al final del body en cada pagina HTML.
 *
 * Requiere que el navbar tenga:
 *   - Contenedor de botones auth con class "auth-btns"
 *
 * Tambien maneja los botones "Anadir" del menu si estan presentes.
 */

(function () {
    'use strict';

    /* ── Ruta base al backend ─────────────────────────────────────── */
    /*
     * Usamos el src del propio script para calcular la ruta base.
     * El script esta en:  .../Cafeteria-del-puente/js/session-nav.js
     * Necesitamos:        .../Cafeteria-del-puente/
     * Quitamos "js/session-nav.js" del final del src absoluto del script.
     */
    const BASE = (function () {
        var scripts = document.querySelectorAll('script[src]');
        var miSrc = '';
        for (var i = 0; i < scripts.length; i++) {
            if (scripts[i].src.indexOf('session-nav.js') !== -1) {
                miSrc = scripts[i].src;
                break;
            }
        }
        // Quitar "js/session-nav.js" del final -> queda la ruta raiz del proyecto
        return miSrc ? miSrc.replace(/js\/session-nav\.js[^]*$/, '') : '/';
    })();

    /* ── Consultar estado de sesion ──────────────────────────────── */
    fetch(BASE + 'backend/validaciones/val_session.php')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.logueado) {
                actualizarNavbar(data);
                habilitarCarrito(data);
            }
        })
        .catch(function () { /* sin sesion o sin conexion, no hacer nada */ });

    /* ── Reemplazar botones auth en el navbar ────────────────────── */
    function actualizarNavbar(data) {
        const contenedores = document.querySelectorAll('.auth-btns');
        const inicial = data.nombre ? data.nombre.charAt(0).toUpperCase() : 'U';
        const html =
            '<a href="' + BASE + 'panel-usuario.php" ' +
            '   class="flex items-center gap-2 py-2 px-5 rounded-full ' +
            '          bg-[#F47E24] hover:bg-[#e06b15] transition-all duration-300 ' +
            '          shadow-lg shadow-[#F47E24]/30">' +
            '  <div class="w-7 h-7 rounded-full bg-white/20 flex items-center justify-center ' +
            '              text-white font-bold text-xs flex-shrink-0">' + inicial + '</div>' +
            '  <span class="text-white text-sm font-semibold">' + escHtml(data.nombre) + '</span>' +
            '  <i class="fas fa-shopping-cart text-white text-xs ml-0.5"></i>' +
            '  <span id="nav-cant-carrito" class="bg-white text-[#F47E24] text-[10px] font-bold ' +
            '        w-4 h-4 rounded-full flex items-center justify-center leading-none ' +
            (data.cant_carrito > 0 ? '' : 'hidden') + '">' +
            data.cant_carrito + '</span>' +
            '</a>' +
            '<a href="' + BASE + 'backend/autenticacion/cerrar_sesion.php" ' +
            '   class="flex items-center gap-1.5 py-2 px-4 rounded-full border border-white/50 ' +
            '          text-white text-sm font-medium hover:text-red-400 hover:border-red-400/60 ' +
            '          transition-all duration-300 backdrop-blur-sm">' +
            '  <i class="fas fa-sign-out-alt text-xs"></i>' +
            '  <span>Salir</span>' +
            '</a>';

        contenedores.forEach(function (el) {
            el.innerHTML = html;
        });
    }

    /* ── Habilitar botones "Anadir" del menu ─────────────────────── */
    function habilitarCarrito(data) {
        const botones = document.querySelectorAll('[data-action="anadir"]');
        if (!botones.length) return;

        botones.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const card = btn.closest('[data-name]');
                const nombre = card ? card.getAttribute('data-name') : '';
                const precio = card ? card.getAttribute('data-price') : '0';

                if (!nombre) return;

                btn.disabled = true;
                btn.textContent = '...';

                fetch(BASE + 'backend/carrito/agregar_item.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'nombre_producto=' + encodeURIComponent(nombre)
                        + '&precio=' + encodeURIComponent(precio)
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res.ok) {
                            btn.textContent = 'Anadido';
                            btn.classList.add('bg-[#F47E24]', 'text-white');
                            actualizarBadgeCarrito(res.cant_carrito);
                            setTimeout(function () {
                                btn.disabled = false;
                                btn.textContent = 'Anadir';
                                btn.classList.remove('bg-[#F47E24]', 'text-white');
                            }, 1800);
                        } else if (res.no_sesion) {
                            window.location.href = BASE + 'login.html';
                        } else {
                            btn.disabled = false;
                            btn.textContent = 'Anadir';
                            mostrarToastSN(res.error || 'Error al agregar');
                        }
                    })
                    .catch(function () {
                        btn.disabled = false;
                        btn.textContent = 'Anadir';
                        mostrarToastSN('Error de conexion');
                    });
            });
        });
    }

    /* ── Actualizar badge del carrito en el navbar ───────────────── */
    function actualizarBadgeCarrito(cant) {
        document.querySelectorAll('#nav-cant-carrito').forEach(function (badge) {
            badge.textContent = cant;
            badge.classList.toggle('hidden', cant <= 0);
        });
    }

    /* ── Toast ligero (sin dependencias) ─────────────────────────── */
    function mostrarToastSN(msg) {
        let t = document.getElementById('sn-toast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'sn-toast';
            t.style.cssText =
                'position:fixed;bottom:24px;right:24px;z-index:9999;' +
                'background:#1e0f07;color:#fff;padding:10px 18px;' +
                'border-radius:14px;font-size:13px;font-weight:600;' +
                'box-shadow:0 8px 24px rgba(0,0,0,.35);display:none;' +
                'font-family:Poppins,sans-serif;';
            document.body.appendChild(t);
        }
        t.textContent = msg;
        t.style.display = 'block';
        clearTimeout(t._timer);
        t._timer = setTimeout(function () { t.style.display = 'none'; }, 2600);
    }

    /* ── Escapar html ────────────────────────────────────────────── */
    function escHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

})();
