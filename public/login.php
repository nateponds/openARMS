<?php
header("Content-Type: application/json");

include "../config/db_config.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["error" => "No data received."]);
    exit;
}

$username = trim($data["username"]);
$password = trim($data["password"]);

// Search by username
$sql = "SELECT * FROM Personnel WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(401);
    echo json_encode([
        "error" => "Username not found."
    ]);
    exit;
}

$user = $result->fetch_assoc();

/*
If you're currently storing plain-text passwords,
keep this.

Later we'll replace it with password_verify().
*/

if ($password != $user["password"]) {
    http_response_code(401);
    echo json_encode([
        "error" => "Incorrect password."
    ]);
    exit;
}

// Login successful
echo json_encode([
    "success" => true,
    "user" => [
        "personnel_id" => $user["personnel_id"],
        "personnel_name" => $user["personnel_name"],
        "username" => $user["username"],
        "role" => $user["role"],
        "shelter_id" => $user["shelter_id"]
    ]
]);

$conn->close();
?>
