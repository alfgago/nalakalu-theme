<?php

define('ABSPATH', __DIR__);

$GLOBALS['nlk_test_is_logged_in'] = false;
$GLOBALS['nlk_test_verified_nonces'] = array();
$GLOBALS['nlk_test_json_error'] = null;

function is_user_logged_in() {
	return $GLOBALS['nlk_test_is_logged_in'];
}

function wp_unslash($value) {
	return $value;
}

function sanitize_text_field($value) {
	return is_string($value) ? trim($value) : '';
}

function wp_verify_nonce($nonce, $action) {
	$GLOBALS['nlk_test_verified_nonces'][] = array($nonce, $action);

	return $nonce === 'valid-nonce' && $action === 'nlk_shop_nonce';
}

function wp_send_json_error($data = null, $status_code = null) {
	$GLOBALS['nlk_test_json_error'] = array(
		'data'   => $data,
		'status' => $status_code,
	);

	throw new RuntimeException('wp_send_json_error');
}

function assert_true($condition, $message) {
	if (! $condition) {
		throw new RuntimeException($message);
	}
}

require __DIR__ . '/../inc/public-ajax-security.php';

$_POST = array('nonce' => 'stale-public-nonce');
$GLOBALS['nlk_test_is_logged_in'] = false;
$GLOBALS['nlk_test_verified_nonces'] = array();

assert_true(
	nlk_public_ajax_verify_nonce('nlk_shop_nonce', 'nonce', 'post'),
	'Logged-out public AJAX requests should not be blocked by stale page-cached nonces.'
);

assert_true(
	$GLOBALS['nlk_test_verified_nonces'] === array(),
	'Logged-out public AJAX requests should not call wp_verify_nonce.'
);

$_POST = array('nonce' => 'valid-nonce');
$GLOBALS['nlk_test_is_logged_in'] = true;
$GLOBALS['nlk_test_verified_nonces'] = array();

assert_true(
	nlk_public_ajax_verify_nonce('nlk_shop_nonce', 'nonce', 'post'),
	'Logged-in AJAX requests should pass with a valid nonce.'
);

assert_true(
	$GLOBALS['nlk_test_verified_nonces'] === array(array('valid-nonce', 'nlk_shop_nonce')),
	'Logged-in AJAX requests should verify the supplied nonce.'
);

$_POST = array('nonce' => 'stale-public-nonce');
$GLOBALS['nlk_test_is_logged_in'] = true;
$GLOBALS['nlk_test_json_error'] = null;

try {
	nlk_public_ajax_check_nonce('nlk_shop_nonce', 'nonce', 'post');
	throw new RuntimeException('Logged-in invalid nonce should have failed.');
} catch (RuntimeException $e) {
	if ($e->getMessage() !== 'wp_send_json_error') {
		throw $e;
	}
}

assert_true(
	$GLOBALS['nlk_test_json_error']['status'] === 403,
	'Logged-in invalid nonce should return a 403 JSON error.'
);

echo "public AJAX security tests passed\n";
