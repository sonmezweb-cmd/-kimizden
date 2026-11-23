<?php
// Yerel test için PayTR get-token taklidi: php -S 127.0.0.1:8090 tests/mock_paytr.php
// Gerçek PayTR'ye bağlanmaz; yalnız alanların varlığını ve imza biçimini kontrol eder.
header('Content-Type: application/json');
$need = ['merchant_id', 'user_ip', 'merchant_oid', 'email', 'payment_amount', 'paytr_token', 'user_basket', 'debug_on', 'no_installment',
    'max_installment', 'user_name', 'user_address', 'user_phone', 'merchant_ok_url', 'merchant_fail_url', 'timeout_limit', 'currency', 'test_mode', 'lang'];
$missing = array_values(array_diff($need, array_keys($_POST)));
if ($missing) { echo json_encode(['status' => 'failed', 'reason' => 'eksik: ' . implode(',', $missing)]); exit; }
if (!ctype_digit($_POST['payment_amount']) || !preg_match('/^[A-Za-z0-9]+$/', $_POST['merchant_oid'])) { echo json_encode(['status' => 'failed', 'reason' => 'bicim']); exit; }
if (base64_decode($_POST['paytr_token'], true) === false || strlen(base64_decode($_POST['paytr_token'])) !== 32) { echo json_encode(['status' => 'failed', 'reason' => 'imza']); exit; }
file_put_contents(sys_get_temp_dir() . '/mock_paytr_last.json', json_encode($_POST));
echo json_encode(['status' => 'success', 'token' => 'MOCK' . substr(md5($_POST['merchant_oid']), 0, 20)]);
