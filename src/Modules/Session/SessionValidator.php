<?php
/**
 * Validateur de session pour le workflow
 * Responsabilité : Validation des données et règles métier de session
 */

declare( strict_types=1 );

namespace TBFormation\WCMultiStepBookingCheckout\Modules\Session;

use TBFormation\WCMultiStepBookingCheckout\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) exit;

class SessionValidator {
    
    private $logger;
    
    public function __construct( Logger $logger ) {
        $this->logger = $logger;
    }
    
    /**
     * Validation des données d'une étape
     */
    public function validate_step_data( int $step, array $session_data ): bool {
        if ( empty( $session_data ) ) {
            $this->logger->warning( 'Session vide pour validation étape', array( 'step' => $step ) );
            return false;
        }
        
        switch ( $step ) {
            case 1:
                return $this->validate_step_1( $session_data );
            case 2:
                return $this->validate_step_2( $session_data );
            case 3:
                return $this->validate_step_3( $session_data );
            case 4:
                return $this->validate_step_4( $session_data );
            default:
                return false;
        }
    }
    
    /**
     * Vérification si une étape peut être complétée
     */
    public function can_complete_step( int $step, array $session_data ): bool {
        $current_step = $session_data['current_step'] ?? 1;
        $completed_steps = $session_data['steps_completed'] ?? array();
        
        // Vérification ordre séquentiel
        if ( $step !== $current_step ) {
            $this->logger->warning( 'Tentative de complétion étape hors séquence', array(
                'requested_step' => $step,
                'current_step' => $current_step
            ) );
            return false;
        }
        
        // Vérification que l'étape précédente est complétée
        if ( $step > 1 && ! in_array( $step - 1, $completed_steps, true ) ) {
            $this->logger->warning( 'Étape précédente non complétée', array(
                'step' => $step,
                'completed_steps' => $completed_steps
            ) );
            return false;
        }
        
        return true;
    }
    
    /**
     * Validation étape 1 - Sélection produit/dates
     */
    private function validate_step_1( array $session_data ): bool {
        // Vérification panier non vide
        if ( ! WC()->cart || WC()->cart->is_empty() ) {
            $this->logger->info( 'Panier vide pour étape 1' );
            return false;
        }
        
        // Vérification présence produit bookable
        $has_booking_product = false;
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $product = $cart_item['data'];
            if ( $product && $product->is_type( 'booking' ) ) {
                $has_booking_product = true;
                break;
            }
        }
        
        if ( ! $has_booking_product ) {
            $this->logger->info( 'Aucun produit bookable dans le panier' );
            return false;
        }
        
        return true;
    }
    
    /**
     * Validation étape 2 - Formulaire
     */
    private function validate_step_2( array $session_data ): bool {
        $form_data = $session_data['form_data'] ?? array();
        
        if ( empty( $form_data ) ) {
            $this->logger->info( 'Données formulaire manquantes pour étape 2' );
            return false;
        }
        
        // Validation champs requis (exemple pour test)
        $required_fields = array( 'field_1', 'field_2' );
        foreach ( $required_fields as $field ) {
            if ( empty( $form_data[ $field ] ) ) {
                $this->logger->info( 'Champ requis manquant', array( 'field' => $field ) );
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Validation étape 3 - Signature
     */
    private function validate_step_3( array $session_data ): bool {
        $signature_data = $session_data['signature_data'] ?? array();
        
        if ( empty( $signature_data ) ) {
            $this->logger->info( 'Données signature manquantes pour étape 3' );
            return false;
        }
        
        // Validation données signature
        $required_signature_fields = array( 'timestamp', 'ip_address' );
        foreach ( $required_signature_fields as $field ) {
            if ( empty( $signature_data[ $field ] ) ) {
                $this->logger->info( 'Champ signature requis manquant', array( 'field' => $field ) );
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Validation étape 4 - Prêt pour checkout
     */
    private function validate_step_4( array $session_data ): bool {
        $completed_steps = $session_data['steps_completed'] ?? array();
        
        // Vérification que toutes les étapes précédentes sont complétées
        $required_steps = array( 1, 2, 3 );
        foreach ( $required_steps as $step ) {
            if ( ! in_array( $step, $completed_steps, true ) ) {
                $this->logger->info( 'Étape requise non complétée pour étape 4', array( 'missing_step' => $step ) );
                return false;
            }
        }
        
        // Vérification données complètes
        if ( empty( $session_data['form_data'] ) || empty( $session_data['signature_data'] ) ) {
            $this->logger->info( 'Données incomplètes pour étape 4' );
            return false;
        }
        
        return true;
    }
}
