<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['id_usuario'])) {
    header('Location: ./login.html'); exit;
}

$id_usuario = $_SESSION['id_usuario'];
$nombre     = $_SESSION['nombre']  ?? 'Usuario';
$usuario    = $_SESSION['usuario'] ?? '';
$rol        = $_SESSION['rol']     ?? 'usuario';

require_once './backend/config/conexion.php';

// Carrito
$stmt_carrito = $pdo->prepare("
    SELECT c.id_carrito, c.cantidad,
           p.id_producto, p.nombre_producto, p.precio_producto, p.imagen
    FROM carrito c
    JOIN productos p ON c.id_producto = p.id_producto
    WHERE c.id_usuario = ?
    ORDER BY c.fecha_agregado DESC
");
$stmt_carrito->execute([$id_usuario]);
$items_carrito = $stmt_carrito->fetchAll();

$total_carrito = 0;
foreach ($items_carrito as $item) {
    $total_carrito += $item['precio_producto'] * $item['cantidad'];
}
$cant_carrito = count($items_carrito);

// Pedidos
$stmt_pedidos = $pdo->prepare("
    SELECT pe.id_pedido, pe.fecha_pedido, pe.total_pedido, pe.estado_pedido, pe.notas_pedido,
           COUNT(dp.id_detalle) AS num_productos
    FROM pedidos pe
    LEFT JOIN detalle_pedido dp ON pe.id_pedido = dp.id_pedido
    WHERE pe.id_usuario = ?
    GROUP BY pe.id_pedido
    ORDER BY pe.fecha_pedido DESC
");
$stmt_pedidos->execute([$id_usuario]);
$pedidos      = $stmt_pedidos->fetchAll();
$cant_pedidos = count($pedidos);

// Gasto total
$gasto_total = $pdo->prepare("
    SELECT COALESCE(SUM(total_pedido),0) FROM pedidos
    WHERE id_usuario = ? AND estado_pedido != 'cancelado'
");
$gasto_total->execute([$id_usuario]);
$gasto_total = (float)$gasto_total->fetchColumn();

// Direcciones guardadas
try {
    $stmt_dirs = $pdo->prepare("
        SELECT id_direccion, alias, direccion, referencia, es_favorita
        FROM direcciones_usuario
        WHERE id_usuario = ?
        ORDER BY es_favorita DESC, creado_en DESC
    ");
    $stmt_dirs->execute([$id_usuario]);
    $direcciones = $stmt_dirs->fetchAll();
} catch (PDOException $e) {
    // La tabla aun no existe — ejecutar la migración primero
    $direcciones = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Panel — Cafeteria del Puente</title>
    <meta name="description" content="Gestiona tu cuenta, carrito y pedidos en Cafeteria del Puente.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.iconify.design/iconify-icon/1.0.8/iconify-icon.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --primary:       #F47E24;
            --primary-dark:  #e06b15;
            --primary-light: rgba(244,126,36,0.10);

            --sidebar-bg:    #1e0f07;
            --sidebar-text:  rgba(255,255,255,0.62);
            --sidebar-hover: rgba(244,126,36,0.14);

            --bg:      #F8F5F1;
            --surface: #FFFFFF;

            --text-main:      #1e0f07;
            --text-secondary: #4a3728;
            --text-tertiary:  #7a6055;

            --border:       #E8DDD6;
            --border-light: #F1EAE4;

            --success:       #22c55e;
            --success-light: rgba(34,197,94,0.12);
            --danger:        #ef4444;
            --danger-light:  rgba(239,68,68,0.12);
            --warning:       #d97706;
            --warning-light: rgba(217,119,6,0.14);
            --info:          #0ea5e9;
            --info-light:    rgba(14,165,233,0.12);
            --purple:        #8b5cf6;
            --purple-light:  rgba(139,92,246,0.12);

            --shadow-sm: 0 2px 8px rgba(30,15,7,0.05), 0 4px 16px rgba(30,15,7,0.06);
            --shadow-md: 0 6px 20px rgba(30,15,7,0.09), 0 16px 32px rgba(30,15,7,0.11);
        }

        * { box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text-secondary); }

        /* Sidebar */
        #sidebar { background: var(--sidebar-bg); transition: transform .3s cubic-bezier(.4,0,.2,1); }
        .nav-item { display:flex; align-items:center; gap:12px; padding:11px 16px; border-radius:10px; margin-bottom:2px; color:var(--sidebar-text); font-weight:600; font-size:.925rem; transition:all .2s; cursor:pointer; }
        .nav-item:hover  { background: var(--sidebar-hover); color: var(--primary); }
        .nav-item.active { background: var(--primary); color:#fff; box-shadow:0 4px 14px rgba(244,126,36,.35); }
        .nav-item iconify-icon { font-size:20px; flex-shrink:0; }
        .nav-section { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:rgba(255,255,255,.28); padding:0 16px; margin:20px 0 6px; }

        /* Cards */
        .card { background:var(--surface); border-radius:16px; box-shadow:var(--shadow-sm); border:1px solid var(--border-light); transition:box-shadow .2s; }
        .card:hover { box-shadow:var(--shadow-md); }
        .stat-card { background:var(--surface); border-left:4px solid var(--primary); border-radius:12px; padding:18px 20px; box-shadow:var(--shadow-sm); border-top:1px solid var(--border-light); border-right:1px solid var(--border-light); border-bottom:1px solid var(--border-light); transition:transform .2s; }
        .stat-card:hover { transform:translateY(-2px); box-shadow:var(--shadow-md); }

        /* Buttons */
        .btn-primary { background:var(--primary); color:#fff; padding:9px 20px; border-radius:8px; font-weight:600; font-size:.875rem; transition:all .2s; border:none; display:inline-flex; align-items:center; gap:8px; cursor:pointer; text-decoration:none; }
        .btn-primary:hover { background:var(--primary-dark); transform:translateY(-1px); }
        .btn-outline { border:1px solid var(--border); color:var(--text-main); background:var(--surface); padding:8px 18px; border-radius:8px; font-weight:600; font-size:.875rem; transition:all .2s; display:inline-flex; align-items:center; gap:8px; cursor:pointer; text-decoration:none; }
        .btn-outline:hover { background:var(--bg); }
        .btn-icon { width:34px; height:34px; border-radius:8px; display:inline-flex; justify-content:center; align-items:center; transition:all .2s; color:var(--text-tertiary); background:transparent; cursor:pointer; border:none; }
        .btn-icon:hover { background:var(--primary-light); color:var(--primary); }

        /* Badges */
        .badge { padding:4px 10px; border-radius:6px; font-size:.72rem; font-weight:700; display:inline-flex; align-items:center; gap:4px; }
        .badge-pendiente  { background:var(--warning-light); color:var(--warning); }
        .badge-en_proceso { background:var(--info-light);    color:var(--info); }
        .badge-listo      { background:var(--purple-light);  color:var(--purple); }
        .badge-entregado  { background:var(--success-light); color:var(--success); }
        .badge-cancelado  { background:var(--danger-light);  color:var(--danger); }

        /* Tables */
        .table-custom { width:100%; border-collapse:separate; border-spacing:0; }
        .table-custom th { background:var(--bg); color:var(--text-tertiary); font-weight:700; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; padding:12px 16px; text-align:left; border-bottom:1px solid var(--border); white-space:nowrap; }
        .table-custom td { padding:14px 16px; border-bottom:1px solid var(--border-light); color:var(--text-secondary); font-size:.875rem; vertical-align:middle; transition:background .15s; }
        .table-custom tbody tr:hover td { background:var(--primary-light); }
        .table-custom tbody tr:last-child td { border-bottom:none; }

        /* Form */
        .input-solid { background:var(--bg); border:1px solid var(--border); color:var(--text-main); border-radius:8px; padding:9px 14px; font-size:.875rem; font-weight:500; transition:all .2s; font-family:inherit; width:100%; }
        .input-solid:focus { outline:none; border-color:var(--primary); background:var(--surface); box-shadow:0 0 0 3px var(--primary-light); }

        /* Views */
        .content-area { display:none; animation:fadeUp .35s ease both; }
        .content-area.active { display:block; }
        @keyframes fadeUp { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }

        /* Scrollbar */
        ::-webkit-scrollbar { width:5px; height:5px; }
        ::-webkit-scrollbar-track { background:transparent; }
        ::-webkit-scrollbar-thumb { background:var(--border); border-radius:10px; }
        ::-webkit-scrollbar-thumb:hover { background:var(--primary); }
    </style>
</head>
<body class="overflow-hidden flex h-screen">

<!-- ════════════════ SIDEBAR ════════════════════════════════════════════ -->
<aside id="sidebar" class="w-[260px] flex flex-col h-full absolute z-30 md:relative transform -translate-x-full md:translate-x-0 shadow-2xl md:shadow-none flex-shrink-0">

    <!-- Logo -->
    <div class="h-[76px] flex items-center px-5 border-b border-white/8 flex-shrink-0">
        <a href="./index.html" class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-[#F47E24] flex items-center justify-center shadow-lg shadow-[#F47E24]/40">
                <iconify-icon icon="solar:cup-hot-bold-duotone" width="22" class="text-white"></iconify-icon>
            </div>
            <div class="leading-tight">
                <div class="text-white font-bold text-[.95rem] tracking-tight">Del Puente</div>
                <div class="text-[.62rem] font-bold text-[#F47E24] uppercase tracking-widest">Mi cuenta</div>
            </div>
        </a>
        <button class="md:hidden ml-auto btn-icon text-white/50 hover:text-white hover:bg-white/10" onclick="toggleSidebar()">
            <iconify-icon icon="solar:close-circle-bold-duotone" width="22"></iconify-icon>
        </button>
    </div>

    <!-- Nav -->
    <div class="flex-1 overflow-y-auto py-5 px-3">
        <div class="nav-section">Mi espacio</div>

        <a onclick="switchView('cuenta')" class="nav-item active" id="nav-cuenta">
            <iconify-icon icon="solar:user-circle-bold-duotone"></iconify-icon>
            <span>Mi perfil</span>
        </a>
        <a onclick="switchView('carrito')" class="nav-item" id="nav-carrito">
            <iconify-icon icon="solar:cart-large-4-bold-duotone"></iconify-icon>
            <span>Mi carrito</span>
            <?php if ($cant_carrito > 0): ?>
            <span class="ml-auto bg-[#F47E24] text-white text-[10px] font-bold w-5 h-5 rounded-full flex items-center justify-center leading-none flex-shrink-0">
                <?= min($cant_carrito, 99) ?>
            </span>
            <?php endif; ?>
        </a>
        <a onclick="switchView('pedidos')" class="nav-item" id="nav-pedidos">
            <iconify-icon icon="solar:clipboard-list-bold-duotone"></iconify-icon>
            <span>Mis pedidos</span>
        </a>
        <a onclick="switchView('direcciones')" class="nav-item" id="nav-direcciones">
            <iconify-icon icon="solar:map-point-bold-duotone"></iconify-icon>
            <span>Mis direcciones</span>
        </a>

        <div class="nav-section">Explorar</div>
        <a href="./index.html" class="nav-item opacity-70 hover:opacity-100">
            <iconify-icon icon="solar:home-angle-bold-duotone"></iconify-icon>
            <span>Inicio</span>
        </a>
        <a href="./menu.php" class="nav-item opacity-70 hover:opacity-100">
            <iconify-icon icon="solar:book-2-bold-duotone"></iconify-icon>
            <span>Ver menu</span>
        </a>
        <a href="./sobre-nosotros.html" class="nav-item opacity-70 hover:opacity-100">
            <iconify-icon icon="solar:info-circle-bold-duotone"></iconify-icon>
            <span>Sobre nosotros</span>
        </a>
        <a href="./contacto.html" class="nav-item opacity-70 hover:opacity-100">
            <iconify-icon icon="solar:chat-round-bold-duotone"></iconify-icon>
            <span>Contacto</span>
        </a>

        <?php if ($rol === 'admin'): ?>
        <div class="nav-section">Admin</div>
        <a href="./panel-admin.php" class="nav-item opacity-70 hover:opacity-100">
            <iconify-icon icon="solar:settings-bold-duotone"></iconify-icon>
            <span>Panel admin</span>
        </a>
        <?php endif; ?>
    </div>

    <!-- User summary -->
    <div class="p-4 border-t border-white/8 flex-shrink-0">
        <div class="flex items-center gap-3 bg-white/6 rounded-xl p-3">
            <div class="w-10 h-10 rounded-full bg-[#F47E24]/20 border border-[#F47E24]/40 flex items-center justify-center text-[#F47E24] font-bold text-sm flex-shrink-0">
                <?= strtoupper(substr($nombre, 0, 1)) ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-white text-sm font-bold truncate"><?= htmlspecialchars($nombre) ?></div>
                <div class="text-[#F47E24] text-[.65rem] font-semibold uppercase tracking-wide">@<?= htmlspecialchars($usuario) ?></div>
            </div>
            <a href="./backend/autenticacion/cerrar_sesion.php"
               class="w-8 h-8 rounded-lg flex items-center justify-center text-red-400/70 hover:text-red-400 hover:bg-red-400/10 transition-all flex-shrink-0"
               title="Cerrar sesion">
                <iconify-icon icon="solar:logout-bold-duotone" width="18"></iconify-icon>
            </a>
        </div>
    </div>

</aside>

<!-- Mobile overlay -->
<div id="mobile-overlay" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-20 hidden md:hidden" onclick="toggleSidebar()"></div>

<!-- ════════════════ MAIN ════════════════════════════════════════════════ -->
<main class="flex-1 flex flex-col min-w-0 bg-[var(--bg)]">

    <!-- Header -->
    <header class="h-[76px] bg-white border-b border-[var(--border-light)] flex items-center justify-between px-6 flex-shrink-0 shadow-sm z-10 sticky top-0">
        <div class="flex items-center gap-4">
            <button class="btn-icon md:hidden" onclick="toggleSidebar()">
                <iconify-icon icon="solar:hamburger-menu-bold-duotone" width="24"></iconify-icon>
            </button>
            <div>
                <h2 class="text-[1rem] font-bold text-[var(--text-main)] leading-tight m-0" id="header-title">Mi perfil</h2>
                <div class="text-[.7rem] font-semibold text-[var(--text-tertiary)] uppercase tracking-wider mt-0.5">Cafeteria del Puente</div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="./menu.php" class="btn-primary shadow-md shadow-[#F47E24]/20 text-sm px-4 py-2">
                <iconify-icon icon="solar:book-2-bold-duotone" width="16"></iconify-icon>
                <span class="hidden sm:inline">Ver menu</span>
            </a>
            <a href="./backend/autenticacion/cerrar_sesion.php"
               class="flex items-center gap-1.5 px-4 py-2 rounded-full border border-red-200 text-red-500 text-sm font-semibold hover:bg-red-50 transition-all">
                <iconify-icon icon="solar:logout-bold-duotone" width="16"></iconify-icon>
                <span class="hidden sm:inline">Salir</span>
            </a>
        </div>
    </header>

    <!-- Scroll area -->
    <div class="flex-1 overflow-y-auto p-5 sm:p-7" id="main-scroll">

        <!-- ══════ VIEW: MI CUENTA ═══════════════════════════════ -->
        <div id="view-cuenta" class="content-area active max-w-[1100px] mx-auto">

            <!-- Welcome -->
            <div class="mb-7">
                <h1 class="text-2xl font-bold text-[var(--text-main)] m-0">
                    Hola, <?= htmlspecialchars($nombre) ?>
                    <iconify-icon icon="solar:cup-hot-bold-duotone" class="text-[var(--primary)] align-middle" width="26"></iconify-icon>
                </h1>
                <p class="text-[var(--text-tertiary)] text-sm font-medium mt-1">Bienvenido a tu espacio personal en Cafeteria del Puente.</p>
            </div>

            <!-- Stat cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-7">

                <div class="stat-card" style="border-left-color:var(--primary)">
                    <div class="flex justify-between items-start mb-3">
                        <span class="text-[.68rem] font-700 uppercase tracking-wider text-[var(--text-tertiary)]">Pedidos</span>
                        <div class="w-8 h-8 rounded-lg bg-[var(--primary-light)] text-[var(--primary)] flex items-center justify-center">
                            <iconify-icon icon="solar:clipboard-list-bold-duotone" width="16"></iconify-icon>
                        </div>
                    </div>
                    <div class="text-2xl font-bold text-[var(--text-main)]"><?= $cant_pedidos ?></div>
                    <div class="text-xs text-[var(--text-tertiary)] mt-1 font-medium">Pedidos realizados</div>
                </div>

                <div class="stat-card" style="border-left-color:var(--info)">
                    <div class="flex justify-between items-start mb-3">
                        <span class="text-[.68rem] font-700 uppercase tracking-wider text-[var(--text-tertiary)]">Carrito</span>
                        <div class="w-8 h-8 rounded-lg bg-[var(--info-light)] text-[var(--info)] flex items-center justify-center">
                            <iconify-icon icon="solar:cart-large-4-bold-duotone" width="16"></iconify-icon>
                        </div>
                    </div>
                    <div class="text-2xl font-bold text-[var(--text-main)]"><?= $cant_carrito ?></div>
                    <div class="text-xs text-[var(--text-tertiary)] mt-1 font-medium">Items en carrito</div>
                </div>

                <div class="stat-card" style="border-left-color:var(--success)">
                    <div class="flex justify-between items-start mb-3">
                        <span class="text-[.68rem] font-700 uppercase tracking-wider text-[var(--text-tertiary)]">Gastado</span>
                        <div class="w-8 h-8 rounded-lg bg-[var(--success-light)] text-[var(--success)] flex items-center justify-center">
                            <iconify-icon icon="solar:dollar-minimalistic-bold-duotone" width="16"></iconify-icon>
                        </div>
                    </div>
                    <div class="text-xl font-bold text-[var(--text-main)]">RD$<?= number_format($gasto_total, 0, '.', ',') ?></div>
                    <div class="text-xs text-[var(--text-tertiary)] mt-1 font-medium">Total acumulado</div>
                </div>

                <div class="stat-card" style="border-left-color:var(--warning)">
                    <div class="flex justify-between items-start mb-3">
                        <span class="text-[.68rem] font-700 uppercase tracking-wider text-[var(--text-tertiary)]">Estado</span>
                        <div class="w-8 h-8 rounded-lg bg-[var(--warning-light)] text-[var(--warning)] flex items-center justify-center">
                            <iconify-icon icon="solar:user-check-bold-duotone" width="16"></iconify-icon>
                        </div>
                    </div>
                    <div class="text-sm font-bold text-[var(--success)] mt-1">Cuenta activa</div>
                    <div class="text-xs text-[var(--text-tertiary)] mt-1 font-medium">@<?= htmlspecialchars($usuario) ?></div>
                </div>

            </div>

            <!-- Profile + Quick actions-->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Profile Card -->
                <div class="card p-6 flex flex-col items-center text-center">
                    <div class="w-20 h-20 rounded-full bg-gradient-to-br from-[#F47E24] to-[#e06b15] flex items-center justify-center text-white font-bold text-3xl shadow-lg shadow-[#F47E24]/30 mb-4">
                        <?= strtoupper(substr($nombre, 0, 1)) ?>
                    </div>
                    <h3 class="text-[var(--text-main)] font-bold text-lg m-0"><?= htmlspecialchars($nombre) ?></h3>
                    <p class="text-[var(--text-tertiary)] text-sm font-medium mt-1">@<?= htmlspecialchars($usuario) ?></p>
                    <span class="mt-3 badge" style="background:<?= $rol==='admin' ? 'var(--warning-light)' : 'var(--primary-light)' ?>;color:<?= $rol==='admin' ? 'var(--warning)' : 'var(--primary)' ?>">
                        <iconify-icon icon="<?= $rol==='admin' ? 'solar:shield-bold-duotone' : 'solar:star-bold-duotone' ?>" width="12"></iconify-icon>
                        <?= $rol === 'admin' ? 'Administrador' : 'Cliente Premium' ?>
                    </span>
                    <hr class="border-[var(--border-light)] my-5 w-full">
                    <div class="w-full space-y-3">
                        <button onclick="switchView('carrito')" class="btn-primary w-full justify-center">
                            <iconify-icon icon="solar:cart-large-4-bold-duotone" width="18"></iconify-icon>
                            Ver mi carrito <?php if($cant_carrito>0): ?><span class="bg-white text-[var(--primary)] text-[10px] font-bold w-5 h-5 rounded-full flex items-center justify-center leading-none"><?= $cant_carrito ?></span><?php endif; ?>
                        </button>
                        <a href="./menu.php" class="btn-outline w-full justify-center">
                            <iconify-icon icon="solar:book-2-bold-duotone" width="18"></iconify-icon>
                            Explorar el menu
                        </a>
                    </div>
                </div>

                <!-- Recent orders -->
                <div class="card overflow-hidden lg:col-span-2">
                    <div class="px-6 py-4 border-b border-[var(--border-light)] flex items-center justify-between">
                        <h3 class="text-[var(--text-main)] font-bold text-[1rem] m-0">Pedidos recientes</h3>
                        <button onclick="switchView('pedidos')" class="text-xs text-[var(--primary)] font-bold hover:underline">
                            Ver todos &rarr;
                        </button>
                    </div>
                    <?php if (empty($pedidos)): ?>
                    <div class="text-center py-14 text-[var(--text-tertiary)]">
                        <iconify-icon icon="solar:clipboard-bold-duotone" width="40" class="opacity-20 mb-3 block mx-auto"></iconify-icon>
                        <p class="text-sm font-medium">Aun no has realizado pedidos</p>
                        <a href="./menu.php" class="btn-primary mt-4 text-sm w-fit mx-auto">Ver el menu</a>
                    </div>
                    <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th class="text-center">Items</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach (array_slice($pedidos, 0, 4) as $p): ?>
                                <tr>
                                    <td>
                                        <div class="font-bold text-[var(--text-main)]">#<?= $p['id_pedido'] ?></div>
                                        <div class="text-[.7rem] text-[var(--text-tertiary)]"><?= date('d/m/Y H:i', strtotime($p['fecha_pedido'])) ?></div>
                                    </td>
                                    <td class="text-center text-sm"><?= $p['num_productos'] ?></td>
                                    <td class="text-center font-bold text-[var(--text-main)] text-sm">RD$<?= number_format((float)$p['total_pedido'],2) ?></td>
                                    <td class="text-center">
                                        <span class="badge badge-<?= $p['estado_pedido'] ?>">
                                            <?= ucfirst(str_replace('_',' ',$p['estado_pedido'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div><!-- /cuenta -->


        <!-- ══════ VIEW: CARRITO ════════════════════════════════ -->
        <div id="view-carrito" class="content-area max-w-[1100px] mx-auto">

            <div class="mb-6">
                <h1 class="text-2xl font-bold text-[var(--text-main)] m-0">Mi carrito</h1>
                <p class="text-[var(--text-tertiary)] text-sm font-medium mt-1">
                    <?= $cant_carrito ?> producto<?= $cant_carrito!=1?'s':'' ?> guardado<?= $cant_carrito!=1?'s':'' ?> para tu proximo pedido.
                </p>
            </div>

            <?php if (empty($items_carrito)): ?>
            <div class="card p-14 text-center">
                <iconify-icon icon="solar:cart-large-4-bold-duotone" width="56" class="text-[var(--border)] mb-4 block mx-auto"></iconify-icon>
                <h3 class="text-[var(--text-main)] font-bold mb-2">Tu carrito esta vacio</h3>
                <p class="text-[var(--text-tertiary)] text-sm mb-6">Agrega productos desde el menu para verlos aqui.</p>
                <a href="./menu.php" class="btn-primary mx-auto w-fit">
                    <iconify-icon icon="solar:book-2-bold-duotone" width="18"></iconify-icon>
                    Ir al menu
                </a>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Cart table -->
                <div class="card overflow-hidden lg:col-span-2">
                    <div class="overflow-x-auto">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th colspan="2">Producto</th>
                                    <th class="text-center">Precio unit.</th>
                                    <th class="text-center">Cantidad</th>
                                    <th class="text-right">Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($items_carrito as $item):
                                $subtotal = $item['precio_producto'] * $item['cantidad'];
                            ?>
                                <tr id="cart-row-<?= $item['id_carrito'] ?>">
                                    <td class="w-12 px-3">
                                        <div class="w-10 h-10 rounded-xl bg-[var(--primary-light)] flex items-center justify-center">
                                            <iconify-icon icon="solar:cup-hot-bold-duotone" class="text-[var(--primary)]" width="20"></iconify-icon>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="font-bold text-[var(--text-main)] text-sm"><?= htmlspecialchars($item['nombre_producto']) ?></div>
                                        <div class="text-[.7rem] text-[var(--text-tertiary)]">Cafeteria del Puente</div>
                                    </td>
                                    <td class="text-center text-sm font-semibold text-[var(--text-main)]">
                                        RD$<?= number_format((float)$item['precio_producto'],2) ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button onclick="cambiarCantidad(<?= $item['id_carrito'] ?>, <?= $item['cantidad']-1 ?>)"
                                                    class="btn-icon w-7 h-7 rounded-lg border border-[var(--border)] hover:border-[var(--primary)] hover:bg-[var(--primary-light)] text-sm font-bold">
                                                −
                                            </button>
                                            <span class="font-bold text-[var(--text-main)] w-5 text-center text-sm" id="qty-<?= $item['id_carrito'] ?>"><?= $item['cantidad'] ?></span>
                                            <button onclick="cambiarCantidad(<?= $item['id_carrito'] ?>, <?= $item['cantidad']+1 ?>)"
                                                    class="btn-icon w-7 h-7 rounded-lg border border-[var(--border)] hover:border-[var(--primary)] hover:bg-[var(--primary-light)] text-sm font-bold">
                                                +
                                            </button>
                                        </div>
                                    </td>
                                    <td class="text-right font-bold text-[var(--primary)] text-sm" id="sub-<?= $item['id_carrito'] ?>">
                                        RD$<?= number_format($subtotal,2) ?>
                                    </td>
                                    <td class="text-center">
                                        <button onclick="eliminarItem(<?= $item['id_carrito'] ?>)"
                                                class="btn-icon text-red-400/60 hover:text-red-500 hover:bg-red-50">
                                            <iconify-icon icon="solar:trash-bin-minimalistic-bold-duotone" width="16"></iconify-icon>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Order summary -->
                <div class="space-y-4">
                    <div class="card p-6">
                        <h3 class="text-[var(--text-main)] font-bold text-[1rem] m-0 mb-5">Resumen del pedido</h3>
                        <div class="space-y-3 text-sm">
                            <?php foreach ($items_carrito as $item): ?>
                            <div class="flex justify-between text-[var(--text-secondary)]">
                                <span class="truncate pr-2"><?= htmlspecialchars($item['nombre_producto']) ?> × <?= $item['cantidad'] ?></span>
                                <span class="font-semibold flex-shrink-0">RD$<?= number_format($item['precio_producto']*$item['cantidad'],2) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="border-t border-[var(--border-light)] mt-4 pt-4 flex justify-between items-center">
                            <span class="font-bold text-[var(--text-main)]">Total</span>
                            <span class="text-xl font-bold text-[var(--primary)]" id="total-carrito">RD$<?= number_format($total_carrito,2) ?></span>
                        </div>
                        <button onclick="realizarPedido()" class="btn-primary w-full justify-center mt-5 py-3">
                            <iconify-icon icon="solar:cart-check-bold-duotone" width="20"></iconify-icon>
                            Realizar pedido
                        </button>
                        <a href="./menu.php" class="btn-outline w-full justify-center mt-3 text-sm">
                            <iconify-icon icon="solar:book-2-bold-duotone" width="16"></iconify-icon>
                            Seguir explorando
                        </a>
                    </div>

                    <div class="card p-5 flex items-center gap-3">
                        <iconify-icon icon="solar:shield-check-bold-duotone" class="text-[var(--success)] flex-shrink-0" width="24"></iconify-icon>
                        <p class="text-xs text-[var(--text-tertiary)] font-medium leading-relaxed m-0">
                            Tu pedido se registra de forma segura y podras rastrearlo en "Mis pedidos".
                        </p>
                    </div>
                </div>

            </div>
            <?php endif; ?>
        </div><!-- /carrito -->


        <!-- ══════ VIEW: MIS PEDIDOS ════════════════════════════ -->
        <div id="view-pedidos" class="content-area max-w-[1100px] mx-auto">

            <div class="mb-6">
                <h1 class="text-2xl font-bold text-[var(--text-main)] m-0">Mis pedidos</h1>
                <p class="text-[var(--text-tertiary)] text-sm font-medium mt-1">Historial completo de tus compras en Cafeteria del Puente.</p>
            </div>

            <?php if (empty($pedidos)): ?>
            <div class="card p-14 text-center">
                <iconify-icon icon="solar:bag-check-bold-duotone" width="56" class="text-[var(--border)] mb-4 block mx-auto"></iconify-icon>
                <h3 class="text-[var(--text-main)] font-bold mb-2">Sin pedidos por ahora</h3>
                <p class="text-[var(--text-tertiary)] text-sm mb-6">Tus pedidos apareceran aqui una vez que los realices.</p>
                <a href="./menu.php" class="btn-primary mx-auto w-fit">Ir al menu</a>
            </div>
            <?php else: ?>
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Fecha</th>
                                <th class="text-center">Productos</th>
                                <th class="text-center">Total</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pedidos as $p): ?>
                            <tr id="ped-fila-<?= $p['id_pedido'] ?>">
                                <td><span class="font-bold text-[var(--text-main)]">#<?= $p['id_pedido'] ?></span></td>
                                <td>
                                    <div class="font-semibold text-[var(--text-main)] text-xs"><?= date('d/m/Y', strtotime($p['fecha_pedido'])) ?></div>
                                    <div class="text-[.7rem] text-[var(--text-tertiary)]"><?= date('H:i', strtotime($p['fecha_pedido'])) ?></div>
                                </td>
                                <td class="text-center text-sm"><?= $p['num_productos'] ?></td>
                                <td class="text-center font-bold text-[var(--primary)] text-sm">RD$<?= number_format((float)$p['total_pedido'],2) ?></td>
                                <td class="text-center">
                                    <span class="badge badge-<?= $p['estado_pedido'] ?>">
                                        <iconify-icon icon="solar:circle-bold-duotone" width="10"></iconify-icon>
                                        <?= ucfirst(str_replace('_',' ',$p['estado_pedido'])) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button onclick="verDetallePedido(<?= $p['id_pedido'] ?>)"
                                            class="btn-icon border border-[var(--border)] hover:border-[var(--primary)] hover:bg-[var(--primary-light)]">
                                        <iconify-icon id="det-ico-<?= $p['id_pedido'] ?>" icon="solar:alt-arrow-down-bold-duotone" width="16"></iconify-icon>
                                    </button>
                                </td>
                            </tr>
                            <tr id="ped-det-<?= $p['id_pedido'] ?>" class="hidden bg-[var(--bg)]">
                                <td colspan="6" class="px-6 py-3">
                                    <p class="text-xs text-[var(--text-tertiary)] italic">Cargando detalle...</p>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div><!-- /pedidos -->

<!-- ══════════════════════════════════════════════════════════
     VIEW: MIS DIRECCIONES
══════════════════════════════════════════════════════════ -->
<div id="view-direcciones" class="content-area max-w-[1100px] mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[var(--text-main)] m-0">Mis direcciones</h1>
            <p class="text-[var(--text-tertiary)] text-sm font-medium mt-1">Guarda hasta 5 direcciones para agilizar tu pedido.</p>
        </div>
        <button onclick="abrirFormDir()" class="btn-primary shadow-md shadow-[#F47E24]/20">
            <iconify-icon icon="solar:map-point-add-bold-duotone" width="18"></iconify-icon>
            Nueva dirección
        </button>
    </div>

    <div id="dirs-lista" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php if (empty($direcciones)): ?>
        <div class="col-span-3 card p-14 text-center" id="dirs-vacio">
            <iconify-icon icon="solar:map-point-bold-duotone" width="56" class="text-[var(--border)] mb-4 block mx-auto"></iconify-icon>
            <h3 class="text-[var(--text-main)] font-bold mb-2">Sin direcciones guardadas</h3>
            <p class="text-[var(--text-tertiary)] text-sm mb-6">Agrega una dirección para facilitar tu próximo pedido.</p>
            <button onclick="abrirFormDir()" class="btn-primary mx-auto w-fit">
                <iconify-icon icon="solar:map-point-add-bold-duotone" width="18"></iconify-icon>
                Agregar dirección
            </button>
        </div>
        <?php else: ?>
        <?php foreach ($direcciones as $dir): ?>
        <div class="card p-5 relative" id="dir-card-<?= $dir['id_direccion'] ?>">
            <?php if ($dir['es_favorita']): ?>
            <span class="absolute top-3 right-3 badge" style="background:var(--warning-light);color:var(--warning)">
                <iconify-icon icon="solar:star-bold-duotone" width="11"></iconify-icon> Favorita
            </span>
            <?php endif; ?>
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl bg-[var(--primary-light)] text-[var(--primary)] flex items-center justify-center flex-shrink-0 mt-0.5">
                    <iconify-icon icon="solar:map-point-bold-duotone" width="20"></iconify-icon>
                </div>
                <div class="min-w-0">
                    <div class="font-bold text-[var(--text-main)] text-sm truncate"><?= htmlspecialchars($dir['alias']) ?></div>
                    <div class="text-[var(--text-secondary)] text-xs mt-1 leading-snug"><?= htmlspecialchars($dir['direccion']) ?></div>
                    <?php if (!empty($dir['referencia'])): ?>
                    <div class="text-[var(--text-tertiary)] text-[.7rem] mt-0.5 italic"><?= htmlspecialchars($dir['referencia']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex gap-2 mt-4 pt-3 border-t border-[var(--border-light)]">
                <button onclick="editarDir(<?= htmlspecialchars(json_encode($dir), ENT_QUOTES) ?>)" class="btn-outline text-xs py-1.5 px-3 flex-1 justify-center">
                    <iconify-icon icon="solar:pen-bold-duotone" width="13"></iconify-icon> Editar
                </button>
                <button onclick="eliminarDir(<?= $dir['id_direccion'] ?>)" class="btn-icon border border-[var(--border)] hover:border-red-300 hover:text-red-500 hover:bg-red-50">
                    <iconify-icon icon="solar:trash-bin-minimalistic-bold-duotone" width="15"></iconify-icon>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div><!-- /direcciones -->

    </div><!-- /main-scroll -->
</main>

<!-- Toast -->
<div id="toast-user" class="fixed bottom-6 right-6 z-50 hidden">
    <div class="bg-[var(--text-main)] text-white text-sm font-semibold px-5 py-3 rounded-xl shadow-2xl flex items-center gap-2.5">
        <iconify-icon id="toast-u-icon" icon="solar:check-circle-bold-duotone" class="text-[var(--primary)]" width="18"></iconify-icon>
        <span id="toast-u-msg">OK</span>
    </div>
</div>

<!-- ═══════════════ MODAL: ELEGIR DIRECCIÓN (CHECKOUT) ════════════════ -->
<div id="modal-checkout" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 hidden" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="cerrarModalCheckout()"></div>
    <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl border border-[var(--border-light)] overflow-hidden" style="max-height:90vh;overflow-y:auto;">
        <!-- Header -->
        <div class="bg-[var(--text-main)] px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-[var(--primary)]/20 flex items-center justify-center">
                    <iconify-icon icon="solar:map-point-bold-duotone" class="text-[var(--primary)]" width="20"></iconify-icon>
                </div>
                <div>
                    <div class="text-white font-bold text-sm">Confirmar pedido</div>
                    <div class="text-white/50 text-[.7rem]">Selecciona una dirección de entrega</div>
                </div>
            </div>
            <button onclick="cerrarModalCheckout()" class="w-8 h-8 rounded-lg flex items-center justify-center text-white/40 hover:text-white hover:bg-white/10 transition-all">
                <iconify-icon icon="solar:close-circle-bold-duotone" width="20"></iconify-icon>
            </button>
        </div>

        <div class="p-6">
            <!-- Resumen rápido -->
            <div class="bg-[var(--bg)] rounded-xl p-4 mb-5 flex justify-between items-center">
                <span class="text-sm text-[var(--text-secondary)] font-medium">Total a pagar</span>
                <span class="text-lg font-bold text-[var(--primary)]" id="co-total">RD$0.00</span>
            </div>

            <!-- Lista de direcciones -->
            <div class="mb-4">
                <div class="flex items-center justify-between mb-3">
                    <label class="text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)]">Dirección de entrega</label>
                    <button onclick="cerrarModalCheckout();switchView('direcciones');" class="text-xs text-[var(--primary)] font-bold hover:underline">
                        + Gestionar
                    </button>
                </div>
                <div id="co-dirs" class="space-y-2"></div>
                <div id="co-no-dirs" class="hidden">
                    <p class="text-sm text-[var(--text-tertiary)] text-center py-3">No tienes direcciones guardadas.</p>
                    <button onclick="cerrarModalCheckout();switchView('direcciones');" class="btn-outline w-full justify-center text-sm">
                        <iconify-icon icon="solar:map-point-add-bold-duotone" width="16"></iconify-icon>
                        Agregar dirección
                    </button>
                </div>
            </div>

            <!-- Opción dirección manual -->
            <details class="mb-5">
                <summary class="cursor-pointer text-xs font-bold text-[var(--primary)] select-none">O escribe una dirección diferente…</summary>
                <div class="mt-3 space-y-2">
                    <input type="text" id="co-dir-manual" placeholder="Ej: Calle Principal #123, Santiago" maxlength="250"
                        class="input-solid" oninput="selectedDirId=null;document.querySelectorAll('.co-dir-chip').forEach(c=>c.classList.remove('co-dir-selected')); recheckCoForm()">
                    <input type="text" id="co-dir-ref" placeholder="Referencia: frente al parque…" maxlength="200"
                        class="input-solid text-sm">
                </div>
            </details>

            <!-- Botones -->
            <div class="flex gap-3">
                <button onclick="cerrarModalCheckout()" class="btn-outline flex-1 justify-center">Cancelar</button>
                <button id="btn-co-confirmar" onclick="abrirModalPago()" class="btn-primary flex-1 justify-center" disabled style="opacity:.4;cursor:not-allowed">
                    <iconify-icon icon="solar:card-bold-duotone" width="18"></iconify-icon>
                    Continuar al pago
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════ MODAL: PAGO (REPRESENTACIÓN) ═══════════════════════════ -->
<div id="modal-pago" class="fixed inset-0 z-[10000] flex items-center justify-center p-4 hidden" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" onclick="cerrarModalPago()"></div>
    <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl border border-[var(--border-light)] overflow-hidden" style="max-height:92vh;overflow-y:auto;">
        <!-- Header -->
        <div class="bg-gradient-to-r from-[#1e0f07] to-[#3d1e0b] px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-[var(--primary)]/20 flex items-center justify-center">
                    <iconify-icon icon="solar:card-bold-duotone" class="text-[var(--primary)]" width="20"></iconify-icon>
                </div>
                <div>
                    <div class="text-white font-bold text-sm">Método de pago</div>
                    <div class="text-white/50 text-[.7rem]">Paso 2 de 2 — Pago seguro</div>
                </div>
            </div>
            <button onclick="cerrarModalPago()" class="w-8 h-8 rounded-lg flex items-center justify-center text-white/40 hover:text-white hover:bg-white/10 transition-all">
                <iconify-icon icon="solar:close-circle-bold-duotone" width="20"></iconify-icon>
            </button>
        </div>

        <!-- AVISO REPRESENTACIÓN -->
        <div class="mx-5 mt-5 bg-amber-50 border border-amber-300 rounded-xl p-4 flex gap-3">
            <iconify-icon icon="solar:shield-warning-bold-duotone" class="text-amber-500 flex-shrink-0 mt-0.5" width="22"></iconify-icon>
            <div>
                <div class="text-amber-800 font-bold text-xs uppercase tracking-wide mb-1">Solo representación</div>
                <p class="text-amber-700 text-xs leading-relaxed m-0">
                    Este formulario es <strong>únicamente demostrativo</strong>. No se capturan, almacenan ni procesan datos reales de tarjeta. Puedes escribir cualquier dato de prueba.
                </p>
            </div>
        </div>

        <div class="p-5 space-y-4">
            <!-- Resumen mini -->
            <div class="bg-[var(--bg)] rounded-xl p-3 flex justify-between items-center text-sm">
                <span class="text-[var(--text-secondary)] font-medium flex items-center gap-2">
                    <iconify-icon icon="solar:cart-check-bold-duotone" class="text-[var(--primary)]" width="16"></iconify-icon>
                    Total a pagar
                </span>
                <span class="font-bold text-[var(--primary)]" id="pago-total">RD$0.00</span>
            </div>

            <!-- Selector tipo tarjeta -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)] mb-2">Tipo de tarjeta</label>
                <div class="flex gap-2">
                    <button type="button" onclick="seleccionarTarjeta(this,'visa')" data-tipo="visa"
                        class="tarjeta-btn flex-1 py-2.5 rounded-xl border-2 border-[var(--border)] flex items-center justify-center gap-2 font-bold text-sm transition-all hover:border-[var(--primary)] text-[var(--text-secondary)]">
                        <iconify-icon icon="logos:visa" width="28"></iconify-icon>
                        Visa
                    </button>
                    <button type="button" onclick="seleccionarTarjeta(this,'mastercard')" data-tipo="mastercard"
                        class="tarjeta-btn flex-1 py-2.5 rounded-xl border-2 border-[var(--border)] flex items-center justify-center gap-2 font-bold text-sm transition-all hover:border-[var(--primary)] text-[var(--text-secondary)]">
                        <iconify-icon icon="logos:mastercard" width="28"></iconify-icon>
                        Mastercard
                    </button>
                    <button type="button" onclick="seleccionarTarjeta(this,'amex')" data-tipo="amex"
                        class="tarjeta-btn flex-1 py-2.5 rounded-xl border-2 border-[var(--border)] flex items-center justify-center gap-2 font-bold text-sm transition-all hover:border-[var(--primary)] text-[var(--text-secondary)]">
                        <iconify-icon icon="solar:card-bold-duotone" width="20"></iconify-icon>
                        Amex
                    </button>
                </div>
            </div>

            <!-- Numero tarjeta -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)] mb-1.5">
                    Número de tarjeta
                    <span class="ml-1 normal-case font-normal text-amber-500">(datos de prueba)</span>
                </label>
                <div class="relative">
                    <input type="text" id="pago-numero" maxlength="19"
                        placeholder="0000 0000 0000 0000"
                        class="input-solid pr-10"
                        oninput="formatearTarjeta(this)">
                    <iconify-icon id="pago-ico-tarjeta" icon="solar:card-bold-duotone" class="absolute right-3 top-1/2 -translate-y-1/2 text-[var(--text-tertiary)]" width="18"></iconify-icon>
                </div>
            </div>

            <!-- Nombre -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)] mb-1.5">Nombre en la tarjeta</label>
                <input type="text" id="pago-nombre" placeholder="Ej: JUAN PEREZ" maxlength="60"
                    class="input-solid" oninput="this.value=this.value.toUpperCase()">
            </div>

            <!-- Fila exp + CVV -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)] mb-1.5">Vence</label>
                    <input type="text" id="pago-vence" placeholder="MM/AA" maxlength="5"
                        class="input-solid" oninput="formatearVence(this)">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)] mb-1.5">CVV</label>
                    <input type="text" id="pago-cvv" placeholder="123" maxlength="4"
                        class="input-solid" oninput="this.value=this.value.replace(/\D/g,'')"
                        onfocus="document.getElementById('pago-flip').classList.add('flipped')" 
                        onblur="document.getElementById('pago-flip').classList.remove('flipped')">
                </div>
            </div>

            <!-- Tarjeta visual animada -->
            <div id="pago-flip" class="pago-card-wrap mx-auto" style="perspective:1000px;width:100%;max-width:320px;height:180px;position:relative;">
                <div class="pago-card-inner" style="position:relative;width:100%;height:100%;transform-style:preserve-3d;transition:transform .5s;">
                    <!-- Frente -->
                    <div class="pago-card-front" style="position:absolute;width:100%;height:100%;backface-visibility:hidden;background:linear-gradient(135deg,#1e0f07,#F47E24);border-radius:16px;padding:20px;color:#fff;display:flex;flex-direction:column;justify-content:space-between;box-shadow:0 8px 32px rgba(244,126,36,.25);">
                        <div class="flex justify-between items-start">
                            <iconify-icon icon="solar:cup-hot-bold-duotone" width="28" class="opacity-80"></iconify-icon>
                            <span id="card-tipo-ico" style="font-size:.7rem;font-weight:700;opacity:.8;letter-spacing:.1em">TARJETA</span>
                        </div>
                        <div>
                            <div id="card-num-display" style="font-size:1.1rem;font-weight:700;letter-spacing:.18em;margin-bottom:10px;font-family:monospace;">•••• •••• •••• ••••</div>
                            <div class="flex justify-between" style="font-size:.72rem;opacity:.8;">
                                <div>
                                    <div style="font-size:.55rem;opacity:.7;margin-bottom:2px">NOMBRE</div>
                                    <div id="card-nombre-display" style="font-weight:600;letter-spacing:.05em">TU NOMBRE</div>
                                </div>
                                <div style="text-align:right">
                                    <div style="font-size:.55rem;opacity:.7;margin-bottom:2px">VENCE</div>
                                    <div id="card-vence-display" style="font-weight:600">MM/AA</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Reverso -->
                    <div class="pago-card-back" style="position:absolute;width:100%;height:100%;backface-visibility:hidden;background:linear-gradient(135deg,#3d1e0b,#1e0f07);border-radius:16px;transform:rotateY(180deg);overflow:hidden;box-shadow:0 8px 32px rgba(30,15,7,.4);">
                        <div style="background:#000;height:44px;margin-top:24px;"></div>
                        <div style="padding:12px 20px;">
                            <div style="font-size:.6rem;color:rgba(255,255,255,.5);margin-bottom:6px">CVV</div>
                            <div style="background:#fff;border-radius:4px;padding:8px 12px;font-family:monospace;font-size:.9rem;letter-spacing:.2em;color:#333;text-align:right;" id="card-cvv-display">•••</div>
                        </div>
                        <div style="text-align:center;margin-top:16px">
                            <span style="color:rgba(255,255,255,.3);font-size:.6rem;font-weight:700;letter-spacing:.1em">SOLO REPRESENTACIÓN — NO SE CAPTURAN DATOS</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones finales -->
            <div class="flex gap-3 pt-2">
                <button onclick="cerrarModalPago()" class="btn-outline flex-1 justify-center text-sm">Atrás</button>
                <button id="btn-pago-pagar" onclick="confirmarPedidoFinal()" class="btn-primary flex-1 justify-center">
                    <iconify-icon icon="solar:cart-check-bold-duotone" width="18"></iconify-icon>
                    Pagar y confirmar
                </button>
            </div>

            <!-- Íconos de seguridad -->
            <div class="flex items-center justify-center gap-4 pt-1 pb-1">
                <iconify-icon icon="solar:lock-password-bold-duotone" class="text-[var(--success)]" width="18"></iconify-icon>
                <span class="text-[.68rem] text-[var(--text-tertiary)] font-semibold">Pago seguro simulado · Solo representación</span>
                <iconify-icon icon="solar:shield-check-bold-duotone" class="text-[var(--success)]" width="18"></iconify-icon>
            </div>
        </div>
    </div>
</div>

<style>
.pago-card-wrap.flipped .pago-card-inner { transform: rotateY(180deg); }
</style>

<!-- ═══════════════ MODAL: FORM DIRECCIÓN ════════════════════════════ -->
<div id="modal-dir-form" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 hidden" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="cerrarFormDir()"></div>
    <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl border border-[var(--border-light)] overflow-hidden">
        <div class="bg-[var(--text-main)] px-6 py-4 flex items-center justify-between">
            <div class="text-white font-bold text-sm" id="form-dir-titulo">Nueva dirección</div>
            <button onclick="cerrarFormDir()" class="w-8 h-8 rounded-lg flex items-center justify-center text-white/40 hover:text-white hover:bg-white/10">
                <iconify-icon icon="solar:close-circle-bold-duotone" width="20"></iconify-icon>
            </button>
        </div>
        <form id="form-dir" class="p-6 space-y-4" onsubmit="submitDir(event)">
            <input type="hidden" id="fd-id" name="id_direccion" value="">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)] mb-1.5">Alias</label>
                <input type="text" id="fd-alias" name="alias" placeholder="Ej: Casa, Trabajo, Casa mamá…" maxlength="80"
                    class="input-solid" required>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)] mb-1.5">Dirección <span class="text-red-400">*</span></label>
                <input type="text" id="fd-direccion" name="direccion" placeholder="Calle, número, sector…" maxlength="250"
                    class="input-solid" required>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)] mb-1.5">Referencia <span class="text-[var(--text-tertiary)] font-normal normal-case tracking-normal">(opcional)</span></label>
                <input type="text" id="fd-referencia" name="referencia" placeholder="Ej: frente al parque, 2do piso…" maxlength="200"
                    class="input-solid">
            </div>
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" id="fd-favorita" name="es_favorita" value="1" class="w-4 h-4 accent-[#F47E24]">
                <span class="text-sm font-semibold text-[var(--text-main)]">Marcar como favorita</span>
            </label>
            <div id="form-dir-error" class="hidden text-red-500 text-xs font-semibold"></div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="cerrarFormDir()" class="btn-outline flex-1 justify-center">Cancelar</button>
                <button type="submit" id="btn-dir-guardar" class="btn-primary flex-1 justify-center">
                    <iconify-icon icon="solar:diskette-bold-duotone" width="17"></iconify-icon>
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
/* ── NAV ──────────────────────────────────────────────── */
const VIEWS  = ['cuenta','carrito','pedidos','direcciones'];
const TITLES = { cuenta:'Mi perfil', carrito:'Mi carrito', pedidos:'Mis pedidos', direcciones:'Mis direcciones' };

function switchView(v) {
    VIEWS.forEach(id => {
        document.getElementById('view-'+id)?.classList.remove('active');
        document.getElementById('nav-'+id)?.classList.remove('active');
    });
    document.getElementById('view-'+v)?.classList.add('active');
    document.getElementById('nav-'+v)?.classList.add('active');
    document.getElementById('header-title').textContent = TITLES[v] || v;
    document.getElementById('main-scroll').scrollTop = 0;
    if (window.innerWidth < 768) toggleSidebar();
    window.location.hash = v;
}

/* ── SIDEBAR MOBILE ──────────────────────────────────── */
function toggleSidebar() {
    const sb = document.getElementById('sidebar');
    const ov = document.getElementById('mobile-overlay');
    const closed = sb.classList.contains('-translate-x-full');
    sb.classList.toggle('-translate-x-full', !closed);
    ov.classList.toggle('hidden', !closed);
}

/* ── TOAST ────────────────────────────────────────────── */
let _tt;
function toast(msg, tipo) {
    clearTimeout(_tt);
    const el = document.getElementById('toast-user');
    document.getElementById('toast-u-msg').textContent = msg;
    document.getElementById('toast-u-icon').setAttribute('icon',
        tipo==='error' ? 'solar:close-circle-bold-duotone' : 'solar:check-circle-bold-duotone');
    document.getElementById('toast-u-icon').style.color = tipo==='error' ? 'var(--danger)' : 'var(--primary)';
    el.classList.remove('hidden');
    _tt = setTimeout(() => el.classList.add('hidden'), 2800);
}

/* ── CAMBIAR CANTIDAD CARRITO ────────────────────────── */
function cambiarCantidad(id, nuevaCant) {
    if (nuevaCant < 1) { eliminarItem(id); return; }
    fetch('./backend/carrito/actualizar_cantidad.php', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`id_carrito=${id}&cantidad=${nuevaCant}`
    }).then(r=>r.json()).then(d=>{
        if (d.ok) {
            document.getElementById('qty-'+id).textContent = nuevaCant;
            toast('Cantidad actualizada');
            setTimeout(()=>location.reload(), 700);
        } else toast(d.error||'Error','error');
    }).catch(()=>toast('Error de conexión al actualizar','error'));
}

/* ── ELIMINAR ITEM CARRITO ───────────────────────────── */
function eliminarItem(id) {
    fetch('./backend/carrito/eliminar_item.php', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`id_carrito=${id}`
    }).then(r=>r.json()).then(d=>{
        if (d.ok) {
            document.getElementById('cart-row-'+id)?.remove();
            toast('Producto eliminado del carrito');
            setTimeout(()=>location.reload(), 700);
        } else toast(d.error||'Error','error');
    }).catch(()=>toast('Error de conexión al eliminar','error'));
}

/* ══════════════════════════════════════════════════
   CHECKOUT CON SELECCIÓN DE DIRECCIÓN
══════════════════════════════════════════════════ */
let selectedDirId = null;
let dirsCache     = [];

function realizarPedido() {
    // Abrir modal de checkout y cargar direcciones
    const totalEl = document.getElementById('total-carrito');
    document.getElementById('co-total').textContent = totalEl ? totalEl.textContent : '';
    selectedDirId = null;

    // Cargar direcciones via AJAX
    fetch('./backend/direcciones/listar_direcciones.php')
    .then(r=>r.json())
    .then(d=>{
        dirsCache = d.direcciones || [];
        renderCoDir(dirsCache);
        document.getElementById('modal-checkout').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    })
    .catch(()=>{
        // Si falla (tabla no existe), abrir modal igual con lista vacía
        dirsCache = [];
        renderCoDir([]);
        document.getElementById('modal-checkout').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    });
}

function renderCoDir(dirs) {
    const cont  = document.getElementById('co-dirs');
    const empty = document.getElementById('co-no-dirs');
    cont.innerHTML = '';

    if (!dirs || dirs.length === 0) {
        empty.classList.remove('hidden');
        recheckCoForm();
        return;
    }
    empty.classList.add('hidden');

    dirs.forEach(dir => {
        const chip = document.createElement('div');
        chip.className = 'co-dir-chip flex items-start gap-3 p-3 rounded-xl border-2 border-[var(--border)] cursor-pointer transition-all hover:border-[var(--primary)] hover:bg-[var(--primary-light)]';
        chip.dataset.id  = dir.id_direccion;
        chip.innerHTML = `
            <div class="w-8 h-8 rounded-lg bg-[var(--primary-light)] text-[var(--primary)] flex items-center justify-center flex-shrink-0 mt-0.5">
                <iconify-icon icon="solar:map-point-bold-duotone" width="16"></iconify-icon>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-[var(--text-main)] text-sm">${escHtml(dir.alias)}</span>
                    ${dir.es_favorita ? '<span style="font-size:.6rem;background:var(--warning-light);color:var(--warning);padding:2px 7px;border-radius:9999px;font-weight:700">★ Favorita</span>' : ''}
                </div>
                <div class="text-[var(--text-secondary)] text-xs mt-0.5 leading-snug">${escHtml(dir.direccion)}</div>
                ${dir.referencia ? `<div class="text-[var(--text-tertiary)] text-[.68rem] italic">${escHtml(dir.referencia)}</div>` : ''}
            </div>
            <div class="co-check w-5 h-5 rounded-full border-2 border-[var(--border)] flex items-center justify-center flex-shrink-0 mt-1"></div>
        `;
        chip.addEventListener('click', () => {
            document.querySelectorAll('.co-dir-chip').forEach(c => {
                c.classList.remove('co-dir-selected');
                c.classList.remove('border-[var(--primary)]', 'bg-[var(--primary-light)]');
                c.style.borderColor = '';
                c.style.backgroundColor = '';
                c.querySelector('.co-check').innerHTML = '';
                c.querySelector('.co-check').style.borderColor = '';
            });
            chip.style.borderColor = 'var(--primary)';
            chip.style.backgroundColor = 'var(--primary-light)';
            chip.querySelector('.co-check').innerHTML = '<iconify-icon icon="solar:check-circle-bold-duotone" style="color:var(--primary)" width="16"></iconify-icon>';
            chip.querySelector('.co-check').style.borderColor = 'var(--primary)';
            chip.classList.add('co-dir-selected');
            selectedDirId = dir.id_direccion;
            // Limpiar campo manual al elegir guardada
            document.getElementById('co-dir-manual').value = '';
            recheckCoForm();
        });

        // Auto-seleccionar favorita
        if (dir.es_favorita && selectedDirId === null) {
            setTimeout(() => chip.click(), 50);
        }

        cont.appendChild(chip);
    });
    recheckCoForm();
}

function recheckCoForm() {
    const manual = document.getElementById('co-dir-manual').value.trim();
    const ok     = selectedDirId !== null || manual.length > 0;
    const btn    = document.getElementById('btn-co-confirmar');
    btn.disabled = !ok;
    btn.style.opacity  = ok ? '' : '.4';
    btn.style.cursor   = ok ? '' : 'not-allowed';
}

function cerrarModalCheckout() {
    document.getElementById('modal-checkout').classList.add('hidden');
    document.body.style.overflow = '';
}

/* ══════════════════════════════════════════════════
   MODAL PAGO (REPRESENTACIÓN)
══════════════════════════════════════════════════ */
function abrirModalPago() {
    // Copiar total al modal de pago
    const totalTxt = document.getElementById('co-total').textContent;
    document.getElementById('pago-total').textContent = totalTxt;
    // Cerrar checkout y abrir pago
    document.getElementById('modal-checkout').classList.add('hidden');
    document.getElementById('modal-pago').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    // Limpiar campos
    ['pago-numero','pago-nombre','pago-vence','pago-cvv'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    actualizarTarjetaVisual();
    document.querySelectorAll('.tarjeta-btn').forEach(b => b.style.borderColor = '');
}

function cerrarModalPago() {
    document.getElementById('modal-pago').classList.add('hidden');
    // Volver al checkout
    document.getElementById('modal-checkout').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function seleccionarTarjeta(btn, tipo) {
    document.querySelectorAll('.tarjeta-btn').forEach(b => b.style.borderColor = '');
    btn.style.borderColor = 'var(--primary)';
    document.getElementById('card-tipo-ico').textContent = tipo.toUpperCase();
    const icoMap = { visa:'logos:visa', mastercard:'logos:mastercard', amex:'solar:card-bold-duotone' };
    document.getElementById('pago-ico-tarjeta').setAttribute('icon', icoMap[tipo] || 'solar:card-bold-duotone');
}

function formatearTarjeta(input) {
    let v = input.value.replace(/\D/g,'').substring(0,16);
    let parts = [];
    for (let i=0; i<v.length; i+=4) parts.push(v.substring(i,i+4));
    input.value = parts.join(' ');
    actualizarTarjetaVisual();
}

function formatearVence(input) {
    let v = input.value.replace(/\D/g,'');
    if (v.length >= 2) v = v.substring(0,2) + '/' + v.substring(2,4);
    input.value = v;
    actualizarTarjetaVisual();
}

function actualizarTarjetaVisual() {
    const num    = document.getElementById('pago-numero')?.value  || '';
    const nombre = document.getElementById('pago-nombre')?.value  || '';
    const vence  = document.getElementById('pago-vence')?.value   || '';
    const cvv    = document.getElementById('pago-cvv')?.value     || '';

    const numFmt = num ? num.padEnd(19,' ').replace(/ /g,'\u2002') : '•••• •••• •••• ••••';
    document.getElementById('card-num-display').textContent    = numFmt.substring(0,19);
    document.getElementById('card-nombre-display').textContent = nombre.toUpperCase() || 'TU NOMBRE';
    document.getElementById('card-vence-display').textContent  = vence || 'MM/AA';
    document.getElementById('card-cvv-display').textContent    = cvv ? cvv.replace(/./g,'•') : '•••';
}

// Live update tarjeta visual
['pago-numero','pago-nombre','pago-vence','pago-cvv'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', actualizarTarjetaVisual);
});

function confirmarPedidoFinal() {
    const btn = document.getElementById('btn-pago-pagar');
    btn.disabled = true;
    btn.innerHTML = '<iconify-icon icon="solar:refresh-circle-bold-duotone" width="18" class="animate-spin"></iconify-icon> Procesando…';

    // Determinar dirección final
    let direccion = '';
    if (selectedDirId !== null) {
        const found = dirsCache.find(d => d.id_direccion == selectedDirId);
        if (found) {
            direccion = found.alias + ': ' + found.direccion;
            if (found.referencia) direccion += ' (' + found.referencia + ')';
        }
    } else {
        const manual = document.getElementById('co-dir-manual')?.value.trim() || '';
        const ref    = document.getElementById('co-dir-ref')?.value.trim()    || '';
        direccion = manual + (ref ? ' – ' + ref : '');
    }

    const body = new URLSearchParams({ direccion_entrega: direccion });

    fetch('./backend/pedidos/crear_pedido.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(r => r.json())
    .then(d => {
        document.getElementById('modal-pago').classList.add('hidden');
        document.body.style.overflow = '';
        if (d.ok) {
            toast('✅ ¡Pedido #' + d.id_pedido + ' realizado con éxito!');
            setTimeout(() => { switchView('pedidos'); location.reload(); }, 1400);
        } else {
            toast(d.error || 'Error al realizar pedido', 'error');
            btn.disabled = false;
            btn.innerHTML = '<iconify-icon icon="solar:cart-check-bold-duotone" width="18"></iconify-icon> Pagar y confirmar';
        }
    })
    .catch(() => {
        document.getElementById('modal-pago').classList.add('hidden');
        document.body.style.overflow = '';
        toast('Error de conexión al procesar el pedido', 'error');
        btn.disabled = false;
        btn.innerHTML = '<iconify-icon icon="solar:cart-check-bold-duotone" width="18"></iconify-icon> Pagar y confirmar';
    });
}

// Actualizar botón confirmar cuando se escribe manualmente
document.getElementById('co-dir-manual')?.addEventListener('input', recheckCoForm);

/* ── Escapar HTML ─────────────────────────────── */
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ══════════════════════════════════════════════════
   GESTIÓN DE DIRECCIONES (Vista Mis Direcciones)
══════════════════════════════════════════════════ */
function abrirFormDir(dir) {
    document.getElementById('form-dir-titulo').textContent = dir ? 'Editar dirección' : 'Nueva dirección';
    document.getElementById('fd-id').value         = dir?.id_direccion || '';
    document.getElementById('fd-alias').value      = dir?.alias        || '';
    document.getElementById('fd-direccion').value  = dir?.direccion    || '';
    document.getElementById('fd-referencia').value = dir?.referencia   || '';
    document.getElementById('fd-favorita').checked = dir?.es_favorita  == 1;
    document.getElementById('form-dir-error').classList.add('hidden');
    document.getElementById('modal-dir-form').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('fd-alias').focus(), 100);
}

function editarDir(dir) { abrirFormDir(dir); }

function cerrarFormDir() {
    document.getElementById('modal-dir-form').classList.add('hidden');
    document.body.style.overflow = '';
}

function submitDir(e) {
    e.preventDefault();
    const errEl = document.getElementById('form-dir-error');
    errEl.classList.add('hidden');
    const btn = document.getElementById('btn-dir-guardar');
    btn.disabled = true;
    btn.innerHTML = '<iconify-icon icon="solar:refresh-circle-bold-duotone" width="16"></iconify-icon> Guardando…';

    const data = new URLSearchParams(new FormData(document.getElementById('form-dir')));
    // FormData con checkbox: si no está marcado no lo envía, forzar 0
    if (!document.getElementById('fd-favorita').checked) data.set('es_favorita','0');
    else data.set('es_favorita','1');

    fetch('./backend/direcciones/guardar_direccion.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: data.toString()
    })
    .then(r=>r.json())
    .then(d=>{
        btn.disabled = false;
        btn.innerHTML = '<iconify-icon icon="solar:diskette-bold-duotone" width="17"></iconify-icon> Guardar';
        if (d.ok) {
            cerrarFormDir();
            toast('Dirección guardada');
            setTimeout(()=>location.reload(), 700);
        } else {
            errEl.textContent = d.error || 'Error al guardar';
            errEl.classList.remove('hidden');
        }
    })
    .catch(()=>{
        btn.disabled = false;
        btn.innerHTML = '<iconify-icon icon="solar:diskette-bold-duotone" width="17"></iconify-icon> Guardar';
        errEl.textContent = 'Error de conexión';
        errEl.classList.remove('hidden');
    });
}

function eliminarDir(id) {
    if (!confirm('¿Eliminar esta dirección?')) return;
    fetch('./backend/direcciones/eliminar_direccion.php', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`id_direccion=${id}`
    })
    .then(r=>r.json())
    .then(d=>{
        if (d.ok) {
            document.getElementById('dir-card-'+id)?.remove();
            toast('Dirección eliminada');
            // Si no quedan tarjetas, mostrar estado vacío
            const lista = document.getElementById('dirs-lista');
            if (lista && lista.querySelectorAll('[id^="dir-card-"]').length === 0) {
                setTimeout(()=>location.reload(),300);
            }
        } else toast(d.error||'Error','error');
    })
    .catch(()=>toast('Error de conexión','error'));
}

/* ── VER DETALLE PEDIDO ──────────────────────────────── */
function verDetallePedido(id) {
    const det = document.getElementById('ped-det-'+id);
    const ico = document.getElementById('det-ico-'+id);
    if (!det.classList.contains('hidden')) {
        det.classList.add('hidden');
        ico.setAttribute('icon','solar:alt-arrow-down-bold-duotone');
        return;
    }
    det.classList.remove('hidden');
    ico.setAttribute('icon','solar:alt-arrow-up-bold-duotone');
    if (det.dataset.cargado) return;
    det.dataset.cargado = '1';
    fetch('./backend/pedidos/detalle_pedido.php?id_pedido='+id)
    .then(r=>r.json()).then(items=>{
        if (items.error) { det.querySelector('td').innerHTML=`<p class="text-red-400 text-xs">${items.error}</p>`; return; }
        let h='<div class="overflow-x-auto"><table class="w-full text-xs border-collapse">';
        h+='<thead><tr class="bg-[var(--bg)] border-b border-[var(--border)]"><th class="text-left px-4 py-2.5 text-[var(--text-tertiary)] font-semibold uppercase tracking-wider">Producto</th><th class="px-4 py-2.5 text-center text-[var(--text-tertiary)] font-semibold uppercase tracking-wider">Cant.</th><th class="px-4 py-2.5 text-right text-[var(--primary)] font-semibold uppercase tracking-wider">Subtotal</th></tr></thead><tbody>';
        items.forEach(i=>{
            h+=`<tr class="border-b border-[var(--border-light)] last:border-0">
                <td class="px-4 py-2.5 font-semibold text-[var(--text-main)]">${escHtml(i.nombre_producto)}</td>
                <td class="px-4 py-2.5 text-center text-[var(--text-secondary)]">${i.cantidad}</td>
                <td class="px-4 py-2.5 text-right font-bold text-[var(--primary)]">RD$${(i.cantidad*i.precio_unitario).toFixed(2)}</td>
            </tr>`;
        });
        h+='</tbody></table></div>';
        det.querySelector('td').innerHTML=h;
    }).catch(()=>{ det.querySelector('td').innerHTML='<p class="text-red-400 text-xs">Error al cargar detalle</p>'; });
}

/* ── HASH ROUTING ───────────────────────────────────── */
const h = location.hash.replace('#','');
if (VIEWS.includes(h)) switchView(h);
</script>

</body>
</html>

