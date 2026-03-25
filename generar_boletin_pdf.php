<?php
/**
 * generar_boletin_pdf.php — Generación de Boletín PDF (RITE)
 * RITE v2.0 — Resolución Nº 1650/24
 * 
 * Formato: A3 apaisado
 * Lógica de agrupación de materias respetada del sistema anterior
 */
ob_start();
require_once 'config.php';
requireLogin();
require_once 'includes/funciones_grupos.php';
require_once 'lib/fpdf/fpdf.php';

$ciclo = cicloId();
$cicloData = cicloActivo();
$cursoId = (int)($_GET['curso'] ?? 0);
$tipo = $_GET['tipo'] ?? 'cuatrimestral'; // cuatrimestral | final
$cuatrimestre = (int)($_GET['cuatrimestre'] ?? 1);
$estudianteId = (int)($_GET['estudiante'] ?? 0);
$todos = isset($_GET['todos']);

if (!$cursoId) { die('Falta parámetro curso.'); }

$cursoInfo = DB::row("SELECT * FROM cursos WHERE id=?", [$cursoId]);
if (!$cursoInfo) { die('Curso no encontrado.'); }

// Obtener lista de estudiantes
if ($todos) {
    $estudiantes = DB::rows(
        "SELECT u.id, u.apellido, u.nombre, u.dni, mat.tipo_matricula
         FROM usuarios u JOIN matriculas mat ON u.id=mat.estudiante_id
         WHERE mat.curso_id=? AND mat.estado='activo'
         ORDER BY u.apellido, u.nombre", [$cursoId]
    );
} else {
    $estudiantes = DB::rows(
        "SELECT u.id, u.apellido, u.nombre, u.dni, mat.tipo_matricula
         FROM usuarios u JOIN matriculas mat ON u.id=mat.estudiante_id
         WHERE u.id=? AND mat.curso_id=? AND mat.estado='activo'", [$estudianteId, $cursoId]
    );
}

if (empty($estudiantes)) { die('No se encontraron estudiantes.'); }

// Obtener grupos y materias individuales
$grupos = obtenerGrupos($cursoInfo['anio'], $ciclo);
$idsEnGrupo = materiasEnGrupos($cursoInfo['anio'], $ciclo);

// Materias individuales (no agrupadas)
$materiasIndiv = DB::rows(
    "SELECT mc.id as materia_curso_id, m.nombre as materia_nombre, m.codigo, m.tipo
     FROM materias_por_curso mc
     JOIN materias m ON mc.materia_id = m.id
     WHERE mc.curso_id = ?
     ORDER BY m.tipo, m.nombre", [$cursoId]
);
$materiasIndiv = array_filter($materiasIndiv, fn($m) => !in_array($m['materia_curso_id'], $idsEnGrupo));
$materiasIndiv = array_values($materiasIndiv);

// =============================================
// CLASE PDF
// =============================================
class BoletinPDF extends FPDF {
    public $schoolName = '';
    public $cursoNombre = '';
    public $anio = '';
    public $tipoBoletin = '';
    public $cuatrimestre = 1;
    
    function Header() {
        $this->SetFont('Helvetica', 'B', 11);
        $this->Cell(0, 6, utf8_decode($this->schoolName), 0, 1, 'C');
        
        $this->SetFont('Helvetica', '', 8);
        $titulo = 'REGISTRO INSTITUCIONAL DE TRAYECTORIAS EDUCATIVAS (RITE)';
        if ($this->tipoBoletin === 'cuatrimestral') {
            $titulo .= ' — ' . $this->cuatrimestre . '° CUATRIMESTRE';
        } else {
            $titulo .= ' — CALIFICACIÓN FINAL';
        }
        $this->Cell(0, 4, utf8_decode($titulo), 0, 1, 'C');
        
        $this->SetFont('Helvetica', '', 7);
        $this->Cell(0, 4, utf8_decode($this->cursoNombre . ' — Ciclo Lectivo ' . $this->anio . ' — Resolución Nº 1650/24'), 0, 1, 'C');
        $this->Ln(2);
    }
    
    function Footer() {
        $this->SetY(-10);
        $this->SetFont('Helvetica', 'I', 6);
        $this->Cell(0, 5, utf8_decode('RITE v2.0 — Escuela Técnica Henry Ford — Generado: ' . date('d/m/Y H:i')), 0, 0, 'L');
        $this->Cell(0, 5, utf8_decode('Página ' . $this->PageNo()), 0, 0, 'R');
    }
}

// =============================================
// GENERAR PDF
// =============================================
$pdf = new BoletinPDF('L', 'mm', 'A3'); // Landscape A3
$pdf->schoolName = SCHOOL_NAME;
$pdf->cursoNombre = $cursoInfo['nombre'];
$pdf->anio = $cicloData['anio'];
$pdf->tipoBoletin = $tipo;
$pdf->cuatrimestre = $cuatrimestre;
$pdf->SetAutoPageBreak(true, 15);

foreach ($estudiantes as $est) {
    $estId = $est['id'];
    $esLiberado = $est['tipo_matricula'] === 'liberado';
    
    $pdf->AddPage();
    
    // Datos del alumno
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(20, 5, utf8_decode('Alumno:'), 0);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(100, 5, utf8_decode($est['apellido'] . ', ' . $est['nombre']), 0);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(20, 5, utf8_decode('Matr.:'), 0);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(30, 5, utf8_decode($est['dni']), 0);
    if ($esLiberado) {
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(0, 128, 0);
        $pdf->Cell(0, 5, utf8_decode('[LIBERADO]'), 0);
        $pdf->SetTextColor(0, 0, 0);
    }
    $pdf->Ln(8);
    
    // =============================================
    // TABLA DE CALIFICACIONES
    // =============================================
    
    // Anchos de columnas
    $wMateria = 90;
    $wVal = 18;
    $wCal = 16;
    $wObs = 25;
    $wInt = 16;
    $wFinal = 18;
    
    // Encabezados
    $pdf->SetFont('Helvetica', 'B', 6);
    $pdf->SetFillColor(30, 58, 95); // ethf-600
    $pdf->SetTextColor(255, 255, 255);
    
    $y = $pdf->GetY();
    $x = $pdf->GetX();
    
    // Fila 1: secciones
    $pdf->Cell($wMateria, 5, '', 1, 0, 'C', true);
    
    if ($tipo === 'cuatrimestral' && $cuatrimestre === 1) {
        $pdf->SetFillColor(5, 95, 70); // emerald
        $pdf->Cell($wVal + $wVal + $wObs, 5, utf8_decode('1° BIMESTRE'), 1, 0, 'C', true);
        $pdf->SetFillColor(16, 120, 60);
        $pdf->Cell($wCal, 5, utf8_decode('1°C'), 1, 0, 'C', true);
    } elseif ($tipo === 'cuatrimestral' && $cuatrimestre === 2) {
        $pdf->SetFillColor(146, 64, 14); // amber
        $pdf->Cell($wVal + $wVal + $wObs, 5, utf8_decode('3° BIMESTRE'), 1, 0, 'C', true);
        $pdf->SetFillColor(180, 80, 20);
        $pdf->Cell($wCal, 5, utf8_decode('2°C'), 1, 0, 'C', true);
    } else { // final
        $pdf->SetFillColor(5, 95, 70);
        $pdf->Cell($wCal, 5, utf8_decode('1°C'), 1, 0, 'C', true);
        $pdf->SetFillColor(146, 64, 14);
        $pdf->Cell($wCal, 5, utf8_decode('2°C'), 1, 0, 'C', true);
        $pdf->SetFillColor(157, 23, 77); // pink
        $pdf->Cell($wInt, 5, utf8_decode('INT 1C'), 1, 0, 'C', true);
        $pdf->Cell($wInt, 5, utf8_decode('INT DIC'), 1, 0, 'C', true);
        $pdf->Cell($wInt, 5, utf8_decode('INT FEB'), 1, 0, 'C', true);
        $pdf->SetFillColor(91, 33, 182); // violet
        $pdf->Cell($wFinal, 5, utf8_decode('FINAL'), 1, 0, 'C', true);
    }
    
    $pdf->Ln();
    
    // Fila 2: sub-encabezados
    $pdf->SetFillColor(243, 244, 246);
    $pdf->SetTextColor(75, 85, 99);
    $pdf->SetFont('Helvetica', 'B', 5);
    $pdf->Cell($wMateria, 4, utf8_decode('MATERIA / ESPACIO CURRICULAR'), 1, 0, 'L', true);
    
    if ($tipo !== 'final') {
        $pdf->Cell($wVal, 4, utf8_decode('VALORACIÓN'), 1, 0, 'C', true);
        $pdf->Cell($wVal, 4, utf8_decode('DESEMPEÑO'), 1, 0, 'C', true);
        $pdf->Cell($wObs, 4, utf8_decode('OBSERVACIONES'), 1, 0, 'C', true);
        $pdf->Cell($wCal, 4, utf8_decode('CALIF.'), 1, 0, 'C', true);
    } else {
        $pdf->Cell($wCal, 4, utf8_decode('CALIF.'), 1, 0, 'C', true);
        $pdf->Cell($wCal, 4, utf8_decode('CALIF.'), 1, 0, 'C', true);
        $pdf->Cell($wInt, 4, utf8_decode('CALIF.'), 1, 0, 'C', true);
        $pdf->Cell($wInt, 4, utf8_decode('CALIF.'), 1, 0, 'C', true);
        $pdf->Cell($wInt, 4, utf8_decode('CALIF.'), 1, 0, 'C', true);
        $pdf->Cell($wFinal, 4, utf8_decode('CALIF.'), 1, 0, 'C', true);
    }
    $pdf->Ln();
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Helvetica', '', 6);
    $row = 0;
    
    // --- MATERIAS INDIVIDUALES ---
    foreach ($materiasIndiv as $mat) {
        $cal = DB::row(
            "SELECT * FROM calificaciones WHERE estudiante_id=? AND materia_curso_id=? AND ciclo_lectivo_id=?",
            [$estId, $mat['materia_curso_id'], $ciclo]
        );
        
        $fill = ($row % 2 === 0);
        $pdf->SetFillColor(249, 250, 251);
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Cell($wMateria, 5, utf8_decode($mat['materia_nombre']), 1, 0, 'L', $fill);
        
        if ($esLiberado) {
            $cols = ($tipo !== 'final') ? 4 : 6;
            $w = ($tipo !== 'final') ? ($wVal+$wVal+$wObs+$wCal) : ($wCal*2+$wInt*3+$wFinal);
            $pdf->SetFont('Helvetica', 'I', 6);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell($w, 5, utf8_decode('LIBERADO'), 1, 0, 'C', $fill);
            $pdf->SetTextColor(0, 0, 0);
        } else {
            printCalificacion($pdf, $cal, $tipo, $cuatrimestre, $wVal, $wCal, $wObs, $wInt, $wFinal, $fill);
        }
        $pdf->Ln();
        $row++;
    }
    
    // --- GRUPOS DE MATERIAS ---
    foreach ($grupos as $grupo) {
        $datos = datosGrupoEstudiante($estId, $grupo, $ciclo);
        
        // Fila del grupo (fondo coloreado)
        $pdf->SetFont('Helvetica', 'B', 6);
        $pdf->SetFillColor(219, 234, 254); // blue-100
        
        $nombreGrupo = $datos['nombre'];
        $subMats = implode(', ', $datos['submaterias']);
        $label = $nombreGrupo . ' (' . $subMats . ')';
        
        $pdf->Cell($wMateria, 5, utf8_decode($label), 1, 0, 'L', true);
        
        if ($esLiberado) {
            $w = ($tipo !== 'final') ? ($wVal+$wVal+$wObs+$wCal) : ($wCal*2+$wInt*3+$wFinal);
            $pdf->SetFont('Helvetica', 'I', 6);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell($w, 5, utf8_decode('LIBERADO'), 1, 0, 'C', true);
            $pdf->SetTextColor(0, 0, 0);
        } else {
            // Construir array tipo calificacion para reusar función
            $calGrupo = [
                'valoracion_1bim' => $datos['valoracion_1bim'],
                'desempeno_1bim' => null,
                'observaciones_1bim' => null,
                'calificacion_1c' => $datos['calificacion_1c'] !== null ? round($datos['calificacion_1c']) : null,
                'valoracion_3bim' => $datos['valoracion_3bim'],
                'desempeno_3bim' => null,
                'observaciones_3bim' => null,
                'calificacion_2c' => $datos['calificacion_2c'] !== null ? round($datos['calificacion_2c']) : null,
                'intensificacion_1c' => $datos['intensificacion_1c'] !== null ? round($datos['intensificacion_1c']) : null,
                'intensificacion_diciembre' => $datos['intensificacion_diciembre'] !== null ? round($datos['intensificacion_diciembre']) : null,
                'intensificacion_febrero' => $datos['intensificacion_febrero'] !== null ? round($datos['intensificacion_febrero']) : null,
                'calificacion_final' => $datos['calificacion_final'],
            ];
            printCalificacion($pdf, $calGrupo, $tipo, $cuatrimestre, $wVal, $wCal, $wObs, $wInt, $wFinal, true);
        }
        $pdf->Ln();
        $row++;
    }
    
    // Espacio para observaciones y firmas
    $pdf->Ln(6);
    $pdf->SetFont('Helvetica', 'B', 7);
    $pdf->Cell(0, 5, utf8_decode('OBSERVACIONES GENERALES:'), 0, 1);
    $pdf->SetFont('Helvetica', '', 6);
    $pdf->MultiCell(0, 4, utf8_decode('TEA: Trayectoria Educativa Avanzada | TEP: Trayectoria Educativa en Proceso | TED: Trayectoria Educativa Discontinua'), 0, 'L');
    $pdf->Ln(6);
    
    // Firmas
    $pdf->SetFont('Helvetica', '', 7);
    $wFirma = 80;
    $pdf->Cell($wFirma, 10, '____________________________', 0, 0, 'C');
    $pdf->Cell($wFirma, 10, '____________________________', 0, 0, 'C');
    $pdf->Cell($wFirma, 10, '____________________________', 0, 1, 'C');
    $pdf->Cell($wFirma, 4, utf8_decode('Preceptor/a'), 0, 0, 'C');
    $pdf->Cell($wFirma, 4, utf8_decode('Director/a'), 0, 0, 'C');
    $pdf->Cell($wFirma, 4, utf8_decode('Responsable'), 0, 1, 'C');
}

ob_end_clean();

$nombreArchivo = ($todos ? $cursoInfo['nombre'] : $est['apellido'] . '_' . $est['nombre']) . '_' . $tipo;
$nombreArchivo = str_replace([' ', '°'], ['_', ''], $nombreArchivo);

$pdf->Output('I', $nombreArchivo . '.pdf');

// =============================================
// FUNCIÓN AUXILIAR: Imprimir celdas de calificación
// =============================================
function printCalificacion($pdf, $cal, $tipo, $cuatrimestre, $wVal, $wCal, $wObs, $wInt, $wFinal, $fill) {
    $pdf->SetFont('Helvetica', '', 6);
    
    if ($tipo !== 'final') {
        // Cuatrimestral
        $colVal = $cuatrimestre === 1 ? 'valoracion_1bim' : 'valoracion_3bim';
        $colDes = $cuatrimestre === 1 ? 'desempeno_1bim' : 'desempeno_3bim';
        $colObs = $cuatrimestre === 1 ? 'observaciones_1bim' : 'observaciones_3bim';
        $colCal = $cuatrimestre === 1 ? 'calificacion_1c' : 'calificacion_2c';
        
        $val = $cal[$colVal] ?? '';
        $des = $cal[$colDes] ?? '';
        $obs = $cal[$colObs] ?? '';
        $nota = $cal[$colCal] ?? '';
        
        // Colorear valoración
        if ($val === 'TEA') { $pdf->SetTextColor(22, 101, 52); $pdf->SetFont('Helvetica','B',6); }
        elseif ($val === 'TED') { $pdf->SetTextColor(153, 27, 27); $pdf->SetFont('Helvetica','B',6); }
        elseif ($val === 'TEP') { $pdf->SetTextColor(133, 77, 14); }
        $pdf->Cell($wVal, 5, utf8_decode($val), 1, 0, 'C', $fill);
        $pdf->SetTextColor(0,0,0); $pdf->SetFont('Helvetica','',6);
        
        $pdf->Cell($wVal, 5, utf8_decode($des), 1, 0, 'C', $fill);
        
        $pdf->SetFont('Helvetica', '', 5);
        $obsCorta = mb_substr($obs, 0, 30);
        $pdf->Cell($wObs, 5, utf8_decode($obsCorta), 1, 0, 'L', $fill);
        $pdf->SetFont('Helvetica', '', 6);
        
        // Nota cuatrimestral
        if ($nota !== '' && $nota !== null) {
            $n = (int)$nota;
            if ($n >= 7) $pdf->SetTextColor(22, 101, 52);
            elseif ($n >= 4) $pdf->SetTextColor(133, 77, 14);
            else $pdf->SetTextColor(153, 27, 27);
            $pdf->SetFont('Helvetica', 'B', 7);
        }
        $pdf->Cell($wCal, 5, $nota !== null && $nota !== '' ? (string)$nota : '-', 1, 0, 'C', $fill);
        $pdf->SetTextColor(0,0,0); $pdf->SetFont('Helvetica','',6);
        
    } else {
        // Final
        $c1 = $cal['calificacion_1c'] ?? '';
        $c2 = $cal['calificacion_2c'] ?? '';
        $i1 = $cal['intensificacion_1c'] ?? '';
        $iD = $cal['intensificacion_diciembre'] ?? '';
        $iF = $cal['intensificacion_febrero'] ?? '';
        $fin = $cal['calificacion_final'] ?? '';
        
        // Cal 1C
        printNotaCell($pdf, $c1, $wCal, $fill);
        // Cal 2C
        printNotaCell($pdf, $c2, $wCal, $fill);
        // Intensificaciones
        printNotaCell($pdf, $i1, $wInt, $fill);
        printNotaCell($pdf, $iD, $wInt, $fill);
        printNotaCell($pdf, $iF, $wInt, $fill);
        // Final
        if ($fin !== '' && $fin !== null) {
            $n = (int)$fin;
            if ($n >= 7) { $pdf->SetTextColor(22,101,52); $pdf->SetFillColor(220,252,231); }
            elseif ($n >= 4) { $pdf->SetTextColor(133,77,14); $pdf->SetFillColor(254,249,195); }
            else { $pdf->SetTextColor(153,27,27); $pdf->SetFillColor(254,226,226); }
            $pdf->SetFont('Helvetica', 'B', 8);
            $pdf->Cell($wFinal, 5, (string)$fin, 1, 0, 'C', true);
            $pdf->SetTextColor(0,0,0); $pdf->SetFont('Helvetica','',6);
            if ($fill) $pdf->SetFillColor(249,250,251); else $pdf->SetFillColor(255,255,255);
        } else {
            $pdf->Cell($wFinal, 5, '-', 1, 0, 'C', $fill);
        }
    }
}

function printNotaCell($pdf, $nota, $w, $fill) {
    if ($nota !== '' && $nota !== null) {
        $n = (int)$nota;
        if ($n >= 7) $pdf->SetTextColor(22,101,52);
        elseif ($n >= 4) $pdf->SetTextColor(133,77,14);
        else $pdf->SetTextColor(153,27,27);
        $pdf->SetFont('Helvetica','B',6);
        $pdf->Cell($w, 5, (string)$nota, 1, 0, 'C', $fill);
        $pdf->SetTextColor(0,0,0); $pdf->SetFont('Helvetica','',6);
    } else {
        $pdf->Cell($w, 5, '-', 1, 0, 'C', $fill);
    }
}
