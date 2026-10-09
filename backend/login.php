<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once "db.php";

$autoloadPath = __DIR__ . "/../vendor/autoload.php";
if (is_file($autoloadPath)) {
    require_once $autoloadPath;
}

if (class_exists("Firebase\\JWT\\JWT")) {
    $jwtEncoder = function ($payload, $secretKey) {
        return \Firebase\JWT\JWT::encode($payload, $secretKey, "HS256");
    };
} else {
    $jwtEncoder = function ($payload, $secretKey) {
        $base64Url = function ($value) {
            return rtrim(strtr(base64_encode($value), "+/", "-_"), "=");
        };
        $header = $base64Url(json_encode(["typ" => "JWT", "alg" => "HS256"]));
        $body = $base64Url(json_encode($payload));
        $signature = $base64Url(hash_hmac("sha256", "{$header}.{$body}", $secretKey, true));
        return "{$header}.{$body}.{$signature}";
    };
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Method not allowed"
    ]);

    exit;
}

$data = json_decode(
    file_get_contents("php://input"),
    true
);

$email = trim($data["email"] ?? "");
$username = trim($data["username"] ?? "");
$password = $data["password"] ?? "";

if (($email === "" && $username === "") || $password === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Username/email and password are required"
    ]);

    exit;
}

$user = null;

// Demo administrator account requested for the frontend login page.
if ($username === "admin" && hash_equals("admin123", $password)) {
    $user = [
        "user_id" => 0,
        "name" => "Administrator",
        "email" => "admin@eventbooking.local",
        "role" => "Administrator"
    ];
} else {
    $stmt = $conn->prepare(
        "SELECT * FROM users WHERE email = ?"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user["password"])) {
        $user = null;
    }
}

if (!$user) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Invalid email or password"
    ]);

    exit;
}


/* JWT */

$secretKey = "event_booking_system_2026_super_secure_jwt_secret_key_123456789";

$issuedAt = time();

$expire = $issuedAt + 3600;


$payload = [

    "iat" => $issuedAt,

    "exp" => $expire,

    "user_id" => $user["user_id"],

    "name" => $user["name"],

    "email" => $user["email"],

    "role" => $user["role"]

];


$jwt = $jwtEncoder($payload, $secretKey);


http_response_code(200);

echo json_encode([

    "success" => true,

    "message" => "Login successful",

    "token" => $jwt,

    "user" => [

        "user_id" => $user["user_id"],

        "name" => $user["name"],

        "email" => $user["email"],

        "role" => $user["role"]

    ]

]);

?>