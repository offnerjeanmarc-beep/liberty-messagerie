<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/repo.php';

header('Content-Type: application/json; charset=utf-8');

if (($_GET['token'] ?? '') !== '7f5e0d9c6f4e4c6aa8b4db1fd9b9e8a2') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$beforeRows = db_all(
    'SELECT id, name, lodgify_property_id, lodgify_room_id, is_active
     FROM properties
     ORDER BY lodgify_property_id'
);

$before = [];
foreach ($beforeRows as $row) {
    $before[(string)$row['lodgify_property_id']] = $row;
}

$items = lodgify_list_properties(200);
foreach ($items as $item) {
    $roomId = $item['rooms'][0]['id'] ?? null;
    prop_ensure(
        (int)$item['id'],
        $roomId ? (int)$roomId : null,
        (string)$item['name']
    );
}

$afterRows = db_all(
    'SELECT id, name, lodgify_property_id, lodgify_room_id, is_active
     FROM properties
     ORDER BY name ASC'
);

$newRows = [];
foreach ($afterRows as $row) {
    if (!isset($before[(string)$row['lodgify_property_id']])) {
        $newRows[] = $row;
    }
}

echo json_encode(
    [
        'ok' => true,
        'lodgify_count' => count($items),
        'new_count' => count($newRows),
        'new_properties' => $newRows,
        'all_properties' => $afterRows,
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
);
