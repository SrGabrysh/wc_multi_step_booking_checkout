<?php
/**
 * Gestionnaire principal du workflow multi-étapes
 * Responsabilité : Orchestration du flux utilisateur et redirections
 */

declare( strict_types=1 );

namespace TBFormation\WCMultiStepBookingCheckout\Modules\Workflow;

use TBFormation\WCMultiStepBookingCheckout\Core\Logger;
use TBFormation\WCMultiStepBookingCheckout\Modules\Session\SessionManager;

if ( ! defined( 'ABSPATH' ) ) exit;

class WorkflowManager {
    
    private $logger;
    private $session_manager;
    private $workflow_handler;
    private $workflow_validator;
    
    public function __construct( Logger $logger, SessionManager $session_manager ) {
        $this->logger = $logger;
        $this->session_manager = $session_manager;
        $this->workflow_handler = new WorkflowHandler( $logger, $session_manager );
        $this->workflow_validator = new WorkflowValidator( $logger );
        
        $this->init_hooks();
    }
    
    /**
     * Initialisation des hooks WordPress/WooCommerce
     */
    private function init_hooks() {
        // Interception du panier vers workflow - Priorité haute pour éviter les conflits
        \add_action( 'template_redirect', array( $this, 'maybe_redirect_to_workflow' ), 5 );
        
        // Protection des pages workflow
        \add_action( 'template_redirect', array( $this, 'protect_workflow_pages' ), 15 );
        
        // Hook après ajout au panier
        \add_filter( 'woocommerce_add_to_cart_redirect', array( $this, 'redirect_after_add_to_cart' ), 10 );
        
        // Validation avant checkout
        \add_action( 'woocommerce_checkout_process', array( $this, 'validate_checkout_access' ), 5 );
        
        // Hook de débogage pour analyser les conflits
        \add_action( 'template_redirect', array( $this, 'debug_template_redirect_hooks' ), 1 );
    }
    
    /**
     * Redirection vers workflow si conditions remplies
     */
    public function maybe_redirect_to_workflow() {
        $this->logger->debug( 'maybe_redirect_to_workflow - Début exécution', array(
            'is_checkout' => \is_checkout(),
            'is_wc_endpoint_url' => \is_wc_endpoint_url(),
            'current_url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'query_vars' => $GLOBALS['wp_query']->query_vars ?? array()
        ) );
        
        // Vérification si on est sur la page checkout classique
        if ( ! \is_checkout() || \is_wc_endpoint_url() ) {
            $this->logger->debug( 'Pas sur page checkout ou endpoint WC détecté - Arrêt redirection' );
            return;
        }
        
        // Vérification si le wizard est déjà complété
        $wizard_complete = $this->session_manager->is_wizard_complete();
        $this->logger->debug( 'Vérification wizard complet', array( 'wizard_complete' => $wizard_complete ) );
        
        if ( $wizard_complete ) {
            $this->logger->debug( 'Wizard déjà complété - Pas de redirection' );
            return;
        }
        
        // Vérification si panier contient des produits bookables
        $has_booking_products = $this->workflow_validator->cart_has_booking_products();
        $this->logger->debug( 'Vérification produits bookables', array( 'has_booking_products' => $has_booking_products ) );
        
        if ( ! $has_booking_products ) {
            $this->logger->info( 'Aucun produit bookable dans le panier - Pas de redirection workflow' );
            return;
        }
        
        // Récupération URL étape 1
        $step_1_url = $this->get_step_url( 1 );
        $this->logger->debug( 'URL étape 1', array( 'step_1_url' => $step_1_url ) );
        
        if ( ! $step_1_url ) {
            $this->logger->error( 'URL étape 1 introuvable - Configuration incorrecte' );
            return;
        }
        
        // Vérification que nous ne sommes pas déjà sur l'étape 1
        $current_page_id = \get_queried_object_id();
        $settings = \get_option( 'wc_msbc_settings', array() );
        $step_1_page_id = $settings['workflow_pages']['step_1'] ?? 0;
        
        if ( $current_page_id === $step_1_page_id ) {
            $this->logger->debug( 'Déjà sur la page étape 1 - Pas de redirection' );
            return;
        }
        
        // Exécution de la redirection
        $this->logger->info( 'Exécution redirection checkout vers workflow étape 1', array(
            'from_url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'to_url' => $step_1_url,
            'current_page_id' => $current_page_id,
            'step_1_page_id' => $step_1_page_id
        ) );
        
        // Démarrage session wizard
        $this->session_manager->start_wizard_session();
        
        \wp_safe_redirect( $step_1_url );
        exit;
    }
    
    /**
     * Protection des pages workflow
     */
    public function protect_workflow_pages() {
        $current_step = $this->get_current_workflow_step();
        
        if ( $current_step === 0 ) {
            return; // Pas sur une page workflow
        }
        
        $session_data = $this->session_manager->get_session_data();
        $allowed_step = $this->workflow_validator->get_allowed_step( $session_data );
        
        // Redirection si accès non autorisé
        if ( $current_step !== $allowed_step ) {
            $redirect_url = $this->get_step_url( $allowed_step );
            
            if ( $redirect_url ) {
                \wc_add_notice( 
                    \__( 'Vous avez été redirigé vers l\'étape appropriée du processus.', 'wc-multi-step-booking-checkout' ),
                    'notice'
                );
                
                $this->logger->info( 'Redirection protection workflow', array(
                    'from_step' => $current_step,
                    'to_step' => $allowed_step
                ) );
                
                \wp_safe_redirect( $redirect_url );
                exit;
            }
        }
    }
    
    /**
     * Redirection après ajout au panier
     */
    public function redirect_after_add_to_cart( $url ) {
        // Vérification si produit bookable ajouté
        if ( ! $this->workflow_validator->cart_has_booking_products() ) {
            return $url;
        }
        
        // Démarrage session wizard
        $this->session_manager->start_wizard_session();
        
        // Redirection vers étape 1
        $step_1_url = $this->get_step_url( 1 );
        return $step_1_url ?: $url;
    }
    
    /**
     * Validation accès checkout
     */
    public function validate_checkout_access() {
        if ( ! $this->workflow_validator->cart_has_booking_products() ) {
            return;
        }
        
        if ( ! $this->session_manager->is_wizard_complete() ) {
            \wc_add_notice( 
                \__( 'Vous devez compléter toutes les étapes du processus avant de finaliser votre commande.', 'wc-multi-step-booking-checkout' ),
                'error'
            );
            
            $step_url = $this->get_step_url( 1 );
            if ( $step_url ) {
                \wp_safe_redirect( $step_url );
                exit;
            }
        }
    }
    
    /**
     * Progression vers étape suivante
     */
    public function advance_to_next_step( int $current_step, array $step_data = array() ): array {
        return $this->workflow_handler->process_step_completion( $current_step, $step_data );
    }
    
    /**
     * Retour vers étape précédente
     */
    public function go_to_previous_step( int $current_step ): array {
        return $this->workflow_handler->process_step_back( $current_step );
    }
    
    /**
     * Récupération de l'étape workflow courante basée sur l'URL
     */
    private function get_current_workflow_step(): int {
        if ( ! \is_page() ) {
            return 0;
        }
        
        $current_page_id = \get_queried_object_id();
        $settings = \get_option( 'wc_msbc_settings', array() );
        $workflow_pages = $settings['workflow_pages'] ?? array();
        
        foreach ( $workflow_pages as $step => $page_id ) {
            if ( $current_page_id === $page_id ) {
                return (int) str_replace( 'step_', '', $step );
            }
        }
        
        return 0;
    }
    
    /**
     * URL d'une étape
     */
    public function get_step_url( int $step ): string {
        $settings = \get_option( 'wc_msbc_settings', array() );
        $workflow_pages = $settings['workflow_pages'] ?? array();
        $page_id = $workflow_pages[ "step_{$step}" ] ?? 0;
        
        if ( ! $page_id ) {
            $this->logger->error( 'Page non configurée pour étape', array( 'step' => $step ) );
            return '';
        }
        
        return \get_permalink( $page_id ) ?: '';
    }
    
    /**
     * Méthode de débogage pour analyser les conflits de hooks
     */
    public function debug_template_redirect_hooks() {
        if ( ! \is_checkout() ) {
            return;
        }
        
        global $wp_filter;
        $template_redirect_hooks = $wp_filter['template_redirect'] ?? null;
        
        if ( $template_redirect_hooks ) {
            $hooks_info = array();
            foreach ( $template_redirect_hooks->callbacks as $priority => $callbacks ) {
                foreach ( $callbacks as $callback_info ) {
                    $callback = $callback_info['function'];
                    $callback_name = 'unknown';
                    
                    if ( is_string( $callback ) ) {
                        $callback_name = $callback;
                    } elseif ( is_array( $callback ) && count( $callback ) === 2 ) {
                        $class = is_object( $callback[0] ) ? get_class( $callback[0] ) : $callback[0];
                        $method = $callback[1];
                        $callback_name = $class . '::' . $method;
                    }
                    
                    $hooks_info[] = array(
                        'priority' => $priority,
                        'callback' => $callback_name
                    );
                }
            }
            
            $this->logger->debug( 'Hooks template_redirect sur page checkout', array(
                'hooks_count' => count( $hooks_info ),
                'hooks' => $hooks_info
            ) );
        }
    }
    
    /**
     * Données de progression pour affichage
     */
    public function get_progress_data(): array {
        $session_data = $this->session_manager->get_session_data();
        $current_step = $session_data['current_step'] ?? 1;
        $completed_steps = $session_data['steps_completed'] ?? array();
        
        return array(
            'current_step' => $current_step,
            'total_steps' => 4,
            'completed_steps' => $completed_steps,
            'progress_percentage' => ( count( $completed_steps ) / 4 ) * 100,
            'step_labels' => array(
                1 => \__( 'Sélection', 'wc-multi-step-booking-checkout' ),
                2 => \__( 'Informations', 'wc-multi-step-booking-checkout' ),
                3 => \__( 'Signature', 'wc-multi-step-booking-checkout' ),
                4 => \__( 'Validation', 'wc-multi-step-booking-checkout' )
            )
        );
    }
}
