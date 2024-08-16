<?php
//header('Access-Control-Allow-Origin: *');
//header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
//header('Access-Control-Max-Age: 1000');
//header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

//define ('INDEX', true);

// require 'inc/dbcon.php';
// require 'inc/base.php';
require 'send-confirm.php';

switch($_SERVER['REQUEST_METHOD']) {
    case 'GET': // GET user by email or username
        $email = isset($_GET['email']) ? $_GET['email'] : null;
        $username = isset($_GET['username']) ? $_GET['username'] : null;

        if ($email) {
            $sql = "SELECT id, email, password, username, isVerified FROM user WHERE email = ?";
            $param = $email;
        } elseif ($username) {
            $sql = "SELECT id, email, password, username, isVerified FROM user WHERE username = ?";
            $param = $username;
        } else {
            $response['code'] = 400;
            $response['status'] = 'Bad Request';
            $response['data'] = 'Email or username must be provided';
            deliver_JSONresponse($response);
            break;
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $response['code'] = 500;
            $response['status'] = 'Internal Server Error';
            $response['data'] = 'Failed to prepare statement';
            deliver_JSONresponse($response);
            break;
        }

        $stmt->bind_param("s", $param);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $response['code'] = 404;
            $response['status'] = 'Not Found';
            $response['data'] = null;
            deliver_JSONresponse($response);
            break;
        }

        $response['data'] = getJsonObjFromResult($result);
        $stmt->close();
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