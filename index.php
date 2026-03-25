<?php
require_once 'config.php';
require_once 'header.php';

$ciclo = cicloActivo();
$stats = [
    'estudiantes' => DB::row("SELECT COUNT(*) as n FROM matriculas WHERE estado='activo'")['n'] ?? 0,
    'cursos' => DB::row("SELECT COUNT(*) as n FROM cursos WHERE ciclo_lectivo_id=?", [cicloId()])['n'] ?? 0,
    'materias' => DB::row("SELECT COUNT(*) as n FROM materias")['n'] ?? 0,
    'profesores' => DB::row("SELECT COUNT(*) as n FROM usuarios WHERE tipo='profesor' AND activo=1")['n'] ?? 0,
];
?>
<div class="max-w-[1400px] mx-auto px-4 py-6">
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-800">Bienvenido, <?= htmlspecialchars(userName()) ?></h1>
        <p class="text-sm text-gray-500"><?= SCHOOL_NAME ?> — Ciclo lectivo <?= $ciclo['anio'] ?? date('Y') ?></p>
    </div>
    
    <?php if (isAdmin()): ?>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <?php foreach ([
            ['Estudiantes', $stats['estudiantes'], 'people-fill', 'blue'],
            ['Cursos', $stats['cursos'], 'book', 'emerald'],
            ['Materias', $stats['materias'], 'journal-text', 'amber'],
            ['Profesores', $stats['profesores'], 'person-badge', 'violet'],
        ] as [$label, $val, $icon, $color]): ?>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-<?= $color ?>-50 text-<?= $color ?>-600 flex items-center justify-center"><i class="bi bi-<?= $icon ?> text-lg"></i></div>
                <div><p class="text-2xl font-bold text-gray-800"><?= $val ?></p><p class="text-xs text-gray-500"><?= $label ?></p></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div class="grid md:grid-cols-3 gap-3">
        <a href="calificaciones.php" class="bg-white rounded-xl border border-gray-200 p-5 hover:border-blue-300 hover:shadow-md transition group">
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-3 group-hover:bg-blue-100"><i class="bi bi-table text-lg"></i></div>
            <h3 class="font-semibold text-gray-800 text-sm">Cargar calificaciones</h3>
            <p class="text-xs text-gray-500 mt-1">Grilla tipo planilla para carga rápida</p>
        </a>
        <a href="cursos.php" class="bg-white rounded-xl border border-gray-200 p-5 hover:border-emerald-300 hover:shadow-md transition group">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-3 group-hover:bg-emerald-100"><i class="bi bi-book text-lg"></i></div>
            <h3 class="font-semibold text-gray-800 text-sm">Cursos y Materias</h3>
            <p class="text-xs text-gray-500 mt-1">Gestión de cursos, materias y rotaciones</p>
        </a>
        <a href="usuarios.php" class="bg-white rounded-xl border border-gray-200 p-5 hover:border-violet-300 hover:shadow-md transition group">
            <div class="w-10 h-10 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center mb-3 group-hover:bg-violet-100"><i class="bi bi-people text-lg"></i></div>
            <h3 class="font-semibold text-gray-800 text-sm">Usuarios</h3>
            <p class="text-xs text-gray-500 mt-1">Profesores, alumnos, preceptores</p>
        </a>
    </div>
    
    <!-- Resumen por curso -->
    <h2 class="text-sm font-semibold text-gray-700 mt-8 mb-3">Distribución por curso</h2>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 border-b text-xs text-gray-500 uppercase tracking-wider">
                <th class="text-left px-4 py-2.5 font-semibold">Curso</th>
                <th class="text-center px-4 py-2.5 font-semibold">Alumnos</th>
                <th class="text-center px-4 py-2.5 font-semibold">Materias</th>
                <th class="text-right px-4 py-2.5 font-semibold">Acción</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            <?php
            $cursos = DB::rows("SELECT c.*, 
                (SELECT COUNT(*) FROM matriculas m WHERE m.curso_id=c.id AND m.estado='activo') as alumnos,
                (SELECT COUNT(*) FROM materias_por_curso mc WHERE mc.curso_id=c.id) as materias
                FROM cursos c WHERE c.ciclo_lectivo_id=? ORDER BY c.anio", [cicloId()]);
            foreach ($cursos as $c): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2.5 font-medium"><?= $c['nombre'] ?></td>
                <td class="px-4 py-2.5 text-center"><?= $c['alumnos'] ?></td>
                <td class="px-4 py-2.5 text-center"><?= $c['materias'] ?></td>
                <td class="px-4 py-2.5 text-right">
                    <a href="cursos.php?id=<?= $c['id'] ?>" class="text-xs text-blue-600 hover:underline">Ver detalle →</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; // isAdmin ?>
    
    <?php if (activeRole() === 'profesor'): ?>
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-800 mb-3">Mis Materias</h2>
        <?php
        $mis = DB::rows("SELECT mc.id, m.nombre as materia, c.nombre as curso
            FROM materias_por_curso mc
            JOIN materias m ON mc.materia_id = m.id
            JOIN cursos c ON mc.curso_id = c.id
            WHERE mc.profesor_id=? OR mc.profesor_id_2=? OR mc.profesor_id_3=?
            ORDER BY c.anio, m.nombre", [userId(), userId(), userId()]);
        if ($mis): ?>
        <div class="space-y-2">
        <?php foreach ($mis as $mat): ?>
            <a href="calificaciones.php?curso=<?= $mat['curso'] ?>&materia=<?= $mat['id'] ?>" 
               class="flex items-center justify-between p-3 rounded-lg border border-gray-100 hover:border-blue-200 hover:bg-blue-50/50 transition text-sm">
                <div><span class="font-medium text-gray-800"><?= htmlspecialchars($mat['materia']) ?></span> <span class="text-gray-400">·</span> <span class="text-gray-500"><?= $mat['curso'] ?></span></div>
                <i class="bi bi-chevron-right text-gray-400"></i>
            </a>
        <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-gray-500 text-sm">No tenés materias asignadas.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php require_once 'footer.php'; ?>
