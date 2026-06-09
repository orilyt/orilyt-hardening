<?php
/**
 * Plugin Name: Orilyt Security Hardening (installeur)
 * Description: Installe la protection Orilyt en mu-plugin (indésactivable depuis l'admin). Activer ce plugin = protection posée ; le désactiver = protection retirée.
 * Version: 1.1
 * Author: Orilyt
 */

if (!defined('ABSPATH')) exit;

define('ORILYT_HARDENING_VERSION', '1.1');
define('ORILYT_HARDENING_MU_FILE', '0-orilyt-hardening.php');

/**
 * Copie le mu-plugin bundlé vers mu-plugins. Retourne true si OK.
 */
function orilyt_hardening_install_mu() {
    $src     = __DIR__ . '/mu/' . ORILYT_HARDENING_MU_FILE;
    $dst_dir = defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
    if (!is_dir($dst_dir) && !wp_mkdir_p($dst_dir)) return false;
    return (bool) copy($src, $dst_dir . '/' . ORILYT_HARDENING_MU_FILE);
}

/**
 * Resync à la mise à jour : mettre à jour le plugin (wp-admin / git pull) ne
 * recopie pas le mu-plugin tout seul. On compare la version stockée à la
 * version bundlée et on recopie si elles diffèrent — sinon le parc resterait
 * sur l'ancienne protection malgré un plugin "à jour".
 */
add_action('admin_init', function () {
    if (get_option('orilyt_hardening_mu_version') !== ORILYT_HARDENING_VERSION) {
        if (orilyt_hardening_install_mu()) {
            update_option('orilyt_hardening_mu_version', ORILYT_HARDENING_VERSION);
        }
    }
});

register_activation_hook(__FILE__, function () {
    $dst_dir = defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
    if (!is_dir($dst_dir) && !wp_mkdir_p($dst_dir)) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Orilyt Hardening : impossible de créer ' . esc_html($dst_dir)
            . '. Vérifiez les droits d\'écriture, puis réactivez le plugin.');
    }
    if (!orilyt_hardening_install_mu()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Orilyt Hardening : copie vers mu-plugins impossible (droits d\'écriture ?).');
    }
    update_option('orilyt_hardening_mu_version', ORILYT_HARDENING_VERSION);
});

register_deactivation_hook(__FILE__, function () {
    $dst_dir = defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
    $file = $dst_dir . '/' . ORILYT_HARDENING_MU_FILE;
    if (file_exists($file)) @unlink($file);
    delete_option('orilyt_hardening_mu_version');
    // purge l'éventuel envoi de reset programmé (fallback non-FPM)
    wp_clear_scheduled_hook('olrl_send_reset');
});

// État visible dans la liste des extensions
add_filter('plugin_row_meta', function ($meta, $file) {
    if ($file === plugin_basename(__FILE__)) {
        $dst_dir = defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
        $actif = file_exists($dst_dir . '/' . ORILYT_HARDENING_MU_FILE);
        $meta[] = $actif
            ? '<span style="color:#2e7d32;font-weight:600;">● Protection mu-plugin active</span>'
            : '<span style="color:#c62828;font-weight:600;">● Protection absente — réactiver le plugin</span>';
    }
    return $meta;
}, 10, 2);
