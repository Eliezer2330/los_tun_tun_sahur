<?php
session_start();
$dbHost = 'localhost'; $dbPort = '3308'; $dbName = 'sigmea';
$dbUser = 'root';      $dbPass = '';

$incidencias = []; $kpi = ['abiertas'=>0,'en_proceso'=>0,'cerradas'=>0,'alta'=>0]; $dbError = null;

try {
    $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $incidencias = $pdo->query('
        SELECT i.id, i.tipo, i.descripcion, i.prioridad, i.estado,
               DATE_FORMAT(i.created_at,"%d/%m/%Y %H:%i") AS fecha,
               DATE_FORMAT(i.closed_at,"%d/%m/%Y %H:%i") AS cerrada_en,
               e.nombre AS espacio_nombre,
               u.nombre AS reportado_por
        FROM incidencias i
        JOIN espacios e ON e.id = i.espacio_id
        JOIN usuarios u ON u.id = i.reportado_por
        ORDER BY FIELD(i.prioridad,"alta","media","baja"),
                 FIELD(i.estado,"abierta","en_proceso","cerrada"),
                 i.created_at DESC
    ')->fetchAll();
    foreach ($incidencias as $i) {
        if (isset($kpi[$i['estado']])) $kpi[$i['estado']]++;
        if ($i['prioridad'] === 'alta') $kpi['alta']++;
    }
} catch (PDOException $e) { $dbError = $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SIGMEA — Incidencias</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#070c18;--surface:#0d1526;--surface2:#111d33;--border:#1a2840;--border-hi:#2a4070;--text:#e2eaf7;--muted:#5a7090;--accent:#4f8ef7;--green:#22c55e;--green-bg:rgba(34,197,94,.1);--green-bd:rgba(34,197,94,.25);--red:#ef4444;--red-bg:rgba(239,68,68,.1);--red-bd:rgba(239,68,68,.25);--yellow:#f59e0b;--yellow-bg:rgba(245,158,11,.1);--yellow-bd:rgba(245,158,11,.25)}
html,body{height:100%;font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);overflow:hidden}
.sidebar{position:fixed;top:0;left:0;bottom:0;width:240px;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;z-index:100;padding:28px 16px}
.brand{display:flex;align-items:center;gap:12px;padding:0 8px 28px;border-bottom:1px solid var(--border)}
.brand-icon{width:36px;height:36px;background:linear-gradient(135deg,var(--accent),#6fb4ff);border-radius:8px;display:grid;place-items:center;font-family:'Syne',sans-serif;font-weight:800;font-size:14px;color:#fff}
.brand-name{font-family:'Syne',sans-serif;font-weight:700;font-size:16px}
.brand-sub{font-size:10px;color:var(--muted);letter-spacing:1.2px;text-transform:uppercase}
.nav-section{margin-top:24px}
.nav-label{font-size:10px;color:var(--muted);letter-spacing:1.5px;text-transform:uppercase;padding:0 12px;margin-bottom:8px}
.nav-item{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:8px;font-size:14px;color:var(--muted);cursor:pointer;transition:all .15s;text-decoration:none;margin-bottom:2px}
.nav-item:hover{background:var(--surface2);color:var(--text)}
.nav-item.active{background:rgba(79,142,247,.12);color:var(--accent)}
.nav-item .ico{font-size:17px;width:20px;text-align:center}
.nav-badge{margin-left:auto;background:var(--red);color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:100px}
.sidebar-footer{margin-top:auto;padding-top:16px;border-top:1px solid var(--border)}
.user-chip{display:flex;align-items:center;gap:10px;padding:10px 12px}
.avatar{width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,#4f8ef7,#22c55e);display:grid;place-items:center;font-family:'Syne',sans-serif;font-weight:700;font-size:12px;color:#fff}
.main{margin-left:240px;height:100vh;overflow-y:auto;padding:32px 36px}
.topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px}
.page-title{font-family:'Syne',sans-serif;font-size:22px;font-weight:700}
.page-sub{font-size:13px;color:var(--muted);margin-top:2px}
.kpi-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px}
.kpi{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px 22px;position:relative;overflow:hidden}
.kpi-val{font-family:'Syne',sans-serif;font-size:32px;font-weight:700;line-height:1}
.kpi-lbl{font-size:12px;color:var(--muted);margin-top:6px}
.kpi-icon{position:absolute;right:18px;top:50%;transform:translateY(-50%);font-size:28px;opacity:.25}
.kpi.red{border-left:3px solid var(--red)}.kpi.red .kpi-val{color:var(--red)}
.kpi.yellow{border-left:3px solid var(--yellow)}.kpi.yellow .kpi-val{color:var(--yellow)}
.kpi.green{border-left:3px solid var(--green)}.kpi.green .kpi-val{color:var(--green)}
.kpi.blue{border-left:3px solid var(--accent)}.kpi.blue .kpi-val{color:var(--accent)}
.table-wrap{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden}
.table-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border)}
.table-title{font-family:'Syne',sans-serif;font-size:14px;font-weight:600}
.search-box{position:relative}
.search-box input{background:var(--surface2);border:1.5px solid var(--border);border-radius:8px;padding:7px 12px 7px 34px;color:var(--text);font-size:13px;outline:none;width:210px;transition:border-color .2s}
.search-box input:focus{border-color:var(--accent)}
.search-box input::placeholder{color:var(--muted)}
.search-ico{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px}
table{width:100%;border-collapse:collapse}
thead th{padding:12px 20px;text-align:left;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;font-weight:500;background:var(--surface2)}
tbody tr{border-top:1px solid var(--border);transition:background .15s}
tbody tr:hover{background:var(--surface2)}
tbody td{padding:13px 20px;font-size:13px}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:100px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.5px}
.badge.alta{background:var(--red-bg);color:var(--red);border:1px solid var(--red-bd)}
.badge.media{background:var(--yellow-bg);color:var(--yellow);border:1px solid var(--yellow-bd)}
.badge.baja{background:rgba(79,142,247,.1);color:var(--accent);border:1px solid rgba(79,142,247,.25)}
.badge.abierta{background:var(--red-bg);color:var(--red);border:1px solid var(--red-bd)}
.badge.en_proceso{background:var(--yellow-bg);color:var(--yellow);border:1px solid var(--yellow-bd)}
.badge.cerrada{background:var(--green-bg);color:var(--green);border:1px solid var(--green-bd)}
.desc-cell{max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--muted);font-size:12px}
.empty-state{text-align:center;padding:60px 20px;color:var(--muted)}
.empty-state .ico{font-size:48px;margin-bottom:12px}
.err-box{background:var(--red-bg);border:1px solid var(--red-bd);color:var(--red);padding:16px 20px;border-radius:10px;margin-bottom:20px;font-size:13px}
</style>
</head>
<body>
<aside class="sidebar">
    <div class="brand">
        <div class="brand-icon">SG</div>
        <div><div class="brand-name">SIGMEA</div><div class="brand-sub">Sistema de Gestión</div></div>
    </div>
    <nav class="nav-section">
        <div class="nav-label">Principal</div>
        <a class="nav-item" href="dashboard.php"><span class="ico">📊</span> Dashboard</a>
        <a class="nav-item" href="espacios.php"><span class="ico">🏛️</span> Espacios</a>
        <a class="nav-item" href="horarios.php"><span class="ico">📅</span> Horarios</a>
    </nav>
    <nav class="nav-section">
        <div class="nav-label">Operación</div>
        <a class="nav-item active" href="incidencias.php"><span class="ico">⚠️</span> Incidencias
            <?php if ($kpi['abiertas'] > 0): ?><span class="nav-badge"><?= $kpi['abiertas'] ?></span><?php endif; ?>
        </a>
        <a class="nav-item" href="mantenimiento.php"><span class="ico">🔧</span> Mantenimiento</a>
        <a class="nav-item" href="buscador.php"><span class="ico">🔍</span> Buscador Inteligente</a>
    </nav>
    <nav class="nav-section">
        <div class="nav-label">Administración</div>
        <a class="nav-item" href="usuarios.php"><span class="ico">👥</span> Usuarios</a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-chip">
            <div class="avatar">A</div>
            <div><div style="font-size:13px;font-weight:500">Administrador</div><div style="font-size:11px;color:var(--muted)">admin</div></div>
        </div>
    </div>
</aside>

<main class="main">
    <div class="topbar">
        <div>
            <h1 class="page-title">⚠️ Incidencias</h1>
            <p class="page-sub">Seguimiento de reportes y fallas</p>
        </div>
    </div>

    <?php if ($dbError): ?>
    <div class="err-box">❌ Error de conexión: <?= htmlspecialchars($dbError) ?></div>
    <?php endif; ?>

    <div class="kpi-row">
        <div class="kpi red"><div class="kpi-val"><?= $kpi['abiertas'] ?></div><div class="kpi-lbl">Abiertas</div><div class="kpi-icon">🔴</div></div>
        <div class="kpi yellow"><div class="kpi-val"><?= $kpi['en_proceso'] ?></div><div class="kpi-lbl">En proceso</div><div class="kpi-icon">🟡</div></div>
        <div class="kpi green"><div class="kpi-val"><?= $kpi['cerradas'] ?></div><div class="kpi-lbl">Cerradas</div><div class="kpi-icon">✅</div></div>
        <div class="kpi blue"><div class="kpi-val"><?= $kpi['alta'] ?></div><div class="kpi-lbl">Alta prioridad</div><div class="kpi-icon">🚨</div></div>
    </div>

    <div class="table-wrap">
        <div class="table-header">
            <span class="table-title">Todas las incidencias</span>
            <div class="search-box">
                <span class="search-ico">🔎</span>
                <input type="text" id="searchInput" placeholder="Buscar…" oninput="filterTable(this.value)">
            </div>
        </div>
        <table id="mainTable">
            <thead>
                <tr><th>#</th><th>Espacio</th><th>Tipo</th><th>Descripción</th><th>Prioridad</th><th>Estado</th><th>Reportado por</th><th>Fecha</th></tr>
            </thead>
            <tbody>
            <?php if (empty($incidencias)): ?>
            <tr><td colspan="8"><div class="empty-state"><div class="ico">✅</div><p>No hay incidencias registradas.</p></div></td></tr>
            <?php else: ?>
            <?php foreach ($incidencias as $i): ?>
            <?php $tipoLabel = ['falla'=>'Falla','ausencia_docente'=>'Ausencia docente','otro'=>'Otro'][$i['tipo']] ?? $i['tipo']; ?>
            <tr>
                <td style="color:var(--muted)">#<?= $i['id'] ?></td>
                <td><strong><?= htmlspecialchars($i['espacio_nombre']) ?></strong></td>
                <td><?= $tipoLabel ?></td>
                <td class="desc-cell" title="<?= htmlspecialchars($i['descripcion']) ?>"><?= htmlspecialchars($i['descripcion']) ?></td>
                <td><span class="badge <?= $i['prioridad'] ?>"><?= ucfirst($i['prioridad']) ?></span></td>
                <td><span class="badge <?= $i['estado'] ?>"><?= ucfirst(str_replace('_',' ',$i['estado'])) ?></span></td>
                <td><?= htmlspecialchars($i['reportado_por']) ?></td>
                <td style="color:var(--muted);font-size:12px"><?= $i['fecha'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<script>
function filterTable(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#mainTable tbody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>
</body>
</html>
