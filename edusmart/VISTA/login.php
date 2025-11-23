<?php
// htdocs/edusmart/login.php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
start_secure_session();

// Si ya está autenticado, redirigir según rol
if (!empty($_SESSION['user'])) {
    $role = $_SESSION['user']['role'];
    if ($role === 'student') {
        header('Location: dashboard_student.php'); exit;
    } elseif ($role === 'parent') {
        header('Location: dashboard_parent.php'); exit;
    } elseif ($role === 'teacher' || $role === 'admin') {
        header('Location: dashboard_teacher.php'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>EduSmart - Inicio de Sesión</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
/* Estilos simplificados basados en tu mockup */
body{font-family:Segoe UI,Tahoma,Arial,sans-serif;background:linear-gradient(135deg,#667eea,#764ba2);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.login-container{background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.25);max-width:900px;width:100%;overflow:hidden}
.login-header{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:32px;text-align:center}
.login-title{font-weight:700;font-size:28px}
.login-subtitle{opacity:.9}
.login-content{padding:32px}
.step{display:none}
.step.active{display:block;animation:fadeIn .4s ease}
@keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
.role-selection{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}
.role-card{background:#f8f9fa;border:2px solid #e0e0e0;border-radius:12px;padding:24px;cursor:pointer;text-align:center;transition:.2s}
.role-card:hover{transform:translateY(-4px);border-color:#667eea;box-shadow:0 10px 24px rgba(102,126,234,.25)}
.role-icon{font-size:40px;margin-bottom:8px}
.form-group{margin-bottom:16px}
.form-input{width:100%;padding:14px;border:2px solid #e0e0e0;border-radius:10px;font-size:15px}
.form-input:focus{outline:none;border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,.12)}
.btn{padding:14px 20px;border:none;border-radius:10px;font-weight:700;cursor:pointer}
.btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
.btn-secondary{background:#fff;border:2px solid #667eea;color:#667eea}
.button-group{display:flex;gap:12px;margin-top:8px}
.badge{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:20px;background:#e8eaf6;color:#667eea;font-weight:700;margin-bottom:12px}
.links{text-align:center;margin-top:12px}
.links a{color:#667eea;text-decoration:none;margin:0 8px}
.error{color:#dc3545;margin-top:8px}
</style>
</head>
<body>
<div class="login-container">
  <div class="login-header">
    <div class="login-title">EduSmart</div>
    <div class="login-subtitle">Plataforma Educativa Integral</div>
  </div>
  <div class="login-content">
    <div class="step active" id="step1">
      <div style="text-align:center;margin-bottom:14px;font-weight:700;">¿Quién eres?</div>
      <div class="role-selection">
        <div class="role-card" onclick="selectRole('student')">
          <div class="role-icon">👨‍🎓</div>
          <div><strong>Estudiante</strong></div>
          <div>Accede a tus cursos y notas</div>
        </div>
        <div class="role-card" onclick="selectRole('parent')">
          <div class="role-icon">👨‍👩‍👧</div>
          <div><strong>Padre/Madre</strong></div>
          <div>Monitorea el progreso</div>
        </div>
        <div class="role-card" onclick="selectRole('teacher')">
          <div class="role-icon">👨‍🏫</div>
          <div><strong>Docente</strong></div>
          <div>Gestiona clases y evalúa</div>
        </div>
      </div>
    </div>

    <div class="step" id="step2">
      <div class="badge"><span id="roleIcon">👨‍🎓</span><span id="roleName">Estudiante</span></div>
      <form id="loginForm">
        <input type="hidden" id="role" name="role" value="student">
        <div class="form-group">
          <input class="form-input" id="email" name="email" type="email" placeholder="Correo electrónico" required>
        </div>
        <div class="form-group">
          <input class="form-input" id="password" name="password" type="password" placeholder="Contraseña" required>
        </div>
        <div class="button-group">
          <button type="button" class="btn btn-secondary" onclick="backToRole()">← Volver</button>
          <button type="submit" class="btn btn-primary">Iniciar Sesión →</button>
        </div>
        <div id="error" class="error"></div>
      </form>
      <div class="links">
        <a href="#" onclick="alert('Función de recuperación en desarrollo.')">Recuperar contraseña</a>
        <a href="#" onclick="alert('Registro deshabilitado en demo.')">Crear cuenta</a>
      </div>
    </div>
  </div>
</div>

<script>
const roleData = {
  student: {icon:'👨‍🎓', name:'Estudiante'},
  parent:  {icon:'👨‍👩‍👧', name:'Padre/Madre'},
  teacher: {icon:'👨‍🏫', name:'Docente'}
};

function selectRole(r){
  document.getElementById('step1').classList.remove('active');
  document.getElementById('step2').classList.add('active');
  document.getElementById('role').value = r;
  document.getElementById('roleIcon').textContent = roleData[r].icon;
  document.getElementById('roleName').textContent = roleData[r].name;
}
function backToRole(){
  document.getElementById('step2').classList.remove('active');
  document.getElementById('step1').classList.add('active');
  document.getElementById('loginForm').reset();
  document.getElementById('error').textContent='';
}

document.getElementById('loginForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const form = new FormData(e.target);
  try{
    const res = await fetch('api/login.php',{method:'POST', body: form, credentials:'same-origin'});
    const data = await res.json();
    if(data.success){
      window.location.href = data.redirect;
    }else{
      document.getElementById('error').textContent = data.message || 'Credenciales inválidas';
    }
  }catch(err){
    document.getElementById('error').textContent = 'Error de red o servidor';
  }
});
</script>
</body>
</html>