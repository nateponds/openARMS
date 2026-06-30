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

$sheltersResult = $conn->query("SELECT shelter_id, shelter_name FROM Shelters ORDER BY shelter_id DESC");

$itemsResult = $conn->query("
    SELECT item_id, item_name, item_type, unit
    FROM Items
    WHERE active = 1
    ORDER BY item_id DESC
");

$inventoryShelterId = isset($_GET["shelter_id"]) ? (int)$_GET["shelter_id"] : 0;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    $shelter_id = (int)($_POST["shelter_id"] ?? 0);
    $shelter_name = trim($_POST["shelter_name"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $contact_person = trim($_POST["contact_person"] ?? "");
    $contact_number = trim($_POST["contact_number"] ?? "");
    $capacity = trim($_POST["capacity"] ?? "");
    $shelter_type = trim($_POST["shelter_type"] ?? "");

    if ($action === "add_shelter") {
        $stmt = $conn->prepare("
            INSERT INTO Shelters
            (shelter_name, address, contact_person, contact_number, capacity, shelter_type)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) { die("Prepare failed (add_shelter): " . $conn->error); }

        $capParam = ($capacity === "") ? null : (int)$capacity;
        $stmt->bind_param(
            "sss i s",
            $shelter_name,
            $address,
            $contact_personParam = ($contact_person === "") ? null : $contact_person,
            $contact_numberParam = ($contact_number === "") ? null : $contact_number,
            $capParam,
            $shelter_typeParam = ($shelter_type === "") ? null : $shelter_type
        );

        $stmt->close();

        $stmt = $conn->prepare("
            INSERT INTO Shelters
            (shelter_name, address, contact_person, contact_number, capacity, shelter_type)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $capParam = ($capacity === "") ? null : (int)$capacity;
        $contact_personParam = ($contact_person === "") ? null : $contact_person;
        $contact_numberParam = ($contact_number === "") ? null : $contact_number;
        $shelter_typeParam = ($shelter_type === "") ? null : $shelter_type;

        $stmt->bind_param(
            "ssssi s",
            $shelter_name,
            $address,
            $contact_personParam,
            $contact_numberParam,
            $capParam,
            $shelter_typeParam
        );
        $stmt->close();

        $stmt = $conn->prepare("
            INSERT INTO Shelters
            (shelter_name, address, contact_person, contact_number, capacity, shelter_type)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "ssssis",
            $shelter_name,
            $address,
            $contact_personParam,
            $contact_numberParam,
            $capParam,
            $shelter_typeParam
        );

        if (!$stmt->execute()) { die("Execute failed (add_shelter): " . $stmt->error); }
        $stmt->close();

    } elseif ($action === "update_shelter") {
        $stmt = $conn->prepare("
            UPDATE Shelters
            SET shelter_name = ?,
                address = ?,
                contact_person = ?,
                contact_number = ?,
                capacity = ?,
                shelter_type = ?
            WHERE shelter_id = ?
        ");
        if (!$stmt) { die("Prepare failed (update_shelter): " . $conn->error); }

        $capParam = ($capacity === "") ? null : (int)$capacity;
        $contact_personParam = ($contact_person === "") ? null : $contact_person;
        $contact_numberParam = ($contact_number === "") ? null : $contact_number;
        $shelter_typeParam = ($shelter_type === "") ? null : $shelter_type;

        $stmt->bind_param(
            "sssisii",
            $shelter_name,
            $address,
            $contact_personParam,
            $contact_numberParam,
            $capParam,
            $shelter_typeParam,
            $shelter_id
        );

        $stmt->close();

        $stmt = $conn->prepare("
            UPDATE Shelters
            SET shelter_name = ?,
                address = ?,
                contact_person = ?,
                contact_number = ?,
                capacity = ?,
                shelter_type = ?
            WHERE shelter_id = ?
        ");
        $stmt->bind_param(
            "sssisii",
            $shelter_name,
            $address,
            $contact_personParam,
            $contact_numberParam,
            $capParam,
            $shelter_typeParam,
            $shelter_id
        );
        $stmt->close();

        $stmt = $conn->prepare("
            UPDATE Shelters
            SET shelter_name = ?,
                address = ?,
                contact_person = ?,
                contact_number = ?,
                capacity = ?,
                shelter_type = ?
            WHERE shelter_id = ?
        ");

        $stmt->bind_param(
            "sssisii",
            $shelter_name,
            $address,
            $contact_personParam,
            $contact_numberParam,
            $capParam,
            $shelter_typeParam,
            $shelter_id
        );

        if (!$stmt->execute()) { die("Execute failed (update_shelter): " . $stmt->error); }
        $stmt->close();

    } elseif ($action === "delete_shelter") {
        $stmt = $conn->prepare("DELETE FROM Shelters WHERE shelter_id = ?");
        $stmt->bind_param("i", $shelter_id);
        $stmt->execute();
        $stmt->close();
    }

    elseif ($action === "update_inventory") {
        $invShelterId = (int)($_POST["inventory_shelter_id"] ?? 0);
        if ($invShelterId > 0) {
            $itemIds = $_POST["item_id"] ?? [];
            $minStocks = $_POST["min_stock"] ?? [];

            $stmt = $conn->prepare("
                INSERT INTO ShelterInventory (shelter_id, item_id, min_stock)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE min_stock = VALUES(min_stock)
            ");
            if (!$stmt) { die("Prepare failed (update_inventory): " . $conn->error); }

            foreach ($itemIds as $idx => $itid) {
                $itid = (int)$itid;
                if ($itid <= 0) continue;

                $ms = $minStocks[$idx] ?? "0";
                $ms = trim((string)$ms);
                if ($ms === "") $ms = "0";
                $msFloat = (float)$ms;

                $stmt->bind_param("iid", $invShelterId, $itid, $msFloat);
                if (!$stmt->execute()) {
                    die("Execute failed (update_inventory): " . $stmt->error);
                }
            }
            $stmt->close();
        }
    }
}

$editShelter = null;
if (isset($_GET["edit"])) {
    $editId = (int)$_GET["edit"];
    $stmt = $conn->prepare("
        SELECT
            shelter_id, shelter_name, address, contact_person, contact_number,
            capacity, shelter_type, created_at
        FROM Shelters
        WHERE shelter_id = ?
    ");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editShelter = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$inventoryMinStock = [];
$inventoryRows = [];
if ($inventoryShelterId > 0) {
    $stmt = $conn->prepare("
        SELECT si.item_id, si.min_stock
        FROM ShelterInventory si
        WHERE si.shelter_id = ?
    ");
    $stmt->bind_param("i", $inventoryShelterId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $inventoryMinStock[(int)$r["item_id"]] = (string)$r["min_stock"];
    }
    $stmt->close();

    $inventoryNameStmt = $conn->prepare("SELECT shelter_name FROM Shelters WHERE shelter_id = ?");
    $inventoryNameStmt->bind_param("i", $inventoryShelterId);
    $inventoryNameStmt->execute();
    $inventoryName = $inventoryNameStmt->get_result()->fetch_assoc();
    $inventoryNameStmt->close();
    $inventoryShelterName = $inventoryName ? $inventoryName["shelter_name"] : "";
} else {
    $inventoryShelterName = "";
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<title>Shelters</title>
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
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
</style>
</head>
<body>
<h2>Shelters</h2>

<div class="grid2">
    <div class="card">
        <h3><?= $editShelter ? "Edit Shelter" : "Add Shelter" ?></h3>

        <form method="post" action="shelters.php">
            <input type="hidden" name="action" value="<?= $editShelter ? "update_shelter" : "add_shelter" ?>">
            <?php if ($editShelter): ?>
                <input type="hidden" name="shelter_id" value="<?= (int)$editShelter["shelter_id"] ?>">
            <?php endif; ?>

            <div class="row">
                <label>Shelter Name</label><br>
                <input type="text" name="shelter_name" required value="<?= h($editShelter["shelter_name"] ?? "") ?>">
            </div>

            <div class="row">
                <label>Address</label><br>
                <input type="text" name="address" required value="<?= h($editShelter["address"] ?? "") ?>">
            </div>

            <div class="row">
                <label>Contact Person (optional)</label><br>
                <input type="text" name="contact_person" value="<?= h($editShelter["contact_person"] ?? "") ?>">
            </div>

            <div class="row">
                <label>Contact Number (optional)</label><br>
                <input type="text" name="contact_number" value="<?= h($editShelter["contact_number"] ?? "") ?>">
            </div>

            <div class="row">
                <label>Capacity (optional)</label><br>
                <input type="number" step="1" name="capacity" value="<?= h($editShelter["capacity"] ?? "") ?>">
            </div>

            <div class="row">
                <label>Shelter Type (optional)</label><br>
                <input type="text" name="shelter_type" value="<?= h($editShelter["shelter_type"] ?? "") ?>">
            </div>

            <div class="actions">
                <button type="submit"><?= $editShelter ? "Save Changes" : "Add Shelter" ?></button>
                <?php if ($editShelter): ?>
                    <a href="shelters.php"><button type="button">Cancel</button></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>Edit Shelter Inventory (Min Stock)</h3>

        <div class="row">
            <label>Select Shelter</label><br>
            <select name="shelter_id" onchange="window.location='shelters.php?shelter_id='+this.value;">
                <option value="">-- Select Shelter --</option>
                <?php
                $sheltersForSelect = $conn->query("SELECT shelter_id, shelter_name FROM Shelters ORDER BY shelter_id DESC");
                while ($s = $sheltersForSelect->fetch_assoc()):
                    $sid = (int)$s["shelter_id"];
                ?>
                    <option value="<?= $sid ?>" <?= ($inventoryShelterId === $sid) ? "selected" : "" ?>>
                        <?= h($s["shelter_name"]) ?> (<?= $sid ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="small-muted">
            <?= $inventoryShelterId > 0 ? "Editing: " . h($inventoryShelterName) : "Choose a shelter to edit its inventory minimums." ?>
        </div>

        <?php if ($inventoryShelterId > 0): ?>
        <form method="post" action="shelters.php?shelter_id=<?= (int)$inventoryShelterId ?>">
            <input type="hidden" name="action" value="update_inventory">
            <input type="hidden" name="inventory_shelter_id" value="<?= (int)$inventoryShelterId ?>">

            <table>
                <thead>
                <tr>
                    <th>Item</th>
                    <th>Unit</th>
                    <th>Min Stock</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $itemsForInv = $conn->query("
                    SELECT item_id, item_name, item_type, unit
                    FROM Items
                    WHERE active = 1
                    ORDER BY item_id DESC
                ");
                while ($it = $itemsForInv->fetch_assoc()):
                    $itemId = (int)$it["item_id"];
                    $minVal = $inventoryMinStock[$itemId] ?? "0";
                ?>
                    <tr>
                        <td>
                            <?= h($it["item_name"]) ?>
                            <div class="small-muted"><?= h($it["item_type"]) ?></div>
                        </td>
                        <td><?= h($it["unit"]) ?></td>
                        <td style="min-width:180px;">
                            <input
                                type="number"
                                step="0.001"
                                name="min_stock[]"
                                value="<?= h($minVal) ?>"
                                required
                            >
                            <input type="hidden" name="item_id[]" value="<?= $itemId ?>">
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>

            <div class="actions" style="margin-top:12px;">
                <button type="submit">Save Inventory Min Stock</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Address</th>
        <th>Contact Person</th>
        <th>Contact Number</th>
        <th>Capacity</th>
        <th>Type</th>
        <th>Created</th>
        <th>Operations</th>
    </tr>
    </thead>
    <tbody>
    <?php
    $resShelters = $conn->query("
        SELECT
            shelter_id, shelter_name, address, contact_person, contact_number,
            capacity, shelter_type, created_at
        FROM Shelters
        ORDER BY shelter_id DESC
    ");
    while ($r = $resShelters->fetch_assoc()):
    ?>
        <tr>
            <td><?= (int)$r["shelter_id"] ?></td>
            <td><?= h($r["shelter_name"]) ?></td>
            <td><?= h($r["address"]) ?></td>
            <td><?= h($r["contact_person"]) ?></td>
            <td><?= h($r["contact_number"]) ?></td>
            <td><?= h($r["capacity"]) ?></td>
            <td><?= h($r["shelter_type"]) ?></td>
            <td><?= h($r["created_at"]) ?></td>
            <td>
                <a href="shelters.php?edit=<?= (int)$r["shelter_id"] ?>">Edit</a>
                &nbsp;|&nbsp;
                <a href="shelters.php?shelter_id=<?= (int)$r["shelter_id"] ?>">Min Stock</a>
                <form method="post" action="shelters.php" style="display:inline; margin-left:6px;">
                    <input type="hidden" name="action" value="delete_shelter">
                    <input type="hidden" name="shelter_id" value="<?= (int)$r["shelter_id"] ?>">
                    <button type="submit" onclick="return confirm('Delete shelter #<?= (int)$r["shelter_id"] ?>?');">Delete</button>
                </form>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

</body>
</html>
