<?php
/**
 * Logger simple pour le plugin
 * Responsabilité : Gestion centralisée des logs
 */

declare( strict_types=1 );

namespace TBFormation\WCMultiStepBookingCheckout\Core;

if ( ! defined( 'ABSPATH' ) ) exit;

class Logger {
    
    private $log_enabled;
    private $log_level;
    
    public function __construct() {
        $settings = \get_option( 'wc_msbc_settings', array() );
        $this->log_enabled = $settings['debug_mode'] ?? false;
        $this->log_level = $settings['log_level'] ?? 'info';
    }
    
    /**
     * Log niveau info
     */
    public function info( string $message, array $context = array() ) {
        $this->log( 'info', $message, $context );
    }
    
    /**
     * Log niveau warning
     */
    public function warning( string $message, array $context = array() ) {
        $this->log( 'warning', $message, $context );
    }
    
    /**
     * Log niveau error
     */
    public function error( string $message, array $context = array() ) {
        $this->log( 'error', $message, $context );
    }
    
    /**
     * Log niveau debug
     */
    public function debug( string $message, array $context = array() ) {
        $this->log( 'debug', $message, $context );
    }
    
    /**
     * Méthode de log principale
     */
    private function log( string $level, string $message, array $context = array() ) {
        if ( ! $this->log_enabled ) {
            return;
        }
        
        if ( ! $this->should_log( $level ) ) {
            return;
        }
        
        $session_id = 'no-session';
        if ( \function_exists( 'WC' ) && \WC() && \WC()->session ) {
            try {
                $session_id = \WC()->session->get_customer_id();
            } catch ( \Exception $e ) {
                $session_id = 'session-error';
            }
        }
        
        $log_entry = array(
            'timestamp' => \current_time( 'Y-m-d H:i:s' ),
            'level' => strtoupper( $level ),
            'message' => $message,
            'context' => $context,
            'user_id' => \get_current_user_id(),
            'session_id' => $session_id
        );
        
        // Log via WooCommerce si disponible
        if ( \function_exists( 'wc_get_logger' ) ) {
            $wc_logger = \wc_get_logger();
            $wc_logger->log( $level, \wp_json_encode( $log_entry ), array( 'source' => 'wc-multi-step-booking-checkout' ) );
        }
        
        // Fallback error_log
        if ( \WP_DEBUG_LOG ) {
            \error_log( '[WC Multi-Step Booking Checkout] ' . \wp_json_encode( $log_entry ) );
        }
    }
    
    /**
     * Vérification si le niveau doit être loggé
     */
    private function should_log( string $level ): bool {
        $levels = array( 'debug' => 1, 'info' => 2, 'warning' => 3, 'error' => 4 );
        
        $current_level = $levels[ $this->log_level ] ?? 2;
        $message_level = $levels[ $level ] ?? 2;
        
        return $message_level >= $current_level;
    }
}
