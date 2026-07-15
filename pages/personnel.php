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

$personnel = $conn->query("
SELECT personnel_id, personnel_name, role, phone, created_at
FROM Personnel
ORDER BY personnel_id DESC
");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    $personnel_id = (int)($_POST["personnel_id"] ?? 0);

    $personnel_name = trim($_POST["personnel_name"] ?? "");
    $role = trim($_POST["role"] ?? "");
    $phone = trim($_POST["phone"] ?? "");

    $personnel_name_param = ($personnel_name === "") ? null : $personnel_name;
    $role_param = ($role === "") ? null : $role;
    $phone_param = ($phone === "") ? null : $phone;

    if ($action === "add") {
        $stmt = $conn->prepare("
        INSERT INTO Personnel
        (personnel_name, role, phone)
        VALUES
        (?, ?, ?)
        ");
        if (!$stmt) { die("Prepare failed (add): " . $conn->error); }

        if ($personnel_name_param === null) die("personnel_name is required.");

        $stmt->bind_param("sss",
                          $personnel_name_param,
                          $role_param,
                          $phone_param
        );

        if (!$stmt->execute()) { die("Execute failed (add): " . $stmt->error); }
        $stmt->close();

    } elseif ($action === "update") {
        $stmt = $conn->prepare("
        UPDATE Personnel
        SET personnel_name = ?,
        role = ?,
        phone = ?
        WHERE personnel_id = ?
        ");
        if (!$stmt) { die("Prepare failed (update): " . $conn->error); }

        if ($personnel_name_param === null) die("personnel_name is required.");

        $stmt->bind_param(
            "sssi",
            $personnel_name_param,
            $role_param,
            $phone_param,
            $personnel_id
        );

        if (!$stmt->execute()) { die("Execute failed (update): " . $stmt->error); }
        $stmt->close();

    } elseif ($action === "delete") {
        $stmt = $conn->prepare("DELETE FROM Personnel WHERE personnel_id = ?");
        $stmt->bind_param("i", $personnel_id);
        $stmt->execute();
        $stmt->close();
    }
}

$editPersonnel = null;
if (isset($_GET["edit"])) {
    $editId = (int)$_GET["edit"];
    $stmt = $conn->prepare("
    SELECT personnel_id, personnel_name, role, phone, created_at
    FROM Personnel
    WHERE personnel_id = ?
    ");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editPersonnel = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<title>Personnel</title>
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
<h2>Personnel</h2>

<div class="card">
<h3><?= $editPersonnel ? "Edit Personnel Instance" : "Add Personnel Instance" ?></h3>

<form method="post" action="personnel.php">
<input type="hidden" name="action" value="<?= $editPersonnel ? "update" : "add" ?>">
<?php if ($editPersonnel): ?>
<input type="hidden" name="personnel_id" value="<?= (int)$editPersonnel["personnel_id"] ?>">
<?php endif; ?>

<div class="row">
<label>Personnel Name</label><br>
<input type="text" name="personnel_name" required value="<?= h($editPersonnel["personnel_name"] ?? "") ?>">
</div>

<div class="row">
<label>Role (optional)</label><br>
<input type="text" name="role" value="<?= h($editPersonnel["role"] ?? "") ?>">
</div>

<div class="row">
<label>Phone (optional)</label><br>
<input type="text" name="phone" value="<?= h($editPersonnel["phone"] ?? "") ?>">
</div>

<div class="actions">
<button type="submit"><?= $editPersonnel ? "Save Changes" : "Add Personnel" ?></button>
<?php if ($editPersonnel): ?>
<a href="personnel.php"><button type="button">Cancel</button></a>
<?php endif; ?>
</div>

</form>
</div>

<table>
<thead>
<tr>
<th>ID</th>
<th>Name</th>
<th>Role</th>
<th>Phone</th>
<th>Created</th>
<th>Operations</th>
</tr>
</thead>
<tbody>
<?php while ($row = $personnel->fetch_assoc()): ?>
<tr>
<td><?= (int)$row["personnel_id"] ?></td>
<td><?= h($row["personnel_name"]) ?></td>
<td><?= h($row["role"]) ?></td>
<td><?= h($row["phone"]) ?></td>
<td><?= h($row["created_at"]) ?></td>
<td>
<a href="personnel.php?edit=<?= (int)$row["personnel_id"] ?>">Edit</a>
<form method="post" action="personnel.php" style="display:inline;">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="personnel_id" value="<?= (int)$row["personnel_id"] ?>">
<button type="submit" onclick="return confirm('Delete personnel #<?= (int)$row["personnel_id"] ?>?');">Delete</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

</body>
</html>
<!--  -->
