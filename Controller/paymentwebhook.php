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

// Check the event type and fetch necessary details
$eventType = $event['data']['attributes']['type'] ?? null;
$paymentIntentId = $event['data']['attributes']['data']['attributes']['payment_intent_id'] ?? null;

if (!$paymentIntentId) {
    http_response_code(400);
    echo 'Missing payment intent ID';
    exit;
}

// Determine the payment status based on the event type
$paymentStatus = null;
if ($eventType === 'payment.paid') {
    $paymentStatus = 'Paid';
} elseif ($eventType === 'payment.failed') {
    $paymentStatus = 'Failed';
} else {
    http_response_code(400);
    echo 'Unknown event type';
    exit;
}

// Find the appsched_id associated with the payment_intent_id
$stmt = $conn->prepare('SELECT appsched_id FROM payments WHERE payment_intent_id = ?');
$stmt->bind_param('s', $paymentIntentId);
$stmt->execute();
$stmt->bind_result($appsched_id);
$stmt->fetch();
$stmt->close();

if (!$appsched_id) {
    http_response_code(400);
    echo 'No matching appointment ID found';
    exit;
}

// Update the payment status and payment method ID
$paymentMethodId = $event['data']['attributes']['data']['attributes']['payment_method_id'] ?? null;
$payments->updatePaymentDetails($appsched_id, $paymentStatus, 'Online', $paymentIntentId, $paymentMethodId);

// Respond with success
http_response_code(200);
echo 'Payment status updated for Payment Intent: ' . $paymentIntentId;
?>
