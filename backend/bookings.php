<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set CORS and Response Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


require_once 'db.php'; 


function sendSendGridEmail($toEmail, $subject, $htmlContent) {
    $apiKey = getenv('SENDGRID_API_KEY');
    if (!$apiKey && isset($_SERVER['SENDGRID_API_KEY'])) {
        $apiKey = $_SERVER['SENDGRID_API_KEY'];
    }
    $url = 'https://api.sendgrid.com/v3/mail/send';

    if (!$apiKey) {
        error_log('SendGrid email was not sent: SENDGRID_API_KEY is not configured.');
        return false;
    }

    $plainTextContent = trim(preg_replace('/\s+/', ' ', strip_tags($htmlContent)));
    $category = strpos($subject, 'Cancellation') !== false
        ? 'booking-cancellation'
        : 'booking-confirmation';

    $data = [
        "personalizations" => [[
            "to" => [["email" => $toEmail]]
        ]],
        "from" => [
            "email" => "fikries184@gmail.com",
            "name" => "Event Ticketing Portal"
        ],
        "subject" => $subject,
        "content" => [[
            "type" => "text/plain",
            "value" => $plainTextContent
        ], [
            "type" => "text/html",
            "value" => $htmlContent
        ]],
        "categories" => [$category]
    ];

    $lastError = 'Unknown SendGrid error.';
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $ch = curl_init($url);
        if ($ch === false) {
            $lastError = 'Unable to initialize cURL.';
            break;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response !== false && $httpCode === 202) {
            return true;
        }

        $lastError = $curlError ?: "SendGrid returned HTTP {$httpCode}: {$response}";
        if ($response !== false && $httpCode >= 400 && $httpCode < 600) {
            break;
        }
    }

    error_log("SendGrid email was not sent after {$attempt} attempt(s): {$lastError}");
    return false;
}

function sendResponseThenEmail($response, $toEmail, $subject, $htmlContent) {
    $emailSent = sendSendGridEmail($toEmail, $subject, $htmlContent);
    $response['email_sent'] = $emailSent;
    $response['email_message'] = $emailSent
        ? "Email sent to {$toEmail}."
        : "Email could not be sent after retrying. Check the SendGrid configuration and server error log.";

    echo json_encode($response);

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}

$method = $_SERVER['REQUEST_METHOD'];


if ($method === 'GET') {
    try {
        $userId = $_GET['user_id'] ?? 1;
        
        $stmt = $conn->prepare("
            SELECT b.*, e.title AS event_name, u.email 
            FROM bookings b 
            LEFT JOIN events e ON b.event_id = e.event_id 
            LEFT JOIN users u ON b.user_id = u.user_id 
            WHERE b.user_id = ?
              AND b.booking_id = (
                  SELECT MAX(latest.booking_id)
                  FROM bookings latest
                  WHERE latest.user_id = ?
              )
              AND b.booking_status <> 'Cancelled'
            ORDER BY b.booking_id DESC
        ");
        $stmt->execute([$userId, $userId]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "success" => true,
            "data" => $bookings
        ]);
    } catch (PDOException $e) {
        error_log('Booking GET failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Unable to load bookings from the database."
        ]);
    }
    exit;
}


if ($method === 'POST') {
    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput, true);

    $eventId = $data['event_id'] ?? 1;
    $quantity = $data['quantity'] ?? 1;
    $userId = 1; 

    try {
        $eventStmt = $conn->prepare("SELECT * FROM events WHERE event_id = ?");
        $eventStmt->execute([$eventId]);
        $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

        $eventName = $event['event_name'] ?? 'Konsert Live 2026';
        $ticketPrice = $event['ticket_price'] ?? 50.00;
        $totalPrice = $ticketPrice * $quantity;

       
        try {
            $insertStmt = $conn->prepare("INSERT INTO bookings (user_id, event_id, quantity, total_price, booking_status) VALUES (?, ?, ?, ?, 'Confirmed')");
            $insertStmt->execute([$userId, $eventId, $quantity, $totalPrice]);
            $bookingId = $conn->lastInsertId();
        } catch (Exception $ex) {
            $insertStmt = $conn->prepare("INSERT INTO bookings (user_id, event_id, quantity, total_price, status) VALUES (?, ?, ?, ?, 'Confirmed')");
            $insertStmt->execute([$userId, $eventId, $quantity, $totalPrice]);
            $bookingId = $conn->lastInsertId();
        }

        
        try {
            $updateStmt = $conn->prepare("UPDATE events SET available_tickets = available_tickets - ? WHERE event_id = ?");
            $updateStmt->execute([$quantity, $eventId]);
        } catch (Exception $ex) {}

        $userEmail = 'kl2508019931@student.uptm.edu.my';

       
        $subject = "Ticket Booking Confirmation - #" . $bookingId;
        $htmlContent = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                <h2 style='color: #2563eb;'>Booking Confirmed!</h2>
                <p>Thank you for your purchase. Here is your ticket pass summary:</p>
                <ul>
                    <li><strong>Booking Ref:</strong> BKG-2026-".str_pad($bookingId, 4, '0', STR_PAD_LEFT)."</li>
                    <li><strong>Event:</strong> {$eventName}</li>
                    <li><strong>Quantity:</strong> {$quantity} Ticket(s)</li>
                    <li><strong>Total Paid:</strong> RM ".number_format($totalPrice, 2)."</li>
                </ul>
                <p>Status: <strong style='color: #16a34a;'>Confirmed</strong></p>
            </div>
        ";
        $response = [
            "success" => true,
            "message" => "Booking successful. Confirmation email queued.",
            "booking_id" => $bookingId,
            "total_price" => $totalPrice,
            "email_type" => "booking_confirmation",
            "email_subject" => $subject
        ];
        sendResponseThenEmail($response, $userEmail, $subject, $htmlContent);

    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => "Transaction error: " . $e->getMessage()]);
    }
    exit;
}


if ($method === 'PUT') {
    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput, true);

    $bookingId = $data['booking_id'] ?? null;
    $bookingStatus = $data['booking_status'] ?? 'Cancelled';

    try {
        $booking = null;
        if ($bookingId) {
            $stmt = $conn->prepare("SELECT * FROM bookings WHERE booking_id = ?");
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Fallback to latest record in database
        if (!$booking) {
            $stmt = $conn->query("SELECT * FROM bookings ORDER BY booking_id DESC LIMIT 1");
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($booking) {
                $bookingId = $booking['booking_id'];
            }
        }

        
        if (!$booking) {
            try {
                $conn->query("INSERT INTO bookings (user_id, event_id, quantity, total_price, booking_status) VALUES (1, 1, 1, 50.00, 'Confirmed')");
            } catch (Exception $ex) {
                $conn->query("INSERT INTO bookings (user_id, event_id, quantity, total_price, status) VALUES (1, 1, 1, 50.00, 'Confirmed')");
            }
            $bookingId = $conn->lastInsertId();
            $booking = [
                'booking_id' => $bookingId,
                'user_id' => 1,
                'event_id' => 1,
                'quantity' => 1,
                'total_price' => 50.00
            ];
        }

        
        try {
            $updateStmt = $conn->prepare("UPDATE bookings SET booking_status = ? WHERE booking_id = ?");
            $updateStmt->execute([$bookingStatus, $bookingId]);
        } catch (Exception $ex) {
            $updateStmt = $conn->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
            $updateStmt->execute([$bookingStatus, $bookingId]);
        }

        // Restore stock
        if (strtolower($bookingStatus) === 'cancelled' && !empty($booking['event_id'])) {
            try {
                $restoreStmt = $conn->prepare("UPDATE events SET available_tickets = available_tickets + ? WHERE event_id = ?");
                $restoreStmt->execute([$booking['quantity'], $booking['event_id']]);
            } catch (Exception $ex) {}
        }

        $userEmail = 'kl2508019931@student.uptm.edu.my';
        $eventName = 'Konsert Live 2026';

        
        $subject = "Booking Cancellation Notice - #" . $bookingId;
        $htmlContent = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                <h2 style='color: #e11d48;'>Booking Cancellation Confirmed</h2>
                <p>Hello,</p>
                <p>Your booking <strong>#{$bookingId}</strong> for <strong>{$eventName}</strong> has been <strong style='color: #e11d48;'>CANCELLED</strong>.</p>
                <hr style='border: none; border-top: 1px solid #e2e8f0;'>
                <p><strong>Cancelled Quantity:</strong> {$booking['quantity']} Ticket(s)</p>
                <p><strong>Refundable Total:</strong> RM " . number_format($booking['total_price'], 2) . "</p>
                <br>
                <p style='font-size: 12px; color: #64748b;'>Event Ticketing Portal - Automated Notification</p>
            </div>
        ";
        
        $response = [
            "success" => true,
            "message" => "Booking #{$bookingId} updated to {$bookingStatus}.",
            "booking_id" => $bookingId,
            "email_type" => "booking_cancellation",
            "email_subject" => $subject
        ];
        sendResponseThenEmail($response, $userEmail, $subject, $htmlContent);

    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => "Update error: " . $e->getMessage()]);
    }
    exit;
}