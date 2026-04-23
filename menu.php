<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once './backend/config/conexion.php';

// ── Obtener todos los productos activos agrupados por categoria ───
// Usamos try/catch: si las columnas nuevas aun no existen (falta migracion),
// caemos en una query simple sin ellas.
try {
    $stmt = $pdo->query("
        SELECT id_producto, nombre_producto,
               COALESCE(descripcion,'') AS descripcion,
               precio_producto, categoria_producto, imagen,
               COALESCE(tipo_stock,'stock') AS tipo_stock,
               COALESCE(stock, 0) AS stock
        FROM productos
        WHERE activo = 1
        ORDER BY FIELD(categoria_producto,'salados','sandwiches','bebidas','postres','otros'), nombre_producto
    ");
    $todos_prods = $stmt->fetchAll();
} catch (PDOException $e) {
    // Columnas nuevas no existen aun => query base sin ellas
    $stmt = $pdo->query("
        SELECT id_producto, nombre_producto,
               '' AS descripcion,
               precio_producto, categoria_producto,
               COALESCE(imagen, NULL) AS imagen,
               'stock' AS tipo_stock,
               999 AS stock
        FROM productos
        WHERE activo = 1
        ORDER BY nombre_producto
    ");
    $todos_prods = $stmt->fetchAll();
}


// Agrupar por categoria
$por_categoria = [];
foreach ($todos_prods as $p) {
    $cat = strtolower(trim($p['categoria_producto'] ?? 'otros'));
    $por_categoria[$cat][] = $p;
}

// Config de cada seccion
$cat_config = [
    'salados'    => ['titulo'=>'Productos Salados y Picaderas', 'sub'=>'Variedad y sabor',     'icono'=>'fa-drumstick-bite'],
    'sandwiches' => ['titulo'=>'Sandwiches y Tostadas',         'sub'=>'Clasico y sabroso',    'icono'=>'fa-bread-slice'],
    'bebidas'    => ['titulo'=>'Bebidas',                        'sub'=>'Frescura para todos',  'icono'=>'fa-glass-cheers'],
    'postres'    => ['titulo'=>'Postres y Dulces',               'sub'=>'El toque dulce',       'icono'=>'fa-ice-cream'],
    'otros'      => ['titulo'=>'Otros productos',                'sub'=>'Mas opciones',         'icono'=>'fa-utensils'],
];

$logueado    = isset($_SESSION['id_usuario']);
$nombre_user = $_SESSION['nombre'] ?? '';
$rol_user    = $_SESSION['rol']    ?? 'usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu — Del Puente Cafe &amp; Snack</title>
    <meta name="description" content="Menu completo de Cafeteria del Puente. Empanadas, sandwiches, bebidas y mas.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .glass-hover{transition:background .3s ease,border-color .3s ease,box-shadow .3s ease;}
        .glass-hover:hover{background:rgba(255,255,255,.22)!important;border-color:rgba(255,255,255,.80)!important;box-shadow:inset 0 0 0 1px rgba(255,255,255,.25),0 4px 20px rgba(255,255,255,.10);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);color:#ffffff!important;}
        .auth-divider{border-left:1px solid rgba(255,255,255,.20);}

        /* ── Stock badges ───────────────────────────────── */
        .stock-agotado-overlay{position:absolute;inset:0;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;z-index:10;}
        .badge-agotado-card{background:#ef4444;color:#fff;font-size:.7rem;font-weight:700;padding:6px 16px;border-radius:999px;letter-spacing:.06em;text-transform:uppercase;}
        article.agotado .btn-anadir{opacity:.4;pointer-events:none;cursor:not-allowed;}
    </style>
</head>
<body class="bg-[#2c1608] min-h-screen flex flex-col">

    <!-- NAVBAR -->
    <header class="w-full top-0 z-50 lg:px-16 px-4 bg-[#55301c] flex flex-wrap items-center py-2 shadow-lg">
        <div class="flex-1 flex justify-between items-center">
            <a href="./index.html" class="text-3xl text-white font-semibold">Menu</a>
        </div>
        <label for="menu-toggle" class="pointer-cursor md:hidden block">
            <svg class="fill-current text-white" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20">
                <title>Menu Toggle</title><path d="M0 3h20v2H0V3zm0 6h20v2H0V9zm0 6h20v2H0v-2z"></path>
            </svg>
        </label>
        <input class="hidden" type="checkbox" id="menu-toggle">
        <div class="hidden md:flex md:items-center md:w-auto w-full">
            <nav>
                <ul class="md:flex items-center justify-between text-base text-white pt-4 md:pt-0">
                    <li><a class="md:p-4 py-3 px-0 block hover:text-[#F47E24] transition-colors" href="./index.html">Inicio</a></li>
                    <li><a class="md:p-4 py-3 px-0 block hover:text-[#F47E24] transition-colors" href="./menu.php">Menu</a></li>
                    <li><a class="md:p-4 py-3 px-0 block hover:text-[#F47E24] transition-colors" href="./sobre-nosotros.php">Sobre Nosotros</a></li>
                    <li><a class="md:p-4 py-3 px-0 block hover:text-[#F47E24] transition-colors" href="./nuestra-gente.html">Nuestra Gente</a></li>
                    <li><a class="md:p-4 py-3 px-0 block hover:text-[#F47E24] transition-colors" href="./contacto.html">Contacto</a></li>
                </ul>
            </nav>
            <div class="flex items-center gap-3 ml-10 pl-8 auth-divider auth-btns">
                <?php if ($logueado): ?>
                <?php
                    $panel_url  = ($rol_user === 'admin') ? './panel-admin.php' : './panel-usuario.php';
                    $panel_ico  = ($rol_user === 'admin') ? 'fa-shield-alt' : 'fa-user';
                    $panel_txt  = ($rol_user === 'admin') ? 'Panel Admin' : htmlspecialchars($nombre_user);
                ?>
                <a href="<?= $panel_url ?>" class="py-2 px-5 bg-[#F47E24] hover:bg-[#e06b15] text-white rounded-full text-sm font-semibold transition-all duration-300 shadow-lg shadow-[#F47E24]/30 flex items-center gap-1.5">
                    <i class="fas <?= $panel_ico ?> text-xs"></i> <?= $panel_txt ?>
                </a>
                <?php if ($rol_user === 'admin'): ?>
                <a href="./panel-usuario.php" class="py-2 px-4 border border-white/30 text-white/80 rounded-full text-sm font-medium hover:text-white hover:border-white/60 transition-all duration-300">
                    <i class="fas fa-user text-xs mr-1"></i> Mi Panel
                </a>
                <?php endif; ?>
                <a href="./backend/autenticacion/cerrar_sesion.php" class="py-2 px-5 border border-white/50 text-white rounded-full text-sm font-medium transition-all duration-300 glass-hover">
                    <i class="fas fa-sign-out-alt mr-1 text-xs"></i> Salir
                </a>
                <?php else: ?>
                <a href="./login.html" class="py-2 px-5 border border-white/50 text-white rounded-full text-sm font-medium transition-all duration-300 glass-hover">
                    <i class="fas fa-sign-in-alt mr-1 text-xs"></i> Iniciar Sesion
                </a>
                <a href="./registro.html" class="py-2 px-5 bg-[#F47E24] hover:bg-[#e06b15] text-white rounded-full text-sm font-semibold transition-all duration-300 shadow-lg shadow-[#F47E24]/30">
                    <i class="fas fa-user-plus mr-1 text-xs"></i> Registrarse
                </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Sticky Category Nav -->
    <nav class="sticky top-0 z-40 bg-[#3a1d0e]/95 backdrop-blur-sm border-b border-[#F47E24]/20 shadow-lg">
        <div class="max-w-7xl mx-auto px-6 lg:px-12">
            <ul class="flex items-center gap-1 overflow-x-auto py-3 scrollbar-none">
                <?php foreach(array_keys($por_categoria) as $i=>$cat):
                    $cfg = $cat_config[$cat] ?? ['icono'=>'fa-utensils'];
                    $isFirst = ($i === 0);
                ?>
                <li>
                    <a href="#<?= $cat ?>" class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold whitespace-nowrap transition-all duration-200 <?= $isFirst?"text-[#F47E24] bg-[#F47E24]/15 border border-[#F47E24]/30 hover:bg-[#F47E24]/25":"text-[#c9a882] hover:text-[#F47E24] hover:bg-[#F47E24]/10" ?>">
                        <i class="fas <?= $cfg['icono'] ?> text-xs"></i> <?= ucfirst($cat) ?>
                    </a>
                </li>
                <?php endforeach; ?>
                <li class="ml-auto">
                    <a href="./contacto.html" class="flex items-center gap-2 px-4 py-2 rounded-full text-xs font-medium text-[#c9a882] hover:text-white border border-white/10 hover:border-white/30 transition-all duration-200 whitespace-nowrap">
                        <i class="fas fa-phone-alt text-xs"></i> Contacto
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <main class="px-6 lg:px-12 pb-20 flex-1">

    <?php if (empty($por_categoria)): ?>
        <div class="flex flex-col items-center justify-center min-h-[60vh] text-center px-4">
            <div class="w-20 h-20 rounded-full bg-[#F47E24]/10 border border-[#F47E24]/20 flex items-center justify-center mb-6">
                <i class="fas fa-coffee text-[#F47E24] text-3xl opacity-60"></i>
            </div>
            <h2 class="text-white text-2xl font-bold mb-3">Menu en preparacion</h2>
            <p class="text-[#c9a882] text-sm max-w-xs leading-relaxed">El administrador esta cargando los productos. Vuelve pronto.</p>
            <a href="./index.html" class="mt-8 inline-flex items-center gap-2 bg-[#F47E24] hover:bg-[#e06b15] text-white text-sm font-bold py-2.5 px-6 rounded-full transition-all">
                <i class="fas fa-home text-xs"></i> Volver al inicio
            </a>
        </div>
    <?php endif; ?>

    <?php foreach ($por_categoria as $cat => $productos):
        $cfg = $cat_config[$cat] ?? ['titulo'=>ucfirst($cat),'sub'=>'','icono'=>'fa-utensils'];
    ?>
        <section class="pt-14 pb-10" id="<?= $cat ?>" data-section="<?= $cat ?>">

            <div class="text-center mb-10">
                <p class="text-[#F47E24] font-semibold uppercase tracking-widest text-xs mb-1"><?= htmlspecialchars($cfg['sub']) ?></p>
                <h2 class="text-3xl lg:text-4xl font-bold text-white"><?= htmlspecialchars($cfg['titulo']) ?></h2>
                <div class="w-16 h-1 bg-[#F47E24] mx-auto mt-3 rounded-full"></div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">

            <?php foreach ($productos as $prod):
                $esIlim    = ($prod['tipo_stock'] === 'ilimitado');
                $stock     = (int)$prod['stock'];
                $agotado   = !$esIlim && $stock <= 0;
                $stockBajo = !$esIlim && !$agotado && $stock < 6;

                // Variantes del campo descripcion (separadas por ·)
                $variantes = [];
                if (!empty($prod['descripcion'])) {
                    $variantes = array_filter(array_map('trim', explode('·', $prod['descripcion'])));
                }
            ?>
                <article class="flex flex-col rounded-xl shadow-xl bg-[#4a2010] group overflow-hidden relative<?= $agotado?' agotado':'' ?>"
                         data-id="<?= $prod['id_producto'] ?>"
                         data-name="<?= htmlspecialchars($prod['nombre_producto'], ENT_QUOTES) ?>"
                         data-price="<?= $prod['precio_producto'] ?>"
                         data-desc="<?= htmlspecialchars($prod['descripcion'] ?? '', ENT_QUOTES) ?>"
                         data-tipo="<?= $prod['tipo_stock'] ?>"
                         data-stock="<?= $esIlim ? 999 : $stock ?>">

                    <!-- Indicador de stock -->
                    <div class="absolute top-3 right-3 w-4 h-4 rounded-full border-2 border-white <?= $agotado ? 'bg-red-500' : 'bg-green-500' ?> z-10"></div>

                    <?php if ($agotado): ?>
                    <div class="stock-agotado-overlay">
                        <span class="badge-agotado-card"><i class="fas fa-times mr-1"></i>Agotado</span>
                    </div>
                    <?php endif; ?>

                    <!-- Imagen -->
                    <?php if (!empty($prod['imagen'])): ?>
                    <img class="w-full h-40 object-cover object-center" src="<?= htmlspecialchars($prod['imagen']) ?>"
                         alt="<?= htmlspecialchars($prod['nombre_producto']) ?>"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <div class="hidden w-full h-40 bg-[#3d1e0b] items-center justify-center">
                        <i class="fas fa-<?= $cfg['icono'] ?> text-[#F47E24] text-3xl opacity-30"></i>
                    </div>
                    <?php else: ?>
                    <div class="w-full h-40 bg-[#3d1e0b] flex items-center justify-center">
                        <i class="fas <?= $cfg['icono'] ?> text-[#F47E24] text-3xl opacity-30"></i>
                    </div>
                    <?php endif; ?>

                    <div class="flex flex-col flex-1">
                        <!-- Rating row (decorativo) -->
                        <div class="flex items-center justify-between my-3">
                            <div class="relative w-5/12 h-8 flex items-center justify-end border-l-4 border-[#F47E24] rounded-tr-full rounded-br-full bg-[#6b3020]">
                                <div class="absolute right-1 z-30 w-6 h-6 rounded-full border-2 border-amber-900 bg-stone-800 flex items-center justify-center"><i class="fas fa-user text-amber-900 text-xs"></i></div>
                                <div class="absolute right-4 z-20 w-6 h-6 rounded-full border-2 border-amber-900 bg-stone-800 flex items-center justify-center"><i class="fas fa-user text-amber-900 text-xs"></i></div>
                                <div class="absolute right-7 z-10 w-6 h-6 rounded-full border-2 border-amber-900 bg-stone-800 flex items-center justify-center"><i class="fas fa-user text-amber-900 text-xs"></i></div>
                            </div>
                            <!-- Calificaciones -->
                            <div class="rating-block relative flex flex-col items-end pr-2 cursor-pointer select-none" data-product-id="<?= $prod['id_producto'] ?>">
                                <div class="flex gap-1 mb-0.5 stars">
                                    <i class="far fa-star text-gray-500 text-xs star" data-rating="1"></i>
                                    <i class="far fa-star text-gray-500 text-xs star" data-rating="2"></i>
                                    <i class="far fa-star text-gray-500 text-xs star" data-rating="3"></i>
                                    <i class="far fa-star text-gray-500 text-xs star" data-rating="4"></i>
                                    <i class="far fa-star text-gray-500 text-xs star" data-rating="5"></i>
                                </div>
                                <span class="text-gray-500 font-medium text-xs tracking-wider mt-0.5 rating-text">-.-</span>
                                <span class="rating-tooltip absolute right-0 bottom-full mb-1 bg-stone-900 text-gray-400 text-xs px-2 py-1 rounded shadow-lg opacity-0 transition-opacity duration-150 whitespace-nowrap pointer-events-none z-50">Haz clic para calificar</span>
                            </div>
                        </div>

                        <!-- Nombre -->
                        <h3 class="px-3 text-lg font-semibold text-white group-hover:text-[#F47E24] transition-colors duration-200 leading-tight">
                            <?= htmlspecialchars($prod['nombre_producto']) ?>
                        </h3>

                        <!-- Variantes/descripcion -->
                        <?php if (!empty($variantes)): ?>
                        <p class="px-3 text-xs text-gray-400 mt-1 mb-4 leading-snug">
                            <?= implode(' &nbsp;·&nbsp; ', array_map('htmlspecialchars', $variantes)) ?>
                        </p>
                        <?php else: ?>
                        <p class="px-3 text-xs text-gray-500 mt-1 mb-4 italic leading-snug">Del Puente Cafe</p>
                        <?php endif; ?>

                        <!-- Precio y boton -->
                        <div class="mt-auto px-3 pb-5 flex items-center justify-between">
                            <span class="text-yellow-400 font-bold text-sm">RD$<?= number_format((float)$prod['precio_producto'],0) ?></span>
                            <?php if (!$agotado): ?>
                            <button class="btn-anadir text-[#F47E24] text-xs font-bold py-1 px-3 border border-[#F47E24] rounded-full uppercase hover:bg-[#F47E24] hover:text-white transition-colors duration-200"
                                    data-action="anadir">
                                <i class="fas fa-shopping-cart mr-1 text-xs"></i> Anadir
                            </button>
                            <?php else: ?>
                            <span class="text-gray-600 text-xs font-semibold px-3 py-1 border border-gray-700 rounded-full">Agotado</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <!-- SECCIÓN POSTRES MANUAL -->
    <section class="pt-14 pb-10" id="postres-manual" data-section="postres">
        <div class="text-center mb-10">
            <p class="text-[#F47E24] font-semibold uppercase tracking-widest text-xs mb-1">El toque dulce</p>
            <h2 class="text-3xl lg:text-4xl font-bold text-white">Postres y Dulces</h2>
            <div class="w-16 h-1 bg-[#F47E24] mx-auto mt-3 rounded-full"></div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">

            <!-- HELADO -->
            <article class="flex flex-col rounded-xl shadow-xl bg-[#4a2010] group overflow-hidden relative"
                     data-id="postre-1"
                     data-name="Helado"
                     data-price="150"
                     data-desc="Cremoso y refrescante"
                     data-tipo="stock"
                     data-stock="999">

                <!-- Indicador de stock -->
                <div class="absolute top-3 right-3 w-4 h-4 rounded-full border-2 border-white bg-green-500 z-10"></div>

                <!-- Imagen placeholder -->
                <div class="w-full h-40 bg-[#3d1e0b] flex items-center justify-center">
                    <i class="fas fa-ice-cream text-[#F47E24] text-3xl opacity-30"></i>
                </div>

                <div class="flex flex-col flex-1">
                    <!-- Rating row -->
                    <div class="flex items-center justify-between my-3">
                        <div class="relative w-5/12 h-8 flex items-center justify-end border-l-4 border-[#F47E24] rounded-tr-full rounded-br-full bg-[#6b3020]">
                            <div class="absolute right-1 z-30 w-6 h-6 rounded-full border-2 border-amber-900 bg-stone-800 flex items-center justify-center"><i class="fas fa-user text-amber-900 text-xs"></i></div>
                            <div class="absolute right-4 z-20 w-6 h-6 rounded-full border-2 border-amber-900 bg-stone-800 flex items-center justify-center"><i class="fas fa-user text-amber-900 text-xs"></i></div>
                            <div class="absolute right-7 z-10 w-6 h-6 rounded-full border-2 border-amber-900 bg-stone-800 flex items-center justify-center"><i class="fas fa-user text-amber-900 text-xs"></i></div>
                        </div>
                        <!-- Calificaciones -->
                        <div class="rating-block relative flex flex-col items-end pr-2 cursor-pointer select-none" data-product-id="postre-1">
                            <div class="flex gap-1 mb-0.5 stars">
                                <i class="far fa-star text-gray-500 text-xs star" data-rating="1"></i>
                                <i class="far fa-star text-gray-500 text-xs star" data-rating="2"></i>
                                <i class="far fa-star text-gray-500 text-xs star" data-rating="3"></i>
                                <i class="far fa-star text-gray-500 text-xs star" data-rating="4"></i>
                                <i class="far fa-star text-gray-500 text-xs star" data-rating="5"></i>
                            </div>
                            <span class="text-gray-500 font-medium text-xs tracking-wider mt-0.5 rating-text">-.-</span>
                            <span class="rating-tooltip absolute right-0 bottom-full mb-1 bg-stone-900 text-gray-400 text-xs px-2 py-1 rounded shadow-lg opacity-0 transition-opacity duration-150 whitespace-nowrap pointer-events-none z-50">Haz clic para calificar</span>
                        </div>
                    </div>

                    <!-- Nombre -->
                    <h3 class="px-3 text-lg font-semibold text-white group-hover:text-[#F47E24] transition-colors duration-200 leading-tight">Helado</h3>

                    <!-- Descripción -->
                    <p class="px-3 text-xs text-gray-500 mt-1 mb-4 italic leading-snug">Cremoso y refrescante</p>

                    <!-- Precio y botón -->
                    <div class="mt-auto px-3 pb-5 flex items-center justify-between">
                        <span class="text-yellow-400 font-bold text-sm">RD$150</span>
                        <button class="btn-anadir text-[#F47E24] text-xs font-bold py-1 px-3 border border-[#F47E24] rounded-full uppercase hover:bg-[#F47E24] hover:text-white transition-colors duration-200"
                                data-action="anadir">
                            <i class="fas fa-shopping-cart mr-1 text-xs"></i> Anadir
                        </button>
                    </div>
                </div>
            </article>

            <!-- BROWNIE -->
            <article class="flex flex-col rounded-xl shadow-xl bg-[#4a2010] group overflow-hidden relative"
                     data-id="postre-2"
                     data-name="Brownie"
                     data-price="180"
                     data-desc="Chocolate intenso"
                     data-tipo="stock"
                     data-stock="999">

                <!-- Indicador de stock -->
                <div class="absolute top-3 right-3 w-4 h-4 rounded-full border-2 border-white bg-green-500 z-10"></div>

                <!-- Imagen placeholder -->
                <div class="w-full h-40 bg-[#3d1e0b] flex items-center justify-center">
                    <i class="fas fa-ice-cream text-[#F47E24] text-3xl opacity-30"></i>
                </div>

                <div class="flex flex-col flex-1">
                    <!-- Rating row -->
                    <div class="flex items-center justify-between my-3">
                        <div class="relative w-5/12 h-8 flex items-center justify-end border-l-4 border-[#F47E24] rounded-tr-full rounded-br-full bg-[#6b3020]">
                            <div class="absolute right-1 z-30 w-6 h-6 rounded-full border-2 border-amber-900 bg-stone-800 flex items-center justify-center"><i class="fas fa-user text-amber-900 text-xs"></i></div>
                            <div class="absolute right-4 z-20 w-6 h-6 rounded-full border-2 border-amber-900 bg-stone-800 flex items-center justify-center"><i class="fas fa-user text-amber-900 text-xs"></i></div>
                            <div class="absolute right-7 z-10 w-6 h-6 rounded-full border-2 border-amber-900 bg-stone-800 flex items-center justify-center"><i class="fas fa-user text-amber-900 text-xs"></i></div>
                        </div>
                        <!-- Calificaciones -->
                        <div class="rating-block relative flex flex-col items-end pr-2 cursor-pointer select-none" data-product-id="postre-2">
                            <div class="flex gap-1 mb-0.5 stars">
                                <i class="far fa-star text-gray-500 text-xs star" data-rating="1"></i>
                                <i class="far fa-star text-gray-500 text-xs star" data-rating="2"></i>
                                <i class="far fa-star text-gray-500 text-xs star" data-rating="3"></i>
                                <i class="far fa-star text-gray-500 text-xs star" data-rating="4"></i>
                                <i class="far fa-star text-gray-500 text-xs star" data-rating="5"></i>
                            </div>
                            <span class="text-gray-500 font-medium text-xs tracking-wider mt-0.5 rating-text">-.-</span>
                            <span class="rating-tooltip absolute right-0 bottom-full mb-1 bg-stone-900 text-gray-400 text-xs px-2 py-1 rounded shadow-lg opacity-0 transition-opacity duration-150 whitespace-nowrap pointer-events-none z-50">Haz clic para calificar</span>
                        </div>
                    </div>

                    <!-- Nombre -->
                    <h3 class="px-3 text-lg font-semibold text-white group-hover:text-[#F47E24] transition-colors duration-200 leading-tight">Brownie</h3>

                    <!-- Descripción -->
                    <p class="px-3 text-xs text-gray-500 mt-1 mb-4 italic leading-snug">Chocolate intenso y textura</p>

                    <!-- Precio y botón -->
                    <div class="mt-auto px-3 pb-5 flex items-center justify-between">
                        <span class="text-yellow-400 font-bold text-sm">RD$180</span>
                        <button class="btn-anadir text-[#F47E24] text-xs font-bold py-1 px-3 border border-[#F47E24] rounded-full uppercase hover:bg-[#F47E24] hover:text-white transition-colors duration-200"
                                data-action="anadir">
                            <i class="fas fa-shopping-cart mr-1 text-xs"></i> Anadir
                        </button>
                    </div>
                </div>
            </article>

            <!-- CHOCOBROWNE -->
            <article class="flex flex-col rounded-xl shadow-xl bg-[#4a2010] group overflow-hidden relative"
                     data-id="postre-3"
                     data-name="Chocobrowne"
                     data-price="200"
                     data-desc="Chocolate y brownie"
                     data-tipo="stock"
                     data-stock="999">

                <!-- Indicador de stock -->
                <div class="absolute top-3 right-3 w-4 h-4 rounded-full border-2 border-white bg-green-500 z-10"></div>

                <!-- Imagen placeholder -->
                <div class="w-full h-40 bg-[#3d1e0b] flex items-center justify-center">
                    <i class="fas fa-ice-cream text-[#F47E24] text-3xl opacity-30"></i>
                </div>

                <div class="flex flex-col flex-1">
                    <!-- Rating row -->
                    <div class="flex items-center justify-between my-3">
                        <div class="relative w-5/12 h-8 flex items-center justify-end border-l-4 border-[#F47E24] rounded-tr-full rounded-br-full bg-[#6b3020]">
                            <div class="absolute right-1 z-30 w-6 h-6 rounded-full border-2 border-amber-900 bg-stone-800 flex items-center justify-center"><i class="fas fa-user text-amber-900 text-xs"></i></div>
                            <div class="absolute right-4 z-20 w-6 h-6 rounded-full border-2 border-amber-900 bg-stone-800 flex items-center justify-center"><i class="fas fa-user text-amber-900 text-xs"></i></div>
                            <div class="absolute right-7 z-10 w-6 h-6 rounded-full border-2 border-amber-900 bg-stone-800 flex items-center justify-center"><i class="fas fa-user text-amber-900 text-xs"></i></div>
                        </div>
                        <!-- Calificaciones -->
                        <div class="rating-block relative flex flex-col items-end pr-2 cursor-pointer select-none" data-product-id="postre-3">
                            <div class="flex gap-1 mb-0.5 stars">
                                <i class="far fa-star text-gray-500 text-xs star" data-rating="1"></i>
                                <i class="far fa-star text-gray-500 text-xs star" data-rating="2"></i>
                                <i class="far fa-star text-gray-500 text-xs star" data-rating="3"></i>
                                <i class="far fa-star text-gray-500 text-xs star" data-rating="4"></i>
                                <i class="far fa-star text-gray-500 text-xs star" data-rating="5"></i>
                            </div>
                            <span class="text-gray-500 font-medium text-xs tracking-wider mt-0.5 rating-text">-.-</span>
                            <span class="rating-tooltip absolute right-0 bottom-full mb-1 bg-stone-900 text-gray-400 text-xs px-2 py-1 rounded shadow-lg opacity-0 transition-opacity duration-150 whitespace-nowrap pointer-events-none z-50">Haz clic para calificar</span>
                        </div>
                    </div>

                    <!-- Nombre -->
                    <h3 class="px-3 text-lg font-semibold text-white group-hover:text-[#F47E24] transition-colors duration-200 leading-tight">Chocobrowne</h3>

                    <!-- Descripción -->
                    <p class="px-3 text-xs text-gray-500 mt-1 mb-4 italic leading-snug">Combinación perfecta</p>

                    <!-- Precio y botón -->
                    <div class="mt-auto px-3 pb-5 flex items-center justify-between">
                        <span class="text-yellow-400 font-bold text-sm">RD$200</span>
                        <button class="btn-anadir text-[#F47E24] text-xs font-bold py-1 px-3 border border-[#F47E24] rounded-full uppercase hover:bg-[#F47E24] hover:text-white transition-colors duration-200"
                                data-action="anadir">
                            <i class="fas fa-shopping-cart mr-1 text-xs"></i> Anadir
                        </button>
                    </div>
                </div>
            </article>

            <!-- FLAN -->
            <article class="flex flex-col rounded-xl shadow-xl bg-[#4a2010] group overflow-hidden relative"
                     data-id="postre-4"
                     data-name="Flan"
                     data-price="120"
                     data-desc="Suave y tradicional"
                     data-tipo="stock"
                     data-stock="999">

                <!-- Indicador de stock -->
                <div class="absolute top-3 right-3 w-4 h-4 rounded-full border-2 border-white bg-green-500 z-10"></div>

                <!-- Imagen placeholder -->
                <div class="w-full h-40 bg-[#3d1e0b] flex items-center justify-center">
                    <i class="fas fa-ice-cream text-[#F47E24] text-3xl opacity-30"></i>
                </div>

                <div class="flex flex-col flex-1">
                    <!-- Rating row -->
                    <div class="flex items-center justify-between my-3">
                        <div class="relative w-5/12 h-8 flex items-center justify-end border-l-4 border-[#F47E24] rounded-tr-full rounded-br-full bg-[#6b3020]">
                            <div class="absolute right-1 z-30 w-6 h-6 rounded-full border-2 border-amber-900 bg-stone-800 flex items-center justify-center"><i class="fas fa-user text-amber-900 text-xs"></i></div>
                            <div class="absolute right-4 z-20 w-6 h-6 rounded-full border-2 border-amber-900 bg-stone-800 flex items-center justify-center"><i class="fas fa-user text-amber-900 text-xs"></i></div>
                            <div class="absolute right-7 z-10 w-6 h-6 rounded-full border-2 border-amber-900 bg-stone-800 flex items-center justify-center"><i class="fas fa-user text-amber-900 text-xs"></i></div>
                        </div>
                        <!-- Calificaciones -->
                        <div class="rating-block relative flex flex-col items-end pr-2 cursor-pointer select-none" data-product-id="postre-4">
                            <div class="flex gap-1 mb-0.5 stars">
                                <i class="far fa-star text-gray-500 text-xs star" data-rating="1"></i>
                                <i class="far fa-star text-gray-500 text-xs star" data-rating="2"></i>
                                <i class="far fa-star text-gray-500 text-xs star" data-rating="3"></i>
                                <i class="far fa-star text-gray-500 text-xs star" data-rating="4"></i>
                                <i class="far fa-star text-gray-500 text-xs star" data-rating="5"></i>
                            </div>
                            <span class="text-gray-500 font-medium text-xs tracking-wider mt-0.5 rating-text">-.-</span>
                            <span class="rating-tooltip absolute right-0 bottom-full mb-1 bg-stone-900 text-gray-400 text-xs px-2 py-1 rounded shadow-lg opacity-0 transition-opacity duration-150 whitespace-nowrap pointer-events-none z-50">Haz clic para calificar</span>
                        </div>
                    </div>

                    <!-- Nombre -->
                    <h3 class="px-3 text-lg font-semibold text-white group-hover:text-[#F47E24] transition-colors duration-200 leading-tight">Flan</h3>

                    <!-- Descripción -->
                    <p class="px-3 text-xs text-gray-500 mt-1 mb-4 italic leading-snug">Suave y tradicional</p>

                    <!-- Precio y botón -->
                    <div class="mt-auto px-3 pb-5 flex items-center justify-between">
                        <span class="text-yellow-400 font-bold text-sm">RD$120</span>
                        <button class="btn-anadir text-[#F47E24] text-xs font-bold py-1 px-3 border border-[#F47E24] rounded-full uppercase hover:bg-[#F47E24] hover:text-white transition-colors duration-200"
                                data-action="anadir">
                            <i class="fas fa-shopping-cart mr-1 text-xs"></i> Anadir
                        </button>
                    </div>
                </div>
            </article>

        </div>
    </section>

    </main>


<!-- ══════════════════════════════════════════════════════════
     MODAL: ANADIR AL CARRITO
═══════════════════════════════════════════════════════════ -->
<div id="modal-carrito" class="fixed inset-0 z-[9999] flex items-end sm:items-center justify-center p-4 hidden" role="dialog" aria-modal="true">
    <div id="modal-backdrop" class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="cerrarModal()"></div>
    <div id="modal-panel" class="relative w-full max-w-sm bg-[#2c1408] rounded-2xl shadow-2xl border border-[#F47E24]/20 overflow-hidden" style="max-height:90vh;overflow-y:auto;">
        <div class="bg-[#3d1e0b] px-5 py-4 flex items-center justify-between border-b border-[#F47E24]/15">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-[#F47E24]/15 flex items-center justify-center"><i class="fas fa-shopping-basket text-[#F47E24] text-sm"></i></div>
                <div><h3 id="modal-titulo" class="text-white font-bold text-sm leading-tight">Producto</h3><p id="modal-precio" class="text-[#F47E24] text-xs font-bold mt-0.5">RD$0</p></div>
            </div>
            <button onclick="cerrarModal()" class="w-8 h-8 rounded-lg flex items-center justify-center text-white/40 hover:text-white hover:bg-white/10 transition-all"><i class="fas fa-times text-sm"></i></button>
        </div>
        <div class="relative h-36 overflow-hidden">
            <img id="modal-img" src="" alt="" class="w-full h-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-[#2c1408]/80 to-transparent"></div>
        </div>
        <div class="px-5 py-5 space-y-5">
            <div id="modal-variantes-wrap">
                <label class="block text-[#c9a882] text-xs font-bold uppercase tracking-wider mb-2"><i class="fas fa-tag mr-1 text-[#F47E24]"></i> Tipo / Variante</label>
                <div id="modal-variantes" class="flex flex-wrap gap-2"></div>
            </div>
            <div>
                <label class="block text-[#c9a882] text-xs font-bold uppercase tracking-wider mb-2"><i class="fas fa-sort-numeric-up-alt mr-1 text-[#F47E24]"></i> Cantidad</label>
                <div class="flex items-center gap-3">
                    <button onclick="cambiarCantModal(-1)" class="w-10 h-10 rounded-xl bg-[#3d1e0b] border border-[#F47E24]/20 text-white font-bold text-lg hover:bg-[#F47E24] hover:border-[#F47E24] transition-all duration-200 flex items-center justify-center">−</button>
                    <span id="modal-cant" class="text-white font-bold text-xl w-8 text-center">1</span>
                    <button onclick="cambiarCantModal(1)" class="w-10 h-10 rounded-xl bg-[#3d1e0b] border border-[#F47E24]/20 text-white font-bold text-lg hover:bg-[#F47E24] hover:border-[#F47E24] transition-all duration-200 flex items-center justify-center">+</button>
                    <span class="text-[#c9a882] text-xs ml-1 font-medium" id="modal-subtotal">Subtotal: RD$0</span>
                </div>
            </div>
            <div>
                <label for="modal-nota" class="block text-[#c9a882] text-xs font-bold uppercase tracking-wider mb-2"><i class="fas fa-pen mr-1 text-[#F47E24]"></i> Nota <span class="text-[#7a5035] font-normal lowercase tracking-normal">(opcional)</span></label>
                <textarea id="modal-nota" rows="2" placeholder="Sin cebolla, extra picante..." maxlength="120" class="w-full bg-[#3d1e0b] border border-[#F47E24]/20 text-white placeholder-[#7a5035] rounded-xl px-4 py-2.5 text-sm font-medium resize-none focus:outline-none focus:border-[#F47E24]/60 transition-all"></textarea>
            </div>
            <div class="bg-[#3d1e0b] rounded-xl px-4 py-3 flex items-center justify-between">
                <span class="text-[#c9a882] text-sm font-medium">Total</span>
                <span id="modal-total" class="text-[#F47E24] font-bold text-lg">RD$0</span>
            </div>
            <div class="flex gap-3 pt-1">
                <button onclick="cerrarModal()" class="flex-1 py-3 rounded-xl border border-white/20 text-white/70 text-sm font-semibold hover:border-white/40 hover:text-white transition-all">Cancelar</button>
                <button id="btn-confirmar" onclick="confirmarAnadir()" class="flex-1 py-3 rounded-xl bg-[#F47E24] hover:bg-[#e06b15] text-white text-sm font-bold transition-all flex items-center justify-center gap-2">
                    <i class="fas fa-shopping-cart text-sm"></i> Anadir al carrito
                </button>
            </div>
            <div id="modal-no-sesion" class="hidden text-center py-4">
                <i class="fas fa-lock text-[#F47E24] text-2xl mb-2 block"></i>
                <p class="text-[#c9a882] text-sm font-medium mb-3">Debes iniciar sesion para agregar productos</p>
                <a href="./login.html" class="inline-flex items-center gap-2 bg-[#F47E24] hover:bg-[#e06b15] text-white text-sm font-bold py-2.5 px-6 rounded-full transition-all">
                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesion
                </a>
            </div>
        </div>
    </div>
</div>

<div id="toast-menu" class="fixed bottom-6 right-6 z-[9998] hidden">
    <div class="flex items-center gap-3 bg-[#2c1408] border border-[#F47E24]/30 text-white text-sm font-semibold px-5 py-3.5 rounded-2xl shadow-2xl min-w-[240px]">
        <div class="w-8 h-8 rounded-full bg-[#F47E24]/15 flex items-center justify-center flex-shrink-0">
            <i id="toast-m-icon" class="fas fa-check text-[#F47E24] text-xs"></i>
        </div>
        <span id="toast-m-msg">Producto agregado</span>
    </div>
</div>

<style>
    #modal-carrito.open{display:flex;}
    #modal-carrito.open #modal-panel{transform:translateY(0);opacity:1;}
    .variante-chip{padding:6px 14px;border-radius:9999px;border:1px solid rgba(244,126,36,.25);color:#c9a882;font-size:.75rem;font-weight:600;cursor:pointer;transition:all .15s;background:#3d1e0b;white-space:nowrap;}
    .variante-chip:hover{border-color:#F47E24;color:#F47E24;}
    .variante-chip.selected{background:#F47E24;border-color:#F47E24;color:#fff;}
</style>

<script>
(function(){
    const LOGUEADO = <?= $logueado ? 'true' : 'false' ?>;
    let modalData={}, cantidad=1, varianteSel='';

    /* ── Delegacion clicks "Anadir" ──────────────── */
    document.addEventListener('click', function(e){
        const btn = e.target.closest('[data-action="anadir"]');
        if(!btn) return;
        e.preventDefault();
        const card = btn.closest('article[data-name]');
        if(!card) return;

        const nombre    = card.dataset.name;
        const precio    = parseFloat(card.dataset.price||'0');
        const imgEl     = card.querySelector('img');
        const imgSrc    = imgEl ? imgEl.src : '';
        const descTxt   = card.dataset.desc || '';
        const idProd    = parseInt(card.dataset.id||'0');
        const stockMax  = parseInt(card.dataset.stock||'999');

        const variantes = descTxt.split(/·|,|;/).map(v=>v.trim().replace(/\s+/g,' ')).filter(v=>v.length>0);

        abrirModal({nombre, precio, imgSrc, variantes, idProd, stockMax});
    });

    /* ── Abrir modal ────────────────────────────── */
    window.abrirModal = function({nombre, precio, imgSrc, variantes, idProd, stockMax}){
        modalData = {nombre, precio, idProd, stockMax: stockMax||999};
        cantidad = 1; varianteSel = '';

        const modal = document.getElementById('modal-carrito');
        const panel = document.getElementById('modal-panel');

        document.getElementById('modal-titulo').textContent = nombre;
        document.getElementById('modal-precio').textContent = 'RD$'+precio.toFixed(2)+' c/u';
        document.getElementById('modal-cant').textContent = '1';
        document.getElementById('modal-nota').value = '';

        const imgEl = document.getElementById('modal-img');
        if(imgSrc){imgEl.src=imgSrc;imgEl.style.display='';}else{imgEl.style.display='none';}

        const wrap = document.getElementById('modal-variantes-wrap');
        const cont = document.getElementById('modal-variantes');
        cont.innerHTML='';
        if(variantes.length>0){
            wrap.style.display='';
            variantes.forEach(v=>{
                const chip = document.createElement('button');
                chip.className='variante-chip'+(variantes.length===1?' unica':'');
                chip.type='button'; chip.textContent=v;
                chip.addEventListener('click',function(){
                    document.querySelectorAll('.variante-chip').forEach(c=>c.classList.remove('selected'));
                    chip.classList.add('selected'); varianteSel=v; actualizarTotales();
                });
                cont.appendChild(chip);
            });
            if(variantes.length===1){cont.firstChild.classList.add('selected');varianteSel=variantes[0];}
        }else{wrap.style.display='none';varianteSel='';}

        const noSesionDiv=document.getElementById('modal-no-sesion');
        const btnConf=document.getElementById('btn-confirmar');
        const bodyEls=modal.querySelectorAll('.space-y-5 > *:not(#modal-no-sesion)');
        if(!LOGUEADO){bodyEls.forEach(el=>{el.style.opacity='.4';el.style.pointerEvents='none';});noSesionDiv.classList.remove('hidden');btnConf.style.display='none';}
        else{bodyEls.forEach(el=>{el.style.opacity='';el.style.pointerEvents='';});noSesionDiv.classList.add('hidden');btnConf.style.display='';}

        actualizarTotales();
        panel.style.transform='translateY(20px)';panel.style.opacity='0';
        modal.classList.remove('hidden');modal.classList.add('open');
        document.body.style.overflow='hidden';
        requestAnimationFrame(()=>{panel.style.transition='transform .28s cubic-bezier(.4,0,.2,1),opacity .28s ease';panel.style.transform='translateY(0)';panel.style.opacity='1';});
    };

    window.cerrarModal = function(){
        const modal=document.getElementById('modal-carrito'),panel=document.getElementById('modal-panel');
        panel.style.transform='translateY(16px)';panel.style.opacity='0';
        setTimeout(()=>{modal.classList.add('hidden');modal.classList.remove('open');document.body.style.overflow='';},240);
    };

    document.addEventListener('keydown',e=>{if(e.key==='Escape')cerrarModal();});

    window.cambiarCantModal = function(delta){
        cantidad=Math.max(1,Math.min(modalData.stockMax||20,cantidad+delta));
        document.getElementById('modal-cant').textContent=cantidad;
        actualizarTotales();
    };

    function actualizarTotales(){
        const total=(modalData.precio||0)*cantidad;
        document.getElementById('modal-subtotal').textContent='Subtotal: RD$'+total.toFixed(2);
        document.getElementById('modal-total').textContent='RD$'+total.toFixed(2);
    }

    window.confirmarAnadir = function(){
        if(!LOGUEADO){window.location.href='./login.html';return;}
        const cont=document.getElementById('modal-variantes');
        if(cont.children.length>1&&!varianteSel){
            const wrap=document.getElementById('modal-variantes-wrap');
            wrap.style.outline='2px solid #F47E24';wrap.style.borderRadius='8px';
            setTimeout(()=>{wrap.style.outline='';wrap.style.borderRadius='';},1400);return;
        }
        const btn=document.getElementById('btn-confirmar');
        btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin mr-2"></i> Agregando...';

        const nota=document.getElementById('modal-nota').value.trim();
        const body=new URLSearchParams({
            id_producto:    modalData.idProd,
            nombre_producto:modalData.nombre,
            cantidad:       cantidad,
            variante:       varianteSel||'',
            nota:           nota
        });

        fetch('./backend/carrito/agregar_item.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()})
        .then(r=>r.json()).then(d=>{
            btn.disabled=false;btn.innerHTML='<i class="fas fa-shopping-cart text-sm"></i> Anadir al carrito';
            if(d.no_sesion){window.location.href='./login.html';return;}
            if(d.error){toastMenu(d.error,'error');return;}
            cerrarModal();
            toastMenu('+'+(varianteSel?varianteSel+' — ':'')+d.producto+' x'+cantidad+' al carrito');
            // Actualizar badge carrito
            const badge=document.getElementById('nav-cant-carrito');
            if(badge){badge.textContent=d.cant_carrito;badge.classList.toggle('hidden',d.cant_carrito<1);}
        }).catch(()=>{btn.disabled=false;btn.innerHTML='<i class="fas fa-shopping-cart text-sm"></i> Anadir al carrito';toastMenu('Error de conexion','error');});
    };

    let _tt;
    window.toastMenu=function(msg,tipo){
        clearTimeout(_tt);
        const el=document.getElementById('toast-menu'),ico=document.getElementById('toast-m-icon'),span=document.getElementById('toast-m-msg');
        span.textContent=msg;ico.className=tipo==='error'?'fas fa-exclamation-circle text-red-400 text-xs':'fas fa-check text-[#F47E24] text-xs';
        el.classList.remove('hidden');_tt=setTimeout(()=>el.classList.add('hidden'),3000);
    };
})();
</script>

<!-- ====================================================== -->
<!-- FOOTER -->
<!-- ====================================================== -->
<footer class="w-full bg-gray-200">
    <!-- Auth CTA Bar -->
    <div class="bg-gradient-to-r from-[#3d1e0b] to-[#55301c] py-5 px-4">
        <div class="mx-auto max-w-7xl flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="text-center sm:text-left">
                <p class="text-white font-semibold text-sm">¿Ya eres parte de nuestra familia?</p>
                <p class="text-[#c9a882] text-xs mt-0.5">Inicia sesión o crea tu cuenta para disfrutar beneficios exclusivos</p>
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
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-8 py-10 max-sm:max-w-sm max-sm:mx-auto gap-y-8">
            <div class="col-span-full mb-10 lg:col-span-2 lg:mb-0">
                <p class="py-2 text-sm text-gray-500 lg:max-w-xs text-center lg:text-left">Comprometidos con
                    el bienestar y la conformidad de nuestros clientes</p>
                <a href="./contacto.html"
                    class="py-2.5 px-5 h-9 block w-fit bg-[#F47E24] rounded-full shadow-sm text-xs text-white mx-auto transition-all duration-500 hover:bg-[#e06b15] lg:mx-0">
                    Contacto
                </a>
            </div>
            <div class="lg:mx-auto text-left">
                <h4 class="text-lg text-gray-900 font-medium mb-7">Índice</h4>
                <ul class="text-sm transition-all duration-500">
                    <li class="mb-6"><a href="./index.html" class="text-gray-600 hover:text-gray-900">Inicio</a></li>
                    <li class="mb-6"><a href="./sobre-nosotros.php" class="text-gray-600 hover:text-gray-900">Sobre nosotros</a></li>
                    <li class="mb-6"><a href="./menu.php" class="text-gray-600 hover:text-gray-900">Productos</a></li>
                    <li class="mb-6"><a href="./contacto.html" class="text-gray-600 hover:text-gray-900">Contacto</a></li>
                    <li><a href="./nuestra-gente.html" class="text-gray-600 hover:text-gray-900">Nuestra Gente</a></li>
                </ul>
            </div>
            <div class="lg:mx-auto text-left">
                <h4 class="text-lg text-gray-900 font-medium mb-7">Productos</h4>
                <ul class="text-sm transition-all duration-500">
                    <li class="mb-6"><a href="./menu.php" class="text-gray-600 hover:text-gray-900">Picaderas</a></li>
                    <li class="mb-6"><a href="./menu.php" class="text-gray-600 hover:text-gray-900">Sandwiches y tostadas</a></li>
                    <li class="mb-6"><a href="./menu.php" class="text-gray-600 hover:text-gray-900">Bebidas</a></li>
                    <li><a href="./menu.php" class="text-gray-600 hover:text-gray-900">Postres</a></li>
                </ul>
            </div>
        </div>
        <div class="py-7 border-t border-gray-200">
            <div class="flex items-center justify-center flex-col lg:justify-between lg:flex-row">
                <span class="text-sm text-gray-500">©<a href="./index.html">Del Puente Cafe y Snack</a> 2026, All rights reserved.</span>
                <div class="flex mt-4 space-x-4 sm:justify-center lg:mt-0">
                    <a href="https://www.instagram.com/delpuentecafe/" target="_blank"
                        class="w-9 h-9 rounded-full bg-gray-700 flex justify-center items-center hover:bg-[#F47E24] transition-colors duration-300">
                        <svg class="w-[1.25rem] h-[1.125rem] text-white" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4.70975 7.93663C4.70975 6.65824 5.76102 5.62163 7.0582 5.62163C8.35537 5.62163 9.40721 6.65824 9.40721 7.93663C9.40721 9.21502 8.35537 10.2516 7.0582 10.2516C5.76102 10.2516 4.70975 9.21502 4.70975 7.93663ZM3.43991 7.93663C3.43991 9.90608 5.05982 11.5025 7.0582 11.5025C9.05658 11.5025 10.6765 9.90608 10.6765 7.93663C10.6765 5.96719 9.05658 4.37074 7.0582 4.37074C5.05982 4.37074 3.43991 5.96719 3.43991 7.93663ZM9.97414 4.22935C9.97408 4.39417 10.0236 4.55531 10.1165 4.69239C10.2093 4.82946 10.3413 4.93633 10.4958 4.99946C10.6503 5.06259 10.8203 5.07916 10.9844 5.04707C11.1484 5.01498 11.2991 4.93568 11.4174 4.81918C11.5357 4.70268 11.6163 4.55423 11.649 4.39259C11.6817 4.23095 11.665 4.06339 11.6011 3.91109C11.5371 3.7588 11.4288 3.6286 11.2898 3.53698C11.1508 3.44536 10.9873 3.39642 10.8201 3.39635H10.8197C10.5955 3.39646 10.3806 3.48424 10.222 3.64043C10.0635 3.79661 9.97434 4.00843 9.97414 4.22935ZM4.21142 13.5892C3.52442 13.5584 3.15101 13.4456 2.90286 13.3504C2.57387 13.2241 2.33914 13.0738 2.09235 12.8309C1.84555 12.588 1.69278 12.3569 1.56527 12.0327C1.46854 11.7882 1.3541 11.4201 1.32287 10.7431C1.28871 10.0111 1.28189 9.79119 1.28189 7.93669C1.28189 6.08219 1.28927 5.86291 1.32287 5.1303C1.35416 4.45324 1.46944 4.08585 1.56527 3.84069C1.69335 3.51647 1.84589 3.28513 2.09235 3.04191C2.3388 2.79869 2.57331 2.64813 2.90286 2.52247C3.1509 2.42713 3.52442 2.31435 4.21142 2.28358C4.95417 2.24991 5.17729 2.24319 7.0582 2.24319C8.9391 2.24319 9.16244 2.25047 9.90582 2.28358C10.5928 2.31441 10.9656 2.42802 11.2144 2.52247C11.5434 2.64813 11.7781 2.79902 12.0249 3.04191C12.2717 3.2848 12.4239 3.51647 12.552 3.84069C12.6487 4.08513 12.7631 4.45324 12.7944 5.1303C12.8285 5.86291 12.8354 6.08219 12.8354 7.93669C12.8354 9.79119 12.8285 10.0105 12.7944 10.7431C12.7631 11.4201 12.6481 11.7881 12.552 12.0327C12.4239 12.3569 12.2714 12.5882 12.0249 12.8309C11.7784 13.0736 11.5434 13.2241 11.2144 13.3504C10.9663 13.4457 10.5928 13.5585 9.90582 13.5892C9.16306 13.6229 8.93994 13.6296 7.0582 13.6296C5.17645 13.6296 4.95395 13.6229 4.21142 13.5892ZM4.15307 1.03424C3.40294 1.06791 2.89035 1.18513 2.4427 1.3568C1.9791 1.53408 1.58663 1.77191 1.19446 2.1578C0.802277 2.54369 0.56157 2.93108 0.381687 3.38797C0.207498 3.82941 0.0885535 4.3343 0.0543922 5.07358C0.0196672 5.81402 0.0117188 6.05074 0.0117188 7.93663C0.0117188 9.82252 0.0196672 10.0592 0.0543922 10.7997C0.0885535 11.539 0.207498 12.0439 0.381687 12.4853C0.56157 12.9419 0.802334 13.3297 1.19446 13.7155C1.58658 14.1012 1.9791 14.3387 2.4427 14.5165C2.89119 14.6881 3.40294 14.8054 4.15307 14.839C4.90479 14.8727 5.1446 14.8811 7.0582 14.8811C8.9718 14.8811 9.212 14.8732 9.96332 14.839C10.7135 14.8054 11.2258 14.6881 11.6737 14.5165C12.137 14.3387 12.5298 14.1014 12.9219 13.7155C13.3141 13.3296 13.5543 12.9419 13.7347 12.4853C13.9089 12.0439 14.0284 11.539 14.062 10.7997C14.0962 10.0587 14.1041 9.82252 14.1041 7.93663C14.1041 6.05074 14.0962 5.81402 14.062 5.07358C14.0278 4.33424 13.9089 3.82913 13.7347 3.38797C13.5543 2.93135 13.3135 2.5443 12.9219 2.1578C12.5304 1.7713 12.137 1.53408 11.6743 1.3568C11.2258 1.18513 10.7135 1.06735 9.96388 1.03424C9.21256 1.00058 8.97236 0.992188 7.05876 0.992188C5.14516 0.992188 4.90479 1.00002 4.15307 1.03424Z" fill="currentColor" />
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
<script>
    // Calificaciones interactivas
    document.querySelectorAll('.rating-block').forEach(block => {
        const stars = block.querySelectorAll('.star');
        const ratingText = block.querySelector('.rating-text');
        const tooltip = block.querySelector('.rating-tooltip');
        const productId = block.dataset.productId;
        let currentRating = 0;

        stars.forEach(star => {
            star.addEventListener('mouseenter', () => {
                const rating = parseInt(star.dataset.rating);
                stars.forEach((s, i) => {
                    s.classList.toggle('fas', i < rating);
                    s.classList.toggle('far', i >= rating);
                    s.classList.toggle('text-yellow-400', i < rating);
                    s.classList.toggle('text-gray-500', i >= rating);
                });
            });

            star.addEventListener('mouseleave', () => {
                stars.forEach((s, i) => {
                    s.classList.toggle('fas', i < currentRating);
                    s.classList.toggle('far', i >= currentRating);
                    s.classList.toggle('text-yellow-400', i < currentRating);
                    s.classList.toggle('text-gray-500', i >= currentRating);
                });
            });

            star.addEventListener('click', () => {
                currentRating = parseInt(star.dataset.rating);
                ratingText.textContent = currentRating.toFixed(1);
                ratingText.classList.remove('text-gray-500');
                ratingText.classList.add('text-yellow-400');
                tooltip.textContent = '¡Gracias por calificar!';
                tooltip.classList.remove('text-gray-400');
                tooltip.classList.add('text-yellow-400');

                // Aquí se enviaría al backend
                console.log(`Producto ${productId} calificado con ${currentRating} estrellas`);
            });
        });

        block.addEventListener('mouseenter', () => {
            tooltip.classList.remove('opacity-0');
            tooltip.classList.add('opacity-100');
        });

        block.addEventListener('mouseleave', () => {
            tooltip.classList.remove('opacity-100');
            tooltip.classList.add('opacity-0');
        });
    });
</script>
</body>
</html>
