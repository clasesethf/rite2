<?php
/**
 * ajax_contenidos.php — CRUD de contenidos via AJAX
 * RITE v2.0
 */
require_once 'config.php';
requireLogin();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'crear':
            $mcId = (int)($data['materia_curso_id'] ?? 0);
            $titulo = trim($data['titulo'] ?? '');
            $tipo = $data['tipo_evaluacion'] ?? 'cualitativa';
            $fecha = $data['fecha_clase'] ?? date('Y-m-d');
            $bimestre = (int)($data['bimestre'] ?? 1);
            
            if (!$mcId || !$titulo) throw new Exception('Título y materia requeridos.');
            
            // Verificar permisos
            if (activeRole() === 'profesor') {
                $perm = DB::row("SELECT id FROM materias_por_curso WHERE id=? AND (profesor_id=? OR profesor_id_2=? OR profesor_id_3=?)",
                    [$mcId, userId(), userId(), userId()]);
                if (!$perm) throw new Exception('Sin permiso.');
            }
            
            // Siguiente orden
            $max = DB::row("SELECT MAX(orden) as m FROM contenidos WHERE materia_curso_id=? AND bimestre=?", [$mcId, $bimestre])['m'] ?? 0;
            
            $id = DB::insert("INSERT INTO contenidos (materia_curso_id, profesor_id, titulo, fecha_clase, bimestre, tipo_evaluacion, orden)
                VALUES (?,?,?,?,?,?,?)", [$mcId, userId(), $titulo, $fecha, $bimestre, $tipo, $max + 1]);
            
            echo json_encode(['ok' => true, 'id' => $id]);
            break;
            
        case 'editar':
            $id = (int)($data['id'] ?? 0);
            $titulo = trim($data['titulo'] ?? '');
            if (!$id || !$titulo) throw new Exception('Datos inválidos.');
            
            DB::query("UPDATE contenidos SET titulo=?, tipo_evaluacion=?, fecha_clase=?, bimestre=? WHERE id=?",
                [$titulo, $data['tipo_evaluacion'] ?? 'cualitativa', $data['fecha_clase'] ?? null, $data['bimestre'] ?? 1, $id]);
            echo json_encode(['ok' => true]);
            break;
            
        case 'eliminar':
            $id = (int)($data['id'] ?? 0);
            DB::query("DELETE FROM contenidos_calificaciones WHERE contenido_id=?", [$id]);
            DB::query("DELETE FROM contenidos WHERE id=?", [$id]);
            echo json_encode(['ok' => true]);
            break;
            
        case 'listar':
            $mcId = (int)($data['materia_curso_id'] ?? $_GET['mc'] ?? 0);
            $contenidos = DB::rows("SELECT * FROM contenidos WHERE materia_curso_id=? AND activo=1 ORDER BY bimestre, orden", [$mcId]);
            echo json_encode(['ok' => true, 'contenidos' => $contenidos]);
            break;
            
        case 'acreditar_todos':
            $contId = (int)($data['contenido_id'] ?? 0);
            $mcId = (int)($data['materia_curso_id'] ?? 0);
            $cursoId = (int)($data['curso_id'] ?? 0);
            
            // Obtener todos los estudiantes del curso
            $estudiantes = DB::rows("SELECT u.id FROM usuarios u JOIN matriculas m ON u.id=m.estudiante_id 
                WHERE m.curso_id=? AND m.estado='activo' AND m.tipo_matricula != 'liberado'", [$cursoId]);
            
            $count = 0;
            foreach ($estudiantes as $est) {
                $existing = DB::row("SELECT id FROM contenidos_calificaciones WHERE contenido_id=? AND estudiante_id=?", [$contId, $est['id']]);
                if (!$existing) {
                    DB::insert("INSERT INTO contenidos_calificaciones (contenido_id, estudiante_id, estado) VALUES (?,?,'A')", [$contId, $est['id']]);
                    $count++;
                } else {
                    DB::query("UPDATE contenidos_calificaciones SET estado='A' WHERE id=?", [$existing['id']]);
                    $count++;
                }
            }
            echo json_encode(['ok' => true, 'count' => $count]);
            break;
            
        default:
            echo json_encode(['ok' => false, 'error' => 'Acción desconocida']);
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
