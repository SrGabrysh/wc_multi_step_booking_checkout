<?php
/**
 * Rendu des shortcodes workflow
 * Responsabilité : Génération HTML des éléments d'interface
 */

declare( strict_types=1 );

namespace TBFormation\WCMultiStepBookingCheckout\Modules\Shortcodes;

use TBFormation\WCMultiStepBookingCheckout\Core\Logger;
use TBFormation\WCMultiStepBookingCheckout\Modules\Workflow\WorkflowManager;

if ( ! defined( 'ABSPATH' ) ) exit;

class ShortcodeRenderer {
    
    private $logger;
    private $workflow_manager;
    
    public function __construct( Logger $logger, WorkflowManager $workflow_manager ) {
        $this->logger = $logger;
        $this->workflow_manager = $workflow_manager;
    }
    
    /**
     * Rendu barre de progression
     */
    public function render_progress_bar( array $atts ): string {
        $progress_data = $this->workflow_manager->get_progress_data();
        
        $show_labels = $atts['show_labels'] === 'true';
        $show_percentage = $atts['show_percentage'] === 'true';
        $css_class = 'wc-msbc-progress ' . sanitize_html_class( $atts['class'] );
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr( $css_class ); ?>" data-current-step="<?php echo esc_attr( $progress_data['current_step'] ); ?>">
            <div class="wc-msbc-progress-header">
                <?php if ( $show_labels ): ?>
                    <div class="wc-msbc-progress-title">
                        <?php printf( 
                            esc_html__( 'Étape %d sur %d', 'wc-multi-step-booking-checkout' ),
                            esc_html( $progress_data['current_step'] ),
                            esc_html( $progress_data['total_steps'] )
                        ); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ( $show_percentage ): ?>
                    <div class="wc-msbc-progress-percentage">
                        <?php echo esc_html( round( $progress_data['progress_percentage'] ) ); ?>%
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="wc-msbc-progress-bar">
                <div class="wc-msbc-progress-track">
                    <div class="wc-msbc-progress-fill" style="width: <?php echo esc_attr( $progress_data['progress_percentage'] ); ?>%"></div>
                </div>
            </div>
            
            <?php if ( $show_labels ): ?>
                <div class="wc-msbc-progress-steps">
                    <?php foreach ( $progress_data['step_labels'] as $step_num => $step_label ): ?>
                        <?php
                        $step_classes = array( 'wc-msbc-step' );
                        if ( in_array( $step_num, $progress_data['completed_steps'], true ) ) {
                            $step_classes[] = 'completed';
                        }
                        if ( $step_num === $progress_data['current_step'] ) {
                            $step_classes[] = 'current';
                        }
                        ?>
                        <div class="<?php echo esc_attr( implode( ' ', $step_classes ) ); ?>">
                            <div class="wc-msbc-step-number"><?php echo esc_html( $step_num ); ?></div>
                            <div class="wc-msbc-step-label"><?php echo esc_html( $step_label ); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Rendu bouton suivant
     */
    public function render_next_button( array $atts ): string {
        $current_step = $this->get_current_step();
        
        if ( $current_step === 0 ) {
            return ''; // Pas sur une page workflow
        }
        
        $button_text = esc_html( $atts['text'] );
        $css_class = 'wc-msbc-next-btn ' . sanitize_html_class( $atts['class'] );
        $validate = $atts['validate'] === 'true';
        
        // Texte spécial pour dernière étape
        if ( $current_step === 4 ) {
            $button_text = __( 'Aller au checkout', 'wc-multi-step-booking-checkout' );
        }
        
        ob_start();
        ?>
        <button 
            type="button" 
            class="<?php echo esc_attr( $css_class ); ?>"
            data-step="<?php echo esc_attr( $current_step ); ?>"
            data-validate="<?php echo esc_attr( $validate ? '1' : '0' ); ?>"
            data-loading-text="<?php esc_attr_e( 'Traitement...', 'wc-multi-step-booking-checkout' ); ?>"
        >
            <?php echo $button_text; ?>
        </button>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Rendu bouton précédent
     */
    public function render_prev_button( array $atts ): string {
        $current_step = $this->get_current_step();
        
        if ( $current_step === 0 ) {
            return ''; // Pas sur une page workflow
        }
        
        // Masquer sur étape 1 si demandé
        if ( $current_step === 1 && $atts['show_on_step_1'] === 'false' ) {
            return '';
        }
        
        $button_text = esc_html( $atts['text'] );
        $css_class = 'wc-msbc-prev-btn ' . sanitize_html_class( $atts['class'] );
        
        ob_start();
        ?>
        <button 
            type="button" 
            class="<?php echo esc_attr( $css_class ); ?>"
            data-step="<?php echo esc_attr( $current_step ); ?>"
            data-loading-text="<?php esc_attr_e( 'Traitement...', 'wc-multi-step-booking-checkout' ); ?>"
        >
            <?php echo $button_text; ?>
        </button>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Rendu garde d'étape
     */
    public function render_step_guard( array $atts, string $content ): string {
        $required_step = absint( $atts['step'] );
        $current_step = $this->get_current_step();
        $error_message = esc_html( $atts['message'] );
        
        // Si pas sur une page workflow, pas de garde
        if ( $current_step === 0 ) {
            return do_shortcode( $content );
        }
        
        // Vérification accès autorisé
        $progress_data = $this->workflow_manager->get_progress_data();
        $completed_steps = $progress_data['completed_steps'];
        
        // Si étape requise pas complétée, afficher message
        if ( $required_step > 1 && ! in_array( $required_step - 1, $completed_steps, true ) ) {
            ob_start();
            ?>
            <div class="wc-msbc-step-guard-error">
                <div class="wc-notice wc-notice--error">
                    <p><?php echo $error_message; ?></p>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }
        
        // Accès autorisé, afficher le contenu
        return do_shortcode( $content );
    }
    
    /**
     * Récupération étape courante
     */
    private function get_current_step(): int {
        if ( ! is_page() ) {
            return 0;
        }
        
        $current_page_id = get_queried_object_id();
        $settings = get_option( 'wc_msbc_settings', array() );
        $workflow_pages = $settings['workflow_pages'] ?? array();
        
        foreach ( $workflow_pages as $step => $page_id ) {
            if ( $current_page_id === $page_id ) {
                return (int) str_replace( 'step_', '', $step );
            }
        }
        
        return 0;
    }
}
