<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

define('INDEX', true);

require 'inc/dbcon.php';
require 'inc/base.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['uuid'])) {
        $uuid = $_GET['uuid'];

        $stmt = $conn->prepare("UPDATE user SET isVerified = 1 WHERE id = ?");
        if (!$stmt) {
            die('{"error":"Prepared Statement failed on prepare: ' . $conn->error . '","status":"fail"}');
        }

        $stmt->bind_param("s", $uuid);
        if (!$stmt->execute()) {
            die('{"error":"Prepared Statement failed on execute: ' . $stmt->error . '","status":"fail"}');
        }

        if ($stmt->affected_rows === 0) {
            $stmt->close();
            die('{"error":"No rows affected","status":"fail"}');
        }

        $stmt->close();
        echo '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Verified</title>
        </head>
        <body>
            <h1>User Verified Successfully!</h1>
            <p>This window will close automatically in 5 seconds...</p>
            <script>
                setTimeout(function() {
                    window.close();
                }, 5000);
            </script>
        </body>
        </html>';
        exit;
    } else {
        die('{"error":"UUID parameter is missing","status":"fail"}');
    }
} else {
    die('{"error":"Invalid request method","status":"fail"}');
}
?>
