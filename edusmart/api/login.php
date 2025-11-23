<?php
// htdocs/edusmart/api/login.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';
start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Método no permitido']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$roleRequested = trim($_POST['role'] ?? '');

if ($email === '' || $password === '' || !in_array($roleRequested, ['student','parent','teacher'], true)) {
    echo json_encode(['success'=>false,'message'=>'Datos incompletos']);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, dni, email, password_hash, role, linked_student_dni, name
        FROM users
        WHERE email = :email
        LIMIT 1
    ");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        echo json_encode(['success'=>false,'message'=>'Credenciales inválidas']);
        exit;
    }

    // Validar rol solicitado vs rol de BD
    $dbRole = $user['role']; // 'admin','teacher','student','parent'
    $allowed = false;
    $redirect = '/edusmart/login.php'; // fallback

    // Ajusta el prefijo '/edusmart/' si tu carpeta se llama distinto
    if ($roleRequested === 'student' && $dbRole === 'student') {
        $allowed = true; $redirect = '/edusmart/dashboard_student.php';
    } elseif ($roleRequested === 'parent' && $dbRole === 'parent') {
        $allowed = true; $redirect = '/edusmart/dashboard_parent.php';
    } elseif ($roleRequested === 'teacher' && ($dbRole === 'teacher' || $dbRole === 'admin')) {
        $allowed = true; $redirect = '/edusmart/dashboard_teacher.php';
    }

    if (!$allowed) {
        echo json_encode(['success'=>false,'message'=>'Rol no autorizado para esta cuenta']);
        exit;
    }

    // Sesión segura
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'dni' => $user['dni'],
        'email' => $user['email'],
        'role' => $dbRole,
        'name' => $user['name'],
        'linked_student_dni' => $user['linked_student_dni']
    ];

    echo json_encode(['success'=>true,'redirect'=>$redirect]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error de servidor']);
}