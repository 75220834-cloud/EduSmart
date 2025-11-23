<?php
// htdocs/edusmart/api/get_student.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';
start_secure_session();

// Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar sesión iniciada
$user = $_SESSION['user'] ?? null;
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

// DNI requerido
$dni = trim($_GET['dni'] ?? '');
if ($dni === '') {
    echo json_encode(['success' => false, 'message' => 'DNI requerido']);
    exit;
}

// Control de acceso según rol
$role = $user['role'] ?? '';
$allowed = false;

if ($role === 'admin' || $role === 'teacher') {
    // Admin y Docente pueden consultar cualquier estudiante
    $allowed = true;
} elseif ($role === 'student') {
    // Estudiante solo puede ver su propio registro
    $allowed = (($user['dni'] ?? '') === $dni);
} elseif ($role === 'parent') {
    // Padre/Madre solo puede ver el registro vinculado a su cuenta
    $allowed = (($user['linked_student_dni'] ?? '') === $dni);
}

if (!$allowed) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

// Consultar estudiante en BD
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT 
            dni,
            name,
            grade_level,
            COALESCE(extra_info, '') AS extra_info
        FROM students
        WHERE dni = :dni
        LIMIT 1
    ");
    $stmt->execute([':dni' => $dni]);
    $student = $stmt->fetch();

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Estudiante no encontrado']);
        exit;
    }

    echo json_encode(['success' => true, 'student' => $student]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de servidor']);
    exit;
}
