<?php
require_once 'config.php';

if (!empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    if ($dni && $pass) {
        $user = DB::row("SELECT * FROM usuarios WHERE dni = ? AND activo = 1", [$dni]);
        if ($user && $user['contrasena'] === $pass) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['nombre'] . ' ' . $user['apellido'];
            $_SESSION['user_type'] = $user['tipo'];
            $_SESSION['role_active'] = $user['tipo'];
            if ($user['roles_secundarios']) {
                $_SESSION['roles'] = array_merge([$user['tipo']], array_map('trim', explode(',', $user['roles_secundarios'])));
            } else {
                $_SESSION['roles'] = [$user['tipo']];
            }
            header('Location: index.php');
            exit;
        }
        $error = 'DNI o contraseña incorrectos.';
    } else {
        $error = 'Completá todos los campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ingresar — RITE</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['DM Sans','sans-serif']},colors:{ethf:{600:'#1e3a5f',700:'#1a3352',800:'#152a44',900:'#0f1f33'}}}}}</script>
<style>*{font-family:'DM Sans',sans-serif}</style>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#0f1f33] via-[#1e3a5f] to-[#1a3352] p-4">
<div class="w-full max-w-sm">
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-white/10 rounded-2xl mb-4 backdrop-blur">
            <span class="text-2xl font-bold text-white">HF</span>
        </div>
        <h1 class="text-white text-xl font-semibold"><?= SCHOOL_NAME ?></h1>
        <p class="text-blue-200/60 text-sm mt-1"><?= APP_NAME ?> v<?= APP_VERSION ?> — Ciclo <?= date('Y') ?></p>
    </div>
    <div class="bg-white rounded-2xl shadow-2xl p-6 space-y-4">
        <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2 rounded-xl"><?= $error ?></div>
        <?php endif; ?>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-gray-400 mb-1.5 uppercase tracking-wider">DNI / Usuario</label>
                <input type="text" name="dni" required autofocus placeholder="Ingresá tu DNI"
                    class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-400 mb-1.5 uppercase tracking-wider">Contraseña</label>
                <input type="password" name="password" required placeholder="••••••••"
                    class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
            </div>
            <button type="submit"
                class="w-full bg-[#1e3a5f] hover:bg-[#152a44] text-white py-2.5 rounded-xl text-sm font-semibold transition shadow-lg">
                Ingresar
            </button>
        </form>
    </div>
</div>
</body>
</html>
