<?php
/**
 * header.php — RITE v2.0 Navbar superior
 */
require_once __DIR__ . '/config.php';
requireLogin();

$_page = basename($_SERVER['PHP_SELF']);
$_type = activeRole();

function nav($pages, $label, $icon = '') {
    global $_page;
    $pages = (array) $pages;
    $active = in_array($_page, $pages);
    $cls = $active ? 'text-blue-700 bg-blue-50 font-semibold' : 'text-gray-600 hover:text-blue-700 hover:bg-gray-50';
    $href = $pages[0];
    echo "<a href=\"$href\" class=\"px-2.5 py-1.5 rounded-lg transition font-medium flex items-center gap-1.5 text-[13px] $cls\">";
    if ($icon) echo "<i class=\"bi bi-$icon text-xs\"></i> ";
    echo "$label</a>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RITE — <?= SCHOOL_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo.png">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={corePlugins:{preflight:false},theme:{extend:{fontFamily:{sans:['DM Sans','sans-serif']},colors:{ethf:{50:'#eff6ff',100:'#dbeafe',500:'#3b82f6',600:'#1e3a5f',700:'#1a3352',800:'#152a44'}}}}}</script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
<style>
*{font-family:'DM Sans',sans-serif}
.nav-dd{display:none;position:absolute;top:100%;left:0;padding-top:4px;z-index:50}
.nav-g:hover .nav-dd{display:block}
.dd-r{left:auto;right:0}
.toast-c{position:fixed;top:60px;right:16px;z-index:9999;display:flex;flex-direction:column;gap:8px}
.toast-t{padding:10px 16px;border-radius:10px;color:#fff;font-size:.85rem;display:flex;align-items:center;gap:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);animation:si .3s ease;min-width:280px}
.toast-s{background:#16a34a}.toast-d{background:#dc2626}.toast-w{background:#d97706}.toast-i{background:#2563eb}
@keyframes si{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes so{from{opacity:1}to{transform:translateX(100%);opacity:0}}
::-webkit-scrollbar{width:6px;height:6px}::-webkit-scrollbar-track{background:#f1f5f9}::-webkit-scrollbar-thumb{background:#94a3b8;border-radius:3px}
@media print{.no-print{display:none!important}}
</style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col" style="font-family:'DM Sans',sans-serif">
<div class="toast-c" id="tc"></div>

<header class="bg-white border-b border-gray-200 sticky top-0 z-40 no-print">
<div class="max-w-[1700px] mx-auto px-3">
<div class="flex items-center h-12 gap-4">
    <a href="index.php" class="flex items-center gap-2 shrink-0">
        <div class="w-7 h-7 bg-ethf-600 rounded-lg flex items-center justify-center"><span class="text-white text-[10px] font-bold">HF</span></div>
        <span class="text-sm font-bold text-ethf-800 hidden sm:block">RITE</span>
    </a>
    
    <nav class="hidden md:flex items-center gap-0.5 h-full">
        <?php nav('index.php', 'Inicio', 'grid-1x2'); ?>
        
        <?php if (in_array($_type, ['admin','directivo'])): ?>
        
        <!-- Calificaciones -->
        <div class="nav-g relative h-full flex items-center">
            <?php nav('calificaciones.php', 'Calificaciones ▾', 'pencil-square'); ?>
            <div class="nav-dd w-56">
                <div class="bg-white border border-gray-200 rounded-xl shadow-xl py-1.5 text-xs">
                    <a href="calificaciones.php" class="flex items-center gap-2 px-3.5 py-2 hover:bg-blue-50 text-gray-600"><i class="bi bi-calculator text-gray-400 w-4"></i>Cargar notas</a>
                    <a href="bloqueos.php" class="flex items-center gap-2 px-3.5 py-2 hover:bg-blue-50 text-gray-600"><i class="bi bi-shield-lock text-gray-400 w-4"></i>Bloqueos</a>
                </div>
            </div>
        </div>
        
        <!-- Académico -->
        <div class="nav-g relative h-full flex items-center">
            <?php nav(['cursos.php','materias.php'], 'Académico ▾', 'journal-text'); ?>
            <div class="nav-dd w-52">
                <div class="bg-white border border-gray-200 rounded-xl shadow-xl py-1.5 text-xs">
                    <a href="cursos.php" class="flex items-center gap-2 px-3.5 py-2 hover:bg-blue-50 text-gray-600"><i class="bi bi-book text-gray-400 w-4"></i>Cursos y Materias</a>
                    <a href="materias.php" class="flex items-center gap-2 px-3.5 py-2 hover:bg-blue-50 text-gray-600"><i class="bi bi-journal-text text-gray-400 w-4"></i>Catálogo de materias</a>
                </div>
            </div>
        </div>
        
        <?php nav('usuarios.php', 'Usuarios', 'people'); ?>
        
        <!-- Reportes -->
        <div class="nav-g relative h-full flex items-center">
            <?php nav(['boletines.php'], 'Reportes ▾', 'file-earmark-text'); ?>
            <div class="nav-dd w-52">
                <div class="bg-white border border-gray-200 rounded-xl shadow-xl py-1.5 text-xs">
                    <a href="boletines.php" class="flex items-center gap-2 px-3.5 py-2 hover:bg-blue-50 text-gray-600"><i class="bi bi-file-earmark-pdf text-gray-400 w-4"></i>Boletines (RITE)</a>
                </div>
            </div>
        </div>
        
        <?php elseif ($_type === 'profesor'): ?>
        <?php nav('calificaciones.php', 'Calificaciones', 'pencil-square'); ?>
        
        <?php elseif ($_type === 'preceptor'): ?>
        <?php nav('asistencias.php', 'Asistencias', 'calendar-check'); ?>
        
        <?php elseif ($_type === 'estudiante'): ?>
        <?php nav('mis_calificaciones.php', 'Mis Calificaciones', 'journal-check'); ?>
        <?php endif; ?>
    </nav>
    
    <div class="ml-auto flex items-center gap-2">
        <span class="text-[11px] text-gray-400 hidden lg:block">Ciclo <?= cicloActivo()['anio'] ?? date('Y') ?></span>
        
        <div class="nav-g relative flex items-center h-12">
            <div class="flex items-center gap-2 pl-3 border-l border-gray-200 cursor-pointer py-2">
                <div class="w-7 h-7 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-[10px] font-bold shrink-0"><?= userInitials() ?></div>
                <div class="hidden sm:block text-left leading-tight">
                    <p class="text-[11px] font-medium text-gray-700"><?= htmlspecialchars(userName()) ?></p>
                    <p class="text-[10px] text-gray-400"><?= ucfirst(activeRole()) ?></p>
                </div>
                <i class="bi bi-chevron-down text-[9px] text-gray-400 ml-0.5"></i>
            </div>
            <div class="nav-dd dd-r w-44">
                <div class="bg-white border border-gray-200 rounded-xl shadow-xl py-1.5 text-xs">
                    <?php if (count($_SESSION['roles'] ?? []) > 1): ?>
                    <div class="px-3.5 py-1 text-[10px] text-gray-400 uppercase tracking-wider font-semibold">Cambiar rol</div>
                    <?php foreach ($_SESSION['roles'] as $r): if ($r !== activeRole()): ?>
                    <a href="?_switch_role=<?= $r ?>" class="flex items-center gap-2 px-3.5 py-2 hover:bg-blue-50 text-gray-600"><i class="bi bi-person-badge text-gray-400 w-4"></i><?= ucfirst($r) ?></a>
                    <?php endif; endforeach; ?>
                    <div class="border-t border-gray-100 my-1"></div>
                    <?php endif; ?>
                    <a href="logout.php" class="flex items-center gap-2 px-3.5 py-2 hover:bg-red-50 text-red-600"><i class="bi bi-box-arrow-right text-red-400 w-4"></i>Cerrar sesión</a>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
</header>

<main class="flex-1 w-full">
<?php
// Role switching
if (isset($_GET['_switch_role']) && in_array($_GET['_switch_role'], $_SESSION['roles'] ?? [])) {
    $_SESSION['role_active'] = $_GET['_switch_role'];
    header('Location: index.php'); exit;
}

// Flash
$_flash = getFlash();
if ($_flash): ?>
<script>document.addEventListener('DOMContentLoaded',()=>toast('<?= addslashes($_flash['msg']) ?>','<?= $_flash['type'] ?>'))</script>
<?php endif; ?>

<script>
function toast(m,t,d){d=d||4000;const c=document.getElementById('tc'),e=document.createElement('div');e.className='toast-t toast-'+(t||'i')[0];const i={s:'check-circle-fill',d:'x-circle-fill',w:'exclamation-triangle-fill',i:'info-circle-fill'};e.innerHTML='<i class="bi bi-'+(i[(t||'i')[0]]||i.i)+'"></i><span>'+m+'</span>';c.appendChild(e);setTimeout(()=>{e.style.animation='so .3s ease forwards';setTimeout(()=>e.remove(),300)},d)}
</script>
