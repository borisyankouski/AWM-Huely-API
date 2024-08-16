<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

define ('INDEX', true);

require 'inc/dbcon.php';
require 'inc/base.php';

switch($_SERVER['REQUEST_METHOD']) {
    case 'GET': // GET statistic { id, gamemode, highest_percentage, lowest_tries, user_id, user_username, date }
        $dateRange = $_GET['date_range'] ?? 'alltime';
        $gamemode = $_GET['gamemode'] ?? 'regular';
        $statistics = getStatisticsByDateRange($conn, $api_response_code, $dateRange, $gamemode);
        deliver_JSONresponse($statistics);
        break;

    case 'POST': // POST user { id, gamemode, highest_percentage, lowest_tries, user_id, user_username, date }
        $id = generateUniqueId($conn);

        $gamemode = $postvars['gamemode'] ?? '';
        $highest_percentage = $postvars['highest_percentage'] ?? null;
        $lowest_tries = $postvars['lowest_tries'] ?? null;
        $user_id = $postvars['user_id'] ?? '';
        $user_username = $postvars['user_username'] ?? '';
        $date = $postvars['date'] ?? date('Y-m-d H:i:s');

        
        // Validate gamemode
        $allowedGamemodes = ['regular', 'blind', 'impossible', 'perfection', 'speedrun'];
        if (!in_array($gamemode, $allowedGamemodes)) {
            die('{"error":"Invalid gamemode","status":"fail"}');
        }

        switch ($gamemode) {
            case 'perfection':
                if ($lowest_tries == null) {
                    die('{"error":"lowest_tries missing","status":"fail"}');
                }
                break;
            default:
                if ($highest_percentage == null) {
                    die('{"error":"highest_percentage missing","status":"fail"}');
                }
                break;
        }

        // Fetch user_id based on user_username
        $stmtUser = $conn->prepare("SELECT id FROM user WHERE username = ?");
        if (!$stmtUser) {
            die('{"error":"Prepared Statement failed for user lookup","status":"fail"}');
        }

        $stmtUser->bind_param("s", $user_username);
        if (!$stmtUser->execute()) {
            die('{"error":"Failed to execute user lookup","status":"fail"}');
        }

        $resultUser = $stmtUser->get_result();
        if (!$resultUser || $resultUser->num_rows === 0) {
            die('{"error":"User not found","status":"fail"}');
        }

        $userRow = $resultUser->fetch_assoc();
        $user_id = $userRow['id'];

        $stmtUser->close();
        
        $stmt = $conn->prepare("INSERT INTO statistic (id, gamemode, highest_percentage, lowest_tries, user_id, user_username, date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            die('{"error":"Prepared Statement failed on prepare","status":"fail"}');
        }

        $stmt->bind_param("ssdisss", $id, $gamemode, $highest_percentage, $lowest_tries, $user_id, $user_username, $date);
        if (!$stmt->execute()) {
            die('{"error":"Prepared Statement failed on execute","status":"fail"}');
        }

        if ($stmt->affected_rows === 0) {
            $stmt->close();
            die('{"error":"No rows affected","status":"fail"}');
        }

        $stmt->close();
        die('{"data":"ok","message":"Record added successfully","status":"ok"}');
        break;

}

function generateUniqueId($conn) {
    do {
        $data = openssl_random_pseudo_bytes(16);
        assert(strlen($data) == 16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        $stmt = $conn->prepare("SELECT id FROM statistic WHERE id = ?");
        $stmt->bind_param("s", $uuid);
        $stmt->execute();
        $stmt->store_result();
        $numRows = $stmt->num_rows;
        $stmt->close();
    } while ($numRows > 0);

    return $uuid;
}

function getStatisticsByDateRange($conn, $api_response_code, $dateRange, $gamemode) {
    $sql = "SELECT ";

    // Select lowest_tries for perfection gamemode, and highest_percentage for other gamemodes
    if ($gamemode === 'perfection') {
        $sql .= "MIN(lowest_tries) AS value";
    } else {
        $sql .= "MAX(highest_percentage) AS value";
    }

    $sql .= ", user_id, user_username, date, gamemode
            FROM statistic
            WHERE ";

    $startDate = '';
    $endDate = date('Y-m-d');

    switch ($dateRange) {
        case 'daily':
            $startDate = $endDate;
            break;
        case 'weekly':
            $startDate = date('Y-m-d', strtotime('last Monday'));
            break;
        case 'monthly':
            $startDate = date('Y-m-01');
            break;
        case 'yearly':
            $startDate = date('Y-01-01');
            break;
        case 'alltime':
            $startDate = date('2020-01-01');
            break;
        default:
            die('{"error":"Invalid date range","status":"fail"}');
    }

    // if ($dateRange !== 'alltime') {
    // }
    $sql .= "DATE(date) BETWEEN ? AND ? ";
    
    // Add gamemode filter
    if (!empty($gamemode)) {
        $sql .= "AND gamemode = ? ";
    }

    $sql .= "GROUP BY user_id";

    // Modify sorting based on gamemode
    if ($gamemode === 'perfection') {
        $sql .= " ORDER BY value ASC";
    } else {
        $sql .= " ORDER BY value DESC";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die('{"error":"Prepared Statement failed on prepare","status":"fail"}');
    }

    $bindParams = array();
    // if ($dateRange !== 'alltime') {
    // }
    
    $bindParams[] = $startDate;
    $bindParams[] = $endDate;
    // Bind gamemode parameter if not empty
    if (!empty($gamemode)) {
        $bindParams[] = $gamemode;
    }

    if (!empty($bindParams)) {
        $bindString = str_repeat('s', count($bindParams));
        $stmt->bind_param($bindString, ...$bindParams);
    }

    if (!$stmt->execute()) {
        die('{"error":"Prepared Statement failed on execute","status":"fail"}');
    }

    $result = $stmt->get_result();
    if (!$result) {
        die('{"error":"Failed to get result","status":"fail"}');
    }

    $response['code'] = 1;
    $response['status'] = $api_response_code[$response['code']]['HTTP Response'];
    $response['data'] = getJsonObjFromResult($result);
    $result->free();
    $conn->close();
    deliver_JSONresponse($response);
    
    return $response;
}



?>