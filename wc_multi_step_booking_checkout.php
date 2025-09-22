<?php
/**
 * Plugin Name: WooCommerce Multi-Step Booking Checkout
 * Plugin URI: https://tb-web.fr
 * Description: Processus de checkout multi-étapes optimisé pour WooCommerce Bookings
 * Version: 1.0.0
 * Author: TB Formation
 * License: GPL v2 or later
 * Text Domain: wc-multi-step-booking-checkout
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * Requires Plugins: woocommerce, woocommerce-bookings
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) exit;

// Constantes
define( 'WC_MULTI_STEP_CHECKOUT_VERSION', '1.0.0' );
define( 'WC_MULTI_STEP_CHECKOUT_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_MULTI_STEP_CHECKOUT_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_MULTI_STEP_CHECKOUT_FILE', __FILE__ );

// Vérification des dépendances
add_action( 'admin_init', 'wc_multi_step_checkout_check_dependencies' );

function wc_multi_step_checkout_check_dependencies() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo __( 'WooCommerce Multi-Step Booking Checkout nécessite WooCommerce pour fonctionner.', 'wc-multi-step-booking-checkout' );
            echo '</p></div>';
        });
        return false;
    }
    
    if ( ! class_exists( 'WC_Bookings' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo __( 'WooCommerce Multi-Step Booking Checkout nécessite WooCommerce Bookings pour fonctionner.', 'wc-multi-step-booking-checkout' );
            echo '</p></div>';
        });
        return false;
    }
    
    return true;
}

// Hooks d'activation/désactivation
register_activation_hook( __FILE__, 'wc_multi_step_checkout_activate' );
register_deactivation_hook( __FILE__, 'wc_multi_step_checkout_deactivate' );

function wc_multi_step_checkout_activate() {
    // Actions lors de l'activation
    if ( ! wc_multi_step_checkout_check_dependencies() ) {
        wp_die( __( 'Ce plugin nécessite WooCommerce et WooCommerce Bookings pour fonctionner.', 'wc-multi-step-booking-checkout' ) );
    }
    
    // Créer les options par défaut
    add_option( 'wc_multi_step_checkout_version', WC_MULTI_STEP_CHECKOUT_VERSION );
    add_option( 'wc_multi_step_checkout_settings', array(
        'enabled' => 'yes',
        'steps' => array(
            'booking_details' => array( 'enabled' => 'yes', 'title' => __( 'Détails de réservation', 'wc-multi-step-booking-checkout' ) ),
            'customer_info' => array( 'enabled' => 'yes', 'title' => __( 'Informations client', 'wc-multi-step-booking-checkout' ) ),
            'payment' => array( 'enabled' => 'yes', 'title' => __( 'Paiement', 'wc-multi-step-booking-checkout' ) ),
            'confirmation' => array( 'enabled' => 'yes', 'title' => __( 'Confirmation', 'wc-multi-step-booking-checkout' ) )
        )
    ));
}

function wc_multi_step_checkout_deactivate() {
    // Actions lors de la désactivation
    // Ne pas supprimer les options pour conserver la configuration
}

// Initialisation du plugin
add_action( 'plugins_loaded', 'wc_multi_step_checkout_init' );

function wc_multi_step_checkout_init() {
    // Chargement de la traduction
    load_plugin_textdomain( 'wc-multi-step-booking-checkout', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    
    // Vérifier les dépendances
    if ( ! wc_multi_step_checkout_check_dependencies() ) {
        return;
    }
    
    // Initialiser le plugin principal
    if ( ! class_exists( 'WC_Multi_Step_Checkout' ) ) {
        require_once WC_MULTI_STEP_CHECKOUT_PATH . 'includes/class-wc-multi-step-checkout.php';
    }
    
    // Lancer le plugin
    WC_Multi_Step_Checkout::instance();
}

// Hook pour ajouter les liens d'action sur la page des plugins
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_multi_step_checkout_action_links' );

function wc_multi_step_checkout_action_links( $links ) {
    $settings_link = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=multi_step' ) . '">' . __( 'Paramètres', 'wc-multi-step-booking-checkout' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}

// Debug - Afficher que le plugin est chargé
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    add_action( 'wp_footer', function() {
        if ( is_checkout() || is_cart() ) {
            echo '<script>console.log("🛒 WC Multi-Step Checkout: Plugin chargé - Version ' . WC_MULTI_STEP_CHECKOUT_VERSION . '");</script>';
        }
    });
}
