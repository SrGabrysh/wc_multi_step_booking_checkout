<?php
/**
 * Plugin Name: WooCommerce Multi-Step Booking Checkout
 * Plugin URI: https://tb-formation.fr
 * Description: Plugin WordPress pour créer un workflow multi-étapes avec WooCommerce Bookings
 * Version: 1.1.0
 * Author: TB Formation
 * Text Domain: wc-multi-step-booking-checkout
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) exit;

// Constantes du plugin
define( 'WC_MSBC_VERSION', '1.1.0' );
define( 'WC_MSBC_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_MSBC_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_MSBC_BASENAME', plugin_basename( __FILE__ ) );

// Autoload Composer
require_once WC_MSBC_PATH . 'vendor/autoload.php';

// Hooks d'activation/désactivation
register_activation_hook( __FILE__, array( 'TBFormation\WCMultiStepBookingCheckout\Core\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TBFormation\WCMultiStepBookingCheckout\Core\Plugin', 'deactivate' ) );

// Initialisation du plugin
add_action( 'plugins_loaded', function() {
    // Vérification des dépendances
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>' . 
                 esc_html__( 'WooCommerce Multi-Step Booking Checkout nécessite WooCommerce pour fonctionner.', 'wc-multi-step-booking-checkout' ) . 
                 '</p></div>';
        } );
        return;
    }
    
    // Chargement des traductions
    load_plugin_textdomain( 'wc-multi-step-booking-checkout', false, dirname( WC_MSBC_BASENAME ) . '/languages' );
    
    // Lancement du plugin
    TBFormation\WCMultiStepBookingCheckout\Core\Plugin::instance();
} );
