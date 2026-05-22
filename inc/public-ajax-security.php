<?php
/**
 * Security helpers for public read-only AJAX endpoints.
 *
 * Public shop/search endpoints are safe to call anonymously, and cached pages
 * can expose expired nonces. Keep nonce enforcement for authenticated users.
 *
 * @package nalakalu-2025
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! function_exists('nlk_public_ajax_is_authenticated_request')) {
	function nlk_public_ajax_is_authenticated_request() {
		return function_exists('is_user_logged_in') && is_user_logged_in();
	}
}

if (! function_exists('nlk_public_ajax_get_nonce_value')) {
	function nlk_public_ajax_get_nonce_value($query_arg = 'nonce', $source = 'request') {
		$source = strtolower((string) $source);
		$data   = $_REQUEST;

		if ('post' === $source) {
			$data = $_POST;
		} elseif ('get' === $source) {
			$data = $_GET;
		}

		if (empty($data[$query_arg])) {
			return '';
		}

		$nonce = function_exists('wp_unslash') ? wp_unslash($data[$query_arg]) : $data[$query_arg];
		$nonce = function_exists('sanitize_text_field') ? sanitize_text_field($nonce) : trim((string) $nonce);

		return $nonce;
	}
}

if (! function_exists('nlk_public_ajax_verify_nonce')) {
	function nlk_public_ajax_verify_nonce($action, $query_arg = 'nonce', $source = 'request') {
		if (! nlk_public_ajax_is_authenticated_request()) {
			return true;
		}

		$nonce = nlk_public_ajax_get_nonce_value($query_arg, $source);

		if ('' === $nonce || ! function_exists('wp_verify_nonce')) {
			return false;
		}

		return (bool) wp_verify_nonce($nonce, $action);
	}
}

if (! function_exists('nlk_public_ajax_check_nonce')) {
	function nlk_public_ajax_check_nonce($action, $query_arg = 'nonce', $source = 'request') {
		if (nlk_public_ajax_verify_nonce($action, $query_arg, $source)) {
			return true;
		}

		if (function_exists('wp_send_json_error')) {
			wp_send_json_error(array('message' => 'Invalid nonce'), 403);
		}

		return false;
	}
}
