<?php
require_once 'db.php';
try {
    $db = getDB();

    $kpiEspacios = (int) $db->query('SELECT COUNT(*) FROM espacios')->fetchColumn();
    $kpiIncidencias = (int) $db->query("SELECT COUNT(*) FROM incidencias WHERE estado = 'abierta'")->fetchColumn();
    $kpiHoras = (int) $db->query("SELECT COALESCE(SUM(TIMESTAMPDIFF(HOUR, hora_inicio, hora_fin)), 0) FROM horarios WHERE fecha_inicio_vigencia <= CURDATE() AND fecha_fin_vigencia >= CURDATE()")->fetchColumn();
    $totalMinutos = (int) $db->query("SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, hora_inicio, hora_fin)), 0) FROM horarios WHERE fecha_inicio_vigencia <= CURDATE() AND fecha_fin_vigencia >= CURDATE()")->fetchColumn();
    $ocupacionProm = $kpiEspacios > 0 ? min(100, round($totalMinutos / ($kpiEspacios * 40 * 60) * 100)) : 0;

    $stmt = $db->query('SELECT e.nombre AS espacio, e.tipo, e.edificio, e.capacidad, e.estado, COALESCE(SUM(TIMESTAMPDIFF(HOUR, h.hora_inicio, h.hora_fin)), 0) AS horasSem, COUNT(i.id) AS incidencias FROM espacios e LEFT JOIN horarios h ON h.espacio_id = e.id AND h.fecha_inicio_vigencia <= CURDATE() AND h.fecha_fin_vigencia >= CURDATE() LEFT JOIN incidencias i ON i.espacio_id = e.id AND i.estado = "abierta" GROUP BY e.id ORDER BY horasSem DESC');
    $reporteRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($reporteRows as &$row) {
        $row['pctOcup'] = min(100, round($row['horasSem'] / 40 * 100));
        if ($row['pctOcup'] < 0) {
            $row['pctOcup'] = 0;
        }
    }
    unset($row);

    $stmt = $db->query("SELECT dia_semana, COUNT(*) AS total FROM horarios WHERE fecha_inicio_vigencia <= CURDATE() AND fecha_fin_vigencia >= CURDATE() GROUP BY dia_semana");
    $dias = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $diasMap = ['Monday' => 'Lun', 'Tuesday' => 'Mar', 'Wednesday' => 'Mié', 'Thursday' => 'Jue', 'Friday' => 'Vie', 'Saturday' => 'Sáb'];
    $diasData = ['Lun' => 0, 'Mar' => 0, 'Mié' => 0, 'Jue' => 0, 'Vie' => 0, 'Sáb' => 0];
    foreach ($dias as $dia => $tot) {
        if (isset($diasMap[$dia])) {
            $diasData[$diasMap[$dia]] = (int) $tot;
        }
    }

    $stmt = $db->query('SELECT tipo, COUNT(*) AS total FROM espacios GROUP BY tipo');
    $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $tipoLabels = ['salon' => 'Salones', 'laboratorio' => 'Laboratorio', 'computo' => 'Cómputo', 'usos_multiples' => 'Usos múlt.'];
    $tiposData = [];
    $colors = ['salon' => '#f59e0b', 'laboratorio' => '#22c55e', 'computo' => '#4f8ef7', 'usos_multiples' => '#a855f7'];
    foreach ($tipos as $row) {
        $tiposData[] = [
            'label' => $tipoLabels[$row['tipo']] ?? ucfirst($row['tipo']),
            'pct' => min(100, max(0, round($row['total'] * 10))),
            'color' => $colors[$row['tipo']] ?? '#4f8ef7',
        ];
    }

    $stmt = $db->query('SELECT i.id, e.nombre AS espacio, i.tipo, i.prioridad, i.created_at FROM incidencias i JOIN espacios e ON e.id = i.espacio_id ORDER BY i.created_at DESC LIMIT 8');
    $incidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->query('SELECT e.nombre AS espacio, COALESCE(SUM(TIMESTAMPDIFF(HOUR, h.hora_inicio, h.hora_fin)), 0) AS horasSem FROM espacios e LEFT JOIN horarios h ON h.espacio_id = e.id AND h.fecha_inicio_vigencia <= CURDATE() AND h.fecha_fin_vigencia >= CURDATE() GROUP BY e.id ORDER BY horasSem DESC LIMIT 5');
    $topEspacios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $kpiEspacios = 0;
    $kpiIncidencias = 0;
    $kpiHoras = 0;
    $ocupacionProm = 0;
    $reporteRows = [];
    $diasData = ['Lun' => 0, 'Mar' => 0, 'Mié' => 0, 'Jue' => 0, 'Vie' => 0, 'Sáb' => 0];
    $tiposData = [];
    $incidencias = [];
    $topEspacios = [];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>NEXUS Academic — Reportes</title>
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
            padding: 0
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
            --purple: #a855f7;
        }

        html,
        body {
            height: 100%;
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            overflow: hidden
        }

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
            padding: 28px 16px
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 8px 28px;
            border-bottom: 1px solid var(--border)
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
            color: #fff
        }

        .brand-name {
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 16px
        }

        .brand-sub {
            font-size: 10px;
            color: var(--muted);
            letter-spacing: 1.2px;
            text-transform: uppercase
        }

        .nav-section {
            margin-top: 24px
        }

        .nav-label {
            font-size: 10px;
            color: var(--muted);
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 0 12px;
            margin-bottom: 8px
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
            margin-bottom: 2px
        }

        .nav-item:hover {
            background: var(--surface2);
            color: var(--text)
        }

        .nav-item.active {
            background: rgba(79, 142, 247, .12);
            color: var(--accent)
        }

        .nav-item .ico {
            font-size: 17px;
            width: 20px;
            text-align: center
        }

        .nav-badge {
            margin-left: auto;
            background: var(--red);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 100px
        }

        .sidebar-footer {
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid var(--border)
        }

        .user-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px
        }

        .avatar {
            width: 32px;
            height: 32;
            border-radius: 8px;
            background: linear-gradient(135deg, #4f8ef7, #22c55e);
            display: grid;
            place-items: center;
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 12px;
            color: #fff
        }

        .user-name {
            font-size: 13px;
            font-weight: 500
        }

        .user-role {
            font-size: 11px;
            color: var(--muted);
            text-transform: capitalize
        }

        .logout-btn {
            margin-left: auto;
            background: none;
            border: none;
            color: var(--muted);
            font-size: 17px;
            cursor: pointer;
            transition: color .2s
        }

        .logout-btn:hover {
            color: var(--red)
        }

        .main {
            margin-left: 240px;
            height: 100vh;
            overflow-y: auto;
            padding: 32px 36px
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px
        }

        .page-title {
            font-family: 'Syne', sans-serif;
            font-size: 22px;
            font-weight: 700
        }

        .page-sub {
            font-size: 13px;
            color: var(--muted);
            margin-top: 2px
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
            text-decoration: none
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid var(--border);
            color: var(--muted)
        }

        .btn-outline:hover {
            border-color: var(--border-hi);
            color: var(--text)
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent), #3a7ae0);
            color: #fff;
            box-shadow: 0 4px 14px rgba(79, 142, 247, .35)
        }

        .btn-primary:hover {
            box-shadow: 0 6px 20px rgba(79, 142, 247, .5)
        }

        .btn-green {
            background: linear-gradient(135deg, var(--green), #16a34a);
            color: #fff;
            box-shadow: 0 4px 14px rgba(34, 197, 94, .3)
        }

        .kpi-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px
        }

        .kpi {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px 20px;
            position: relative;
            overflow: hidden;
            transition: border-color .2s
        }

        .kpi-val {
            font-family: 'Syne', sans-serif;
            font-size: 28px;
            font-weight: 700;
            line-height: 1
        }

        .kpi-lbl {
            font-size: 11px;
            color: var(--muted);
            margin-top: 5px
        }

        .kpi-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 24px;
            opacity: .2
        }

        .kpi.blue {
            border-left: 3px solid var(--accent)
        }

        .kpi.blue .kpi-val {
            color: var(--accent)
        }

        .kpi.green {
            border-left: 3px solid var(--green)
        }

        .kpi.green .kpi-val {
            color: var(--green)
        }

        .kpi.purple {
            border-left: 3px solid var(--purple)
        }

        .kpi.yellow {
            border-left: 3px solid var(--yellow)
        }

        .kpi.yellow .kpi-val {
            color: var(--yellow)
        }

        .reports-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px
        }

        .report-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px
        }

        .rc-title {
            font-family: 'Syne', sans-serif;
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 4px
        }

        .rc-sub {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 20px
        }

        .rc-actions {
            display: flex;
            gap: 8px;
            margin-top: 16px
        }

        .bar-list {
            display: flex;
            flex-direction: column;
            gap: 10px
        }

        .bar-item {
            display: flex;
            align-items: center;
            gap: 12px
        }

        .bar-label {
            font-size: 12px;
            width: 60px;
            flex-shrink: 0;
            color: var(--muted);
            text-align: right
        }

        .bar-track {
            flex: 1;
            height: 8px;
            background: var(--surface2);
            border-radius: 100px;
            overflow: hidden
        }

        .bar-fill {
            height: 100%;
            border-radius: 100px;
            transition: width .6s cubic-bezier(.22, 1, .36, 1)
        }

        .bar-val {
            font-size: 12px;
            width: 36px;
            text-align: right;
            font-weight: 600
        }

        .chart-svg {
            width: 100%;
            overflow: visible
        }

        .chart-bar {
            transition: opacity .15s
        }

        .chart-bar:hover {
            opacity: .75;
            cursor: pointer
        }

        .mini-table {
            width: 100%;
            border-collapse: collapse
        }

        .mini-table th {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .6px;
            padding: 8px 10px;
            border-bottom: 1px solid var(--border);
            text-align: left
        }

        .mini-table td {
            font-size: 13px;
            padding: 10px 10px;
            border-bottom: 1px solid var(--border)
        }

        .mini-table tr:last-child td {
            border-bottom: none
        }

        .mini-table tr:hover td {
            background: var(--surface2)
        }

        .badge {
            display: inline-flex;
            align-items: center;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 100px;
            text-transform: uppercase
        }

        .badge.alta {
            background: var(--red-bg);
            color: var(--red);
            border: 1px solid var(--red-bd)
        }

        .badge.media {
            background: var(--yellow-bg);
            color: var(--yellow);
            border: 1px solid var(--yellow-bd)
        }

        .badge.baja {
            background: var(--green-bg);
            color: var(--green);
            border: 1px solid var(--green-bd)
        }

        .badge.falla {
            background: var(--red-bg);
            color: var(--red);
            border: 1px solid var(--red-bd)
        }

        .badge.ausencia_docente {
            background: rgba(168, 85, 247, .1);
            color: #a855f7;
            border: 1px solid rgba(168, 85, 247, .25)
        }

        .badge.otro {
            background: rgba(79, 142, 247, .1);
            color: var(--accent);
            border: 1px solid rgba(79, 142, 247, .3)
        }

        .export-section {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px
        }

        .exp-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px
        }

        .exp-title {
            font-family: 'Syne', sans-serif;
            font-size: 15px;
            font-weight: 700
        }

        .exp-table {
            width: 100%;
            border-collapse: collapse
        }

        .exp-table th {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .6px;
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            text-align: left;
            background: var(--surface2)
        }

        .exp-table td {
            font-size: 13px;
            padding: 10px 12px;
            border-bottom: 1px solid var(--border)
        }

        .exp-table tr:last-child td {
            border-bottom: none
        }

        .exp-table tr:hover td {
            background: var(--surface2)
        }

        .pct-bar {
            display: inline-flex;
            align-items: center;
            gap: 8px
        }

        .pct-mini {
            width: 60px;
            height: 5px;
            background: var(--surface2);
            border-radius: 100px;
            overflow: hidden
        }

        .pct-mini-fill {
            height: 100%;
            border-radius: 100px;
            background: var(--accent)
        }

        .toast {
            position: fixed;
            bottom: 28px;
            right: 28px;
            z-index: 999;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, .4);
            transform: translateY(80px);
            opacity: 0;
            transition: all .3s cubic-bezier(.22, 1, .36, 1)
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1
        }

        .toast.success {
            border-left: 3px solid var(--green)
        }

        @media(max-width:1100px) {
            .reports-grid {
                grid-template-columns: 1fr
            }
        }

        @media(max-width:900px) {
            .sidebar {
                display: none
            }

            .main {
                margin-left: 0;
                padding: 16px
            }

            .kpi-row {
                grid-template-columns: 1fr 1fr
            }
        }
    </style>
</head>

<body>
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-icon">NX</div>
            <div>
                <div class="brand-name">NEXUS</div>
                <div class="brand-sub">Academic</div>
            </div>
        </div>
        <nav class="nav-section">
            <div class="nav-label">Principal</div>
            <a class="nav-item" href="dashboard.php"><span class="ico">📊</span> Dashboard</a>
            <a class="nav-item" href="espacios.php"><span class="ico">🏛️</span> Espacios</a>
            <a class="nav-item" href="horarios.php"><span class="ico">📅</span> Horarios</a>
        </nav>
        <nav class="nav-section">
            <div class="nav-label">Operación</div>
            <a class="nav-item" href="incidencias.php"><span class="ico">⚠️</span> Incidencias <span
                    class="nav-badge"><?= $kpiIncidencias ?></span></a>
            <a class="nav-item" href="mantenimiento.php"><span class="ico">🔧</span> Mantenimiento</a>
            <a class="nav-item" href="buscador.php"><span class="ico">🔍</span> Buscador Inteligente</a>
        </nav>
        <nav class="nav-section" id="admin-nav">
            <div class="nav-label">Administración</div>
            <a class="nav-item" href="usuarios.php"><span class="ico">👥</span> Usuarios</a>
            <a class="nav-item active" href="reportes.php"><span class="ico">📈</span> Reportes</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-chip">
                <div class="avatar" id="user-avatar">A</div>
                <div>
                    <div class="user-name" id="user-name">Admin</div>
                    <div class="user-role" id="user-role">admin</div>
                </div>
                <button class="logout-btn" onclick="logout()">⏻</button>
            </div>
        </div>
    </aside>
    <div class="toast" id="toast"></div>
    <main class="main">
        <div class="topbar">
            <div>
                <h1 class="page-title">📈 Reportes</h1>
                <p class="page-sub">Análisis de ocupación, incidencias y toma de decisiones</p>
            </div>
            <div style="display:flex;gap:10px"><a class="btn btn-outline" href="dashboard.html">← Dashboard</a><button
                    class="btn btn-green" onclick="exportarCSV()">⬇️ Exportar CSV</button></div>
        </div>
        <div class="kpi-row">
            <div class="kpi blue">
                <div class="kpi-val" id="rk-espacios"><?= $kpiEspacios ?></div>
                <div class="kpi-lbl">Espacios totales</div>
                <div class="kpi-icon">🏛️</div>
            </div>
            <div class="kpi green">
                <div class="kpi-val" id="rk-ocup"><?= $ocupacionProm ?>%</div>
                <div class="kpi-lbl">Ocupación promedio</div>
                <div class="kpi-icon">📊</div>
            </div>
            <div class="kpi yellow">
                <div class="kpi-val" id="rk-incid"><?= $kpiIncidencias ?></div>
                <div class="kpi-lbl">Incidencias del mes</div>
                <div class="kpi-icon">⚠️</div>
            </div>
            <div class="kpi purple">
                <div class="kpi-val" id="rk-horas"><?= $kpiHoras ?></div>
                <div class="kpi-lbl">Horas impartidas/sem</div>
                <div class="kpi-icon">⏱️</div>
            </div>
        </div>
        <div class="reports-grid">
            <div class="report-card">
                <div class="rc-title">📅 Ocupación por día de la semana</div>
                <div class="rc-sub">Porcentaje promedio de espacios en uso por día</div>
                <svg class="chart-svg" viewBox="0 0 400 160" id="chartDias"></svg>
                <div class="rc-actions"><button class="btn btn-outline" style="font-size:12px"
                        onclick="exportarCSV('dias')">⬇️ Exportar</button></div>
            </div>
            <div class="report-card">
                <div class="rc-title">🏛️ Ocupación por tipo de espacio</div>
                <div class="rc-sub">Uso promedio semanal según categoría</div>
                <div class="bar-list" id="barTipos"></div>
                <div class="rc-actions"><button class="btn btn-outline" style="font-size:12px"
                        onclick="exportarCSV('tipos')">⬇️ Exportar</button></div>
            </div>
            <div class="report-card">
                <div class="rc-title">⚠️ Incidencias recientes</div>
                <div class="rc-sub">Últimas 8 incidencias del sistema</div>
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>Espacio</th>
                            <th>Tipo</th>
                            <th>Prioridad</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody id="incidTable"></tbody>
                </table>
                <div class="rc-actions"><button class="btn btn-outline" style="font-size:12px"
                        onclick="exportarCSV('incidencias')">⬇️ Exportar</button></div>
            </div>
            <div class="report-card">
                <div class="rc-title">🏆 Espacios más utilizados</div>
                <div class="rc-sub">Ranking por horas programadas en la semana actual</div>
                <div class="bar-list" id="barEspacios"></div>
                <div class="rc-actions"><button class="btn btn-outline" style="font-size:12px"
                        onclick="exportarCSV('espacios')">⬇️ Exportar</button></div>
            </div>
        </div>
        <div class="export-section">
            <div class="exp-header">
                <div>
                    <div class="exp-title">📋 Reporte de ocupación por espacio</div>
                    <div style="font-size:12px;color:var(--muted);margin-top:3px">Semana actual · Ordenado por horas
                        programadas</div>
                </div>
                <button class="btn btn-green" onclick="exportarCSV('completo')">⬇️ Exportar CSV completo</button>
            </div>
            <table class="exp-table" id="mainTable">
                <thead>
                    <tr>
                        <th>Espacio</th>
                        <th>Tipo</th>
                        <th>Edificio</th>
                        <th>Capacidad</th>
                        <th>Horas/sem</th>
                        <th>% Ocupación</th>
                        <th>Incidencias</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody id="mainTableBody"></tbody>
            </table>
        </div>
    </main>
    <script>
        const REPORTE = <?= json_encode($reporteRows, JSON_UNESCAPED_UNICODE) ?>;
        const DIAS_DATA = <?= json_encode($diasData, JSON_UNESCAPED_UNICODE) ?>;
        const TIPOS_DATA = <?= json_encode($tiposData, JSON_UNESCAPED_UNICODE) ?>;
        const INCID_DATA = <?= json_encode($incidencias, JSON_UNESCAPED_UNICODE) ?>;
        const ESPACIOS_TOP = <?= json_encode($topEspacios, JSON_UNESCAPED_UNICODE) ?>;
        function drawDiasChart() { const svg = document.getElementById('chartDias'); const dias = Object.entries(DIAS_DATA); const W = 400, H = 140, pad = 30, barW = 38, gap = (W - pad * 2 - barW * dias.length) / (dias.length - 1); let html = ''; let maxPct = Math.max(...dias.map(([_, v]) => v), 1); dias.forEach(([dia, pct], i) => { const h = Math.max(4, (pct / maxPct) * 100); html += `<rect class="chart-bar" x="${pad + i * (barW + gap)}" y="${H - 24 - h}" width="${barW}" height="${h}" fill="#4f8ef7"></rect><text x="${pad + i * (barW + gap) + barW / 2}" y="${H - 24 - h - 8}" text-anchor="middle" font-size="11" fill="#e2eaf7">${pct}</text><text x="${pad + i * (barW + gap) + barW / 2}" y="${H - 4}" text-anchor="middle" font-size="11" fill="#9bb0d6">${dia}</text>`; }); svg.innerHTML = html; }
        function renderTipos() { const container = document.getElementById('barTipos'); container.innerHTML = TIPOS_DATA.map(item => `<div class="bar-item"><span class="bar-label">${item.label}</span><div class="bar-track"><div class="bar-fill" style="width:${item.pct}%;background:${item.color}"></div></div><span class="bar-val">${item.pct}%</span></div>`).join(''); }
        function renderIncidencias() { const body = document.getElementById('incidTable'); body.innerHTML = INCID_DATA.map(item => `<tr><td>${item.espacio}</td><td>${item.tipo}</td><td><span class="badge ${item.prioridad}">${item.prioridad}</span></td><td>${item.created_at.split(' ')[0]}</td></tr>`).join(''); }
        function renderTopEspacios() { const container = document.getElementById('barEspacios'); container.innerHTML = ESPACIOS_TOP.map(item => `<div class="bar-item"><span class="bar-label">${item.espacio}</span><div class="bar-track"><div class="bar-fill" style="width:${Math.min(100, item.horasSem * 2)}%;"></div></div><span class="bar-val">${item.horasSem}h</span></div>`).join(''); }
        function renderMainTable() { const body = document.getElementById('mainTableBody'); body.innerHTML = REPORTE.map(item => `<tr><td>${item.espacio}</td><td>${item.tipo}</td><td>${item.edificio}</td><td>${item.capacidad}</td><td>${item.horasSem}</td><td>${item.pctOcup}%</td><td>${item.incidencias}</td><td>${item.estado}</td></tr>`).join(''); }
        function exportarCSV(type = 'completo') { alert('Exportar CSV no implementado aun para tipo: ' + type); } function logout() { sessionStorage.removeItem('nx_user'); window.location.href = 'login_page.php'; }
        window.addEventListener('DOMContentLoaded', () => { drawDiasChart(); renderTipos(); renderIncidencias(); renderTopEspacios(); renderMainTable(); });
    </script>
</body>

</html>