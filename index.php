<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$dsn = "mysql:host=localhost;dbname=YOUR_DB;charset=utf8mb4";
$user = "YOUR_USER";
$pass = "YOUR_PASS";

try {
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["error" => "DB connection failed"]);
  exit;
}

$resource = $_GET['resource'] ?? '';

if ($resource === 'suppliers') {
  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("
      SELECT
        id,
        supplier_name AS name,
        contact,
        email,
        address,
        supplier_type,
        NULL AS contact_person,
        created_at
      FROM Suppliers
      ORDER BY id DESC
    ");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
  }
  http_response_code(405);
  echo json_encode(["error" => "Method not allowed"]);
  exit;
}

if ($resource === 'shelters') {
  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("
      SELECT shelter_id, shelter_name
      FROM Shelters
      ORDER BY shelter_id DESC
    ");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
  }
  http_response_code(405);
  echo json_encode(["error" => "Method not allowed"]);
  exit;
}

if ($resource === 'items') {
  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("
      SELECT
        i.item_id,
        i.item_name AS name,
        i.item_type AS category,
        i.item_quantity AS quantity,
        i.unit,
        i.min_stock,
        i.expiry_date,
        latest.shelter_id,
        latest.shelter_name
      FROM Items i
      LEFT JOIN (
        SELECT
          t.item_name,
          t.shelter_id,
          t.shelter_name
        FROM (
          SELECT
            il.item AS item_name,
            il.shelter_name,
            s.shelter_id,
            il.transaction_id,
            ROW_NUMBER() OVER (PARTITION BY il.item ORDER BY il.transaction_id DESC) AS rn
          FROM InventoryLogs il
          LEFT JOIN Shelters s ON s.shelter_name = il.shelter_name
        ) t
        WHERE t.rn = 1
      ) latest
        ON latest.item_name = i.item_name
      ORDER BY i.item_id DESC
    ");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    $name = trim((string)($data['name'] ?? ''));
    $category = trim((string)($data['category'] ?? ''));
    $unit = trim((string)($data['unit'] ?? ''));
    $quantity = (int)($data['quantity'] ?? 0);
    $min_stock = (int)($data['min_stock'] ?? 0);
    $expiry_date = $data['expiry_date'] ?? null;
    if ($expiry_date === '') $expiry_date = null;

    $shelter_id = $data['shelter_id'] ?? null;

    if ($name === '' || $category === '' || $unit === '') {
      http_response_code(400);
      echo json_encode(["error" => "Missing required fields: name, category, unit"]);
      exit;
    }

    $stmt = $pdo->prepare("
      INSERT INTO Items (item_name, item_type, item_quantity, unit, min_stock, expiry_date)
      VALUES (:name, :category, :quantity, :unit, :min_stock, :expiry_date)
    ");
    $stmt->execute([
      ':name' => $name,
      ':category' => $category,
      ':quantity' => $quantity,
      ':unit' => $unit,
      ':min_stock' => $min_stock,
      ':expiry_date' => $expiry_date
    ]);

    $newId = (int)$pdo->lastInsertId();

    // Inventory log (only if shelter_id supplied)
    if ($shelter_id !== null && $shelter_id !== '') {
      $sh = $pdo->prepare("SELECT shelter_name FROM Shelters WHERE shelter_id = :id");
      $sh->execute([':id' => $shelter_id]);
      $shelter = $sh->fetch(PDO::FETCH_ASSOC);

      if ($shelter) {
        $personnel = 'Admin';
        $qty = (int)$quantity;
        if ($qty < 0) $qty = 0; // ensure positive for IN

        $log = $pdo->prepare("
          INSERT INTO InventoryLogs (transaction_date, item, quantity, transaction_type, personnel, shelter_name)
          VALUES (CURDATE(), :item, :qty, 'IN', :personnel, :shelter_name)
        ");
        $log->execute([
          ':item' => $name,
          ':qty' => $qty,
          ':personnel' => $personnel,
          ':shelter_name' => $shelter['shelter_name']
        ]);
      }
    }

    echo json_encode(["ok" => true, "item_id" => $newId]);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents("php://input"), true);

    $id = $_GET['id'] ?? null;
    if ($id === null) {
      http_response_code(400);
      echo json_encode(["error" => "Missing id"]);
      exit;
    }
    $id = (int)$id;

    $name = trim((string)($data['name'] ?? ''));
    $category = trim((string)($data['category'] ?? ''));
    $unit = trim((string)($data['unit'] ?? ''));
    $quantity = (int)($data['quantity'] ?? 0);
    $min_stock = (int)($data['min_stock'] ?? 0);
    $expiry_date = $data['expiry_date'] ?? null;
    if ($expiry_date === '') $expiry_date = null;

    $shelter_id = $data['shelter_id'] ?? null;

    if ($name === '' || $category === '' || $unit === '') {
      http_response_code(400);
      echo json_encode(["error" => "Missing required fields: name, category, unit"]);
      exit;
    }

    $old = $pdo->prepare("SELECT item_name, item_quantity FROM Items WHERE item_id = :id");
    $old->execute([':id' => $id]);
    $oldRow = $old->fetch(PDO::FETCH_ASSOC);

    if (!$oldRow) {
      http_response_code(404);
      echo json_encode(["error" => "Item not found"]);
      exit;
    }

    $oldName = $oldRow['item_name'];
    $oldQty = (int)$oldRow['item_quantity'];
    $delta = $quantity - $oldQty;

    $stmt = $pdo->prepare("
      UPDATE Items
      SET item_name = :name,
          item_type = :category,
          item_quantity = :quantity,
          unit = :unit,
          min_stock = :min_stock,
          expiry_date = :expiry_date
      WHERE item_id = :id
    ");
    $stmt->execute([
      ':name' => $name,
      ':category' => $category,
      ':quantity' => $quantity,
      ':unit' => $unit,
      ':min_stock' => $min_stock,
      ':expiry_date' => $expiry_date,
      ':id' => $id
    ]);

    // Inventory log (only if shelter_id supplied)
    if ($shelter_id !== null && $shelter_id !== '') {
      $sh = $pdo->prepare("SELECT shelter_name FROM Shelters WHERE shelter_id = :id");
      $sh->execute([':id' => $shelter_id]);
      $shelter = $sh->fetch(PDO::FETCH_ASSOC);

      if ($shelter && $delta !== 0) {
        $personnel = 'Admin';
        $type = ($delta >= 0) ? 'IN' : 'OUT';
        $qtyAbs = abs($delta);

        // Logs store "item" as string; use the old name to preserve continuity if frontend expects item-by-name history.
        // If you prefer continuity under the updated name, change $logItem to $name.
        $logItem = $oldName;

        $log = $pdo->prepare("
          INSERT INTO InventoryLogs (transaction_date, item, quantity, transaction_type, personnel, shelter_name)
          VALUES (CURDATE(), :item, :qty, :ttype, :personnel, :shelter_name)
        ");
        $log->execute([
          ':item' => $logItem,
          ':qty' => $qtyAbs,
          ':ttype' => $type,
          ':personnel' => $personnel,
          ':shelter_name' => $shelter['shelter_name']
        ]);
      }
    }

    echo json_encode(["ok" => true, "item_id" => $id]);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if ($id === null) {
      http_response_code(400);
      echo json_encode(["error" => "Missing id"]);
      exit;
    }
    $id = (int)$id;

    $stmt = $pdo->prepare("DELETE FROM Items WHERE item_id = :id");
    $stmt->execute([':id' => $id]);

    echo json_encode(["ok" => true]);
    exit;
  }

  http_response_code(405);
  echo json_encode(["error" => "Method not allowed"]);
  exit;
}

if ($resource === 'stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
  $tot = $pdo->query("SELECT COUNT(*) AS total_items, SUM(item_quantity) AS total_quantity FROM Items")
             ->fetch(PDO::FETCH_ASSOC);

  $low_stock_count = (int)$pdo->query("
    SELECT COUNT(*)
    FROM Items
    WHERE item_quantity <= min_stock
  ")->fetchColumn();

  $expired_count = (int)$pdo->query("
    SELECT COUNT(*)
    FROM Items
    WHERE expiry_date IS NOT NULL
      AND expiry_date < CURDATE()
  ")->fetchColumn();

  $don = $pdo->query("SELECT COUNT(*) AS total_donations FROM Donations")->fetch(PDO::FETCH_ASSOC);

  $byCat = [];
  $stmt = $pdo->query("
    SELECT item_type AS category,
           COUNT(*) AS count,
           SUM(item_quantity) AS total_qty
    FROM Items
    GROUP BY item_type
  ");
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $byCat[] = [
      "category" => $row["category"],
      "count" => (int)$row["count"],
      "total_qty" => (int)$row["total_qty"]
    ];
  }

  $recent = [];
  $stmt = $pdo->query("
    SELECT
      transaction_type AS action,
      quantity,
      item,
      transaction_date AS logged_at
    FROM InventoryLogs
    ORDER BY transaction_id DESC
    LIMIT 10
  ");
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $recent[] = [
      "action" => $row["action"],
      "quantity" => (int)$row["quantity"],
      "item_name" => $row["item"],
      "logged_at" => $row["logged_at"]
    ];
  }

  $total_suppliers = (int)$pdo->query("SELECT COUNT(*) AS c FROM Suppliers")
                             ->fetch(PDO::FETCH_ASSOC)["c"];

  $out = [
    "total_items" => (int)($tot["total_items"] ?? 0),
    "total_quantity" => (int)($tot["total_quantity"] ?? 0),
    "low_stock_count" => $low_stock_count,
    "expired_count" => $expired_count,
    "total_donations" => (int)($don["total_donations"] ?? 0),
    "total_suppliers" => $total_suppliers,
    "by_category" => $byCat,
    "recent_logs" => $recent
  ];

  echo json_encode($out);
  exit;
}

if ($resource === 'alerts' && $_SERVER['REQUEST_METHOD'] === 'GET') {
  $latestShelterSql = "
    SELECT t.item_name, t.shelter_name
    FROM (
      SELECT
        il.item AS item_name,
        il.shelter_name,
        il.transaction_id,
        ROW_NUMBER() OVER (PARTITION BY il.item ORDER BY il.transaction_id DESC) AS rn
      FROM InventoryLogs il
    ) t
    WHERE t.rn = 1
  ";

  $low_stock = $pdo->query("
    SELECT item_name AS name, item_quantity AS quantity, min_stock
    FROM Items
    WHERE item_quantity <= min_stock
  ")->fetchAll(PDO::FETCH_ASSOC);

  $expired = $pdo->query("
    SELECT
      i.item_name AS name,
      ls.shelter_name
    FROM Items i
    JOIN ( $latestShelterSql ) ls ON ls.item_name = i.item_name
    WHERE i.expiry_date IS NOT NULL
      AND i.expiry_date < CURDATE()
  ")->fetchAll(PDO::FETCH_ASSOC);

  $expiring_soon = $pdo->query("
    SELECT
      i.item_name AS name,
      i.expiry_date,
      ls.shelter_name
    FROM Items i
    JOIN ( $latestShelterSql ) ls ON ls.item_name = i.item_name
    WHERE i.expiry_date IS NOT NULL
      AND i.expiry_date >= CURDATE()
      AND i.expiry_date <= (CURDATE() + INTERVAL 30 DAY)
  ")->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    "low_stock" => array_map(function($r){
      return [
        "name" => $r["name"],
        "quantity" => (int)$r["quantity"],
        "min_stock" => (int)$r["min_stock"]
      ];
    }, $low_stock),
    "expired" => array_map(function($r){
      return [
        "name" => $r["name"],
        "shelter_name" => $r["shelter_name"]
      ];
    }, $expired),
    "expiring_soon" => array_map(function($r){
      return [
        "name" => $r["name"],
        "expiry_date" => $r["expiry_date"],
        "shelter_name" => $r["shelter_name"]
      ];
    }, $expiring_soon)
  ]);
  exit;
}

// Create supplier (POST without resource)
if ($resource === '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $data = json_decode(file_get_contents("php://input"), true);

  $name = trim((string)($data['name'] ?? ''));
  $contact = isset($data['contact']) ? trim((string)$data['contact']) : null;
  $email = isset($data['email']) ? trim((string)$data['email']) : null;
  $address = isset($data['address']) ? trim((string)$data['address']) : null;

  if ($name === '') {
    http_response_code(400);
    echo json_encode(["error" => "Supplier name is required"]);
    exit;
  }

  $stmt = $pdo->prepare("
    INSERT INTO Suppliers (supplier_name, contact, email, address, supplier_type)
    VALUES (:name, :contact, :email, :address, :supplier_type)
  ");
  $stmt->execute([
    ':name' => $name,
    ':contact' => ($contact === '' ? null : $contact),
    ':email' => ($email === '' ? null : $email),
    ':address' => ($address === '' ? null : $address),
    ':supplier_type' => (string)($data['supplier_type'] ?? 'General')
  ]);

  echo json_encode(["ok" => true, "id" => $pdo->lastInsertId()]);
  exit;
}

http_response_code(404);
echo json_encode(["error" => "Not found"]);
?>
