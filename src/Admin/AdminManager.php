<?php
/**
 * Gestionnaire de l'interface d'administration
 * Responsabilité : Orchestration des pages d'admin et settings
 */

declare( strict_types=1 );

namespace TBFormation\WCMultiStepBookingCheckout\Admin;

use TBFormation\WCMultiStepBookingCheckout\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) exit;

class AdminManager {
    
    private $logger;
    private $modules;
    private $settings_renderer;
    
    public function __construct( Logger $logger, array $modules ) {
        $this->logger = $logger;
        $this->modules = $modules;
        $this->settings_renderer = new SettingsRenderer( $logger );
        
        $this->init_hooks();
    }
    
    /**
     * Initialisation des hooks d'administration
     */
    private function init_hooks() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        
        // Liens dans la liste des plugins
        add_filter( 'plugin_action_links_' . WC_MSBC_BASENAME, array( $this, 'add_plugin_links' ) );
        
        // Notice de configuration
        add_action( 'admin_notices', array( $this, 'show_configuration_notices' ) );
    }
    
    /**
     * Ajout du menu d'administration
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Multi-Step Booking Checkout', 'wc-multi-step-booking-checkout' ),
            __( 'Multi-Step Checkout', 'wc-multi-step-booking-checkout' ),
            'manage_woocommerce',
            'wc-multi-step-booking-checkout',
            array( $this->settings_renderer, 'render_settings_page' )
        );
        
        $this->logger->debug( 'Menu admin ajouté' );
    }
    
    /**
     * Enregistrement des paramètres
     */
    public function register_settings() {
        register_setting(
            'wc_msbc_settings_group',
            'wc_msbc_settings',
            array(
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
                'default' => $this->get_default_settings()
            )
        );
        
        // Section principale
        add_settings_section(
            'wc_msbc_main_section',
            __( 'Configuration du Workflow', 'wc-multi-step-booking-checkout' ),
            array( $this->settings_renderer, 'render_main_section' ),
            'wc-multi-step-booking-checkout'
        );
        
        // Champ pages du workflow
        add_settings_field(
            'workflow_pages',
            __( 'Pages du Workflow', 'wc-multi-step-booking-checkout' ),
            array( $this->settings_renderer, 'render_workflow_pages_field' ),
            'wc-multi-step-booking-checkout',
            'wc_msbc_main_section'
        );
        
        // Champ TTL session
        add_settings_field(
            'session_ttl',
            __( 'Durée de Session (minutes)', 'wc-multi-step-booking-checkout' ),
            array( $this->settings_renderer, 'render_session_ttl_field' ),
            'wc-multi-step-booking-checkout',
            'wc_msbc_main_section'
        );
        
        // Section debug
        add_settings_section(
            'wc_msbc_debug_section',
            __( 'Options de Debug', 'wc-multi-step-booking-checkout' ),
            array( $this->settings_renderer, 'render_debug_section' ),
            'wc-multi-step-booking-checkout'
        );
        
        // Champ mode debug
        add_settings_field(
            'debug_mode',
            __( 'Mode Debug', 'wc-multi-step-booking-checkout' ),
            array( $this->settings_renderer, 'render_debug_mode_field' ),
            'wc-multi-step-booking-checkout',
            'wc_msbc_debug_section'
        );
    }
    
    /**
     * Sanitisation des paramètres
     */
    public function sanitize_settings( array $input ): array {
        $sanitized = array();
        
        // Pages workflow
        if ( isset( $input['workflow_pages'] ) && is_array( $input['workflow_pages'] ) ) {
            $sanitized['workflow_pages'] = array();
            for ( $i = 1; $i <= 4; $i++ ) {
                $page_id = absint( $input['workflow_pages'][ "step_{$i}" ] ?? 0 );
                if ( $page_id > 0 ) {
                    $sanitized['workflow_pages'][ "step_{$i}" ] = $page_id;
                }
            }
        }
        
        // TTL session
        $session_ttl = absint( $input['session_ttl'] ?? 1200 );
        $sanitized['session_ttl'] = max( 300, min( 3600, $session_ttl ) ); // Entre 5 et 60 min
        
        // Redirection checkout
        $sanitized['redirect_checkout'] = ! empty( $input['redirect_checkout'] );
        
        // Version wizard
        $sanitized['wizard_version'] = sanitize_text_field( $input['wizard_version'] ?? '1.0' );
        
        // Debug
        $sanitized['debug_mode'] = ! empty( $input['debug_mode'] );
        $sanitized['log_level'] = in_array( $input['log_level'] ?? 'info', array( 'debug', 'info', 'warning', 'error' ), true )
            ? $input['log_level']
            : 'info';
        
        $this->logger->info( 'Paramètres sauvegardés', array(
            'workflow_pages' => count( $sanitized['workflow_pages'] ?? array() ),
            'session_ttl' => $sanitized['session_ttl'],
            'debug_mode' => $sanitized['debug_mode']
        ) );
        
        return $sanitized;
    }
    
    /**
     * Chargement des assets admin
     */
    public function enqueue_admin_assets( string $hook_suffix ) {
        // Seulement sur notre page de settings
        if ( $hook_suffix !== 'woocommerce_page_wc-multi-step-booking-checkout' ) {
            return;
        }
        
        wp_enqueue_style(
            'wc-msbc-admin',
            WC_MSBC_URL . 'assets/css/admin.css',
            array(),
            WC_MSBC_VERSION
        );
        
        wp_enqueue_script(
            'wc-msbc-admin',
            WC_MSBC_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            WC_MSBC_VERSION,
            true
        );
        
        wp_localize_script( 'wc-msbc-admin', 'wcMsbcAdmin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'wc_msbc_admin' ),
            'messages' => array(
                'saved' => __( 'Paramètres sauvegardés avec succès.', 'wc-multi-step-booking-checkout' ),
                'error' => __( 'Erreur lors de la sauvegarde.', 'wc-multi-step-booking-checkout' )
            )
        ) );
    }
    
    /**
     * Liens dans la liste des plugins
     */
    public function add_plugin_links( array $links ): array {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'admin.php?page=wc-multi-step-booking-checkout' ),
            __( 'Paramètres', 'wc-multi-step-booking-checkout' )
        );
        
        array_unshift( $links, $settings_link );
        
        return $links;
    }
    
    /**
     * Notices de configuration
     */
    public function show_configuration_notices() {
        // Vérification si sur une page WooCommerce
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'woocommerce' ) === false ) {
            return;
        }
        
        $settings = get_option( 'wc_msbc_settings', array() );
        $workflow_pages = $settings['workflow_pages'] ?? array();
        
        // Vérification pages configurées
        $missing_pages = array();
        for ( $i = 1; $i <= 4; $i++ ) {
            $page_id = $workflow_pages[ "step_{$i}" ] ?? 0;
            if ( ! $page_id || get_post_status( $page_id ) !== 'publish' ) {
                $missing_pages[] = $i;
            }
        }
        
        if ( ! empty( $missing_pages ) ) {
            $message = sprintf(
                __( 'WC Multi-Step Booking Checkout : Veuillez configurer les pages pour les étapes : %s. <a href="%s">Configurer maintenant</a>', 'wc-multi-step-booking-checkout' ),
                implode( ', ', $missing_pages ),
                admin_url( 'admin.php?page=wc-multi-step-booking-checkout' )
            );
            
            echo '<div class="notice notice-warning is-dismissible"><p>' . $message . '</p></div>';
        }
    }
    
    /**
     * Paramètres par défaut
     */
    private function get_default_settings(): array {
        return array(
            'workflow_pages' => array(
                'step_1' => 4267,
                'step_2' => 4279,
                'step_3' => 4284,
                'step_4' => 4291
            ),
            'session_ttl' => 1200,
            'redirect_checkout' => true,
            'wizard_version' => '1.0',
            'debug_mode' => false,
            'log_level' => 'info'
        );
    }
}
