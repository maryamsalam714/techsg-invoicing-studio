
<?php
// Items Catalogue API
// GET    - List items
// POST   - Add item
// PUT    - Update item
// DELETE - Soft-delete item

require __DIR__ . '/../includes/api.php';

$user = require_login();
$method = $_SERVER['REQUEST_METHOD'];

function item_out(array $r): array {
    return [
        'id' => 'ITM-' . str_pad((string)$r['id'], 6, '0', STR_PAD_LEFT),
        'name' => (string)$r['name'],
        'sku' => (string)$r['sku'],
        'category' => (string)$r['category'],
        'unit' => (string)$r['unit'],
        'price' => (float)$r['price'],
        'costPrice' => (float)$r['cost_price'],
        'stock' => (int)$r['stock'],
        'lowStock' => (int)$r['low_stock'],
        'description' => (string)$r['description']
    ];
}

function item_input(array $in): array {
    $v = [];

    foreach ([
        'name' => 150,
        'sku' => 40,
        'category' => 80,
        'unit' => 30,
        'description' => 5000
    ] as $key => $max) {
        $v[$key] = trim((string)($in[$key] ?? ''));

        if (mb_strlen($v[$key]) > $max) {
            json_error("$key is too long.", 422);
        }
    }

    if ($v['name'] === '' || $v['category'] === '') {
        json_error('Item name and category are required.', 422);
    }

    foreach (['price', 'costPrice', 'stock', 'lowStock'] as $key) {
        $v[$key] = (float)($in[$key] ?? 0);

        if (!is_finite($v[$key]) || $v[$key] < 0) {
            json_error('Prices and stock must be non-negative numbers.', 422);
        }
    }

    if (
        floor($v['stock']) != $v['stock'] ||
        floor($v['lowStock']) != $v['lowStock']
    ) {
        json_error('Stock values must be whole numbers.', 422);
    }

    return $v;
}

function item_find_id(string $code): int {
    global $mysqli;

    if (!preg_match('/^ITM-(\d{1,10})$/', $code, $m)) {
        json_error('Invalid item ID.', 422);
    }

    $id = (int)$m[1];

    $stmt = $mysqli->prepare(
        'SELECT id FROM items WHERE id = ? AND is_active = 1'
    );

    $stmt->bind_param('i', $id);
    $stmt->execute();

    if (!$stmt->get_result()->fetch_assoc()) {
        json_error('Item not found.', 404);
    }

    return $id;
}

// GET: Retrieve items
if ($method === 'GET') {
    $result = $mysqli->query(
        'SELECT id, name, sku, category, unit, price,
                cost_price, stock, low_stock, description
         FROM items
         WHERE is_active = 1
         ORDER BY id DESC'
    );

    $rows = $result->fetch_all(MYSQLI_ASSOC);

    json_out([
        'ok' => true,
        'items' => array_map('item_out', $rows)
    ]);
}

// All write operations require CSRF protection.
require_csrf();

// POST: Create item
if ($method === 'POST') {
    $v = item_input(read_json());
    $sku = $v['sku'] === '' ? null : $v['sku'];

    try {
        $stmt = $mysqli->prepare(
            'INSERT INTO items
            (sku, name, category, unit, price, cost_price,
             stock, low_stock, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->bind_param(
            'ssssddiis',
            $sku,
            $v['name'],
            $v['category'],
            $v['unit'],
            $v['price'],
            $v['costPrice'],
            $v['stock'],
            $v['lowStock'],
            $v['description']
        );

        $stmt->execute();

    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() === 1062) {
            json_error('This SKU already exists.', 409);
        }

        throw $e;
    }

    $id = (int)$mysqli->insert_id;
    $code = 'ITM-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);

    audit('create', 'item', $code, $v['name']);

    $stmt = $mysqli->prepare(
        'SELECT id, name, sku, category, unit, price,
                cost_price, stock, low_stock, description
         FROM items WHERE id = ?'
    );

    $stmt->bind_param('i', $id);
    $stmt->execute();

    json_out([
        'ok' => true,
        'item' => item_out($stmt->get_result()->fetch_assoc())
    ], 201);
}

// Identify item for PUT or DELETE.
$id = item_find_id(trim((string)($_GET['id'] ?? '')));

// PUT: Update item
if ($method === 'PUT') {
    $v = item_input(read_json());
    $sku = $v['sku'] === '' ? null : $v['sku'];

    try {
        $stmt = $mysqli->prepare(
            'UPDATE items SET
                sku = ?, name = ?, category = ?, unit = ?,
                price = ?, cost_price = ?, stock = ?,
                low_stock = ?, description = ?
             WHERE id = ?'
        );

        $stmt->bind_param(
            'ssssddiisi',
            $sku,
            $v['name'],
            $v['category'],
            $v['unit'],
            $v['price'],
            $v['costPrice'],
            $v['stock'],
            $v['lowStock'],
            $v['description'],
            $id
        );

        $stmt->execute();

    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() === 1062) {
            json_error('This SKU already exists.', 409);
        }

        throw $e;
    }

    audit(
        'update',
        'item',
        trim((string)($_GET['id'] ?? '')),
        $v['name']
    );

    $stmt = $mysqli->prepare(
        'SELECT id, name, sku, category, unit, price,
                cost_price, stock, low_stock, description
         FROM items WHERE id = ?'
    );

    $stmt->bind_param('i', $id);
    $stmt->execute();

    json_out([
        'ok' => true,
        'item' => item_out($stmt->get_result()->fetch_assoc())
    ]);
}

// DELETE: Soft-delete item
if ($method === 'DELETE') {
    $stmt = $mysqli->prepare(
        'UPDATE items SET is_active = 0 WHERE id = ?'
    );

    $stmt->bind_param('i', $id);
    $stmt->execute();

    audit(
        'delete',
        'item',
        trim((string)($_GET['id'] ?? '')),
        null
    );

    json_out(['ok' => true]);
}

json_error('Method not allowed.', 405);
