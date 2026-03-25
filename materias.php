<?php
/**
 * materias.php — Catálogo de Materias
 * RITE v2.0
 */
require_once 'config.php';
requireRole('admin', 'directivo');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'crear') {
            $nombre = trim($_POST['nombre']);
            $codigo = trim($_POST['codigo']);
            $tipo = $_POST['tipo'] ?? 'aula';
            if (!$nombre || !$codigo) throw new Exception('Nombre y código son obligatorios.');
            DB::insert("INSERT INTO materias (nombre, codigo, tipo) VALUES (?,?,?)", [$nombre, $codigo, $tipo]);
            flash('success', "Materia '$nombre' creada.");
        } elseif ($action === 'editar') {
            DB::query("UPDATE materias SET nombre=?, codigo=?, tipo=? WHERE id=?",
                [trim($_POST['nombre']), trim($_POST['codigo']), $_POST['tipo'], (int)$_POST['id']]);
            flash('success', 'Materia actualizada.');
        }
    } catch (Exception $e) { flash('danger', $e->getMessage()); }
    header('Location: materias.php'); exit;
}

$materias = DB::rows("SELECT m.*, 
    (SELECT COUNT(*) FROM materias_por_curso mc WHERE mc.materia_id=m.id) as asignaciones
    FROM materias m ORDER BY m.tipo, m.nombre");

require_once 'header.php';
?>
<div class="max-w-[1200px] mx-auto px-4 py-4">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-lg font-semibold text-gray-800">Catálogo de Materias</h1>
            <p class="text-xs text-gray-500"><?= count($materias) ?> materias registradas</p>
        </div>
        <button onclick="document.getElementById('modalCrear').classList.remove('hidden')" 
            class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-[#1e3a5f] rounded-lg hover:bg-[#152a44]">
            <i class="bi bi-plus-lg"></i> Nueva materia
        </button>
    </div>
    
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead><tr class="bg-gray-50 border-b text-xs text-gray-500 uppercase tracking-wider">
            <th class="text-left px-4 py-2.5 font-semibold">Materia</th>
            <th class="text-left px-4 py-2.5 font-semibold">Código</th>
            <th class="text-center px-4 py-2.5 font-semibold">Tipo</th>
            <th class="text-center px-4 py-2.5 font-semibold">Cursos</th>
            <th class="text-right px-4 py-2.5 font-semibold">Acción</th>
        </tr></thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($materias as $m): ?>
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-2.5 font-medium text-gray-800"><?= htmlspecialchars($m['nombre']) ?></td>
            <td class="px-4 py-2.5 text-gray-500 font-mono text-xs"><?= htmlspecialchars($m['codigo']) ?></td>
            <td class="px-4 py-2.5 text-center">
                <span class="text-[10px] px-1.5 py-0.5 rounded font-medium <?= $m['tipo']==='taller'?'bg-amber-50 text-amber-700':'bg-blue-50 text-blue-700' ?>"><?= ucfirst($m['tipo']) ?></span>
            </td>
            <td class="px-4 py-2.5 text-center text-gray-500"><?= $m['asignaciones'] ?></td>
            <td class="px-4 py-2.5 text-right">
                <button onclick="editMat(<?= htmlspecialchars(json_encode($m)) ?>)" class="text-gray-400 hover:text-blue-600 text-xs"><i class="bi bi-pencil"></i></button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal Crear -->
<div id="modalCrear" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
<div class="bg-white rounded-xl shadow-xl w-full max-w-sm mx-4 p-5">
    <div class="flex justify-between mb-4"><h3 class="font-semibold">Nueva materia</h3><button onclick="this.closest('[id]').classList.add('hidden')" class="text-gray-400"><i class="bi bi-x-lg"></i></button></div>
    <form method="POST" class="space-y-3">
        <input type="hidden" name="action" value="crear">
        <div><label class="text-xs font-semibold text-gray-500 uppercase">Nombre</label><input name="nombre" required class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500"></div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="text-xs font-semibold text-gray-500 uppercase">Código</label><input name="codigo" required class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="text-xs font-semibold text-gray-500 uppercase">Tipo</label><select name="tipo" class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-sm"><option value="aula">Aula</option><option value="taller">Taller</option></select></div>
        </div>
        <button class="w-full py-2 bg-[#1e3a5f] text-white rounded-lg text-sm font-semibold">Crear</button>
    </form>
</div>
</div>

<!-- Modal Editar -->
<div id="modalEditar" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
<div class="bg-white rounded-xl shadow-xl w-full max-w-sm mx-4 p-5">
    <div class="flex justify-between mb-4"><h3 class="font-semibold">Editar materia</h3><button onclick="this.closest('[id]').classList.add('hidden')" class="text-gray-400"><i class="bi bi-x-lg"></i></button></div>
    <form method="POST" class="space-y-3">
        <input type="hidden" name="action" value="editar">
        <input type="hidden" name="id" id="emId">
        <div><label class="text-xs font-semibold text-gray-500 uppercase">Nombre</label><input name="nombre" id="emNombre" required class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500"></div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="text-xs font-semibold text-gray-500 uppercase">Código</label><input name="codigo" id="emCodigo" required class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="text-xs font-semibold text-gray-500 uppercase">Tipo</label><select name="tipo" id="emTipo" class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-sm"><option value="aula">Aula</option><option value="taller">Taller</option></select></div>
        </div>
        <button class="w-full py-2 bg-[#1e3a5f] text-white rounded-lg text-sm font-semibold">Guardar</button>
    </form>
</div>
</div>

<script>
function editMat(m) { document.getElementById('emId').value=m.id; document.getElementById('emNombre').value=m.nombre; document.getElementById('emCodigo').value=m.codigo; document.getElementById('emTipo').value=m.tipo; document.getElementById('modalEditar').classList.remove('hidden'); }
document.querySelectorAll('.fixed').forEach(m => m.addEventListener('click', e => { if(e.target===m) m.classList.add('hidden'); }));
</script>
<?php require_once 'footer.php'; ?>
