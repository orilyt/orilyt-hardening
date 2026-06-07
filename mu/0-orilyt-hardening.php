<?php
/**
 * Plugin Name: Orilyt Security Hardening
 * Description: Anti-enumeration WP + rate limit lost password (déployé 2026-04-23 suite campagne ciblée)
 * Version: 1.0
 * Author: Orilyt
 */

if (!defined('ABSPATH')) exit;

// =====================================================================
// 1. Bloquer énumération /?author=N → 404
// =====================================================================
add_action('init', function () {
    if (is_admin()) return;
    if (isset($_GET['author']) && is_numeric($_GET['author'])) {
        status_header(404);
        nocache_headers();
        wp_die('Not found', 'Not found', array('response' => 404));
    }
}, 1);

// =====================================================================
// 2. Bloquer /wp-json/wp/v2/users aux anonymes
// =====================================================================
add_filter('rest_authentication_errors', function ($result) {
    if (!empty($result)) return $result;
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if (strpos($uri, '/wp/v2/users') !== false && !is_user_logged_in()) {
        return new WP_Error('rest_forbidden', 'Authentication required', array('status' => 401));
    }
    return $result;
});

// =====================================================================
// 3. Rate limit + réponse uniformisée lost password
//    - Max 5 POST / 15 min / IP
//    - Si user n'existe pas : fake success (pas de leak énumération)
// =====================================================================
add_action('login_form_lostpassword', function () {
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') return;

    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    if ($ip) {
        $key = 'olrl_' . md5($ip);
        $count = (int) get_transient($key);
        if ($count >= 5) {
            wp_safe_redirect(wp_login_url() . '?checkemail=confirm');
            exit;
        }
        set_transient($key, $count + 1, 900);
    }

    $user_login = isset($_POST['user_login']) ? trim(wp_unslash($_POST['user_login'])) : '';
    if ($user_login === '') return;

    $user = is_email($user_login)
        ? get_user_by('email', $user_login)
        : get_user_by('login', $user_login);

    if (!$user) {
        wp_safe_redirect(wp_login_url() . '?checkemail=confirm');
        exit;
    }
}, 1);

// =====================================================================
// 4. Génériciser login errors (pas de "Invalid username")
// =====================================================================
add_filter('login_errors', function ($error) {
    if (stripos($error, 'lost your password') !== false ||
        stripos($error, 'mot de passe oublié') !== false) {
        return $error;
    }
    return '<strong>Error:</strong> Invalid credentials.';
});
