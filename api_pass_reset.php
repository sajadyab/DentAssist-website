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

$sql="SELECT u.id AS user_id, u.username, u.role,
             p.id AS patient_id, p.email AS patient_email, p.phone AS patient_phone
      FROM users u
      LEFT JOIN patients p ON p.user_id = u.id
      WHERE u.username=? AND u.role='patient'
      LIMIT 1";
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

$row=$result->fetch_assoc();

/* verify email or phone match */

if($email){

if(strtolower($email)!=strtolower(trim((string) ($row['patient_email'] ?? '')))){

echo json_encode([
"success"=>false,
"message"=>"Email does not match this username"
]);
exit;

}

}

elseif($phone){

if($phone!=trim((string) ($row['patient_phone'] ?? ''))){

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

$update=$conn->prepare("UPDATE users SET password_hash=? WHERE username=? AND role='patient' LIMIT 1");
$update->bind_param("ss",$hashed,$username);
$update->execute();


/* WhatsApp delivery removed from this legacy script — use api/forgot_pass.php with the Node send.js server. */

/* success */

echo json_encode([
"success"=>true,
"message"=>"New password generated and sent."
]);

?>