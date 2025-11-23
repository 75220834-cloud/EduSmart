<?php
// htdocs/edusmart/dashboard_teacher.php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
start_secure_session();

// Proteger ruta
if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['teacher','admin'], true)) {
    header('Location: login.php'); exit;
}
$user = $_SESSION['user'];

// Cargar cursos y estudiantes (para UI)
try {
    $db = getDB();
    $courses = $db->query("SELECT id, name FROM courses ORDER BY name")->fetchAll();

    // Lista de 8 estudiantes para la asistencia
    $students = $db->query("SELECT dni, name, grade_level FROM students ORDER BY dni LIMIT 8")->fetchAll();
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error cargando datos: " . htmlspecialchars($e->getMessage());
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>EduSmart - Panel Docente</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body{font-family:Segoe UI,Tahoma,Geneva,Verdana,sans-serif;background:#f5f7fa;margin:0}
.navbar{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:16px 0;box-shadow:0 2px 10px rgba(0,0,0,.1)}
.navbar-content{max-width:1200px;margin:0 auto;padding:0 16px;display:flex;justify-content:space-between;align-items:center}
.logo{font-weight:700;font-size:20px}
.container{max-width:1200px;margin:0 auto;padding:20px 16px}
.page-header{background:#fff;padding:20px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.05);margin-bottom:16px}
.tabs{display:flex;gap:8px;margin:12px 0}
.tab-btn{padding:10px 16px;border:2px solid #667eea;background:#fff;color:#667eea;border-radius:8px;cursor:pointer;font-weight:700}
.tab-btn.active{background:#667eea;color:#fff}
.tab{display:none}
.tab.active{display:block}
.card{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.05);padding:20px;margin-bottom:16px}
.form-group{margin-bottom:14px}
.label{font-weight:700;margin-bottom:6px;display:block}
.input,select,textarea{width:100%;padding:12px;border:2px solid #e0e0e0;border-radius:8px}
.btn{padding:12px 16px;border:none;border-radius:8px;font-weight:700;cursor:pointer}
.btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
.btn-secondary{background:#fff;border:2px solid #667eea;color:#667eea}
.grid{display:grid;gap:12px}
.grid-2{grid-template-columns:1fr 1fr}
.table{width:100%;border-collapse:collapse}
.table th{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;text-align:left;padding:12px}
.table td{border-bottom:1px solid #e0e0e0;padding:12px}
.center{text-align:center}
.badge{padding:4px 10px;border-radius:16px;font-size:12px;font-weight:700}
.badge.ok{background:#d4edda;color:#155724}
.badge.no{background:#f8d7da;color:#721c24}
.success{display:none;background:#d4edda;color:#155724;padding:12px;border-left:4px solid #28a745;border-radius:8px;margin-top:10px}
.error{display:none;background:#f8d7da;color:#721c24;padding:12px;border-left:4px solid #dc3545;border-radius:8px;margin-top:10px}
@media(max-width:768px){.grid-2{grid-template-columns:1fr}}
a{color:#fff;text-decoration:none}
</style>
</head>
<body>
<div class="navbar">
  <div class="navbar-content">
    <div class="logo">🎓 EduSmart — Panel Docente</div>
    <div>
      <span><?= htmlspecialchars($user['name']) ?></span> |
      <a href="logout.php" style="text-decoration:underline;color:#fff">Cerrar sesión</a>
    </div>
  </div>
</div>

<div class="container">
  <div class="page-header">
    <h2>Bienvenido, <?= htmlspecialchars($user['name']) ?></h2>
    <div class="tabs">
      <button class="tab-btn active" onclick="openTab('tab-grades', this)">Subir Notas</button>
      <button class="tab-btn" onclick="openTab('tab-attendance', this)">Registro de Asistencia</button>
    </div>
  </div>

  <!-- Subir Notas -->
  <div id="tab-grades" class="tab active">
    <div class="card">
      <h3>📝 Registro de Calificaciones</h3>
      <div class="grid grid-2">
        <div class="form-group">
          <label class="label">DNI del Estudiante</label>
          <input class="input" id="grade_dni" type="text" placeholder="Ej: 75842196" maxlength="20" oninput="lookupStudent()">
          <small>Ingrese 8 dígitos para buscar automáticamente</small>
          <div id="student_preview" class="success" style="display:none;margin-top:8px"></div>
          <div id="student_error" class="error" style="display:none;margin-top:8px"></div>
        </div>
        <div class="form-group">
          <label class="label">Curso</label>
          <select id="grade_course" class="input">
            <option value="">Seleccionar curso...</option>
            <?php foreach ($courses as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="grid grid-2">
        <div class="form-group">
          <label class="label">Calificación (0-20)</label>
          <input class="input" id="grade_value" type="number" min="0" max="20" step="0.5" placeholder="Ej: 16.5">
        </div>
        <div class="form-group">
          <label class="label">Retroalimentación</label>
          <textarea id="grade_comments" rows="4" placeholder="- Fortalezas\n- Áreas de mejora\n- Recomendaciones"></textarea>
        </div>
      </div>
      <button class="btn btn-primary" onclick="saveGrade()">💾 Guardar Calificación</button>
      <div id="grade_success" class="success">✅ Calificación guardada correctamente.</div>
      <div id="grade_error" class="error">❌ Error guardando la calificación.</div>
    </div>
  </div>

  <!-- Registro de Asistencia -->
  <div id="tab-attendance" class="tab">
    <div class="card">
      <h3>✅ Registro de Asistencia — <span id="today"></span></h3>
      <div style="margin-bottom:8px;">
        <button class="btn btn-secondary" onclick="markAll(true)">Marcar todos presentes</button>
        <button class="btn btn-secondary" onclick="markAll(false)">Desmarcar todos</button>
        <button class="btn btn-secondary" onclick="downloadCSV()">Descargar CSV</button>
      </div>
      <table class="table">
        <thead>
          <tr>
            <th>#</th><th>DNI</th><th>Estudiante</th><th>Grado</th><th class="center">Presente</th><th class="center">Estado</th>
          </tr>
        </thead>
        <tbody id="att_body">
          <?php $i=1; foreach ($students as $s): ?>
          <tr data-dni="<?= htmlspecialchars($s['dni']) ?>">
            <td><strong><?= $i++ ?></strong></td>
            <td><?= htmlspecialchars($s['dni']) ?></td>
            <td><?= htmlspecialchars($s['name']) ?></td>
            <td><?= htmlspecialchars($s['grade_level']) ?></td>
            <td class="center">
              <input type="checkbox" checked onchange="updateRow(this)">
            </td>
            <td class="center">
              <span class="badge ok">Presente</span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div style="margin:8px 0">
        <strong>Total:</strong> <span id="st_total">0</span> |
        <strong>Presentes:</strong> <span id="st_present">0</span> |
        <strong>Ausentes:</strong> <span id="st_absent">0</span> |
        <strong>Asistencia:</strong> <span id="st_rate">0%</span>
      </div>
      <button class="btn btn-primary" onclick="saveAttendance()">💾 Guardar Asistencia del Día</button>
      <div id="att_success" class="success">✅ Asistencia guardada correctamente.</div>
      <div id="att_error" class="error">❌ Error guardando la asistencia.</div>
    </div>
  </div>
</div>

<script>
function openTab(id, btn){
  document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
}

// Subir Notas
async function lookupStudent(){
  const dni = document.getElementById('grade_dni').value.trim();
  const ok = document.getElementById('student_preview');
  const err = document.getElementById('student_error');
  ok.style.display='none'; err.style.display='none';
  if (dni.length < 8) return;
  try{
    const res = await fetch(`api/get_student.php?dni=${encodeURIComponent(dni)}`, {credentials:'same-origin'});
    const data = await res.json();
    if (data.success){
      ok.textContent = `👤 ${data.student.name} — Grado: ${data.student.grade_level}`;
      ok.style.display='block';
    } else {
      err.textContent = data.message || 'No encontrado';
      err.style.display='block';
    }
  }catch(e){
    err.textContent = 'Error de red o servidor';
    err.style.display='block';
  }
}

async function saveGrade(){
  const dni = document.getElementById('grade_dni').value.trim();
  const courseId = document.getElementById('grade_course').value;
  const gradeVal = document.getElementById('grade_value').value;
  const comments = document.getElementById('grade_comments').value.trim();
  const ok = document.getElementById('grade_success');
  const err = document.getElementById('grade_error');
  ok.style.display='none'; err.style.display='none';

  if (dni.length < 8) { err.textContent='Ingrese un DNI válido.'; err.style.display='block'; return; }
  if (!courseId) { err.textContent='Seleccione un curso.'; err.style.display='block'; return; }
  const num = parseFloat(gradeVal);
  if (Number.isNaN(num) || num < 0 || num > 20) { err.textContent='Ingrese una nota entre 0 y 20.'; err.style.display='block'; return; }

  try{
    const fd = new FormData();
    fd.append('student_dni', dni);
    fd.append('course_id', courseId);
    fd.append('grade', num.toString());
    fd.append('comments', comments);

    const res = await fetch('api/save_grade.php', {
      method:'POST',
      body: fd,
      credentials:'same-origin'
    });
    const data = await res.json();
    if (data.success){
      ok.textContent = '✅ Calificación guardada correctamente.';
      ok.style.display='block';
      // Limpiar campos
      document.getElementById('grade_value').value = '';
      document.getElementById('grade_comments').value = '';
    } else {
      err.textContent = data.message || 'Error guardando la calificación.';
      err.style.display='block';
    }
  }catch(e){
    err.textContent = 'Error de red o servidor.';
    err.style.display='block';
  }
}

// Asistencia
function updateRow(checkbox){
  const tr = checkbox.closest('tr');
  const badge = tr.querySelector('.badge');
  if (checkbox.checked){
    badge.textContent = 'Presente';
    badge.classList.remove('no');
    badge.classList.add('ok');
  } else {
    badge.textContent = 'Ausente';
    badge.classList.remove('ok');
    badge.classList.add('no');
  }
  recalcStats();
}

function recalcStats(){
  const rows = Array.from(document.querySelectorAll('#att_body tr'));
  const total = rows.length;
  let present = 0;
  rows.forEach(r=>{
    const cb = r.querySelector('input[type="checkbox"]');
    if (cb && cb.checked) present++;
  });
  const absent = total - present;
  const rate = total ? ((present/total)*100).toFixed(1) : '0.0';
  document.getElementById('st_total').textContent = total.toString();
  document.getElementById('st_present').textContent = present.toString();
  document.getElementById('st_absent').textContent = absent.toString();
  document.getElementById('st_rate').textContent = rate + '%';
}

function markAll(state){
  const rows = Array.from(document.querySelectorAll('#att_body tr'));
  rows.forEach(r=>{
    const cb = r.querySelector('input[type="checkbox"]');
    if (cb){
      cb.checked = state;
      // actualizar badge
      const badge = r.querySelector('.badge');
      if (state){
        badge.textContent = 'Presente';
        badge.classList.remove('no');
        badge.classList.add('ok');
      } else {
        badge.textContent = 'Ausente';
        badge.classList.remove('ok');
        badge.classList.add('no');
      }
    }
  });
  recalcStats();
}

function downloadCSV(){
  const today = new Date().toISOString().slice(0,10);
  const rows = Array.from(document.querySelectorAll('#att_body tr'));
  let csv = 'DNI,Nombre,Grado,Estado,Fecha\n';
  rows.forEach(r=>{
    const dni = r.getAttribute('data-dni') || '';
    const tds = r.querySelectorAll('td');
    const name = tds[2]?.textContent?.trim() || '';
    const grade = tds[3]?.textContent?.trim() || '';
    const cb = r.querySelector('input[type="checkbox"]');
    const status = (cb && cb.checked) ? 'present' : 'absent';
    csv += `"${dni}","${name}","${grade}","${status}","${today}"\n`;
  });
  const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `asistencia_${today}.csv`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

async function saveAttendance(){
  const ok = document.getElementById('att_success');
  const err = document.getElementById('att_error');
  ok.style.display='none'; err.style.display='none';

  const today = new Date().toISOString().slice(0,10);
  const rows = Array.from(document.querySelectorAll('#att_body tr'));
  const entries = rows.map(r=>{
    const dni = r.getAttribute('data-dni') || '';
    const cb = r.querySelector('input[type="checkbox"]');
    const status = (cb && cb.checked) ? 'present' : 'absent';
    return { student_dni: dni, status };
  });

  try{
    const res = await fetch('api/save_attendance.php', {
      method:'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ date: today, entries }),
      credentials:'same-origin'
    });
    const data = await res.json();
    if (data.success){
      ok.textContent = '✅ Asistencia guardada correctamente.';
      ok.style.display='block';
    } else {
      err.textContent = data.message || 'Error guardando asistencia.';
      err.style.display='block';
    }
  }catch(e){
    err.textContent = 'Error de red o servidor.';
    err.style.display='block';
  }
}

// Inicializar fecha y estadísticas
(function init(){
  const opts = { weekday: 'long', year:'numeric', month:'long', day:'numeric' };
  const todayStr = new Date().toLocaleDateString('es-PE', opts);
  document.getElementById('today').textContent = todayStr;
  recalcStats();
})();
</script>
</body>
</html>