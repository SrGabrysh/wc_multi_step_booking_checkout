<?php
/**
 * Validateur pour le workflow
 * Responsabilité : Validation des règles métier du workflow
 */

declare( strict_types=1 );

namespace TBFormation\WCMultiStepBookingCheckout\Modules\Workflow;

use TBFormation\WCMultiStepBookingCheckout\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) exit;

class WorkflowValidator {
    
    private $logger;
    
    public function __construct( Logger $logger ) {
        $this->logger = $logger;
    }
    
    /**
     * Vérification si le panier contient des produits bookables
     */
    public function cart_has_booking_products(): bool {
        if ( ! WC()->cart || WC()->cart->is_empty() ) {
            return false;
        }
        
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $product = $cart_item['data'];
            if ( $product && $product->is_type( 'booking' ) ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Détermination de l'étape autorisée pour l'utilisateur
     */
    public function get_allowed_step( array $session_data ): int {
        // Si pas de session, retour au panier
        if ( empty( $session_data ) ) {
            return 0; // Redirection vers panier
        }
        
        // Si pas de panier, retour au panier
        if ( ! $this->cart_has_booking_products() ) {
            return 0;
        }
        
        $current_step = $session_data['current_step'] ?? 1;
        $completed_steps = $session_data['steps_completed'] ?? array();
        
        // Vérification cohérence session
        if ( ! $this->is_session_coherent( $current_step, $completed_steps ) ) {
            $this->logger->warning( 'Session incohérente détectée', array(
                'current_step' => $current_step,
                'completed_steps' => $completed_steps
            ) );
            return 1; // Retour étape 1
        }
        
        return $current_step;
    }
    
    /**
     * Vérification de cohérence de session
     */
    private function is_session_coherent( int $current_step, array $completed_steps ): bool {
        // L'étape courante ne peut pas être inférieure au nombre d'étapes complétées + 1
        $expected_min_step = count( $completed_steps ) + 1;
        
        if ( $current_step < $expected_min_step ) {
            return false;
        }
        
        // Vérification séquence des étapes complétées
        for ( $i = 1; $i < $current_step; $i++ ) {
            if ( ! in_array( $i, $completed_steps, true ) ) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Validation qu'un utilisateur peut accéder à une étape
     */
    public function can_access_step( int $step, array $session_data ): bool {
        // Étape 1 toujours accessible si panier valide
        if ( $step === 1 ) {
            return $this->cart_has_booking_products();
        }
        
        $completed_steps = $session_data['steps_completed'] ?? array();
        
        // Pour accéder à une étape N, il faut avoir complété l'étape N-1
        return in_array( $step - 1, $completed_steps, true );
    }
    
    /**
     * Validation des prérequis d'une étape
     */
    public function validate_step_prerequisites( int $step ): bool {
        switch ( $step ) {
            case 1:
                return $this->validate_step_1_prerequisites();
            case 2:
                return $this->validate_step_2_prerequisites();
            case 3:
                return $this->validate_step_3_prerequisites();
            case 4:
                return $this->validate_step_4_prerequisites();
            default:
                return false;
        }
    }
    
    /**
     * Prérequis étape 1
     */
    private function validate_step_1_prerequisites(): bool {
        // Vérification WooCommerce actif
        if ( ! class_exists( 'WooCommerce' ) ) {
            return false;
        }
        
        // Vérification session disponible
        if ( ! WC()->session ) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Prérequis étape 2
     */
    private function validate_step_2_prerequisites(): bool {
        // Vérification étape 1 complétée
        if ( ! $this->cart_has_booking_products() ) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Prérequis étape 3
     */
    private function validate_step_3_prerequisites(): bool {
        // Mêmes prérequis que étape 2 pour MVP
        return $this->validate_step_2_prerequisites();
    }
    
    /**
     * Prérequis étape 4
     */
    private function validate_step_4_prerequisites(): bool {
        // Mêmes prérequis que étapes précédentes
        return $this->validate_step_3_prerequisites();
    }
    
    /**
     * Validation configuration du plugin
     */
    public function validate_plugin_configuration(): array {
        $errors = array();
        $settings = get_option( 'wc_msbc_settings', array() );
        
        // Vérification pages configurées
        $workflow_pages = $settings['workflow_pages'] ?? array();
        for ( $i = 1; $i <= 4; $i++ ) {
            $page_id = $workflow_pages[ "step_{$i}" ] ?? 0;
            if ( ! $page_id || get_post_status( $page_id ) !== 'publish' ) {
                $errors[] = sprintf( __( 'Page étape %d non configurée ou non publiée', 'wc-multi-step-booking-checkout' ), $i );
            }
        }
        
        // Vérification TTL session
        $session_ttl = $settings['session_ttl'] ?? 0;
        if ( $session_ttl < 300 || $session_ttl > 3600 ) {
            $errors[] = __( 'TTL session doit être entre 5 et 60 minutes', 'wc-multi-step-booking-checkout' );
        }
        
        return $errors;
    }
}
