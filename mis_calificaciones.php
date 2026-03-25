<?php
/**
 * mis_calificaciones.php — Vista de calificaciones para estudiantes
 * RITE v2.0
 */
require_once 'config.php';
requireRole('estudiante');
require_once 'header.php';

$ciclo = cicloId();
$estId = userId();

// Obtener curso del alumno
$matricula = DB::row("SELECT m.*, c.nombre as curso_nombre FROM matriculas m JOIN cursos c ON m.curso_id=c.id WHERE m.estudiante_id=? AND m.estado='activo' AND c.ciclo_lectivo_id=?", [$estId, $ciclo]);

if (!$matricula): ?>
<div class="max-w-[800px] mx-auto px-4 py-8 text-center text-gray-500">No tenés matrícula activa en el ciclo actual.</div>
<?php require_once 'footer.php'; exit; endif;

// Obtener materias y calificaciones
$materias = DB::rows("SELECT mc.id, m.nombre as materia, m.tipo,
    COALESCE(p.apellido,'') as profesor,
    c.valoracion_1bim, c.desempeno_1bim, c.calificacion_1c,
    c.valoracion_3bim, c.desempeno_3bim, c.calificacion_2c,
    c.intensificacion_1c, c.intensificacion_diciembre, c.intensificacion_febrero,
    c.calificacion_final
    FROM materias_por_curso mc
    JOIN materias m ON mc.materia_id = m.id
    LEFT JOIN usuarios p ON mc.profesor_id = p.id
    LEFT JOIN calificaciones c ON c.materia_curso_id = mc.id AND c.estudiante_id = ? AND c.ciclo_lectivo_id = ?
    WHERE mc.curso_id = ?
    ORDER BY m.tipo, m.nombre", [$estId, $ciclo, $matricula['curso_id']]);
?>

<div class="max-w-[1200px] mx-auto px-4 py-5">
    <div class="mb-5">
        <h1 class="text-lg font-semibold text-gray-800">Mis Calificaciones</h1>
        <p class="text-sm text-gray-500"><?= $matricula['curso_nombre'] ?> — Ciclo <?= cicloActivo()['anio'] ?></p>
    </div>
    
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 border-b text-[10px] text-gray-500 uppercase tracking-wider">
                <th class="text-left px-4 py-2.5 font-semibold min-w-[200px] sticky left-0 bg-gray-50">Materia</th>
                <th class="text-center px-2 py-2.5 font-semibold" style="background:#ecfdf5">Val 1°B</th>
                <th class="text-center px-2 py-2.5 font-semibold" style="background:#ecfdf5">Des 1°B</th>
                <th class="text-center px-2 py-2.5 font-semibold" style="background:#dcfce7">Cal 1°C</th>
                <th class="text-center px-2 py-2.5 font-semibold" style="background:#fffbeb">Val 3°B</th>
                <th class="text-center px-2 py-2.5 font-semibold" style="background:#fffbeb">Des 3°B</th>
                <th class="text-center px-2 py-2.5 font-semibold" style="background:#fef3c7">Cal 2°C</th>
                <th class="text-center px-2 py-2.5 font-semibold" style="background:#fdf2f8">Int 1C</th>
                <th class="text-center px-2 py-2.5 font-semibold" style="background:#fdf2f8">Int Dic</th>
                <th class="text-center px-2 py-2.5 font-semibold" style="background:#fdf2f8">Int Feb</th>
                <th class="text-center px-2 py-2.5 font-semibold" style="background:#f5f3ff">Final</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($materias as $m):
            $final = $m['calificacion_final'];
            $finalCls = $final === null ? '' : ($final >= 7 ? 'text-green-700 font-bold' : ($final >= 4 ? 'text-amber-700 font-bold' : 'text-red-700 font-bold'));
        ?>
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-2.5 sticky left-0 bg-white">
                <div class="font-medium text-gray-800"><?= htmlspecialchars($m['materia']) ?></div>
                <div class="text-[10px] text-gray-400"><?= $m['profesor'] ?></div>
            </td>
            <td class="text-center px-2 py-2.5 text-xs <?= $m['valoracion_1bim'] === 'TEA' ? 'text-green-700 font-semibold' : ($m['valoracion_1bim'] === 'TED' ? 'text-red-600 font-semibold' : 'text-amber-600') ?>"><?= $m['valoracion_1bim'] ?: '—' ?></td>
            <td class="text-center px-2 py-2.5 text-xs text-gray-600"><?= $m['desempeno_1bim'] ?: '—' ?></td>
            <td class="text-center px-2 py-2.5 font-semibold <?= ($m['calificacion_1c'] ?? 0) >= 7 ? 'text-green-700' : (($m['calificacion_1c'] ?? 0) >= 4 ? 'text-amber-700' : 'text-red-600') ?>"><?= $m['calificacion_1c'] ?: '—' ?></td>
            <td class="text-center px-2 py-2.5 text-xs <?= $m['valoracion_3bim'] === 'TEA' ? 'text-green-700 font-semibold' : ($m['valoracion_3bim'] === 'TED' ? 'text-red-600 font-semibold' : 'text-amber-600') ?>"><?= $m['valoracion_3bim'] ?: '—' ?></td>
            <td class="text-center px-2 py-2.5 text-xs text-gray-600"><?= $m['desempeno_3bim'] ?: '—' ?></td>
            <td class="text-center px-2 py-2.5 font-semibold <?= ($m['calificacion_2c'] ?? 0) >= 7 ? 'text-green-700' : (($m['calificacion_2c'] ?? 0) >= 4 ? 'text-amber-700' : 'text-red-600') ?>"><?= $m['calificacion_2c'] ?: '—' ?></td>
            <td class="text-center px-2 py-2.5 text-xs text-gray-600"><?= $m['intensificacion_1c'] ?: '—' ?></td>
            <td class="text-center px-2 py-2.5 text-xs text-gray-600"><?= $m['intensificacion_diciembre'] ?: '—' ?></td>
            <td class="text-center px-2 py-2.5 text-xs text-gray-600"><?= $m['intensificacion_febrero'] ?: '—' ?></td>
            <td class="text-center px-2 py-2.5 text-sm <?= $finalCls ?>"><?= $final ?: '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
