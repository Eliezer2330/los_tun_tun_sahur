<?php
// ============================================================
// login.php — Página visual de inicio de sesión
// Conecta a sigmea en localhost:3308 y gestiona la sesión
// ============================================================
session_start();

$dbHost = 'localhost'; $dbPort = '3308'; $dbName = 'sigmea';
$dbUser = 'root';      $dbPass = '';

$error   = '';
$success = '';

// Si ya tiene sesión activa, redirigir al dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// ── Procesar formulario ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $rolFront = trim($_POST['rol']      ?? '');

    if (!$email || !$password || !$rolFront) {
        $error = 'Todos los campos son requeridos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Formato de correo inválido.';
    } else {
        try {
            $pdo  = new PDO("mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $stmt = $pdo->prepare('SELECT id, nombre, email, password_hash, rol, activo FROM usuarios WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if (!$user) {
                usleep(300000);
                $error = 'Credenciales incorrectas. Verifica tu correo y contraseña.';
            } elseif ((int)$user['activo'] === 0) {
                $error = 'Tu cuenta está desactivada. Contacta al administrador.';
            } elseif (!password_verify($password, $user['password_hash'])) {
                $error = 'Credenciales incorrectas. Verifica tu correo y contraseña.';
            } elseif ($user['rol'] !== $rolFront) {
                $error = 'El rol seleccionado no coincide con tu cuenta.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']     = $user['id'];
                $_SESSION['user_email']  = $user['email'];
                $_SESSION['user_rol']    = $user['rol'];
                $_SESSION['user_nombre'] = $user['nombre'];
                header('Location: dashboard.php');
                exit;
            }
        } catch (PDOException $ex) {
            error_log('[SIGMEA][login] ' . $ex->getMessage());
            $error = 'Error de conexión con la base de datos. Intenta más tarde.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SIGMEA — Iniciar Sesión</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#070c18;--surface:#0d1526;--surface2:#111d33;
  --border:#1a2840;--text:#e2eaf7;--muted:#5a7090;
  --accent:#4f8ef7;--accent2:#6fb4ff;
  --green:#22c55e;--green-bg:rgba(34,197,94,.1);--green-bd:rgba(34,197,94,.25);
  --red:#ef4444;--red-bg:rgba(239,68,68,.1);--red-bd:rgba(239,68,68,.25)
}
html,body{
  min-height:100%;font-family:'DM Sans',sans-serif;
  background:var(--bg);color:var(--text);
  display:flex;align-items:center;justify-content:center;
  padding:24px;
}

/* ── Fondo animado ── */
body::before{
  content:'';position:fixed;inset:0;
  background:
    radial-gradient(ellipse 60% 40% at 20% 20%, rgba(79,142,247,.12) 0%, transparent 70%),
    radial-gradient(ellipse 50% 35% at 80% 80%, rgba(34,197,94,.08) 0%, transparent 70%);
  pointer-events:none;
}

/* ── Card ── */
.card{
  position:relative;
  width:100%;max-width:440px;
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:20px;
  padding:44px 40px 40px;
  box-shadow:0 24px 80px rgba(0,0,0,.5);
}
.card::before{
  content:'';position:absolute;top:0;left:50%;transform:translateX(-50%);
  width:60%;height:1px;
  background:linear-gradient(90deg,transparent,var(--accent),transparent);
}

/* ── Brand ── */
.brand{display:flex;align-items:center;gap:14px;margin-bottom:32px;justify-content:center}
.brand-icon{
  width:44px;height:44px;border-radius:10px;
  background:linear-gradient(135deg,var(--accent),var(--accent2));
  display:grid;place-items:center;
  font-family:'Syne',sans-serif;font-weight:800;font-size:16px;color:#fff;
  box-shadow:0 4px 16px rgba(79,142,247,.35);
}
.brand-name{font-family:'Syne',sans-serif;font-weight:800;font-size:22px}
.brand-sub{font-size:11px;color:var(--muted);letter-spacing:1.4px;text-transform:uppercase;margin-top:1px}

/* ── Headings ── */
.login-title{font-family:'Syne',sans-serif;font-size:18px;font-weight:700;margin-bottom:4px;text-align:center}
.login-sub{font-size:13px;color:var(--muted);margin-bottom:28px;text-align:center}

/* ── Form ── */
.form-group{margin-bottom:18px}
label{display:block;font-size:12px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:7px}
input[type="email"],input[type="password"],select{
  width:100%;background:var(--surface2);
  border:1.5px solid var(--border);border-radius:10px;
  padding:11px 14px;color:var(--text);font-size:14px;
  font-family:'DM Sans',sans-serif;
  outline:none;transition:border-color .2s,box-shadow .2s;
  appearance:none;
}
input[type="email"]:focus,input[type="password"]:focus,select:focus{
  border-color:var(--accent);
  box-shadow:0 0 0 3px rgba(79,142,247,.15);
}
input::placeholder{color:var(--muted)}
select option{background:var(--surface2);color:var(--text)}

/* ── Password wrapper ── */
.pwd-wrap{position:relative}
.pwd-wrap input{padding-right:44px}
.pwd-toggle{
  position:absolute;right:12px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;color:var(--muted);
  font-size:16px;padding:4px;transition:color .15s;
}
.pwd-toggle:hover{color:var(--text)}

/* ── Button ── */
.btn-login{
  width:100%;margin-top:8px;
  background:linear-gradient(135deg,var(--accent),var(--accent2));
  border:none;border-radius:10px;
  padding:13px;
  color:#fff;font-family:'Syne',sans-serif;font-weight:700;font-size:15px;
  cursor:pointer;transition:opacity .2s,transform .1s;
  box-shadow:0 4px 20px rgba(79,142,247,.3);
}
.btn-login:hover{opacity:.9;transform:translateY(-1px)}
.btn-login:active{transform:translateY(0)}
.btn-login:disabled{opacity:.5;cursor:not-allowed;transform:none}

/* ── Alerts ── */
.alert{
  display:flex;align-items:flex-start;gap:10px;
  padding:12px 14px;border-radius:10px;
  font-size:13px;margin-bottom:20px;
}
.alert-error{background:var(--red-bg);border:1px solid var(--red-bd);color:var(--red)}
.alert-success{background:var(--green-bg);border:1px solid var(--green-bd);color:var(--green)}

/* ── Divider ── */
.divider{display:flex;align-items:center;gap:10px;margin:22px 0;color:var(--muted);font-size:12px}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border)}

/* ── Footer link ── */
.footer-note{text-align:center;font-size:12px;color:var(--muted);margin-top:22px}

/* ── Spinner ── */
@keyframes spin{to{transform:rotate(360deg)}}
.spinner{display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;margin-right:6px}
</style>
</head>
<body>

<div class="card">

    <!-- Brand -->
    <div class="brand">
        <div class="brand-icon">SG</div>
        <div>
            <div class="brand-name">SIGMEA</div>
            <div class="brand-sub">Sistema de Gestión</div>
        </div>
    </div>

    <h1 class="login-title">Bienvenido de nuevo</h1>
    <p class="login-sub">Inicia sesión para acceder al sistema</p>

    <!-- Alerta de error -->
    <?php if ($error): ?>
    <div class="alert alert-error" id="alertBox">
        <span>⚠️</span>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>

    <!-- Formulario -->
    <form method="POST" action="login.php" id="loginForm" onsubmit="handleSubmit(event)">

        <div class="form-group">
            <label for="email">Correo electrónico</label>
            <input
                type="email"
                id="email"
                name="email"
                placeholder="usuario@institucional.edu.mx"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                required
                autocomplete="email"
            >
        </div>

        <div class="form-group">
            <label for="password">Contraseña</label>
            <div class="pwd-wrap">
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="••••••••"
                    required
                    autocomplete="current-password"
                >
                <button type="button" class="pwd-toggle" onclick="togglePwd()" title="Mostrar/ocultar contraseña" aria-label="Toggle password">
                    👁
                </button>
            </div>
        </div>

        <div class="form-group">
            <label for="rol">Rol</label>
            <select id="rol" name="rol" required>
                <option value="" disabled <?= empty($_POST['rol']) ? 'selected' : '' ?>>Selecciona tu rol…</option>
                <option value="admin"     <?= (($_POST['rol'] ?? '') === 'admin')     ? 'selected' : '' ?>>Administrador</option>
                <option value="academico" <?= (($_POST['rol'] ?? '') === 'academico') ? 'selected' : '' ?>>Académico</option>
                <option value="prefecto"  <?= (($_POST['rol'] ?? '') === 'prefecto')  ? 'selected' : '' ?>>Prefecto</option>
            </select>
        </div>

        <button type="submit" class="btn-login" id="btnLogin">
            Iniciar sesión
        </button>

    </form>

    <div class="footer-note">
        ¿Problemas para acceder? Contacta al administrador del sistema.
    </div>

</div>

<script>
function togglePwd() {
    const inp = document.getElementById('password');
    inp.type = inp.type === 'password' ? 'text' : 'password';
}

function handleSubmit(e) {
    const btn = document.getElementById('btnLogin');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Verificando…';
}
</script>
</body>
</html>
