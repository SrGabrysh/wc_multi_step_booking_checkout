<?php
/**
 * Gestionnaire des actions workflow
 * Responsabilité : Traitement des transitions entre étapes
 */

declare( strict_types=1 );

namespace TBFormation\WCMultiStepBookingCheckout\Modules\Workflow;

use TBFormation\WCMultiStepBookingCheckout\Core\Logger;
use TBFormation\WCMultiStepBookingCheckout\Modules\Session\SessionManager;

if ( ! defined( 'ABSPATH' ) ) exit;

class WorkflowHandler {
    
    private $logger;
    private $session_manager;
    
    public function __construct( Logger $logger, SessionManager $session_manager ) {
        $this->logger = $logger;
        $this->session_manager = $session_manager;
    }
    
    /**
     * Traitement de la complétion d'une étape
     */
    public function process_step_completion( int $step, array $step_data = array() ): array {
        $this->logger->info( 'Traitement complétion étape', array(
            'step' => $step,
            'has_data' => ! empty( $step_data )
        ) );
        
        // Validation des données d'étape
        if ( ! $this->validate_step_data( $step, $step_data ) ) {
            return array(
                'success' => false,
                'message' => __( 'Les données de l\'étape ne sont pas valides.', 'wc-multi-step-booking-checkout' ),
                'redirect_url' => ''
            );
        }
        
        // Complétion de l'étape
        $success = $this->session_manager->complete_step( $step, $step_data );
        
        if ( ! $success ) {
            return array(
                'success' => false,
                'message' => __( 'Erreur lors de la sauvegarde. Veuillez réessayer.', 'wc-multi-step-booking-checkout' ),
                'redirect_url' => ''
            );
        }
        
        // Détermination de l'URL de redirection
        $redirect_url = $this->get_next_step_url( $step );
        
        return array(
            'success' => true,
            'message' => __( 'Étape complétée avec succès.', 'wc-multi-step-booking-checkout' ),
            'redirect_url' => $redirect_url,
            'next_step' => $step + 1
        );
    }
    
    /**
     * Traitement du retour vers étape précédente
     */
    public function process_step_back( int $current_step ): array {
        if ( $current_step <= 1 ) {
            return array(
                'success' => false,
                'message' => __( 'Vous êtes déjà à la première étape.', 'wc-multi-step-booking-checkout' ),
                'redirect_url' => ''
            );
        }
        
        $previous_step = $current_step - 1;
        
        // Mise à jour de l'étape courante en session
        $session_data = $this->session_manager->get_session_data();
        $session_data['current_step'] = $previous_step;
        
        $success = $this->session_manager->update_session_data( $session_data );
        
        if ( ! $success ) {
            return array(
                'success' => false,
                'message' => __( 'Erreur lors du retour en arrière.', 'wc-multi-step-booking-checkout' ),
                'redirect_url' => ''
            );
        }
        
        $redirect_url = $this->get_step_url( $previous_step );
        
        return array(
            'success' => true,
            'message' => __( 'Retour à l\'étape précédente.', 'wc-multi-step-booking-checkout' ),
            'redirect_url' => $redirect_url,
            'previous_step' => $previous_step
        );
    }
    
    /**
     * Validation des données d'étape
     */
    private function validate_step_data( int $step, array $step_data ): bool {
        switch ( $step ) {
            case 1:
                return $this->validate_step_1_data( $step_data );
            case 2:
                return $this->validate_step_2_data( $step_data );
            case 3:
                return $this->validate_step_3_data( $step_data );
            case 4:
                return $this->validate_step_4_data( $step_data );
            default:
                return false;
        }
    }
    
    /**
     * Validation données étape 1
     */
    private function validate_step_1_data( array $step_data ): bool {
        // Étape 1 : Vérification panier
        if ( ! WC()->cart || WC()->cart->is_empty() ) {
            $this->logger->warning( 'Tentative complétion étape 1 avec panier vide' );
            return false;
        }
        
        return true;
    }
    
    /**
     * Validation données étape 2
     */
    private function validate_step_2_data( array $step_data ): bool {
        // Validation formulaire - champs requis
        $required_fields = array( 'field_1', 'field_2' );
        
        foreach ( $required_fields as $field ) {
            if ( empty( $step_data[ $field ] ) ) {
                $this->logger->warning( 'Champ requis manquant étape 2', array( 'field' => $field ) );
                return false;
            }
        }
        
        // Sanitisation des données
        foreach ( $step_data as $key => $value ) {
            if ( is_string( $value ) ) {
                $step_data[ $key ] = sanitize_text_field( $value );
            }
        }
        
        return true;
    }
    
    /**
     * Validation données étape 3
     */
    private function validate_step_3_data( array $step_data ): bool {
        // Validation signature - pour MVP on accepte un placeholder
        if ( empty( $step_data['signature_accepted'] ) ) {
            $this->logger->warning( 'Signature non acceptée étape 3' );
            return false;
        }
        
        return true;
    }
    
    /**
     * Validation données étape 4
     */
    private function validate_step_4_data( array $step_data ): bool {
        // Étape 4 : Validation finale avant checkout
        $session_data = $this->session_manager->get_session_data();
        $completed_steps = $session_data['steps_completed'] ?? array();
        
        // Vérification que toutes les étapes sont complétées
        $required_steps = array( 1, 2, 3 );
        foreach ( $required_steps as $required_step ) {
            if ( ! in_array( $required_step, $completed_steps, true ) ) {
                $this->logger->warning( 'Étape requise non complétée pour finalisation', array(
                    'missing_step' => $required_step
                ) );
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * URL de l'étape suivante
     */
    private function get_next_step_url( int $current_step ): string {
        $next_step = $current_step + 1;
        
        // Si étape 4 complétée, redirection vers checkout
        if ( $next_step > 4 ) {
            return wc_get_checkout_url();
        }
        
        return $this->get_step_url( $next_step );
    }
    
    /**
     * URL d'une étape spécifique
     */
    private function get_step_url( int $step ): string {
        $settings = get_option( 'wc_msbc_settings', array() );
        $workflow_pages = $settings['workflow_pages'] ?? array();
        $page_id = $workflow_pages[ "step_{$step}" ] ?? 0;
        
        if ( ! $page_id ) {
            return '';
        }
        
        return get_permalink( $page_id ) ?: '';
    }
}
