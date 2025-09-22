<?php
/**
 * Gestionnaire de session pour le workflow
 * Responsabilité : Orchestration des données de session
 */

declare( strict_types=1 );

namespace TBFormation\WCMultiStepBookingCheckout\Modules\Session;

use TBFormation\WCMultiStepBookingCheckout\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) exit;

class SessionManager {
    
    private $logger;
    private $session_handler;
    private $session_validator;
    
    public function __construct( Logger $logger ) {
        $this->logger = $logger;
        $this->session_handler = new SessionHandler( $logger );
        $this->session_validator = new SessionValidator( $logger );
        
        $this->init_hooks();
    }
    
    /**
     * Initialisation des hooks
     */
    private function init_hooks() {
        // Nettoyage automatique des sessions expirées
        add_action( 'wc_msbc_cleanup_sessions', array( $this->session_handler, 'cleanup_expired_sessions' ) );
        
        // Hook lors de la création de commande
        add_action( 'woocommerce_checkout_create_order', array( $this, 'transfer_session_to_order' ), 10, 2 );
    }
    
    /**
     * Démarrage d'une nouvelle session wizard
     */
    public function start_wizard_session(): bool {
        if ( ! WC()->session ) {
            $this->logger->error( 'Session WooCommerce non disponible' );
            return false;
        }
        
        $session_data = array(
            'wizard_started' => time(),
            'current_step' => 1,
            'steps_completed' => array(),
            'form_data' => array(),
            'signature_data' => array(),
            'wizard_version' => $this->get_wizard_version()
        );
        
        return $this->session_handler->set_wizard_data( $session_data );
    }
    
    /**
     * Récupération des données de session
     */
    public function get_session_data(): array {
        return $this->session_handler->get_wizard_data();
    }
    
    /**
     * Mise à jour des données de session
     */
    public function update_session_data( array $data ): bool {
        return $this->session_handler->update_wizard_data( $data );
    }
    
    /**
     * Validation d'une étape
     */
    public function validate_step( int $step ): bool {
        return $this->session_validator->validate_step_data( $step, $this->get_session_data() );
    }
    
    /**
     * Marquage d'une étape comme complétée
     */
    public function complete_step( int $step, array $step_data = array() ): bool {
        $session_data = $this->get_session_data();
        
        if ( ! $this->session_validator->can_complete_step( $step, $session_data ) ) {
            $this->logger->warning( 'Tentative de complétion d\'étape non autorisée', array(
                'step' => $step,
                'current_step' => $session_data['current_step'] ?? 0
            ) );
            return false;
        }
        
        // Mise à jour des données
        $session_data['steps_completed'][] = $step;
        $session_data['current_step'] = $step + 1;
        
        // Stockage des données spécifiques à l'étape
        if ( ! empty( $step_data ) ) {
            $session_data = $this->merge_step_data( $session_data, $step, $step_data );
        }
        
        $result = $this->update_session_data( $session_data );
        
        if ( $result ) {
            $this->logger->info( 'Étape complétée', array(
                'step' => $step,
                'next_step' => $session_data['current_step']
            ) );
        }
        
        return $result;
    }
    
    /**
     * Vérification si le wizard est complété
     */
    public function is_wizard_complete(): bool {
        $session_data = $this->get_session_data();
        $completed_steps = $session_data['steps_completed'] ?? array();
        
        return count( $completed_steps ) >= 4 && in_array( 4, $completed_steps, true );
    }
    
    /**
     * Transfert des données vers la commande
     */
    public function transfer_session_to_order( $order, $data ) {
        if ( ! $this->is_wizard_complete() ) {
            return;
        }
        
        $session_data = $this->get_session_data();
        
        // Métadonnées de commande
        $order->add_meta_data( '_tb_form_data', $session_data['form_data'] ?? array() );
        $order->add_meta_data( '_tb_signature', $session_data['signature_data'] ?? array() );
        $order->add_meta_data( '_tb_wizard_version', $session_data['wizard_version'] ?? '1.0' );
        
        // Note de commande
        $order->add_order_note( __( 'Commande créée via Wizard multi-étapes', 'wc-multi-step-booking-checkout' ) );
        
        $this->logger->info( 'Données transférées vers commande', array(
            'order_id' => $order->get_id()
        ) );
    }
    
    /**
     * Fusion des données d'étape
     */
    private function merge_step_data( array $session_data, int $step, array $step_data ): array {
        switch ( $step ) {
            case 2:
                $session_data['form_data'] = array_merge( 
                    $session_data['form_data'] ?? array(), 
                    $step_data 
                );
                break;
                
            case 3:
                $session_data['signature_data'] = array_merge(
                    $session_data['signature_data'] ?? array(),
                    array(
                        'timestamp' => time(),
                        'ip_address' => $this->get_client_ip(),
                        'contract_version' => $this->get_wizard_version()
                    ),
                    $step_data
                );
                break;
        }
        
        return $session_data;
    }
    
    /**
     * Version du wizard
     */
    private function get_wizard_version(): string {
        $settings = get_option( 'wc_msbc_settings', array() );
        return $settings['wizard_version'] ?? '1.0';
    }
    
    /**
     * IP du client
     */
    private function get_client_ip(): string {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
