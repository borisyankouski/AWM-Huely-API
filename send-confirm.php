<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

define('INDEX', true);

require 'inc/dbcon.php';
require 'inc/base.php';

if (isset($_GET['mail_to']) && isset($_GET['name'])){
    getIdAndSendMail($_GET['mail_to'], $_GET['name']);
} else {
    // echo "Error: Missing query parameters.";
}

function getIdAndSendMail($email, $name) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT id FROM user WHERE email = ? AND username = ?");
    if (!$stmt) {
        die('{"error":"Prepared Statement failed on prepare: ' . $conn->error . '","status":"fail"}');
    }

    $stmt->bind_param("ss", $email, $name);
    if (!$stmt->execute()) {
        die('{"error":"Prepared Statement failed on execute: ' . $stmt->error . '","status":"fail"}');
    }

    $stmt->bind_result($userId);
    $stmt->fetch();
    $stmt->close();
    sendEmailWithTemplateFromQueryParams($userId, $email, $name);
}

function sendEmailWithTemplateFromQueryParams($uuid, $email, $name){
    $mail_to = $email;
    $name = $name;

    $api_key = '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';
    $url = 'https://www.kovskib.com/Huely/api/verify-user.php?uuid=' . $uuid;
    $username = ', <strong>' . $name . '</strong>';

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.sendgrid.com/v3/mail/send",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode(array(
            "personalizations" => array(
                array(
                    "to" => array(
                        array(
                            "email" => $mail_to
                        )
                    ),
                    "dynamic_template_data" => array(
                        "username" => $username,
                        "url" => $url
                    )
                )
            ),
            "from" => array(
                "email" => "noreply@kovskib.com"
            ),
            "subject" => "Huely Verification Email",
            "content" => array(
                array(
                    "type" => "text/html",
                    "value" => "Content missing! Please Try again..."
                )
            ),
            "template_id" => "d-7d1e742c1e9f42368b57344ba9f22b3e"
        )),
        CURLOPT_HTTPHEADER => array(
            "authorization: Bearer $api_key",
            "content-type: application/json"
        ),
    ));

    $response = curl_exec($curl);
    echo($response);
    $err = curl_error($curl);
    echo($err);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        echo $response;
    }
}
?>
