<?php
require_once __DIR__ . "/db_config.php";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

function h($s) { return htmlspecialchars($s ?? "", ENT_QUOTES, "UTF-8"); }

function toFloat($v) {
    $v = trim((string)$v);
    if ($v === '') return 0.0;
    return (float)$v;
}

function fetchItemsByShelter($conn, $shelter_id) {
    $stmt = $conn->prepare("
    SELECT item_id, item_name, unit
    FROM Items
    WHERE shelter_id = ? AND active = 1
    ORDER BY item_id DESC
    ");
    $stmt->bind_param("i", $shelter_id);
    $stmt->execute();

    $stmt->bind_result($item_id, $item_name, $unit);

    $rows = [];
    while ($stmt->fetch()) {
        $rows[] = [
            "item_id" => $item_id,
            "item_name" => $item_name,
            "unit" => $unit
        ];
    }

    $stmt->close();
    return $rows;
}

$shelters = $conn->query("SELECT shelter_id, shelter_name FROM Shelters ORDER BY shelter_id DESC");
$personnel = $conn->query("SELECT personnel_id, personnel_name FROM Personnel ORDER BY personnel_id DESC");

$editMovement = null;
$movement_to_edit = isset($_GET["edit"]) ? (int)$_GET["edit"] : 0;

if ($movement_to_edit > 0) {
    $stmt = $conn->prepare("
        SELECT
            transaction_id, transaction_date,
            item_id, shelter_id,
            quantity, transaction_type,
            personnel_id, transaction_notes,
            created_at
        FROM InventoryLogs
        WHERE transaction_id = ?
    ");
    $stmt->bind_param("i", $movement_to_edit);
    $stmt->execute();
    $editMovement = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "reload_items") {
        $itemsForForm = [];

        if (isset($_POST["shelter_id"])) {
            $itemsForForm = fetchItemsByShelter($conn, (int)$_POST["shelter_id"]);
        }

        goto render_page;
    }

    $transaction_type = $_POST["transaction_type"] ?? "";

    $transaction_date = trim($_POST["transaction_date"] ?? "");
    $quantity = toFloat($_POST["quantity"] ?? "0");

    $personnel_id = (int)($_POST["personnel_id"] ?? 0);
    $transaction_notes = trim($_POST["transaction_notes"] ?? "");
    $transaction_notes_param = ($transaction_notes === "") ? null : $transaction_notes;

    if ($transaction_date === "") {
        die("transaction_date is required.");
    }
    if ($quantity <= 0) {
        die("quantity must be > 0.");
    }
    if ($personnel_id <= 0) {
        die("personnel_id is required.");
    }

    $conn->begin_transaction();

    try {
        $updateItemOnHand = function($item_id, $delta) use ($conn) {
            $stmt = $conn->prepare("SELECT on_hand_qty FROM Items WHERE item_id = ? FOR UPDATE");
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            if (!$res) {
                $stmt->close();
                throw new Exception("Item not found (ID: $item_id).");
            }
            $current = toFloat($res["on_hand_qty"]);
            $newQty = $current + $delta;

            $stmt->close();

            $stmt2 = $conn->prepare("UPDATE Items SET on_hand_qty = ? WHERE item_id = ?");
            $stmt2->bind_param("di", $newQty, $item_id);
            $stmt2->execute();
            if ($stmt2->affected_rows !== 1) {
                $stmt2->close();
                throw new Exception("Failed to update on_hand_qty for item $item_id.");
            }
            $stmt2->close();

            return $newQty;
        };

        $insertLog = function($transaction_date, $item_id, $shelter_id, $quantity, $transaction_type, $personnel_id, $transaction_notes_param) use ($conn) {
            $stmt = $conn->prepare("
                INSERT INTO InventoryLogs
                (transaction_date, item_id, shelter_id, quantity, transaction_type, personnel_id, transaction_notes)
                VALUES
                (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "siidssi",
                $transaction_date,
                $item_id,
                $shelter_id,
                $quantity,
                $transaction_type,
                $personnel_id,
                $transaction_notes_param
            );

            $stmt->close();
        };

        $insertLog2 = function($transaction_date, $item_id, $shelter_id, $quantity, $transaction_type, $personnel_id, $transaction_notes_param) use ($conn) {
            $stmt = $conn->prepare("
                INSERT INTO InventoryLogs
                (transaction_date, item_id, shelter_id, quantity, transaction_type, personnel_id, transaction_notes)
                VALUES
                (?, ?, ?, ?, ?, ?, ?)
            ");
            $qty = $quantity;
            $stmt->bind_param(
                "siidsis",
                $transaction_date,
                $item_id,
                $shelter_id,
                $qty,
                $transaction_type,
                $personnel_id,
                $transaction_notes_param
            );
            $stmt->close();
        };

        $insertLogInline = function($transaction_date, $item_id, $shelter_id, $quantity, $transaction_type, $personnel_id, $transaction_notes_param) use ($conn) {
            $stmt = $conn->prepare("
                INSERT INTO InventoryLogs
                (transaction_date, item_id, shelter_id, quantity, transaction_type, personnel_id, transaction_notes)
                VALUES
                (?, ?, ?, ?, ?, ?, ?)
            ");
            $qty = $quantity;
            $stmt->bind_param(
                "siidsis",
                $transaction_date,
                $item_id,
                $shelter_id,
                $qty,
                $transaction_type,
                $personnel_id,
                $transaction_notes_param
            );
            $stmt->close();
        };

        $insertLogFinal = function($transaction_date, $item_id, $shelter_id, $quantity, $transaction_type, $personnel_id, $transaction_notes_param) use ($conn) {
            if ($transaction_notes_param === null) {
                $stmt = $conn->prepare("
                    INSERT INTO InventoryLogs
                    (transaction_date, item_id, shelter_id, quantity, transaction_type, personnel_id, transaction_notes)
                    VALUES
                    (?, ?, ?, ?, ?, ?, NULL)
                ");
                $qty = $quantity;
                $stmt->bind_param("sii ds i", $transaction_date, $item_id, $shelter_id, $qty, $transaction_type, $personnel_id);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO InventoryLogs
                    (transaction_date, item_id, shelter_id, quantity, transaction_type, personnel_id, transaction_notes)
                    VALUES
                    (?, ?, ?, ?, ?, ?, ?)
                ");
                $qty = $quantity;
                $stmt->bind_param("sii d s i s", $transaction_date, $item_id, $shelter_id, $qty, $transaction_type, $personnel_id, $transaction_notes_param);
                $stmt->execute();
                $stmt->close();
            }
        };

        if ($action !== "add") {
            throw new Exception("Only add is supported for inventory movements in this page.");
        }

        if ($transaction_type === "IN") {
            $item_id = (int)($_POST["item_id"] ?? 0);
            $shelter_id = (int)($_POST["shelter_id"] ?? 0);

            if ($item_id <= 0 || $shelter_id <= 0) throw new Exception("Select item and shelter.");

            $updateItemOnHand($item_id, +$quantity);

            $insertLogFinal($transaction_date, $item_id, $shelter_id, $quantity, $transaction_type, $personnel_id, $transaction_notes_param);

        } elseif ($transaction_type === "OUT") {
            $item_id = (int)($_POST["item_id"] ?? 0);
            $shelter_id = (int)($_POST["shelter_id"] ?? 0);

            if ($item_id <= 0 || $shelter_id <= 0) throw new Exception("Select item and shelter.");

            $stmt = $conn->prepare("SELECT on_hand_qty FROM Items WHERE item_id = ? FOR UPDATE");
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$res) throw new Exception("Item not found.");

            $current = toFloat($res["on_hand_qty"]);
            $newQty = $current - $quantity;
            if ($newQty < 0) throw new Exception("Insufficient on-hand quantity for OUT.");

            $updateItemOnHand($item_id, -$quantity);

            $insertLogFinal($transaction_date, $item_id, $shelter_id, $quantity, $transaction_type, $personnel_id, $transaction_notes_param);

        } elseif ($transaction_type === "ADJUST") {
            $item_id = (int)($_POST["item_id"] ?? 0);
            $shelter_id = (int)($_POST["shelter_id"] ?? 0);

            $adjust_mode = $_POST["adjust_mode"] ?? "INCREASE";
            if ($adjust_mode !== "INCREASE" && $adjust_mode !== "DECREASE") $adjust_mode = "INCREASE";

            if ($item_id <= 0 || $shelter_id <= 0) throw new Exception("Select item and shelter.");

            if ($adjust_mode === "DECREASE") {
                $stmt = $conn->prepare("SELECT on_hand_qty FROM Items WHERE item_id = ? FOR UPDATE");
                $stmt->bind_param("i", $item_id);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$res) throw new Exception("Item not found.");

                $current = toFloat($res["on_hand_qty"]);
                if ($current - $quantity < 0) throw new Exception("Insufficient on-hand quantity for ADJUST DECREASE.");
                $updateItemOnHand($item_id, -$quantity);
            } else {
                $updateItemOnHand($item_id, +$quantity);
            }

            $insertLogFinal($transaction_date, $item_id, $shelter_id, $quantity, "ADJUST", $personnel_id, $transaction_notes_param);

        } elseif ($transaction_type === "TRANSFER") {
            $from_item_id = (int)($_POST["from_item_id"] ?? 0);
            $from_shelter_id = (int)($_POST["from_shelter_id"] ?? 0);

            $to_item_id = (int)($_POST["to_item_id"] ?? 0);
            $to_shelter_id = (int)($_POST["to_shelter_id"] ?? 0);

            if ($from_item_id <= 0 || $from_shelter_id <= 0 || $to_item_id <= 0 || $to_shelter_id <= 0) {
                throw new Exception("Select FROM and TO items/shelters.");
            }

            $stmt = $conn->prepare("SELECT on_hand_qty FROM Items WHERE item_id = ? FOR UPDATE");
            $stmt->bind_param("i", $from_item_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$res) throw new Exception("FROM item not found.");

            $current = toFloat($res["on_hand_qty"]);
            if ($current - $quantity < 0) throw new Exception("Insufficient on-hand quantity for TRANSFER OUT part.");

            $updateItemOnHand($from_item_id, -$quantity);
            $insertLogFinal($transaction_date, $from_item_id, $from_shelter_id, $quantity, "TRANSFER", $personnel_id, $transaction_notes_param);

            $updateItemOnHand($to_item_id, +$quantity);
            $insertLogFinal($transaction_date, $to_item_id, $to_shelter_id, $quantity, "TRANSFER", $personnel_id, $transaction_notes_param);

        } else {
            throw new Exception("Invalid transaction_type.");
        }

        $conn->commit();
        header("Location: inventory_movements.php");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        die($e->getMessage());
    }
}

$itemsForForm = [];
$itemsFromForm = [];
$itemsToForm = [];

$formTransactionType = $_POST["transaction_type"] ?? ($editMovement["transaction_type"] ?? "IN");

if ($_POST["shelter_id"] ?? null) {
    $itemsForForm = fetchItemsByShelter($conn, (int)$_POST["shelter_id"]);
}

if (isset($_POST["shelter_id"])) {
    echo "<pre>";
    echo "shelter_id=".(int)$_POST["shelter_id"]."\n";
    echo "itemsForForm_count=".count($itemsForForm)."\n";
    echo "</pre>";
}

if ($_POST["from_shelter_id"] ?? null) {
    $itemsFromForm = fetchItemsByShelter($conn, (int)$_POST["from_shelter_id"]);
}
if ($_POST["to_shelter_id"] ?? null) {
    $itemsToForm = fetchItemsByShelter($conn, (int)$_POST["to_shelter_id"]);
}
render_page:
$movementResult = $conn->query("
    SELECT
        il.transaction_id,
        il.transaction_date,
        il.transaction_type,
        il.quantity,
        il.item_id,
        il.shelter_id,
        il.personnel_id,
        il.transaction_notes,
        il.created_at,
        s.shelter_name,
        it.item_name,
        p.personnel_name
    FROM InventoryLogs il
    JOIN Shelters s ON s.shelter_id = il.shelter_id
    JOIN Items it ON it.item_id = il.item_id
    JOIN Personnel p ON p.personnel_id = il.personnel_id
    ORDER BY il.transaction_id DESC
");
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<title>Inventory Movements</title>
<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; margin-top: 16px; }
th, td { border: 1px solid #ddd; padding: 8px; }
th { background: #f4f4f4; }
form { margin: 0; }
.row { margin-bottom: 10px; }
input[type="text"], input[type="number"], input[type="date"], select { padding: 6px; width: 100%; max-width: 420px; }
.actions button { padding: 6px 10px; margin-right: 6px; }
.card { border: 1px solid #ddd; padding: 14px; border-radius: 6px; max-width: 1100px; }
.small-muted { color: #666; font-size: 12px; margin-top: 6px; }
</style>
</head>
<body>
<h2>Inventory Movements</h2>

<div class="card">
<h3>Add Inventory Movement</h3>

<form method="post" action="inventory_movements.php">
<input type="hidden" name="action" value="add">

<div class="row">
<label>Transaction Type</label><br>
<select name="transaction_type" required>
    <?php
    $types = ["IN","OUT","ADJUST","TRANSFER"];
    $currentType = $formTransactionType;
    foreach ($types as $t) {
        $sel = ($currentType === $t) ? "selected" : "";
        echo "<option value=\"{$t}\" {$sel}>{$t}</option>";
    }
    ?>
</select>
</div>

<div class="row">
<label>Transaction Date</label><br>
<input type="date" name="transaction_date" required value="<?= h($_POST["transaction_date"] ?? date("Y-m-d")) ?>">
</div>

<div class="row">
<label>Quantity</label><br>
<input type="number" step="0.001" name="quantity" required value="<?= h($_POST["quantity"] ?? "0") ?>">
</div>

<div class="row">
<label>Personnel</label><br>
<select name="personnel_id" required>
    <option value="">-- Select Personnel --</option>
    <?php while ($prow = $personnel->fetch_assoc()): ?>
        <?php $pid = (int)$prow["personnel_id"]; ?>
        <option value="<?= $pid ?>" <?= ((int)($_POST["personnel_id"] ?? 0) === $pid) ? "selected" : "" ?>>
            <?= h($prow["personnel_name"]) ?> (<?= $pid ?>)
        </option>
    <?php endwhile; ?>
</select>
</div>

<div class="row">
<label>Notes (optional)</label><br>
<input type="text" name="transaction_notes" value="<?= h($_POST["transaction_notes"] ?? "") ?>">
</div>

<?php if ($formTransactionType === "IN" || $formTransactionType === "OUT" || $formTransactionType === "ADJUST"): ?>
<div class="row">
<label>Shelter</label><br>
<select name="shelter_id" required>
    <option value="">-- Select Shelter --</option>
    <?php
    $selShelter = (int)($_POST["shelter_id"] ?? 0);
    $shelters->data_seek(0);
    while ($srow = $shelters->fetch_assoc()):
        $sid = (int)$srow["shelter_id"];
    ?>
    <option value="<?= $sid ?>" <?= ($selShelter === $sid) ? "selected" : "" ?>>
        <?= h($srow["shelter_name"]) ?> (<?= $sid ?>)
    </option>
    <?php endwhile; ?>
</select>
</div>

<div class="row">
<label>Item</label><br>
<select name="item_id" required>
    <option value="">-- Select Item --</option>
    <?php
    $items = $itemsForForm;
    $currentItem = (int)($_POST["item_id"] ?? 0);
    foreach ($items as $itrow):
        $iid = (int)$itrow["item_id"];
    ?>
    <option value="<?= $iid ?>" <?= ($currentItem === $iid) ? "selected" : "" ?>>
        <?= h($itrow["item_name"]) ?> (<?= h($itrow["unit"]) ?>) [<?= $iid ?>]
    </option>
    <?php endforeach; ?>
</select>
</div>

<?php if ($formTransactionType === "ADJUST"): ?>
<div class="row">
<label>Adjust Mode</label><br>
<select name="adjust_mode" required>
    <?php $mode = $_POST["adjust_mode"] ?? "INCREASE"; ?>
    <option value="INCREASE" <?= $mode === "INCREASE" ? "selected" : "" ?>>INCREASE</option>
    <option value="DECREASE" <?= $mode === "DECREASE" ? "selected" : "" ?>>DECREASE</option>
</select>
</div>
<?php endif; ?>

<?php elseif ($formTransactionType === "TRANSFER"): ?>
<div style="display:flex; gap:16px; flex-wrap:wrap;">
<div style="flex:1; min-width:320px;">
    <div class="row">
        <label>From Shelter</label><br>
        <select name="from_shelter_id" required>
            <option value="">-- Select Shelter --</option>
            <?php
            $selFS = (int)($_POST["from_shelter_id"] ?? 0);
            $shelters->data_seek(0);
            while ($srow = $shelters->fetch_assoc()):
                $sid = (int)$srow["shelter_id"];
            ?>
            <option value="<?= $sid ?>" <?= ($selFS === $sid) ? "selected" : "" ?>>
                <?= h($srow["shelter_name"]) ?> (<?= $sid ?>)
            </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="row">
        <label>From Item</label><br>
        <select name="from_item_id" required>
            <option value="">-- Select Item --</option>
            <?php
            $fromItems = $itemsFromForm;
            $currentFI = (int)($_POST["from_item_id"] ?? 0);
            foreach ($fromItems as $itrow):
                $iid = (int)$itrow["item_id"];
            ?>
            <option value="<?= $iid ?>" <?= ($currentFI === $iid) ? "selected" : "" ?>>
                <?= h($itrow["item_name"]) ?> (<?= h($itrow["unit"]) ?>) [<?= $iid ?>]
            </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div style="flex:1; min-width:320px;">
    <div class="row">
        <label>To Shelter</label><br>
        <select name="to_shelter_id" required>
            <option value="">-- Select Shelter --</option>
            <?php
            $selTS = (int)($_POST["to_shelter_id"] ?? 0);
            $shelters->data_seek(0);
            while ($srow = $shelters->fetch_assoc()):
                $sid = (int)$srow["shelter_id"];
            ?>
            <option value="<?= $sid ?>" <?= ($selTS === $sid) ? "selected" : "" ?>>
                <?= h($srow["shelter_name"]) ?> (<?= $sid ?>)
            </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="row">
        <label>To Item</label><br>
        <select name="to_item_id" required>
            <option value="">-- Select Item --</option>
            <?php
            $toItems = $itemsToForm;
            $currentTI = (int)($_POST["to_item_id"] ?? 0);
            foreach ($toItems as $itrow):
                $iid = (int)$itrow["item_id"];
            ?>
            <option value="<?= $iid ?>" <?= ($currentTI === $iid) ? "selected" : "" ?>>
                <?= h($itrow["item_name"]) ?> (<?= h($itrow["unit"]) ?>) [<?= $iid ?>]
            </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
</div>

<div class="row">
<label>Shelter</label><br>
<select name="shelter_id" required onchange="/* remove auto-submit if you want */">
<option value="">-- Select Shelter --</option>
<?php
$selShelter = (int)($_POST["shelter_id"] ?? 0);
$shelters->data_seek(0);
while ($srow = $shelters->fetch_assoc()):
    $sid = (int)$srow["shelter_id"];
?>
<option value="<?= $sid ?>" <?= ($selShelter === $sid) ? "selected" : "" ?>>
<?= h($srow["shelter_name"]) ?> (<?= $sid ?>)
</option>
<?php endwhile; ?>
</select>

<button type="submit" name="action" value="reload_items">Reload Items</button>
</div>


<div class="small-muted">TRANSFER writes two log rows (TRANSFER OUT and TRANSFER IN) and updates on-hand in both items.</div>
<?php endif; ?>

<div class="actions">
<button type="submit">Add Movement</button>
<a href="inventory.php"><button type="button">Back to Items</button></a>
</div>

</form>
</div>

<table>
<thead>
<tr>
<th>ID</th>
<th>Date</th>
<th>Type</th>
<th>Quantity</th>
<th>Item</th>
<th>Shelter</th>
<th>Personnel</th>
<th>Notes</th>
<th>Created</th>
</tr>
</thead>
<tbody>
<?php while ($m = $movementResult->fetch_assoc()): ?>
<tr>
<td><?= (int)$m["transaction_id"] ?></td>
<td><?= h($m["transaction_date"]) ?></td>
<td><?= h($m["transaction_type"]) ?></td>
<td><?= h($m["quantity"]) ?></td>
<td><?= h($m["item_name"]) ?> (<?= (int)$m["item_id"] ?>)</td>
<td><?= h($m["shelter_name"]) ?> (<?= (int)$m["shelter_id"] ?>)</td>
<td><?= h($m["personnel_name"]) ?> (<?= (int)$m["personnel_id"] ?>)</td>
<td><?= h($m["transaction_notes"]) ?></td>
<td><?= h($m["created_at"]) ?></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

</body>
</html>
