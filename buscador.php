<?php
session_start();
$dbHost = 'localhost'; $dbPort = '3308'; $dbName = 'sigmea';
$dbUser = 'root';      $dbPass = '';

$resultados = []; $dbError = null; $buscando = false; $criterios = [];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['buscar'])) {
    $buscando = true;
    $capMin   = (int)($_GET['cap_min'] ?? 0);
    $tipo     = $_GET['tipo'] ?? '';
    $edificio = $_GET['edificio'] ?? '';

    try {
        $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $where  = ["e.estado = 'disponible'", "e.capacidad >= :cap_min"];
        $params = [':cap_min' => $capMin];

        if ($tipo)     { $where[] = 'e.tipo = :tipo';         $params[':tipo']     = $tipo; }
        if ($edificio) { $where[] = 'e.edificio = :edificio'; $params[':edificio'] = $edificio; }

        // Excluir espacios ocupados AHORA
        $where[] = "NOT EXISTS (
            SELECT 1 FROM horarios h
            WHERE h.espacio_id = e.id
              AND h.dia_semana = DAYNAME(NOW())
              AND h.hora_inicio <= TIME(NOW())
              AND h.hora_fin    >= TIME(NOW())
              AND h.fecha_inicio_vigencia <= CURDATE()
              AND h.fecha_fin_vigencia    >= CURDATE()
        )";

        $sql = 'SELECT e.id, e.nombre, e.tipo, e.capacidad, e.edificio, e.piso, e.equipamiento,
                       (e.capacidad - :cap_min2) AS excedente
                FROM espacios e
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY excedente ASC, e.nombre ASC';
        $params[':cap_min2'] = $capMin;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) {
            $exc = (int)$r['excedente'];
            $resultados[] = array_merge($r, [
                'equip'  => json_decode($r['equipamiento'] ?? '[]', true),
                'ajuste' => $exc <= 10 ? 'perfecto' : ($exc <= 20 ? 'bueno' : 'holgado'),
            ]);
        }
        $criterios = ['cap_min'=>$capMin,'tipo'=>$tipo,'edificio'=>$edificio];
    } catch (PDOException $e) { $dbError = $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SIGMEA — Buscador</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#070c18;--surface:#0d1526;--surface2:#111d33;--border:#1a2840;--border-hi:#2a4070;--text:#e2eaf7;--muted:#5a7090;--accent:#4f8ef7;--accent-glow:rgba(79,142,247,.2);--green:#22c55e;--green-bg:rgba(34,197,94,.1);--green-bd:rgba(34,197,94,.25);--red:#ef4444;--red-bg:rgba(239,68,68,.1);--red-bd:rgba(239,68,68,.25);--yellow:#f59e0b;--yellow-bg:rgba(245,158,11,.1);--yellow-bd:rgba(245,158,11,.25)}
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
.topbar{margin-bottom:28px}
.page-title{font-family:'Syne',sans-serif;font-size:22px;font-weight:700}
.page-sub{font-size:13px;color:var(--muted);margin-top:2px}
.search-panel{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:28px;margin-bottom:28px}
.search-panel-title{font-family:'Syne',sans-serif;font-size:15px;font-weight:700;margin-bottom:20px;color:var(--accent)}
.form-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px}
.form-group label{display:block;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
.form-group input,.form-group select{width:100%;background:var(--surface2);border:1.5px solid var(--border);border-radius:8px;padding:10px 14px;color:var(--text);font-size:13px;font-family:'DM Sans',sans-serif;outline:none;transition:border-color .2s;-webkit-appearance:none}
.form-group input:focus,.form-group select:focus{border-color:var(--accent)}
.form-group select option{background:var(--surface2)}
.btn-search{background:linear-gradient(135deg,var(--accent),#3a7ae0);color:#fff;border:none;padding:11px 28px;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:500;cursor:pointer;box-shadow:0 4px 14px rgba(79,142,247,.35);transition:box-shadow .2s}
.btn-search:hover{box-shadow:0 6px 20px rgba(79,142,247,.5)}
.results-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px}
.result-card{background:var(--surface);border:1.5px solid var(--green-bd);border-radius:14px;padding:20px;position:relative;overflow:hidden;transition:transform .2s,box-shadow .2s;animation:fadeIn .3s ease both}
@keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
.result-card:hover{transform:translateY(-2px);box-shadow:0 4px 24px rgba(34,197,94,.15)}
.result-card .status-bar{position:absolute;top:0;left:0;right:0;height:3px;background:var(--green);border-radius:14px 14px 0 0}
.card-room{font-family:'Syne',sans-serif;font-weight:700;font-size:18px;margin-top:4px}
.card-type{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-top:2px;margin-bottom:12px}
.card-info{font-size:12px;color:var(--muted);line-height:1.8}
.card-info strong{color:var(--text);font-weight:500}
.ajuste-badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:100px;font-size:10px;font-weight:600;text-transform:uppercase;margin-top:12px}
.ajuste-badge.perfecto{background:var(--green-bg);color:var(--green);border:1px solid var(--green-bd)}
.ajuste-badge.bueno{background:rgba(79,142,247,.1);color:var(--accent);border:1px solid rgba(79,142,247,.25)}
.ajuste-badge.holgado{background:var(--yellow-bg);color:var(--yellow);border:1px solid var(--yellow-bd)}
.empty-state{text-align:center;padding:60px 20px;color:var(--muted)}
.empty-state .ico{font-size:48px;margin-bottom:12px}
.results-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.results-title{font-family:'Syne',sans-serif;font-size:15px;font-weight:600}
.count-badge{background:var(--green-bg);color:var(--green);border:1px solid var(--green-bd);padding:4px 12px;border-radius:100px;font-size:12px;font-weight:600}
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
        <a class="nav-item" href="incidencias.php"><span class="ico">⚠️</span> Incidencias</a>
        <a class="nav-item" href="mantenimiento.php"><span class="ico">🔧</span> Mantenimiento</a>
        <a class="nav-item active" href="buscador.php"><span class="ico">🔍</span> Buscador Inteligente</a>
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
        <h1 class="page-title">🔍 Buscador Inteligente</h1>
        <p class="page-sub">Encuentra espacios disponibles en tiempo real</p>
    </div>

    <?php if ($dbError): ?>
    <div class="err-box">❌ Error de conexión: <?= htmlspecialchars($dbError) ?></div>
    <?php endif; ?>

    <div class="search-panel">
        <div class="search-panel-title">Filtros de búsqueda</div>
        <form method="GET" action="buscador.php">
            <input type="hidden" name="buscar" value="1">
            <div class="form-grid">
                <div class="form-group">
                    <label>Capacidad mínima</label>
                    <input type="number" name="cap_min" min="0" placeholder="Ej. 30" value="<?= htmlspecialchars($_GET['cap_min'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Tipo de espacio</label>
                    <select name="tipo">
                        <option value="">Todos</option>
                        <option value="salon" <?= ($_GET['tipo']??'')==='salon'?'selected':'' ?>>Salón</option>
                        <option value="laboratorio" <?= ($_GET['tipo']??'')==='laboratorio'?'selected':'' ?>>Laboratorio</option>
                        <option value="computo" <?= ($_GET['tipo']??'')==='computo'?'selected':'' ?>>Cómputo</option>
                        <option value="usos_multiples" <?= ($_GET['tipo']??'')==='usos_multiples'?'selected':'' ?>>Usos múltiples</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Edificio</label>
                    <select name="edificio">
                        <option value="">Todos</option>
                        <?php foreach(['A','B','C','D','E'] as $ed): ?>
                        <option value="<?= $ed ?>" <?= ($_GET['edificio']??'')===$ed?'selected':'' ?>>Edificio <?= $ed ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn-search">🔍 Buscar espacios libres ahora</button>
        </form>
    </div>

    <?php if ($buscando): ?>
    <div class="results-header">
        <span class="results-title">Resultados</span>
        <span class="count-badge"><?= count($resultados) ?> espacio<?= count($resultados)!==1?'s':'' ?> disponible<?= count($resultados)!==1?'s':'' ?></span>
    </div>

    <?php if (empty($resultados)): ?>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px">
        <div class="empty-state"><div class="ico">🔍</div><p>No se encontraron espacios disponibles con esos criterios.</p></div>
    </div>
    <?php else: ?>
    <div class="results-grid">
        <?php foreach ($resultados as $i => $r): ?>
        <?php $tipoLabel = ['salon'=>'Salón','laboratorio'=>'Laboratorio','computo'=>'Cómputo','usos_multiples'=>'Usos múltiples'][$r['tipo']] ?? $r['tipo']; ?>
        <div class="result-card" style="animation-delay:<?= $i*0.04 ?>s">
            <div class="status-bar"></div>
            <div class="card-room"><?= htmlspecialchars($r['nombre']) ?></div>
            <div class="card-type"><?= $tipoLabel ?> · Edificio <?= $r['edificio'] ?> · Piso <?= $r['piso'] ?></div>
            <div class="card-info">
                <strong>Capacidad:</strong> 👥 <?= $r['capacidad'] ?><br>
                <?php if (!empty($r['equip'])): ?>
                <strong>Equipo:</strong> <?= implode(' ', $r['equip']) ?>
                <?php endif; ?>
            </div>
            <div><span class="ajuste-badge <?= $r['ajuste'] ?>">Ajuste <?= $r['ajuste'] ?></span></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
