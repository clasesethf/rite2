<?php
/**
 * ajax_guardar.php — Guardar calificaciones via AJAX
 * RITE v2.0
 */
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['ok' => false, 'error' => 'Datos inválidos']);
    exit;
}

$ciclo = cicloId();
$saved = 0;
$errors = [];

try {
    DB::get()->beginTransaction();
    
    foreach ($data['calificaciones'] ?? [] as $row) {
        $estId = (int)($row['estudiante_id'] ?? 0);
        $mcId = (int)($row['materia_curso_id'] ?? 0);
        if (!$estId || !$mcId) continue;
        
        // Verificar permisos del profesor
        if (activeRole() === 'profesor') {
            $perm = DB::row("SELECT id FROM materias_por_curso WHERE id=? AND (profesor_id=? OR profesor_id_2=? OR profesor_id_3=?)", 
                [$mcId, userId(), userId(), userId()]);
            if (!$perm) {
                $errors[] = "Sin permiso para materia $mcId";
                continue;
            }
        }
        
        // Campos permitidos
        $campos = [
            'valoracion_1bim', 'desempeno_1bim', 'observaciones_1bim',
            'calificacion_1c',
            'valoracion_3bim', 'desempeno_3bim', 'observaciones_3bim',
            'calificacion_2c',
            'intensificacion_1c', 'intensificacion_diciembre', 'intensificacion_febrero',
            'calificacion_final'
        ];
        
        // Cargar bloqueos
        $bloqueos = DB::row("SELECT * FROM bloqueos WHERE ciclo_lectivo_id=?", [$ciclo]);
        
        // Upsert
        $existing = DB::row("SELECT id FROM calificaciones WHERE estudiante_id=? AND materia_curso_id=? AND ciclo_lectivo_id=?",
            [$estId, $mcId, $ciclo]);
        
        if (!$existing) {
            DB::insert("INSERT INTO calificaciones (estudiante_id, materia_curso_id, ciclo_lectivo_id) VALUES (?,?,?)",
                [$estId, $mcId, $ciclo]);
        }
        
        foreach ($campos as $campo) {
            if (!array_key_exists($campo, $row)) continue;
            
            // Verificar bloqueo
            $bloqueoKey = str_replace(['calificacion_', 'intensificacion_'], ['calificacion_', 'intensificacion_'], $campo);
            if ($bloqueos && !empty($bloqueos['bloqueo_general']) && activeRole() === 'profesor') continue;
            if ($bloqueos && !empty($bloqueos[$campo]) && activeRole() === 'profesor') continue;
            
            $val = $row[$campo];
            if ($val === '' || $val === null) $val = null;
            
            // Validar rango numérico
            if (in_array($campo, ['calificacion_1c','calificacion_2c','intensificacion_1c','intensificacion_diciembre','intensificacion_febrero','calificacion_final'])) {
                if ($val !== null && ($val < 1 || $val > 10)) continue;
            }
            
            DB::query("UPDATE calificaciones SET $campo=? WHERE estudiante_id=? AND materia_curso_id=? AND ciclo_lectivo_id=?",
                [$val, $estId, $mcId, $ciclo]);
        }
        
        // Auto-calcular final
        $cal = DB::row("SELECT * FROM calificaciones WHERE estudiante_id=? AND materia_curso_id=? AND ciclo_lectivo_id=?",
            [$estId, $mcId, $ciclo]);
        
        if ($cal) {
            $final = calcularFinal($cal);
            if ($final !== null) {
                DB::query("UPDATE calificaciones SET calificacion_final=? WHERE id=?", [$final, $cal['id']]);
            }
        }
        
        $saved++;
    }
    
    // Contenidos
    foreach ($data['contenidos'] ?? [] as $row) {
        $contId = (int)($row['contenido_id'] ?? 0);
        $estId = (int)($row['estudiante_id'] ?? 0);
        $estado = $row['estado'] ?? null;
        $nota = $row['calificacion_numerica'] ?? null;
        
        if (!$contId || !$estId) continue;
        
        $existing = DB::row("SELECT id FROM contenidos_calificaciones WHERE contenido_id=? AND estudiante_id=?", [$contId, $estId]);
        if ($existing) {
            DB::query("UPDATE contenidos_calificaciones SET estado=?, calificacion_numerica=?, updated_at=CURRENT_TIMESTAMP WHERE id=?",
                [$estado, $nota, $existing['id']]);
        } else {
            DB::insert("INSERT INTO contenidos_calificaciones (contenido_id, estudiante_id, estado, calificacion_numerica) VALUES (?,?,?,?)",
                [$contId, $estId, $estado, $nota]);
        }
    }
    
    DB::get()->commit();
    echo json_encode(['ok' => true, 'saved' => $saved, 'errors' => $errors]);
    
} catch (Exception $e) {
    DB::get()->rollBack();
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

/**
 * Calcular calificación final automáticamente
 */
function calcularFinal(array $cal): ?int {
    $c1 = $cal['calificacion_1c'];
    $c2 = $cal['calificacion_2c'];
    
    if ($c1 === null || $c2 === null) return null;
    
    // Si tiene intensificación febrero, usa esa
    if ($cal['intensificacion_febrero'] !== null) return (int)$cal['intensificacion_febrero'];
    
    // Si tiene intensificación diciembre
    if ($cal['intensificacion_diciembre'] !== null) {
        if ($cal['intensificacion_diciembre'] >= 4) return (int)$cal['intensificacion_diciembre'];
        return null; // Va a febrero
    }
    
    // Si tiene intensificación 1c
    $nota1 = ($cal['intensificacion_1c'] !== null && $cal['intensificacion_1c'] >= 4) 
        ? (int)$cal['intensificacion_1c'] : (int)$c1;
    
    // Promedio
    $prom = ($nota1 + (int)$c2) / 2;
    $final = (int)round($prom);
    
    // Si desaprueba, va a intensificación
    if ($final < 7) return null;
    
    return $final;
}
