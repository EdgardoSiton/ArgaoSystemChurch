<?php
require_once __DIR__ . '/../Model/payments_mod.php';
require_once __DIR__ . '/../Model/db_connection.php';
require '../vendor/autoload.php';

$apiKey = 'sk_test_UPJT1HR9EGJtj1gZgi5EnR7N';
$payments = new Payments($conn, $apiKey);

// Read the webhook payload from PayMongo
$payload = @file_get_contents('php://input');
$event = json_decode($payload, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo 'Invalid JSON';
    exit;
}

// Check the event type
$eventType = $event['data']['attributes']['type'] ?? null;
$paymentIntentId = $event['data']['attributes']['data']['attributes']['payment_intent_id'] ?? null;

if (!$paymentIntentId) {
    http_response_code(400);
    echo 'Missing payment intent ID';
    exit;
}

// Get the status based on event type
if ($eventType === 'payment.paid') {
    $paymentStatus = 'Paid';
} elseif ($eventType === 'payment.failed') {
    $paymentStatus = 'Failed';
} else {
    http_response_code(400);
    echo 'Unknown event type';
    exit;
}

// Update the payment status based on the payment intent
// You need to ensure `appsched_id` is tied to `payment_intent_id` in your database
$stmt = $conn->prepare('UPDATE payments SET payment_status = ?, updated_at = NOW() WHERE payment_intent_id = ?');
$stmt->bind_param('ss', $paymentStatus, $paymentIntentId);
$stmt->execute();
$stmt->close();

// Respond with success
http_response_code(200);
echo 'Payment status updated for Payment Intent: ' . $paymentIntentId;
?>
