<?php
class ControllerExtensionPaymentPayoutsPay extends Controller {
    private function generateTrackingID() {
        $GUID = '';
        $chars = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        for ($i = 0; $i < 15; $i++) {
            $GUID .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $GUID;
    }

    private function callPayouts($receiverEmailArray, $receiverAmountArray, $currencyCode, $note, $trackingId) {
        $apiEndpoint = $this->config->get('payouts_pay_test') ? 'https://api.sandbox.paypal.com/v1/payments/payouts' : 'https://api.paypal.com/v1/payments/payouts';
        $clientId = $this->config->get('payouts_pay_client_id');
        $clientSecret = $this->config->get('payouts_pay_client_secret');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret)
        ]);

        $data = [
            'sender_batch_header' => [
                'sender_batch_id' => $trackingId,
                'email_subject' => 'You have a payment'
            ],
            'items' => array_map(function ($email, $amount) use ($currencyCode) {
                return [
                    'recipient_type' => 'EMAIL',
                    'amount' => [
                        'value' => $amount,
                        'currency' => $currencyCode
                    ],
                    'receiver' => $email,
                    'note' => '',
                    'sender_item_id' => $this->generateTrackingID()
                ];
            }, $receiverEmailArray, $receiverAmountArray),
        ];

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $this->log->write('PAYOUTS_PAY :: CURL ERROR: ' . curl_error($ch));
        }

        curl_close($ch);

        return json_decode($response, true);
    }

    public function index() {
        $this->language->load('extension/payment/payouts_pay');
        $data['text_testmode'] = $this->language->get('text_testmode');
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['testmode'] = $this->config->get('payouts_pay_test');

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        
        $receiverEmailArray = [];
        $receiverAmountArray = [];
        $adminAmount = 0;

        foreach ($this->cart->getProducts() as $product) {
            if ($product['seller_id'] > 0) {
                $receiverEmailArray[] = $this->db->query("SELECT paypal_email FROM " . DB_PREFIX . "sellers WHERE seller_id = '" . (int)$product['seller_id'] . "'")->row['paypal_email'];
                $receiverAmountArray[] = $this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')) * $product['quantity'];
            } else {
                $adminAmount += $this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')) * $product['quantity'];
            }
        }

        $shiptotal = $this->cart->hasShipping() ? $this->session->data['shipping_method']['cost'] : 0;
        $adminAmount += $shiptotal;

        $receiverEmailArray[] = $this->config->get('payouts_pay_adminemail');
        $receiverAmountArray[] = $adminAmount;

        $trackingId = $this->generateTrackingID();
        $response = $this->callPayouts($receiverEmailArray, $receiverAmountArray, $this->session->data['currency'], 'Payment for order ' . $this->session->data['order_id'], $trackingId);

        if (isset($response['batch_header']['batch_status']) && $response['batch_header']['batch_status'] == 'SUCCESS') {
            $data['action'] = $response['links'][0]['href'];
        } else {
            $this->log->write('PAYOUTS_PAY :: API CALL FAILED: ' . print_r($response, true));
            $data['action'] = $this->url->link('checkout/checkout', '', 'SSL');
        }

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_loading'] = $this->language->get('text_loading');
        $data['custom'] = $this->session->data['order_id'];

        return $this->load->view('extension/payment/payouts_pay', $data);
    }

    public function callback() {
        // Handle IPN or webhooks if needed
		// Get the request body and headers
        $body = file_get_contents('php://input');
        $headers = getallheaders();

        // Verify the webhook signature
        $paypalSignature = isset($headers['PAYPAL-TRANSMISSION-SIG']) ? $headers['PAYPAL-TRANSMISSION-SIG'] : '';
        $paypalCertUrl = isset($headers['PAYPAL-CERT-URL']) ? $headers['PAYPAL-CERT-URL'] : '';
        $paypalTransmissionId = isset($headers['PAYPAL-TRANSMISSION-ID']) ? $headers['PAYPAL-TRANSMISSION-ID'] : '';
        $paypalTransmissionTime = isset($headers['PAYPAL-TRANSMISSION-TIME']) ? $headers['PAYPAL-TRANSMISSION-TIME'] : '';
        $paypalWebhookId = isset($headers['PAYPAL-WEBHOOK-ID']) ? $headers['PAYPAL-WEBHOOK-ID'] : '';
        $paypalWebhookEvent = isset($headers['PAYPAL-WEBHOOK-EVENT']) ? $headers['PAYPAL-WEBHOOK-EVENT'] : '';

        // Check if the webhook event type is relevant
        if ($paypalWebhookEvent == 'PAYMENT.SALE.COMPLETED' || $paypalWebhookEvent == 'PAYMENT.SALE.DENIED') {
            $webhookData = json_decode($body, true);

            // Validate and process the webhook data
            if ($webhookData) {
                $batchId = isset($webhookData['batch_header']['batch_id']) ? $webhookData['batch_header']['batch_id'] : '';

                if ($batchId) {
                    // Log the webhook data for debugging purposes
                    $this->log->write('PAYOUTS_PAY :: Webhook received: ' . print_r($webhookData, true));

                    // Process the payout result
                    // You can update your database here or send notifications as needed

                    // Respond to PayPal to acknowledge receipt of the webhook
                    http_response_code(200);
                    echo json_encode(['status' => 'success']);
                    return;
                }
            }

            // Respond with error if the webhook data is invalid
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid webhook data']);
        } else {
            // Respond with an error for unsupported webhook events
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Unsupported webhook event']);
        }
    }
}
?>
