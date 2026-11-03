<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); parse_str(file_get_contents('php://input'), $p);
header('Content-Type: application/json');
if ($path === '/v1/checkout/sessions') { echo json_encode(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.test/cs_test_1', 'client_reference_id' => $p['client_reference_id'] ?? null]); }
elseif ($path === '/v1/checkout/sessions/cs_test_1') { echo json_encode(['id' => 'cs_test_1', 'client_reference_id' => '1', 'customer' => 'cus_1', 'subscription' => 'sub_1']); }
elseif ($path === '/v1/billing_portal/sessions') { echo json_encode(['url' => 'https://billing.stripe.test/' . ($p['customer'] ?? '')]); }
elseif (str_starts_with($path, '/v1/subscriptions/')) { echo json_encode(['id' => 'sub_1', 'status' => 'active', 'customer' => 'cus_1', 'current_period_end' => time() + 30 * 86400, 'metadata' => ['user_id' => '1']]); }
else { http_response_code(404); echo json_encode(['error' => ['message' => "unhandled $path"]]); }
