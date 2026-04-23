<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once './backend/config/conexion.php';

// ── Cargar contenido editable desde BD ─────────────────────────────────
$contenido = [];
try {
    $rows = $pdo->query("SELECT clave, valor FROM contenido_web")->fetchAll();
    foreach ($rows as $r) $contenido[$r['clave']] = $r['valor'];
} catch (PDOException $e) { /* tabla puede no existir aún */ }

$historia      = $contenido['historia']      ?? '';
$stat_anos     = $contenido['stat_anos']     ?? '—';
$stat_platos   = $contenido['stat_platos']   ?? '—';
$stat_sonrisas = $contenido['stat_sonrisas'] ?? '—';
$dias = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo'];
$horarios = [];
foreach ($dias as $d) {
    $horarios[$d] = $contenido['horario_'.$d] ?? '— Próximamente —';
}
$es_admin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sobre Nosotros – Cafetería del Puente</title>
    <meta name="description"
        content="Conoce la historia, los valores y el horario de Cafetería del Puente. Un espacio hecho para ti.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- CDN de Tailwind eliminado: se usa el style.css compilado para evitar conflictos de reset -->
    <link rel="stylesheet" href="style.css">
    <style>
        .glass-hover {
            transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .glass-hover:hover {
            background: rgba(255, 255, 255, 0.22) !important;
            border-color: rgba(255, 255, 255, 0.80) !important;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.25), 0 4px 20px rgba(255, 255, 255, 0.10);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: #ffffff !important;
        }

        .auth-divider {
            border-left: 1px solid rgba(255, 255, 255, 0.20);
        }

        /* ── FIX CROSS-BROWSER: imágenes achatadas en Firefox/Safari/Edge ── */
        /* El hero hero background image */
        section.relative img.object-cover,
        section.relative img.object-contain {
            width: 100% !important;
            object-fit: cover !important;
        }
        /* Foto de historia (columna derecha) */
        .rounded-2xl > img,
        .rounded-2xl img.object-cover {
            width: 100% !important;
            object-fit: cover !important;
            object-position: center !important;
        }
        /* Logos SVG — sin !important para que h-10 del navbar funcione */
        img[src$=".svg"] {
            height: auto;
        }
        /* Logo del navbar: tamaño estándar */
        header img[src$=".svg"] {
            height: 4rem !important;
            width: auto !important;
            display: block !important;
        }
        /* Hero banner: imagen de fondo a pantalla completa */
        .hero-bg-img {
            position: absolute !important;
            inset: 0 !important;
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            object-position: center !important;
        }
    </style>
</head>

<body class="bg-[#1e0f07] text-white">

    <!-- ====================================================== -->
    <!-- NAVBAR -->
    <!-- ====================================================== -->
    <header
        class="w-full top-0 z-50 lg:px-16 px-4 bg-[#1e0f07]/97 backdrop-blur-sm flex flex-wrap items-center py-3 shadow-xl border-b border-[#F47E24]/15">
        <div class="flex-1 flex justify-between items-center">
            <a href="./index.html">
                <img src="./images/logo-blanco.svg" class="h-10" alt="Logo Cafetería del Puente">
            </a>
        </div>

        <label for="menu-toggle" class="pointer-cursor md:hidden block">
            <svg class="fill-current text-white" xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                viewBox="0 0 20 20">
                <title>Menú de navegación</title>
                <path d="M0 3h20v2H0V3zm0 6h20v2H0V9zm0 6h20v2H0v-2z"></path>
            </svg>
        </label>
        <input class="hidden" type="checkbox" id="menu-toggle" />

        <div class="hidden md:flex md:items-center md:w-auto w-full" id="menu">
            <nav>
                <ul class="md:flex items-center justify-between text-base text-white pt-4 md:pt-0">
                    <li><a class="md:p-4 py-3 px-0 block hover:text-[#F47E24] transition-colors duration-200"
                            href="./index.html">Inicio</a></li>
                    <li><a class="md:p-4 py-3 px-0 block hover:text-[#F47E24] transition-colors duration-200"
                            href="./menu.php">Menú</a></li>
                    <li><a class="md:p-4 py-3 px-0 block text-[#F47E24] font-semibold"
                            href="./sobre-nosotros.php">Sobre Nosotros</a></li>
                    <li><a class="md:p-4 py-3 px-0 block hover:text-[#F47E24] transition-colors duration-200"
                            href="./nuestra-gente.html">Nuestra Gente</a></li>
                    <li><a class="md:p-4 py-3 px-0 block hover:text-[#F47E24] transition-colors duration-200"
                            href="./contacto.html">Contacto</a></li>
                </ul>
            </nav>
            <!-- Auth buttons -->
            <div class="hidden md:flex items-center gap-3 ml-10 pl-8 auth-divider auth-btns">
                <a href="./login.html"
                    class="py-2 px-5 border border-white/50 text-white rounded-full text-sm font-medium transition-all duration-300 backdrop-blur-sm glass-hover">
                    <i class="fas fa-sign-in-alt mr-1 text-xs"></i> Iniciar Sesión
                </a>
                <a href="./registro.html"
                    class="py-2 px-5 bg-[#F47E24] hover:bg-[#e06b15] text-white rounded-full text-sm font-semibold transition-all duration-300 shadow-lg shadow-[#F47E24]/30">
                    <i class="fas fa-user-plus mr-1 text-xs"></i> Registrarse
                </a>
            </div>
        </div>
    </header>

    <!-- ====================================================== -->
    <!-- HERO BANNER — imagen con degradado hacia el fondo -->
    <!-- ====================================================== -->
    <section class="relative flex items-center justify-center overflow-hidden" style="height:65vh; min-height:420px">

        <!-- Background image -->
        <img src="./images/puente fondo.jpg" alt="Cafetería del Puente"
            class="hero-bg-img scale-105">

        <!-- Gradient: imagen visible arriba, funde al color de página abajo -->
        <div class="absolute inset-0"
            style="background: linear-gradient(to bottom, rgba(30,15,7,0.30) 0%, rgba(0,0,0,0.40) 40%, rgba(30,15,7,0.88) 75%, #1e0f07 100%);">
        </div>

        <!-- Content -->
        <div class="relative z-10 text-center px-6">
            <span
                class="inline-block text-white uppercase tracking-[0.3em] text-xs font-bold mb-4 px-5 py-2 border border-white/30 rounded-full bg-[#F47E24]/10">
                ☕ Nuestra historia
            </span>
            <h1 class="text-5xl md:text-7xl font-extrabold text-white mt-4 tracking-tight leading-none drop-shadow-2xl">
                Sobre <span class="text-[#F47E24]">Nosotros</span>
            </h1>
            <p class="text-[#f5dfc0] mt-5 text-lg max-w-xl mx-auto leading-relaxed">
                Un espacio donde el sabor, la gente y el puente se encuentran cada día.
            </p>
        </div>
    </section>

    <!-- ====================================================== -->
    <!-- NUESTRA HISTORIA — Placeholder para admin -->
    <!-- ====================================================== -->
    <section class="bg-[#1e0f07] py-20 px-4 md:px-10">
        <div class="max-w-6xl mx-auto">

            <!-- Título — encima del grid, ancho completo -->
            <div class="mb-10 flex justify-between items-center">
                <div>
                    <span class="text-[#F47E24] uppercase tracking-[0.25em] text-xs font-bold">Quiénes somos</span>
                    <h2 class="text-4xl md:text-5xl font-bold text-[#f5ece4] mt-3">
                        Nuestra <span class="text-[#F47E24]">Historia</span>
                    </h2>
                </div>
                <?php if ($es_admin): ?>
                <button id="editBtn" class="bg-[#F47E24] text-white px-4 py-2 rounded-lg hover:bg-[#e06b15] transition-colors flex items-center gap-2">
                    <i class="fas fa-edit"></i>Editar página
                </button>
                <?php endif; ?>
            </div>

            <!-- Grid: 2 columnas — historia izq | foto der -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-start">

                <!-- LEFT: Historia + Stats -->
                <div>
                    <!-- Historia dinámica -->
                    <?php if (!empty($historia)): ?>
                    <div class="relative border border-[#F47E24]/25 rounded-2xl p-7 bg-[#3d1e0b]">
                        <p id="texto-historia" class="text-[#c9a882] text-sm leading-relaxed"><?= nl2br(htmlspecialchars($historia)) ?></p>
                    </div>
                    <?php else: ?>
                    <!-- Placeholder si no hay historia -->
                    <div class="relative border border-dashed border-[#F47E24]/25 rounded-2xl p-7 bg-[#3d1e0b] overflow-hidden">
                        <span class="absolute top-4 right-4 text-[10px] uppercase tracking-widest text-[#F47E24]/70 border border-[#F47E24]/20 rounded-full px-3 py-1 bg-[#F47E24]/5">Próximamente</span>
                        <div class="space-y-3 mt-4">
                            <div class="h-3 bg-[#F47E24]/12 rounded-full w-full"></div>
                            <div class="h-3 bg-[#F47E24]/12 rounded-full w-5/6"></div>
                            <div class="h-3 bg-[#F47E24]/12 rounded-full w-full"></div>
                            <div class="h-3 bg-[#F47E24]/12 rounded-full w-4/6"></div>
                        </div>
                        <div class="space-y-3 mt-5">
                            <div class="h-3 bg-[#F47E24]/12 rounded-full w-full"></div>
                            <div class="h-3 bg-[#F47E24]/12 rounded-full w-3/4"></div>
                            <div class="h-3 bg-[#F47E24]/12 rounded-full w-full"></div>
                        </div>
                        <div class="mt-8 flex items-start gap-3 border-t border-[#F47E24]/15 pt-5">
                            <div class="w-8 h-8 rounded-full bg-[#F47E24]/10 flex items-center justify-center flex-shrink-0 mt-0.5">
                                <i class="fas fa-pen text-[#F47E24]/60 text-xs"></i>
                            </div>
                            <p class="text-[#c9a882] text-xs leading-relaxed italic">
                                Este espacio está reservado para la historia de Cafetería del Puente.
                                El administrador podrá completar este contenido desde el panel admin.
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Stats dinámicas -->
                    <div class="grid grid-cols-3 gap-4 mt-6">
                        <div class="bg-[#3d1e0b] border border-[#F47E24]/20 rounded-2xl p-4 text-center">
                            <div class="text-2xl font-bold text-[#F47E24]" id="stat-anos"><?= htmlspecialchars($stat_anos) ?></div>
                            <div class="text-[#c9a882] text-xs mt-2 uppercase tracking-wider leading-tight">Años de historia</div>
                        </div>
                        <div class="bg-[#3d1e0b] border border-[#F47E24]/20 rounded-2xl p-4 text-center">
                            <div class="text-2xl font-bold text-[#F47E24]" id="stat-platos"><?= htmlspecialchars($stat_platos) ?></div>
                            <div class="text-[#c9a882] text-xs mt-2 uppercase tracking-wider leading-tight">Platos servidos</div>
                        </div>
                        <div class="bg-[#3d1e0b] border border-[#F47E24]/20 rounded-2xl p-4 text-center">
                            <div class="text-2xl font-bold text-[#F47E24]" id="stat-sonrisas"><?= htmlspecialchars($stat_sonrisas) ?></div>
                            <div class="text-[#c9a882] text-xs mt-2 uppercase tracking-wider leading-tight">Sonrisas al día</div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Foto sola -->
                <div
                    class="relative rounded-2xl overflow-hidden shadow-2xl ring-1 ring-[#F47E24]/20 h-full min-h-[460px]">
                    <img src="./images/puente fondo.jpg" alt="Cafetería del Puente"
                        class="w-full h-full object-cover object-center">
                    <div
                        class="absolute inset-0 bg-gradient-to-t from-[#1e0f07]/90 via-transparent to-transparent flex items-end p-7">
                        <div>
                            <h3 class="text-xl font-bold text-white mb-1">Del Puente Cafetería</h3>
                            <p class="text-[#f5dfc0] text-sm">Ven y pasa un grandioso momento aquí. Un espacio hecho
                                para ti.</p>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </section>

    <!-- Modal de Edición Admin -->
    <?php if ($es_admin): ?>
    <div id="editModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="bg-[#1e0f07] px-6 py-4 flex items-center justify-between">
                <h3 class="text-white font-bold text-lg">Editar contenido de la página</h3>
                <button id="cancelBtn" class="text-white/40 hover:text-white text-xl">&times;</button>
            </div>
            <div class="p-6">
                <form id="editForm">
                    <div class="mb-5">
                        <label class="block text-gray-700 font-semibold mb-2"><i class="fas fa-book text-[#F47E24] mr-2"></i>Historia de la cafetería</label>
                        <textarea id="historia" name="historia" class="w-full p-3 border border-gray-200 rounded-xl text-sm" rows="5" placeholder="Escribe la historia del local..."><?= htmlspecialchars($historia) ?></textarea>
                    </div>
                    <div class="mb-5">
                        <label class="block text-gray-700 font-semibold mb-3"><i class="fas fa-clock text-[#F47E24] mr-2"></i>Horarios de atención</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <?php foreach ($dias as $d):
                                $nombres = ['lunes'=>'Lunes','martes'=>'Martes','miercoles'=>'Miércoles','jueves'=>'Jueves','viernes'=>'Viernes','sabado'=>'Sábado','domingo'=>'Domingo'];
                            ?>
                            <div>
                                <label class="block text-gray-600 text-xs font-semibold mb-1"><?= $nombres[$d] ?></label>
                                <input type="text" name="horario_<?= $d ?>" value="<?= htmlspecialchars($horarios[$d]) ?>" placeholder="Ej: 7:00 AM – 5:00 PM o Cerrado"
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-5">
                        <label class="block text-gray-700 font-semibold mb-3"><i class="fas fa-chart-bar text-[#F47E24] mr-2"></i>Estadísticas</label>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-gray-600 text-xs font-semibold mb-1">Años de historia</label>
                                <input type="text" name="stat_anos" value="<?= htmlspecialchars($stat_anos) ?>" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-gray-600 text-xs font-semibold mb-1">Platos servidos</label>
                                <input type="text" name="stat_platos" value="<?= htmlspecialchars($stat_platos) ?>" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-gray-600 text-xs font-semibold mb-1">Sonrisas al día</label>
                                <input type="text" name="stat_sonrisas" value="<?= htmlspecialchars($stat_sonrisas) ?>" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                            </div>
                        </div>
                    </div>
                    <div id="edit-msg" class="hidden mb-4 p-3 rounded-xl text-sm font-semibold"></div>
                    <div class="flex justify-end gap-3">
                        <button type="button" id="cancelBtn2" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition-all">Cancelar</button>
                        <button type="submit" class="px-5 py-2.5 bg-[#F47E24] hover:bg-[#e06b15] text-white rounded-xl font-semibold transition-all">
                            <i class="fas fa-save mr-2"></i>Guardar cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ====================================================== -->
    <!-- NUESTROS VALORES -->
    <!-- ====================================================== -->
    <section class="bg-[#2c1408] py-20 px-4 md:px-10">
        <div class="max-w-6xl mx-auto">

            <div class="text-center mb-14">
                <span class="text-[#F47E24] uppercase tracking-[0.25em] text-xs font-bold">Lo que nos mueve</span>
                <h2 class="text-4xl md:text-5xl font-bold text-[#f5ece4] mt-3">
                    Nuestros <span class="text-[#F47E24]">Valores</span>
                </h2>
                <div class="w-16 h-1 bg-[#F47E24] rounded-full mx-auto mt-5"></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">

                <!-- Calidad -->
                <div
                    class="bg-[#3d1e0b] border border-[#F47E24]/15 rounded-2xl p-7 flex flex-col items-center text-center hover:border-[#F47E24]/45 hover:bg-[#4d2610] transition-all duration-300 group">
                    <div
                        class="w-14 h-14 rounded-2xl bg-[#F47E24]/15 flex items-center justify-center mb-5 group-hover:bg-[#F47E24]/30 transition-colors duration-300">
                        <i class="fas fa-star text-[#F47E24] text-xl"></i>
                    </div>
                    <h3 class="text-[#f5ece4] font-bold text-lg mb-2">Calidad</h3>
                    <p class="text-[#c9a882] text-sm leading-relaxed">Ingredientes frescos y preparación con dedicación
                        en cada pedido.</p>
                </div>

                <!-- Tradición -->
                <div
                    class="bg-[#3d1e0b] border border-[#F47E24]/15 rounded-2xl p-7 flex flex-col items-center text-center hover:border-[#F47E24]/45 hover:bg-[#4d2610] transition-all duration-300 group">
                    <div
                        class="w-14 h-14 rounded-2xl bg-[#F47E24]/15 flex items-center justify-center mb-5 group-hover:bg-[#F47E24]/30 transition-colors duration-300">
                        <i class="fas fa-heart text-[#F47E24] text-xl"></i>
                    </div>
                    <h3 class="text-[#f5ece4] font-bold text-lg mb-2">Tradición</h3>
                    <p class="text-[#c9a882] text-sm leading-relaxed">Sabores auténticos que conectan con las raíces de
                        nuestra comunidad.</p>
                </div>

                <!-- Comunidad -->
                <div
                    class="bg-[#3d1e0b] border border-[#F47E24]/15 rounded-2xl p-7 flex flex-col items-center text-center hover:border-[#F47E24]/45 hover:bg-[#4d2610] transition-all duration-300 group">
                    <div
                        class="w-14 h-14 rounded-2xl bg-[#F47E24]/15 flex items-center justify-center mb-5 group-hover:bg-[#F47E24]/30 transition-colors duration-300">
                        <i class="fas fa-people-group text-[#F47E24] text-xl"></i>
                    </div>
                    <h3 class="text-[#f5ece4] font-bold text-lg mb-2">Comunidad</h3>
                    <p class="text-[#c9a882] text-sm leading-relaxed">Un espacio donde todos son bienvenidos y cada
                        visita es especial.</p>
                </div>

                <!-- Sabor -->
                <div
                    class="bg-[#3d1e0b] border border-[#F47E24]/15 rounded-2xl p-7 flex flex-col items-center text-center hover:border-[#F47E24]/45 hover:bg-[#4d2610] transition-all duration-300 group">
                    <div
                        class="w-14 h-14 rounded-2xl bg-[#F47E24]/15 flex items-center justify-center mb-5 group-hover:bg-[#F47E24]/30 transition-colors duration-300">
                        <i class="fas fa-utensils text-[#F47E24] text-xl"></i>
                    </div>
                    <h3 class="text-[#f5ece4] font-bold text-lg mb-2">Sabor</h3>
                    <p class="text-[#c9a882] text-sm leading-relaxed">Cada bocado cuenta una historia. El sabor es
                        nuestra firma.</p>
                </div>

            </div>
        </div>
    </section>

    <!-- ====================================================== -->
    <!-- HORARIO DE ATENCIÓN -->
    <!-- ====================================================== -->
    <section class="bg-[#1e0f07] py-20 px-4 md:px-10">
        <div class="max-w-4xl mx-auto">

            <div class="text-center mb-12">
                <span class="text-[#F47E24] uppercase tracking-[0.25em] text-xs font-bold">Cuándo visitarnos</span>
                <h2 class="text-4xl md:text-5xl font-bold text-[#f5ece4] mt-3">
                    Horario de <span class="text-[#F47E24]">Atención</span>
                </h2>
                <div class="w-16 h-1 bg-[#F47E24] rounded-full mx-auto mt-5"></div>
            </div>

            <div class="bg-[#2c1408] border border-[#F47E24]/15 rounded-2xl overflow-hidden shadow-2xl">

                <!-- Header row -->
                <div class="grid grid-cols-2 bg-[#F47E24]/15 border-b border-[#F47E24]/20 px-6 py-4">
                    <span class="text-[#F47E24] text-xs font-bold uppercase tracking-widest">Día</span>
                    <span class="text-[#F47E24] text-xs font-bold uppercase tracking-widest">Horario</span>
                </div>

                <!-- Days -->
                <div id="schedule-rows">
                    <?php
                    $colores = [
                        'lunes'=>'green','martes'=>'green','miercoles'=>'green',
                        'jueves'=>'green','viernes'=>'green','sabado'=>'amber','domingo'=>'red'
                    ];
                    $nombres = [
                        'lunes'=>'Lunes','martes'=>'Martes','miercoles'=>'Miércoles',
                        'jueves'=>'Jueves','viernes'=>'Viernes','sabado'=>'Sábado','domingo'=>'Domingo'
                    ];
                    foreach ($dias as $i => $d):
                        $color = $colores[$d];
                        $h = $horarios[$d];
                        $cerrado = strtolower(trim($h)) === 'cerrado';
                        $dotColor = $cerrado ? 'bg-red-400' : ($color==='amber' ? 'bg-amber-400' : 'bg-green-500');
                        $isLast = ($i === count($dias)-1);
                    ?>
                    <div class="grid grid-cols-2 px-6 py-4 <?= $isLast?'':'border-b border-[#F47E24]/10' ?> hover:bg-[#F47E24]/5 transition-colors duration-150">
                        <span class="text-white font-medium flex items-center gap-3">
                            <span class="w-2 h-2 rounded-full <?= $dotColor ?>"></span><?= $nombres[$d] ?>
                        </span>
                        <span class="text-sm flex items-center gap-2">
                            <i class="fas fa-clock text-[#F47E24]/40 text-xs"></i>
                            <span id="hor-<?= $d ?>" class="<?= $cerrado ? 'text-red-400' : 'text-[#f5ece4]' ?> font-medium"><?= htmlspecialchars($h) ?></span>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Legend -->
                <div class="px-6 py-4 border-t border-[#F47E24]/15 bg-[#1e0f07]/50 flex items-center gap-5">
                    <span class="flex items-center gap-1.5 text-xs text-[#a07050]">
                        <span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span> Abierto
                    </span>
                    <span class="flex items-center gap-1.5 text-xs text-[#a07050]">
                        <span class="w-2 h-2 rounded-full bg-amber-400 inline-block"></span> Horario especial
                    </span>
                    <span class="flex items-center gap-1.5 text-xs text-[#a07050]">
                        <span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span> Cerrado
                    </span>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-center gap-3 text-[#a07050] text-sm">
                <i class="fas fa-map-pin text-[#F47E24]/60"></i>
                <span>Ubicados en el Puente — te esperamos</span>
            </div>

        </div>
    </section>

    <!-- ====================================================== -->
    <!-- CTA -->
    <!-- ====================================================== -->
    <section class="bg-[#2c1408] py-16 px-4 md:px-10">
        <div class="max-w-3xl mx-auto text-center">
            <div
                class="bg-gradient-to-br from-[#3d1e0b] to-[#1e0f07] border border-[#F47E24]/25 rounded-3xl p-12 shadow-2xl relative overflow-hidden">
                <div
                    class="absolute top-0 left-1/2 -translate-x-1/2 w-64 h-32 bg-[#F47E24]/10 rounded-full blur-[60px] pointer-events-none">
                </div>
                <i class="fas fa-coffee text-[#F47E24]/35 text-5xl mb-6 block relative z-10"></i>
                <h2 class="text-3xl md:text-4xl font-bold text-[#f5ece4] mb-4 relative z-10">¿Listo para visitarnos?
                </h2>
                <p class="text-[#c9a882] mb-8 text-lg relative z-10">Pasa por el puente y vive la experiencia. Una
                    taza, una sonrisa, un momento.</p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center relative z-10">
                    <a href="./menu.php"
                        class="inline-flex items-center justify-center gap-2 bg-[#F47E24] hover:bg-[#e06b15] text-white font-bold py-3 px-8 rounded-full transition-all duration-200 hover:shadow-lg">
                        <i class="fas fa-book-open text-sm"></i> Ver el Menú
                    </a>
                    <a href="./contacto.html"
                        class="inline-flex items-center justify-center gap-2 border border-[#F47E24]/30 hover:border-[#F47E24]/70 text-[#f5ece4] font-bold py-3 px-8 rounded-full transition-all duration-200 hover:text-[#F47E24]">
                        <i class="fas fa-envelope text-sm"></i> Contáctanos
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================== -->
    <!-- FOOTER -->
    <!-- ====================================================== -->
    <footer class="w-full bg-gray-200">
        <!-- Auth CTA Bar -->
        <div class="bg-gradient-to-r from-[#3d1e0b] to-[#55301c] py-5 px-4">
            <div class="mx-auto max-w-7xl flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-center sm:text-left">
                    <p class="text-white font-semibold text-sm">¿Ya eres parte de nuestra familia?</p>
                    <p class="text-[#c9a882] text-xs mt-0.5">Inicia sesión o crea tu cuenta para disfrutar beneficios
                        exclusivos</p>
                </div>
                <div class="flex items-center gap-3 flex-shrink-0">
                    <a href="./login.html"
                        class="py-2 px-5 border border-[#F47E24]/60 hover:border-[#F47E24] text-[#F47E24] rounded-full text-xs font-semibold transition-all duration-300 hover:bg-[#F47E24]/10">
                        <i class="fas fa-sign-in-alt mr-1"></i> Iniciar Sesión
                    </a>
                    <a href="./registro.html"
                        class="py-2 px-5 bg-[#F47E24] hover:bg-[#e06b15] text-white rounded-full text-xs font-semibold transition-all duration-300 shadow-md">
                        <i class="fas fa-user-plus mr-1"></i> Registrarse
                    </a>
                </div>
            </div>
        </div>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <!--Grid-->
            <div
                class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-8 py-10 max-sm:max-w-sm max-sm:mx-auto gap-y-8">
                <div class="col-span-full mb-10 lg:col-span-2 lg:mb-0">
                    <a>
                        <img src="./images/logo-original.svg" class="h-20 w-20">
                    </a>
                    <p class="py-8 text-sm text-gray-500 lg:max-w-xs text-center lg:text-left">Comprometidos con
                        el bienestar y la conformidad de nuestros clientes</p>
                    <a href="./contacto.html"
                        class="py-2.5 px-5 h-9 block w-fit bg-[#F47E24] rounded-full shadow-sm text-xs text-white mx-auto transition-all duration-500 hover:bg-[#e06b15] lg:mx-0">
                        Contacto
                    </a>
                </div>
                <!--End Col-->
                <div class="lg:mx-auto text-left">
                    <h4 class="text-lg text-gray-900 font-medium mb-7">Indice</h4>
                    <ul class="text-sm transition-all duration-500">
                        <li class="mb-6"><a href="./index.html" class="text-gray-600 hover:text-gray-900">Inicio</a>
                        </li>
                        <li class="mb-6"><a href="./sobre-nosotros.php" class="text-gray-600 hover:text-gray-900">Sobre
                                nosotros</a></li>
                        <li class="mb-6"><a href="./menu.php" class="text-gray-600 hover:text-gray-900">Productos</a>
                        </li>
                        <li class="mb-6"><a href="./contacto.html"
                                class="text-gray-600 hover:text-gray-900">Contacto</a>
                        </li>
                        <li><a href="./nuestra-gente.html" class="text-gray-600 hover:text-gray-900">Nuestra Gente</a>
                        </li>
                    </ul>
                </div>
                <!--End Col-->
                <div class="lg:mx-auto text-left">
                    <h4 class="text-lg text-gray-900 font-medium mb-7">Productos</h4>
                    <ul class="text-sm transition-all duration-500">
                        <li class="mb-6"><a href="./menu.php#salados"
                                class="text-gray-600 hover:text-gray-900">Picaderas</a>
                        </li>
                        <li class="mb-6"><a href="./menu.php#sandwiches"
                                class="text-gray-600 hover:text-gray-900">Sandwiches y
                                tostadas</a></li>
                        <li class="mb-6"><a href="./menu.php#bebidas"
                                class="text-gray-600 hover:text-gray-900">Bebidas</a>
                        </li>
                        <li><a href="./menu.php" class="text-gray-600 hover:text-gray-900">Postres</a></li>
                    </ul>
                </div>
                <!--End Col-->
            </div>
            <!--End Grid-->
            <div class="py-7 border-t border-gray-200">
                <div class="flex items-center justify-center flex-col lg:justify-between lg:flex-row">
                    <span class="text-sm text-gray-500">©<a href="">Del Puente Cafe y Snack</a> 2026,
                        All rights reserved.</span>
                    <div class="flex mt-4 space-x-4 sm:justify-center lg:mt-0">
                        <a href="https://www.instagram.com/delpuentecafe/"
                            class="w-9 h-9 rounded-full bg-gray-700 flex justify-center items-center hover:bg-[#F47E24] transition-colors duration-300">
                            <svg class="w-[1.25rem] h-[1.125rem] text-white" viewBox="0 0 15 15" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M4.70975 7.93663C4.70975 6.65824 5.76102 5.62163 7.0582 5.62163C8.35537 5.62163 9.40721 6.65824 9.40721 7.93663C9.40721 9.21502 8.35537 10.2516 7.0582 10.2516C5.76102 10.2516 4.70975 9.21502 4.70975 7.93663ZM3.43991 7.93663C3.43991 9.90608 5.05982 11.5025 7.0582 11.5025C9.05658 11.5025 10.6765 9.90608 10.6765 7.93663C10.6765 5.96719 9.05658 4.37074 7.0582 4.37074C5.05982 4.37074 3.43991 5.96719 3.43991 7.93663ZM9.97414 4.22935C9.97408 4.39417 10.0236 4.55531 10.1165 4.69239C10.2093 4.82946 10.3413 4.93633 10.4958 4.99946C10.6503 5.06259 10.8203 5.07916 10.9844 5.04707C11.1484 5.01498 11.2991 4.93568 11.4174 4.81918C11.5357 4.70268 11.6163 4.55423 11.649 4.39259C11.6817 4.23095 11.665 4.06339 11.6011 3.91109C11.5371 3.7588 11.4288 3.6286 11.2898 3.53698C11.1508 3.44536 10.9873 3.39642 10.8201 3.39635H10.8197C10.5955 3.39646 10.3806 3.48424 10.222 3.64043C10.0635 3.79661 9.97434 4.00843 9.97414 4.22935ZM4.21142 13.5892C3.52442 13.5584 3.15101 13.4456 2.90286 13.3504C2.57387 13.2241 2.33914 13.0738 2.09235 12.8309C1.84555 12.588 1.69278 12.3569 1.56527 12.0327C1.46854 11.7882 1.3541 11.4201 1.32287 10.7431C1.28871 10.0111 1.28189 9.79119 1.28189 7.93669C1.28189 6.08219 1.28927 5.86291 1.32287 5.1303C1.35416 4.45324 1.46944 4.08585 1.56527 3.84069C1.69335 3.51647 1.84589 3.28513 2.09235 3.04191C2.3388 2.79869 2.57331 2.64813 2.90286 2.52247C3.1509 2.42713 3.52442 2.31435 4.21142 2.28358C4.95417 2.24991 5.17729 2.24319 7.0582 2.24319C8.9391 2.24319 9.16244 2.25047 9.90582 2.28358C10.5928 2.31441 10.9656 2.42802 11.2144 2.52247C11.5434 2.64813 11.7781 2.79902 12.0249 3.04191C12.2717 3.2848 12.4239 3.51647 12.552 3.84069C12.6487 4.08513 12.7631 4.45324 12.7944 5.1303C12.8285 5.86291 12.8354 6.08219 12.8354 7.93669C12.8354 9.79119 12.8285 10.0105 12.7944 10.7431C12.7631 11.4201 12.6481 11.7881 12.552 12.0327C12.4239 12.3569 12.2714 12.5882 12.0249 12.8309C11.7784 13.0736 11.5434 13.2241 11.2144 13.3504C10.9663 13.4457 10.5928 13.5585 9.90582 13.5892C9.16306 13.6229 8.93994 13.6296 7.0582 13.6296C5.17645 13.6296 4.95395 13.6229 4.21142 13.5892ZM4.15307 1.03424C3.40294 1.06791 2.89035 1.18513 2.4427 1.3568C1.9791 1.53408 1.58663 1.77191 1.19446 2.1578C0.802277 2.54369 0.56157 2.93108 0.381687 3.38797C0.207498 3.82941 0.0885535 4.3343 0.0543922 5.07358C0.0196672 5.81402 0.0117188 6.05074 0.0117188 7.93663C0.0117188 9.82252 0.0196672 10.0592 0.0543922 10.7997C0.0885535 11.539 0.207498 12.0439 0.381687 12.4853C0.56157 12.9419 0.802334 13.3297 1.19446 13.7155C1.58658 14.1012 1.9791 14.3387 2.4427 14.5165C2.89119 14.6881 3.40294 14.8054 4.15307 14.839C4.90479 14.8727 5.1446 14.8811 7.0582 14.8811C8.9718 14.8811 9.212 14.8732 9.96332 14.839C10.7135 14.8054 11.2258 14.6881 11.6737 14.5165C12.137 14.3387 12.5298 14.1014 12.9219 13.7155C13.3141 13.3296 13.5543 12.9419 13.7347 12.4853C13.9089 12.0439 14.0284 11.539 14.062 10.7997C14.0962 10.0587 14.1041 9.82252 14.1041 7.93663C14.1041 6.05074 14.0962 5.81402 14.062 5.07358C14.0278 4.33424 13.9089 3.82913 13.7347 3.38797C13.5543 2.93135 13.3135 2.5443 12.9219 2.1578C12.5304 1.7713 12.137 1.53408 11.6743 1.3568C11.2258 1.18513 10.7135 1.06735 9.96388 1.03424C9.21256 1.00058 8.97236 0.992188 7.05876 0.992188C5.14516 0.992188 4.90479 1.00002 4.15307 1.03424Z"
                                    fill="currentColor" />
                            </svg>
                        </a>
                        <a href="https://wa.me/18095728853" target="_blank"
                            class="w-9 h-9 rounded-full bg-gray-700 flex justify-center items-center hover:bg-[#25D366] transition-colors duration-300">
                            <i class="fab fa-whatsapp text-white text-base"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="./js/session-nav.js"></script>
    <?php if ($es_admin): ?>
    <script>
        const editBtn  = document.getElementById('editBtn');
        const editModal = document.getElementById('editModal');
        const cancelBtn  = document.getElementById('cancelBtn');
        const cancelBtn2 = document.getElementById('cancelBtn2');
        const editForm   = document.getElementById('editForm');
        const editMsg    = document.getElementById('edit-msg');

        editBtn.addEventListener('click', () => {
            editModal.classList.remove('hidden');
            editModal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        });
        function cerrarEdit() {
            editModal.classList.add('hidden');
            editModal.classList.remove('flex');
            document.body.style.overflow = '';
        }
        cancelBtn.addEventListener('click', cerrarEdit);
        if (cancelBtn2) cancelBtn2.addEventListener('click', cerrarEdit);

        editForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const btn = editForm.querySelector('button[type="submit"]');
            btn.disabled = true; btn.textContent = 'Guardando...';
            const fd = new FormData(editForm);
            fetch('./backend/admin/guardar_contenido.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    btn.disabled = false; btn.innerHTML = '<i class="fas fa-save mr-2"></i>Guardar cambios';
                    if (d.ok) {
                        editMsg.className = 'mb-4 p-3 rounded-xl text-sm font-semibold bg-green-100 text-green-700';
                        editMsg.textContent = '\u2705 ' + d.mensaje;
                        editMsg.classList.remove('hidden');
                        setTimeout(() => { cerrarEdit(); location.reload(); }, 1200);
                    } else {
                        editMsg.className = 'mb-4 p-3 rounded-xl text-sm font-semibold bg-red-100 text-red-700';
                        editMsg.textContent = '\u274c ' + (d.error || 'Error al guardar.');
                        editMsg.classList.remove('hidden');
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    editMsg.className = 'mb-4 p-3 rounded-xl text-sm font-semibold bg-red-100 text-red-700';
                    editMsg.textContent = '\u274c Error de conexión.';
                    editMsg.classList.remove('hidden');
                });
        });
    </script>
    <?php endif; ?>
</body>

</html>