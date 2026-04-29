<?php
session_start();
$dbHost = 'localhost'; $dbPort = '3308'; $dbName = 'sigmea';
$dbUser = 'root';      $dbPass = '';

$horarios = []; $dbError = null;
$dias = ['Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado'];

try {
    $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $horarios = $pdo->query('
        SELECT h.id, h.grupo, h.materia, h.dia_semana,
               TIME_FORMAT(h.hora_inicio,"%H:%i") AS hora_inicio,
               TIME_FORMAT(h.hora_fin,"%H:%i") AS hora_fin,
               h.fecha_inicio_vigencia, h.fecha_fin_vigencia,
               e.nombre AS espacio_nombre, e.tipo AS espacio_tipo, e.edificio,
               u.nombre AS docente_nombre
        FROM horarios h
        JOIN espacios e ON e.id = h.espacio_id
        JOIN usuarios u ON u.id = h.docente_id
        WHERE h.fecha_inicio_vigencia <= CURDATE() AND h.fecha_fin_vigencia >= CURDATE()
        ORDER BY FIELD(h.dia_semana,"Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"), h.hora_inicio
    ')->fetchAll();
} catch (PDOException $e) { $dbError = $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SIGMEA — Horarios</title>
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
.sidebar-footer{margin-top:auto;padding-top:16px;border-top:1px solid var(--border)}
.user-chip{display:flex;align-items:center;gap:10px;padding:10px 12px}
.avatar{width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,#4f8ef7,#22c55e);display:grid;place-items:center;font-family:'Syne',sans-serif;font-weight:700;font-size:12px;color:#fff}
.main{margin-left:240px;height:100vh;overflow-y:auto;padding:32px 36px}
.topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px}
.page-title{font-family:'Syne',sans-serif;font-size:22px;font-weight:700}
.page-sub{font-size:13px;color:var(--muted);margin-top:2px}
.day-section{margin-bottom:28px}
.day-label{font-family:'Syne',sans-serif;font-size:13px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:1.5px;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid var(--border)}
.table-wrap{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:8px}
.table-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border)}
.table-title{font-family:'Syne',sans-serif;font-size:14px;font-weight:600}
.search-box{position:relative}
.search-box input{background:var(--surface2);border:1.5px solid var(--border);border-radius:8px;padding:7px 12px 7px 34px;color:var(--text);font-size:13px;outline:none;width:220px;transition:border-color .2s}
.search-box input:focus{border-color:var(--accent)}
.search-box input::placeholder{color:var(--muted)}
.search-ico{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px}
table{width:100%;border-collapse:collapse}
thead th{padding:12px 20px;text-align:left;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;font-weight:500;background:var(--surface2)}
tbody tr{border-top:1px solid var(--border);transition:background .15s}
tbody tr:hover{background:var(--surface2)}
tbody td{padding:13px 20px;font-size:13px}
.badge-type{display:inline-block;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;background:rgba(79,142,247,.12);color:var(--accent);border:1px solid rgba(79,142,247,.25)}
.today-badge{background:rgba(34,197,94,.15);color:var(--green);border-color:rgba(34,197,94,.3);font-size:10px;padding:2px 7px;border-radius:100px;margin-left:8px;font-weight:600}
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
        <a class="nav-item active" href="horarios.php"><span class="ico">📅</span> Horarios</a>
    </nav>
    <nav class="nav-section">
        <div class="nav-label">Operación</div>
        <a class="nav-item" href="incidencias.php"><span class="ico">⚠️</span> Incidencias</a>
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
            <h1 class="page-title">📅 Horarios</h1>
            <p class="page-sub">Horarios vigentes por día de la semana</p>
        </div>
        <div class="search-box">
            <span class="search-ico">🔎</span>
            <input type="text" id="searchInput" placeholder="Buscar materia, espacio…" oninput="filterTable(this.value)">
        </div>
    </div>

    <?php if ($dbError): ?>
    <div class="err-box">❌ Error de conexión: <?= htmlspecialchars($dbError) ?></div>
    <?php endif; ?>

    <?php
    $hoyEn = date('l'); // Monday, Tuesday...
    $grouped = [];
    foreach ($horarios as $h) $grouped[$h['dia_semana']][] = $h;
    ?>

    <?php if (empty($horarios)): ?>
    <div class="table-wrap">
        <div class="empty-state"><div class="ico">📅</div><p>No hay horarios registrados en la base de datos.</p></div>
    </div>
    <?php else: ?>
    <?php foreach ($dias as $diaEn => $diaEs): ?>
        <?php if (empty($grouped[$diaEn])) continue; ?>
        <div class="day-section">
            <div class="day-label">
                <?= $diaEs ?>
                <?php if ($diaEn === $hoyEn): ?><span class="today-badge">Hoy</span><?php endif; ?>
            </div>
            <div class="table-wrap">
                <table class="filterable">
                    <thead>
                        <tr>
                            <th>Horario</th><th>Espacio</th><th>Materia</th>
                            <th>Grupo</th><th>Docente</th><th>Vigencia</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($grouped[$diaEn] as $h): ?>
                    <tr>
                        <td><strong><?= $h['hora_inicio'] ?> – <?= $h['hora_fin'] ?></strong></td>
                        <td><?= htmlspecialchars($h['espacio_nombre']) ?> <span class="badge-type"><?= $h['edificio'] ?></span></td>
                        <td><?= htmlspecialchars($h['materia']) ?></td>
                        <td><?= htmlspecialchars($h['grupo']) ?></td>
                        <td><?= htmlspecialchars($h['docente_nombre']) ?></td>
                        <td style="color:var(--muted);font-size:12px"><?= $h['fecha_inicio_vigencia'] ?> → <?= $h['fecha_fin_vigencia'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>
</main>

<script>
function filterTable(q) {
    q = q.toLowerCase();
    document.querySelectorAll('.filterable tbody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>
</body>
</html>
