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

$suppliers = $conn->query("SELECT supplier_id, supplier_name FROM Suppliers ORDER BY supplier_id DESC");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $supplier_id = (int)($_POST["supplier_id"] ?? 0);

    $supplier_name = trim($_POST["supplier_name"] ?? "");
    $contact = trim($_POST["contact"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $supplier_type = trim($_POST["supplier_type"] ?? "");
    if ($supplier_type === "") $supplier_type = "General";

    if ($action === "add") {
        $stmt = $conn->prepare("
        INSERT INTO Suppliers
        (supplier_name, contact, email, address, supplier_type)
        VALUES
        (?, ?, ?, ?, ?)
        ");
        if (!$stmt) { die("Prepare failed (add): " . $conn->error); }

        $stmt->bind_param(
            "sssss",
            $supplier_name,
            $contact_param,
            $email_param,
            $address_param,
            $supplier_type
        );

        $contact_param = ($contact === "") ? null : $contact;
        $email_param   = ($email === "") ? null : $email;
        $address_param = ($address === "") ? null : $address;

        if (!$stmt->execute()) { die("Execute failed (add): " . $stmt->error); }
        $stmt->close();

    } elseif ($action === "update") {
        $stmt = $conn->prepare("
        UPDATE Suppliers
        SET
        supplier_name = ?,
        contact = ?,
        email = ?,
        address = ?,
        supplier_type = ?
        WHERE supplier_id = ?
        ");
        if (!$stmt) { die("Prepare failed (update): " . $conn->error); }

        $stmt->bind_param(
            "sssssi",
            $supplier_name,
            $contact_param,
            $email_param,
            $address_param,
            $supplier_type,
            $supplier_id
        );

        $contact_param = ($contact === "") ? null : $contact;
        $email_param   = ($email === "") ? null : $email;
        $address_param = ($address === "") ? null : $address;

        if (!$stmt->execute()) { die("Execute failed (update): " . $stmt->error); }
        $stmt->close();

    } elseif ($action === "delete") {
        $stmt = $conn->prepare("DELETE FROM Suppliers WHERE supplier_id = ?");
        $stmt->bind_param("i", $supplier_id);
        $stmt->execute();
        $stmt->close();
    }
}

$editSupplier = null;
if (isset($_GET["edit"])) {
    $editId = (int)$_GET["edit"];
    $stmt = $conn->prepare("
    SELECT
    supplier_id, supplier_name, contact, email, address, supplier_type,
    created_at
    FROM Suppliers
    WHERE supplier_id = ?
    ");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editSupplier = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$result = $conn->query("
SELECT
supplier_id, supplier_name, contact, email, address, supplier_type, created_at
FROM Suppliers
ORDER BY supplier_id DESC
");
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<title>Suppliers</title>
<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; margin-top: 16px; }
th, td { border: 1px solid #ddd; padding: 8px; }
th { background: #f4f4f4; }
form { margin: 0; }
.row { margin-bottom: 10px; }
input[type="text"], input[type="email"], textarea, select { padding: 6px; width: 100%; max-width: 420px; }
.actions button { padding: 6px 10px; margin-right: 6px; }
.card { border: 1px solid #ddd; padding: 14px; border-radius: 6px; max-width: 980px; }
.small-muted { color: #666; font-size: 12px; margin-top: 6px; }
textarea { height: 80px; resize: vertical; }
</style>
</head>
<body>
<h2>Suppliers</h2>

<div class="card">
<h3><?= $editSupplier ? "Edit Supplier Instance" : "Add Supplier Instance" ?></h3>

<form method="post" action="suppliers.php">
<input type="hidden" name="action" value="<?= $editSupplier ? "update" : "add" ?>">
<?php if ($editSupplier): ?>
<input type="hidden" name="supplier_id" value="<?= (int)$editSupplier["supplier_id"] ?>">
<?php endif; ?>

<div class="row">
<label>Supplier Name</label><br>
<input type="text" name="supplier_name" required value="<?= h($editSupplier["supplier_name"] ?? "") ?>">
</div>

<div class="row">
<label>Contact</label><br>
<input type="text" name="contact" value="<?= h($editSupplier["contact"] ?? "") ?>">
</div>

<div class="row">
<label>Email</label><br>
<input type="email" name="email" value="<?= h($editSupplier["email"] ?? "") ?>">
</div>

<div class="row">
<label>Address</label><br>
<textarea name="address"><?= h($editSupplier["address"] ?? "") ?></textarea>
</div>

<div class="row">
<label>Supplier Type</label><br>
<input type="text" name="supplier_type" required value="<?= h($editSupplier["supplier_type"] ?? "General") ?>">
</div>

<div class="actions">
<button type="submit"><?= $editSupplier ? "Save Changes" : "Add Supplier" ?></button>
<?php if ($editSupplier): ?>
<a href="suppliers.php"><button type="button">Cancel</button></a>
<?php endif; ?>
</div>

</form>
</div>

<table>
<thead>
<tr>
<th>ID</th>
<th>Name</th>
<th>Contact</th>
<th>Email</th>
<th>Address</th>
<th>Type</th>
<th>Created</th>
<th>Operations</th>
</tr>
</thead>
<tbody>
<?php while ($row = $result->fetch_assoc()): ?>
<tr>
<td><?= (int)$row["supplier_id"] ?></td>
<td><?= h($row["supplier_name"]) ?></td>
<td><?= h($row["contact"]) ?></td>
<td><?= h($row["email"]) ?></td>
<td><?= h($row["address"]) ?></td>
<td><?= h($row["supplier_type"]) ?></td>
<td><?= h($row["created_at"]) ?></td>
<td>
<a href="suppliers.php?edit=<?= (int)$row["supplier_id"] ?>">Edit</a>
<form method="post" action="suppliers.php" style="display:inline;">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="supplier_id" value="<?= (int)$row["supplier_id"] ?>">
<button type="submit" onclick="return confirm('Delete supplier #<?= (int)$row["supplier_id"] ?>?');">Delete</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

</body>
</html>
