<?php
/**
 * Gestionnaire de session WooCommerce
 * Responsabilité : Manipulation directe des données de session
 */

declare( strict_types=1 );

namespace TBFormation\WCMultiStepBookingCheckout\Modules\Session;

use TBFormation\WCMultiStepBookingCheckout\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) exit;

class SessionHandler {
    
    private const SESSION_KEY = 'wc_msbc_wizard_data';
    private $logger;
    
    public function __construct( Logger $logger ) {
        $this->logger = $logger;
    }
    
    /**
     * Récupération des données wizard de la session
     */
    public function get_wizard_data(): array {
        if ( ! $this->is_session_available() ) {
            return array();
        }
        
        $data = WC()->session->get( self::SESSION_KEY, array() );
        
        // Vérification de l'expiration
        if ( $this->is_session_expired( $data ) ) {
            $this->clear_wizard_data();
            return array();
        }
        
        return $data;
    }
    
    /**
     * Définition des données wizard
     */
    public function set_wizard_data( array $data ): bool {
        if ( ! $this->is_session_available() ) {
            $this->logger->error( 'Session WooCommerce non disponible pour set_wizard_data' );
            return false;
        }
        
        // Ajout du timestamp d'expiration
        $data['expires_at'] = time() + $this->get_session_ttl();
        
        WC()->session->set( self::SESSION_KEY, $data );
        
        $this->logger->debug( 'Données wizard définies en session', array(
            'expires_at' => $data['expires_at'],
            'data_keys' => array_keys( $data )
        ) );
        
        return true;
    }
    
    /**
     * Mise à jour des données wizard
     */
    public function update_wizard_data( array $new_data ): bool {
        $current_data = $this->get_wizard_data();
        
        if ( empty( $current_data ) ) {
            return $this->set_wizard_data( $new_data );
        }
        
        $merged_data = array_merge( $current_data, $new_data );
        return $this->set_wizard_data( $merged_data );
    }
    
    /**
     * Suppression des données wizard
     */
    public function clear_wizard_data(): bool {
        if ( ! $this->is_session_available() ) {
            return false;
        }
        
        WC()->session->__unset( self::SESSION_KEY );
        
        $this->logger->debug( 'Données wizard supprimées de la session' );
        
        return true;
    }
    
    /**
     * Vérification de disponibilité de la session
     */
    private function is_session_available(): bool {
        return WC() && WC()->session && WC()->session->get_customer_id();
    }
    
    /**
     * Vérification d'expiration de session
     */
    private function is_session_expired( array $data ): bool {
        if ( empty( $data['expires_at'] ) ) {
            return true;
        }
        
        return time() > $data['expires_at'];
    }
    
    /**
     * TTL de session configuré
     */
    private function get_session_ttl(): int {
        $settings = get_option( 'wc_msbc_settings', array() );
        return absint( $settings['session_ttl'] ?? 1200 ); // 20 minutes par défaut
    }
    
    /**
     * Nettoyage des sessions expirées (pour cron futur)
     */
    public function cleanup_expired_sessions() {
        // Pour version future avec stockage BDD
        // Actuellement les sessions WC se nettoient automatiquement
        $this->logger->debug( 'Nettoyage des sessions expirées appelé' );
    }
}
