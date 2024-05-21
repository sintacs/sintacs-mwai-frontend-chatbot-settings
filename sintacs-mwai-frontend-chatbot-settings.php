<?php
/**
 * Plugin Name: Sintacs Mwai Frontend Chatbot Settings
 * Description: Allows users to change chatbot parameters in the frontend.
 * Version: 1.0
 * Author: Dirk Krölls, Sintacs
 */

// Ensure that the plugin is not called directly
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

include_once( 'sintacs-mwai-frontend-chatbot-settings-admin.php' );
//include_once( 'sintacs-mwai-frontend-discussions.php' );

class SintacsMwaiFrontendChatbotSettings {
	var string $plugin_name = 'Sintacs Mwai Frontend Chatbot Settings';

	// Values for chatbot modes. Where are they defined in ai-engine?
	var array $chatbot_modes = [ 'chat','assistant','images' ];

	var string $chatbot_id = '';

	// Parameter names to save with the form in the frontend
	var array $chatbot_settings = [];

	// Parameter names to save with the form in the backend
	var array $parameter_names = [];

	// Parameter names to skip from the form in the frontend
	var array $parameter_to_skip = [
		'icon',
		'iconText',
		'iconAlt',
		'iconPosition',
		'scope',
		'apiKey',
		'botId'
	];

	// Parameter names to show in the frontend
	// These field(s) are missing in the init constant so we add it here manually. Use it for sorting too. ToDo: add parameter dynamically
	var array $parameter_to_show = [ 'name','envId','instructions', 'model', 'temperature' ];

	// Parameters that are not editable but should still be displayed in the form
	var array $readonly_parameters = [ 'botId' ];

	// Define an array with the roles that should have access
	private $allowed_roles;

	public function __construct() {
		add_action( 'plugins_loaded',array( $this,'check_ai_engine_plugin_status' ) );
		//add_action('wp_enqueue_scripts',array($this,'enqueue_scripts'));

		add_shortcode( 'ai_engine_extension_form',array( $this,'form_shortcode' ) );

		// Register the AJAX actions for get_available_models
		add_action( 'wp_ajax_get_available_models',array( $this,'ajax_get_available_models' ) );
		add_action( 'wp_ajax_nopriv_get_available_models',array( $this,'ajax_get_available_models' ) );

		add_action( 'wp_ajax_save_ai_engine_parameters',array( $this,'save_parameters' ) );
		add_action( 'wp_ajax_nopriv_save_ai_engine_parameters',array( $this,'save_parameters' ) );

		if ( $this->is_ai_engine_pro_active() ) {
			// AI Engine Pro-specific initializations
		}

		// Generate the parameter_names based on MWAI_CHATBOT_DEFAULT_PARAMS and exclusion of parameter_to_skip
		// Check first if constant is defined
		if ( defined( 'MWAI_CHATBOT_DEFAULT_PARAMS' ) ) {
			$this->parameter_names = array_diff( array_keys( MWAI_CHATBOT_DEFAULT_PARAMS ),$this->parameter_to_skip );

			// Adds array parameter_to_show to parameter_names array at the beginning
			$this->parameter_names = array_merge( $this->parameter_to_show,$this->parameter_names );

			// Make the array unique
			$this->parameter_names = array_unique( $this->parameter_names );
		}

		$this->allowed_roles = get_option( 'sintacs_mwai_chatbot_frontend_allowed_roles',[ 'administrator' ] ); // Default to 'administrator' if not set
	}

	public function check_ai_engine_plugin_status() {
		if ( ! is_plugin_active( 'ai-engine/ai-engine.php' ) && ! is_plugin_active( 'ai-engine-pro/ai-engine-pro.php' ) ) {
			add_action( 'admin_notices',array( $this,'ai_engine_dependency_notice' ) );
			deactivate_plugins( plugin_basename( __FILE__ ) );
		}
	}

	public function ai_engine_dependency_notice() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		$plugin_data = get_plugin_data( __FILE__ );
		$plugin_name = $plugin_data['Name'];
		?>
        <div class="notice notice-error">
            <p><?php echo sprintf( __( 'The plugin %s requires the ai-engine or ai-engine-pro plugin to function. Please install and activate one of these plugins.','text-domain' ),$plugin_name ); ?></p>
        </div>
		<?php
	}

	private function is_ai_engine_pro_active() {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		return is_plugin_active( 'ai-engine-pro/ai-engine-pro.php' ) || is_plugin_active( 'ai-engine/ai-engine.php' );
	}

	public function enqueue_scripts() {
		if ( ! $this->is_ai_engine_pro_active() ) {
			return;
		}
		wp_enqueue_script( 'sintacs-mwai-frontend-chatbot-settings', plugin_dir_url( __FILE__ ) . 'assets/js/sintacs-mwai-frontend-chatbot-settings.js', array( 'jquery' ), null, true );
		wp_localize_script( 'sintacs-mwai-frontend-chatbot-settings', 'aiEngineExtensionAjax', array( 'ajaxurl'  => admin_url( 'admin-ajax.php' ),
		                                                                                            'security' => wp_create_nonce( 'secure_action' )
		) );
		wp_enqueue_style( 'sintacs-mwai-frontend-chatbot-settings', plugin_dir_url( __FILE__ ) . 'assets/css/sintacs-mwai-frontend-chatbot-settings.css' );
	}

	public function save_parameters() {
		if ( ! $this->current_user_has_access() ) {
			wp_send_json_error( [ 'message' => 'You are not allowed to change these settings.' ] );

			return;
		}

		// Extract formData: model=ft%3Agpt-3.5-turbo-0613%3Afmcg%3A%3A8XVP5Cst&maxTokens=1024&temperature=0.8&instructions=Converse%20as%20if%20you%20were%20an%20AI%20assistant.%20Be%20friendly%2C%20creative.%20%7BCONTENT%7D&envId=o3n4pqhr
		parse_str( nl2br( $_POST['formData'] ),$formDataArray );

//var_dump($formDataArray);

		// Überprüfen des Nonce
		//error_log('Empfangener Nonce-Wert: ' . $formDataArray['security']); // Zum Debuggen
		if ( ! isset( $formDataArray['security'] ) || ! wp_verify_nonce( $formDataArray['security'],'sichere_handlung' ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed.' ] );

			return; // Stop execution if the nonce test fails
		}

		$extractedParameters = [];
		foreach ( $this->parameter_names as $parameter_name ) {
			if ( isset( $formDataArray[ $parameter_name ] ) ) {
				// Check if the parameter is a textarea type
				if ( $parameter_name == 'instructions' ) { // Assuming 'instructions' is your textarea field
					$extractedParameters[ $parameter_name ] = sanitize_textarea_field( $formDataArray[ $parameter_name ] );
				} else {
					$extractedParameters[ $parameter_name ] = sanitize_text_field( $formDataArray[ $parameter_name ] );
				}
			} else {
				$extractedParameters[ $parameter_name ] = false; // to include unchecked checkboxes
			}
		}
		// Adding botId to the extracted parameters array
		$extractedParameters['botId'] = sanitize_text_field( $_POST['chatbotId'] );

		// Extracting the botId and the settings to be updated from the POST data
		$botId = sanitize_text_field( $extractedParameters['botId'] );

		// Loading the current Chatbot settings
		$chatbots = get_option( 'mwai_chatbots',[] );

		$found = false;
		foreach ( $chatbots as &$chatbot ) {
			if ( $chatbot['botId'] === $botId ) {
				// Updating the settings for the found chatbot
				foreach ( $extractedParameters as $key => $value ) {
					$chatbot[ $key ] = $value;
				}
				$found = true;
				break;
			}
		}

		// Ensuring the chatbot was found and updated
		if ( ! $found ) {
			wp_send_json_error( [ 'message' => 'Chatbot with the specified botId was not found.' ] );

			return;
		}

		// Save the updated chatbots back to the database
		update_option( 'mwai_chatbots',$chatbots );

		wp_send_json_success( [ 'message' => 'Chatbot settings updated successfully.' ] );
	}

	public function form_shortcode($atts) {
	    $atts = shortcode_atts(array(
	        'chatbot_id' => ''
	    ), $atts, 'ai_engine_extension_form');

	    $chatbot_id = sanitize_text_field($atts['chatbot_id']);

	    if ( ! $this->current_user_has_access() ) {
	        return 'You are not allowed to access this feature.';
	    }

	    if ( ! $this->is_ai_engine_pro_active() ) {
	        return 'AI Engine (Pro) is not active.';
	    }

	    // Enqueue scripts and styles only when the shortcode is processed
	    $this->enqueue_scripts();

	    $form_elements = $this->generate_form_elements_from_parameters($chatbot_id);

	    // Form header
	    $form_header = '<div class="sintacs-card-header sintacs-d-flex sintacs-justify-content-between sintacs-align-items-center">
	                    <h4>Chatbot Settings</h4>
	                    <span id="settings-info"></span>
	                </div>';

	    // Form body
	    $form_body = '<div class="sintacs-form-row">' . $form_elements . '</div>';

	    // Form footer
	    $form_footer = '';

	    // Form
	    $form_html = '<div id="sintacs-ai-engine-extension-form-wrapper" class="sintacs-container sintacs-mt-4">
	                <div class="sintacs-card">
	                    ' . $form_header . '
	                    <div class="sintacs-card-body">
	                        <div id="form-success-message" style="display: none;" class="sintacs-alert sintacs-alert-success"></div>
	                        <form id="sintacs-ai-engine-extension-form" class="sintacs-needs-validation sintacs-bg-white sintacs-p-2" novalidate>
	                            ' . $form_body . '
	                            <div class="sintacs-btn-wrapper">
	                                <button type="submit" class="sintacs-btn sintacs-btn-primary sintacs-btn-sm sintacs-m-2">' . __( 'Save' ) . '</button>
	                            </div>
	                        </form>
	                    </div>
	                </div>
	            </div>';

	    return $form_html;
	}

	public function generate_form_elements_from_parameters($chatbot_id = '') {
	    $form_elements = '';

	    // hidden chatbot_id
	    $form_elements .= '<input type="hidden" name="botId" value="' . esc_attr($chatbot_id) . '" class="sintacs-form-control sintacs-form-control-sm">';
	    $form_elements .= wp_nonce_field( 'sichere_handlung','security',true,false );

	    foreach ( $this->parameter_names as $parameter_name ) {
	        // ToDo: Check if the parameter exists in the Chatbot settings

	        // readonly Parameters werden angezeigt, können aber nicht geändert werden
	        $readonly = in_array( $parameter_name,$this->readonly_parameters ) ? ' readonly' : '';

	        $label = esc_html( ucfirst( str_replace( '_',' ',$parameter_name ) ) ); // Generate label from parameter name

	        // Differentiate form elements based on parameter type
	        switch ( $parameter_name ) {
	            case 'model': // Dropdown for models
	                $form_elements .= "<div class='sintacs-form-floating m-2'>";
	                $form_elements .= "<select id='{$parameter_name}' name='{$parameter_name}' class='sintacs-form-select sintacs-form-select-sm'{$readonly}>";
	                $form_elements .= "</select>";
	                $form_elements .= "<label for='{$parameter_name}'>{$label}</label></div>";
	                break;
	            case 'instructions':// Textarea for instructions
	            case 'context':// Textarea for instructions
	                $form_elements .= "<div class='sintacs-form-floating m-2'>";
	                $form_elements .= "<textarea id='{$parameter_name}' name='{$parameter_name}' class='sintacs-form-control sintacs-form-control-sm' placeholder='{$label}'{$readonly}></textarea>";
	                $form_elements .= "<label for='{$parameter_name}'>{$label}</label></div>";
	                break;

	            case 'mode':
	                $modes = $this->chatbot_modes;

	                // Add the Select field for mode
	                $form_elements .= "<div class='sintacs-form-floating m-2'>";
	                $form_elements .= "<select id='mode' name='mode' class='sintacs-form-select sintacs-form-select-sm'{$readonly}>";
	                foreach ( $modes as $mode ) {
	                    $form_elements .= "<option value='{$mode}'>{$mode}</option>";
	                }
	                $form_elements .= "</select>";
	                $form_elements .= "<label for='mode'>Mode</label></div>";
	                break;

	            case 'envId':
	                $environments = $this->get_all_mwai_options( 'ai_envs' ); // Load all environments

	                // Add the Select field for environments
	                $form_elements .= "<div class='sintacs-form-floating m-2'>";
	                $form_elements .= "<select id='envId' name='envId' class='sintacs-form-select sintacs-form-select-sm'{$readonly}>";
	                foreach ( $environments as $env ) {
	                    $form_elements .= "<option value='{$env['id']}'>{$env['name']}</option>";
	                }
	                $form_elements .= "</select>";
	                $form_elements .= "<label for='envId'>Environment</label></div>";
	                break;

	            // Add checkbox for some elements
	            case 'window' :
	            case 'fullscreen' :
	            case 'copyButton' :
	            case 'localMemory' :
	            case 'contentAware' :

	                $form_elements .= "<div class='sintacs-form-check sintacs-form-switch m-2 py-2'>";
	                $form_elements .= "<input class='sintacs-form-check-input' type='checkbox' id='{$parameter_name}' name='{$parameter_name}' value='1' {$readonly}>";
	                $form_elements .= "<label class='sintacs-form-check-label' for='{$parameter_name}'>{$label}</label></div>";
	                break;

	            default: // Standard text fields for other parameters
	                $form_elements .= "<div class='sintacs-form-floating m-2'>";
	                $form_elements .= "<input type='text' id='{$parameter_name}' name='{$parameter_name}' value='' class='sintacs-form-control sintacs-form-control-sm' placeholder='{$label}'{$readonly}>";
	                $form_elements .= "<label for='{$parameter_name}'>{$label}</label></div>";
	                break;
	        }
	    }

	    return $form_elements;
	}

	public function ajax_get_available_models() {
		// Set chatbot id
		$this->setChatbotId( $_POST['chatbotId'] );
		$chatbot_id = $this->chatbot_id;

		$models = $this->get_available_models();

		$chatbots = $this->get_wp_option( 'mwai_chatbots' );

		if ( ! empty( $chatbots ) ) {
			foreach ( $chatbots as $chatbot ) {
				if ( isset( $chatbot['botId'] ) && $chatbot['botId'] === $chatbot_id ) {
					$this->chatbot_settings = $chatbot;
				}
			}
		}

		wp_send_json_success( [ 'models' => $models,'chatbot_settings' => $this->chatbot_settings ] );
	}

	private function get_available_models() {
		$options = $this->get_wp_option( 'mwai_options' );

		$models = [];

		// Add default model
		$models[] = [
			'model' => '',
			'name'  => 'Default'
		];

		if ( isset( $options['openai_models'] ) ) {
			$models = array_merge( $models,$options['openai_models'] );
		}
		if ( isset( $options['anthropic_models'] ) ) {
			$models = array_merge( $models,$options['anthropic_models'] );
		}

		$finetunes = $this->get_finetunes_by_chatbotId( $this->chatbot_id );

		if ( ! empty( $finetunes ) ) {
			$finetunes_prepared = [];
			foreach ( $finetunes as $finetune ) {
				$finetune_name        = ( isset( $finetune['suffix'] ) ) ? $finetune['suffix'] : $finetune['id'];
				$finetunes_prepared[] = array( 'model' => $finetune['model'],'name' => $finetune_name );
			}

			$models = array_merge( $models,$finetunes_prepared );
		}

		return $models;
	}

	private function get_finetunes_by_chatbotId( $chatbot_id ) {
		$envId = $this->get_environment_id_by_chatbot_id( $chatbot_id );

		$options = $this->get_wp_option( 'mwai_options' );

		if ( ! empty( $options['ai_envs'] ) ) {
			foreach ( $options['ai_envs'] as $env ) {
				if ( $env['id'] === $envId && isset( $env['finetunes'] ) ) {
					return $env['finetunes'];
				}
			}
		}

	}

	public function setChatbotId( string $chatbot_id ): void {
		$this->chatbot_id = $chatbot_id;
	}

	private function get_environment_id_by_chatbot_id( $chatbotId ) {
		// Retrieve chatbots from WP options under the key 'mwai_chatbots'
		$chatbots = $this->get_wp_option( 'mwai_chatbots' );
		foreach ( $chatbots as $chatbot ) {
			if ( isset( $chatbot['botId'] ) && $chatbot['botId'] === $chatbotId ) {
				// Return the environment ID if the chatbot was found
				return $chatbot['envId'] ?? null;
			}
		}

		// Return null if no match was found
		return null;
	}

	public function get_mwai_chatbots() {
		// Retrieve chatbots from WP options under the key 'mwai_chatbots'
		$chatbots = $this->get_wp_option( 'mwai_chatbots',[] );

		return $chatbots;
	}

	private function get_wp_option( $option_name ) {
		// Ensure you use the correct option key
		return get_option( $option_name,[] );
	}

	public function get_chatbot_settings(): array {
		return $this->chatbot_settings;
	}

	public function get_all_mwai_options( $option = 'null' ) {
		$options = $this->get_wp_option( 'mwai_options' );

		if ( $option ) {
			return $options[ $option ];
		}

		return $options ?? []; // Return all environments
	}

	private function current_user_has_access(): bool {
		$user  = wp_get_current_user();
		$roles = (array) $user->roles;
		foreach ( $roles as $role ) {
			if ( in_array( $role,$this->allowed_roles ) ) {
				return true;
			}
		}

		return false;
	}
}

new SintacsMwaiFrontendChatbotSettings();
