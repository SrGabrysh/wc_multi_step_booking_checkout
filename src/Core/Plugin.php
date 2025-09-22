<?php
/**
 * Classe principale d'orchestration du plugin
 * Responsabilité : Initialisation des modules et coordination générale
 */

declare( strict_types=1 );

namespace TBFormation\WCMultiStepBookingCheckout\Core;

use TBFormation\WCMultiStepBookingCheckout\Modules\Workflow\WorkflowManager;
use TBFormation\WCMultiStepBookingCheckout\Modules\Session\SessionManager;
use TBFormation\WCMultiStepBookingCheckout\Modules\Shortcodes\ShortcodeManager;

if ( ! defined( 'ABSPATH' ) ) exit;

class Plugin {
    
    private static $instance = null;
    private $modules = array();
    private $logger;
    
    /**
     * Singleton instance
     */
    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructeur privé - Pattern Singleton
     */
    private function __construct() {
        $this->init_core();
        $this->init_modules();
        $this->init_admin();
    }
    
    /**
     * Initialisation des composants core
     */
    private function init_core() {
        $this->logger = new Logger();
        
        $this->logger->info( 'WC Multi-Step Booking Checkout initialisé', array(
            'version' => WC_MSBC_VERSION,
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo( 'version' )
        ) );
    }
    
    /**
     * Initialisation des modules métier
     */
    private function init_modules() {
        // Module Session - Base pour tous les autres
        $this->modules['session'] = new SessionManager( $this->logger );
        
        // Module Workflow - Gestion des étapes
        $this->modules['workflow'] = new WorkflowManager( 
            $this->logger, 
            $this->modules['session'] 
        );
        
        // Module Shortcodes - Interface Elementor
        $this->modules['shortcodes'] = new ShortcodeManager( 
            $this->logger,
            $this->modules['workflow'],
            $this->modules['session']
        );
        
        $this->logger->info( 'Modules initialisés', array(
            'modules' => array_keys( $this->modules )
        ) );
    }
    
    /**
     * Initialisation de l'administration
     */
    private function init_admin() {
        // Pas d'interface d'administration - configuration via shortcodes uniquement
    }
    
    /**
     * Récupération d'un module
     */
    public function get_module( string $name ) {
        return $this->modules[ $name ] ?? null;
    }
    
    /**
     * Hook d'activation
     */
    public static function activate() {
        // Options par défaut
        $default_settings = array(
            'workflow_pages' => array(
                'step_1' => 4267, // Page 1 ID
                'step_2' => 4279, // Page 2 ID  
                'step_3' => 4284, // Page 3 ID
                'step_4' => 4291  // Page 4 ID
            ),
            'session_ttl' => 1200, // 20 minutes
            'redirect_checkout' => true,
            'wizard_version' => '1.0'
        );
        
        add_option( 'wc_msbc_settings', $default_settings );
        
        // Création des tables si nécessaire (pour versions futures)
        self::maybe_create_tables();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Hook de désactivation
     */
    public static function deactivate() {
        // Nettoyage des tâches cron
        wp_clear_scheduled_hook( 'wc_msbc_cleanup_sessions' );
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Création des tables (pour versions futures)
     */
    private static function maybe_create_tables() {
        // Réservé pour versions futures avec stockage BDD
        // Actuellement tout en session WooCommerce
    }
}
