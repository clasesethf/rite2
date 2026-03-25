<?php
/**
 * includes/funciones_grupos.php — Agrupación y Cálculo de Materias
 * RITE v2.0 — Lógica portada del sistema anterior
 * 
 * Regla para cuatrimestres:
 *   Si MIN(notas) >= 7 → promedio entero (INT)
 *   Sino → MIN (nota más baja prevalece)
 * 
 * Regla para final (fórmula Excel):
 *   1. Si MIN(1C, 2C) >= 7 → promedio(1C, 2C)
 *   2. Si 2C >= 7 y hay intensif_1C → promedio(2C, intensif_1C)
 *   3. Si hay diciembre → diciembre
 *   4. Si hay febrero → febrero
 *   5. Sino → null
 */

/**
 * Obtener grupos de materias para un año de curso
 */
function obtenerGrupos(int $cursoAnio, int $cicloId): array {
    $grupos = DB::rows(
        "SELECT gm.*, rc.tipo_calculo, rc.nota_minima_prevalece 
         FROM grupos_materias gm
         LEFT JOIN reglas_calculo_grupo rc ON gm.id = rc.grupo_id AND rc.activo = 1
         WHERE gm.curso_anio = ? AND gm.ciclo_lectivo_id = ? AND gm.activo = 1
         ORDER BY gm.orden_visualizacion, gm.nombre",
        [$cursoAnio, $cicloId]
    );
    
    foreach ($grupos as &$g) {
        $g['materias'] = DB::rows(
            "SELECT mg.*, m.nombre as materia_nombre, m.codigo as materia_codigo,
                    mc.id as materia_curso_id, mc.requiere_subgrupos
             FROM materias_grupo mg
             JOIN materias_por_curso mc ON mg.materia_curso_id = mc.id
             JOIN materias m ON mc.materia_id = m.id
             WHERE mg.grupo_id = ? AND mg.activo = 1
             ORDER BY m.nombre",
            [$g['id']]
        );
    }
    
    return $grupos;
}

/**
 * IDs de materias que están en algún grupo (para excluirlas de la lista individual)
 */
function materiasEnGrupos(int $cursoAnio, int $cicloId): array {
    $rows = DB::rows(
        "SELECT DISTINCT mg.materia_curso_id
         FROM materias_grupo mg
         JOIN grupos_materias gm ON mg.grupo_id = gm.id
         WHERE gm.curso_anio = ? AND gm.ciclo_lectivo_id = ? AND gm.activo = 1 AND mg.activo = 1",
        [$cursoAnio, $cicloId]
    );
    return array_column($rows, 'materia_curso_id');
}

/**
 * Calcular nota de grupo para un cuatrimestre
 * 
 * Lógica: Si MIN >= 7 → INT(promedio). Sino → MIN.
 */
function calcularGrupoCuatrimestre(int $estId, array $grupo, int $cuatrimestre, int $cicloId): ?float {
    $col = $cuatrimestre === 1 ? 'calificacion_1c' : 'calificacion_2c';
    $notas = [];
    
    foreach ($grupo['materias'] as $mat) {
        $cal = DB::row(
            "SELECT $col FROM calificaciones WHERE estudiante_id=? AND materia_curso_id=? AND ciclo_lectivo_id=?",
            [$estId, $mat['materia_curso_id'], $cicloId]
        );
        if ($cal && $cal[$col] !== null && $cal[$col] !== '') {
            $notas[] = (float)$cal[$col];
        }
    }
    
    if (empty($notas)) return null;
    
    if (min($notas) >= 7) {
        return floor(array_sum($notas) / count($notas));
    }
    return min($notas);
}

/**
 * Obtener valoración del grupo para un cuatrimestre (bimestral)
 * Toma la peor valoración de las materias del grupo
 */
function calcularGrupoValoracion(int $estId, array $grupo, int $cuatrimestre, int $cicloId): ?string {
    $col = $cuatrimestre === 1 ? 'valoracion_1bim' : 'valoracion_3bim';
    $vals = [];
    $orden = ['TED' => 1, 'TEP' => 2, 'TEA' => 3];
    
    foreach ($grupo['materias'] as $mat) {
        $cal = DB::row(
            "SELECT $col FROM calificaciones WHERE estudiante_id=? AND materia_curso_id=? AND ciclo_lectivo_id=?",
            [$estId, $mat['materia_curso_id'], $cicloId]
        );
        if ($cal && !empty($cal[$col])) {
            $vals[] = $cal[$col];
        }
    }
    
    if (empty($vals)) return null;
    
    // Retornar la peor (menor orden)
    usort($vals, fn($a, $b) => ($orden[$a] ?? 0) - ($orden[$b] ?? 0));
    return $vals[0];
}

/**
 * Obtener intensificaciones del grupo
 * Toma la MENOR (más restrictiva) de las materias del grupo
 */
function calcularGrupoIntensificaciones(int $estId, array $grupo, int $cicloId): array {
    $result = ['intensificacion_1c' => null, 'intensificacion_diciembre' => null, 'intensificacion_febrero' => null];
    
    foreach ($grupo['materias'] as $mat) {
        $cal = DB::row(
            "SELECT intensificacion_1c, intensificacion_diciembre, intensificacion_febrero
             FROM calificaciones WHERE estudiante_id=? AND materia_curso_id=? AND ciclo_lectivo_id=?",
            [$estId, $mat['materia_curso_id'], $cicloId]
        );
        if (!$cal) continue;
        
        foreach (['intensificacion_1c', 'intensificacion_diciembre', 'intensificacion_febrero'] as $campo) {
            if ($cal[$campo] !== null && $cal[$campo] !== '') {
                $v = (float)$cal[$campo];
                $result[$campo] = $result[$campo] === null ? $v : min($result[$campo], $v);
            }
        }
    }
    
    return $result;
}

/**
 * Calcular nota final del grupo (fórmula Excel)
 * 
 * 1. Si MIN(1C, 2C) >= 7 → promedio(1C, 2C)
 * 2. Si 2C >= 7 y hay intensif_1C → promedio(2C, intensif_1C)
 * 3. Si hay diciembre → diciembre
 * 4. Si hay febrero → febrero
 * 5. null
 */
function calcularFinalGrupo(?float $cal1c, ?float $cal2c, ?float $int1c, ?float $intDic, ?float $intFeb): ?float {
    if ($cal1c === null || $cal2c === null || $cal1c <= 0 || $cal2c <= 0) return null;
    
    // Paso 1
    if (min($cal1c, $cal2c) >= 7) {
        return ($cal1c + $cal2c) / 2;
    }
    
    // Paso 2
    if ($cal2c >= 7 && $int1c !== null) {
        return ($cal2c + $int1c) / 2;
    }
    
    // Paso 3
    if ($intDic !== null) return $intDic;
    
    // Paso 4
    if ($intFeb !== null) return $intFeb;
    
    return null;
}

/**
 * Calcular nota final de materia individual (misma fórmula)
 */
function calcularFinalMateria(?int $cal1c, ?int $cal2c, ?int $int1c, ?int $intDic, ?int $intFeb): ?float {
    return calcularFinalGrupo(
        $cal1c !== null ? (float)$cal1c : null,
        $cal2c !== null ? (float)$cal2c : null,
        $int1c !== null ? (float)$int1c : null,
        $intDic !== null ? (float)$intDic : null,
        $intFeb !== null ? (float)$intFeb : null
    );
}

/**
 * Obtener datos completos de un grupo para un estudiante (para boletín)
 */
function datosGrupoEstudiante(int $estId, array $grupo, int $cicloId): array {
    $cal1c = calcularGrupoCuatrimestre($estId, $grupo, 1, $cicloId);
    $cal2c = calcularGrupoCuatrimestre($estId, $grupo, 2, $cicloId);
    $val1 = calcularGrupoValoracion($estId, $grupo, 1, $cicloId);
    $val2 = calcularGrupoValoracion($estId, $grupo, 2, $cicloId);
    $ints = calcularGrupoIntensificaciones($estId, $grupo, $cicloId);
    
    $final = calcularFinalGrupo($cal1c, $cal2c, $ints['intensificacion_1c'], $ints['intensificacion_diciembre'], $ints['intensificacion_febrero']);
    
    return [
        'nombre' => $grupo['nombre'],
        'codigo' => $grupo['codigo'],
        'valoracion_1bim' => $val1,
        'calificacion_1c' => $cal1c,
        'valoracion_3bim' => $val2,
        'calificacion_2c' => $cal2c,
        'intensificacion_1c' => $ints['intensificacion_1c'],
        'intensificacion_diciembre' => $ints['intensificacion_diciembre'],
        'intensificacion_febrero' => $ints['intensificacion_febrero'],
        'calificacion_final' => $final !== null ? round($final) : null,
        'es_grupo' => true,
        'submaterias' => array_column($grupo['materias'], 'materia_nombre'),
    ];
}
