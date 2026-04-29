<?php
// ============================================================
// espacios.php  — Página visual de gestión de espacios
// Conecta a sigmea en localhost:3308 y muestra los espacios
// ============================================================
session_start();

$dbHost = 'localhost'; $dbPort = '3308'; $dbName = 'sigmea';
$dbUser = 'root';      $dbPass = '';

$espacios = []; $dbError = null;

try {
    $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $espacios = $pdo->query('
        SELECT
            e.id, e.nombre, e.tipo, e.capacidad, e.edificio, e.piso,
            e.equipamiento, e.estado,
            h.materia, h.grupo,
            u.nombre AS docente,
            TIME_FORMAT(h.hora_inicio, "%H:%i") AS hora_inicio,
            TIME_FORMAT(h.hora_fin,    "%H:%i") AS hora_fin
        FROM espacios e
        LEFT JOIN horarios h ON (
            h.espacio_id = e.id
            AND h.dia_semana = DAYNAME(NOW())
            AND h.hora_inicio <= TIME(NOW())
            AND h.hora_fin    >= TIME(NOW())
            AND h.fecha_inicio_vigencia <= CURDATE()
            AND h.fecha_fin_vigencia    >= CURDATE()
        )
        LEFT JOIN usuarios u ON u.id = h.docente_id
        ORDER BY e.edificio, e.piso, e.nombre
    ')->fetchAll();

    // Calcular estado real y KPIs
    $kpi = ['libres' => 0, 'ocupados' => 0, 'mantenimiento' => 0, 'total' => count($espacios)];
    foreach ($espacios as &$e) {
        if ($e['estado'] === 'mantenimiento') {
            $e['estado_real'] = 'mantenimiento';
            $kpi['mantenimiento']++;
        } elseif (!empty($e['materia'])) {
            $e['estado_real'] = 'ocupado';
            $kpi['ocupados']++;
        } else {
            $e['estado_real'] = 'libre';
            $kpi['libres']++;
        }
        $e['equipamiento'] = json_decode($e['equipamiento'] ?? '[]', true) ?? [];
    }
    unset($e);

} catch (PDOException $ex) {
    $dbError = $ex->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SIGMEA — Espacios</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#070c18;--surface:#0d1526;--surface2:#111d33;
  --border:#1a2840;--border-hi:#2a4070;
  --text:#e2eaf7;--muted:#5a7090;--accent:#4f8ef7;
  --green:#22c55e;--green-bg:rgba(34,197,94,.1);--green-bd:rgba(34,197,94,.25);
  --red:#ef4444;--red-bg:rgba(239,68,68,.1);--red-bd:rgba(239,68,68,.25);
  --yellow:#f59e0b;--yellow-bg:rgba(245,158,11,.1);--yellow-bd:rgba(245,158,11,.25)
}
html,body{height:100%;font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);overflow:hidden}

/* ── Sidebar ── */
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

/* ── Main ── */
.main{margin-left:240px;height:100vh;overflow-y:auto;padding:32px 36px}
.topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px}
.page-title{font-family:'Syne',sans-serif;font-size:22px;font-weight:700}
.page-sub{font-size:13px;color:var(--muted);margin-top:2px}
.search-box{position:relative}
.search-box input{background:var(--surface2);border:1.5px solid var(--border);border-radius:8px;padding:7px 12px 7px 34px;color:var(--text);font-size:13px;outline:none;width:240px;transition:border-color .2s}
.search-box input:focus{border-color:var(--accent)}
.search-box input::placeholder{color:var(--muted)}
.search-ico{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px}

/* ── KPI Cards ── */
.kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px}
.kpi-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px 22px}
.kpi-label{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
.kpi-value{font-family:'Syne',sans-serif;font-size:30px;font-weight:800;line-height:1}
.kpi-card.libre .kpi-value{color:var(--green)}
.kpi-card.ocupado .kpi-value{color:var(--red)}
.kpi-card.mant .kpi-value{color:var(--yellow)}
.kpi-card.total .kpi-value{color:var(--accent)}

/* ── Table ── */
.table-wrap{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden}
.table-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border)}
.table-title{font-family:'Syne',sans-serif;font-size:14px;font-weight:600}
table{width:100%;border-collapse:collapse}
thead th{padding:12px 20px;text-align:left;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;font-weight:500;background:var(--surface2)}
tbody tr{border-top:1px solid var(--border);transition:background .15s}
tbody tr:hover{background:var(--surface2)}
tbody td{padding:13px 20px;font-size:13px;vertical-align:middle}

/* ── Badges ── */
.badge{display:inline-block;padding:3px 9px;border-radius:6px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
.badge-libre{background:var(--green-bg);color:var(--green);border:1px solid var(--green-bd)}
.badge-ocupado{background:var(--red-bg);color:var(--red);border:1px solid var(--red-bd)}
.badge-mantenimiento{background:var(--yellow-bg);color:var(--yellow);border:1px solid var(--yellow-bd)}
.badge-tipo{background:rgba(79,142,247,.12);color:var(--accent);border:1px solid rgba(79,142,247,.25)}

/* ── Misc ── */
.empty-state{text-align:center;padding:60px 20px;color:var(--muted)}
.empty-state .ico{font-size:48px;margin-bottom:12px}
.err-box{background:var(--red-bg);border:1px solid var(--red-bd);color:var(--red);padding:16px 20px;border-radius:10px;margin-bottom:20px;font-size:13px}
.equip-tag{display:inline-block;background:var(--surface2);border:1px solid var(--border);border-radius:4px;padding:1px 6px;font-size:10px;color:var(--muted);margin:1px}
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
        <a class="nav-item active" href="espacios.php"><span class="ico">🏛️</span> Espacios</a>
        <a class="nav-item" href="horarios.php"><span class="ico">📅</span> Horarios</a>
    </nav>
    <nav class="nav-section">
        <div class="nav-label">Operación</div>
        <a class="nav-item" href="incidencias.php"><span class="ico">⚠️</span> Incidencias</a>
        <a class="nav-item" href="mantenimiento.php"><span class="ico">🔧</span> Mantenimiento</a>
        <a class="nav-item" href="buscador.php"><span class="ico">🔍</span> Buscador Inteligente</a>
    </nav>
    <nav class="nav-section">
        <div class="nav-label">Administración</div>
        <a class="nav-item" href="reportes.php"><span class="ico">📈</span> Reportes</a>
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
            <h1 class="page-title">🏛️ Espacios</h1>
            <p class="page-sub">Estado en tiempo real de todos los espacios del instituto</p>
        </div>
        <div class="search-box">
            <span class="search-ico">🔎</span>
            <input type="text" id="searchInput" placeholder="Buscar espacio, edificio…" oninput="filterTable(this.value)">
        </div>
    </div>

    <?php if ($dbError): ?>
    <div class="err-box">❌ Error de conexión: <?= htmlspecialchars($dbError) ?></div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <?php if (!$dbError): ?>
    <div class="kpi-grid">
        <div class="kpi-card libre">
            <div class="kpi-label">🟢 Libres</div>
            <div class="kpi-value"><?= $kpi['libres'] ?></div>
        </div>
        <div class="kpi-card ocupado">
            <div class="kpi-label">🔴 Ocupados</div>
            <div class="kpi-value"><?= $kpi['ocupados'] ?></div>
        </div>
        <div class="kpi-card mant">
            <div class="kpi-label">🟡 Mantenimiento</div>
            <div class="kpi-value"><?= $kpi['mantenimiento'] ?></div>
        </div>
        <div class="kpi-card total">
            <div class="kpi-label">📦 Total</div>
            <div class="kpi-value"><?= $kpi['total'] ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabla de espacios -->
    <div class="table-wrap">
        <div class="table-header">
            <div class="table-title">Todos los espacios</div>
        </div>

        <?php if (empty($espacios)): ?>
        <div class="empty-state">
            <div class="ico">🏛️</div>
            <p>No hay espacios registrados en la base de datos.</p>
        </div>
        <?php else: ?>
        <table id="mainTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Espacio</th>
                    <th>Tipo</th>
                    <th>Edificio / Piso</th>
                    <th>Capacidad</th>
                    <th>Estado</th>
                    <th>En uso ahora</th>
                    <th>Equipamiento</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($espacios as $e): ?>
            <tr>
                <td style="color:var(--muted)"><?= $e['id'] ?></td>
                <td><strong><?= htmlspecialchars($e['nombre']) ?></strong></td>
                <td><span class="badge badge-tipo"><?= htmlspecialchars($e['tipo']) ?></span></td>
                <td><?= htmlspecialchars($e['edificio']) ?> <span style="color:var(--muted)">/ P<?= $e['piso'] ?></span></td>
                <td><?= $e['capacidad'] ?> <span style="color:var(--muted);font-size:11px">alumnos</span></td>
                <td>
                    <?php if ($e['estado_real'] === 'libre'): ?>
                        <span class="badge badge-libre">Libre</span>
                    <?php elseif ($e['estado_real'] === 'ocupado'): ?>
                        <span class="badge badge-ocupado">Ocupado</span>
                    <?php else: ?>
                        <span class="badge badge-mantenimiento">Mantenimiento</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px">
                    <?php if (!empty($e['materia'])): ?>
                        <strong><?= htmlspecialchars($e['materia']) ?></strong><br>
                        <span style="color:var(--muted)"><?= htmlspecialchars($e['grupo']) ?> · <?= $e['hora_inicio'] ?>–<?= $e['hora_fin'] ?></span><br>
                        <span style="color:var(--muted)"><?= htmlspecialchars($e['docente'] ?? '') ?></span>
                    <?php else: ?>
                        <span style="color:var(--muted)">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($e['equipamiento'])): ?>
                        <?php foreach ($e['equipamiento'] as $eq): ?>
                            <span class="equip-tag"><?= htmlspecialchars($eq) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span style="color:var(--muted);font-size:12px">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
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
