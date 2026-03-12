<?php
function getDB() {
    $dbPath = __DIR__ . '/picklist.db';
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA busy_timeout = 5000"); // wait up to 5 seconds before failing on lock
    $db->exec("PRAGMA foreign_keys = ON");

    $db->exec("CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        adults INTEGER DEFAULT 0,
        children INTEGER DEFAULT 0,
        week_date TEXT,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        status TEXT DEFAULT 'pending'
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL,
        category TEXT NOT NULL,
        item_name TEXT NOT NULL,
        item_detail TEXT DEFAULT '',
        completed INTEGER DEFAULT 0,
        FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS config_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category TEXT NOT NULL,
        item_name TEXT NOT NULL,
        has_detail INTEGER DEFAULT 0,
        detail_label TEXT DEFAULT '',
        active INTEGER DEFAULT 1,
        sort_order INTEGER DEFAULT 0
    )");

    // Seed default items if table is empty
    $count = $db->query("SELECT COUNT(*) FROM config_items")->fetchColumn();
    if ($count == 0) {
        $items = [
            ['DAIRY',        'Salted Butter',               0, '',     1, 1],
            ['DAIRY',        'Unsalted Butter',              0, '',     1, 2],
            ['DAIRY',        'Eggs',                        0, '',     1, 3],
            ['DRY GOODS',    'Canned Tuna',                 0, '',     1, 1],
            ['DRY GOODS',    'Canned Chicken',              0, '',     1, 2],
            ['DRY GOODS',    'Almond Milk',                 0, '',     1, 3],
            ['DRY GOODS',    "Kid's Snacks (16 and Under)", 0, '',     1, 4],
            ['FROZEN ITEMS', 'Ground Beef',                 0, '',     1, 1],
            ['FROZEN ITEMS', 'Fish Nuggets',                0, '',     1, 2],
            ['FROZEN ITEMS', 'Whole Turkey',                0, '',     1, 3],
            ['SPECIALS',     'Coffee',                      0, '',     1, 1],
            ['SPECIALS',     'Tea',                         0, '',     1, 2],
            ['OTHER ITEMS',  'Diapers (Child)',             1, 'Size', 1, 1],
            ['OTHER ITEMS',  'Diapers (Adult) Male/Female', 1, 'Size', 1, 2],
        ];
        $stmt = $db->prepare(
            "INSERT INTO config_items (category, item_name, has_detail, detail_label, active, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        foreach ($items as $row) {
            $stmt->execute($row);
        }
    }

    return $db;
}
