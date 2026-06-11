<?php
/**
 * Plugin Name: GrossistKit
 * Plugin URI:  https://linmadgross.se
 * GitHub Plugin URI: patrykszczepaniks1-cloud/grossist-kit
 * Description: B2B management suite for WooCommerce — customer groups, pricing, quick orders, and signup approvals.
 * Version:     1.0.0
 * Author:      Patryk Szczepanik
 * License:     GPL-2.0+
 * Text Domain: grossist-kit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─── Constants ───────────────────────────────────────────────────────────────
define( 'GK_VERSION',      '1.0.0' );
define( 'GK_PLUGIN_DIR',   plugin_dir_path( __FILE__ ) );
define( 'GK_PLUGIN_URL',   plugin_dir_url( __FILE__ ) );
define( 'GK_TEXT_DOMAIN',  'grossist-kit' );

// Customer group meta keys — same as linmad-kundgrupper so no data migration needed
define( 'GK_USER_META_KEY',    'lg_kundgrupp' );
define( 'GK_PRICE_META_PREFIX', 'lg_pris_' );

// Signup post type
define( 'GK_SIGNUP_POST_TYPE', 'gk_signup' );

// ─── Load includes ───────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>GrossistKit</strong> requires WooCommerce to be active.</p></div>';
        } );
        return;
    }

    require_once GK_PLUGIN_DIR . 'includes/class-customer-groups.php';
    require_once GK_PLUGIN_DIR . 'includes/class-org-number.php';
    require_once GK_PLUGIN_DIR . 'includes/class-signups.php';
    require_once GK_PLUGIN_DIR . 'includes/class-admin-menu.php';

    new GK_Customer_Groups();
    new GK_Org_Number();
    new GK_Signups();
    new GK_Admin_Menu();
} );

// ─── Activation ──────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, function () {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-signups.php';
    GK_Signups::register_post_type();
    flush_rewrite_rules();
} );
