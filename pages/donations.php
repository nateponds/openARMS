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
$suppliers = $conn->query("SELECT supplier_id, supplier_name FROM Suppliers ORDER BY supplier_id DESC");

$items = $conn->query("
SELECT item_id, item_name, item_type, unit, shelter_id
FROM Items
WHERE active = 1
ORDER BY item_id DESC
");

$donations = $conn->query("
SELECT
d.donation_id,
d.donor_name,
d.description,
d.shelter_id,
d.supplier_id,
d.received_date,
d.receipt_notes
FROM Donations d
ORDER BY d.donation_id DESC
");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    $donation_id = (int)($_POST["donation_id"] ?? 0);

    $donor_name = trim($_POST["donor_name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $receipt_notes = trim($_POST["receipt_notes"] ?? "");

    $shelter_id = $_POST["shelter_id"] ?? "";
    $supplier_id = $_POST["supplier_id"] ?? "";

    $shelter_id_param = ($shelter_id === "" ? null : (int)$shelter_id);
    $supplier_id_param = ($supplier_id === "" ? null : (int)$supplier_id);

    $received_date = trim($_POST["received_date"] ?? "");
    if ($received_date === "") die("received_date is required.");

    $donor_name_param = ($donor_name === "") ? null : $donor_name;
    $description_param = ($description === "") ? null : $description;
    $receipt_notes_param = ($receipt_notes === "") ? null : $receipt_notes;

    $item_id = (int)($_POST["item_id"] ?? 0);
    $item_quantity = trim($_POST["item_quantity"] ?? "");
    if ($item_id <= 0) die("item_id is required.");
    if ($item_quantity === "") $item_quantity = "0";

    if ($action === "add") {
        $stmt = $conn->prepare("
        INSERT INTO Donations
        (donor_name, description, shelter_id, supplier_id, received_date, receipt_notes)
        VALUES
        (?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) { die("Prepare failed (add donation): " . $conn->error); }

        $stmt->bind_param(
            "ssiiss",
            $donor_name_param,
            $description_param,
            $shelter_id_param,
            $supplier_id_param,
            $received_date,
            $receipt_notes_param
        );
        $stmt->close();

        $stmt = $conn->prepare("
        INSERT INTO Donations
        (donor_name, description, shelter_id, supplier_id, received_date, receipt_notes)
        VALUES
        (?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) { die("Prepare failed (add donation re): " . $conn->error); }

        $stmt->bind_param(
            "ssiiss",
            $donor_name_param,
            $description_param,
            $shelter_id_param,
            $supplier_id_param,
            $received_date,
            $receipt_notes_param
        );
        $stmt->close();

        $stmt = $conn->prepare("
        INSERT INTO Donations
        (donor_name, description, shelter_id, supplier_id, received_date, receipt_notes)
        VALUES
        (?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) { die("Prepare failed (add donation final): " . $conn->error); }

        $stmt->bind_param(
            "ss i i s s",
            $donor_name_param,
            $description_param,
            $shelter_id_param,
            $supplier_id_param,
            $received_date,
            $receipt_notes_param
        );
        $stmt->close();

        if ($shelter_id_param === null && $supplier_id_param === null) {
            $stmt = $conn->prepare("
            INSERT INTO Donations
            (donor_name, description, shelter_id, supplier_id, received_date, receipt_notes)
            VALUES
            (?, ?, NULL, NULL, ?, ?)
            ");
            $stmt->bind_param("ssss", $donor_name_param, $description_param, $received_date, $receipt_notes_param);
        } elseif ($shelter_id_param === null) {
            $stmt = $conn->prepare("
            INSERT INTO Donations
            (donor_name, description, shelter_id, supplier_id, received_date, receipt_notes)
            VALUES
            (?, ?, NULL, ?, ?, ?)
            ");
            $stmt->bind_param("ssiss", $donor_name_param, $description_param, $supplier_id_param, $received_date, $receipt_notes_param);
        } elseif ($supplier_id_param === null) {
            $stmt = $conn->prepare("
            INSERT INTO Donations
            (donor_name, description, shelter_id, supplier_id, received_date, receipt_notes)
            VALUES
            (?, ?, ?, NULL, ?, ?)
            ");
            $stmt->bind_param("ssiss", $donor_name_param, $description_param, $shelter_id_param, $received_date, $receipt_notes_param);
        } else {
            $stmt = $conn->prepare("
            INSERT INTO Donations
            (donor_name, description, shelter_id, supplier_id, received_date, receipt_notes)
            VALUES
            (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssii ss", $donor_name_param, $description_param, $shelter_id_param, $supplier_id_param, $received_date, $receipt_notes_param);
            $stmt->close();
        }

        if ($shelter_id_param === null && $supplier_id_param === null) {
            $stmt = $conn->prepare("
            INSERT INTO Donations
            (donor_name, description, shelter_id, supplier_id, received_date, receipt_notes)
            VALUES
            (?, ?, NULL, NULL, ?, ?)
            ");
            $stmt->bind_param("ssss", $donor_name_param, $description_param, $received_date, $receipt_notes_param);
        } elseif ($shelter_id_param === null) {
            $stmt = $conn->prepare("
            INSERT INTO Donations
            (donor_name, description, shelter_id, supplier_id, received_date, receipt_notes)
            VALUES
            (?, ?, NULL, ?, ?, ?)
            ");
            $stmt->bind_param("ssi ss", $donor_name_param, $description_param, $supplier_id_param, $received_date, $receipt_notes_param);
            $stmt->close();
        }

        if ($shelter_id_param === null && $supplier_id_param === null) {
            $stmt = $conn->prepare("
            INSERT INTO Donations
            (donor_name, description, shelter_id, supplier_id, received_date, receipt_notes)
            VALUES
            (?, ?, NULL, NULL, ?, ?)
            ");
            $stmt->bind_param("ssss", $donor_name_param, $description_param, $received_date, $receipt_notes_param);
        } elseif ($shelter_id_param === null) {
            $stmt = $conn->prepare("
            INSERT INTO Donations
            (donor_name, description, shelter_id, supplier_id, received_date, receipt_notes)
            VALUES
            (?, ?, NULL, ?, ?, ?)
            ");
            $stmt->bind_param("ssiss", $donor_name_param, $description_param, $supplier_id_param, $received_date, $receipt_notes_param);
        } elseif ($supplier_id_param === null) {
            $stmt = $conn->prepare("
            INSERT INTO Donations
            (donor_name, description, shelter_id, supplier_id, received_date, receipt_notes)
            VALUES
            (?, ?, ?, NULL, ?, ?)
            ");
            $stmt->bind_param("ssiss", $donor_name_param, $description_param, $shelter_id_param, $received_date, $receipt_notes_param);
        } else {
            $stmt = $conn->prepare("
            INSERT INTO Donations
            (donor_name, description, shelter_id, supplier_id, received_date, receipt_notes)
            VALUES
            (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssiiss", $donor_name_param, $description_param, $shelter_id_param, $supplier_id_param, $received_date, $receipt_notes_param);
        }

        if (!$stmt->execute()) die("Execute failed (add donation): " . $stmt->error);
        $donation_id = $conn->insert_id;
        $stmt->close();

        $stmt = $conn->prepare("
        INSERT INTO DonationLines
        (donation_id, item_id, item_quantity, line_notes)
        VALUES
        (?, ?, ?, NULL)
        ");
        if (!$stmt) { die("Prepare failed (add donation line): " . $conn->error); }

        $stmt->bind_param("iid", $donation_id, $item_id, $item_quantity);
        if (!$stmt->execute()) die("Execute failed (add donation line): " . $stmt->error);
        $stmt->close();

    } elseif ($action === "update") {
        if ($shelter_id_param === null && $supplier_id_param === null) {
            $stmt = $conn->prepare("
            UPDATE Donations
            SET donor_name = ?, description = ?, shelter_id = NULL, supplier_id = NULL, received_date = ?, receipt_notes = ?
            WHERE donation_id = ?
            ");
            $stmt->bind_param("ssss i", $donor_name_param, $description_param, $received_date, $receipt_notes_param, $donation_id);
            $stmt->close();
        }

        if ($shelter_id_param === null && $supplier_id_param === null) {
            $stmt = $conn->prepare("
            UPDATE Donations
            SET donor_name = ?, description = ?, shelter_id = NULL, supplier_id = NULL, received_date = ?, receipt_notes = ?
            WHERE donation_id = ?
            ");
            $stmt->bind_param("ssss i", $donor_name_param, $description_param, $received_date, $receipt_notes_param, $donation_id);
        } elseif ($shelter_id_param === null) {
            $stmt = $conn->prepare("
            UPDATE Donations
            SET donor_name = ?, description = ?, shelter_id = NULL, supplier_id = ?, received_date = ?, receipt_notes = ?
            WHERE donation_id = ?
            ");
            $stmt->bind_param("ssiss i", $donor_name_param, $description_param, $supplier_id_param, $received_date, $receipt_notes_param, $donation_id);
        } elseif ($supplier_id_param === null) {
            $stmt = $conn->prepare("
            UPDATE Donations
            SET donor_name = ?, description = ?, shelter_id = ?, supplier_id = NULL, received_date = ?, receipt_notes = ?
            WHERE donation_id = ?
            ");
            $stmt->bind_param("ssiss i", $donor_name_param, $description_param, $shelter_id_param, $received_date, $receipt_notes_param, $donation_id);
        } else {
            $stmt = $conn->prepare("
            UPDATE Donations
            SET donor_name = ?, description = ?, shelter_id = ?, supplier_id = ?, received_date = ?, receipt_notes = ?
            WHERE donation_id = ?
            ");
            $stmt->bind_param("ssiisi i", $donor_name_param, $description_param, $shelter_id_param, $supplier_id_param, $received_date, $receipt_notes_param, $donation_id);
        }

        if (!$stmt->execute()) die("Execute failed (update donation): " . $stmt->error);
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM DonationLines WHERE donation_id = ?");
        $stmt->bind_param("i", $donation_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("
        INSERT INTO DonationLines
        (donation_id, item_id, item_quantity, line_notes)
        VALUES
        (?, ?, ?, NULL)
        ");
        $stmt->bind_param("iid", $donation_id, $item_id, $item_quantity);
        if (!$stmt->execute()) die("Execute failed (update donation line): " . $stmt->error);
        $stmt->close();

    } elseif ($action === "delete") {
        $stmt = $conn->prepare("DELETE FROM Donations WHERE donation_id = ?");
        $stmt->bind_param("i", $donation_id);
        $stmt->execute();
        $stmt->close();
    }

    //header("Location: " . strtok($_SERVER["REQUEST_URI"], "?"));
    //exit;
}

$editDonation = null;
$editLine = null;

if (isset($_GET["edit"])) {
    $editId = (int)$_GET["edit"];

    $stmt = $conn->prepare("
    SELECT
    donation_id,
    donor_name,
    description,
    shelter_id,
    supplier_id,
    received_date,
    receipt_notes
    FROM Donations
    WHERE donation_id = ?
    ");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editDonation = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("
    SELECT item_id, item_quantity
    FROM DonationLines
    WHERE donation_id = ?
    LIMIT 1
    ");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editLine = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<title>Donations</title>
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
<h2>Donations</h2>

<div class="card">
<h3><?= $editDonation ? "Edit Donation" : "Add Donation" ?></h3>

<form method="post" action="donations.php">
<input type="hidden" name="action" value="<?= $editDonation ? "update" : "add" ?>">
<?php if ($editDonation): ?>
<input type="hidden" name="donation_id" value="<?= (int)$editDonation["donation_id"] ?>">
<?php endif; ?>

<div class="row">
<label>Donor Name (optional)</label><br>
<input type="text" name="donor_name" value="<?= h($editDonation["donor_name"] ?? "") ?>">
</div>

<div class="row">
<label>Description (optional)</label><br>
<input type="text" name="description" value="<?= h($editDonation["description"] ?? "") ?>">
</div>

<div class="row">
<label>Shelter (optional)</label><br>
<select name="shelter_id">
<option value="" <?= (!isset($editDonation["shelter_id"]) || $editDonation["shelter_id"] === null || (int)$editDonation["shelter_id"] === 0) ? "selected" : "" ?>>-- Select Shelter (optional) --</option>
<?php while ($srow = $shelters->fetch_assoc()): ?>
<?php $sid = (int)$srow["shelter_id"]; ?>
<option value="<?= $sid ?>" <?= ((int)($editDonation["shelter_id"] ?? 0) === $sid) ? "selected" : "" ?>>
<?= h($srow["shelter_name"]) ?> (<?= $sid ?>)
</option>
<?php endwhile; ?>
</select>
</div>

<div class="row">
<label>Supplier (optional)</label><br>
<select name="supplier_id">
<option value="" <?= (!isset($editDonation["supplier_id"]) || $editDonation["supplier_id"] === null || (int)$editDonation["supplier_id"] === 0) ? "selected" : "" ?>>-- Select Supplier (optional) --</option>
<?php while ($sup = $suppliers->fetch_assoc()): ?>
<?php $spid = (int)$sup["supplier_id"]; ?>
<option value="<?= $spid ?>" <?= ((int)($editDonation["supplier_id"] ?? 0) === $spid) ? "selected" : "" ?>>
<?= h($sup["supplier_name"]) ?> (<?= $spid ?>)
</option>
<?php endwhile; ?>
</select>
</div>

<div class="row">
<label>Received Date</label><br>
<input type="date" name="received_date" required value="<?= h($editDonation["received_date"] ?? date('Y-m-d')) ?>">
</div>

<div class="row">
<label>Receipt Notes (optional)</label><br>
<input type="text" name="receipt_notes" value="<?= h($editDonation["receipt_notes"] ?? "") ?>">
</div>

<div class="row">
<label>Donation Line Item</label><br>
<select name="item_id" required>
<option value="">-- Select Item --</option>
<?php
$selectedItemId = (int)($editLine["item_id"] ?? 0);
while ($irow = $items->fetch_assoc()):
    $iid = (int)$irow["item_id"];
?>
<option value="<?= $iid ?>" <?= ($selectedItemId === $iid) ? "selected" : "" ?>>
<?= h($irow["item_name"]) ?> (<?= h($irow["unit"]) ?>) - <?= h($irow["item_type"]) ?> [Shelter <?= (int)$irow["shelter_id"] ?>]
</option>
<?php endwhile; ?>
</select>
</div>

<div class="row">
<label>Item Quantity</label><br>
<input type="number" step="0.001" name="item_quantity" required value="<?= h($editLine["item_quantity"] ?? "0") ?>">
</div>

<div class="actions">
<button type="submit"><?= $editDonation ? "Save Changes" : "Add Donation" ?></button>
<?php if ($editDonation): ?>
<a href="donations.php"><button type="button">Cancel</button></a>
<?php endif; ?>
</div>
</form>
</div>

<table>
<thead>
<tr>
<th>Donation ID</th>
<th>Donor</th>
<th>Description</th>
<th>Shelter ID</th>
<th>Supplier ID</th>
<th>Received</th>
<th>Receipt Notes</th>
<th>Operations</th>
</tr>
</thead>
<tbody>
<?php while ($row = $donations->fetch_assoc()): ?>
<tr>
<td><?= (int)$row["donation_id"] ?></td>
<td><?= h($row["donor_name"]) ?></td>
<td><?= h($row["description"]) ?></td>
<td><?= $row["shelter_id"] === null ? "" : (int)$row["shelter_id"] ?></td>
<td><?= $row["supplier_id"] === null ? "" : (int)$row["supplier_id"] ?></td>
<td><?= h($row["received_date"]) ?></td>
<td><?= h($row["receipt_notes"]) ?></td>
<td>
<a href="donations.php?edit=<?= (int)$row["donation_id"] ?>">Edit</a>
<form method="post" action="donations.php" style="display:inline;">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="donation_id" value="<?= (int)$row["donation_id"] ?>">
<button type="submit" onclick="return confirm('Delete donation #<?= (int)$row["donation_id"] ?>?');">Delete</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

</body>
</html>
