<?php
header('Content-Type: application/json');
require_once 'db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $db = getDB();

    switch ($action) {

        // ── Toggle a single item completed ──────────────────────────────
        case 'toggle_item':
            $itemId = (int)($_POST['item_id'] ?? 0);
            $completed = (int)($_POST['completed'] ?? 0);
            $stmt = $db->prepare("UPDATE order_items SET completed = ? WHERE id = ?");
            $stmt->execute([$completed, $itemId]);

            // Check if ALL items for this order are now complete
            $stmt2 = $db->prepare(
                "SELECT order_id FROM order_items WHERE id = ?"
            );
            $stmt2->execute([$itemId]);
            $row = $stmt2->fetch(PDO::FETCH_ASSOC);
            $orderId = $row['order_id'];

            $total     = $db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ?");
            $total->execute([$orderId]);
            $done      = $db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ? AND completed = 1");
            $done->execute([$orderId]);

            echo json_encode([
                'success'    => true,
                'total'      => (int)$total->fetchColumn(),
                'completed'  => (int)$done->fetchColumn(),
                'order_id'   => $orderId,
            ]);
            break;

        // ── Complete an entire order ─────────────────────────────────────
        case 'complete_order':
            $orderId = (int)($_POST['order_id'] ?? 0);
            $db->prepare("UPDATE orders SET status = 'complete' WHERE id = ?")->execute([$orderId]);
            echo json_encode(['success' => true]);
            break;

        // ── Delete / cancel an order ─────────────────────────────────────
        case 'delete_order':
            $orderId = (int)($_POST['order_id'] ?? 0);
            $db->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
            $db->prepare("DELETE FROM orders WHERE id = ?")->execute([$orderId]);
            echo json_encode(['success' => true]);
            break;

        // ── Remove a single item from an order ───────────────────────────
        case 'remove_order_item':
            $itemId  = (int)($_POST['item_id']  ?? 0);
            $orderId = (int)($_POST['order_id'] ?? 0);
            $db->prepare("DELETE FROM order_items WHERE id = ? AND order_id = ?")->execute([$itemId, $orderId]);
            $total = $db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ?");
            $total->execute([$orderId]);
            $done  = $db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ? AND completed = 1");
            $done->execute([$orderId]);
            echo json_encode([
                'success'   => true,
                'total'     => (int)$total->fetchColumn(),
                'completed' => (int)$done->fetchColumn(),
            ]);
            break;

        // ── Fetch live order queue for employee dashboard ────────────────
        case 'get_orders':
            $stmt = $db->query(
                "SELECT o.id, o.name, o.adults, o.children, o.week_date, o.notes,
                        o.created_at, o.status,
                        COUNT(oi.id) as total_items,
                        SUM(oi.completed) as completed_items
                 FROM orders o
                 LEFT JOIN order_items oi ON oi.order_id = o.id
                 WHERE o.status = 'pending'
                 GROUP BY o.id
                 ORDER BY o.created_at ASC"
            );
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($orders as &$order) {
                $istmt = $db->prepare(
                    "SELECT id, category, item_name, item_detail, completed
                     FROM order_items WHERE order_id = ?
                     ORDER BY category, id"
                );
                $istmt->execute([$order['id']]);
                $order['items'] = $istmt->fetchAll(PDO::FETCH_ASSOC);
            }

            echo json_encode(['success' => true, 'orders' => $orders]);
            break;

        // ── Admin: get config items ──────────────────────────────────────
        case 'get_config':
            $stmt = $db->query(
                "SELECT * FROM config_items ORDER BY category, sort_order, id"
            );
            echo json_encode(['success' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // ── Admin: save config items ─────────────────────────────────────
        case 'save_config':
            $items = json_decode(file_get_contents('php://input'), true)['items'] ?? [];
            $db->exec("DELETE FROM config_items");
            $stmt = $db->prepare(
                "INSERT INTO config_items (category, item_name, has_detail, detail_label, active, sort_order, unavailable, size_options)
                 VALUES (:category, :item_name, :has_detail, :detail_label, :active, :sort_order, :unavailable, :size_options)"
            );
            foreach ($items as $i => $item) {
                $stmt->execute([
                    ':category'    => $item['category'],
                    ':item_name'   => $item['item_name'],
                    ':has_detail'  => (int)($item['has_detail'] ?? 0),
                    ':detail_label'=> $item['detail_label'] ?? '',
                    ':active'      => (int)($item['active'] ?? 1),
                    ':sort_order'  => $i,
                    ':unavailable' => (int)($item['unavailable'] ?? 0),
                    ':size_options'=> $item['size_options'] ?? '',
                ]);
            }
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
