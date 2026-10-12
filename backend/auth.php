<?php

$autoloadPath = __DIR__ . "/../vendor/autoload.php";
if (is_file($autoloadPath)) {
    require_once $autoloadPath;
}

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function getJwtSecret()
{
    $secret = getenv("EVENT_BOOKING_JWT_SECRET");
    if ($secret === false || trim($secret) === "") {
        $secret = $_SERVER["EVENT_BOOKING_JWT_SECRET"] ?? "";
    }
    if (!is_string($secret) || trim($secret) === "") {
        throw new RuntimeException("JWT secret is not configured.");
    }
    return $secret;
}

function authenticateUser()
{
    $headers = function_exists("getallheaders") ? getallheaders() : [];
    $authHeader = $headers["Authorization"]
        ?? $headers["authorization"]
        ?? $_SERVER["HTTP_AUTHORIZATION"]
        ?? "";

    if (empty($authHeader)) {

        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Authorization token is required"
        ]);

        exit;
    }

    if (!preg_match('/^Bearer\s+(\S+)$/D', trim($authHeader), $matches)) {

        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Invalid authorization format"
        ]);

        exit;
    }

    $token = $matches[1];

    try {
        $secretKey = getJwtSecret();
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
                || !$payload) {
                throw new RuntimeException("Invalid token.");
            }
            if (!isset($payload->exp) || !is_numeric($payload->exp)
                || (int) $payload->exp <= time()) {
                throw new RuntimeException("Expired token.");
            }

            $decoded = $payload;
        }

        if (!isset($decoded->exp) || !is_numeric($decoded->exp)
            || (int) $decoded->exp <= time()) {
            throw new RuntimeException("Expired token.");
        }

        return $decoded;

    } catch (Throwable $e) {
        if ($e instanceof RuntimeException
            && $e->getMessage() === "JWT secret is not configured.") {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Authentication service is not configured"
            ]);
            exit;
        }

        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Invalid token"
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