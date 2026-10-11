<?php

$autoloadPath = __DIR__ . "/../vendor/autoload.php";
if (is_file($autoloadPath)) {
    require_once $autoloadPath;
}

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function authenticateUser()
{
    $headers = getallheaders();

    $authHeader = $headers["Authorization"] ?? "";

    if (empty($authHeader)) {

        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Authorization token is required"
        ]);

        exit;
    }

    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {

        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Invalid authorization format"
        ]);

        exit;
    }

    $token = $matches[1];

    // MUST be the same key used in login.php
    $secretKey =
        "event_booking_system_2026_super_secure_jwt_secret_key_123456789";

    try {
        if (class_exists("Firebase\\JWT\\JWT")) {
            $decoded = JWT::decode(
                $token,
                new Key($secretKey, "HS256")
            );
        } else {
            $parts = explode(".", $token);
            if (count($parts) !== 3) {
                throw new RuntimeException("Invalid token format.");
            }

            $base64UrlDecode = function ($value) {
                $padding = strlen($value) % 4;
                if ($padding > 0) {
                    $value .= str_repeat("=", 4 - $padding);
                }
                return base64_decode(strtr($value, "-_", "+/"), true);
            };

            $signature = $base64UrlDecode($parts[2]);
            $expectedSignature = hash_hmac(
                "sha256",
                "{$parts[0]}.{$parts[1]}",
                $secretKey,
                true
            );
            $payload = json_decode($base64UrlDecode($parts[1]), false);

            if ($signature === false
                || !hash_equals($expectedSignature, $signature)
                || !$payload
                || !isset($payload->exp)
                || (int) $payload->exp < time()) {
                throw new RuntimeException("Invalid or expired token.");
            }

            $decoded = $payload;
        }

        return $decoded;

    } catch (Throwable $e) {

        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Invalid or expired token"
        ]);

        exit;
    }
}

function requireRole($user, $allowedRoles)
{
    if (!in_array($user->role, $allowedRoles)) {

        http_response_code(403);

        echo json_encode([
            "success" => false,
            "message" => "Access denied. Insufficient permission."
        ]);

        exit;
    }
}
?>