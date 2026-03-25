<?php
/**
 * bloqueos.php — Gestión de Bloqueos de Calificaciones
 * RITE v2.0
 */
require_once 'config.php';
requireRole('admin', 'directivo');

$ciclo = cicloId();

// Asegurar que exista registro de bloqueos
DB::query("INSERT OR IGNORE INTO bloqueos (ciclo_lectivo_id) VALUES (?)", [$ciclo]);

// Guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campos = [
        'bloqueo_general',
        'valoracion_1bim', 'desempeno_1bim', 'observaciones_1bim', 'calificacion_1c',
        'valoracion_3bim', 'desempeno_3bim', 'observaciones_3bim', 'calificacion_2c',
        'intensificacion_1c', 'intensificacion_diciembre', 'intensificacion_febrero',
        'calificacion_final'
    ];
    
    $sets = [];
    $params = [];
    foreach ($campos as $c) {
        $sets[] = "$c = ?";
        $params[] = isset($_POST[$c]) ? 1 : 0;
    }
    $params[] = $ciclo;
    
    DB::query("UPDATE bloqueos SET " . implode(', ', $sets) . " WHERE ciclo_lectivo_id = ?", $params);
    flash('success', 'Configuración de bloqueos actualizada.');
    header('Location: bloqueos.php');
    exit;
}

$bloqueos = DB::row("SELECT * FROM bloqueos WHERE ciclo_lectivo_id = ?", [$ciclo]);

require_once 'header.php';

// Definir secciones para la UI
$secciones = [
    'general' => [
        'titulo' => 'Bloqueo General',
        'desc' => 'Bloquea TODO el sistema de calificaciones para los profesores.',
        'color' => 'red',
        'icon' => 'shield-lock-fill',
        'campos' => ['bloqueo_general' => 'Activar bloqueo general']
    ],
    '1bim' => [
        'titulo' => '1° Bimestre',
        'desc' => 'Columnas del primer bimestre.',
        'color' => 'emerald',
        'icon' => '1-circle-fill',
        'campos' => [
            'valoracion_1bim' => 'Valoración (TEA/TEP/TED)',
            'desempeno_1bim' => 'Desempeño (MB/B/R)',
            'observaciones_1bim' => 'Observaciones',
        ]
    ],
    '1c' => [
        'titulo' => '1° Cuatrimestre',
        'desc' => 'Calificación numérica del 1° cuatrimestre.',
        'color' => 'emerald',
        'icon' => 'calendar-event',
        'campos' => [
            'calificacion_1c' => 'Calificación 1°C (1-10)',
        ]
    ],
    '3bim' => [
        'titulo' => '3° Bimestre',
        'desc' => 'Columnas del tercer bimestre.',
        'color' => 'amber',
        'icon' => '3-circle-fill',
        'campos' => [
            'valoracion_3bim' => 'Valoración (TEA/TEP/TED)',
            'desempeno_3bim' => 'Desempeño (MB/B/R)',
            'observaciones_3bim' => 'Observaciones',
        ]
    ],
    '2c' => [
        'titulo' => '2° Cuatrimestre',
        'desc' => 'Calificación numérica del 2° cuatrimestre.',
        'color' => 'amber',
        'icon' => 'calendar2-event',
        'campos' => [
            'calificacion_2c' => 'Calificación 2°C (1-10)',
        ]
    ],
    'intensif' => [
        'titulo' => 'Intensificación',
        'desc' => 'Periodos de intensificación para alumnos que desaprueban.',
        'color' => 'pink',
        'icon' => 'arrow-repeat',
        'campos' => [
            'intensificacion_1c' => 'Intensificación 1°C',
            'intensificacion_diciembre' => 'Intensificación Diciembre',
            'intensificacion_febrero' => 'Intensificación Febrero',
        ]
    ],
    'final' => [
        'titulo' => 'Calificación Final',
        'desc' => 'Nota definitiva del ciclo lectivo.',
        'color' => 'violet',
        'icon' => 'trophy-fill',
        'campos' => [
            'calificacion_final' => 'Calificación Final',
        ]
    ],
];

// Contar bloqueados
$totalBloqueados = 0;
$totalCampos = 0;
foreach ($secciones as $sec) {
    foreach ($sec['campos'] as $k => $v) {
        $totalCampos++;
        if (!empty($bloqueos[$k])) $totalBloqueados++;
    }
}
?>

<div class="max-w-[900px] mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-lg font-semibold text-gray-800">Bloqueos de Calificaciones</h1>
            <p class="text-xs text-gray-500 mt-0.5">
                Ciclo <?= cicloActivo()['anio'] ?? '' ?> · 
                <span class="<?= $totalBloqueados > 0 ? 'text-red-600 font-medium' : 'text-green-600' ?>">
                    <?= $totalBloqueados ?>/<?= $totalCampos ?> campos bloqueados
                </span>
            </p>
        </div>
        <div class="flex gap-2">
            <button onclick="toggleAll(true)" class="text-xs px-3 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 transition">
                <i class="bi bi-lock-fill"></i> Bloquear todo
            </button>
            <button onclick="toggleAll(false)" class="text-xs px-3 py-1.5 rounded-lg border border-green-200 text-green-600 hover:bg-green-50 transition">
                <i class="bi bi-unlock-fill"></i> Desbloquear todo
            </button>
        </div>
    </div>
    
    <form method="POST" id="formBloqueos">
    <?php foreach ($secciones as $key => $sec): ?>
    <div class="bg-white rounded-xl border border-gray-200 mb-3 overflow-hidden">
        <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-100">
            <div class="w-8 h-8 rounded-lg bg-<?= $sec['color'] ?>-50 text-<?= $sec['color'] ?>-600 flex items-center justify-center shrink-0">
                <i class="bi bi-<?= $sec['icon'] ?> text-sm"></i>
            </div>
            <div class="flex-1">
                <h3 class="text-sm font-semibold text-gray-800"><?= $sec['titulo'] ?></h3>
                <p class="text-[11px] text-gray-400"><?= $sec['desc'] ?></p>
            </div>
            <?php 
            $secBloqueados = 0;
            foreach ($sec['campos'] as $k => $v) { if (!empty($bloqueos[$k])) $secBloqueados++; }
            $secTotal = count($sec['campos']);
            ?>
            <span class="text-[10px] font-medium px-2 py-0.5 rounded-full <?= $secBloqueados > 0 ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600' ?>">
                <?= $secBloqueados > 0 ? "$secBloqueados bloqueado" . ($secBloqueados > 1 ? 's' : '') : 'Abierto' ?>
            </span>
        </div>
        <div class="px-4 py-3 space-y-2">
            <?php foreach ($sec['campos'] as $campo => $label): ?>
            <label class="flex items-center gap-3 py-1 px-2 rounded-lg hover:bg-gray-50 cursor-pointer transition group">
                <div class="relative">
                    <input type="checkbox" name="<?= $campo ?>" value="1" <?= !empty($bloqueos[$campo]) ? 'checked' : '' ?>
                        class="sr-only peer bloqueo-check">
                    <div class="w-9 h-5 bg-gray-200 rounded-full peer-checked:bg-red-500 transition-colors"></div>
                    <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow peer-checked:translate-x-4 transition-transform"></div>
                </div>
                <div class="flex-1">
                    <span class="text-sm text-gray-700 group-hover:text-gray-900"><?= $label ?></span>
                </div>
                <span class="text-[10px] font-mono <?= !empty($bloqueos[$campo]) ? 'text-red-500' : 'text-green-500' ?> peer-state">
                    <i class="bi bi-<?= !empty($bloqueos[$campo]) ? 'lock-fill' : 'unlock' ?>"></i>
                    <?= !empty($bloqueos[$campo]) ? 'BLOQUEADO' : 'ABIERTO' ?>
                </span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    
    <div class="flex justify-end mt-4">
        <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-semibold text-white bg-[#1e3a5f] rounded-xl hover:bg-[#152a44] transition shadow-sm">
            <i class="bi bi-check2-all"></i> Guardar configuración
        </button>
    </div>
    </form>
</div>

<script>
function toggleAll(block) {
    document.querySelectorAll('.bloqueo-check').forEach(cb => cb.checked = block);
}
</script>

<?php require_once 'footer.php'; ?>
