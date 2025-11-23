<?php
// htdocs/edusmart/api/save_grade.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';
start_secure_session();

// Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar sesión iniciada y rol
$user = $_SESSION['user'] ?? null;
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
if (!in_array($user['role'] ?? '', ['admin', 'teacher'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solo docentes/admin pueden guardar notas']);
    exit;
}

// Leer y validar parámetros
$student_dni = trim((string)($_POST['student_dni'] ?? ''));
$course_id   = (int)($_POST['course_id'] ?? 0);
$gradeRaw    = $_POST['grade'] ?? null;
$comments    = trim((string)($_POST['comments'] ?? ''));

if ($student_dni === '' || $course_id <= 0 || $gradeRaw === null) {
    echo json_encode(['success' => false, 'message' => 'Parámetros incompletos (student_dni, course_id, grade)']);
    exit;
}

$grade = filter_var($gradeRaw, FILTER_VALIDATE_FLOAT);
if ($grade === false || $grade < 0 || $grade > 20) {
    echo json_encode(['success' => false, 'message' => 'La calificación debe ser un número entre 0 y 20']);
    exit;
}
if (mb_strlen($comments) > 2000) {
    echo json_encode(['success' => false, 'message' => 'Comentarios demasiado largos (máx 2000 caracteres)']);
    exit;
}

try {
    $db = getDB();

    // Validar estudiante
    $st = $db->prepare("SELECT 1 FROM students WHERE dni = :dni");
    $st->execute([':dni' => $student_dni]);
    if (!$st->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Estudiante no existe']);
        exit;
    }

    // Validar curso
    $ct = $db->prepare("SELECT 1 FROM courses WHERE id = :id");
    $ct->execute([':id' => $course_id]);
    if (!$ct->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Curso no existe']);
        exit;
    }

    // Insertar calificación
    $ins = $db->prepare("
        INSERT INTO grades (student_dni, course_id, grade, teacher_id, comments, created_at)
        VALUES (:student_dni, :course_id, :grade, :teacher_id, :comments, NOW())
    ");

    $ins->execute([
        ':student_dni' => $student_dni,
        ':course_id'   => $course_id,
        ':grade'       => $grade,
        ':teacher_id'  => (int)$user['id'],
        ':comments'    => ($comments !== '' ? $comments : null),
    ]);

    $gradeId = (int)$db->lastInsertId();

    echo json_encode([
        'success'  => true,
        'message'  => 'Calificación guardada',
        'grade_id' => $gradeId
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de servidor']);
    exit;
}
