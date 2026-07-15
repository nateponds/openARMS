<?php
require_once __DIR__ . "/../config/db_config.php";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

function h($s) { return htmlspecialchars($s ?? "", ENT_QUOTES, "UTF-8"); }

$shelters = $conn->query("SELECT shelter_id, shelter_name FROM Shelters ORDER BY shelter_id DESC");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    $item_id = (int)($_POST["item_id"] ?? 0);

    $shelter_id = (int)($_POST["shelter_id"] ?? 0);
    $item_name = trim($_POST["item_name"] ?? "");
    $item_type = trim($_POST["item_type"] ?? "");
    $unit = trim($_POST["unit"] ?? "");
    $active = isset($_POST["active"]) ? 1 : 0;

    $received_date = trim($_POST["received_date"] ?? "");
    $expiry_date = trim($_POST["expiry_date"] ?? "");
    $expiry_date_param = ($expiry_date === "") ? null : $expiry_date;

    $notes = trim($_POST["notes"] ?? "");
    $notes_param = ($notes === "") ? null : $notes;

    $initial_qty = trim($_POST["initial_qty"] ?? "");
    if ($initial_qty === "") $initial_qty = "0";

    $on_hand_qty = trim($_POST["on_hand_qty"] ?? "");
    if ($on_hand_qty === "") $on_hand_qty = $initial_qty;

    if ($action === "add") {
        $stmt = $conn->prepare("
        INSERT INTO Items
        (shelter_id, item_name, item_type, unit, active, received_date, expiry_date, notes, initial_qty, on_hand_qty)
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) { die("Prepare failed (add): " . $conn->error); }
        $stmt->bind_param(
            "isssisssdd",
            $shelter_id,
            $item_name,
            $item_type,
            $unit,
            $active,
            $received_date,
            $expiry_date_param,
            $notes_param,
            $initial_qty,
            $on_hand_qty
        );
        if (!$stmt->execute()) { die("Execute failed (add): " . $stmt->error); }
        $stmt->close();

    } elseif ($action === "update") {
        $stmt = $conn->prepare("
        UPDATE Items
        SET shelter_id = ?,
        item_name = ?,
        item_type = ?,
        unit = ?,
        active = ?,
        received_date = ?,
        expiry_date = ?,
        notes = ?,
        initial_qty = ?,
        on_hand_qty = ?
        WHERE item_id = ?
        ");
        if (!$stmt) { die("Prepare failed (update): " . $conn->error); }
        $stmt->bind_param(
            "isssisssdd",
            $shelter_id,
            $item_name,
            $item_type,
            $unit,
            $active,
            $received_date,
            $expiry_date_param,
            $notes_param,
            $initial_qty,
            $on_hand_qty,
            $item_id
        );
        if (!$stmt->execute()) { die("Execute failed (update): " . $stmt->error); }
        $stmt->close();

    } elseif ($action === "delete") {
        $stmt = $conn->prepare("DELETE FROM Items WHERE item_id = ?");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $stmt->close();
    }

    //header("Location: " . strtok($_SERVER["REQUEST_URI"], "?"));
    //exit;
}

$editItem = null;
if (isset($_GET["edit"])) {
    $editId = (int)$_GET["edit"];
    $stmt = $conn->prepare("
    SELECT
    item_id, shelter_id,
    item_name, item_type, unit, active,
    received_date, expiry_date, notes,
    initial_qty, on_hand_qty,
    created_at, updated_at
    FROM Items
    WHERE item_id = ?
    ");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editItem = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$result = $conn->query("
SELECT
i.item_id, i.shelter_id,
i.item_name, i.item_type, i.unit, i.active,
i.received_date, i.expiry_date, i.notes,
i.initial_qty, i.on_hand_qty,
i.created_at, i.updated_at
FROM Items i
ORDER BY i.item_id DESC
");
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<title>Items</title>
<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; margin-top: 16px; }
th, td { border: 1px solid #ddd; padding: 8px; }
th { background: #f4f4f4; }
form { margin: 0; }
.row { margin-bottom: 10px; }
input[type="text"], input[type="number"], input[type="date"], select { padding: 6px; width: 100%; max-width: 420px; }
.actions button { padding: 6px 10px; margin-right: 6px; }
.card { border: 1px solid #ddd; padding: 14px; border-radius: 6px; max-width: 980px; }
.small-muted { color: #666; font-size: 12px; margin-top: 6px; }
</style>
</head>
<body>
<h2>Items</h2>

<div class="card">
<h3><?= $editItem ? "Edit Item Instance" : "Add Item Instance" ?></h3>

<form method="post" action="inventory.php">
<input type="hidden" name="action" value="<?= $editItem ? "update" : "add" ?>">
<?php if ($editItem): ?>
<input type="hidden" name="item_id" value="<?= (int)$editItem["item_id"] ?>">
<?php endif; ?>

<div class="row">
<label>Shelter</label><br>
<select name="shelter_id" required>
<option value="">-- Select Shelter --</option>
<?php while ($srow = $shelters->fetch_assoc()): ?>
<?php $sid = (int)$srow["shelter_id"]; ?>
<option value="<?= $sid ?>" <?= ((int)($editItem["shelter_id"] ?? 0) === $sid) ? "selected" : "" ?>>
<?= h($srow["shelter_name"]) ?> (<?= $sid ?>)
</option>
<?php endwhile; ?>
</select>
</div>

<div class="row">
<label>Item Name</label><br>
<input type="text" name="item_name" required value="<?= h($editItem["item_name"] ?? "") ?>">
</div>

<div class="row">
<label>Item Type</label><br>
<input type="text" name="item_type" required value="<?= h($editItem["item_type"] ?? "") ?>">
</div>

<div class="row">
<label>Unit</label><br>
<input type="text" name="unit" required value="<?= h($editItem["unit"] ?? "") ?>">
</div>

<div class="row">
<label>
<input type="checkbox" name="active" value="1" <?= (int)($editItem["active"] ?? 1) === 1 ? "checked" : "" ?>>
Active
</label>
</div>

<div class="row">
<label>Received Date</label><br>
<input type="date" name="received_date" required value="<?= h($editItem["received_date"] ?? "") ?>">
</div>

<div class="row">
<label>Expiry Date (optional)</label><br>
<input type="date" name="expiry_date" value="<?= h($editItem["expiry_date"] ?? "") ?>">
</div>

<div class="row">
<label>Notes (optional)</label><br>
<input type="text" name="notes" value="<?= h($editItem["notes"] ?? "") ?>">
</div>

<div class="row">
<label>Initial Quantity</label><br>
<input type="number" step="0.001" name="initial_qty" required value="<?= h($editItem["initial_qty"] ?? "0") ?>">
</div>

<div class="row">
<label>On-hand Quantity</label><br>
<input type="number" step="0.001" name="on_hand_qty" required value="<?= h($editItem["on_hand_qty"] ?? ($editItem["initial_qty"] ?? "0")) ?>">
<div class="small-muted">Tip: on-hand can be set equal to initial quantity for new receipts.</div>
</div>

<div class="actions">
<button type="submit"><?= $editItem ? "Save Changes" : "Add Item" ?></button>
<?php if ($editItem): ?>
<a href="inventory.php"><button type="button">Cancel</button></a>
<?php endif; ?>
</div>
</form>
</div>

<table>
<thead>
<tr>
<th>ID</th>
<th>Shelter ID</th>
<th>Name</th>
<th>Type</th>
<th>Unit</th>
<th>Active</th>
<th>Received</th>
<th>Expiry</th>
<th>Condition</th>
<th>Initial Qty</th>
<th>On-hand Qty</th>
<th>Created</th>
<th>Updated</th>
<th>Operations</th>
</tr>
</thead>
<tbody>
<?php while ($row = $result->fetch_assoc()): ?>
<tr>
<td><?= (int)$row["item_id"] ?></td>
<td><?= (int)$row["shelter_id"] ?></td>
<td><?= h($row["item_name"]) ?></td>
<td><?= h($row["item_type"]) ?></td>
<td><?= h($row["unit"]) ?></td>
<td><?= ((int)$row["active"] === 1) ? "Yes" : "No" ?></td>
<td><?= h($row["received_date"]) ?></td>
<td><?= h($row["expiry_date"]) ?></td>
<td><?= h($row["notes"]) ?></td>
<td><?= h($row["initial_qty"]) ?></td>
<td><?= h($row["on_hand_qty"]) ?></td>
<td><?= h($row["created_at"]) ?></td>
<td><?= h($row["updated_at"]) ?></td>
<td>
<a href="inventory.php?edit=<?= (int)$row["item_id"] ?>">Edit</a>
<form method="post" action="inventory.php" style="display:inline;">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="item_id" value="<?= (int)$row["item_id"] ?>">
<button type="submit" onclick="return confirm('Delete item #<?= (int)$row["item_id"] ?>?');">Delete</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

</body>
</html>
