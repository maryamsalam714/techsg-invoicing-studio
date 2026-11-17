
<?php
require __DIR__ . '/../includes/api.php';

$user = require_login();

function project_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function project_id($value) {
    if (!preg_match('/^PRJ-(\d+)$/', (string)$value, $matches)) {
        return 0;
    }

    return (int)$matches[1];
}

function find_project_client($mysqli, $clientName) {
    $stmt = $mysqli->prepare(
        "SELECT id FROM clients
         WHERE name = ? AND is_archived = 0
         LIMIT 1"
    );

    $stmt->bind_param("s", $clientName);
    $stmt->execute();

    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $result ? (int)$result['id'] : 0;
}

function get_project_record($mysqli, $id) {
    $stmt = $mysqli->prepare(
        "SELECT p.*, c.name AS client_name
         FROM projects p
         JOIN clients c ON c.id = p.client_id
         WHERE p.id = ?
         LIMIT 1"
    );

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    return [
        'id' => 'PRJ-' . $row['id'],
        'name' => $row['project_name'],
        'client' => $row['client_name'],
        'startDate' => $row['start_date'],
        'deadline' => $row['deadline'],
        'budget' => (float)$row['budget'],
        'progress' => (int)$row['progress'],
        'status' => $row['status'],
        'manager' => $row['manager'],
        'description' => $row['description'],
        'createdAt' => $row['created_at'],
        'updatedAt' => $row['updated_at']
    ];
}

$method = $_SERVER['REQUEST_METHOD'];

/* GET: Fetch projects */
if ($method === 'GET') {
    $sql = "
        SELECT p.*, c.name AS client_name
        FROM projects p
        JOIN clients c ON c.id = p.client_id
        ORDER BY p.created_at DESC
    ";

    $result = $mysqli->query($sql);
    $projects = [];

    while ($row = $result->fetch_assoc()) {
        $projects[] = [
            'id' => 'PRJ-' . $row['id'],
            'name' => $row['project_name'],
            'client' => $row['client_name'],
            'startDate' => $row['start_date'],
            'deadline' => $row['deadline'],
            'budget' => (float)$row['budget'],
            'progress' => (int)$row['progress'],
            'status' => $row['status'],
            'manager' => $row['manager'],
            'description' => $row['description'],
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at']
        ];
    }

    project_response([
        'ok' => true,
        'projects' => $projects
    ]);
}

/* All other methods require CSRF protection */
require_csrf();

/* DELETE: Delete a project */
if ($method === 'DELETE') {
    $id = project_id($_GET['id'] ?? '');

    if (!$id) {
        project_response([
            'ok' => false,
            'message' => 'Invalid project ID.'
        ], 400);
    }

    $stmt = $mysqli->prepare("DELETE FROM projects WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $deleted = $stmt->affected_rows;
    $stmt->close();

    if (!$deleted) {
        project_response([
            'ok' => false,
            'message' => 'Project not found.'
        ], 404);
    }

    project_response([
        'ok' => true,
        'message' => 'Project deleted successfully.'
    ]);
}

/* POST and PUT: Create or update a project */
if ($method === 'POST' || $method === 'PUT') {
    $data = read_json();

    $id = $method === 'PUT'
        ? project_id($_GET['id'] ?? '')
        : 0;

    if ($method === 'PUT' && !$id) {
        project_response([
            'ok' => false,
            'message' => 'Invalid project ID.'
        ], 400);
    }

    $name = trim($data['name'] ?? '');
    $clientName = trim($data['client'] ?? '');
    $startDate = trim($data['startDate'] ?? '');
    $deadline = trim($data['deadline'] ?? '');
    $budget = $data['budget'] ?? 0;
    $progress = $data['progress'] ?? 0;
    $status = trim($data['status'] ?? 'Planning');
    $manager = trim($data['manager'] ?? '');
    $description = trim($data['description'] ?? '');

    $allowedStatuses = [
        'Planning',
        'Active',
        'On Hold',
        'Completed',
        'Cancelled'
    ];

    if ($name === '' || strlen($name) > 100) {
        project_response([
            'ok' => false,
            'message' => 'Enter a project name (maximum 100 characters).'
        ], 400);
    }

    if ($clientName === '') {
        project_response([
            'ok' => false,
            'message' => 'Enter a client name.'
        ], 400);
    }

    $validStart = DateTime::createFromFormat('!Y-m-d', $startDate);
    $validDeadline = DateTime::createFromFormat('!Y-m-d', $deadline);

    if (
        !$validStart ||
        $validStart->format('Y-m-d') !== $startDate ||
        !$validDeadline ||
        $validDeadline->format('Y-m-d') !== $deadline
    ) {
        project_response([
            'ok' => false,
            'message' => 'Please enter valid project dates.'
        ], 400);
    }

    if ($deadline < $startDate) {
        project_response([
            'ok' => false,
            'message' => 'Deadline cannot be before the start date.'
        ], 400);
    }

    if (!is_numeric($budget) || $budget < 0 || $budget > 9999999999999) {
        project_response([
            'ok' => false,
            'message' => 'Please enter a valid project budget.'
        ], 400);
    }

    if (
        filter_var($progress, FILTER_VALIDATE_INT) === false ||
        $progress < 0 ||
        $progress > 100
    ) {
        project_response([
            'ok' => false,
            'message' => 'Progress must be between 0 and 100.'
        ], 400);
    }

    if (!in_array($status, $allowedStatuses, true)) {
        project_response([
            'ok' => false,
            'message' => 'Invalid project status.'
        ], 400);
    }

    if (strlen($manager) > 100) {
        project_response([
            'ok' => false,
            'message' => 'Manager name is too long.'
        ], 400);
    }

    $clientId = find_project_client($mysqli, $clientName);

    if (!$clientId) {
        project_response([
            'ok' => false,
            'message' => 'Client not found. Please enter the exact name of an existing, active client.'
        ], 400);
    }

    $budget = (float)$budget;
    $progress = (int)$progress;
    $createdBy = (int)($user['id'] ?? 0);

    if ($method === 'POST') {
        $stmt = $mysqli->prepare(
            "INSERT INTO projects
            (project_name, client_id, start_date, deadline, budget,
             progress, status, manager, description, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "sissdisssi",
            $name,
            $clientId,
            $startDate,
            $deadline,
            $budget,
            $progress,
            $status,
            $manager,
            $description,
            $createdBy
        );
    } else {
        $stmt = $mysqli->prepare(
            "UPDATE projects SET
                project_name = ?,
                client_id = ?,
                start_date = ?,
                deadline = ?,
                budget = ?,
                progress = ?,
                status = ?,
                manager = ?,
                description = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            "sissdisssi",
            $name,
            $clientId,
            $startDate,
            $deadline,
            $budget,
            $progress,
            $status,
            $manager,
            $description,
            $id
        );
    }

    if (!$stmt->execute()) {
        $stmt->close();

        project_response([
            'ok' => false,
            'message' => 'Unable to save the project.'
        ], 500);
    }

    if ($method === 'POST') {
        $id = $mysqli->insert_id;
    }

    $stmt->close();

    $record = get_project_record($mysqli, $id);

    project_response([
        'ok' => true,
        'message' => $method === 'POST'
            ? 'Project created successfully.'
            : 'Project updated successfully.',
        'record' => $record
    ]);
}

project_response([
    'ok' => false,
    'message' => 'Method not allowed.'
], 405);
