<?php
// ── Conexión a la base de datos sigmea ──────────────────────────
session_start();

// Proteger página: si no hay sesión, redirigir al login
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$dbHost = 'localhost';
$dbPort = '3308';
$dbName = 'sigmea';
$dbUser = 'root';
$dbPass = '';

$espaciosDB = [];
$kpiDB = ['libres' => 0, 'ocupados' => 0, 'mantenimiento' => 0, 'total' => 0];
$dbError = null;

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Consulta: espacios + horario activo en este momento
    $sql = '
        SELECT
            e.id, e.nombre, e.tipo, e.capacidad, e.edificio, e.piso,
            e.equipamiento, e.estado,
            h.materia, h.grupo,
            u.nombre AS docente,
            TIME_FORMAT(h.hora_fin, "%H:%i") AS hora_fin
        FROM espacios e
        LEFT JOIN horarios h ON (
            h.espacio_id = e.id
            AND h.dia_semana  = DAYNAME(NOW())
            AND h.hora_inicio <= TIME(NOW())
            AND h.hora_fin    >= TIME(NOW())
            AND h.fecha_inicio_vigencia <= CURDATE()
            AND h.fecha_fin_vigencia    >= CURDATE()
        )
        LEFT JOIN usuarios u ON u.id = h.docente_id
        ORDER BY e.edificio, e.nombre
    ';

    $rows = $pdo->query($sql)->fetchAll();

    foreach ($rows as $r) {
        // Estado real: mantenimiento > ocupado (con horario activo) > libre
        if ($r['estado'] === 'mantenimiento') {
            $estadoReal = 'mantenimiento';
        } elseif (!empty($r['materia'])) {
            $estadoReal = 'ocupado';
        } else {
            $estadoReal = 'libre';
        }

        $espaciosDB[] = [
            'id' => (int) $r['id'],
            'nombre' => $r['nombre'],
            'tipo' => $r['tipo'],
            'cap' => (int) $r['capacidad'],
            'edificio' => $r['edificio'],
            'piso' => (int) $r['piso'],
            'estado' => $estadoReal,
            'materia' => $r['materia'],
            'docente' => $r['docente'],
            'grupo' => $r['grupo'],
            'hasta' => $r['hora_fin'],
            'equip' => json_decode($r['equipamiento'] ?? '[]', true),
        ];

        $kpiDB['total']++;
        if ($estadoReal === 'libre')
            $kpiDB['libres']++;
        elseif ($estadoReal === 'ocupado')
            $kpiDB['ocupados']++;
        elseif ($estadoReal === 'mantenimiento')
            $kpiDB['mantenimiento']++;
    }

} catch (PDOException $e) {
    $dbError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SIGMEA — Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap"
        rel="stylesheet" />
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --bg: #070c18;
            --surface: #0d1526;
            --surface2: #111d33;
            --border: #1a2840;
            --border-hi: #2a4070;
            --text: #e2eaf7;
            --muted: #5a7090;
            --accent: #4f8ef7;
            --accent-glow: rgba(79, 142, 247, .2);
            --green: #22c55e;
            --green-bg: rgba(34, 197, 94, .1);
            --green-bd: rgba(34, 197, 94, .25);
            --red: #ef4444;
            --red-bg: rgba(239, 68, 68, .1);
            --red-bd: rgba(239, 68, 68, .25);
            --yellow: #f59e0b;
            --yellow-bg: rgba(245, 158, 11, .1);
            --yellow-bd: rgba(245, 158, 11, .25);
        }

        html,
        body {
            height: 100%;
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            overflow: hidden;
        }

        /* ── Sidebar ── */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 240px;
            background: var(--surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            z-index: 100;
            padding: 28px 16px;
            transition: transform .3s;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 8px 28px;
            border-bottom: 1px solid var(--border);
        }

        .brand-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--accent), #6fb4ff);
            border-radius: 8px;
            display: grid;
            place-items: center;
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 14px;
            color: #fff;
        }

        .brand-name {
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 16px;
        }

        .brand-sub {
            font-size: 10px;
            color: var(--muted);
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }

        .nav-section {
            margin-top: 24px;
        }

        .nav-label {
            font-size: 10px;
            color: var(--muted);
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 0 12px;
            margin-bottom: 8px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 14px;
            color: var(--muted);
            cursor: pointer;
            transition: all .15s;
            text-decoration: none;
            margin-bottom: 2px;
        }

        .nav-item:hover {
            background: var(--surface2);
            color: var(--text);
        }

        .nav-item.active {
            background: rgba(79, 142, 247, .12);
            color: var(--accent);
        }

        .nav-item .ico {
            font-size: 17px;
            width: 20px;
            text-align: center;
        }

        .nav-badge {
            margin-left: auto;
            background: var(--red);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 100px;
        }

        .sidebar-footer {
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }

        .user-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 8px;
        }

        .avatar {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: linear-gradient(135deg, #4f8ef7, #22c55e);
            display: grid;
            place-items: center;
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 12px;
            color: #fff;
        }

        .user-name {
            font-size: 13px;
            font-weight: 500;
        }

        .user-role {
            font-size: 11px;
            color: var(--muted);
            text-transform: capitalize;
        }

        .logout-btn {
            margin-left: auto;
            background: none;
            border: none;
            color: var(--muted);
            font-size: 17px;
            cursor: pointer;
            transition: color .2s;
        }

        .logout-btn:hover {
            color: var(--red);
        }

        /* ── Main content ── */
        .main {
            margin-left: 240px;
            height: 100vh;
            overflow-y: auto;
            padding: 32px 36px;
        }

        /* ── Topbar ── */
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
        }

        .page-title {
            font-family: 'Syne', sans-serif;
            font-size: 22px;
            font-weight: 700;
        }

        .page-sub {
            font-size: 13px;
            color: var(--muted);
            margin-top: 2px;
        }

        .topbar-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            cursor: pointer;
            border: none;
            transition: all .2s;
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid var(--border);
            color: var(--muted);
        }

        .btn-outline:hover {
            border-color: var(--border-hi);
            color: var(--text);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent), #3a7ae0);
            color: #fff;
            box-shadow: 0 4px 14px rgba(79, 142, 247, .35);
        }

        .btn-primary:hover {
            box-shadow: 0 6px 20px rgba(79, 142, 247, .5);
        }

        .live-dot {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--green);
            font-weight: 500;
        }

        .live-dot::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--green);
            animation: pulse 1.8s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
                transform: scale(1)
            }

            50% {
                opacity: .4;
                transform: scale(.7)
            }
        }

        /* ── KPI cards ── */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }

        .kpi {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px 22px;
            position: relative;
            overflow: hidden;
            transition: border-color .2s;
        }

        .kpi:hover {
            border-color: var(--border-hi);
        }

        .kpi-val {
            font-family: 'Syne', sans-serif;
            font-size: 32px;
            font-weight: 700;
            line-height: 1;
        }

        .kpi-lbl {
            font-size: 12px;
            color: var(--muted);
            margin-top: 6px;
        }

        .kpi-icon {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 28px;
            opacity: .25;
        }

        .kpi.green {
            border-left: 3px solid var(--green);
        }

        .kpi.red {
            border-left: 3px solid var(--red);
        }

        .kpi.yellow {
            border-left: 3px solid var(--yellow);
        }

        .kpi.blue {
            border-left: 3px solid var(--accent);
        }

        .kpi.green .kpi-val {
            color: var(--green);
        }

        .kpi.red .kpi-val {
            color: var(--red);
        }

        .kpi.yellow.kpi-val {
            color: var(--yellow);
        }

        .kpi.blue .kpi-val {
            color: var(--accent);
        }

        /* ── Filters ── */
        .filters {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .filter-btn {
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            border: 1.5px solid var(--border);
            background: transparent;
            color: var(--muted);
            transition: all .15s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-btn:hover {
            border-color: var(--border-hi);
            color: var(--text);
        }

        .filter-btn.active {
            background: rgba(79, 142, 247, .12);
            border-color: var(--accent);
            color: var(--accent);
        }

        .filter-btn.f-green.active {
            background: var(--green-bg);
            border-color: var(--green);
            color: var(--green);
        }

        .filter-btn.f-red.active {
            background: var(--red-bg);
            border-color: var(--red);
            color: var(--red);
        }

        .filter-btn.f-yellow.active {
            background: var(--yellow-bg);
            border-color: var(--yellow);
            color: var(--yellow);
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .dot.g {
            background: var(--green);
        }

        .dot.r {
            background: var(--red);
        }

        .dot.y {
            background: var(--yellow);
        }

        .search-box {
            margin-left: auto;
            position: relative;
        }

        .search-box input {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: 8px;
            padding: 8px 14px 8px 36px;
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            outline: none;
            width: 220px;
            transition: border-color .2s;
        }

        .search-box input:focus {
            border-color: var(--accent);
        }

        .search-box input::placeholder {
            color: var(--muted);
        }

        .search-ico {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 14px;
        }

        /* ── Grid de espacios ── */
        .grid-title {
            font-family: 'Syne', sans-serif;
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--muted);
        }

        .spaces-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 14px;
        }

        .space-card {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: 14px;
            padding: 18px 16px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: transform .2s, border-color .2s, box-shadow .2s;
            animation: fadeIn .3s ease both;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(6px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .space-card:hover {
            transform: translateY(-2px);
        }

        .space-card.libre {
            border-color: var(--green-bd);
        }

        .space-card.libre:hover {
            box-shadow: 0 4px 24px rgba(34, 197, 94, .15);
        }

        .space-card.ocupado {
            border-color: var(--red-bd);
        }

        .space-card.ocupado:hover {
            box-shadow: 0 4px 24px rgba(239, 68, 68, .15);
        }

        .space-card.mantenimiento {
            border-color: var(--yellow-bd);
        }

        .space-card.mantenimiento:hover {
            box-shadow: 0 4px 24px rgba(245, 158, 11, .15);
        }

        .card-status-bar {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            border-radius: 14px 14px 0 0;
        }

        .libre .card-status-bar {
            background: var(--green);
        }

        .ocupado .card-status-bar {
            background: var(--red);
        }

        .mantenimiento .card-status-bar {
            background: var(--yellow);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-top: 4px;
        }

        .card-room {
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 17px;
        }

        .card-type {
            font-size: 10px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 2px;
        }

        .status-badge {
            font-size: 10px;
            font-weight: 600;
            padding: 3px 9px;
            border-radius: 100px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .libre .status-badge {
            background: var(--green-bg);
            color: var(--green);
            border: 1px solid var(--green-bd);
        }

        .ocupado .status-badge {
            background: var(--red-bg);
            color: var(--red);
            border: 1px solid var(--red-bd);
        }

        .mantenimiento .status-badge {
            background: var(--yellow-bg);
            color: var(--yellow);
            border: 1px solid var(--yellow-bd);
        }

        .card-divider {
            height: 1px;
            background: var(--border);
            margin: 12px 0;
        }

        .card-info {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.7;
        }

        .card-info strong {
            color: var(--text);
            font-weight: 500;
        }

        .card-footer {
            margin-top: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cap-badge {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            color: var(--muted);
        }

        .equip-icons {
            display: flex;
            gap: 4px;
        }

        .equip-ico {
            width: 22px;
            height: 22px;
            border-radius: 5px;
            background: var(--surface2);
            border: 1px solid var(--border);
            display: grid;
            place-items: center;
            font-size: 11px;
            color: var(--muted);
        }

        /* ── Detail panel (slide-in) ── */
        .detail-panel {
            position: fixed;
            top: 0;
            right: -420px;
            bottom: 0;
            width: 400px;
            background: var(--surface);
            border-left: 1px solid var(--border);
            z-index: 200;
            padding: 32px 28px;
            transition: right .3s cubic-bezier(.22, 1, .36, 1);
            overflow-y: auto;
        }

        .detail-panel.open {
            right: 0;
        }

        .panel-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            width: 32px;
            height: 32px;
            display: grid;
            place-items: center;
            cursor: pointer;
            color: var(--muted);
            font-size: 16px;
            transition: all .2s;
        }

        .panel-close:hover {
            color: var(--text);
            border-color: var(--border-hi);
        }

        .panel-room {
            font-family: 'Syne', sans-serif;
            font-size: 26px;
            font-weight: 700;
            margin-top: 8px;
        }

        .panel-type {
            font-size: 12px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-top: 4px;
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }

        .info-key {
            color: var(--muted);
        }

        .info-val {
            font-weight: 500;
        }

        .panel-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 24px;
        }

        .panel-btn {
            padding: 12px;
            border-radius: 10px;
            font-size: 13px;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            border: 1.5px solid var(--border);
            background: transparent;
            color: var(--muted);
            transition: all .2s;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .panel-btn:hover {
            border-color: var(--border-hi);
            color: var(--text);
            background: var(--surface2);
        }

        .panel-btn.danger:hover {
            border-color: var(--red-bd);
            color: var(--red);
            background: var(--red-bg);
        }

        .panel-btn.success:hover {
            border-color: var(--green-bd);
            color: var(--green);
            background: var(--green-bg);
        }

        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .5);
            backdrop-filter: blur(2px);
            z-index: 150;
            opacity: 0;
            pointer-events: none;
            transition: opacity .3s;
        }

        .overlay.show {
            opacity: 1;
            pointer-events: all;
        }

        /* ── Responsive ── */
        @media (max-width: 900px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main {
                margin-left: 0;
                padding: 20px 16px;
            }

            .kpi-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>

<body>

    <!-- OVERLAY -->
    <div class="overlay" id="overlay" onclick="closePanel()"></div>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-icon">SG</div>
            <div>
                <div class="brand-name">SIGMEA</div>
                <div class="brand-sub">Sistema de Gestión</div>
            </div>
        </div>

        <nav class="nav-section">
            <div class="nav-label">Principal</div>
            <a class="nav-item active" href="dashboard.php">
                <span class="ico">📊</span> Dashboard
            </a>
            <a class="nav-item" href="espacios.php">
                <span class="ico">🏛️</span> Espacios
            </a>
            <a class="nav-item" href="horarios.php">
                <span class="ico">📅</span> Horarios
            </a>
        </nav>

        <nav class="nav-section">
            <div class="nav-label">Operación</div>
            <a class="nav-item" href="incidencias.php">
                <span class="ico">⚠️</span> Incidencias
                <span class="nav-badge" id="badge-incid">3</span>
            </a>
            <a class="nav-item" href="mantenimiento.php">
                <span class="ico">🔧</span> Mantenimiento
            </a>
            <a class="nav-item" href="buscador.php">
                <span class="ico">🔍</span> Buscador Inteligente
            </a>
        </nav>

        <nav class="nav-section" id="admin-nav">
            <div class="nav-label">Administración</div>
            <a class="nav-item" href="usuarios.php">
                <span class="ico">👥</span> Usuarios
            </a>
            <a class="nav-item" href="reportes.php">
                <span class="ico">📈</span> Reportes
            </a>
            <a class="nav-item" href="pages/logout.php">
                <span class="ico">⏻</span> Cerrar sesión
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-chip">
                <div class="avatar" id="user-avatar">A</div>
                <div>
                    <div class="user-name" id="user-name">Administrador</div>
                    <div class="user-role" id="user-role">admin</div>
                </div>
                <button class="logout-btn" onclick="window.location='pages/logout.php'" title="Cerrar sesión">⏻</button>
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main">
        <!-- Topbar -->
        <div class="topbar">
            <div>
                <h1 class="page-title">Dashboard</h1>
                <p class="page-sub" id="current-time">Cargando hora…</p>
            </div>
            <div class="topbar-actions">
                <div class="live-dot">En vivo</div>
                <button class="btn btn-outline" onclick="refreshGrid()">↻ Actualizar</button>
                <button class="btn btn-primary" onclick="window.location='buscador.php'">+ Buscar espacio</button>
            </div>
        </div>

        <!-- KPIs -->
        <div class="kpi-row">
            <div class="kpi green">
                <div class="kpi-val" id="kpi-libres">—</div>
                <div class="kpi-lbl">Espacios libres</div>
                <div class="kpi-icon">🟢</div>
            </div>
            <div class="kpi red">
                <div class="kpi-val" id="kpi-ocupados">—</div>
                <div class="kpi-lbl">Espacios ocupados</div>
                <div class="kpi-icon">🔴</div>
            </div>
            <div class="kpi yellow">
                <div class="kpi-val" id="kpi-mant">—</div>
                <div class="kpi-lbl">En mantenimiento</div>
                <div class="kpi-icon">🟡</div>
            </div>

        </div>

        <!-- Filters -->
        <div class="filters">
            <button class="filter-btn active" data-f="todos" onclick="setFilter(this)">Todos</button>
            <button class="filter-btn f-green" data-f="libre" onclick="setFilter(this)">
                <span class="dot g"></span> Libres
            </button>
            <button class="filter-btn f-red" data-f="ocupado" onclick="setFilter(this)">
                <span class="dot r"></span> Ocupados
            </button>
            <button class="filter-btn f-yellow" data-f="mantenimiento" onclick="setFilter(this)">
                <span class="dot y"></span> Mantenimiento
            </button>
            <button class="filter-btn" data-f="salon" onclick="setFilter(this)">🏫 Salones</button>
            <button class="filter-btn" data-f="laboratorio" onclick="setFilter(this)">🔬 Laboratorios</button>
            <button class="filter-btn" data-f="computo" onclick="setFilter(this)">💻 Cómputo</button>
            <div class="search-box">
                <span class="search-ico">🔎</span>
                <input type="text" id="searchInput" placeholder="Buscar aula…" oninput="filterByName(this.value)" />
            </div>
        </div>

        <!-- Grid title -->
        <div class="grid-title" id="grid-subtitle">Todos los espacios</div>

        <!-- Spaces Grid -->
        <div class="spaces-grid" id="spacesGrid"></div>
    </main>

    <!-- DETAIL PANEL -->
    <aside class="detail-panel" id="detailPanel">
        <button class="panel-close" onclick="closePanel()">✕</button>
        <div id="panelContent"></div>
    </aside>

    <script>
        // ── Datos desde la base de datos (inyectados por PHP) ──
        <?php if ($dbError): ?>
            console.error('Error BD: <?= htmlspecialchars($dbError) ?>');
        <?php endif; ?>
        const SPACES = <?= json_encode($espaciosDB, JSON_UNESCAPED_UNICODE) ?>;
        const KPI_DB = <?= json_encode($kpiDB) ?>;

        let currentFilter = 'todos';
        let searchTerm = '';

        // ── Render ──
        function renderGrid() {
            const grid = document.getElementById('spacesGrid');

            let data = SPACES;
            if (currentFilter !== 'todos') {
                data = data.filter(s =>
                    s.estado === currentFilter || s.tipo === currentFilter
                );
            }
            if (searchTerm) {
                data = data.filter(s => s.nombre.toLowerCase().includes(searchTerm));
            }

            document.getElementById('grid-subtitle').textContent =
                `${data.length} espacio${data.length !== 1 ? 's' : ''} encontrado${data.length !== 1 ? 's' : ''}`;

            grid.innerHTML = data.map((s, i) => `
        <div class="space-card ${s.estado}" onclick="openDetail(${s.id})"
             style="animation-delay:${i * 0.04}s">
          <div class="card-status-bar"></div>
          <div class="card-header">
            <div>
              <div class="card-room">${s.nombre}</div>
              <div class="card-type">${tipoLabel(s.tipo)}</div>
            </div>
            <span class="status-badge">${estadoLabel(s.estado)}</span>
          </div>
          <div class="card-divider"></div>
          <div class="card-info">
            ${s.estado === 'ocupado' ? `<strong>${s.materia}</strong><br>${s.docente} · Grp. ${s.grupo}<br>Hasta ${s.hasta}` : ''}
            ${s.estado === 'libre' ? `<strong>Disponible</strong><br>Libre hasta las ${s.hasta}` : ''}
            ${s.estado === 'mantenimiento' ? `<strong>En mantenimiento</strong><br>${s.motivo || 'Sin descripción'}` : ''}
          </div>
          <div class="card-footer">
            <div class="cap-badge">👥 ${s.cap}</div>
            <div class="equip-icons">${s.equip.map(e => `<div class="equip-ico">${e}</div>`).join('')}</div>
          </div>
        </div>
      `).join('');

            updateKPIs();
        }

        function updateKPIs() {
            const libres = SPACES.filter(s => s.estado === 'libre').length;
            const ocup = SPACES.filter(s => s.estado === 'ocupado').length;
            const mant = SPACES.filter(s => s.estado === 'mantenimiento').length;
            document.getElementById('kpi-libres').textContent = libres;
            document.getElementById('kpi-ocupados').textContent = ocup;
            document.getElementById('kpi-mant').textContent = mant;
        }

        // ── Filters ──
        function setFilter(btn) {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.dataset.f;
            renderGrid();
        }
        function filterByName(val) {
            searchTerm = val.toLowerCase();
            renderGrid();
        }
        function refreshGrid() {
            const grid = document.getElementById('spacesGrid');
            grid.style.opacity = '0.4';
            setTimeout(() => { grid.style.opacity = '1'; renderGrid(); }, 400);
            // TODO: fetch('/api/espacios.php').then(r=>r.json()).then(data=>{ SPACES = data; renderGrid(); })
        }

        // ── Detail Panel ──
        function openDetail(id) {
            const s = SPACES.find(x => x.id === id);
            if (!s) return;

            const panel = document.getElementById('detailPanel');
            const cont = document.getElementById('panelContent');

            const statusColor = { libre: 'var(--green)', ocupado: 'var(--red)', mantenimiento: 'var(--yellow)' };

            cont.innerHTML = `
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
          <span style="width:10px;height:10px;border-radius:50%;background:${statusColor[s.estado]};display:inline-block"></span>
          <span style="font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:1px">${estadoLabel(s.estado)}</span>
        </div>
        <div class="panel-room">${s.nombre}</div>
        <div class="panel-type">${tipoLabel(s.tipo)} · Edificio ${s.edificio} · Piso ${s.piso}</div>

        <div class="info-row"><span class="info-key">Capacidad</span><span class="info-val">${s.cap} personas</span></div>
        <div class="info-row"><span class="info-key">Equipamiento</span><span class="info-val">${s.equip.join(' ')}</span></div>
        ${s.estado === 'ocupado' ? `
        <div class="info-row"><span class="info-key">Materia</span><span class="info-val">${s.materia}</span></div>
        <div class="info-row"><span class="info-key">Docente</span><span class="info-val">${s.docente}</span></div>
        <div class="info-row"><span class="info-key">Grupo</span><span class="info-val">${s.grupo}</span></div>
        <div class="info-row"><span class="info-key">Libre a las</span><span class="info-val" style="color:var(--green)">${s.hasta}</span></div>
        ` : ''}
        ${s.estado === 'mantenimiento' ? `
        <div class="info-row"><span class="info-key">Motivo</span><span class="info-val" style="color:var(--yellow)">${s.motivo}</span></div>
        ` : ''}
        ${s.estado === 'libre' ? `
        <div class="info-row"><span class="info-key">Libre hasta</span><span class="info-val" style="color:var(--green)">${s.hasta}</span></div>
        ` : ''}

        <div class="panel-actions">
          ${s.estado === 'libre' ? `<button class="panel-btn success" onclick="alert('TODO: Formulario de asignación rápida')">📅 Asignar horario</button>` : ''}
          ${s.estado === 'ocupado' ? `<button class="panel-btn" onclick="alert('TODO: Confirmar ausencia de docente')">👤 Reportar ausencia docente</button>` : ''}
          <button class="panel-btn danger" onclick="alert('TODO: Modal de reporte de incidencia')">⚠️ Reportar incidencia</button>
          ${s.estado !== 'mantenimiento' ? `<button class="panel-btn" onclick="alert('TODO: Cambiar a estado mantenimiento')">🔧 Pasar a mantenimiento</button>` : `<button class="panel-btn success" onclick="alert('TODO: Marcar mantenimiento como finalizado')">✅ Marcar como disponible</button>`}
          <button class="panel-btn" onclick="alert('TODO: Ver historial de ocupación')">📊 Ver historial</button>
        </div>
      `;

            panel.classList.add('open');
            document.getElementById('overlay').classList.add('show');
        }

        function closePanel() {
            document.getElementById('detailPanel').classList.remove('open');
            document.getElementById('overlay').classList.remove('show');
        }

        // ── Helpers ──
        const tipoLabel = t => ({ salon: 'Salón', laboratorio: 'Laboratorio', computo: 'Cómputo', usos_multiples: 'Usos múltiples' }[t] || t);
        const estadoLabel = e => ({ libre: 'Libre', ocupado: 'Ocupado', mantenimiento: 'Mantenimiento' }[e] || e);

        // ── Clock ──
        function updateClock() {
            const now = new Date();
            const d = now.toLocaleDateString('es-MX', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            const t = now.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
            document.getElementById('current-time').textContent = `${d} · ${t}`;
        }
        updateClock(); setInterval(updateClock, 1000);

        // ── Auto-refresh every 30s (simulated) ──
        setInterval(() => { refreshGrid(); }, 30000);

        // ── User session ──
        const session = JSON.parse(sessionStorage.getItem('nx_user') || '{"email":"admin@universidad.edu.mx","role":"admin"}');
        document.getElementById('user-name').textContent = session.email.split('@')[0];
        document.getElementById('user-role').textContent = session.role;
        document.getElementById('user-avatar').textContent = session.email[0].toUpperCase();
        if (session.role !== 'admin') {
            document.getElementById('admin-nav').style.display = 'none';
        }

        function logout() {
            sessionStorage.removeItem('nx_user');
            window.location.href = 'login.php';
        }

        // ── Init ──
        renderGrid();
    </script>
</body>

</html>