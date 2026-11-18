
<?php
// api/audit.php
// GET: return audit history for administrators.
require __DIR__ . '/../includes/api.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed.', 405);
}

$stmt = $mysqli->prepare(
    "SELECT
        a.id,
        a.user_id,
        a.action,
        a.entity,
        a.entity_id,
        a.details,
        a.ip,
        a.created_at,
        u.name AS user_name,
        u.username AS user_username
     FROM audit_log a
     LEFT JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC, a.id DESC"
);

$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$logs = [];

foreach ($rows as $row) {
    $rawAction = strtolower((string)$row['action']);
    $entity = trim((string)($row['entity'] ?? ''));
    $entityId = trim((string)($row['entity_id'] ?? ''));
    $details = trim((string)($row['details'] ?? ''));

    // Convert database action names into the labels used by the page.
    if ($rawAction === 'create') {
        $action = 'Create';
    } elseif ($rawAction === 'update') {
        $action = 'Update';
    } elseif ($rawAction === 'delete') {
        $action = 'Delete';
    } elseif (in_array($rawAction, ['login', 'login_failed'], true)) {
        $action = 'Login';
    } else {
        $action = 'System';
    }

    // Build a readable activity description.
    $entityLabel = $entity !== ''
        ? ucfirst(str_replace('_', ' ', $entity))
        : 'System';

    if ($rawAction === 'login') {
        $activity = 'User signed in';
    } elseif ($rawAction === 'login_failed') {
        $activity = 'Failed login attempt';
    } elseif ($entityId !== '') {
        $activity = $entityLabel . ' ' . $entityId . ' ' . strtolower($action) . 'd';
    } else {
        $activity = $entityLabel . ' ' . strtolower($action) . 'd';
    }

    // Use the attempted username for failed logins.
    $userName = trim((string)($row['user_name'] ?? ''));
    $username = trim((string)($row['user_username'] ?? ''));

    if ($userName === '') {
        $userName = $rawAction === 'login_failed'
            ? ($entityId !== '' ? $entityId : 'Unknown user')
            : 'System';
    }

    if ($username === '') {
        $username = $rawAction === 'login_failed'
            ? 'Unsuccessful sign-in'
            : '';
    }

    $logs[] = [
        'id'       => (int)$row['id'],
        'activity' => $activity,
        'details'  => $details !== '' ? $details : 'No additional details',
        'user'     => $userName,
        'email'    => $username,
        'action'   => $action,
        'date'     => $row['created_at'],
        'ip'       => (string)($row['ip'] ?? ''),
    ];
}

json_out([
    'ok'   => true,
    'logs' => $logs,
    'total' => count($logs),
]);
