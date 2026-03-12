<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$name     = trim($_POST['name'] ?? '');
$adults   = max(0, (int)($_POST['adults']   ?? 0));
$children = max(0, (int)($_POST['children'] ?? 0));
$notes    = trim($_POST['notes'] ?? '');
$weekDate = trim($_POST['week_date'] ?? '');

if ($name === '') {
    header('Location: index.php?error=name');
    exit;
}

$db = getDB();

// Insert order
$stmt = $db->prepare(
    "INSERT INTO orders (name, adults, children, week_date, notes)
     VALUES (:name, :adults, :children, :week_date, :notes)"
);
$stmt->execute([
    ':name'      => $name,
    ':adults'    => $adults,
    ':children'  => $children,
    ':week_date' => $weekDate,
    ':notes'     => $notes,
]);
$orderId = $db->lastInsertId();

// Gather selected items
$configStmt = $db->query(
    "SELECT * FROM config_items WHERE active = 1 ORDER BY category, sort_order"
);
$configItems = $configStmt->fetchAll(PDO::FETCH_ASSOC);

$insertItem = $db->prepare(
    "INSERT INTO order_items (order_id, category, item_name, item_detail)
     VALUES (:order_id, :category, :item_name, :item_detail)"
);

foreach ($configItems as $cfg) {
    $key = 'item_' . $cfg['id'];
    if (!empty($_POST[$key])) {
        $detail = '';
        if ($cfg['has_detail']) {
            $detailKey = 'detail_' . $cfg['id'];
            $detail = trim($_POST[$detailKey] ?? '');
        }
        $insertItem->execute([
            ':order_id'   => $orderId,
            ':category'   => $cfg['category'],
            ':item_name'  => $cfg['item_name'],
            ':item_detail'=> $detail,
        ]);
    }
}

header('Location: index.php?success=1&order_id=' . $orderId);
exit;
