<?php
/**
 * Plugin Name: Orilyt Security Hardening
 * Description: Anti-enumeration WP + rate limit lost password (déployé 2026-04-23 suite campagne ciblée)
 * Version: 1.1.1
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
// 3. Rate limit + réponse uniformisée lost password (v1.1)
//    - Max 5 POST / 15 min / IP
//    - Réponse identique compte existant / inexistant : même URL ET même
//      temps de réponse. L'email de reset part HORS de la requête, pour
//      qu'aucun delta de timing (envoi SMTP) ne réénumère les comptes.
//    - Le hook `login_form_lostpassword` se déclenche AVANT le
//      retrieve_password() du core (wp-login.php) : on intercepte donc
//      avant tout envoi, puis on `exit` pour court-circuiter le core.
// =====================================================================

// Envoi du reset hors-requête (utilisé par le fallback non-FPM via wp-cron).
add_action('olrl_send_reset', function ($user_login) {
    if (is_string($user_login) && $user_login !== '') {
        retrieve_password($user_login); // génère la clé + envoie l'email
    }
}, 10, 1);

add_action('login_form_lostpassword', function () {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;

    // IP réelle : on ne lit X-Forwarded-For que derrière un proxy de
    // confiance explicitement déclaré (sinon l'en-tête est spoofable par
    // le client). rightmost = IP ajoutée par le dernier proxy traversé,
    // robuste pour UN seul hop (ex. Cloudflare seul). En multi-hop ou
    // derrière CF, préférer CF-Connecting-IP côté infra.
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (defined('OLRL_TRUST_PROXY') && OLRL_TRUST_PROXY && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $xff = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip  = trim(end($xff));
    }

    if ($ip) {
        $key   = 'olrl_' . md5($ip);
        $count = (int) get_transient($key);
        if ($count >= 5) {
            // Déjà bloqué : on NE réincrémente PAS (sinon le TTL se rafraîchit
            // à chaque hit → verrou perpétuel, y compris pour une IP partagée).
            // Sortie identique au cas nominal.
            wp_safe_redirect(wp_login_url() . '?checkemail=confirm');
            exit;
        }
        set_transient($key, $count + 1, 900);
    }

    $user_login = isset($_POST['user_login']) ? trim(wp_unslash($_POST['user_login'])) : '';
    if ($user_login === '') return; // laisse WP afficher son erreur "champ vide"

    $user = is_email($user_login)
        ? get_user_by('email', $user_login)
        : get_user_by('login', $user_login);

    // Réponse identique dans les deux cas, émise AVANT tout envoi mail.
    nocache_headers();
    wp_safe_redirect(wp_login_url() . '?checkemail=confirm');

    // PHP-FPM expose fastcgi_finish_request(), LiteSpeed expose
    // litespeed_finish_request() : les deux flushent la réponse 302 au client
    // PUIS laissent PHP continuer côté serveur. On envoie le mail après ce
    // flush, donc l'envoi SMTP est hors du temps de réponse observable.
    $flush = function_exists('fastcgi_finish_request')
        ? 'fastcgi_finish_request'
        : (function_exists('litespeed_finish_request') ? 'litespeed_finish_request' : null);

    if ($flush) {
        $flush();
        if ($user) {
            ignore_user_abort(true);
            retrieve_password($user_login);
        }
        exit;
    }

    // Fallback (mod_php sans aucune fonction de flush) : on programme l'envoi
    // hors-requête via wp-cron. Seul écart restant pour un compte valide : une
    // écriture cron (~1 ms), très en-dessous du jitter réseau.
    if ($user && !wp_next_scheduled('olrl_send_reset', array($user_login))) {
        wp_schedule_single_event(time(), 'olrl_send_reset', array($user_login));
    }
    exit;
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
