<?php
// htdocs/edusmart/api/get_grades.php
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

$role = $user['role'] ?? '';
$paramDni = trim((string)($_GET['student_dni'] ?? ''));

// Determinar el DNI objetivo según el rol
$targetDni = null;
if ($role === 'student') {
    $targetDni = $user['dni'] ?? '';
    // Si vino un DNI en query y no coincide con el suyo, denegar
    if ($paramDni !== '' && $paramDni !== $targetDni) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
        exit;
    }
} elseif ($role === 'parent') {
    $targetDni = $user['linked_student_dni'] ?? '';
    if ($paramDni !== '' && $paramDni !== $targetDni) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
        exit;
    }
} elseif ($role === 'teacher' || $role === 'admin') {
    // Docente/Admin deben especificar el DNI del estudiante si no tienen dni propio
    if ($paramDni === '') {
        echo json_encode(['success' => false, 'message' => 'student_dni requerido']);
        exit;
    }
    $targetDni = $paramDni;
} else {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Rol no autorizado']);
    exit;
}

if ($targetDni === '') {
    echo json_encode(['success' => false, 'message' => 'No se pudo determinar el estudiante']);
    exit;
}

try {
    $db = getDB();

    // Verificar que el estudiante exista y traer info
    $stmtStudent = $db->prepare("SELECT dni, name, grade_level FROM students WHERE dni = :dni LIMIT 1");
    $stmtStudent->execute([':dni' => $targetDni]);
    $student = $stmtStudent->fetch();
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Estudiante no encontrado']);
        exit;
    }

    // Obtener todas las calificaciones para el estudiante con nombre de curso
    $stmtGrades = $db->prepare("
        SELECT 
            g.id,
            g.student_dni,
            g.course_id,
            c.name AS course_name,
            g.grade,
            g.comments,
            g.created_at
        FROM grades g
        INNER JOIN courses c ON c.id = g.course_id
        WHERE g.student_dni = :dni
        ORDER BY c.name ASC, g.created_at DESC
    ");
    $stmtGrades->execute([':dni' => $targetDni]);
    $rows = $stmtGrades->fetchAll();

    // Formatear resultados
    $grades = [];
    foreach ($rows as $r) {
        $grades[] = [
            'id'          => (int)$r['id'],
            'student_dni' => $r['student_dni'],
            'course_id'   => (int)$r['course_id'],
            'course_name' => $r['course_name'],
            'grade'       => is_null($r['grade']) ? null : (float)$r['grade'],
            'comments'    => $r['comments'] ?? '',
            'created_at'  => $r['created_at']
        ];
    }

    // Calcular resumen basado en la última nota por curso
    $latestByCourse = [];
    foreach ($grades as $g) {
        $cid = $g['course_id'];
        if (!isset($latestByCourse[$cid])) {
            $latestByCourse[$cid] = $g;
        } else {
            // Como vienen ordenadas DESC por created_at, el primero que vimos debería ser el último
            // Pero por seguridad, comparamos fechas:
            if (strtotime((string)$g['created_at']) > strtotime((string)$latestByCourse[$cid]['created_at'])) {
                $latestByCourse[$cid] = $g;
            }
        }
    }

    $count = count($latestByCourse);
    $avg = 0.0;
    $max = null;
    $approved = 0;

    if ($count > 0) {
        $sum = 0.0;
        foreach ($latestByCourse as $g) {
            $gradeVal = (float)$g['grade'];
            $sum += $gradeVal;
            if ($max === null || $gradeVal > $max) $max = $gradeVal;
            if ($gradeVal >= 11.0) $approved++;
        }
        $avg = $sum / $count;
    }

    $summary = [
        'count'    => $count,
        'average'  => $count > 0 ? number_format($avg, 1, '.', '') : '0.0',
        'max'      => $max !== null ? number_format((float)$max, 1, '.', '') : '0.0',
        'approved' => $approved
    ];

    echo json_encode([
        'success' => true,
        'student' => $student,
        'grades'  => $grades,
        'summary' => $summary
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de servidor']);
    exit;
}
