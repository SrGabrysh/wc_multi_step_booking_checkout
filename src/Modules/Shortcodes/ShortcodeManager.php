<?php
/**
 * Gestionnaire des shortcodes pour Elementor
 * Responsabilité : Orchestration et enregistrement des shortcodes
 */

declare( strict_types=1 );

namespace TBFormation\WCMultiStepBookingCheckout\Modules\Shortcodes;

use TBFormation\WCMultiStepBookingCheckout\Core\Logger;
use TBFormation\WCMultiStepBookingCheckout\Modules\Workflow\WorkflowManager;
use TBFormation\WCMultiStepBookingCheckout\Modules\Session\SessionManager;

if ( ! defined( 'ABSPATH' ) ) exit;

class ShortcodeManager {
    
    private $logger;
    private $workflow_manager;
    private $session_manager;
    private $shortcode_renderer;
    
    public function __construct( Logger $logger, WorkflowManager $workflow_manager, SessionManager $session_manager ) {
        $this->logger = $logger;
        $this->workflow_manager = $workflow_manager;
        $this->session_manager = $session_manager;
        $this->shortcode_renderer = new ShortcodeRenderer( $logger, $workflow_manager );
        
        $this->register_shortcodes();
        $this->init_ajax_handlers();
    }
    
    /**
     * Enregistrement des shortcodes
     */
    private function register_shortcodes() {
        add_shortcode( 'wcmsbc_progress', array( $this, 'render_progress_bar' ) );
        add_shortcode( 'wcmsbc_next', array( $this, 'render_next_button' ) );
        add_shortcode( 'wcmsbc_prev', array( $this, 'render_prev_button' ) );
        add_shortcode( 'wcmsbc_step_guard', array( $this, 'render_step_guard' ) );
        
        $this->logger->debug( 'Shortcodes enregistrés', array(
            'shortcodes' => array( 'wcmsbc_progress', 'wcmsbc_next', 'wcmsbc_prev', 'wcmsbc_step_guard' )
        ) );
    }
    
    /**
     * Initialisation des handlers AJAX
     */
    private function init_ajax_handlers() {
        add_action( 'wp_ajax_wc_msbc_next_step', array( $this, 'handle_next_step_ajax' ) );
        add_action( 'wp_ajax_nopriv_wc_msbc_next_step', array( $this, 'handle_next_step_ajax' ) );
        
        add_action( 'wp_ajax_wc_msbc_prev_step', array( $this, 'handle_prev_step_ajax' ) );
        add_action( 'wp_ajax_nopriv_wc_msbc_prev_step', array( $this, 'handle_prev_step_ajax' ) );
        
        // Enqueue assets sur pages workflow
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }
    
    /**
     * Shortcode barre de progression
     */
    public function render_progress_bar( $atts ) {
        $atts = shortcode_atts( array(
            'show_labels' => 'true',
            'show_percentage' => 'false',
            'class' => ''
        ), $atts );
        
        return $this->shortcode_renderer->render_progress_bar( $atts );
    }
    
    /**
     * Shortcode bouton suivant
     */
    public function render_next_button( $atts ) {
        $atts = shortcode_atts( array(
            'text' => __( 'Suivant', 'wc-multi-step-booking-checkout' ),
            'class' => 'btn btn-primary',
            'validate' => 'true'
        ), $atts );
        
        return $this->shortcode_renderer->render_next_button( $atts );
    }
    
    /**
     * Shortcode bouton précédent
     */
    public function render_prev_button( $atts ) {
        $atts = shortcode_atts( array(
            'text' => __( 'Précédent', 'wc-multi-step-booking-checkout' ),
            'class' => 'btn btn-secondary',
            'show_on_step_1' => 'false'
        ), $atts );
        
        return $this->shortcode_renderer->render_prev_button( $atts );
    }
    
    /**
     * Shortcode garde d'étape (protection contenu)
     */
    public function render_step_guard( $atts, $content = '' ) {
        $atts = shortcode_atts( array(
            'step' => '1',
            'message' => __( 'Vous devez compléter les étapes précédentes.', 'wc-multi-step-booking-checkout' )
        ), $atts );
        
        return $this->shortcode_renderer->render_step_guard( $atts, $content );
    }
    
    /**
     * Handler AJAX étape suivante
     */
    public function handle_next_step_ajax() {
        // Vérification nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'wc_msbc_next_step' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Token de sécurité invalide.', 'wc-multi-step-booking-checkout' )
            ) );
        }
        
        $current_step = absint( $_POST['current_step'] ?? 0 );
        $step_data = $_POST['step_data'] ?? array();
        
        // Sanitisation des données
        $step_data = $this->sanitize_step_data( $step_data );
        
        $this->logger->info( 'AJAX next step', array(
            'current_step' => $current_step,
            'data_keys' => array_keys( $step_data )
        ) );
        
        // Traitement via WorkflowManager
        $result = $this->workflow_manager->advance_to_next_step( $current_step, $step_data );
        
        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }
    
    /**
     * Handler AJAX étape précédente
     */
    public function handle_prev_step_ajax() {
        // Vérification nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'wc_msbc_prev_step' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Token de sécurité invalide.', 'wc-multi-step-booking-checkout' )
            ) );
        }
        
        $current_step = absint( $_POST['current_step'] ?? 0 );
        
        $this->logger->info( 'AJAX prev step', array(
            'current_step' => $current_step
        ) );
        
        // Traitement via WorkflowManager
        $result = $this->workflow_manager->go_to_previous_step( $current_step );
        
        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }
    
    /**
     * Chargement des assets
     */
    public function enqueue_assets() {
        if ( ! $this->is_workflow_page() ) {
            return;
        }
        
        wp_enqueue_script(
            'wc-msbc-workflow',
            WC_MSBC_URL . 'assets/js/workflow.js',
            array( 'jquery' ),
            WC_MSBC_VERSION,
            true
        );
        
        wp_enqueue_style(
            'wc-msbc-workflow',
            WC_MSBC_URL . 'assets/css/workflow.css',
            array(),
            WC_MSBC_VERSION
        );
        
        // Variables JavaScript
        wp_localize_script( 'wc-msbc-workflow', 'wcMsbcAjax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'next_nonce' => wp_create_nonce( 'wc_msbc_next_step' ),
            'prev_nonce' => wp_create_nonce( 'wc_msbc_prev_step' ),
            'messages' => array(
                'loading' => __( 'Traitement en cours...', 'wc-multi-step-booking-checkout' ),
                'error' => __( 'Une erreur est survenue.', 'wc-multi-step-booking-checkout' )
            )
        ) );
    }
    
    /**
     * Vérification si on est sur une page workflow
     */
    private function is_workflow_page(): bool {
        if ( ! is_page() ) {
            return false;
        }
        
        $current_page_id = get_queried_object_id();
        $settings = get_option( 'wc_msbc_settings', array() );
        $workflow_pages = $settings['workflow_pages'] ?? array();
        
        return in_array( $current_page_id, $workflow_pages, true );
    }
    
    /**
     * Sanitisation des données d'étape
     */
    private function sanitize_step_data( array $data ): array {
        $sanitized = array();
        
        foreach ( $data as $key => $value ) {
            $key = sanitize_key( $key );
            
            if ( is_array( $value ) ) {
                $sanitized[ $key ] = $this->sanitize_step_data( $value );
            } elseif ( is_string( $value ) ) {
                $sanitized[ $key ] = sanitize_text_field( $value );
            } else {
                $sanitized[ $key ] = $value;
            }
        }
        
        return $sanitized;
    }
}
