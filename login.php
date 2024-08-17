<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

define ('INDEX', true);

require 'inc/dbcon.php';
require 'inc/base.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $username = $_GET['username'] ?? '';
    $password = $_GET['password'] ?? '';

    // Check if username and password are provided
    if(empty($username) || empty($password)){
        echo '{"error":"Missing username or password","status":"fail-missing"}';
        exit;
    }

    // Fetch user data based on username
    $stmt = $conn->prepare("SELECT * FROM user WHERE username = ?");
    if (!$stmt) {
        echo '{"error":"Prepared Statement failed for user lookup","status":"fail-prepare"}';
        exit;
    }

    $stmt->bind_param("s", $username);
    if (!$stmt->execute()) {
        echo '{"error":"Failed to execute user lookup","status":"fail-execute"}';
        $stmt->close();
        exit;
    }

    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) {
        echo '{"error":"Credentials mismatch","status":"fail-credentials"}';
        $stmt->close();
        exit;
    }

    $userRow = $result->fetch_assoc();
    $hashedPassword = $userRow['password'];
    $userRow['password'] = "hidden"; 

    // Check if user is verified
    if ($userRow['isVerified'] == 0) {
        echo '{"error":"User is unverified","status":"fail-verification"}';
        $stmt->close();
        exit;
    }

    $stmt->close();

    // Verify password
    if (password_verify($password, $hashedPassword)) {
        // echo '{"data":"Successful login","status":"ok"}';
        echo '{"data":' . json_encode($userRow) . ',"status":"ok"}';
    } else {
        echo '{"error":"Credentials mismatch","status":"fail-credentials"}';
    }
} else {
    echo '{"error":"Invalid request method","status":"fail-invalid"}';
}
?>
