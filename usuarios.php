<?php
/**
 * usuarios.php — Gestión de Usuarios
 * RITE v2.0
 */
require_once 'config.php';
requireRole('admin', 'directivo');

// Acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'crear':
                $nombre = trim($_POST['nombre']);
                $apellido = trim($_POST['apellido']);
                $dni = trim($_POST['dni']);
                $tipo = $_POST['tipo'];
                $pass = $_POST['contrasena'] ?: $dni;
                if (!$nombre || !$apellido || !$dni || !$tipo) throw new Exception('Completá todos los campos.');
                DB::insert("INSERT INTO usuarios (nombre, apellido, dni, contrasena, tipo) VALUES (?,?,?,?,?)",
                    [$nombre, $apellido, $dni, $pass, $tipo]);
                flash('success', "Usuario $apellido creado.");
                break;
            case 'editar':
                $id = (int)$_POST['id'];
                $nombre = trim($_POST['nombre']);
                $apellido = trim($_POST['apellido']);
                $dni = trim($_POST['dni']);
                $tipo = $_POST['tipo'];
                DB::query("UPDATE usuarios SET nombre=?, apellido=?, dni=?, tipo=? WHERE id=?",
                    [$nombre, $apellido, $dni, $tipo, $id]);
                if (!empty($_POST['contrasena'])) {
                    DB::query("UPDATE usuarios SET contrasena=? WHERE id=?", [$_POST['contrasena'], $id]);
                }
                flash('success', 'Usuario actualizado.');
                break;
            case 'toggle':
                $id = (int)$_POST['id'];
                DB::query("UPDATE usuarios SET activo = NOT activo WHERE id=?", [$id]);
                flash('success', 'Estado actualizado.');
                break;
        }
    } catch (Exception $e) {
        flash('danger', $e->getMessage());
    }
    header('Location: usuarios.php' . (isset($_GET['tipo']) ? '?tipo=' . $_GET['tipo'] : ''));
    exit;
}

$filtroTipo = $_GET['tipo'] ?? '';
$buscar = $_GET['q'] ?? '';

$sql = "SELECT * FROM usuarios WHERE 1=1";
$params = [];
if ($filtroTipo) { $sql .= " AND tipo=?"; $params[] = $filtroTipo; }
if ($buscar) { $sql .= " AND (nombre LIKE ? OR apellido LIKE ? OR dni LIKE ?)"; $params = array_merge($params, ["%$buscar%","%$buscar%","%$buscar%"]); }
$sql .= " ORDER BY tipo, apellido, nombre";
$usuarios = DB::rows($sql, $params);

require_once 'header.php';

$badges = ['admin'=>'bg-red-50 text-red-700','directivo'=>'bg-purple-50 text-purple-700','profesor'=>'bg-green-50 text-green-700','preceptor'=>'bg-amber-50 text-amber-700','estudiante'=>'bg-blue-50 text-blue-700'];
?>
<div class="max-w-[1400px] mx-auto px-4 py-4">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-lg font-semibold text-gray-800">Usuarios</h1>
        <button onclick="document.getElementById('modalCrear').classList.remove('hidden')" 
            class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-[#1e3a5f] rounded-lg hover:bg-[#152a44] transition">
            <i class="bi bi-plus-lg"></i> Nuevo usuario
        </button>
    </div>
    
    <!-- Filtros -->
    <form class="flex flex-wrap gap-2 mb-4">
        <div class="relative flex-1 min-w-[200px] max-w-[320px]">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($buscar) ?>" placeholder="Buscar por nombre o DNI..."
                class="w-full pl-8 pr-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        <select name="tipo" onchange="this.form.submit()" class="px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none">
            <option value="">Todos los roles</option>
            <?php foreach (['estudiante','profesor','preceptor','directivo','admin'] as $t): ?>
            <option value="<?= $t ?>" <?= $filtroTipo===$t?'selected':'' ?>><?= ucfirst($t) ?>s</option>
            <?php endforeach; ?>
        </select>
        <button class="px-3 py-2 text-sm bg-gray-100 rounded-lg hover:bg-gray-200 transition">Buscar</button>
    </form>
    
    <div class="text-xs text-gray-500 mb-2"><?= count($usuarios) ?> usuarios encontrados</div>
    
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead><tr class="bg-gray-50 border-b text-xs text-gray-500 uppercase tracking-wider">
            <th class="text-left px-4 py-2.5 font-semibold">Nombre</th>
            <th class="text-left px-4 py-2.5 font-semibold">DNI</th>
            <th class="text-left px-4 py-2.5 font-semibold">Rol</th>
            <th class="text-center px-4 py-2.5 font-semibold">Estado</th>
            <th class="text-right px-4 py-2.5 font-semibold">Acciones</th>
        </tr></thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($usuarios as $u): ?>
        <tr class="hover:bg-gray-50 <?= !$u['activo']?'opacity-40':'' ?>">
            <td class="px-4 py-2.5 font-medium text-gray-800"><?= htmlspecialchars($u['apellido'] . ', ' . $u['nombre']) ?></td>
            <td class="px-4 py-2.5 text-gray-500 font-mono text-xs"><?= htmlspecialchars($u['dni']) ?></td>
            <td class="px-4 py-2.5"><span class="inline-block px-2 py-0.5 rounded text-xs font-medium <?= $badges[$u['tipo']] ?? '' ?>"><?= ucfirst($u['tipo']) ?></span></td>
            <td class="px-4 py-2.5 text-center">
                <span class="inline-block w-2 h-2 rounded-full <?= $u['activo']?'bg-green-400':'bg-gray-300' ?>"></span>
            </td>
            <td class="px-4 py-2.5 text-right space-x-1">
                <button onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)" class="text-gray-400 hover:text-blue-600"><i class="bi bi-pencil"></i></button>
                <form method="POST" class="inline"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button class="text-gray-400 hover:text-red-500"><i class="bi bi-<?= $u['activo']?'eye-slash':'eye' ?>"></i></button></form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal Crear -->
<div id="modalCrear" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
<div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-5">
    <div class="flex items-center justify-between mb-4"><h3 class="font-semibold text-gray-800">Nuevo usuario</h3><button onclick="this.closest('[id]').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="bi bi-x-lg"></i></button></div>
    <form method="POST" class="space-y-3">
        <input type="hidden" name="action" value="crear">
        <div class="grid grid-cols-2 gap-3">
            <div><label class="text-xs font-semibold text-gray-500 uppercase">Nombre</label><input name="nombre" required class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="text-xs font-semibold text-gray-500 uppercase">Apellido</label><input name="apellido" required class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500"></div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="text-xs font-semibold text-gray-500 uppercase">DNI / Usuario</label><input name="dni" required class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="text-xs font-semibold text-gray-500 uppercase">Rol</label><select name="tipo" required class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none">
                <option value="estudiante">Estudiante</option><option value="profesor">Profesor</option><option value="preceptor">Preceptor</option><option value="directivo">Directivo</option><option value="admin">Admin</option>
            </select></div>
        </div>
        <div><label class="text-xs font-semibold text-gray-500 uppercase">Contraseña (opcional, default=DNI)</label><input name="contrasena" class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500"></div>
        <button class="w-full py-2 bg-[#1e3a5f] text-white rounded-lg text-sm font-semibold hover:bg-[#152a44]">Crear</button>
    </form>
</div>
</div>

<!-- Modal Editar -->
<div id="modalEditar" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
<div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-5">
    <div class="flex items-center justify-between mb-4"><h3 class="font-semibold text-gray-800">Editar usuario</h3><button onclick="this.closest('[id]').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="bi bi-x-lg"></i></button></div>
    <form method="POST" class="space-y-3" id="formEditar">
        <input type="hidden" name="action" value="editar">
        <input type="hidden" name="id" id="editId">
        <div class="grid grid-cols-2 gap-3">
            <div><label class="text-xs font-semibold text-gray-500 uppercase">Nombre</label><input name="nombre" id="editNombre" required class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="text-xs font-semibold text-gray-500 uppercase">Apellido</label><input name="apellido" id="editApellido" required class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500"></div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="text-xs font-semibold text-gray-500 uppercase">DNI</label><input name="dni" id="editDni" required class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="text-xs font-semibold text-gray-500 uppercase">Rol</label><select name="tipo" id="editTipo" class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none">
                <option value="estudiante">Estudiante</option><option value="profesor">Profesor</option><option value="preceptor">Preceptor</option><option value="directivo">Directivo</option><option value="admin">Admin</option>
            </select></div>
        </div>
        <div><label class="text-xs font-semibold text-gray-500 uppercase">Nueva contraseña (dejar vacío para no cambiar)</label><input name="contrasena" class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500"></div>
        <button class="w-full py-2 bg-[#1e3a5f] text-white rounded-lg text-sm font-semibold hover:bg-[#152a44]">Guardar</button>
    </form>
</div>
</div>

<script>
function editUser(u) {
    document.getElementById('editId').value = u.id;
    document.getElementById('editNombre').value = u.nombre;
    document.getElementById('editApellido').value = u.apellido;
    document.getElementById('editDni').value = u.dni;
    document.getElementById('editTipo').value = u.tipo;
    document.getElementById('modalEditar').classList.remove('hidden');
}
document.querySelectorAll('.fixed').forEach(m => m.addEventListener('click', e => { if (e.target === m) m.classList.add('hidden'); }));
</script>
<?php require_once 'footer.php'; ?>
