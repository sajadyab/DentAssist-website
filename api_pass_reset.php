<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$conn = Database::getInstance()->getConnection();

$data = json_decode(file_get_contents("php://input"), true);

$username = trim($data['username'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');

if(!$username){
echo json_encode([
"success"=>false,
"message"=>"Username is required"
]);
exit;
}

/* check if username exists */

$sql="SELECT * FROM patients WHERE username=? LIMIT 1";
$stmt=$conn->prepare($sql);
$stmt->bind_param("s",$username);
$stmt->execute();

$result=$stmt->get_result();

if($result->num_rows==0){

echo json_encode([
"success"=>false,
"message"=>"Username not found"
]);
exit;

}

$patient=$result->fetch_assoc();

/* verify email or phone match */

if($email){

if(strtolower($email)!=strtolower($patient['email'])){

echo json_encode([
"success"=>false,
"message"=>"Email does not match this username"
]);
exit;

}

}

elseif($phone){

if($phone!=$patient['phone']){

echo json_encode([
"success"=>false,
"message"=>"Phone number does not match this username"
]);
exit;

}

}

else{

echo json_encode([
"success"=>false,
"message"=>"Provide email or phone number"
]);
exit;

}


/* generate new password */

$newPass=substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789"),0,8);

/* hash password */

$hashed=password_hash($newPass,PASSWORD_DEFAULT);


/* update database */

$update=$conn->prepare("UPDATE patients SET password=? WHERE username=?");
$update->bind_param("ss",$hashed,$username);
$update->execute();


/* WhatsApp delivery removed from this legacy script — use api/forgot_pass.php with the Node send.js server. */

/* success */

echo json_encode([
"success"=>true,
"message"=>"New password generated and sent."
]);

?>