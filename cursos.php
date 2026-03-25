<?php
/**
 * cursos.php — Gestión de Cursos, Materias y Subgrupos
 * RITE v2.0
 */
require_once 'config.php';
requireRole('admin', 'directivo');

// === ACCIONES POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            // --- Asignar profesor a materia ---
            case 'asignar_profesor':
                $mc_id = (int)$_POST['materia_curso_id'];
                $campo = $_POST['campo']; // profesor_id, profesor_id_2, profesor_id_3
                $prof_id = $_POST['profesor_id'] ?: null;
                if (in_array($campo, ['profesor_id','profesor_id_2','profesor_id_3'])) {
                    DB::query("UPDATE materias_por_curso SET $campo=? WHERE id=?", [$prof_id, $mc_id]);
                    flash('success', 'Profesor asignado correctamente.');
                }
                break;
                
            // --- Toggle subgrupos ---
            case 'toggle_subgrupos':
                $mc_id = (int)$_POST['materia_curso_id'];
                $val = (int)$_POST['requiere_subgrupos'];
                DB::query("UPDATE materias_por_curso SET requiere_subgrupos=? WHERE id=?", [$val, $mc_id]);
                flash('success', $val ? 'Rotaciones activadas.' : 'Rotaciones desactivadas.');
                break;
                
            // --- Crear subgrupo ---
            case 'crear_subgrupo':
                $mc_id = (int)$_POST['materia_curso_id'];
                $nombre = trim($_POST['nombre']);
                if ($nombre) {
                    DB::insert("INSERT OR IGNORE INTO subgrupos (materia_curso_id, nombre) VALUES (?,?)", [$mc_id, $nombre]);
                    flash('success', "Subgrupo '$nombre' creado.");
                }
                break;
                
            // --- Eliminar subgrupo ---
            case 'eliminar_subgrupo':
                $sg_id = (int)$_POST['subgrupo_id'];
                DB::query("DELETE FROM estudiantes_subgrupo WHERE subgrupo_id=?", [$sg_id]);
                DB::query("DELETE FROM subgrupos WHERE id=?", [$sg_id]);
                flash('success', 'Subgrupo eliminado.');
                break;
                
            // --- Asignar alumno a subgrupo ---
            case 'asignar_subgrupo':
                $sg_id = (int)$_POST['subgrupo_id'];
                $est_id = (int)$_POST['estudiante_id'];
                // Quitar de otros subgrupos de la misma materia_curso
                $mc_id = DB::row("SELECT materia_curso_id FROM subgrupos WHERE id=?", [$sg_id])['materia_curso_id'] ?? 0;
                $sgs = DB::rows("SELECT id FROM subgrupos WHERE materia_curso_id=?", [$mc_id]);
                foreach ($sgs as $sg) {
                    DB::query("DELETE FROM estudiantes_subgrupo WHERE subgrupo_id=? AND estudiante_id=?", [$sg['id'], $est_id]);
                }
                DB::insert("INSERT INTO estudiantes_subgrupo (subgrupo_id, estudiante_id) VALUES (?,?)", [$sg_id, $est_id]);
                flash('success', 'Alumno asignado al subgrupo.');
                break;
                
            // --- Tipo matrícula ---
            case 'tipo_matricula':
                $mat_id = (int)$_POST['matricula_id'];
                $tipo = $_POST['tipo_matricula'];
                if (in_array($tipo, ['regular','recursando','liberado'])) {
                    DB::query("UPDATE matriculas SET tipo_matricula=? WHERE id=?", [$tipo, $mat_id]);
                    flash('success', 'Tipo de matrícula actualizado.');
                }
                break;
        }
    } catch (Exception $e) {
        flash('danger', 'Error: ' . $e->getMessage());
    }
    
    // Redirect back
    $back = $_POST['_back'] ?? $_SERVER['REQUEST_URI'];
    header("Location: $back");
    exit;
}

// === DATOS ===
$cursoId = $_GET['id'] ?? '';
$tab = $_GET['tab'] ?? 'materias';

$cursos = DB::rows("SELECT * FROM cursos WHERE ciclo_lectivo_id=? ORDER BY anio", [cicloId()]);
$profesores = DB::rows("SELECT id, nombre, apellido FROM usuarios WHERE tipo='profesor' AND activo=1 ORDER BY apellido");
$curso = null;

if ($cursoId) {
    $curso = DB::row("SELECT * FROM cursos WHERE id=?", [$cursoId]);
}

require_once 'header.php';
?>

<div class="max-w-[1500px] mx-auto px-4 py-4">
    
    <!-- Breadcrumb + título -->
    <div class="flex items-center gap-3 mb-4">
        <h1 class="text-lg font-semibold text-gray-800">
            <?= $curso ? htmlspecialchars($curso['nombre']) : 'Cursos' ?>
        </h1>
        <?php if ($curso): ?>
        <a href="cursos.php" class="text-xs text-blue-600 hover:underline">← Todos los cursos</a>
        <?php endif; ?>
    </div>
    
    <?php if (!$curso): ?>
    <!-- =================== LISTA DE CURSOS =================== -->
    <div class="grid md:grid-cols-3 lg:grid-cols-4 gap-3">
        <?php foreach ($cursos as $c):
            $al = DB::row("SELECT COUNT(*) as n FROM matriculas WHERE curso_id=? AND estado='activo'", [$c['id']])['n'];
            $ma = DB::row("SELECT COUNT(*) as n FROM materias_por_curso WHERE curso_id=?", [$c['id']])['n'];
        ?>
        <a href="cursos.php?id=<?= $c['id'] ?>" class="bg-white rounded-xl border border-gray-200 p-5 hover:border-blue-300 hover:shadow-md transition">
            <h3 class="font-semibold text-gray-800"><?= $c['nombre'] ?></h3>
            <div class="flex gap-4 mt-2 text-xs text-gray-500">
                <span><i class="bi bi-people"></i> <?= $al ?> alumnos</span>
                <span><i class="bi bi-journal-text"></i> <?= $ma ?> materias</span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    
    <?php else: ?>
    <!-- =================== DETALLE DE CURSO =================== -->
    
    <!-- Tabs -->
    <div class="flex gap-1 mb-4 border-b border-gray-200">
        <?php foreach (['materias' => 'Materias', 'alumnos' => 'Alumnos'] as $k => $label): ?>
        <a href="cursos.php?id=<?= $cursoId ?>&tab=<?= $k ?>" 
           class="px-4 py-2 text-sm font-medium border-b-2 transition <?= $tab === $k ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>
    
    <?php if ($tab === 'materias'): ?>
    <!-- =========== TAB: MATERIAS =========== -->
    <?php
    $materias_curso = DB::rows("SELECT mc.*, m.nombre as materia, m.tipo as tipo_materia,
        p1.apellido as prof1_apellido, p1.nombre as prof1_nombre,
        p2.apellido as prof2_apellido, p2.nombre as prof2_nombre,
        p3.apellido as prof3_apellido, p3.nombre as prof3_nombre
        FROM materias_por_curso mc
        JOIN materias m ON mc.materia_id = m.id
        LEFT JOIN usuarios p1 ON mc.profesor_id = p1.id
        LEFT JOIN usuarios p2 ON mc.profesor_id_2 = p2.id
        LEFT JOIN usuarios p3 ON mc.profesor_id_3 = p3.id
        WHERE mc.curso_id = ?
        ORDER BY m.tipo DESC, m.nombre", [$cursoId]);
    ?>
    
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead><tr class="bg-gray-50 border-b text-xs text-gray-500 uppercase tracking-wider">
            <th class="text-left px-4 py-2.5 font-semibold">Materia</th>
            <th class="text-left px-4 py-2.5 font-semibold">Profesor</th>
            <th class="text-center px-4 py-2.5 font-semibold">Rotación</th>
            <th class="text-right px-4 py-2.5 font-semibold">Acciones</th>
        </tr></thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($materias_curso as $mc): ?>
        <tr class="hover:bg-gray-50 group" id="mc-<?= $mc['id'] ?>">
            <td class="px-4 py-2.5">
                <div class="flex items-center gap-2">
                    <span class="inline-block w-2 h-2 rounded-full <?= $mc['tipo_materia'] === 'taller' ? 'bg-amber-400' : 'bg-blue-400' ?>"></span>
                    <span class="font-medium text-gray-800"><?= htmlspecialchars($mc['materia']) ?></span>
                    <?php if ($mc['tipo_materia'] === 'taller'): ?>
                    <span class="text-[10px] bg-amber-50 text-amber-700 px-1.5 py-0.5 rounded font-medium">Taller</span>
                    <?php endif; ?>
                </div>
            </td>
            <td class="px-4 py-2.5 text-gray-600 text-xs">
                <?php
                $profs = [];
                if ($mc['prof1_apellido']) $profs[] = $mc['prof1_apellido'];
                if ($mc['prof2_apellido']) $profs[] = $mc['prof2_apellido'];
                if ($mc['prof3_apellido']) $profs[] = $mc['prof3_apellido'];
                echo $profs ? implode(', ', $profs) : '<span class="text-gray-400">Sin asignar</span>';
                ?>
            </td>
            <td class="px-4 py-2.5 text-center">
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="toggle_subgrupos">
                    <input type="hidden" name="materia_curso_id" value="<?= $mc['id'] ?>">
                    <input type="hidden" name="requiere_subgrupos" value="<?= $mc['requiere_subgrupos'] ? 0 : 1 ?>">
                    <input type="hidden" name="_back" value="cursos.php?id=<?= $cursoId ?>&tab=materias">
                    <button type="submit" class="text-xs px-2 py-1 rounded-lg border transition <?= $mc['requiere_subgrupos'] ? 'bg-amber-50 border-amber-200 text-amber-700' : 'bg-gray-50 border-gray-200 text-gray-400' ?>">
                        <i class="bi bi-<?= $mc['requiere_subgrupos'] ? 'arrow-repeat' : 'dash' ?>"></i>
                        <?= $mc['requiere_subgrupos'] ? 'Sí' : 'No' ?>
                    </button>
                </form>
            </td>
            <td class="px-4 py-2.5 text-right">
                <button onclick="openProf(<?= $mc['id'] ?>, '<?= addslashes($mc['materia']) ?>', <?= $mc['profesor_id'] ?: 'null' ?>, <?= $mc['profesor_id_2'] ?: 'null' ?>, <?= $mc['profesor_id_3'] ?: 'null' ?>)" 
                    class="text-xs text-blue-600 hover:underline"><i class="bi bi-pencil"></i> Editar</button>
                <?php if ($mc['requiere_subgrupos']): ?>
                <a href="cursos.php?id=<?= $cursoId ?>&tab=materias&subgrupos=<?= $mc['id'] ?>" 
                   class="text-xs text-amber-600 hover:underline ml-2"><i class="bi bi-people"></i> Subgrupos</a>
                <?php endif; ?>
            </td>
        </tr>
        
        <?php // Panel de subgrupos inline
        if (($mc['requiere_subgrupos']) && (($_GET['subgrupos'] ?? '') == $mc['id'])):
            $subgrupos = DB::rows("SELECT * FROM subgrupos WHERE materia_curso_id=? ORDER BY nombre", [$mc['id']]);
            $alumnos_curso = DB::rows("SELECT u.id, u.apellido, u.nombre FROM usuarios u JOIN matriculas m ON u.id=m.estudiante_id WHERE m.curso_id=? AND m.estado='activo' ORDER BY u.apellido", [$cursoId]);
        ?>
        <tr><td colspan="4" class="bg-amber-50/50 px-4 py-3 border-t border-amber-200">
            <div class="text-xs font-semibold text-amber-800 mb-2"><i class="bi bi-arrow-repeat"></i> Subgrupos de <?= htmlspecialchars($mc['materia']) ?></div>
            
            <div class="flex gap-4 flex-wrap mb-3">
                <?php foreach ($subgrupos as $sg):
                    $miembros = DB::rows("SELECT u.id, u.apellido, u.nombre FROM estudiantes_subgrupo es JOIN usuarios u ON es.estudiante_id=u.id WHERE es.subgrupo_id=? ORDER BY u.apellido", [$sg['id']]);
                ?>
                <div class="bg-white rounded-lg border border-amber-200 p-3 min-w-[200px] flex-1">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-semibold text-sm text-gray-800"><?= htmlspecialchars($sg['nombre']) ?></span>
                        <form method="POST" class="inline" onsubmit="return confirm('¿Eliminar subgrupo?')">
                            <input type="hidden" name="action" value="eliminar_subgrupo">
                            <input type="hidden" name="subgrupo_id" value="<?= $sg['id'] ?>">
                            <input type="hidden" name="_back" value="cursos.php?id=<?= $cursoId ?>&tab=materias&subgrupos=<?= $mc['id'] ?>">
                            <button class="text-red-400 hover:text-red-600 text-xs"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                    <div class="space-y-0.5 text-xs text-gray-600 mb-2">
                        <?php foreach ($miembros as $m): ?>
                        <div><?= htmlspecialchars($m['apellido'] . ', ' . $m['nombre']) ?></div>
                        <?php endforeach; ?>
                        <?php if (!$miembros): ?><div class="text-gray-400 italic">Sin alumnos</div><?php endif; ?>
                    </div>
                    <!-- Agregar alumno -->
                    <form method="POST" class="flex gap-1">
                        <input type="hidden" name="action" value="asignar_subgrupo">
                        <input type="hidden" name="subgrupo_id" value="<?= $sg['id'] ?>">
                        <input type="hidden" name="_back" value="cursos.php?id=<?= $cursoId ?>&tab=materias&subgrupos=<?= $mc['id'] ?>">
                        <select name="estudiante_id" class="flex-1 text-xs border border-gray-200 rounded px-1 py-1">
                            <option value="">+ Agregar...</option>
                            <?php foreach ($alumnos_curso as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['apellido'] . ', ' . $a['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="text-xs bg-amber-600 text-white px-2 py-1 rounded hover:bg-amber-700">+</button>
                    </form>
                </div>
                <?php endforeach; ?>
                
                <!-- Crear subgrupo -->
                <form method="POST" class="bg-white rounded-lg border border-dashed border-amber-300 p-3 min-w-[200px] flex flex-col justify-center">
                    <input type="hidden" name="action" value="crear_subgrupo">
                    <input type="hidden" name="materia_curso_id" value="<?= $mc['id'] ?>">
                    <input type="hidden" name="_back" value="cursos.php?id=<?= $cursoId ?>&tab=materias&subgrupos=<?= $mc['id'] ?>">
                    <input type="text" name="nombre" placeholder="Nombre del subgrupo" class="text-xs border border-gray-200 rounded px-2 py-1.5 mb-2">
                    <button class="text-xs bg-amber-600 text-white px-3 py-1.5 rounded hover:bg-amber-700"><i class="bi bi-plus"></i> Crear subgrupo</button>
                </form>
            </div>
        </td></tr>
        <?php endif; ?>
        
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    
    <?php elseif ($tab === 'alumnos'): ?>
    <!-- =========== TAB: ALUMNOS =========== -->
    <?php
    $alumnos = DB::rows("SELECT u.*, m.id as matricula_id, m.tipo_matricula
        FROM usuarios u
        JOIN matriculas m ON u.id = m.estudiante_id
        WHERE m.curso_id=? AND m.estado='activo'
        ORDER BY u.apellido, u.nombre", [$cursoId]);
    ?>
    
    <div class="flex items-center justify-between mb-3">
        <span class="text-sm text-gray-500"><?= count($alumnos) ?> alumnos matriculados</span>
    </div>
    
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead><tr class="bg-gray-50 border-b text-xs text-gray-500 uppercase tracking-wider">
            <th class="text-center px-3 py-2.5 font-semibold w-10">#</th>
            <th class="text-left px-4 py-2.5 font-semibold">Alumno</th>
            <th class="text-left px-4 py-2.5 font-semibold">Matrícula</th>
            <th class="text-center px-4 py-2.5 font-semibold">Tipo</th>
        </tr></thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($alumnos as $i => $a): ?>
        <tr class="hover:bg-gray-50">
            <td class="px-3 py-2 text-center text-gray-400 text-xs"><?= $i + 1 ?></td>
            <td class="px-4 py-2 font-medium text-gray-800"><?= htmlspecialchars($a['apellido'] . ', ' . $a['nombre']) ?></td>
            <td class="px-4 py-2 text-gray-500 font-mono text-xs"><?= htmlspecialchars($a['dni']) ?></td>
            <td class="px-4 py-2 text-center">
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="tipo_matricula">
                    <input type="hidden" name="matricula_id" value="<?= $a['matricula_id'] ?>">
                    <input type="hidden" name="_back" value="cursos.php?id=<?= $cursoId ?>&tab=alumnos">
                    <select name="tipo_matricula" onchange="this.form.submit()" 
                        class="text-xs border border-gray-200 rounded px-2 py-1 <?= $a['tipo_matricula'] === 'liberado' ? 'bg-green-50 text-green-700' : ($a['tipo_matricula'] === 'recursando' ? 'bg-amber-50 text-amber-700' : 'bg-gray-50 text-gray-600') ?>">
                        <option value="regular" <?= $a['tipo_matricula'] === 'regular' ? 'selected' : '' ?>>Regular</option>
                        <option value="recursando" <?= $a['tipo_matricula'] === 'recursando' ? 'selected' : '' ?>>Recursando</option>
                        <option value="liberado" <?= $a['tipo_matricula'] === 'liberado' ? 'selected' : '' ?>>Liberado</option>
                    </select>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; // tab ?>
    
    <?php endif; // curso seleccionado ?>
</div>

<!-- Modal: Asignar Profesor -->
<div class="modal fade" id="modalProf" tabindex="-1"><div class="modal-dialog"><div class="modal-content" style="border-radius:12px;border:none">
    <div class="modal-header" style="border-bottom:1px solid #e5e7eb;padding:12px 16px">
        <h5 class="modal-title text-sm font-semibold" id="modalProfTitle">Asignar Profesor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <form method="POST">
    <div class="modal-body" style="padding:16px">
        <input type="hidden" name="action" value="asignar_profesor">
        <input type="hidden" name="materia_curso_id" id="mp_mcid">
        <input type="hidden" name="_back" value="cursos.php?id=<?= $cursoId ?>&tab=materias">
        
        <?php foreach (['profesor_id' => 'Profesor 1', 'profesor_id_2' => 'Profesor 2', 'profesor_id_3' => 'Profesor 3'] as $campo => $label): ?>
        <div class="mb-3">
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider"><?= $label ?></label>
            <select name="<?= $campo ?>" id="mp_<?= $campo ?>" class="form-select form-select-sm mt-1" style="font-size:13px">
                <option value="">— Sin asignar —</option>
                <?php foreach ($profesores as $p): ?>
                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['apellido'] . ', ' . $p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="modal-footer" style="border-top:1px solid #e5e7eb;padding:12px 16px">
        <button type="submit" name="campo" value="profesor_id" class="btn btn-sm btn-primary" style="background:#1e3a5f;border:none;border-radius:8px">Guardar</button>
    </div>
    </form>
</div></div></div>

<script>
function openProf(mcId, nombre, p1, p2, p3) {
    document.getElementById('modalProfTitle').textContent = 'Profesores: ' + nombre;
    document.getElementById('mp_mcid').value = mcId;
    document.getElementById('mp_profesor_id').value = p1 || '';
    document.getElementById('mp_profesor_id_2').value = p2 || '';
    document.getElementById('mp_profesor_id_3').value = p3 || '';
    new bootstrap.Modal(document.getElementById('modalProf')).show();
}
// Submit individual profesor change
document.querySelectorAll('#modalProf select').forEach(sel => {
    sel.addEventListener('change', function() {
        const form = this.closest('form');
        const campo = document.createElement('input');
        campo.type = 'hidden'; campo.name = 'campo'; campo.value = this.name;
        form.appendChild(campo);
        form.submit();
    });
});
</script>

<?php require_once 'footer.php'; ?>
