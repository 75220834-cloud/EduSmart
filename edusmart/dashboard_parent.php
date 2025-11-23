<?php
// htdocs/edusmart/dashboard_parent.php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
start_secure_session();

// Proteger ruta: solo padres/madres
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'parent') {
    header('Location: login.php'); exit;
}
$user = $_SESSION['user'];
$childDni = $user['linked_student_dni'] ?? '';

// Cargar datos básicos del estudiante hijo(a)
$student = null;
if ($childDni !== '') {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT dni, name, grade_level FROM students WHERE dni = :dni LIMIT 1");
        $stmt->execute([':dni' => $childDni]);
        $student = $stmt->fetch();
    } catch (Throwable $e) {
        // Silenciar error en UI, se intentará con AJAX también
        $student = null;
    }
}

$studentName = $student['name'] ?? 'Estudiante';
$studentGradeLevel = $student['grade_level'] ?? '—';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>EduSmart - Panel Padre/Madre</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;background:#f5f7fa;min-height:100vh}
.navbar{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:16px 0;box-shadow:0 2px 10px rgba(0,0,0,0.1)}
.navbar-content{max-width:1200px;margin:0 auto;padding:0 16px;display:flex;justify-content:space-between;align-items:center}
.logo{font-weight:700;font-size:20px}
.container{max-width:1200px;margin:0 auto;padding:20px 16px}
.report-card{background:#fff;border-radius:15px;padding:24px;box-shadow:0 2px 10px rgba(0,0,0,0.05)}
.report-header{text-align:center;margin-bottom:24px;padding-bottom:16px;border-bottom:4px solid #667eea}
.institution-logo{font-size:42px;margin-bottom:8px}
.report-title{font-size:24px;color:#333;margin-bottom:6px;font-weight:700}
.report-subtitle{color:#666}
.student-info{background:linear-gradient(135deg,#f8f9fa,#e9ecef);padding:18px;border-radius:12px;margin:18px 0;border-left:5px solid #667eea}
.info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
.info-item{display:flex;flex-direction:column;gap:4px}
.info-label{color:#666;font-size:12px;font-weight:700;letter-spacing:.5px;text-transform:uppercase}
.info-value{color:#333;font-size:16px;font-weight:700}
.grades-table{width:100%;border-collapse:collapse;margin-top:10px}
.grades-table thead{background:linear-gradient(135deg,#667eea,#764ba2)}
.grades-table th{color:#fff;padding:14px 12px;text-align:left;font-weight:700}
.grades-table td{padding:14px 12px;border-bottom:1px solid #e0e0e0;vertical-align:top}
.grades-table tbody tr:hover{background:#f8f9fa}
.course-name-cell{font-weight:700;color:#333}
.grade-cell{text-align:center;font-weight:800;font-size:18px}
.grade-excellent{color:#28a745}
.grade-good{color:#17a2b8}
.grade-regular{color:#ffc107}
.grade-poor{color:#dc3545}
.feedback-cell{background:#fff3cd;padding:12px;border-radius:8px;border-left:4px solid #ffc107;color:#856404;line-height:1.5}
.summary-section{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-top:18px}
.summary-card{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:18px;border-radius:12px;text-align:center}
.summary-label{font-size:13px;opacity:.95;margin-bottom:6px}
.summary-value{font-size:28px;font-weight:800}
.print-button{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;padding:12px 32px;border-radius:10px;font-size:16px;font-weight:700;cursor:pointer;margin-top:20px}
.print-button:hover{opacity:.95}
a{color:#fff;text-decoration:underline}
@media print {.navbar,.print-button{display:none} body{background:#fff}}
</style>
</head>
<body>
<div class="navbar">
  <div class="navbar-content">
    <div class="logo">🎓 EduSmart — Panel Padre/Madre</div>
    <div>
      <span><?= htmlspecialchars($user['name']) ?></span> |
      <a href="logout.php">Cerrar sesión</a>
    </div>
  </div>
</div>

<div class="container">
  <div class="report-card">
    <div class="report-header">
      <div class="institution-logo">🎓</div>
      <div class="report-title">📋 Boleta de Notas</div>
      <div class="report-subtitle">Reporte Académico del Estudiante</div>
    </div>

    <div class="student-info">
      <div class="info-grid">
        <div class="info-item">
          <span class="info-label">👤 Estudiante</span>
          <span class="info-value" id="st_name"><?= htmlspecialchars($studentName) ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">🆔 DNI</span>
          <span class="info-value" id="st_dni"><?= htmlspecialchars($childDni) ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">📚 Grado</span>
          <span class="info-value" id="st_grade"><?= htmlspecialchars($studentGradeLevel) ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">📅 Periodo</span>
          <span class="info-value" id="period_label">Periodo Actual</span>
        </div>
      </div>
    </div>

    <table class="grades-table">
      <thead>
        <tr>
          <th style="width: 30%;">Curso</th>
          <th style="width: 10%; text-align: center;">Nota</th>
          <th style="width: 60%;">Retroalimentación del Docente</th>
        </tr>
      </thead>
      <tbody id="gradesBody">
        <tr><td colspan="3">Cargando...</td></tr>
      </tbody>
    </table>

    <div class="summary-section">
      <div class="summary-card">
        <div class="summary-label">Promedio General</div>
        <div class="summary-value" id="averageGrade">-</div>
      </div>
      <div class="summary-card">
        <div class="summary-label">Cursos Aprobados</div>
        <div class="summary-value" id="approvedCourses">-</div>
      </div>
      <div class="summary-card">
        <div class="summary-label">Nota Más Alta</div>
        <div class="summary-value" id="highestGrade">-</div>
      </div>
    </div>

    <center>
      <button class="print-button" onclick="window.print()">🖨️ Imprimir Boleta</button>
    </center>
  </div>
</div>

<script>
const CHILD_DNI = <?= json_encode($childDni) ?>;

// Cargar ficha del estudiante (por si no se pudo en servidor)
async function loadStudent(){
  try{
    const res = await fetch(`api/get_student.php?dni=${encodeURIComponent(CHILD_DNI)}`, {credentials:'same-origin'});
    const data = await res.json();
    if (data.success){
      document.getElementById('st_name').textContent = data.student.name;
      document.getElementById('st_dni').textContent = data.student.dni;
      document.getElementById('st_grade').textContent = data.student.grade_level;
    }
  }catch(e){
    // Silenciar error
  }
}

function getGradeClass(grade){
  if (grade >= 18) return 'grade-excellent';
  if (grade >= 14) return 'grade-good';
  if (grade >= 11) return 'grade-regular';
  return 'grade-poor';
}

// Cargar notas
async function loadGrades(){
  const tbody = document.getElementById('gradesBody');
  tbody.innerHTML = '<tr><td colspan="3">Cargando...</td></tr>';

  try{
    const res = await fetch(`api/get_grades.php?student_dni=${encodeURIComponent(CHILD_DNI)}`, {credentials:'same-origin'});
    const data = await res.json();
    if (!data.success){ tbody.innerHTML = '<tr><td colspan="3">No se pudieron cargar las notas.</td></tr>'; return; }

    // Mantener la nota más reciente por curso
    const latestByCourse = {};
    (data.grades || []).forEach(g=>{
      const k = g.course_name;
      if (!latestByCourse[k] || new Date(g.created_at) > new Date(latestByCourse[k].created_at)) {
        latestByCourse[k] = g;
      }
    });
    const list = Object.values(latestByCourse).sort((a,b)=>a.course_name.localeCompare(b.course_name));

    if (list.length === 0){
      tbody.innerHTML = '<tr><td colspan="3">Aún no hay calificaciones registradas.</td></tr>';
    } else {
      tbody.innerHTML = '';
      list.forEach(item=>{
        const grade = parseFloat(item.grade);
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td class="course-name-cell">${item.course_name}</td>
          <td class="grade-cell ${getGradeClass(grade)}">${isNaN(grade) ? '-' : grade}</td>
          <td>
            <div class="feedback-cell">${(item.comments || 'Sin retroalimentación').replace(/\n/g,'<br>')}</div>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }

    // Resumen
    if (data.summary){
      document.getElementById('averageGrade').textContent = data.summary.average ?? '-';
      document.getElementById('approvedCourses').textContent = `${data.summary.approved ?? 0}/${data.summary.count ?? 0}`;
      document.getElementById('highestGrade').textContent = data.summary.max ?? '-';
    } else {
      // Calcular si no viene summary
      const grades = list.map(x=>parseFloat(x.grade)).filter(x=>!isNaN(x));
      const avg = grades.length ? (grades.reduce((a,b)=>a+b,0)/grades.length) : 0;
      const approved = grades.filter(g=>g>=11).length;
      const max = grades.length ? Math.max(...grades) : 0;
      document.getElementById('averageGrade').textContent = avg.toFixed(1);
      document.getElementById('approvedCourses').textContent = `${approved}/${grades.length}`;
      document.getElementById('highestGrade').textContent = max.toFixed(1);
    }
  }catch(e){
    tbody.innerHTML = '<tr><td colspan="3">Error de red/servidor al cargar notas.</td></tr>';
  }
}

window.addEventListener('load', ()=>{
  if (!CHILD_DNI){ alert('No hay estudiante vinculado a esta cuenta.'); return; }
  loadStudent();
  loadGrades();
});
</script>
</body>
</html>
