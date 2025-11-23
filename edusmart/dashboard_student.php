<?php
// htdocs/edusmart/dashboard_student.php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
start_secure_session();

// Proteger ruta: solo estudiantes
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header('Location: login.php'); exit;
}
$user = $_SESSION['user'];
$studentDni = $user['dni'] ?? '';
$studentName = $user['name'] ?? 'Estudiante';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>EduSmart - Panel Estudiante</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;background:#f5f7fa;min-height:100vh}
.navbar{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:16px 0;box-shadow:0 2px 10px rgba(0,0,0,0.1)}
.navbar-content{max-width:1200px;margin:0 auto;padding:0 16px;display:flex;justify-content:space-between;align-items:center}
.logo{font-weight:700;font-size:20px}
.container{max-width:1200px;margin:0 auto;padding:20px 16px}
.page-header{background:#fff;padding:20px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.05);margin-bottom:16px}
.page-title{font-size:24px;color:#333;margin-bottom:8px}
.page-subtitle{color:#666}
.tabs{display:flex;gap:8px;margin-top:12px}
.tab-btn{padding:10px 16px;border:2px solid #667eea;background:#fff;color:#667eea;border-radius:8px;cursor:pointer;font-weight:700}
.tab-btn.active{background:#667eea;color:#fff}
.tab{display:none}
.tab.active{display:block}
.courses-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
.course-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.05);transition:all .3s ease}
.course-card:hover{transform:translateY(-4px);box-shadow:0 10px 24px rgba(0,0,0,.1)}
.course-header{display:flex;align-items:center;gap:12px;margin-bottom:10px}
.course-icon{width:52px;height:52px;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;color:#fff}
.course-name{font-weight:700;color:#333}
.course-grade{display:flex;align-items:center;justify-content:space-between;margin-top:10px;padding:12px;background:#e8eaf6;border-radius:10px}
.grade-value{font-size:24px;font-weight:700}
.grade-excellent{color:#28a745}
.grade-good{color:#17a2b8}
.grade-regular{color:#ffc107}
.grade-poor{color:#dc3545}
.progress-bar{width:100%;height:8px;background:#e0e0e0;border-radius:10px;overflow:hidden;margin-top:12px}
.progress-fill{height:100%;background:linear-gradient(90deg,#667eea,#764ba2)}
.feedback-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.05);margin-bottom:16px}
.feedback-header{display:flex;align-items:center;gap:16px;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #eee}
.feedback-title{flex:1}
.course-name-large{font-size:18px;font-weight:700;color:#333}
.feedback-section{background:#fff3cd;padding:14px;border-radius:10px;border-left:5px solid #ffc107;margin-bottom:12px}
.feedback-label{font-weight:700;color:#856404;margin-bottom:6px}
.feedback-text{color:#856404;line-height:1.5}
.exercise-box{background:#f8f9fa;border-left:5px solid #667eea;padding:14px;border-radius:10px}
.exercise-header{font-weight:700;color:#333;margin-bottom:8px}
.exercise-question{background:#fff;padding:12px;border-radius:8px;margin-bottom:8px}
.exercise-answer{background:#d4edda;padding:12px;border-radius:8px;border-left:4px solid #28a745}
.answer-label{font-weight:700;color:#155724;margin-bottom:6px}
.answer-text{color:#155724;line-height:1.5}
.small{font-size:12px;color:#555}
a{color:#fff;text-decoration:underline}
@media(max-width:768px){.courses-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="navbar">
  <div class="navbar-content">
    <div class="logo">🎓 EduSmart — Panel Estudiante</div>
    <div>
      <span><?= htmlspecialchars($studentName) ?></span> |
      <a href="logout.php">Cerrar sesión</a>
    </div>
  </div>
</div>

<div class="container">
  <div class="page-header">
    <div class="page-title">📚 Aula Virtual</div>
    <div class="page-subtitle">Tus cursos y progreso — Periodo actual</div>
    <div class="tabs">
      <button class="tab-btn active" onclick="openTab('tab-aula', this)">Aula Virtual</button>
      <button class="tab-btn" onclick="openTab('tab-follow', this)">Seguimiento</button>
    </div>
  </div>

  <!-- Aula Virtual -->
  <div id="tab-aula" class="tab active">
    <div id="coursesGrid" class="courses-grid"></div>
    <div class="small" id="summary"></div>
  </div>

  <!-- Seguimiento -->
  <div id="tab-follow" class="tab">
    <div id="feedbackContainer"></div>
  </div>
</div>

<script>
const STUDENT_DNI = <?= json_encode($studentDni) ?>;
const STUDENT_NAME = <?= json_encode($studentName) ?>;

// Iconos por curso
const courseIcons = {
  'Matemática':'🔢','Comunicación':'📖','Ciencia y Tecnología':'🔬','Inglés':'🌍',
  'Historia, Geografía y Economía':'🗺️','Educación Cívica y Ciudadanía':'⚖️',
  'Educación Física':'⚽','Arte y Cultura':'🎨','Informática':'💻','Tutoría y Orientación Educativa':'🎯'
};

function openTab(id, btn){
  document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
}

function getGradeClass(grade){
  if (grade >= 18) return 'grade-excellent';
  if (grade >= 14) return 'grade-good';
  if (grade >= 11) return 'grade-regular';
  return 'grade-poor';
}

async function loadGrades(){
  const grid = document.getElementById('coursesGrid');
  const summary = document.getElementById('summary');
  grid.innerHTML = 'Cargando...';
  summary.textContent = '';

  try{
    const res = await fetch(`api/get_grades.php?student_dni=${encodeURIComponent(STUDENT_DNI)}`, {credentials:'same-origin'});
    const data = await res.json();
    if (!data.success){ grid.innerHTML = 'No se pudieron cargar las notas.'; return; }

    // Agrupar por curso y tomar la nota más reciente
    const latestByCourse = {};
    data.grades.forEach(g=>{
      const key = g.course_name;
      if (!latestByCourse[key] || new Date(g.created_at) > new Date(latestByCourse[key].created_at)) {
        latestByCourse[key] = g;
      }
    });

    const items = Object.values(latestByCourse);
    if (items.length === 0){ grid.innerHTML = 'Aún no tienes notas registradas.'; return; }

    grid.innerHTML = '';
    items.sort((a,b)=>a.course_name.localeCompare(b.course_name)).forEach(item=>{
      const grade = parseFloat(item.grade);
      const gradeClass = getGradeClass(grade);
      const progress = Math.max(0, Math.min(100, (grade/20)*100));

      const card = document.createElement('div');
      card.className = 'course-card';
      card.innerHTML = `
        <div class="course-header">
          <div class="course-icon">${courseIcons[item.course_name] || '📘'}</div>
          <div class="course-name">${item.course_name}</div>
        </div>
        <div class="course-grade">
          <span>Nota Actual</span>
          <span class="grade-value ${gradeClass}">${grade}</span>
        </div>
        <div class="progress-bar"><div class="progress-fill" style="width:${progress}%"></div></div>
      `;
      grid.appendChild(card);
    });

    if (data.summary){
      summary.textContent = `Promedio: ${data.summary.average} | Cursos: ${data.summary.count} | Máxima: ${data.summary.max}`;
    }
  }catch(e){
    grid.innerHTML = 'Error de red/servidor al cargar notas.';
  }
}

function sampleExerciseFor(course){
  switch(course){
    case 'Matemática': return {q:'Resuelve: Si 3x + 5 = 20, ¿cuál es x?', a:'x = 5. 3x=15 ⇒ x=15/3.'};
    case 'Comunicación': return {q:'Identifica el sujeto en: "Los estudiantes escribieron una carta".', a:'Sujeto: "Los estudiantes".'};
    case 'Ciencia y Tecnología': return {q:'Define fotosíntesis.', a:'Proceso por el cual plantas convierten luz, agua y CO₂ en glucosa y O₂.'};
    case 'Inglés': return {q:'Complete: "She ___ to school every day."', a:'goes (3ra persona singular).'};
    case 'Informática': return {q:'¿Qué es un algoritmo?', a:'Conjunto de pasos finitos para resolver un problema.'};
    default: return {q:'Ejercicio de práctica', a:'Próximamente.'};
  }
}

async function loadFollowUp(){
  const container = document.getElementById('feedbackContainer');
  container.innerHTML = 'Cargando...';

  try{
    const res = await fetch(`api/get_grades.php?student_dni=${encodeURIComponent(STUDENT_DNI)}`, {credentials:'same-origin'});
    const data = await res.json();
    if (!data.success){ container.innerHTML = 'No se pudo cargar el seguimiento.'; return; }

    // Tomar último comentario por curso
    const latestByCourse = {};
    data.grades.forEach(g=>{
      const key = g.course_name;
      if (!latestByCourse[key] || new Date(g.created_at) > new Date(latestByCourse[key].created_at)) {
        latestByCourse[key] = g;
      }
    });

    const items = Object.values(latestByCourse);
    if (items.length === 0){ container.innerHTML = 'No hay retroalimentación disponible.'; return; }

    container.innerHTML = '';
    items.sort((a,b)=>a.course_name.localeCompare(b.course_name)).forEach(item=>{
      const ex = sampleExerciseFor(item.course_name);
      const feedback = item.comments && item.comments.trim() !== '' ? item.comments : 'Sin retroalimentación registrada.';
      const card = document.createElement('div');
      card.className = 'feedback-card';
      card.innerHTML = `
        <div class="feedback-header">
          <div class="course-icon" style="width:56px;height:56px;color:#fff">${courseIcons[item.course_name] || '📘'}</div>
          <div class="feedback-title">
            <div class="course-name-large">${item.course_name}</div>
            <div class="small">Última actualización: ${new Date(item.created_at).toLocaleString('es-PE')}</div>
          </div>
        </div>
        <div class="feedback-section">
          <div class="feedback-label">💬 Comentarios del Docente</div>
          <div class="feedback-text">${feedback.replace(/\n/g,'<br>')}</div>
        </div>
        <div class="exercise-box">
          <div class="exercise-header">🤖 Ejercicio Práctico</div>
          <div class="exercise-question"><strong>
	     Pregunta:</strong><br>${ex.q}</div>
          <div class="exercise-answer">
            <div class="answer-label">✅ Solución Explicada</div>
            <div class="answer-text">${ex.a}</div>
          </div>
        </div>
      `;
      container.appendChild(card);
    });
  }catch(e){
    container.innerHTML = 'Error de red/servidor al cargar seguimiento.';
  }
}

window.addEventListener('load', ()=>{
  loadGrades();
  loadFollowUp();
});
</script>
</body>
</html>