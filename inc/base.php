<?php
// Volgende stukje is om de CORS Preflight te laten werken
// Access-Control headers are received during OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
  if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
      // may also be using PUT, PATCH, HEAD etc
      header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
  if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
      header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
  exit(0);
}

// Met de volgende if kan je een test doen om te zien of de request uit je app komt.
// Voor dit voorbeeld is de 'die' uitgeschakeld
// if(empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
//     (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'be.kovskib'  &&
//      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'be.ophalvens.mijnfantastischeapp')) { 
//     niet van de app
//     zet de volgende lijn in commentaar als je dit in de browser wilt testen
//     die('ID-10T');
// } 

if (!defined('INDEX')) {
   die('Error : ID-10T');
}
// --- Step 1: Initialize variables and functions

// Define API response codes and their related HTTP response
$api_response_code = array(0 => array('HTTP Response' => 400, 'Message' => 'Unknown Error'), 1 => array('HTTP Response' => 200, 'Message' => 'Success'), 2 => array('HTTP Response' => 403, 'Message' => 'HTTPS Required'), 3 => array('HTTP Response' => 401, 'Message' => 'Authentication Required'), 4 => array('HTTP Response' => 401, 'Message' => 'Authentication Failed'), 5 => array('HTTP Response' => 404, 'Message' => 'Invalid Request'), 6 => array('HTTP Response' => 400, 'Message' => 'Invalid Response Format'), 7 => array('HTTP Response' => 400, 'Message' => 'DB problems'), 8 => array('HTTP Response' => 400, 'Message' => 'Empty Resultset'));

// Set default HTTP response of 'Unknown Error'
$response['code'] = 0;
$response['status'] = 404;
$response['data'] = NULL;

if (!$conn) {
    // geen DB verbinding, antwoorden naar client!
    $response['code'] = 7;
    $response['status'] = $api_response_code[$response['code']]['HTTP Response'];
    $response['data'] = mysqli_connect_error();
    deliver_response($response);
}

// --- Step 2: Authorization

// Require connections to be made via HTTPS
// Disable this if your server is not using HTTPS
if ($_SERVER['HTTPS'] != 'on') {
    $response['code'] = 2;
    $response['status'] = $api_response_code[$response['code']]['HTTP Response'];
    $response['data'] = $api_response_code[$response['code']]['Message'];
    deliver_response($response);
}
// gegevens ophalen uit de body!

$body = file_get_contents('php://input');
$postvars = json_decode($body, true);


// Voorlopig gaat de request goed, we zetten de response code hier op 1
// Hier kan je eventueel eerst controle doen op meegeleverde credentials, als je dat wilt.
// In dat geval de $response['code] pas zetten na die test!
$response['code'] = 1;
$response['status'] = $api_response_code[$response['code']]['HTTP Response'];

function deliver_response(&$api_response) {
    // Define HTTP responses
    $http_response_code = array(200 => 'OK', 400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden', 404 => 'Not Found');
    
    // Ensure the status code exists in the response array
    if (!isset($http_response_code[$api_response['status']])) {
        $api_response['status'] = 400; // Default to Bad Request if status is not defined
    }

    // Set HTTP Response
    header('HTTP/1.1 ' . $api_response['status'] . ' ' . $http_response_code[$api_response['status']]);
    // Set HTTP Response Content Type
    header('Content-Type: application/json; charset=utf-8');
    // Format data into a JSON response
    $json_response = json_encode($api_response, JSON_UNESCAPED_UNICODE);
    // Deliver formatted data
    echo $json_response;
    // End script process
    exit;
}

function deliver_JSONresponse(&$api_response) {
    // Define HTTP responses
    $http_response_code = array(200 => 'OK', 400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden', 404 => 'Not Found');
    
    // Ensure the status code exists in the response array
    if (!isset($http_response_code[$api_response['status']])) {
        $api_response['status'] = 400; // Default to Bad Request if status is not defined
    }

    // Set HTTP Response
    header('HTTP/1.1 ' . $api_response['status'] . ' ' . $http_response_code[$api_response['status']]);
    // Set HTTP Response Content Type
    header('Content-Type: application/json; charset=utf-8');
    // Format data into a JSON response
    $json_response =  '{"data":'.$api_response['data'].'}';
    //$json_response = json_encode($api_response, JSON_UNESCAPED_UNICODE);
    // Deliver formatted data
    echo $json_response;
    // End script process
    exit;
}

/*
 getJsonObjFromResult krijgt een resultset (by reference) en forceert alle
 nummerieke velden naan numerieke waarden, zodat clientside niet nog eens
 moet worden omgezet naar getallen voor velden met getalwaarden.
*/
function getJsonObjFromResult(&$result){
    $fixed = array();
    $typeArray = array(
                    MYSQLI_TYPE_TINY, MYSQLI_TYPE_SHORT, MYSQLI_TYPE_INT24,    
                    MYSQLI_TYPE_LONG, MYSQLI_TYPE_LONGLONG,
                    MYSQLI_TYPE_DECIMAL, 
                    MYSQLI_TYPE_FLOAT, MYSQLI_TYPE_DOUBLE );
    $fieldList = array();
    // haal de veldinformatie van de velden in deze resultset op
    while($info = $result->fetch_field()){
        $fieldList[] = $info;
    }
    // haal de data uit de result en pas deze aan als het veld een
    // getaltype zou moeten bevatten
    while ($row = $result -> fetch_assoc()) {
        $fixedRow = array();
        $teller = 0;
        foreach ($row as $key => $value) {
            if (in_array($fieldList[$teller] -> type, $typeArray )) {
                $fixedRow[$key] = 0 + $value;
            } else {
                $fixedRow[$key] = $value;
            }
            $teller++;
        }
        $fixed[] = $fixedRow;
    }
    // geef een json object terug
    return json_encode($fixed, JSON_UNESCAPED_UNICODE);
}
?>