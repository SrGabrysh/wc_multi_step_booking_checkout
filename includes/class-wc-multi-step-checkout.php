<?php
/**
 * Classe principale du plugin WC Multi-Step Checkout
 *
 * @package WC_Multi_Step_Checkout
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WC_Multi_Step_Checkout {

    /**
     * Instance unique du plugin
     *
     * @var WC_Multi_Step_Checkout
     */
    private static $instance = null;

    /**
     * Version du plugin
     *
     * @var string
     */
    public $version;

    /**
     * Paramètres du plugin
     *
     * @var array
     */
    public $settings;

    /**
     * Constructeur
     */
    private function __construct() {
        $this->version = WC_MULTI_STEP_CHECKOUT_VERSION;
        $this->init();
    }

    /**
     * Obtenir l'instance unique
     *
     * @return WC_Multi_Step_Checkout
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialisation du plugin
     */
    private function init() {
        // Charger les paramètres
        $this->settings = get_option( 'wc_multi_step_checkout_settings', array() );

        // Hooks d'initialisation
        add_action( 'init', array( $this, 'init_hooks' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

        // Hooks WooCommerce
        add_action( 'woocommerce_init', array( $this, 'woocommerce_init' ) );

        // Hooks admin
        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this, 'admin_menu' ) );
            add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_settings_page' ) );
        }
    }

    /**
     * Initialisation des hooks
     */
    public function init_hooks() {
        // Vérifier si le plugin est activé
        if ( ! $this->is_enabled() ) {
            return;
        }

        // Hooks pour modifier le checkout
        add_action( 'wp_loaded', array( $this, 'setup_checkout_hooks' ) );
    }

    /**
     * Initialisation WooCommerce
     */
    public function woocommerce_init() {
        if ( ! $this->is_enabled() ) {
            return;
        }

        // Hooks spécifiques à WooCommerce
        add_filter( 'woocommerce_checkout_fields', array( $this, 'modify_checkout_fields' ) );
    }

    /**
     * Vérifier si le plugin est activé
     *
     * @return bool
     */
    public function is_enabled() {
        return isset( $this->settings['enabled'] ) && $this->settings['enabled'] === 'yes';
    }

    /**
     * Configuration des hooks de checkout
     */
    public function setup_checkout_hooks() {
        if ( ! is_admin() && ( is_checkout() || is_cart() ) ) {
            // Hooks pour modifier l'apparence du checkout
            add_action( 'woocommerce_before_checkout_form', array( $this, 'add_checkout_steps' ), 5 );
        }
    }

    /**
     * Ajouter les étapes au checkout
     */
    public function add_checkout_steps() {
        if ( ! $this->is_enabled() ) {
            return;
        }

        $steps = $this->get_checkout_steps();
        if ( empty( $steps ) ) {
            return;
        }

        echo '<div class="wc-multi-step-checkout-wrapper">';
        echo '<div class="wc-multi-step-progress">';
        
        $current_step = 1;
        $step_number = 1;
        
        foreach ( $steps as $step_key => $step_data ) {
            if ( ! isset( $step_data['enabled'] ) || $step_data['enabled'] !== 'yes' ) {
                continue;
            }
            
            $class = 'step';
            if ( $step_number === $current_step ) {
                $class .= ' active';
            }
            
            echo '<div class="' . esc_attr( $class ) . '">';
            echo '<span class="step-number">' . esc_html( $step_number ) . '</span>';
            echo '<span class="step-title">' . esc_html( $step_data['title'] ) . '</span>';
            echo '</div>';
            
            $step_number++;
        }
        
        echo '</div>'; // .wc-multi-step-progress
        echo '</div>'; // .wc-multi-step-checkout-wrapper
    }

    /**
     * Obtenir les étapes configurées
     *
     * @return array
     */
    public function get_checkout_steps() {
        $default_steps = array(
            'booking_details' => array( 
                'enabled' => 'yes', 
                'title' => __( 'Détails de réservation', 'wc-multi-step-booking-checkout' ) 
            ),
            'customer_info' => array( 
                'enabled' => 'yes', 
                'title' => __( 'Informations client', 'wc-multi-step-booking-checkout' ) 
            ),
            'payment' => array( 
                'enabled' => 'yes', 
                'title' => __( 'Paiement', 'wc-multi-step-booking-checkout' ) 
            ),
            'confirmation' => array( 
                'enabled' => 'yes', 
                'title' => __( 'Confirmation', 'wc-multi-step-booking-checkout' ) 
            )
        );

        return isset( $this->settings['steps'] ) ? $this->settings['steps'] : $default_steps;
    }

    /**
     * Modifier les champs de checkout
     *
     * @param array $fields
     * @return array
     */
    public function modify_checkout_fields( $fields ) {
        // Modifications des champs selon les étapes
        return $fields;
    }

    /**
     * Charger les scripts frontend
     */
    public function enqueue_scripts() {
        if ( ! is_checkout() && ! is_cart() ) {
            return;
        }

        wp_enqueue_style( 
            'wc-multi-step-checkout', 
            WC_MULTI_STEP_CHECKOUT_URL . 'assets/css/frontend.css', 
            array(), 
            $this->version 
        );

        wp_enqueue_script( 
            'wc-multi-step-checkout', 
            WC_MULTI_STEP_CHECKOUT_URL . 'assets/js/frontend.js', 
            array( 'jquery' ), 
            $this->version, 
            true 
        );

        // Variables JavaScript
        wp_localize_script( 'wc-multi-step-checkout', 'wcMultiStepCheckout', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'wc_multi_step_checkout' ),
            'steps' => $this->get_checkout_steps(),
            'current_step' => 1
        ));
    }

    /**
     * Charger les scripts admin
     *
     * @param string $hook
     */
    public function admin_enqueue_scripts( $hook ) {
        if ( strpos( $hook, 'woocommerce' ) === false ) {
            return;
        }

        wp_enqueue_style( 
            'wc-multi-step-checkout-admin', 
            WC_MULTI_STEP_CHECKOUT_URL . 'assets/css/admin.css', 
            array(), 
            $this->version 
        );
    }

    /**
     * Ajouter le menu admin
     */
    public function admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Multi-Step Checkout', 'wc-multi-step-booking-checkout' ),
            __( 'Multi-Step Checkout', 'wc-multi-step-booking-checkout' ),
            'manage_woocommerce',
            'wc-multi-step-checkout',
            array( $this, 'admin_page' )
        );
    }

    /**
     * Page d'administration
     */
    public function admin_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __( 'WooCommerce Multi-Step Checkout', 'wc-multi-step-booking-checkout' ) . '</h1>';
        echo '<p>' . __( 'Configuration du processus de checkout multi-étapes.', 'wc-multi-step-booking-checkout' ) . '</p>';
        echo '<p><strong>' . __( 'Version:', 'wc-multi-step-booking-checkout' ) . '</strong> ' . esc_html( $this->version ) . '</p>';
        echo '</div>';
    }

    /**
     * Ajouter la page de paramètres WooCommerce
     *
     * @param array $settings
     * @return array
     */
    public function add_settings_page( $settings ) {
        // Ajouter la page de paramètres si nécessaire
        return $settings;
    }

    /**
     * Obtenir la version du plugin
     *
     * @return string
     */
    public function get_version() {
        return $this->version;
    }
}
