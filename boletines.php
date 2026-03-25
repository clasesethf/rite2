<?php
/**
 * boletines.php — Generación de Boletines (RITE)
 * RITE v2.0
 */
require_once 'config.php';
requireRole('admin', 'directivo', 'preceptor');
require_once 'header.php';

$ciclo = cicloId();
$cursoSel = $_GET['curso'] ?? '';

$cursos = DB::rows("SELECT * FROM cursos WHERE ciclo_lectivo_id=? ORDER BY anio", [$ciclo]);

$alumnos = [];
if ($cursoSel) {
    $alumnos = DB::rows(
        "SELECT u.id, u.apellido, u.nombre, u.dni, mat.tipo_matricula
         FROM usuarios u
         JOIN matriculas mat ON u.id = mat.estudiante_id
         WHERE mat.curso_id = ? AND mat.estado = 'activo'
         ORDER BY u.apellido, u.nombre",
        [$cursoSel]
    );
}

$cursoInfo = $cursoSel ? DB::row("SELECT * FROM cursos WHERE id=?", [$cursoSel]) : null;
?>
<div class="max-w-[1200px] mx-auto px-4 py-5">
    <div class="mb-5">
        <h1 class="text-lg font-semibold text-gray-800">Boletines (RITE)</h1>
        <p class="text-xs text-gray-500">Generación de boletines en PDF — Resolución Nº 1650/24</p>
    </div>
    
    <!-- Selector de curso -->
    <form class="flex flex-wrap gap-3 mb-5 items-end">
        <div>
            <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Curso</label>
            <select name="curso" onchange="this.form.submit()" class="px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 min-w-[200px]">
                <option value="">Seleccionar curso...</option>
                <?php foreach ($cursos as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $c['id']==$cursoSel?'selected':'' ?>><?= $c['nombre'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <?php if ($cursoSel && $alumnos): ?>
        <div class="flex gap-2">
            <a href="generar_boletin_pdf.php?curso=<?= $cursoSel ?>&tipo=cuatrimestral&cuatrimestre=1&todos=1" target="_blank"
               class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-[#1e3a5f] rounded-lg hover:bg-[#152a44] transition">
                <i class="bi bi-file-earmark-pdf"></i> PDF 1° Cuatrimestre (todos)
            </a>
            <a href="generar_boletin_pdf.php?curso=<?= $cursoSel ?>&tipo=cuatrimestral&cuatrimestre=2&todos=1" target="_blank"
               class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 transition">
                <i class="bi bi-file-earmark-pdf"></i> PDF 2° Cuatrimestre (todos)
            </a>
            <a href="generar_boletin_pdf.php?curso=<?= $cursoSel ?>&tipo=final&todos=1" target="_blank"
               class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-violet-700 bg-violet-50 border border-violet-200 rounded-lg hover:bg-violet-100 transition">
                <i class="bi bi-file-earmark-pdf"></i> PDF Final (todos)
            </a>
        </div>
        <?php endif; ?>
    </form>
    
    <?php if ($cursoSel && $alumnos): ?>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead><tr class="bg-gray-50 border-b text-xs text-gray-500 uppercase tracking-wider">
            <th class="text-center px-3 py-2.5 font-semibold w-10">#</th>
            <th class="text-left px-4 py-2.5 font-semibold">Alumno</th>
            <th class="text-left px-4 py-2.5 font-semibold">Matrícula</th>
            <th class="text-center px-4 py-2.5 font-semibold">Tipo</th>
            <th class="text-right px-4 py-2.5 font-semibold">Boletines individuales</th>
        </tr></thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($alumnos as $i => $a): ?>
        <tr class="hover:bg-gray-50">
            <td class="px-3 py-2 text-center text-gray-400 text-xs"><?= $i+1 ?></td>
            <td class="px-4 py-2 font-medium text-gray-800"><?= htmlspecialchars($a['apellido'] . ', ' . $a['nombre']) ?></td>
            <td class="px-4 py-2 text-gray-500 font-mono text-xs"><?= htmlspecialchars($a['dni']) ?></td>
            <td class="px-4 py-2 text-center">
                <span class="text-[10px] px-1.5 py-0.5 rounded font-medium <?= $a['tipo_matricula']==='liberado'?'bg-green-50 text-green-700':($a['tipo_matricula']==='recursando'?'bg-amber-50 text-amber-700':'bg-gray-50 text-gray-600') ?>">
                    <?= ucfirst($a['tipo_matricula']) ?>
                </span>
            </td>
            <td class="px-4 py-2 text-right space-x-1">
                <a href="generar_boletin_pdf.php?estudiante=<?= $a['id'] ?>&curso=<?= $cursoSel ?>&tipo=cuatrimestral&cuatrimestre=1" target="_blank"
                   class="text-xs text-blue-600 hover:underline">1°C</a>
                <a href="generar_boletin_pdf.php?estudiante=<?= $a['id'] ?>&curso=<?= $cursoSel ?>&tipo=cuatrimestral&cuatrimestre=2" target="_blank"
                   class="text-xs text-amber-600 hover:underline">2°C</a>
                <a href="generar_boletin_pdf.php?estudiante=<?= $a['id'] ?>&curso=<?= $cursoSel ?>&tipo=final" target="_blank"
                   class="text-xs text-violet-600 hover:underline">Final</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php elseif ($cursoSel): ?>
    <div class="text-center py-10 text-gray-400"><i class="bi bi-inbox text-3xl"></i><p class="mt-2">No hay alumnos matriculados.</p></div>
    <?php else: ?>
    <div class="text-center py-10 text-gray-400"><i class="bi bi-file-earmark-pdf text-3xl"></i><p class="mt-2">Seleccioná un curso para generar boletines.</p></div>
    <?php endif; ?>
</div>
<?php require_once 'footer.php'; ?>
