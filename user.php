<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

define ('INDEX', true);

// require 'inc/dbcon.php';
// require 'inc/base.php';
require 'send-confirm.php';

switch($_SERVER['REQUEST_METHOD']) {
    case 'GET': // GET user { id, email, password, username, isverified }
        $sql="select id, email, password, username, isVerified FROM user";

        $result = $conn -> query($sql);

        if (!$result) {
            $response['code'] = 7;
            $response['status'] = $api_response_code[$response['code']]['HTTP Response'];
            $response['data'] = $conn->error;
            deliver_response($response);
        }

        $response['data'] = getJsonObjFromResult($result);
        $result->free();
        $conn->close();
        deliver_JSONresponse($response);
        break;
    case 'POST': // POST user { id, email, password, username }
        $id = generateUniqueId();
        $hashedPassword = password_hash($postvars['password'], PASSWORD_BCRYPT);
        $email = $postvars['email'];
        $username = $postvars['username'];

        // echo('--id'.$id);
        // echo('--password'.$hashedPassword);
        // echo('--email'.$email);
        // echo('--username'.$username);

        $stmt = $conn->prepare("INSERT INTO user (id, email, password, username) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            die('{"error":"Prepared Statement failed on prepare","status":"fail"}');
        }

        $stmt->bind_param("ssss", $id, $email, $hashedPassword, $username);
        if (!$stmt->execute()) {
            die('{"error":"Prepared Statement failed on execute","status":"fail"}');
        }

        if ($stmt->affected_rows === 0) {
            $stmt->close();
            die('{"error":"No rows affected","status":"fail"}');
        }

        getIdAndSendMail($email, $username);
        $stmt->close();
        die('{"data":"ok","message":"Record added successfully","status":"ok"}');
        break;
        
}

function generateUniqueId() {
    global $conn;
    do {
        $data = openssl_random_pseudo_bytes(16);
        assert(strlen($data) == 16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        $stmt = $conn->prepare("SELECT id FROM user WHERE id = ?");
        $stmt->bind_param("s", $uuid);
        $stmt->execute();
        $stmt->store_result();
        $numRows = $stmt->num_rows;
        $stmt->close();
    } while ($numRows > 0);

    return $uuid;
}
?>