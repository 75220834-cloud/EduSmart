<?php
// htdocs/edusmart/api/save_attendance.php
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
    echo json_encode(['success' => false, 'message' => 'Solo docentes/admin pueden guardar asistencia']);
    exit;
}

// Intentar leer cuerpo como JSON; si no, usar POST (compatibilidad)
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    // Compatibilidad con form-data: entries puede venir como JSON en un campo
    $payload = [
        'date' => $_POST['date'] ?? null,
        'entries' => isset($_POST['entries']) ? json_decode((string)$_POST['entries'], true) : null
    ];
}

$date = trim((string)($payload['date'] ?? ''));
$entries = $payload['entries'] ?? null;

if ($date === '' || !is_array($entries)) {
    echo json_encode(['success' => false, 'message' => 'Parámetros incompletos (date, entries)']);
    exit;
}

// Validar formato de fecha YYYY-MM-DD
$dt = DateTime::createFromFormat('Y-m-d', $date);
if (!$dt || $dt->format('Y-m-d') !== $date) {
    echo json_encode(['success' => false, 'message' => 'Fecha inválida (use formato YYYY-MM-DD)']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();

    // Preparar sentencias
    $stmtCheckStudent = $db->prepare("SELECT 1 FROM students WHERE dni = :dni");
    // Evitar duplicados del mismo docente/fecha/estudiante
    $stmtDelete = $db->prepare("
        DELETE FROM attendance 
        WHERE date = :date AND student_dni = :dni AND teacher_id = :teacher_id
    ");
    $stmtInsert = $db->prepare("
        INSERT INTO attendance (date, student_dni, status, teacher_id, created_at)
        VALUES (:date, :dni, :status, :teacher_id, NOW())
    ");

    $saved = 0;
    foreach ($entries as $entry) {
        $dni = trim((string)($entry['student_dni'] ?? ''));
        $status = trim((string)($entry['status'] ?? ''));

        if ($dni === '' || !in_array($status, ['present', 'absent'], true)) {
            // Entrada inválida, omitir
            continue;
        }

        // Validar estudiante existe
        $stmtCheckStudent->execute([':dni' => $dni]);
        if (!$stmtCheckStudent->fetch()) {
            // Si no existe, omitir (evita violar FKs)
            continue;
        }

        // Eliminar registro previo del mismo docente y fecha para este estudiante
        $stmtDelete->execute([
            ':date' => $date,
            ':dni' => $dni,
            ':teacher_id' => (int)$user['id']
        ]);

        // Insertar nueva marca de asistencia
        $stmtInsert->execute([
            ':date' => $date,
            ':dni' => $dni,
            ':status' => $status,
            ':teacher_id' => (int)$user['id']
        ]);

        $saved++;
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Asistencia guardada correctamente',
        'date' => $date,
        'saved' => $saved
    ]);
    exit;

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de servidor']);
    exit;
}
