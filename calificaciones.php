<?php
/**
 * calificaciones.php — Grilla de Calificaciones (port completo)
 * RITE v2.0 — Escuela Técnica Henry Ford
 */
require_once 'config.php';
requireRole('admin', 'directivo', 'profesor');

$ciclo = cicloId();
$uid = userId();
$role = activeRole();
$esAdmin = in_array($role, ['admin', 'directivo']);

// ==================== GUARDAR CALIFICACIONES (POST) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_calificaciones'])) {
    $mcId = (int)$_POST['materia_curso_id'];
    $saved = 0;
    foreach ($_POST['est'] ?? [] as $estId => $datos) {
        $estId = (int)$estId;
        $exists = DB::row("SELECT id FROM calificaciones WHERE estudiante_id=? AND materia_curso_id=? AND ciclo_lectivo_id=?", [$estId, $mcId, $ciclo]);
        if (!$exists) DB::insert("INSERT INTO calificaciones (estudiante_id, materia_curso_id, ciclo_lectivo_id) VALUES (?,?,?)", [$estId, $mcId, $ciclo]);
        
        $campos = ['valoracion_1bim','desempeno_1bim','observaciones_1bim','calificacion_1c',
                    'valoracion_3bim','desempeno_3bim','observaciones_3bim','calificacion_2c',
                    'intensificacion_1c','intensificacion_diciembre','intensificacion_febrero','calificacion_final'];
        foreach ($campos as $c) {
            if (!array_key_exists($c, $datos)) continue;
            $val = trim($datos[$c]); $val = $val === '' ? null : $val;
            DB::query("UPDATE calificaciones SET $c=? WHERE estudiante_id=? AND materia_curso_id=? AND ciclo_lectivo_id=?", [$val, $estId, $mcId, $ciclo]);
        }
        // Save contenidos
        foreach ($_POST['cont'][$estId] ?? [] as $contId => $cd) {
            $contId = (int)$contId;
            $estado = !empty($cd['estado']) ? $cd['estado'] : null;
            $nota = !empty($cd['nota']) ? $cd['nota'] : null;
            $ex = DB::row("SELECT id FROM contenidos_calificaciones WHERE contenido_id=? AND estudiante_id=?", [$contId, $estId]);
            if ($ex) { DB::query("UPDATE contenidos_calificaciones SET estado=?, calificacion_numerica=?, updated_at=CURRENT_TIMESTAMP WHERE id=?", [$estado, $nota, $ex['id']]); }
            elseif ($estado || $nota) { DB::insert("INSERT INTO contenidos_calificaciones (contenido_id, estudiante_id, estado, calificacion_numerica) VALUES (?,?,?,?)", [$contId, $estId, $estado, $nota]); }
        }
        $saved++;
    }
    flash('success', "$saved calificaciones guardadas.");
    header('Location: ' . $_SERVER['REQUEST_URI']); exit;
}

// ==================== CREAR/ELIMINAR CONTENIDO ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $mcId = (int)$_POST['materia_curso_id'];
    if ($_POST['accion'] === 'crear_contenido') {
        $titulo = trim($_POST['titulo'] ?? '');
        if ($titulo) {
            $bim = (int)($_POST['bimestre'] ?? 1);
            $max = DB::row("SELECT MAX(orden) as m FROM contenidos WHERE materia_curso_id=? AND bimestre=?", [$mcId, $bim])['m'] ?? 0;
            DB::insert("INSERT INTO contenidos (materia_curso_id, profesor_id, titulo, fecha_clase, bimestre, tipo_evaluacion, orden, activo) VALUES (?,?,?,?,?,?,?,1)",
                [$mcId, $uid, $titulo, date('Y-m-d'), $bim, $_POST['tipo_evaluacion'] ?? 'cualitativa', $max+1]);
            flash('success', "Contenido '$titulo' creado.");
        }
    } elseif ($_POST['accion'] === 'eliminar_contenido') {
        $cid = (int)$_POST['contenido_id'];
        DB::query("DELETE FROM contenidos_calificaciones WHERE contenido_id=?", [$cid]);
        DB::query("DELETE FROM contenidos WHERE id=?", [$cid]);
        flash('success', 'Contenido eliminado.');
    }
    header('Location: ' . $_SERVER['REQUEST_URI']); exit;
}

// ==================== DATOS ====================
$cursoSel = $_GET['curso'] ?? ''; $materiaSel = $_GET['materia'] ?? ''; $sgFiltro = $_GET['subgrupo'] ?? 'todos';
if ($esAdmin) { $cursos = DB::rows("SELECT * FROM cursos WHERE ciclo_lectivo_id=? ORDER BY anio", [$ciclo]); }
else { $cursos = DB::rows("SELECT DISTINCT c.* FROM cursos c JOIN materias_por_curso mc ON c.id=mc.curso_id WHERE c.ciclo_lectivo_id=? AND (mc.profesor_id=? OR mc.profesor_id_2=? OR mc.profesor_id_3=?) ORDER BY c.anio", [$ciclo,$uid,$uid,$uid]); }

$materias = [];
if ($cursoSel) {
    $sql = "SELECT mc.id, m.nombre, mc.requiere_subgrupos, COALESCE(p.apellido,'') as prof FROM materias_por_curso mc JOIN materias m ON mc.materia_id=m.id LEFT JOIN usuarios p ON mc.profesor_id=p.id WHERE mc.curso_id=?";
    $p = [$cursoSel];
    if (!$esAdmin) { $sql .= " AND (mc.profesor_id=? OR mc.profesor_id_2=? OR mc.profesor_id_3=?)"; array_push($p,$uid,$uid,$uid); }
    $materias = DB::rows("$sql ORDER BY m.nombre", $p);
}

$bloqueos = DB::row("SELECT * FROM bloqueos WHERE ciclo_lectivo_id=?", [$ciclo]) ?? [];
function bloqueado($c) { global $bloqueos,$esAdmin; if ($esAdmin) return false; return !empty($bloqueos['bloqueo_general']) || !empty($bloqueos[$c]); }
function dis($c, $lib=false) { return ($lib || bloqueado($c)) ? 'disabled' : ''; }

$estudiantes=[]; $calificaciones=[]; $contenidos=[]; $subgrupos=[]; $materiaInfo=null; $contCals=[]; $obsPredefinidas=[];
if ($cursoSel && $materiaSel) {
    $materiaInfo = DB::row("SELECT mc.*, m.nombre as materia_nombre, c.nombre as curso_nombre, COALESCE(p.apellido,'') as prof1 FROM materias_por_curso mc JOIN materias m ON mc.materia_id=m.id JOIN cursos c ON mc.curso_id=c.id LEFT JOIN usuarios p ON mc.profesor_id=p.id WHERE mc.id=?", [$materiaSel]);
    if ($materiaInfo && $materiaInfo['requiere_subgrupos']) $subgrupos = DB::rows("SELECT * FROM subgrupos WHERE materia_curso_id=? ORDER BY nombre", [$materiaSel]);
    
    $sql = "SELECT u.id,u.apellido,u.nombre,u.dni,mat.tipo_matricula,COALESCE(es.sg,'') as subgrupo FROM usuarios u JOIN matriculas mat ON u.id=mat.estudiante_id LEFT JOIN (SELECT es.estudiante_id,s.nombre as sg FROM estudiantes_subgrupo es JOIN subgrupos s ON es.subgrupo_id=s.id WHERE s.materia_curso_id=?) es ON u.id=es.estudiante_id WHERE mat.curso_id=? AND mat.estado='activo'";
    $p = [$materiaSel,$cursoSel];
    if ($sgFiltro!=='todos' && $materiaInfo['requiere_subgrupos']) { if ($sgFiltro==='sin') $sql.=" AND COALESCE(es.sg,'')=''"; else { $sql.=" AND es.sg=?"; $p[]=$sgFiltro; } }
    $estudiantes = DB::rows("$sql ORDER BY mat.tipo_matricula,u.apellido,u.nombre", $p);
    
    foreach ($estudiantes as $e) { $c=DB::row("SELECT * FROM calificaciones WHERE estudiante_id=? AND materia_curso_id=? AND ciclo_lectivo_id=?",[$e['id'],$materiaSel,$ciclo]); if($c) $calificaciones[$e['id']]=$c; }
    $contenidos = DB::rows("SELECT * FROM contenidos WHERE materia_curso_id=? AND activo=1 ORDER BY bimestre,orden", [$materiaSel]);
    if ($contenidos) { $cids=array_column($contenidos,'id'); $ph=implode(',',array_fill(0,count($cids),'?')); foreach(DB::rows("SELECT * FROM contenidos_calificaciones WHERE contenido_id IN ($ph)",$cids) as $r) $contCals[$r['estudiante_id']][$r['contenido_id']]=$r; }
    $obsPredefinidas = DB::rows("SELECT * FROM observaciones_predefinidas WHERE activo=1 ORDER BY categoria,texto");
}
$nLib=count(array_filter($estudiantes,fn($e)=>$e['tipo_matricula']==='liberado'));
$nRec=count(array_filter($estudiantes,fn($e)=>$e['tipo_matricula']==='recursando'));

require_once 'header.php';
?>
<link rel="stylesheet" href="calificaciones.css">
<style>
.ss-hidden{display:none}
.ss-badge{display:inline-block;padding:1px 5px;border-radius:3px;font-size:9px;font-weight:600}
.ss-badge-lib{background:#dcfce7;color:#166534}.ss-badge-rec{background:#fef9c3;color:#854d0e}.ss-badge-sg{background:#dbeafe;color:#1e40af}
.ss-btn-obs{font-family:'DM Sans',sans-serif;padding:3px 8px;border:1px solid #e5e7eb;border-radius:6px;background:#fff;cursor:pointer;font-size:11px;color:#6b7280;transition:all .15s;white-space:nowrap}
.ss-btn-obs:hover{border-color:#93c5fd;background:#eff6ff}
.ss-btn-obs-on{background:#dcfce7;border-color:#86efac;color:#166534}
.ss-btn-calc{background:none;border:1px solid #e5e7eb;border-radius:4px;cursor:pointer;color:#6b7280;padding:2px 4px;font-size:10px;flex-shrink:0}
.ss-btn-calc:hover{background:#eff6ff;border-color:#93c5fd;color:#3b82f6}
.ss-cell-status{font-size:9px;text-align:center;padding:4px 2px;line-height:1.2}
.ss-cell-ok{color:#16a34a}
.ss-cont-a{background:#dcfce7!important;color:#166534!important;font-weight:600}
.ss-cont-ep{background:#fef9c3!important;color:#854d0e!important;font-weight:600}
.ss-cont-na{background:#fee2e2!important;color:#991b1b!important;font-weight:600}
.ss-modal-bg{position:fixed;inset:0;z-index:50;display:flex;align-items:flex-start;justify-content:center;padding-top:60px;background:rgba(0,0,0,.35)}
.ss-modal{background:#fff;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.2);width:100%;max-width:550px;margin:0 16px;display:flex;flex-direction:column;max-height:80vh}
.ss-modal-head{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #e5e7eb}
.ss-modal-head h3{font-size:14px;font-weight:600;color:#1f2937;margin:0}
.ss-modal-close{background:none;border:none;color:#9ca3af;cursor:pointer;font-size:16px}.ss-modal-close:hover{color:#374151}
.ss-modal-body{padding:16px;overflow-y:auto;flex:1}
.ss-modal-foot{padding:12px 16px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:8px}
.ss-obs-pred{padding:4px 10px;border:1px solid #e5e7eb;border-radius:6px;background:#f9fafb;cursor:pointer;font-size:11px;color:#4b5563;transition:all .15s;font-family:'DM Sans',sans-serif}
.ss-obs-pred:hover{background:#dbeafe;border-color:#93c5fd;color:#1e40af}
.hidden{display:none!important}
</style>

<div class="cal-page">
<form method="POST" id="formCal">
<input type="hidden" name="guardar_calificaciones" value="1">
<input type="hidden" name="materia_curso_id" value="<?= (int)$materiaSel ?>">

    <div class="cal-bar">
        <div class="cal-bar-form">
            <div class="cal-sel"><label>Curso</label>
                <select onchange="location='calificaciones.php?curso='+this.value">
                    <option value="">Seleccionar curso...</option>
                    <?php foreach ($cursos as $c): ?><option value="<?= $c['id'] ?>" <?= $c['id']==$cursoSel?'selected':'' ?>><?= $c['nombre'] ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="cal-sel" style="min-width:260px"><label>Materia</label>
                <select onchange="location='calificaciones.php?curso=<?= $cursoSel ?>&materia='+this.value" <?= !$cursoSel?'disabled':'' ?>>
                    <option value="">Seleccionar materia...</option>
                    <?php foreach ($materias as $m): ?><option value="<?= $m['id'] ?>" <?= $m['id']==$materiaSel?'selected':'' ?>><?= $m['requiere_subgrupos']?'🔄 ':'' ?><?= htmlspecialchars($m['nombre']) ?><?= $m['prof']?" — {$m['prof']}":'' ?></option><?php endforeach; ?>
                </select>
            </div>
            <?php if ($materiaInfo && $materiaInfo['requiere_subgrupos'] && $subgrupos): ?>
            <div class="cal-sel"><label>Subgrupo</label>
                <select onchange="location='calificaciones.php?curso=<?= $cursoSel ?>&materia=<?= $materiaSel ?>&subgrupo='+this.value">
                    <option value="todos" <?= $sgFiltro==='todos'?'selected':'' ?>>Todos</option>
                    <?php foreach ($subgrupos as $sg): ?><option value="<?= htmlspecialchars($sg['nombre']) ?>" <?= $sgFiltro===$sg['nombre']?'selected':'' ?>><?= htmlspecialchars($sg['nombre']) ?></option><?php endforeach; ?>
                    <option value="sin" <?= $sgFiltro==='sin'?'selected':'' ?>>Sin subgrupo</option>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <div class="cal-bar-spacer"></div>
        <?php if ($estudiantes): ?>
        <div class="cal-bar-actions no-print">
            <button type="button" class="cal-btn-sec" onclick="toggleContenidos()"><i class="bi bi-list-check"></i> Contenidos (<?= count($contenidos) ?>)</button>
            <button type="submit" class="cal-btn" id="btnGuardar"><i class="bi bi-check2-all"></i> Guardar</button>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($materiaInfo && $estudiantes): ?>
    <div class="cal-info">
        <strong><?= htmlspecialchars($materiaInfo['curso_nombre']) ?></strong>
        <span class="cal-sep">·</span> <?= htmlspecialchars($materiaInfo['materia_nombre']) ?>
        <span class="cal-sep">·</span> <?= count($estudiantes) ?> alumnos
        <?php if ($materiaInfo['prof1']): ?><span class="cal-sep">·</span> Prof. <?= $materiaInfo['prof1'] ?><?php endif; ?>
        <?php if ($nLib): ?><span class="cal-sep">|</span> <span class="text-muted"><?= $nLib ?> lib.</span><?php endif; ?>
        <?php if ($nRec): ?> <span class="text-amber"><?= $nRec ?> rec.</span><?php endif; ?>
        <?php if (!empty($bloqueos['bloqueo_general']) && !$esAdmin): ?><span class="cal-sep">|</span> <span style="color:#dc2626"><i class="bi bi-lock-fill"></i> Bloqueo general</span><?php endif; ?>
    </div>

    <div class="ss-wrap"><div class="ss-scroll">
    <table class="ss" id="grilla">
        <thead>
            <tr class="ss-sections">
                <th rowspan="2" class="ss-col-est">Estudiante</th>
                <?php if ($contenidos): ?><th colspan="<?= count($contenidos) ?>" class="ss-sec ss-sec-cont">Contenidos</th><?php endif; ?>
                <th colspan="3" class="ss-sec ss-sec-1b">1° Bimestre</th>
                <th rowspan="2" class="ss-sec ss-sec-1c">Cal 1°C</th>
                <th colspan="3" class="ss-sec ss-sec-3b">3° Bimestre</th>
                <th rowspan="2" class="ss-sec ss-sec-2c">Cal 2°C</th>
                <th rowspan="2" class="ss-sec ss-sec-int">Int 1°C</th>
                <th rowspan="2" class="ss-sec ss-sec-int">Int Dic</th>
                <th rowspan="2" class="ss-sec ss-sec-int">Int Feb</th>
                <th rowspan="2" class="ss-sec ss-sec-fin">Final</th>
            </tr>
            <tr class="ss-sub">
                <?php foreach ($contenidos as $i => $ct): ?><th class="ss-sec-cont ss-col-cont" title="<?= htmlspecialchars($ct['titulo']) ?>">C<?= $i+1 ?></th><?php endforeach; ?>
                <th class="ss-sec-1b">Val.</th><th class="ss-sec-1b">Des.</th><th class="ss-sec-1b">Obs.</th>
                <th class="ss-sec-3b">Val.</th><th class="ss-sec-3b">Des.</th><th class="ss-sec-3b">Obs.</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $tipoAct = '';
        foreach ($estudiantes as $est):
            $eid=$est['id']; $cal=$calificaciones[$eid]??[]; $esLib=$est['tipo_matricula']==='liberado'; $esRec=$est['tipo_matricula']==='recursando';
            $c1=$cal['calificacion_1c']??null; $c2=$cal['calificacion_2c']??null; $int1c=$cal['intensificacion_1c']??null; $intDic=$cal['intensificacion_diciembre']??null;
            $needsI1=(!$esLib && $c1!==null && (int)$c1<=6);
            $needsIDic=(!$esLib && (($c2!==null && (int)$c2<=6) || ($c1!==null && (int)$c1<=6 && empty($int1c))));
            $aproboDic=($intDic!==null && (float)$intDic>=4);
            if ($est['tipo_matricula']!==$tipoAct): $tipoAct=$est['tipo_matricula']; $lbl=['regular'=>'REGULARES','recursando'=>'RECURSANDO','liberado'=>'LIBERADOS']; $cn=count(array_filter($estudiantes,fn($e)=>$e['tipo_matricula']===$tipoAct)); ?>
            <tr class="ss-sep"><td colspan="100"><?= $lbl[$tipoAct]??'' ?> — <?= $cn ?></td></tr>
            <?php endif; ?>
        <tr class="ss-row <?= $esLib?'ss-lib':'' ?> <?= $esRec?'ss-rec':'' ?>" data-eid="<?= $eid ?>">
            <td class="ss-col-est">
                <div class="ss-est-name"><?= htmlspecialchars($est['apellido'].', '.$est['nombre']) ?></div>
                <div style="display:flex;gap:3px;align-items:center;margin-top:1px;flex-wrap:wrap">
                    <span style="font-size:10px;color:#9ca3af">Mat. <?= htmlspecialchars($est['dni']) ?></span>
                    <?php if($esLib):?><span class="ss-badge ss-badge-lib">LIBERADO</span><?php endif;?>
                    <?php if($esRec):?><span class="ss-badge ss-badge-rec">RECURSANDO</span><?php endif;?>
                    <?php if($est['subgrupo']):?><span class="ss-badge ss-badge-sg"><?= htmlspecialchars($est['subgrupo']) ?></span><?php endif;?>
                </div>
            </td>
            <?php foreach ($contenidos as $ct): $cc=$contCals[$eid][$ct['id']]??null; $cE=$cc['estado']??''; $cN=$cc['calificacion_numerica']??''; ?>
            <td class="ss-cell ss-sec-cont"><?php if($esLib):?><span class="ss-lib-mark">L</span><?php elseif($ct['tipo_evaluacion']==='numerica'):?><input type="text" class="ss-input ss-input-cont" maxlength="4" name="cont[<?=$eid?>][<?=$ct['id']?>][nota]" value="<?=htmlspecialchars($cN)?>">
            <?php else:?><select class="ss-input ss-sel-cont <?=$cE?'ss-cont-'.strtolower($cE):''?>" name="cont[<?=$eid?>][<?=$ct['id']?>][estado]" onchange="this.className='ss-input ss-sel-cont '+(this.value?'ss-cont-'+this.value.toLowerCase():'')"><option value="">-</option><option value="A" <?=$cE==='A'?'selected':''?>>A</option><option value="EP" <?=$cE==='EP'?'selected':''?>>EP</option><option value="NA" <?=$cE==='NA'?'selected':''?>>NA</option></select><?php endif;?></td>
            <?php endforeach; ?>
            
            <td class="ss-cell ss-sec-1b"><select name="est[<?=$eid?>][valoracion_1bim]" class="ss-input ss-sel-val" <?=dis('valoracion_1bim',$esLib)?>><option value="">-</option><?php if(!$esLib):?><option value="TEA" <?=($cal['valoracion_1bim']??'')==='TEA'?'selected':''?>>TEA</option><option value="TEP" <?=($cal['valoracion_1bim']??'')==='TEP'?'selected':''?>>TEP</option><option value="TED" <?=($cal['valoracion_1bim']??'')==='TED'?'selected':''?>>TED</option><?php endif;?></select></td>
            <td class="ss-cell ss-sec-1b"><select name="est[<?=$eid?>][desempeno_1bim]" class="ss-input" <?=dis('valoracion_1bim',$esLib)?>><option value="">-</option><?php if(!$esLib): foreach(['Excelente','Muy Bueno','Bueno','Regular','Malo'] as $d):?><option value="<?=$d?>" <?=($cal['desempeno_1bim']??'')===$d?'selected':''?>><?=$d?></option><?php endforeach;endif;?></select></td>
            <td class="ss-cell ss-sec-1b"><?php if(!$esLib): $ov=$cal['observaciones_1bim']??''; $oc=$ov?count(array_filter(explode(';',$ov))):0; ?>
                <textarea name="est[<?=$eid?>][observaciones_1bim]" id="obs_<?=$eid?>_1" class="ss-hidden"><?=htmlspecialchars($ov)?></textarea>
                <button type="button" class="ss-btn-obs <?=$oc?'ss-btn-obs-on':''?>" <?=dis('observaciones_1bim')?> onclick="abrirObs(<?=$eid?>,1,'<?=addslashes($est['apellido'].', '.$est['nombre'])?>')"><i class="bi bi-chat-square-text<?=$oc?'-fill':''?>"></i> <?=$oc?:' Sin obs.'?></button>
            <?php endif;?></td>
            
            <td class="ss-cell ss-sec-1c"><select name="est[<?=$eid?>][calificacion_1c]" class="ss-input ss-sel-nota" <?=dis('calificacion_1c',$esLib)?> data-est="<?=$eid?>"><option value="">-</option><?php if(!$esLib):for($i=1;$i<=10;$i++):?><option value="<?=$i?>" <?=($c1??'')==$i?'selected':''?>><?=$i?></option><?php endfor;endif;?></select></td>
            
            <td class="ss-cell ss-sec-3b"><select name="est[<?=$eid?>][valoracion_3bim]" class="ss-input ss-sel-val" <?=dis('valoracion_3bim',$esLib)?>><option value="">-</option><?php if(!$esLib):?><option value="TEA" <?=($cal['valoracion_3bim']??'')==='TEA'?'selected':''?>>TEA</option><option value="TEP" <?=($cal['valoracion_3bim']??'')==='TEP'?'selected':''?>>TEP</option><option value="TED" <?=($cal['valoracion_3bim']??'')==='TED'?'selected':''?>>TED</option><?php endif;?></select></td>
            <td class="ss-cell ss-sec-3b"><select name="est[<?=$eid?>][desempeno_3bim]" class="ss-input" <?=dis('valoracion_3bim',$esLib)?>><option value="">-</option><?php if(!$esLib): foreach(['Excelente','Muy Bueno','Bueno','Regular','Malo'] as $d):?><option value="<?=$d?>" <?=($cal['desempeno_3bim']??'')===$d?'selected':''?>><?=$d?></option><?php endforeach;endif;?></select></td>
            <td class="ss-cell ss-sec-3b"><?php if(!$esLib): $ov3=$cal['observaciones_3bim']??''; $oc3=$ov3?count(array_filter(explode(';',$ov3))):0; ?>
                <textarea name="est[<?=$eid?>][observaciones_3bim]" id="obs_<?=$eid?>_3" class="ss-hidden"><?=htmlspecialchars($ov3)?></textarea>
                <button type="button" class="ss-btn-obs <?=$oc3?'ss-btn-obs-on':''?>" <?=dis('observaciones_3bim')?> onclick="abrirObs(<?=$eid?>,3,'<?=addslashes($est['apellido'].', '.$est['nombre'])?>')"><i class="bi bi-chat-square-text<?=$oc3?'-fill':''?>"></i> <?=$oc3?:' Sin obs.'?></button>
            <?php endif;?></td>
            
            <td class="ss-cell ss-sec-2c"><select name="est[<?=$eid?>][calificacion_2c]" class="ss-input ss-sel-nota" <?=dis('calificacion_2c',$esLib)?> data-est="<?=$eid?>"><option value="">-</option><?php if(!$esLib):for($i=1;$i<=10;$i++):?><option value="<?=$i?>" <?=($c2??'')==$i?'selected':''?>><?=$i?></option><?php endfor;endif;?></select></td>
            
            <td class="ss-cell ss-sec-int"><?php if($esLib):?><span class="ss-lib-mark">L</span><?php elseif($needsI1):?><select name="est[<?=$eid?>][intensificacion_1c]" class="ss-input ss-sel-nota" <?=dis('intensificacion_1c')?>><option value="">-</option><?php for($i=7;$i<=10;$i++):?><option value="<?=$i?>" <?=($int1c??'')==$i?'selected':''?>><?=$i?></option><?php endfor;?></select><div style="font-size:9px;color:#6b7280;text-align:center">Orig: <?=$c1?></div><?php else:?><div class="ss-cell-status ss-cell-ok"><i class="bi bi-check-circle-fill"></i></div><input type="hidden" name="est[<?=$eid?>][intensificacion_1c]" value=""><?php endif;?></td>
            
            <td class="ss-cell ss-sec-int"><?php if($esLib):?><span class="ss-lib-mark">L</span><?php elseif($needsIDic):?><select name="est[<?=$eid?>][intensificacion_diciembre]" class="ss-input ss-sel-nota" <?=dis('intensificacion_diciembre')?>><option value="">-</option><?php for($i=4;$i<=10;$i++):?><option value="<?=$i?>" <?=($intDic??'')==$i?'selected':''?>><?=$i?></option><?php endfor;?></select><?php else:?><div class="ss-cell-status ss-cell-ok"><i class="bi bi-check-circle-fill"></i></div><input type="hidden" name="est[<?=$eid?>][intensificacion_diciembre]" value=""><?php endif;?></td>
            
            <td class="ss-cell ss-sec-int"><?php if($esLib):?><span class="ss-lib-mark">L</span><?php elseif($needsIDic && !$aproboDic):?><select name="est[<?=$eid?>][intensificacion_febrero]" class="ss-input ss-sel-nota" <?=dis('intensificacion_febrero')?>><option value="">-</option><?php for($i=4;$i<=10;$i++):?><option value="<?=$i?>" <?=($cal['intensificacion_febrero']??'')==$i?'selected':''?>><?=$i?></option><?php endfor;?></select><?php elseif($needsIDic && $aproboDic):?><div class="ss-cell-status ss-cell-ok"><i class="bi bi-check-circle-fill"></i> Dic.</div><input type="hidden" name="est[<?=$eid?>][intensificacion_febrero]" value=""><?php else:?><div class="ss-cell-status ss-cell-ok"><i class="bi bi-check-circle-fill"></i></div><input type="hidden" name="est[<?=$eid?>][intensificacion_febrero]" value=""><?php endif;?></td>
            
            <td class="ss-cell ss-sec-fin"><?php if($esLib):?><span class="ss-lib-mark">LIB</span><?php else: $fn=$cal['calificacion_final']??''; $fc=''; if($fn!==''){$f=(int)$fn;$fc=$f>=7?'ss-g-high':($f>=4?'ss-g-mid':'ss-g-low');}?>
                <div style="display:flex;align-items:center;gap:2px;padding:0 2px"><input type="text" name="est[<?=$eid?>][calificacion_final]" class="ss-input ss-input-nota ss-nota-bold <?=$fc?>" value="<?=htmlspecialchars($fn)?>" style="flex:1" <?=dis('calificacion_final')?> data-est="<?=$eid?>"><button type="button" class="ss-btn-calc" onclick="calcFinal(<?=$eid?>)" title="Calcular"><i class="bi bi-calculator"></i></button></div>
            <?php endif;?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div></div>
    <div class="cal-footer no-print"><span>Tab/Enter navegar · Ctrl+S guardar</span><button type="submit" class="cal-btn" style="font-size:11px"><i class="bi bi-check2-all"></i> Guardar (<?= count($estudiantes) ?>)</button></div>
    <?php elseif (!$cursoSel): ?><div class="cal-empty"><i class="bi bi-hand-index" style="font-size:2em;color:#d1d5db"></i><p>Seleccioná un curso y materia.</p></div>
    <?php else: ?><div class="cal-empty"><i class="bi bi-inbox" style="font-size:2em;color:#d1d5db"></i><p>No hay alumnos.</p></div><?php endif; ?>
</form>
</div>

<!-- MODAL OBSERVACIONES -->
<div id="modalObs" class="ss-modal-bg hidden" onclick="if(event.target===this)cerrarObs()">
<div class="ss-modal">
    <div class="ss-modal-head"><h3 id="obsTitle">Observaciones</h3><button onclick="cerrarObs()" class="ss-modal-close"><i class="bi bi-x-lg"></i></button></div>
    <div class="ss-modal-body">
        <div id="obsInfo" style="font-size:12px;color:#6b7280;margin-bottom:10px"></div>
        <?php if (!empty($obsPredefinidas)): ?>
        <div style="margin-bottom:12px"><div style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;margin-bottom:6px">Predefinidas</div>
            <div style="display:flex;flex-wrap:wrap;gap:4px"><?php foreach ($obsPredefinidas as $op): ?>
            <button type="button" class="ss-obs-pred" onclick="agregarObsPred(this.textContent.trim())"><?= htmlspecialchars($op['texto']) ?></button>
            <?php endforeach; ?></div>
        </div>
        <?php endif; ?>
        <div><div style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;margin-bottom:4px">Personalizada</div>
            <div style="display:flex;gap:4px"><input type="text" id="obsCustom" placeholder="Escribir observación..." style="flex:1;padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px;font-size:13px;outline:none;font-family:'DM Sans',sans-serif">
            <button type="button" onclick="agregarObsCustom()" style="padding:6px 12px;background:#1e3a5f;color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer">+</button></div>
        </div>
        <div style="margin-top:12px"><div style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;margin-bottom:4px">Actuales</div><div id="obsLista"></div></div>
    </div>
    <div class="ss-modal-foot"><button type="button" onclick="cerrarObs()" style="padding:6px 16px;background:#1e3a5f;color:white;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer">Guardar y cerrar</button></div>
</div>
</div>

<!-- PANEL CONTENIDOS -->
<?php if ($materiaInfo && $estudiantes): ?>
<div id="panelCont" class="ss-modal-bg hidden" onclick="if(event.target===this)toggleContenidos()">
<div class="ss-modal" style="max-width:500px">
    <div class="ss-modal-head"><h3><i class="bi bi-list-check"></i> Contenidos</h3><button onclick="toggleContenidos()" class="ss-modal-close"><i class="bi bi-x-lg"></i></button></div>
    <div class="ss-modal-body" style="max-height:50vh;overflow-y:auto">
        <?php foreach ($contenidos as $i => $ct): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:6px;border-bottom:1px solid #f3f4f6">
            <span style="background:#dbeafe;color:#1e40af;font-size:10px;font-weight:700;width:24px;height:24px;border-radius:4px;display:flex;align-items:center;justify-content:center;flex-shrink:0">C<?= $i+1 ?></span>
            <div style="flex:1"><div style="font-size:13px;font-weight:500"><?= htmlspecialchars($ct['titulo']) ?></div><div style="font-size:10px;color:#9ca3af"><?= $ct['tipo_evaluacion']==='numerica'?'Numérica':'Cualitativa' ?> · Bim <?= $ct['bimestre'] ?></div></div>
            <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar?')"><input type="hidden" name="accion" value="eliminar_contenido"><input type="hidden" name="materia_curso_id" value="<?=$materiaSel?>"><input type="hidden" name="contenido_id" value="<?=$ct['id']?>"><button style="background:none;border:none;color:#ef4444;cursor:pointer"><i class="bi bi-trash"></i></button></form>
        </div>
        <?php endforeach; ?>
        <?php if (!$contenidos): ?><p style="text-align:center;color:#9ca3af;padding:20px">No hay contenidos.</p><?php endif; ?>
    </div>
    <div class="ss-modal-foot">
        <form method="POST" style="display:flex;gap:4px;width:100%"><input type="hidden" name="accion" value="crear_contenido"><input type="hidden" name="materia_curso_id" value="<?=$materiaSel?>">
            <input type="text" name="titulo" placeholder="Título..." required style="flex:1;padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px;font-size:12px;outline:none;font-family:'DM Sans',sans-serif">
            <select name="tipo_evaluacion" style="padding:6px;border:1px solid #e5e7eb;border-radius:6px;font-size:11px"><option value="cualitativa">Cual.</option><option value="numerica">Num.</option></select>
            <select name="bimestre" style="padding:6px;border:1px solid #e5e7eb;border-radius:6px;font-size:11px"><option value="1">1°B</option><option value="3">3°B</option></select>
            <button style="padding:6px 12px;background:#1e3a5f;color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer"><i class="bi bi-plus"></i></button>
        </form>
    </div>
</div>
</div>
<?php endif; ?>

<script>
let obsEid=0,obsBim=0;
function abrirObs(eid,bim,nom){obsEid=eid;obsBim=bim;document.getElementById('obsTitle').textContent='Observaciones '+bim+'° Bimestre';document.getElementById('obsInfo').textContent=nom;renderObs();document.getElementById('modalObs').classList.remove('hidden');}
function cerrarObs(){document.getElementById('modalObs').classList.add('hidden');}
function obsField(){return document.getElementById('obs_'+obsEid+'_'+obsBim);}
function obsList(){const f=obsField();return f?f.value.split(';').map(s=>s.trim()).filter(Boolean):[];}
function setObs(list){const f=obsField();if(f)f.value=list.join('; ');const b=f?.parentElement?.querySelector('.ss-btn-obs');if(b){b.innerHTML=list.length?'<i class="bi bi-chat-square-text-fill"></i> '+list.length:'<i class="bi bi-chat-square-text"></i> Sin obs.';b.classList.toggle('ss-btn-obs-on',list.length>0);}renderObs();}
function renderObs(){const l=obsList(),el=document.getElementById('obsLista');if(!l.length){el.innerHTML='<div style="color:#d1d5db;font-size:12px;text-align:center;padding:10px">Sin observaciones</div>';return;}el.innerHTML=l.map((t,i)=>'<div style="display:flex;align-items:center;gap:6px;padding:4px 6px;background:#f9fafb;border-radius:6px;margin-bottom:3px;font-size:12px"><span style="flex:1">'+t+'</span><button type="button" onclick="quitarObs('+i+')" style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:11px"><i class="bi bi-x-circle"></i></button></div>').join('');}
function agregarObsPred(t){const l=obsList();if(!l.includes(t)){l.push(t);setObs(l);}}
function agregarObsCustom(){const inp=document.getElementById('obsCustom'),t=inp.value.trim();if(t){const l=obsList();l.push(t);setObs(l);inp.value='';}}
function quitarObs(i){const l=obsList();l.splice(i,1);setObs(l);}
function toggleContenidos(){document.getElementById('panelCont')?.classList.toggle('hidden');}

// Valoración coloring
document.querySelectorAll('.ss-sel-val').forEach(s=>{const c=()=>{s.className=s.className.replace(/ss-val-\w+/g,'');if(s.value)s.classList.add('ss-val-'+s.value.toLowerCase());};s.addEventListener('change',c);c();});
// Nota coloring
document.querySelectorAll('.ss-sel-nota').forEach(s=>{const c=()=>{s.classList.remove('ss-g-high','ss-g-mid','ss-g-low');const v=parseInt(s.value);if(!isNaN(v))s.classList.add(v>=7?'ss-g-high':v>=4?'ss-g-mid':'ss-g-low');};s.addEventListener('change',c);c();});
// Final nota coloring
document.querySelectorAll('.ss-input-nota').forEach(inp=>{const c=()=>{inp.classList.remove('ss-g-high','ss-g-mid','ss-g-low');const v=parseInt(inp.value);if(!isNaN(v))inp.classList.add(v>=7?'ss-g-high':v>=4?'ss-g-mid':'ss-g-low');};inp.addEventListener('input',c);c();});

// Calcular final (fórmula Excel)
function calcFinal(eid){const row=document.querySelector('[data-eid="'+eid+'"]');if(!row)return;const g=n=>{const el=row.querySelector('[name="est['+eid+']['+n+']"]');return el&&el.value?parseFloat(el.value):null;};
const c1=g('calificacion_1c'),c2=g('calificacion_2c'),i1=g('intensificacion_1c'),iD=g('intensificacion_diciembre'),iF=g('intensificacion_febrero');
let f=null;if(c1!==null&&c2!==null){if(Math.min(c1,c2)>=7)f=Math.round((c1+c2)/2);else if(c2>=7&&i1!==null)f=Math.round((c2+i1)/2);else if(iD!==null)f=iD;else if(iF!==null)f=iF;}
const inp=row.querySelector('[name="est['+eid+'][calificacion_final]"]');if(inp&&f!==null){inp.value=f;inp.classList.remove('ss-g-high','ss-g-mid','ss-g-low');inp.classList.add(f>=7?'ss-g-high':f>=4?'ss-g-mid':'ss-g-low');}}

// Ctrl+S
document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key==='s'){e.preventDefault();document.getElementById('formCal').submit();}});
</script>
<?php require_once 'footer.php'; ?>
