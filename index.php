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
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["error" => "DB connection failed"]);
  exit;
}

$resource = $_GET['resource'] ?? '';
if ($resource !== 'suppliers') {
  http_response_code(404);
  echo json_encode(["error" => "Not found"]);
  exit;
}

if ($resource === 'suppliers') {if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $stmt = $pdo->query("
  SELECT supplier_id AS id,
  supplier_name AS name,
  email,
  address,
  supplier_type,
  NULL AS contact,
  NOW() AS created_at
  FROM Suppliers
  ORDER BY supplier_id DESC
  ");
  echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $raw = file_get_contents("php://input");
  $data = json_decode($raw, true);

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
  INSERT INTO Suppliers (supplier_name, email, address, supplier_type)
  VALUES (:name, :email, :address, :supplier_type)
  ");
  $stmt->execute([
    ':name' => $name,
    ':email' => ($email === '') ? null : $email,
                 ':address' => ($address === '') ? null : $address,
                 ':supplier_type' => $data['supplier_type'] ?? 'N/A'
  ]);


  echo json_encode(["ok" => true, "id" => $pdo->lastInsertId()]);
  exit;
}
  exit;
}

if ($resource === 'stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {

  $stmt = $pdo->query("SELECT COUNT(*) AS total_items,
                      SUM(item_quantity) AS total_quantity
                      FROM Items");
  $tot = $stmt->fetch(PDO::FETCH_ASSOC);

  //change
  $low_stock_count = 0;
  $expired_count = 0;

  //change
  $stmt = $pdo->query("SELECT COUNT(*) AS total_donations FROM Donations");
  $don = $stmt->fetch(PDO::FETCH_ASSOC);

  $byCat = [];
  $stmt = $pdo->query("SELECT item_type AS category,
                      COUNT(*) AS count,
                      SUM(item_quantity) AS total_qty
                      FROM Items
                      GROUP BY item_type");
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $byCat[] = [
      "category" => $row["category"],
      "count" => (int)$row["count"],
      "total_qty" => (int)$row["total_qty"]
    ];
  }

  $recent = [];
  $stmt = $pdo->query("SELECT transaction_type AS action,
                      quantity,
                      item,
                      transaction_date AS logged_at
                      FROM InventoryLogs
                      ORDER BY transaction_id DESC
                      LIMIT 10");
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $recent[] = [
      "action" => $row["action"],
      "quantity" => (int)$row["quantity"],
      "item_name" => $row["item"],
      "logged_at" => $row["logged_at"]
    ];
  }

  $out = [
    "total_items" => (int)($tot["total_items"] ?? 0),
    "total_quantity" => (int)($tot["total_quantity"] ?? 0),
    "low_stock_count" => (int)$low_stock_count,
    "expired_count" => (int)$expired_count,
    "total_donations" => (int)($don["total_donations"] ?? 0),
    "total_suppliers" => 0,
    "by_category" => $byCat,
    "recent_logs" => $recent
  ];

  $stmt = $pdo->query("SELECT COUNT(*) AS c FROM Suppliers");
  $sup = $stmt->fetch(PDO::FETCH_ASSOC);
  $out["total_suppliers"] = (int)($sup["c"] ?? 0);

  echo json_encode($out);
  exit;
}

if ($resource === 'alerts' && $_SERVER['REQUEST_METHOD'] === 'GET') {

  //change
  $low_stock = [];
  $expired = [];
  $expiring_soon = [];

  $stmt = $pdo->query("SELECT item_name, item_quantity FROM Items");
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
  }

  echo json_encode([
    "low_stock" => $low_stock,
    "expired" => $expired,
    "expiring_soon" => $expiring_soon
  ]);
  exit;
}

http_response_code(405);
echo json_encode(["error" => "Method not allowed"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard</title>
<link rel="stylesheet" href="index.css">
</head>
<body>

<div class="app">
<div id="sidebar-container"></div>
<main class="main">
<div class="topbar">
<div class="topbar-title">Dashboard</div>
<div class="topbar-right">
<span id="alert-count-badge" class="alert-badge" style="display:none">0 alerts</span>
<span style="font-size:13px;color:var(--gray-600);">Admin</span>
</div>
</div>

<div class="content">
<div class="stats-grid" id="stats-grid">
<div class="stat-card"><div class="stat-label">Total Items</div><div class="stat-value" id="s-items">–</div><div class="stat-sub">inventory types</div></div>
<div class="stat-card"><div class="stat-label">Total Qty</div><div class="stat-value" id="s-qty">–</div><div class="stat-sub">units in stock</div></div>
<div class="stat-card warning"><div class="stat-label">Low Stock</div><div class="stat-value" id="s-low">–</div><div class="stat-sub">items need restock</div></div>
<div class="stat-card danger"><div class="stat-label">Expired</div><div class="stat-value" id="s-exp">–</div><div class="stat-sub">items past expiry</div></div>
<div class="stat-card accent"><div class="stat-label">Donations</div><div class="stat-value" id="s-don">–</div><div class="stat-sub">total records</div></div>
<div class="stat-card"><div class="stat-label">Suppliers</div><div class="stat-value" id="s-sup">–</div><div class="stat-sub">registered vendors</div></div>
</div>

<div class="grid-2" style="margin-bottom:18px;">
<div class="card">
<div class="card-title">📦 Stock by Category</div>
<div id="category-breakdown"></div>
</div>
<div class="card">
<div class="card-title">⚡ Recent Activity</div>
<div id="recent-activity"></div>
</div>
</div>

<div class="card">
<div class="card-title">🚨 Active Alerts</div>
<div id="dashboard-alerts"></div>
</div>
</div>
</main>
</div>

<div id="toast"></div>

<script>

// Load sidebar
fetch('sidebar.html')
.then(r => r.text())
.then(html => {
  document.getElementById('sidebar-container').innerHTML = html;
  document.querySelectorAll('.nav-item')[0].classList.add('active');
});

function toast(msg, type='success') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'show ' + type;
  setTimeout(() => t.className = '', 2500);
}

function fmt(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-PH', {month:'short',day:'numeric',year:'numeric'});
}

async function api(resource, method='GET', body=null) {
  const opts = { method, headers: {'Content-Type':'application/json'} };
  if (body) opts.body = JSON.stringify(body);
  const url = `http://localhost/api/index.php?resource=${encodeURIComponent(resource)}`;
  const r = await fetch(url, opts);
  return r.json();
}


async function loadDashboard() {
  const [stats, alerts] = await Promise.all([
    api('stats'),
                                            api('alerts')
  ]);
  document.getElementById('s-items').textContent = stats.total_items;
  document.getElementById('s-qty').textContent = stats.total_quantity.toLocaleString();
  document.getElementById('s-low').textContent = stats.low_stock_count;
  document.getElementById('s-exp').textContent = stats.expired_count;
  document.getElementById('s-don').textContent = stats.total_donations;
  document.getElementById('s-sup').textContent = stats.total_suppliers;

  const totalAlerts = alerts.low_stock.length + alerts.expired.length + alerts.expiring_soon.length;
  const badge = document.getElementById('alert-count-badge');
  if (totalAlerts > 0) { badge.style.display=''; badge.textContent = totalAlerts + ' alerts'; }
  else badge.style.display = 'none';

  document.getElementById('category-breakdown').innerHTML = stats.by_category.map(c => `
  <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--gray-100);">
  <div style="display:flex;align-items:center;gap:10px;">
  <span class="badge badge-${c.category.toLowerCase()}">${c.category}</span>
  <span style="font-size:13px">${c.count} item types</span>
  </div>
  <strong style="font-size:14px">${c.total_qty.toLocaleString()} units</strong>
  </div>
  `).join('');

  document.getElementById('recent-activity').innerHTML = stats.recent_logs.map(l => `
  <div class="feed-item">
  <div class="feed-dot ${l.action.toLowerCase()}">${l.action === 'IN' ? '↓' : '↑'}</div>
  <div class="feed-body">
  <strong>${l.action === 'IN' ? '+' : '-'}${l.quantity} units — ${l.item_name}</strong>
  <time>${fmt(l.logged_at)}</time>
  </div>
  </div>
  `).join('');

  const allA = [
    ...alerts.expired.map(a => ({...a, type:'danger', msg:`<strong>${a.name}</strong> — expired (${a.shelter_name})`})),
    ...alerts.low_stock.map(a => ({...a, type:'warning', msg:`<strong>${a.name}</strong> — low stock: ${a.quantity} left (min ${a.min_stock})`})),
    ...alerts.expiring_soon.map(a => ({...a, type:'warning', msg:`<strong>${a.name}</strong> — expires ${fmt(a.expiry_date)} (${a.shelter_name})`}))
  ];
  document.getElementById('dashboard-alerts').innerHTML = allA.length ? allA.map(a => `
  <div class="alert-item">
  <div class="alert-dot ${a.type}"></div>
  <div class="alert-text">${a.msg}</div>
  </div>
  `).join('') : '<div class="empty-state"><div class="empty-icon">✅</div><p>No active alerts</p></div>';
}

loadDashboard();
</script>
</body>
</html>
