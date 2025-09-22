<?php
/**
 * Rendu des pages d'administration
 * Responsabilité : Génération HTML des pages de settings
 */

declare( strict_types=1 );

namespace TBFormation\WCMultiStepBookingCheckout\Admin;

use TBFormation\WCMultiStepBookingCheckout\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) exit;

class SettingsRenderer {
    
    private $logger;
    
    public function __construct( Logger $logger ) {
        $this->logger = $logger;
    }
    
    /**
     * Rendu de la page de paramètres principale
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'Vous n\'avez pas les permissions suffisantes.', 'wc-multi-step-booking-checkout' ) );
        }
        
        // Traitement de la soumission
        if ( isset( $_POST['submit'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'wc_msbc_settings_group-options' ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 esc_html__( 'Paramètres sauvegardés avec succès.', 'wc-multi-step-booking-checkout' ) . 
                 '</p></div>';
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'WC Multi-Step Booking Checkout - Configuration', 'wc-multi-step-booking-checkout' ); ?></h1>
            
            <div class="wc-msbc-admin-header">
                <p class="description">
                    <?php esc_html_e( 'Configurez le workflow multi-étapes pour vos réservations WooCommerce Bookings.', 'wc-multi-step-booking-checkout' ); ?>
                </p>
            </div>
            
            <form method="post" action="options.php" class="wc-msbc-settings-form">
                <?php
                settings_fields( 'wc_msbc_settings_group' );
                do_settings_sections( 'wc-multi-step-booking-checkout' );
                submit_button();
                ?>
            </form>
            
            <div class="wc-msbc-admin-sidebar">
                <?php $this->render_status_widget(); ?>
                <?php $this->render_shortcodes_widget(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Section principale
     */
    public function render_main_section() {
        echo '<p>' . esc_html__( 'Configuration des pages et paramètres du workflow.', 'wc-multi-step-booking-checkout' ) . '</p>';
    }
    
    /**
     * Champ pages du workflow
     */
    public function render_workflow_pages_field() {
        $settings = get_option( 'wc_msbc_settings', array() );
        $workflow_pages = $settings['workflow_pages'] ?? array();
        
        $pages = get_pages( array(
            'post_status' => array( 'publish', 'draft' )
        ) );
        ?>
        <table class="wc-msbc-pages-table">
            <?php for ( $i = 1; $i <= 4; $i++ ): ?>
                <?php
                $field_name = "wc_msbc_settings[workflow_pages][step_{$i}]";
                $current_value = $workflow_pages[ "step_{$i}" ] ?? 0;
                $step_labels = array(
                    1 => __( 'Étape 1 - Sélection', 'wc-multi-step-booking-checkout' ),
                    2 => __( 'Étape 2 - Informations', 'wc-multi-step-booking-checkout' ),
                    3 => __( 'Étape 3 - Signature', 'wc-multi-step-booking-checkout' ),
                    4 => __( 'Étape 4 - Validation', 'wc-multi-step-booking-checkout' )
                );
                ?>
                <tr>
                    <td>
                        <label for="step_<?php echo esc_attr( $i ); ?>">
                            <strong><?php echo esc_html( $step_labels[ $i ] ); ?></strong>
                        </label>
                    </td>
                    <td>
                        <select name="<?php echo esc_attr( $field_name ); ?>" id="step_<?php echo esc_attr( $i ); ?>" class="regular-text">
                            <option value="0"><?php esc_html_e( '-- Sélectionner une page --', 'wc-multi-step-booking-checkout' ); ?></option>
                            <?php foreach ( $pages as $page ): ?>
                                <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $current_value, $page->ID ); ?>>
                                    <?php echo esc_html( $page->post_title ); ?>
                                    <?php if ( $page->post_status === 'draft' ): ?>
                                        <?php esc_html_e( '(Brouillon)', 'wc-multi-step-booking-checkout' ); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ( $current_value > 0 ): ?>
                            <a href="<?php echo esc_url( get_edit_post_link( $current_value ) ); ?>" target="_blank" class="button button-small">
                                <?php esc_html_e( 'Modifier', 'wc-multi-step-booking-checkout' ); ?>
                            </a>
                            <a href="<?php echo esc_url( get_permalink( $current_value ) ); ?>" target="_blank" class="button button-small">
                                <?php esc_html_e( 'Voir', 'wc-multi-step-booking-checkout' ); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endfor; ?>
        </table>
        <p class="description">
            <?php esc_html_e( 'Sélectionnez les pages qui seront utilisées pour chaque étape du workflow.', 'wc-multi-step-booking-checkout' ); ?>
        </p>
        <?php
    }
    
    /**
     * Champ TTL session
     */
    public function render_session_ttl_field() {
        $settings = get_option( 'wc_msbc_settings', array() );
        $session_ttl = $settings['session_ttl'] ?? 1200;
        ?>
        <input 
            type="number" 
            name="wc_msbc_settings[session_ttl]" 
            value="<?php echo esc_attr( $session_ttl / 60 ); ?>" 
            min="5" 
            max="60" 
            step="1" 
            class="small-text"
        />
        <p class="description">
            <?php esc_html_e( 'Durée de validité d\'une session de workflow en minutes (5-60).', 'wc-multi-step-booking-checkout' ); ?>
        </p>
        <?php
    }
    
    /**
     * Section debug
     */
    public function render_debug_section() {
        echo '<p>' . esc_html__( 'Options de débogage et de logs.', 'wc-multi-step-booking-checkout' ) . '</p>';
    }
    
    /**
     * Champ mode debug
     */
    public function render_debug_mode_field() {
        $settings = get_option( 'wc_msbc_settings', array() );
        $debug_mode = $settings['debug_mode'] ?? false;
        $log_level = $settings['log_level'] ?? 'info';
        ?>
        <label>
            <input type="checkbox" name="wc_msbc_settings[debug_mode]" value="1" <?php checked( $debug_mode ); ?> />
            <?php esc_html_e( 'Activer le mode debug', 'wc-multi-step-booking-checkout' ); ?>
        </label>
        <br><br>
        
        <label for="log_level"><?php esc_html_e( 'Niveau de log :', 'wc-multi-step-booking-checkout' ); ?></label>
        <select name="wc_msbc_settings[log_level]" id="log_level">
            <option value="debug" <?php selected( $log_level, 'debug' ); ?>><?php esc_html_e( 'Debug', 'wc-multi-step-booking-checkout' ); ?></option>
            <option value="info" <?php selected( $log_level, 'info' ); ?>><?php esc_html_e( 'Info', 'wc-multi-step-booking-checkout' ); ?></option>
            <option value="warning" <?php selected( $log_level, 'warning' ); ?>><?php esc_html_e( 'Warning', 'wc-multi-step-booking-checkout' ); ?></option>
            <option value="error" <?php selected( $log_level, 'error' ); ?>><?php esc_html_e( 'Error', 'wc-multi-step-booking-checkout' ); ?></option>
        </select>
        
        <p class="description">
            <?php esc_html_e( 'Active les logs détaillés pour le débogage. Les logs sont visibles dans WooCommerce > Status > Logs.', 'wc-multi-step-booking-checkout' ); ?>
        </p>
        <?php
    }
    
    /**
     * Widget de statut
     */
    private function render_status_widget() {
        $settings = get_option( 'wc_msbc_settings', array() );
        $workflow_pages = $settings['workflow_pages'] ?? array();
        
        // Vérification configuration
        $configured_pages = 0;
        $page_errors = array();
        
        for ( $i = 1; $i <= 4; $i++ ) {
            $page_id = $workflow_pages[ "step_{$i}" ] ?? 0;
            if ( $page_id > 0 ) {
                if ( get_post_status( $page_id ) === 'publish' ) {
                    $configured_pages++;
                } else {
                    $page_errors[] = $i;
                }
            }
        }
        
        $is_fully_configured = $configured_pages === 4;
        ?>
        <div class="postbox">
            <h3 class="hndle"><?php esc_html_e( 'Statut de Configuration', 'wc-multi-step-booking-checkout' ); ?></h3>
            <div class="inside">
                <div class="wc-msbc-status-item">
                    <span class="wc-msbc-status-label"><?php esc_html_e( 'Pages configurées :', 'wc-multi-step-booking-checkout' ); ?></span>
                    <span class="wc-msbc-status-value <?php echo $is_fully_configured ? 'success' : 'warning'; ?>">
                        <?php echo esc_html( $configured_pages ); ?>/4
                    </span>
                </div>
                
                <div class="wc-msbc-status-item">
                    <span class="wc-msbc-status-label"><?php esc_html_e( 'WooCommerce :', 'wc-multi-step-booking-checkout' ); ?></span>
                    <span class="wc-msbc-status-value <?php echo class_exists( 'WooCommerce' ) ? 'success' : 'error'; ?>">
                        <?php echo class_exists( 'WooCommerce' ) ? esc_html__( 'Actif', 'wc-multi-step-booking-checkout' ) : esc_html__( 'Inactif', 'wc-multi-step-booking-checkout' ); ?>
                    </span>
                </div>
                
                <div class="wc-msbc-status-item">
                    <span class="wc-msbc-status-label"><?php esc_html_e( 'WC Bookings :', 'wc-multi-step-booking-checkout' ); ?></span>
                    <span class="wc-msbc-status-value <?php echo class_exists( 'WC_Bookings' ) ? 'success' : 'warning'; ?>">
                        <?php echo class_exists( 'WC_Bookings' ) ? esc_html__( 'Actif', 'wc-multi-step-booking-checkout' ) : esc_html__( 'Inactif', 'wc-multi-step-booking-checkout' ); ?>
                    </span>
                </div>
                
                <?php if ( ! empty( $page_errors ) ): ?>
                    <div class="wc-msbc-status-warning">
                        <p><strong><?php esc_html_e( 'Attention :', 'wc-multi-step-booking-checkout' ); ?></strong></p>
                        <p><?php printf( esc_html__( 'Les pages des étapes %s ne sont pas publiées.', 'wc-multi-step-booking-checkout' ), implode( ', ', $page_errors ) ); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Widget shortcodes
     */
    private function render_shortcodes_widget() {
        ?>
        <div class="postbox">
            <h3 class="hndle"><?php esc_html_e( 'Shortcodes Disponibles', 'wc-multi-step-booking-checkout' ); ?></h3>
            <div class="inside">
                <div class="wc-msbc-shortcode-item">
                    <code>[wcmsbc_progress]</code>
                    <p><?php esc_html_e( 'Affiche la barre de progression', 'wc-multi-step-booking-checkout' ); ?></p>
                </div>
                
                <div class="wc-msbc-shortcode-item">
                    <code>[wcmsbc_next]</code>
                    <p><?php esc_html_e( 'Bouton "Suivant"', 'wc-multi-step-booking-checkout' ); ?></p>
                </div>
                
                <div class="wc-msbc-shortcode-item">
                    <code>[wcmsbc_prev]</code>
                    <p><?php esc_html_e( 'Bouton "Précédent"', 'wc-multi-step-booking-checkout' ); ?></p>
                </div>
                
                <div class="wc-msbc-shortcode-item">
                    <code>[wcmsbc_step_guard step="2"]Contenu[/wcmsbc_step_guard]</code>
                    <p><?php esc_html_e( 'Protection de contenu par étape', 'wc-multi-step-booking-checkout' ); ?></p>
                </div>
                
                <p class="description">
                    <?php esc_html_e( 'Utilisez ces shortcodes dans vos pages Elementor pour intégrer le workflow.', 'wc-multi-step-booking-checkout' ); ?>
                </p>
            </div>
        </div>
        <?php
    }
}
