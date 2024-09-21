<?php
require_once __DIR__ . '/../Model/payments_mod.php';
require '../vendor/autoload.php';
require_once __DIR__ . '/../Model/db_connection.php';

// Disable error display temporarily
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$apiKey = 'sk_test_UPJT1HR9EGJtj1gZgi5EnR7N';
$payments = new Payments($conn, $apiKey);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = $_POST['payment_method'] ?? null;
    $appointment_id = isset($_POST['appsched_id']) ? intval($_POST['appsched_id']) : null;
    $paymentMethodId = $_POST['payment_method_id'] ?? null;

    if (!$appointment_id) {
        echo "Appointment ID is missing!";
        exit;
    }

    if (!$paymentMethod) {
        echo "Payment method is missing!";
        exit;
    }

    try {
        if ($paymentMethod === 'credit_card') {
            $finalAmount = $payments->getFinalAmount($appointment_id);
            if ($finalAmount === null) {
                throw new Exception('Appointment not found.');
            }

            $result = $payments->createPayMongoLink($finalAmount, $appointment_id);

            if (isset($result['checkout_url'])) {
                $paymentIntentId = $result['payment_intent_id'];
                $paymentMethodId = $result['payment_method_id'];

                $payments->updatePaymentDetails($appointment_id, 'Pending', $paymentMethod, $paymentIntentId, $paymentMethodId);

                header("Location: " . $result['checkout_url']);
                exit;
            } else {
                echo "Failed to retrieve payment link.";
            }
        } elseif ($paymentMethod === 'over_the_counter') {
            // Handle over the counter payment
            $result = $payments->handleOverTheCounterPayment($appointment_id);
            echo "Over the counter payment recorded. Please follow the OTC process.";
        } else {
            echo "Invalid payment method.";
        }
    } catch (PaymongoException $e) {
        error_log("PayMongo API error: " . $e->getMessage());
        echo "PayMongo API error occurred.";
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        echo "Database error occurred.";
    } catch (Exception $e) {
        error_log("General error: " . $e->getMessage());
        echo "An error occurred.";
    }
}
?>
