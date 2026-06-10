<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$request = explode('/', trim($_SERVER['PATH_INFO'] ?? '/', '/'));
$resource = $request[0] ?? null;
$id = $request[1] ?? null;

$db = getDB();

// ========== ADVISORIES ==========
if ($method === 'GET' && $resource === 'advisories') {
    $stmt = $db->query("SELECT * FROM advisories ORDER BY created_at DESC");
    $advisories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($advisories);
    exit;
}

if ($method === 'POST' && $resource === 'advisories') {
    $input = json_decode(file_get_contents('php://input'), true);
    $uuid = uniqid();
    $stmt = $db->prepare("INSERT INTO advisories 
        (uuid, title, type, effective_date, time, summary, target_audience, severity, heat_index_value, action_mode, authority, source, status, private_confirmed)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $status = ($input['target_audience'] === 'public') ? 'active' : 'pending';
    $stmt->execute([
        $uuid, $input['title'], $input['type'], $input['effective_date'], $input['time'] ?? 'Immediate',
        $input['summary'], $input['target_audience'], $input['severity'] ?? 'Danger',
        $input['heat_index_value'] ?? null, $input['action_mode'], $input['authority'] ?? 'LGU/DepEd',
        $input['source'] ?? 'OCR+AI', $status, 0
    ]);
    echo json_encode(['success' => true, 'uuid' => $uuid]);
    exit;
}

if ($method === 'PUT' && $resource === 'advisories' && $id === 'confirm') {
    $input = json_decode(file_get_contents('php://input'), true);
    $uuid = $input['uuid'];
    $stmt = $db->prepare("UPDATE advisories SET private_confirmed = 1, status = 'active' WHERE uuid = ? AND target_audience = 'private'");
    $stmt->execute([$uuid]);
    echo json_encode(['success' => true]);
    exit;
}

// ========== SUBSCRIBERS ==========
if ($method === 'GET' && $resource === 'subscribers') {
    $stmt = $db->query("SELECT * FROM subscribers");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($method === 'POST' && $resource === 'subscribers') {
    $input = json_decode(file_get_contents('php://input'), true);
    $stmt = $db->prepare("INSERT INTO subscribers (name, school_type, phone, email) VALUES (?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE name = VALUES(name), school_type = VALUES(school_type), email = VALUES(email)");
    foreach ($input as $sub) {
        $stmt->execute([$sub['name'], $sub['school_type'], $sub['phone'], $sub['email'] ?? null]);
    }
    echo json_encode(['success' => true]);
    exit;
}

// ========== SMS BROADCAST (Simulated) ==========
if ($method === 'POST' && $resource === 'sms') {
    $input = json_decode(file_get_contents('php://input'), true);
    $message = $input['message'] ?? 'SIGLA Alert: New suspension advisory issued.';
    $stmt = $db->query("SELECT phone, name FROM subscribers");
    $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Simulate sending SMS (log to file or just return)
    $log = "[" . date('Y-m-d H:i:s') . "] BROADCAST: '$message' to " . count($subs) . " recipients\n";
    file_put_contents('sms_sim.log', $log, FILE_APPEND);
    echo json_encode(['success' => true, 'recipients' => count($subs), 'simulated' => true]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Endpoint not found']);
?>