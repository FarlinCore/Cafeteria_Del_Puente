<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ./login.html'); exit;
}

$nombre_admin  = $_SESSION['nombre']  ?? 'Admin';
require_once './backend/config/conexion.php';

// ── Stats generales ────────────────────────────────────────────────
$stat_usuarios  = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'usuario'")->fetchColumn();
$stat_pedidos   = $pdo->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();
$stat_ingresos  = $pdo->query("SELECT COALESCE(SUM(total_pedido),0) FROM pedidos WHERE estado_pedido != 'cancelado'")->fetchColumn();
$pedidos_hoy    = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE DATE(fecha_pedido) = CURDATE()")->fetchColumn();
$ingresos_hoy   = $pdo->query("SELECT COALESCE(SUM(total_pedido),0) FROM pedidos WHERE DATE(fecha_pedido) = CURDATE() AND estado_pedido != 'cancelado'")->fetchColumn();

$estados_count = [];
foreach (['pendiente','en_proceso','listo','entregado','cancelado'] as $e) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE estado_pedido = ?");
    $s->execute([$e]); $estados_count[$e] = (int)$s->fetchColumn();
}

// ── Pedidos completos ──────────────────────────────────────────────
$todos_pedidos = $pdo->query("
    SELECT pe.id_pedido, pe.fecha_pedido, pe.total_pedido, pe.estado_pedido, pe.notas_pedido,
           u.nombre, u.apellido, u.usuario,
           COUNT(dp.id_detalle) AS num_items
    FROM pedidos pe
    JOIN usuarios u ON pe.id_usuario = u.id_usuario
    LEFT JOIN detalle_pedido dp ON pe.id_pedido = dp.id_pedido
    GROUP BY pe.id_pedido ORDER BY pe.fecha_pedido DESC
")->fetchAll();

// ── Usuarios ───────────────────────────────────────────────────────
$todos_usuarios = $pdo->query("
    SELECT u.*, (SELECT COUNT(*) FROM pedidos WHERE pedidos.id_usuario = u.id_usuario) AS total_pedidos
    FROM usuarios u ORDER BY fecha_creacion DESC
")->fetchAll();

// ── Todos los productos (con stock) ───────────────────────────────
try {
    $todos_productos = $pdo->query("
        SELECT p.*,
               COALESCE(p.tipo_stock,'stock') AS tipo_stock,
               COALESCE(p.stock,0) AS stock_num,
               COALESCE(p.descripcion,'') AS descripcion
        FROM productos p
        ORDER BY p.categoria_producto, p.nombre_producto
    ")->fetchAll();
} catch (PDOException $e) {
    $todos_productos = $pdo->query("
        SELECT p.*,
               'stock' AS tipo_stock,
               0 AS stock_num,
               '' AS descripcion
        FROM productos p
        ORDER BY p.categoria_producto, p.nombre_producto
    ")->fetchAll();
}


// ── Inventario stats ──────────────────────────────────────────────
$inv_total     = count(array_filter($todos_productos, fn($p) => $p['activo']));
$inv_agotados  = count(array_filter($todos_productos, fn($p) => $p['activo'] && $p['tipo_stock']==='stock' && (int)$p['stock_num']===0));
$inv_bajo      = count(array_filter($todos_productos, fn($p) => $p['activo'] && $p['tipo_stock']==='stock' && (int)$p['stock_num'] > 0 && (int)$p['stock_num'] < 6));
$inv_bebidas   = count(array_filter($todos_productos, fn($p) => $p['activo'] && $p['tipo_stock']==='ilimitado'));

// ── Economía ──────────────────────────────────────────────────────
$ventas_cat = [];
try {
    $ventas_cat = $pdo->query("
        SELECT p.categoria_producto, COALESCE(SUM(dp.precio_unitario * dp.cantidad),0) AS ingresos, SUM(dp.cantidad) AS unidades
        FROM detalle_pedido dp
        JOIN productos p ON dp.id_producto = p.id_producto
        JOIN pedidos pe ON dp.id_pedido = pe.id_pedido
        WHERE pe.estado_pedido != 'cancelado'
        GROUP BY p.categoria_producto ORDER BY ingresos DESC
    ")->fetchAll();
} catch(Exception $e) {}

$top_productos = [];
try {
    $top_productos = $pdo->query("
        SELECT p.nombre_producto, p.categoria_producto,
               SUM(dp.cantidad) AS total_vendido,
               SUM(dp.precio_unitario * dp.cantidad) AS ingresos
        FROM detalle_pedido dp
        JOIN productos p ON dp.id_producto = p.id_producto
        JOIN pedidos pe ON dp.id_pedido = pe.id_pedido
        WHERE pe.estado_pedido != 'cancelado'
        GROUP BY dp.id_producto ORDER BY total_vendido DESC LIMIT 6
    ")->fetchAll();
} catch(Exception $e) {}

$categorias_disponibles = ['salados','sandwiches','bebidas','postres','otros'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin — Cafeteria del Puente</title>
    <meta name="description" content="Panel de administracion completo de Cafeteria del Puente.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.iconify.design/iconify-icon/1.0.8/iconify-icon.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary:#F47E24; --primary-dark:#e06b15; --primary-light:rgba(244,126,36,.10);
            --sidebar-bg:#1e0f07; --sidebar-text:rgba(255,255,255,.62); --sidebar-hover:rgba(244,126,36,.14);
            --bg:#F8F5F1; --surface:#fff;
            --text-main:#1e0f07; --text-secondary:#4a3728; --text-tertiary:#7a6055;
            --border:#E8DDD6; --border-light:#F1EAE4;
            --success:#22c55e; --success-light:rgba(34,197,94,.12);
            --danger:#ef4444;  --danger-light:rgba(239,68,68,.12);
            --warning:#d97706; --warning-light:rgba(217,119,6,.14);
            --info:#0ea5e9;    --info-light:rgba(14,165,233,.12);
            --purple:#8b5cf6;  --purple-light:rgba(139,92,246,.12);
            --shadow-sm:0 2px 8px rgba(30,15,7,.05),0 4px 16px rgba(30,15,7,.06);
            --shadow-md:0 6px 20px rgba(30,15,7,.09),0 16px 32px rgba(30,15,7,.11);
        }
        *{box-sizing:border-box;}
        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text-secondary);}

        #sidebar{background:var(--sidebar-bg);transition:transform .3s cubic-bezier(.4,0,.2,1);}
        .nav-item{display:flex;align-items:center;gap:12px;padding:11px 16px;border-radius:10px;margin-bottom:2px;color:var(--sidebar-text);font-weight:600;font-size:.925rem;transition:all .2s;cursor:pointer;}
        .nav-item:hover{background:var(--sidebar-hover);color:var(--primary);}
        .nav-item.active{background:var(--primary);color:#fff;box-shadow:0 4px 14px rgba(244,126,36,.35);}
        .nav-item iconify-icon{font-size:20px;flex-shrink:0;}
        .nav-section{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.28);padding:0 16px;margin:20px 0 6px;}

        .card{background:var(--surface);border-radius:16px;box-shadow:var(--shadow-sm);border:1px solid var(--border-light);transition:box-shadow .2s;}
        .card:hover{box-shadow:var(--shadow-md);}
        .stat-card{background:var(--surface);border-left:4px solid var(--primary);border-radius:12px;padding:18px 20px;box-shadow:var(--shadow-sm);border-right:1px solid var(--border-light);border-top:1px solid var(--border-light);border-bottom:1px solid var(--border-light);transition:transform .2s;}
        .stat-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-md);}

        .btn-primary{background:var(--primary);color:#fff;padding:9px 20px;border-radius:8px;font-weight:600;font-size:.875rem;transition:all .2s;border:none;display:inline-flex;align-items:center;gap:8px;cursor:pointer;text-decoration:none;}
        .btn-primary:hover{background:var(--primary-dark);transform:translateY(-1px);}
        .btn-outline{border:1px solid var(--border);color:var(--text-main);background:var(--surface);padding:8px 18px;border-radius:8px;font-weight:600;font-size:.875rem;transition:all .2s;display:inline-flex;align-items:center;gap:8px;cursor:pointer;text-decoration:none;}
        .btn-outline:hover{background:var(--bg);}
        .btn-icon{width:34px;height:34px;border-radius:8px;display:inline-flex;justify-content:center;align-items:center;transition:all .2s;color:var(--text-tertiary);background:transparent;cursor:pointer;border:none;}
        .btn-icon:hover{background:var(--primary-light);color:var(--primary);}

        .badge{padding:4px 10px;border-radius:6px;font-size:.72rem;font-weight:700;display:inline-flex;align-items:center;gap:4px;}
        .badge-pendiente{background:var(--warning-light);color:var(--warning);}
        .badge-en_proceso{background:var(--info-light);color:var(--info);}
        .badge-listo{background:var(--purple-light);color:var(--purple);}
        .badge-entregado{background:var(--success-light);color:var(--success);}
        .badge-cancelado{background:var(--danger-light);color:var(--danger);}
        .badge-activo{background:var(--success-light);color:var(--success);}
        .badge-inactivo{background:var(--border-light);color:var(--text-tertiary);}
        .badge-admin{background:var(--info-light);color:var(--info);}
        .badge-usuario{background:var(--primary-light);color:var(--primary);}
        .badge-agotado{background:var(--danger-light);color:var(--danger);}
        .badge-bajo{background:var(--warning-light);color:var(--warning);}
        .badge-ok{background:var(--success-light);color:var(--success);}
        .badge-ilimitado{background:var(--info-light);color:var(--info);}

        .table-custom{width:100%;border-collapse:separate;border-spacing:0;}
        .table-custom th{background:var(--bg);color:var(--text-tertiary);font-weight:700;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;padding:12px 16px;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap;}
        .table-custom td{padding:13px 16px;border-bottom:1px solid var(--border-light);color:var(--text-secondary);font-size:.875rem;vertical-align:middle;transition:background .15s;}
        .table-custom tbody tr:hover td{background:var(--primary-light);}
        .table-custom tbody tr:last-child td{border-bottom:none;}

        .input-solid{background:var(--bg);border:1px solid var(--border);color:var(--text-main);border-radius:8px;padding:9px 14px;font-size:.875rem;font-weight:500;transition:all .2s;font-family:inherit;width:100%;}
        .input-solid:focus{outline:none;border-color:var(--primary);background:var(--surface);box-shadow:0 0 0 3px var(--primary-light);}

        .select-estado{border:none;background:transparent;font-size:.72rem;font-weight:700;cursor:pointer;outline:none;padding:4px 6px;border-radius:6px;font-family:inherit;}

        .content-area{display:none;animation:fadeUp .35s ease both;}
        .content-area.active{display:block;}
        @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

        /* Stock indicator */
        .stock-bar-wrap{height:4px;background:var(--border);border-radius:2px;overflow:hidden;min-width:60px;}
        .stock-bar{height:4px;border-radius:2px;transition:width .5s;}

        /* Product form overlay */
        #form-producto-wrap{transition:all .3s ease;}

        ::-webkit-scrollbar{width:5px;height:5px;}
        ::-webkit-scrollbar-track{background:transparent;}
        ::-webkit-scrollbar-thumb{background:var(--border);border-radius:10px;}
        ::-webkit-scrollbar-thumb:hover{background:var(--primary);}
    </style>
</head>
<body class="overflow-hidden flex h-screen">

<!-- ══════════ SIDEBAR ══════════════════════════════════════════════ -->
<aside id="sidebar" class="w-[260px] flex flex-col h-full absolute z-30 md:relative transform -translate-x-full md:translate-x-0 shadow-2xl md:shadow-none flex-shrink-0">

    <div class="h-[76px] flex items-center px-5 border-b border-white/8 flex-shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-[#F47E24] flex items-center justify-center shadow-lg shadow-[#F47E24]/40">
                <iconify-icon icon="solar:cup-hot-bold-duotone" width="22" class="text-white"></iconify-icon>
            </div>
            <div class="leading-tight">
                <div class="text-white font-bold text-[.95rem] tracking-tight">Del Puente</div>
                <div class="text-[.62rem] font-bold text-[#F47E24] uppercase tracking-widest">Admin Portal</div>
            </div>
        </div>
        <button class="md:hidden ml-auto btn-icon text-white/50 hover:text-white hover:bg-white/10" onclick="toggleSidebar()">
            <iconify-icon icon="solar:close-circle-bold-duotone" width="22"></iconify-icon>
        </button>
    </div>

    <div class="flex-1 overflow-y-auto py-5 px-3">
        <div class="nav-section">Principal</div>
        <a onclick="switchView('dashboard')" class="nav-item active" id="nav-dashboard">
            <iconify-icon icon="solar:home-angle-bold-duotone"></iconify-icon><span>Dashboard</span>
        </a>
        <a onclick="switchView('pedidos')" class="nav-item" id="nav-pedidos">
            <iconify-icon icon="solar:clipboard-list-bold-duotone"></iconify-icon><span>Pedidos</span>
            <span class="ml-auto bg-[#F47E24] text-white text-[10px] font-bold w-5 h-5 rounded-full flex items-center justify-center leading-none flex-shrink-0"><?= min($stat_pedidos,99) ?></span>
        </a>
        <a onclick="switchView('usuarios')" class="nav-item" id="nav-usuarios">
            <iconify-icon icon="solar:users-group-bold-duotone"></iconify-icon><span>Usuarios</span>
        </a>

        <div class="nav-section">Inventario y productos</div>
        <a onclick="switchView('inventario')" class="nav-item" id="nav-inventario">
            <iconify-icon icon="solar:box-bold-duotone"></iconify-icon><span>Inventario</span>
            <?php if ($inv_agotados > 0): ?>
            <span class="ml-auto bg-red-500 text-white text-[10px] font-bold w-5 h-5 rounded-full flex items-center justify-center leading-none flex-shrink-0"><?= $inv_agotados ?></span>
            <?php endif; ?>
        </a>
        <a onclick="switchView('productos')" class="nav-item" id="nav-productos">
            <iconify-icon icon="solar:chef-hat-heart-bold-duotone"></iconify-icon><span>Productos</span>
        </a>
        <a onclick="switchView('economia')" class="nav-item" id="nav-economia">
            <iconify-icon icon="solar:chart-bold-duotone"></iconify-icon><span>Economia</span>
        </a>

        <div class="nav-section">Sitio web</div>
        <a href="./menu.php" class="nav-item opacity-70 hover:opacity-100">
            <iconify-icon icon="solar:book-2-bold-duotone"></iconify-icon><span>Ver menu</span>
        </a>
        <a href="./index.html" class="nav-item opacity-70 hover:opacity-100">
            <iconify-icon icon="solar:global-bold-duotone"></iconify-icon><span>Ver sitio</span>
        </a>
        <a href="./backend/migration/configurar_bd.php" class="nav-item opacity-50 hover:opacity-80 text-yellow-400" target="_blank">
            <iconify-icon icon="solar:settings-bold-duotone"></iconify-icon><span>Migracion BD</span>
        </a>
    </div>

    <div class="p-4 border-t border-white/8 flex-shrink-0">
        <div class="flex items-center gap-3 bg-white/6 rounded-xl p-3">
            <div class="w-10 h-10 rounded-full bg-[#F47E24]/20 border border-[#F47E24]/40 flex items-center justify-center text-[#F47E24] font-bold text-sm flex-shrink-0">
                <?= strtoupper(substr($nombre_admin, 0, 2)) ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-white text-sm font-bold truncate"><?= htmlspecialchars($nombre_admin) ?></div>
                <div class="text-[#F47E24] text-[.65rem] font-semibold uppercase tracking-wide">Administrador</div>
            </div>
            <a href="./backend/autenticacion/cerrar_sesion.php" class="w-8 h-8 rounded-lg flex items-center justify-center text-red-400/70 hover:text-red-400 hover:bg-red-400/10 transition-all flex-shrink-0" title="Cerrar sesion">
                <iconify-icon icon="solar:logout-bold-duotone" width="18"></iconify-icon>
            </a>
        </div>
    </div>
</aside>

<div id="mobile-overlay" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-20 hidden md:hidden" onclick="toggleSidebar()"></div>

<!-- ══════════ MAIN ══════════════════════════════════════════════════ -->
<main class="flex-1 flex flex-col min-w-0 bg-[var(--bg)]">

    <header class="h-[76px] bg-white border-b border-[var(--border-light)] flex items-center justify-between px-6 flex-shrink-0 shadow-sm z-10 sticky top-0">
        <div class="flex items-center gap-4">
            <button class="btn-icon md:hidden" onclick="toggleSidebar()">
                <iconify-icon icon="solar:hamburger-menu-bold-duotone" width="24"></iconify-icon>
            </button>
            <div>
                <h2 class="text-[1rem] font-bold text-[var(--text-main)] m-0 leading-tight" id="header-title">Dashboard</h2>
                <div class="text-[.7rem] font-semibold text-[var(--text-tertiary)] uppercase tracking-wider mt-0.5">Cafeteria del Puente</div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="./panel-usuario.php" class="hidden sm:flex items-center gap-1.5 btn-outline text-sm px-4 py-2 rounded-full">
                <iconify-icon icon="solar:user-circle-bold-duotone" width="18"></iconify-icon>Mi cuenta
            </a>
            <a href="./backend/autenticacion/cerrar_sesion.php" class="flex items-center gap-1.5 px-4 py-2 rounded-full border border-red-200 text-red-500 text-sm font-semibold hover:bg-red-50 transition-all">
                <iconify-icon icon="solar:logout-bold-duotone" width="16"></iconify-icon>
                <span class="hidden sm:inline">Salir</span>
            </a>
        </div>
    </header>

    <div class="flex-1 overflow-y-auto p-5 sm:p-7" id="main-scroll">

        <!-- ══ DASHBOARD ══════════════════════════════════════════════ -->
        <div id="view-dashboard" class="content-area active max-w-[1400px] mx-auto">
            <div class="mb-7 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-[var(--text-main)] m-0">Bienvenido, <?= htmlspecialchars($nombre_admin) ?> <iconify-icon icon="solar:hand-stars-bold-duotone" class="text-[var(--primary)] align-middle" width="28"></iconify-icon></h1>
                    <p class="text-[var(--text-tertiary)] text-sm font-medium mt-1">Resumen de operaciones en tiempo real.</p>
                </div>
                <a href="./menu.php" class="btn-primary shadow-md shadow-[#F47E24]/20">
                    <iconify-icon icon="solar:eye-bold-duotone" width="18"></iconify-icon>Ver menu
                </a>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-7">
                <?php
                $cards = [
                    ['label'=>'Pedidos',    'val'=>$stat_pedidos,  'sub'=>"$pedidos_hoy hoy",                 'icon'=>'solar:clipboard-list-bold-duotone',    'color'=>'var(--primary)',  'bg'=>'var(--primary-light)'],
                    ['label'=>'Usuarios',   'val'=>$stat_usuarios, 'sub'=>'Clientes registrados',             'icon'=>'solar:users-group-bold-duotone',       'color'=>'var(--info)',     'bg'=>'var(--info-light)'],
                    ['label'=>'Ingresos',   'val'=>'RD$'.number_format((float)$stat_ingresos,0,'.',','), 'sub'=>'Acumulado','icon'=>'solar:dollar-minimalistic-bold-duotone','color'=>'var(--success)', 'bg'=>'var(--success-light)'],
                    ['label'=>'Hoy',        'val'=>'RD$'.number_format((float)$ingresos_hoy,0,'.',','),  'sub'=>'Ingresos de hoy', 'icon'=>'solar:sun-bold-duotone',           'color'=>'var(--warning)', 'bg'=>'var(--warning-light)'],
                    ['label'=>'Pendientes', 'val'=>$estados_count['pendiente'], 'sub'=>'Por atender',        'icon'=>'solar:clock-circle-bold-duotone',      'color'=>'var(--purple)',   'bg'=>'var(--purple-light)'],
                    ['label'=>'Agotados',   'val'=>$inv_agotados,  'sub'=>'Productos sin stock',             'icon'=>'solar:danger-bold-duotone',            'color'=>$inv_agotados>0?'var(--danger)':'var(--success)', 'bg'=>$inv_agotados>0?'var(--danger-light)':'var(--success-light)'],
                ];
                foreach ($cards as $c): ?>
                <div class="stat-card" style="border-left-color:<?= $c['color'] ?>">
                    <div class="flex justify-between items-start mb-3">
                        <span class="text-[.68rem] font-700 uppercase tracking-wider text-[var(--text-tertiary)]"><?= $c['label'] ?></span>
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:<?= $c['bg'] ?>;color:<?= $c['color'] ?>">
                            <iconify-icon icon="<?= $c['icon'] ?>" width="17"></iconify-icon>
                        </div>
                    </div>
                    <div class="text-xl font-bold text-[var(--text-main)]"><?= $c['val'] ?></div>
                    <div class="text-xs text-[var(--text-tertiary)] mt-1 font-medium"><?= $c['sub'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <div class="card p-6 flex flex-col">
                    <h3 class="text-[var(--text-main)] font-bold text-[1rem] m-0 mb-1">Estado de pedidos</h3>
                    <p class="text-[var(--text-tertiary)] text-xs font-medium mb-5">Distribucion actual</p>
                    <div class="flex-1 flex items-center justify-center min-h-[160px]"><canvas id="chartEstados"></canvas></div>
                    <div class="mt-4 space-y-1.5">
                        <?php
                        $col_map = ['pendiente'=>'#d97706','en_proceso'=>'#0ea5e9','listo'=>'#8b5cf6','entregado'=>'#22c55e','cancelado'=>'#ef4444'];
                        $lbl_map = ['pendiente'=>'Pendiente','en_proceso'=>'En proceso','listo'=>'Listo','entregado'=>'Entregado','cancelado'=>'Cancelado'];
                        $total_p = max((int)$stat_pedidos,1);
                        foreach ($estados_count as $est=>$cnt): $pct=round(($cnt/$total_p)*100); ?>
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-sm" style="background:<?= $col_map[$est] ?>"></span><span class="text-[var(--text-secondary)] font-medium"><?= $lbl_map[$est] ?></span></div>
                            <span class="font-bold text-[var(--text-main)]"><?= $cnt ?> <span class="font-normal text-[var(--text-tertiary)]">(<?= $pct ?>%)</span></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card overflow-hidden lg:col-span-2">
                    <div class="px-6 py-4 border-b border-[var(--border-light)] flex items-center justify-between">
                        <h3 class="text-[var(--text-main)] font-bold text-[1rem] m-0">Pedidos recientes</h3>
                        <button onclick="switchView('pedidos')" class="text-xs text-[var(--primary)] font-bold hover:underline">Ver todos &rarr;</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table-custom">
                            <thead><tr><th>Pedido</th><th>Cliente</th><th class="text-center">Items</th><th class="text-center">Total</th><th class="text-center">Estado</th></tr></thead>
                            <tbody>
                            <?php foreach(array_slice($todos_pedidos,0,5) as $p): ?>
                            <tr>
                                <td><span class="font-bold text-[var(--text-main)]">#<?= $p['id_pedido'] ?></span><div class="text-[.7rem] text-[var(--text-tertiary)]"><?= date('d/m H:i', strtotime($p['fecha_pedido'])) ?></div></td>
                                <td><div class="flex items-center gap-2"><div class="w-7 h-7 rounded-full bg-[var(--primary-light)] text-[var(--primary)] flex items-center justify-center font-bold text-xs"><?= strtoupper(substr($p['nombre'],0,1)) ?></div><span class="text-xs font-semibold"><?= htmlspecialchars($p['nombre'].' '.$p['apellido']) ?></span></div></td>
                                <td class="text-center text-xs"><?= $p['num_items'] ?></td>
                                <td class="text-center font-bold text-xs">RD$<?= number_format((float)$p['total_pedido'],2) ?></td>
                                <td class="text-center"><span class="badge badge-<?= $p['estado_pedido'] ?>"><?= ucfirst(str_replace('_',' ',$p['estado_pedido'])) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ PEDIDOS ══════════════════════════════════════════════════ -->
        <div id="view-pedidos" class="content-area max-w-[1400px] mx-auto">
            <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-[var(--text-main)] m-0">Gestion de pedidos</h1>
                    <p class="text-[var(--text-tertiary)] text-sm font-medium mt-1">Cambia el estado directamente en la tabla.</p>
                </div>
                <select id="filtro-estado" onchange="filtrarPedidos()" class="input-solid w-auto rounded-xl py-2.5 text-sm font-semibold cursor-pointer">
                    <option value="">Todos los estados</option>
                    <option value="pendiente">Pendiente</option><option value="en_proceso">En proceso</option>
                    <option value="listo">Listo</option><option value="entregado">Entregado</option><option value="cancelado">Cancelado</option>
                </select>
            </div>
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table-custom" id="tabla-pedidos">
                        <thead><tr><th>#</th><th>Cliente</th><th class="text-center">Items</th><th class="text-center">Total</th><th class="text-center">Fecha</th><th class="text-center">Estado</th><th class="text-center">Ver</th></tr></thead>
                        <tbody>
                        <?php foreach($todos_pedidos as $p): ?>
                        <tr class="pedido-fila" data-estado="<?= $p['estado_pedido'] ?>" id="fila-p-<?= $p['id_pedido'] ?>">
                            <td><span class="font-bold text-[var(--text-main)]">#<?= $p['id_pedido'] ?></span></td>
                            <td><div class="flex items-center gap-2"><div class="w-8 h-8 rounded-full bg-[var(--primary-light)] text-[var(--primary)] flex items-center justify-center font-bold text-xs"><?= strtoupper(substr($p['nombre'],0,1)) ?></div><div><div class="font-bold text-[var(--text-main)] text-xs"><?= htmlspecialchars($p['nombre'].' '.$p['apellido']) ?></div><div class="text-[.7rem] text-[var(--text-tertiary)]">@<?= htmlspecialchars($p['usuario']) ?></div></div></div></td>
                            <td class="text-center text-xs"><?= $p['num_items'] ?></td>
                            <td class="text-center font-bold text-xs">RD$<?= number_format((float)$p['total_pedido'],2) ?></td>
                            <td class="text-center text-[.72rem] text-[var(--text-tertiary)]"><?= date('d/m/Y', strtotime($p['fecha_pedido'])) ?><br><?= date('H:i', strtotime($p['fecha_pedido'])) ?></td>
                            <td class="text-center">
                                <select onchange="cambiarEstadoPedido(<?= $p['id_pedido'] ?>, this)" class="badge badge-<?= $p['estado_pedido'] ?> select-estado" id="sel-<?= $p['id_pedido'] ?>">
                                    <option value="pendiente"  <?= $p['estado_pedido']==='pendiente' ?'selected':'' ?>>Pendiente</option>
                                    <option value="en_proceso" <?= $p['estado_pedido']==='en_proceso'?'selected':'' ?>>En proceso</option>
                                    <option value="listo"      <?= $p['estado_pedido']==='listo'     ?'selected':'' ?>>Listo</option>
                                    <option value="entregado"  <?= $p['estado_pedido']==='entregado' ?'selected':'' ?>>Entregado</option>
                                    <option value="cancelado"  <?= $p['estado_pedido']==='cancelado' ?'selected':'' ?>>Cancelado</option>
                                </select>
                            </td>
                            <td class="text-center"><button onclick="toggleDetalle(<?= $p['id_pedido'] ?>)" class="btn-icon border border-[var(--border)] hover:border-[var(--primary)] hover:bg-[var(--primary-light)]"><iconify-icon id="ico-<?= $p['id_pedido'] ?>" icon="solar:alt-arrow-down-bold-duotone" width="16"></iconify-icon></button></td>
                        </tr>
                        <tr id="det-<?= $p['id_pedido'] ?>" class="hidden bg-[var(--bg)]"><td colspan="7" class="px-8 py-3"><div class="text-xs text-[var(--text-tertiary)] italic">Cargando detalle...</div></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if(empty($todos_pedidos)): ?><div class="text-center py-20 text-[var(--text-tertiary)]"><iconify-icon icon="solar:clipboard-bold-duotone" width="48" class="opacity-20 mb-3 block mx-auto"></iconify-icon><p class="text-sm font-medium">No hay pedidos registrados</p></div><?php endif; ?>
            </div>
        </div>

        <!-- ══ USUARIOS ══════════════════════════════════════════════════ -->
        <div id="view-usuarios" class="content-area max-w-[1400px] mx-auto">
            <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div><h1 class="text-2xl font-bold text-[var(--text-main)] m-0">Usuarios registrados</h1><p class="text-[var(--text-tertiary)] text-sm font-medium mt-1">Gestiona el acceso y estado de cada cuenta.</p></div>
                <div class="relative"><iconify-icon icon="solar:magnifier-linear" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--text-tertiary)]" width="18"></iconify-icon><input type="text" id="buscar-usuario" oninput="filtrarUsuarios()" placeholder="Buscar..." class="input-solid pl-10 rounded-xl py-2.5 w-60 text-sm"></div>
            </div>
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table-custom" id="tabla-usuarios">
                        <thead><tr><th>Usuario</th><th>Correo</th><th class="text-center">Rol</th><th class="text-center">Pedidos</th><th class="text-center">Registro</th><th class="text-center">Estado</th><th class="text-center">Accion</th></tr></thead>
                        <tbody>
                        <?php foreach($todos_usuarios as $u): ?>
                        <tr class="usuario-fila" id="fila-u-<?= $u['id_usuario'] ?>">
                            <td><div class="flex items-center gap-2"><div class="w-8 h-8 rounded-full bg-[var(--primary-light)] text-[var(--primary)] flex items-center justify-center font-bold text-xs"><?= strtoupper(substr($u['nombre'],0,1)) ?></div><div><div class="font-bold text-[var(--text-main)] text-xs"><?= htmlspecialchars($u['nombre'].' '.$u['apellido']) ?></div><div class="text-[.7rem] text-[var(--text-tertiary)]">@<?= htmlspecialchars($u['usuario']) ?></div></div></div></td>
                            <td class="text-xs"><?= htmlspecialchars($u['correo']) ?></td>
                            <td class="text-center"><span class="badge badge-<?= $u['rol'] ?>"><?= ucfirst($u['rol']) ?></span></td>
                            <td class="text-center font-bold text-sm"><?= $u['total_pedidos'] ?></td>
                            <td class="text-center text-[.72rem] text-[var(--text-tertiary)]"><?= date('d/m/Y',strtotime($u['fecha_creacion'])) ?></td>
                            <td class="text-center"><span id="estado-u-<?= $u['id_usuario'] ?>" class="badge <?= ($u['activo']==='1'||$u['activo']==1)?'badge-activo':'badge-inactivo' ?>"><?= ($u['activo']==='1'||$u['activo']==1)?'Activo':'Inactivo' ?></span></td>
                            <td class="text-center"><?php if($u['id_usuario']!=$_SESSION['id_usuario']): ?><button id="btn-u-<?= $u['id_usuario'] ?>" onclick="toggleUsuario(<?= $u['id_usuario'] ?>,<?= ($u['activo']==='1'||$u['activo']==1)?'true':'false' ?>)" class="btn-icon <?= ($u['activo']==='1'||$u['activo']==1)?'text-red-400 hover:bg-red-50 border border-red-200':'text-green-500 hover:bg-green-50 border border-green-200' ?>"><iconify-icon icon="<?= ($u['activo']==='1'||$u['activo']==1)?'solar:user-block-bold-duotone':'solar:user-check-bold-duotone' ?>" width="16"></iconify-icon></button><?php else: ?><span class="text-[.7rem] text-[var(--text-tertiary)]">Tu cuenta</span><?php endif; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ══ INVENTARIO ══════════════════════════════════════════════ -->
        <div id="view-inventario" class="content-area max-w-[1400px] mx-auto">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-[var(--text-main)] m-0">Gestion de inventario</h1>
                <p class="text-[var(--text-tertiary)] text-sm font-medium mt-1">Controla el stock de cada producto disponible en el menu.</p>
            </div>

            <!-- Inv stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="stat-card" style="border-left-color:var(--primary)">
                    <div class="flex justify-between items-start mb-2"><span class="text-[.68rem] uppercase tracking-wider text-[var(--text-tertiary)] font-700">Total activos</span><div class="w-8 h-8 rounded-lg bg-[var(--primary-light)] text-[var(--primary)] flex items-center justify-center"><iconify-icon icon="solar:box-bold-duotone" width="17"></iconify-icon></div></div>
                    <div class="text-2xl font-bold text-[var(--text-main)]"><?= $inv_total ?></div>
                    <div class="text-xs text-[var(--text-tertiary)] mt-1">Productos en el menu</div>
                </div>
                <div class="stat-card" style="border-left-color:var(--danger)">
                    <div class="flex justify-between items-start mb-2"><span class="text-[.68rem] uppercase tracking-wider text-[var(--text-tertiary)] font-700">Agotados</span><div class="w-8 h-8 rounded-lg bg-[var(--danger-light)] text-[var(--danger)] flex items-center justify-center"><iconify-icon icon="solar:danger-bold-duotone" width="17"></iconify-icon></div></div>
                    <div class="text-2xl font-bold text-[var(--text-main)]"><?= $inv_agotados ?></div>
                    <div class="text-xs text-[var(--text-tertiary)] mt-1">Stock en cero</div>
                </div>
                <div class="stat-card" style="border-left-color:var(--warning)">
                    <div class="flex justify-between items-start mb-2"><span class="text-[.68rem] uppercase tracking-wider text-[var(--text-tertiary)] font-700">Stock bajo</span><div class="w-8 h-8 rounded-lg bg-[var(--warning-light)] text-[var(--warning)] flex items-center justify-center"><iconify-icon icon="solar:danger-triangle-bold-duotone" width="17"></iconify-icon></div></div>
                    <div class="text-2xl font-bold text-[var(--text-main)]"><?= $inv_bajo ?></div>
                    <div class="text-xs text-[var(--text-tertiary)] mt-1">Menos de 5 unidades</div>
                </div>
                <div class="stat-card" style="border-left-color:var(--info)">
                    <div class="flex justify-between items-start mb-2"><span class="text-[.68rem] uppercase tracking-wider text-[var(--text-tertiary)] font-700">Bebidas</span><div class="w-8 h-8 rounded-lg bg-[var(--info-light)] text-[var(--info)] flex items-center justify-center"><iconify-icon icon="solar:cup-hot-bold-duotone" width="17"></iconify-icon></div></div>
                    <div class="text-2xl font-bold text-[var(--text-main)]"><?= $inv_bebidas ?></div>
                    <div class="text-xs text-[var(--text-tertiary)] mt-1">Stock ilimitado</div>
                </div>
            </div>

            <!-- Inv table -->
            <div class="card overflow-hidden">
                <div class="p-4 border-b border-[var(--border-light)] bg-[var(--bg)] flex items-center gap-3">
                    <input type="text" id="buscar-inv" oninput="filtrarInventario()" placeholder="Buscar producto..." class="input-solid rounded-xl py-2 w-56 text-sm">
                    <select id="filtro-cat-inv" onchange="filtrarInventario()" class="input-solid rounded-xl py-2 w-auto text-sm font-semibold cursor-pointer">
                        <option value="">Todas las categorias</option>
                        <?php foreach($categorias_disponibles as $cat): ?>
                        <option value="<?= $cat ?>"><?= ucfirst($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="overflow-x-auto">
                    <table class="table-custom" id="tabla-inv">
                        <thead><tr><th>Producto</th><th>Categoria</th><th class="text-center">Precio</th><th>Stock actual</th><th class="text-center w-[180px]">Ajustar stock</th><th class="text-center">Estado</th></tr></thead>
                        <tbody>
                        <?php foreach($todos_productos as $prod):
                            $s = (int)$prod['stock_num'];
                            $esIlim = ($prod['tipo_stock'] === 'ilimitado');
                            $isActivo = ((int)$prod['activo'] === 1);
                            $estadoBadge = !$isActivo ? 'inactivo' : ($esIlim ? 'ilimitado' : ($s===0 ? 'agotado' : ($s<6 ? 'bajo' : 'ok')));
                            $estadoLabel = !$isActivo ? 'Inactivo' : ($esIlim ? 'En Stock' : ($s===0 ? 'Agotado' : ($s<6 ? 'Stock bajo' : 'Disponible')));
                            $maxBar = 50; $pct = $esIlim ? 100 : min(100, ($s/$maxBar)*100);
                            $barColor = $esIlim ? '#0ea5e9' : ($s===0 ? '#ef4444' : ($s<6 ? '#d97706' : '#22c55e'));
                        ?>
                        <tr class="inv-fila" data-cat="<?= htmlspecialchars($prod['categoria_producto']) ?>" data-nombre="<?= htmlspecialchars(strtolower($prod['nombre_producto'])) ?>" id="inv-row-<?= $prod['id_producto'] ?>">
                            <td>
                                <div class="flex items-center gap-3">
                                    <?php if(!empty($prod['imagen'])): ?>
                                    <img src="<?= htmlspecialchars($prod['imagen']) ?>" class="w-9 h-9 rounded-lg object-cover flex-shrink-0" onerror="this.style.display='none'">
                                    <?php else: ?>
                                    <div class="w-9 h-9 rounded-lg bg-[var(--primary-light)] flex items-center justify-center flex-shrink-0"><iconify-icon icon="solar:chef-hat-heart-bold-duotone" class="text-[var(--primary)]" width="18"></iconify-icon></div>
                                    <?php endif; ?>
                                    <span class="font-bold text-[var(--text-main)] text-sm"><?= htmlspecialchars($prod['nombre_producto']) ?></span>
                                </div>
                            </td>
                            <td class="text-xs capitalize text-[var(--text-secondary)]"><?= htmlspecialchars($prod['categoria_producto'] ?? '—') ?></td>
                            <td class="text-center font-bold text-[var(--primary)] text-sm">RD$<?= number_format((float)$prod['precio_producto'],2) ?></td>
                            <td>
                                <?php if ($esIlim): ?>
                                <div class="flex items-center gap-2"><span class="text-sm font-bold text-[var(--info)]">∞ Ilimitado</span></div>
                                <?php else: ?>
                                <div class="flex flex-col gap-1.5">
                                    <div class="text-sm font-bold text-[var(--text-main)]" id="stock-num-<?= $prod['id_producto'] ?>"><?= $s ?> u.</div>
                                    <div class="stock-bar-wrap"><div class="stock-bar" style="width:<?= $pct ?>%;background:<?= $barColor ?>"></div></div>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (!$esIlim): ?>
                                <div class="flex items-center justify-center gap-1">
                                    <button onclick="ajustarStock(<?= $prod['id_producto'] ?>,-5)" class="btn-icon w-7 h-7 text-xs border border-[var(--border)] hover:bg-red-50 hover:border-red-200 hover:text-red-500 font-bold rounded-lg">-5</button>
                                    <button onclick="ajustarStock(<?= $prod['id_producto'] ?>,-1)" class="btn-icon w-7 h-7 text-xs border border-[var(--border)] hover:bg-red-50 hover:border-red-200 hover:text-red-500 font-bold rounded-lg">-1</button>
                                    <button onclick="setStockModal(<?= $prod['id_producto'] ?>, '<?= addslashes($prod['nombre_producto']) ?>', <?= $s ?>)" class="btn-icon w-7 h-7 border border-[var(--primary)] text-[var(--primary)] hover:bg-[var(--primary-light)] rounded-lg"><iconify-icon icon="solar:pen-bold-duotone" width="13"></iconify-icon></button>
                                    <button onclick="ajustarStock(<?= $prod['id_producto'] ?>,1)" class="btn-icon w-7 h-7 text-xs border border-[var(--border)] hover:bg-green-50 hover:border-green-200 hover:text-green-600 font-bold rounded-lg">+1</button>
                                    <button onclick="ajustarStock(<?= $prod['id_producto'] ?>,10)" class="btn-icon w-7 h-7 text-xs border border-[var(--border)] hover:bg-green-50 hover:border-green-200 hover:text-green-600 font-bold rounded-lg">+10</button>
                                </div>
                                <?php else: ?>
                                <span class="text-xs text-[var(--text-tertiary)]">No aplica</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><span class="badge badge-<?= $estadoBadge ?>"><?= $estadoLabel ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ══ PRODUCTOS ═══════════════════════════════════════════════ -->
        <div id="view-productos" class="content-area max-w-[1200px] mx-auto">
            <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div><h1 class="text-2xl font-bold text-[var(--text-main)] m-0">Administrar productos</h1><p class="text-[var(--text-tertiary)] text-sm font-medium mt-1"><?= count($todos_productos) ?> productos totales en la base de datos.</p></div>
                <button onclick="toggleFormProducto()" id="btn-toggle-form" class="btn-primary shadow-md shadow-[#F47E24]/20">
                    <iconify-icon icon="solar:add-circle-bold-duotone" width="18"></iconify-icon>Nuevo producto
                </button>
            </div>

            <!-- Form agregar/editar producto -->
            <div id="form-producto-wrap" class="card p-6 mb-6 hidden">
                <h3 id="form-prod-titulo" class="text-[var(--text-main)] font-bold text-lg mb-5">Agregar nuevo producto</h3>
                <form id="form-producto" onsubmit="submitProducto(event)" enctype="multipart/form-data">
                    <input type="hidden" name="accion" id="prod-accion" value="agregar">
                    <input type="hidden" name="id_producto" id="prod-id" value="">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-[var(--text-tertiary)] uppercase tracking-wider mb-1.5">Nombre del producto *</label>
                            <input type="text" name="nombre_producto" id="prod-nombre" required placeholder="Ej: Empanadas de pollo" class="input-solid rounded-xl">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-[var(--text-tertiary)] uppercase tracking-wider mb-1.5">Precio (RD$) *</label>
                            <input type="number" name="precio_producto" id="prod-precio" required min="1" step="0.01" placeholder="0.00" class="input-solid rounded-xl">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-[var(--text-tertiary)] uppercase tracking-wider mb-1.5">Categoria *</label>
                            <select name="categoria_producto" id="prod-categoria" required class="input-solid rounded-xl cursor-pointer">
                                <option value="">Seleccionar...</option>
                                <?php foreach($categorias_disponibles as $cat): ?>
                                <option value="<?= $cat ?>"><?= ucfirst($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-[var(--text-tertiary)] uppercase tracking-wider mb-1.5">Tipo de stock</label>
                            <select name="tipo_stock" id="prod-tipo-stock" onchange="toggleStockField()" class="input-solid rounded-xl cursor-pointer">
                                <option value="stock">Con stock (cantidad exacta)</option>
                                <option value="ilimitado">Ilimitado (bebidas, etc.)</option>
                            </select>
                        </div>
                        <div id="stock-field-wrap">
                            <label class="block text-xs font-bold text-[var(--text-tertiary)] uppercase tracking-wider mb-1.5">Stock inicial</label>
                            <input type="number" name="stock" id="prod-stock" min="0" value="10" placeholder="0" class="input-solid rounded-xl">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-[var(--text-tertiary)] uppercase tracking-wider mb-1.5">Imagen del producto</label>
                            <div class="flex gap-2 items-center">
                                <input type="file" name="imagen" id="prod-imagen" accept="image/*" onchange="previewImagen(this)" class="input-solid rounded-xl text-sm py-2 cursor-pointer flex-1">
                                <div id="img-preview-wrap" class="hidden w-12 h-12 rounded-lg overflow-hidden flex-shrink-0 border border-[var(--border)]">
                                    <img id="img-preview" src="" class="w-full h-full object-cover">
                                </div>
                            </div>
                            <input type="text" name="imagen_url" id="prod-imagen-url" placeholder="O pegar URL de imagen..." class="input-solid rounded-xl mt-2 text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-[var(--text-tertiary)] uppercase tracking-wider mb-1.5">Descripcion / Variantes</label>
                            <textarea name="descripcion" id="prod-descripcion" rows="2" placeholder="Ej: Pollo · Res · Queso (separa opciones con ·)" class="input-solid rounded-xl resize-none"></textarea>
                            <p class="text-[.7rem] text-[var(--text-tertiary)] mt-1">Separa las variantes con <strong>·</strong> para que aparezcan como opciones en el menu.</p>
                        </div>
                    </div>

                    <div class="flex gap-3 mt-6">
                        <button type="submit" class="btn-primary">
                            <iconify-icon icon="solar:check-circle-bold-duotone" width="18"></iconify-icon>
                            <span id="btn-submit-prod-txt">Guardar producto</span>
                        </button>
                        <button type="button" onclick="cancelarFormProducto()" class="btn-outline">Cancelar</button>
                    </div>
                </form>
            </div>

            <!-- Products table -->
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table-custom">
                        <thead><tr><th>Producto</th><th>Descripcion/Variantes</th><th>Cat.</th><th class="text-center">Precio</th><th class="text-center">Stock</th><th class="text-center">Estado</th><th class="text-center">Acciones</th></tr></thead>
                        <tbody id="tbody-productos">
                        <?php foreach($todos_productos as $prod):
                            $isAct = ((int)$prod['activo'] === 1);
                            $esIlim = ($prod['tipo_stock'] === 'ilimitado');
                        ?>
                        <tr id="prod-fila-<?= $prod['id_producto'] ?>">
                            <td>
                                <div class="flex items-center gap-2.5">
                                    <?php if(!empty($prod['imagen'])): ?>
                                    <img src="<?= htmlspecialchars($prod['imagen']) ?>" class="w-10 h-10 rounded-lg object-cover flex-shrink-0" onerror="this.className='w-10 h-10 rounded-lg bg-[var(--primary-light)] flex-shrink-0'">
                                    <?php else: ?>
                                    <div class="w-10 h-10 rounded-lg bg-[var(--primary-light)] flex items-center justify-center flex-shrink-0"><iconify-icon icon="solar:chef-hat-heart-bold-duotone" class="text-[var(--primary)]" width="18"></iconify-icon></div>
                                    <?php endif; ?>
                                    <span class="font-bold text-[var(--text-main)] text-sm"><?= htmlspecialchars($prod['nombre_producto']) ?></span>
                                </div>
                            </td>
                            <td class="text-xs text-[var(--text-secondary)] max-w-[180px] truncate"><?= htmlspecialchars($prod['descripcion'] ?? '—') ?></td>
                            <td class="text-xs capitalize text-[var(--text-secondary)]"><?= htmlspecialchars($prod['categoria_producto'] ?? '—') ?></td>
                            <td class="text-center font-bold text-[var(--primary)] text-sm">RD$<?= number_format((float)$prod['precio_producto'],2) ?></td>
                            <td class="text-center text-xs font-semibold"><?= $esIlim ? '<span class="badge badge-ilimitado">En Stock</span>' : ((int)$prod['stock_num'].' u.') ?></td>
                            <td class="text-center"><span id="activo-badge-<?= $prod['id_producto'] ?>" class="badge <?= $isAct?'badge-activo':'badge-inactivo' ?>"><?= $isAct?'Activo':'Inactivo' ?></span></td>
                            <td class="text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <button onclick="editarProducto(<?= htmlspecialchars(json_encode($prod), ENT_QUOTES) ?>)" class="btn-icon border border-[var(--border)] hover:border-[var(--primary)] hover:bg-[var(--primary-light)] hover:text-[var(--primary)]" title="Editar">
                                        <iconify-icon icon="solar:pen-bold-duotone" width="15"></iconify-icon>
                                    </button>
                                    <button onclick="toggleActivoProd(<?= $prod['id_producto'] ?>,<?= (int)$prod['activo'] ?>)" class="btn-icon border <?= $isAct?'border-red-200 text-red-400 hover:bg-red-50':'border-green-200 text-green-500 hover:bg-green-50' ?>" title="<?= $isAct?'Desactivar':'Activar' ?>">
                                        <iconify-icon icon="<?= $isAct?'solar:eye-closed-bold-duotone':'solar:eye-bold-duotone' ?>" width="15" id="activo-ico-<?= $prod['id_producto'] ?>"></iconify-icon>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ══ ECONOMIA ════════════════════════════════════════════════ -->
        <div id="view-economia" class="content-area max-w-[1200px] mx-auto">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-[var(--text-main)] m-0">Economia del negocio</h1>
                <p class="text-[var(--text-tertiary)] text-sm font-medium mt-1">Resumen de ingresos, ventas y productos mas vendidos.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Revenue by category -->
                <div class="card p-6">
                    <h3 class="text-[var(--text-main)] font-bold text-[1rem] m-0 mb-5">Ingresos por categoria</h3>
                    <?php if(empty($ventas_cat)): ?>
                    <p class="text-[var(--text-tertiary)] text-sm text-center py-8">Sin datos de ventas aun.</p>
                    <?php else: ?>
                        <?php
                        $total_ing = array_sum(array_column($ventas_cat,'ingresos'));
                        $cat_colors = ['salados'=>'#F47E24','sandwiches'=>'#8b5cf6','bebidas'=>'#0ea5e9','postres'=>'#22c55e','otros'=>'#d97706'];
                        foreach($ventas_cat as $vc):
                            $pct = $total_ing > 0 ? round(($vc['ingresos']/$total_ing)*100) : 0;
                            $color = $cat_colors[$vc['categoria_producto']] ?? '#F47E24';
                        ?>
                        <div class="mb-4">
                            <div class="flex justify-between items-center mb-1.5">
                                <span class="text-sm font-semibold text-[var(--text-main)] capitalize"><?= htmlspecialchars($vc['categoria_producto']) ?></span>
                                <div class="text-right">
                                    <span class="text-sm font-bold text-[var(--text-main)]">RD$<?= number_format((float)$vc['ingresos'],0,'.',',') ?></span>
                                    <span class="text-xs text-[var(--text-tertiary)] ml-1">&bull; <?= $vc['unidades'] ?> u.</span>
                                </div>
                            </div>
                            <div class="h-2 bg-[var(--border)] rounded-full overflow-hidden">
                                <div class="h-2 rounded-full transition-all duration-700" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
                            </div>
                            <div class="text-[.7rem] text-[var(--text-tertiary)] mt-0.5 text-right font-medium"><?= $pct ?>%</div>
                        </div>
                        <?php endforeach; ?>
                        <div class="border-t border-[var(--border-light)] pt-3 mt-3 flex justify-between">
                            <span class="text-sm font-bold text-[var(--text-main)]">Total ingresos</span>
                            <span class="text-[var(--primary)] font-bold text-sm">RD$<?= number_format((float)$total_ing,0,'.',',') ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Top productos -->
                <div class="card overflow-hidden">
                    <div class="px-6 py-4 border-b border-[var(--border-light)]">
                        <h3 class="text-[var(--text-main)] font-bold text-[1rem] m-0">Productos mas vendidos</h3>
                    </div>
                    <?php if(empty($top_productos)): ?>
                    <div class="text-center py-12"><p class="text-[var(--text-tertiary)] text-sm">Sin datos de ventas aun.</p></div>
                    <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="table-custom">
                            <thead><tr><th>Producto</th><th>Categoria</th><th class="text-center">Unidades</th><th class="text-right">Ingresos</th></tr></thead>
                            <tbody>
                            <?php foreach($top_productos as $i=>$tp): ?>
                            <tr>
                                <td><div class="flex items-center gap-2.5"><span class="w-6 h-6 rounded-full bg-[var(--primary-light)] text-[var(--primary)] text-xs font-bold flex items-center justify-center"><?= $i+1 ?></span><span class="font-bold text-[var(--text-main)] text-sm"><?= htmlspecialchars($tp['nombre_producto']) ?></span></div></td>
                                <td class="capitalize text-xs"><?= htmlspecialchars($tp['categoria_producto']) ?></td>
                                <td class="text-center font-bold text-sm"><?= $tp['total_vendido'] ?></td>
                                <td class="text-right font-bold text-[var(--primary)] text-sm">RD$<?= number_format((float)$tp['ingresos'],0,'.',',') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Resumen financiero -->
            <div class="card p-6">
                <h3 class="text-[var(--text-main)] font-bold text-[1rem] m-0 mb-4">Resumen financiero</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <?php
                    $fin_cards = [
                        ['label'=>'Ingresos totales','val'=>'RD$'.number_format((float)$stat_ingresos,0,'.',','),'color'=>'var(--success)','icon'=>'solar:dollar-minimalistic-bold-duotone'],
                        ['label'=>'Ingresos hoy','val'=>'RD$'.number_format((float)$ingresos_hoy,0,'.',','),'color'=>'var(--primary)','icon'=>'solar:sun-bold-duotone'],
                        ['label'=>'Pedidos totales','val'=>$stat_pedidos,'color'=>'var(--info)','icon'=>'solar:clipboard-list-bold-duotone'],
                        ['label'=>'Ticket promedio','val'=>($stat_pedidos>0 ? 'RD$'.number_format((float)$stat_ingresos/(int)$stat_pedidos,2) : '—'),'color'=>'var(--purple)','icon'=>'solar:chart-bold-duotone'],
                    ];
                    foreach($fin_cards as $fc): ?>
                    <div class="bg-[var(--bg)] rounded-xl p-4 border border-[var(--border-light)]">
                        <div class="flex items-center gap-2 mb-2">
                            <iconify-icon icon="<?= $fc['icon'] ?>" width="18" style="color:<?= $fc['color'] ?>"></iconify-icon>
                            <span class="text-[.7rem] font-700 uppercase tracking-wider text-[var(--text-tertiary)]"><?= $fc['label'] ?></span>
                        </div>
                        <div class="text-xl font-bold text-[var(--text-main)]"><?= $fc['val'] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div><!-- /main-scroll -->
</main>

<!-- Toast -->
<div id="toast-admin" class="fixed bottom-6 right-6 z-50 hidden">
    <div class="bg-[var(--text-main)] text-white text-sm font-semibold px-5 py-3 rounded-xl shadow-2xl flex items-center gap-2.5">
        <iconify-icon id="toast-icon" icon="solar:check-circle-bold-duotone" class="text-[var(--primary)]" width="18"></iconify-icon>
        <span id="toast-msg">OK</span>
    </div>
</div>

<!-- Modal: editar stock manualmente -->
<div id="modal-stock" class="fixed inset-0 z-50 items-center justify-center hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="cerrarModalStock()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl p-6 w-80 z-10">
        <h3 class="font-bold text-[var(--text-main)] mb-1" id="modal-stock-titulo">Ajustar stock</h3>
        <p class="text-xs text-[var(--text-tertiary)] mb-4">Introduce la cantidad exacta disponible.</p>
        <input type="number" id="modal-stock-input" min="0" class="input-solid rounded-xl text-lg font-bold text-center mb-4" placeholder="0">
        <input type="hidden" id="modal-stock-id">
        <div class="flex gap-3">
            <button onclick="cerrarModalStock()" class="btn-outline flex-1">Cancelar</button>
            <button onclick="confirmarStockModal()" class="btn-primary flex-1">Aplicar</button>
        </div>
    </div>
</div>

<script>
const VIEWS = ['dashboard','pedidos','usuarios','inventario','productos','economia'];
const TITLES = {dashboard:'Dashboard',pedidos:'Gestion de pedidos',usuarios:'Usuarios',inventario:'Inventario',productos:'Administrar productos',economia:'Economia'};

function switchView(v){
    VIEWS.forEach(id=>{document.getElementById('view-'+id)?.classList.remove('active');document.getElementById('nav-'+id)?.classList.remove('active');});
    document.getElementById('view-'+v)?.classList.add('active');
    document.getElementById('nav-'+v)?.classList.add('active');
    document.getElementById('header-title').textContent=TITLES[v];
    document.getElementById('main-scroll').scrollTop=0;
    if(window.innerWidth<768) toggleSidebar();
    if(v==='dashboard'&&window._chart1) window._chart1.resize();
    window.location.hash=v;
}

function toggleSidebar(){
    const sb=document.getElementById('sidebar'),ov=document.getElementById('mobile-overlay');
    const closed=sb.classList.contains('-translate-x-full');
    sb.classList.toggle('-translate-x-full',!closed);
    ov.classList.toggle('hidden',!closed);
}

let _tt;
function toast(msg,tipo){
    clearTimeout(_tt);
    const el=document.getElementById('toast-admin');
    document.getElementById('toast-msg').textContent=msg;
    document.getElementById('toast-icon').setAttribute('icon',tipo==='error'?'solar:close-circle-bold-duotone':'solar:check-circle-bold-duotone');
    document.getElementById('toast-icon').style.color=tipo==='error'?'var(--danger)':'var(--primary)';
    el.classList.remove('hidden');
    _tt=setTimeout(()=>el.classList.add('hidden'),2800);
}

/* ── PEDIDOS ───────────────────────────────────────── */
function cambiarEstadoPedido(id,sel){
    const nuevo=sel.value; sel.disabled=true;
    fetch('./backend/admin/cambiar_estado_pedido.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`id_pedido=${id}&estado=${encodeURIComponent(nuevo)}`})
    .then(r=>r.json()).then(d=>{sel.disabled=false;if(d.ok){sel.className=sel.className.replace(/badge-\S+/,'badge-'+nuevo);document.getElementById('fila-p-'+id)?.setAttribute('data-estado',nuevo);toast('Estado: '+nuevo.replaceAll('_',' '));}else toast(d.error||'Error','error');}).catch(()=>{sel.disabled=false;toast('Error de conexion','error');});
}

function toggleDetalle(id){
    const det=document.getElementById('det-'+id),ico=document.getElementById('ico-'+id);
    if(!det.classList.contains('hidden')){det.classList.add('hidden');ico.setAttribute('icon','solar:alt-arrow-down-bold-duotone');return;}
    det.classList.remove('hidden');ico.setAttribute('icon','solar:alt-arrow-up-bold-duotone');
    if(det.dataset.cargado)return;det.dataset.cargado='1';
    fetch('./backend/pedidos/detalle_pedido.php?id_pedido='+id).then(r=>r.json()).then(items=>{
        if(items.error){det.querySelector('td').innerHTML=`<p class="text-red-400 text-xs">${items.error}</p>`;return;}
        let h='<div class="overflow-x-auto"><table class="w-full text-xs border-collapse"><thead><tr class="bg-white border-b border-[var(--border)]"><th class="text-left px-4 py-2.5 text-[var(--text-tertiary)] font-semibold">Producto</th><th class="px-4 py-2.5 text-center text-[var(--text-tertiary)] font-semibold">Cant.</th><th class="px-4 py-2.5 text-center text-[var(--text-tertiary)] font-semibold">P. Unit.</th><th class="px-4 py-2.5 text-right text-[var(--primary)] font-semibold">Subtotal</th></tr></thead><tbody>';
        items.forEach(i=>{h+=`<tr class="border-b border-[var(--border-light)] last:border-0"><td class="px-4 py-2.5 font-semibold text-[var(--text-main)]">${i.nombre_producto}</td><td class="px-4 py-2.5 text-center">${i.cantidad}</td><td class="px-4 py-2.5 text-center">RD$${parseFloat(i.precio_unitario).toFixed(2)}</td><td class="px-4 py-2.5 text-right font-bold text-[var(--primary)]">RD$${(i.cantidad*i.precio_unitario).toFixed(2)}</td></tr>`;});
        h+='</tbody></table></div>'; det.querySelector('td').innerHTML=h;
    }).catch(()=>{det.querySelector('td').innerHTML='<p class="text-red-400 text-xs">Error al cargar detalle.</p>';});
}

function filtrarPedidos(){
    const v=document.getElementById('filtro-estado').value;
    document.querySelectorAll('.pedido-fila').forEach(f=>{
        const m=!v||f.dataset.estado===v;f.style.display=m?'':'none';
        const d=document.getElementById('det-'+f.id.replace('fila-p-',''));if(d)d.style.display=m?'':'none';
    });
}

/* ── USUARIOS ──────────────────────────────────────── */
function toggleUsuario(id,activo){
    if(!confirm('¿Seguro?')) return;
    const btn=document.getElementById('btn-u-'+id),badge=document.getElementById('estado-u-'+id);
    btn.disabled=true;
    fetch('./backend/admin/cambiar_estado_usuario.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`id_usuario=${id}&accion=${activo?'desactivar':'activar'}`})
    .then(r=>r.json()).then(d=>{btn.disabled=false;if(d.ok){const a=d.activo==='1';badge.textContent=a?'Activo':'Inactivo';badge.className='badge '+(a?'badge-activo':'badge-inactivo');btn.setAttribute('onclick',`toggleUsuario(${id},${a})`);btn.querySelector('iconify-icon')?.setAttribute('icon',a?'solar:user-block-bold-duotone':'solar:user-check-bold-duotone');toast('Usuario '+(a?'activado':'desactivado'));}else toast(d.error||'Error','error');}).catch(()=>{btn.disabled=false;toast('Error de conexion','error');});
}

function filtrarUsuarios(){
    const q=document.getElementById('buscar-usuario').value.toLowerCase();
    document.querySelectorAll('.usuario-fila').forEach(f=>{f.style.display=f.textContent.toLowerCase().includes(q)?'':'none';});
}

/* ── INVENTARIO ────────────────────────────────────── */
function filtrarInventario(){
    const q=document.getElementById('buscar-inv').value.toLowerCase();
    const c=document.getElementById('filtro-cat-inv').value;
    document.querySelectorAll('.inv-fila').forEach(f=>{
        const matchQ=!q||f.dataset.nombre.includes(q);
        const matchC=!c||f.dataset.cat===c;
        f.style.display=(matchQ&&matchC)?'':'none';
    });
}

function ajustarStock(id,delta){
    const op=delta>0?'sumar':'restar';
    const absDelta=Math.abs(delta);
    fetch('./backend/admin/actualizar_stock.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`id_producto=${id}&stock=${absDelta}&operacion=${op}`})
    .then(r=>r.json()).then(d=>{
        if(d.ok){
            const el=document.getElementById('stock-num-'+id);
            if(el) el.textContent=d.stock+' u.';
            toast(delta>0?`+${absDelta} unidades agregadas`:`-${absDelta} unidades restadas`);
        }else toast(d.error||'Error','error');
    }).catch(()=>toast('Error de conexion','error'));
}

function setStockModal(id,nombre,actual){
    document.getElementById('modal-stock-titulo').textContent='Stock: '+nombre;
    document.getElementById('modal-stock-input').value=actual;
    document.getElementById('modal-stock-id').value=id;
    const m=document.getElementById('modal-stock');m.classList.remove('hidden');m.classList.add('flex');
}

function cerrarModalStock(){
    const m=document.getElementById('modal-stock');m.classList.add('hidden');m.classList.remove('flex');
}

function confirmarStockModal(){
    const id=document.getElementById('modal-stock-id').value;
    const val=document.getElementById('modal-stock-input').value;
    fetch('./backend/admin/actualizar_stock.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`id_producto=${id}&stock=${val}&operacion=set`})
    .then(r=>r.json()).then(d=>{
        if(d.ok){
            const el=document.getElementById('stock-num-'+id);
            if(el) el.textContent=d.stock+' u.';
            toast('Stock actualizado a '+d.stock+' unidades');
            cerrarModalStock();
        }else toast(d.error||'Error','error');
    }).catch(()=>toast('Error','error'));
}

/* ── PRODUCTOS CRUD ────────────────────────────────── */
let formAbierto=false;

function toggleFormProducto(){
    const wrap=document.getElementById('form-producto-wrap');
    formAbierto=!formAbierto;
    wrap.classList.toggle('hidden',!formAbierto);
    if(formAbierto){
        document.getElementById('prod-accion').value='agregar';
        document.getElementById('form-prod-titulo').textContent='Agregar nuevo producto';
        document.getElementById('btn-submit-prod-txt').textContent='Guardar producto';
        document.getElementById('form-producto').reset();
        document.getElementById('prod-id').value='';
        document.getElementById('img-preview-wrap').classList.add('hidden');
        toggleStockField();
        wrap.scrollIntoView({behavior:'smooth',block:'nearest'});
    }
}

function cancelarFormProducto(){formAbierto=false;document.getElementById('form-producto-wrap').classList.add('hidden');}

function toggleStockField(){
    const tipo=document.getElementById('prod-tipo-stock').value;
    document.getElementById('stock-field-wrap').style.display=tipo==='ilimitado'?'none':'';
}

function previewImagen(input){
    const wrap=document.getElementById('img-preview-wrap');
    const preview=document.getElementById('img-preview');
    if(input.files&&input.files[0]){
        const reader=new FileReader();
        reader.onload=e=>{preview.src=e.target.result;wrap.classList.remove('hidden');};
        reader.readAsDataURL(input.files[0]);
    }
}

function editarProducto(p){
    const wrap=document.getElementById('form-producto-wrap');
    formAbierto=true; wrap.classList.remove('hidden');
    document.getElementById('form-prod-titulo').textContent='Editar: '+p.nombre_producto;
    document.getElementById('btn-submit-prod-txt').textContent='Actualizar producto';
    document.getElementById('prod-accion').value='editar';
    document.getElementById('prod-id').value=p.id_producto;
    document.getElementById('prod-nombre').value=p.nombre_producto||'';
    document.getElementById('prod-descripcion').value=p.descripcion||'';
    document.getElementById('prod-precio').value=p.precio_producto||'';
    document.getElementById('prod-categoria').value=p.categoria_producto||'';
    document.getElementById('prod-tipo-stock').value=p.tipo_stock||'stock';
    document.getElementById('prod-stock').value=p.stock_num||'';
    document.getElementById('prod-imagen-url').value=p.imagen||'';
    if(p.imagen){
        document.getElementById('img-preview').src=p.imagen;
        document.getElementById('img-preview-wrap').classList.remove('hidden');
    }
    toggleStockField();
    wrap.scrollIntoView({behavior:'smooth',block:'nearest'});
}

function submitProducto(e){
    e.preventDefault();
    const btn=document.querySelector('#form-producto button[type="submit"]');
    btn.disabled=true;
    const fd=new FormData(document.getElementById('form-producto'));
    fetch('./backend/admin/gestionar_producto.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>{
        btn.disabled=false;
        if(d.ok){toast(d.mensaje||'Guardado correctamente');cancelarFormProducto();setTimeout(()=>location.reload(),800);}
        else toast(d.error||'Error','error');
    }).catch(()=>{btn.disabled=false;toast('Error de conexion','error');});
}

function toggleActivoProd(id,activo){
    const accion=activo?'toggle_activo':'toggle_activo';
    fetch('./backend/admin/gestionar_producto.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`accion=toggle_activo&id_producto=${id}&activo=${activo}`})
    .then(r=>r.json()).then(d=>{
        if(d.ok){
            const badge=document.getElementById('activo-badge-'+id);
            const ico=document.getElementById('activo-ico-'+id);
            badge.textContent=d.activo?'Activo':'Inactivo';
            badge.className='badge '+(d.activo?'badge-activo':'badge-inactivo');
            if(ico) ico.setAttribute('icon',d.activo?'solar:eye-closed-bold-duotone':'solar:eye-bold-duotone');
            toast(d.activo?'Producto activado':'Producto desactivado');
        }else toast(d.error||'Error','error');
    }).catch(()=>toast('Error','error'));
}

/* ── CHART ──────────────────────────────────────────── */
window.addEventListener('load',()=>{
    const ctx=document.getElementById('chartEstados')?.getContext('2d');
    if(!ctx)return;
    window._chart1=new Chart(ctx,{type:'doughnut',data:{labels:['Pendiente','En proceso','Listo','Entregado','Cancelado'],datasets:[{data:[<?= implode(',',array_values($estados_count)) ?>],backgroundColor:['#d97706','#0ea5e9','#8b5cf6','#22c55e','#ef4444'],borderWidth:0,hoverOffset:6}]},options:{responsive:true,maintainAspectRatio:false,cutout:'72%',plugins:{legend:{display:false},tooltip:{backgroundColor:'#1e0f07',titleColor:'#fff',bodyColor:'#fff',padding:10,cornerRadius:8}}}});
});

/* ── HASH ROUTING ───────────────────────────────────── */
const h=location.hash.replace('#','');
if(VIEWS.includes(h))switchView(h);
</script>
</body>
</html>
