<?php
/**
 * Plugin Name: Sintacs Mwai Frontend Chatbot Settings
 * Description: Allows users to change chatbot parameters on the frontend.
 * Version: 1.3.5
 * Author: Dirk Krölls, Sintacs
 */

// Ensure that the plugin is not called directly
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Define the parameters to skip as a constant
const SINTACS_MWAI_CHATBOT_PARAMETER_TO_SKIP = [
	'icon',
	'iconText',
	'iconAlt',
	'iconPosition',
	'scope',
	'apiKey',
	'botId'
];


include_once( 'sintacs-mwai-frontend-chatbot-settings-admin.php' );

// Define the activation function
function sintacs_mwai_frontend_chatbot_settings_activate() {
	// Set the default allowed roles to 'administrator' if not already set
	if ( get_option( 'sintacs_mwai_chatbot_frontend_allowed_roles' ) === false ) {
		update_option( 'sintacs_mwai_chatbot_frontend_allowed_roles',[ 'administrator' ] );
	}

	// Set all parameters to be shown by default, excluding those in SINTACS_MWAI_CHATBOT_PARAMETER_TO_SKIP
	if ( defined( 'MWAI_CHATBOT_DEFAULT_PARAMS' ) && get_option( 'sintacs_mwai_chatbot_parameters_to_show' ) === false ) {
		$all_parameters     = array_keys( MWAI_CHATBOT_DEFAULT_PARAMS );
		$parameters_to_show = array_diff( $all_parameters,SINTACS_MWAI_CHATBOT_PARAMETER_TO_SKIP );

		// Include the $parameter_to_show property
		$default_parameters_to_show = ( new SintacsMwaiFrontendChatbotSettings() )->parameter_to_show;
		$parameters_to_show         = array_merge( $default_parameters_to_show,$parameters_to_show );

		// Make para to show unique
		$parameters_to_show = array_unique( $parameters_to_show );

		update_option( 'sintacs_mwai_chatbot_parameters_to_show',$parameters_to_show );
	}
}

function get_theme_option_name( $object ) {
	$reflection = new ReflectionClass( $object );
	$property   = $reflection->getProperty( 'themes_option_name' );
	$property->setAccessible( true );

	return $property->getValue( $object );
}


// Register the activation hook
register_activation_hook( __FILE__,'sintacs_mwai_frontend_chatbot_settings_activate' );

// Register the uninstall hook
register_uninstall_hook( __FILE__,'sintacs_mwai_frontend_chatbot_settings_uninstall' );

// Add settings link on plugin page
function sintacs_mwai_frontend_chatbot_settings_action_links( $links ) {
	$settings_link = '<a href="admin.php?page=sintacs-ai-engine-settings">' . __( 'Settings' ) . '</a>';
	array_unshift( $links,$settings_link );

	return $links;
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ),'sintacs_mwai_frontend_chatbot_settings_action_links' );

class SintacsMwaiFrontendChatbotSettings {

	var string $plugin_name = 'Sintacs Mwai Frontend Chatbot Settings';

	// Values for chatbot modes. Where are they defined in ai-engine?
	var array $chatbot_modes = [ 'chat','assistant','images' ];

	var string $chatbot_id = '';

	// Parameter names to save with the form in the backend
	var array $parameter_names = [];

	// Parameter names to skip from the form in the frontend
	var array $parameter_to_skip = [];

	// Parameter names to show in the frontend
	// These field(s) are missing in the init constant so we add it here manually. Use it for sorting too. ToDo: add parameter dynamically
	var array $parameter_to_show = [ 'name','envId','instructions','model','temperature' ];

	// Parameters that are not editable but should still be displayed in the form
	var array $readonly_parameters = [ 'botId' ];

	/*
	 * both do not work either with 1/0 or true/false
		 //'window'               => '',
		//'fullscreen'           => '',
	 * */
	var array $chatbot_shortcode_overwrite_parameters = [
		'aiName'               => '',
		'userName'             => '',
		'themeId'              => '',
		'textInputPlaceholder' => '',
		'textSend'             => '',
		'textClear'            => '',
		'textInputMaxLength'   => '',
		'textCompliance'       => '',
		'icon'                 => '',
		'iconText'             => '',
		'iconAlt'              => '',
		'iconPosition'         => '',
	];

	// Define an array with the roles that should have access
	private $allowed_roles;

	public function __construct() {

		// set parameter_tq_skip to constant
		$this->parameter_to_skip = SINTACS_MWAI_CHATBOT_PARAMETER_TO_SKIP;

		add_action( 'plugins_loaded',array( $this,'check_ai_engine_plugin_status' ) );
		//add_action('wp_enqueue_scripts',array($this,'enqueue_scripts'));

		add_shortcode( 'ai_engine_extension_form',array( $this,'form_shortcode' ) );

		// AI Engine Filter
		// This filter is used to overwrite the default parameters for the chatbot
		add_filter( 'mwai_chatbot_params',array( $this,'overwrite_chatbot_params' ),10,1 );

		// Register the AJAX actions for get_available_models
		add_action( 'wp_ajax_get_available_models',array( $this,'ajax_get_available_models' ) );
		add_action( 'wp_ajax_nopriv_get_available_models',array( $this,'ajax_get_available_models' ) );

		add_action( 'wp_ajax_save_ai_engine_parameters',array( $this,'save_parameters' ) );
		add_action( 'wp_ajax_nopriv_save_ai_engine_parameters',array( $this,'save_parameters' ) );
		add_action( 'wp_ajax_save_user_settings',array( $this,'save_user_settings' ) );


		add_action( 'wp_ajax_save_to_original',array( $this,'save_to_original' ) );
		add_action( 'wp_ajax_get_default_settings',function () {
			$chatbot_id       = $_POST['chatbotId'];
			$chatbot_settings = [];
			// Load user settings for this chatbot
			$user_id               = get_current_user_id();
			$user_chatbot_settings = get_user_meta( $user_id,'sintacs_mwai_chatbot_settings_' . $chatbot_id,true );
			// dont merge, send both to the frontend as separat array so the js can handle it
			$chatbot_settings['user_settings']    = $user_chatbot_settings;
			$chatbot_settings['default_settings'] = $this->get_chatbot_settings_by_chatbot_id( $chatbot_id );

			wp_send_json_success( $chatbot_settings );
		} );

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

		// Register the add-on with AI Engine Pro
		add_filter( 'mwai_addons',array( $this,'register_addon' ),10,1 );
	}

	public function register_addon( $addons ) {
		$addons[] = [
			'slug'         => "sintacs-mwai-frontend",
			'name'         => "Sintacs Mwai Frontend Chatbot Settings",
			'icon_url'     => 'https://sintacs.de/favicon.png',
			'description'  => "Allows users to change chatbot parameters on the frontend.",
			'install_url'  => "https://github.com/sintacs/sintacs-mwai-frontend-chatbot-settings",
			'settings_url' => admin_url( 'admin.php?page=sintacs-ai-engine-settings' ),
			'enabled'      => true
		];

		return $addons;
	}

	public function check_ai_engine_plugin_status() {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
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
		wp_enqueue_script( 'sintacs-mwai-frontend-chatbot-settings',plugin_dir_url( __FILE__ ) . 'assets/js/sintacs-mwai-frontend-chatbot-settings.js',array( 'jquery' ),null,true );
		wp_localize_script( 'sintacs-mwai-frontend-chatbot-settings','aiEngineExtensionAjax',array(
			'ajaxurl'  => admin_url( 'admin-ajax.php' ),
			'security' => wp_create_nonce( 'secure_action' )
		) );
		wp_enqueue_style( 'sintacs-mwai-frontend-chatbot-settings',plugin_dir_url( __FILE__ ) . 'assets/css/sintacs-mwai-frontend-chatbot-settings.css' );
	}

	public function save_parameters() {
		if ( ! $this->current_user_has_access() ) {
			wp_send_json_error( [ 'message' => 'You are not allowed to change these settings.' ] );

			return;
		}

		parse_str( nl2br( $_POST['formData'] ),$formDataArray );

		if ( ! isset( $formDataArray['security'] ) || ! wp_verify_nonce( $formDataArray['security'],'sichere_handlung' ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed.' ] );

			return;
		}

		$extractedParameters = [];
		$parameters_to_show  = get_option( 'sintacs_mwai_chatbot_parameters_to_show',$this->parameter_to_show );

		foreach ( $parameters_to_show as $parameter_name ) {
			if ( isset( $formDataArray[ $parameter_name ] ) ) {
				$value                                  = str_replace( '&nbsp;',' ',$formDataArray[ $parameter_name ] );
				$extractedParameters[ $parameter_name ] = stripslashes( $value );
			}
		}

		$chatbot_id = sanitize_text_field( $_POST['chatbotId'] );
		$user_id    = get_current_user_id();
		update_user_meta( $user_id,'sintacs_mwai_chatbot_settings_' . $chatbot_id,$extractedParameters );

		wp_send_json_success( [ 'message' => 'Chatbot settings updated successfully. Reloading the page to take effect.' ] );
	}

	public function save_user_settings() {
		$chatbotId = $_POST['chatbotId'];
		$envId     = $_POST['envId'];
		$user_id   = get_current_user_id();

		// Load existing user settings
		$user_settings = get_user_meta( $user_id,'sintacs_mwai_chatbot_settings_' . $chatbotId,true );

		// Update the envId
		$user_settings['envId'] = $envId;

		// Save the updated settings
		update_user_meta( $user_id,'sintacs_mwai_chatbot_settings_' . $chatbotId,$user_settings );

		wp_send_json_success();
	}

	public function form_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'chatbot_id'  => '',
			'allow_users' => '',
		),$atts,'ai_engine_extension_form' );

		if ( ! $this->is_ai_engine_pro_active() ) {
			return 'AI Engine (Pro) is not active.';
		}

		if ( ! $this->current_user_has_access() ) {
			return 'You are not allowed to access this feature.';
		}

		if ( ! empty( $atts['chatbot_id'] ) ) {
			$chatbot_id = sanitize_text_field( $atts['chatbot_id'] );
		} else {
			$chatbot_id = '';
		}

		if ( ! empty( $atts['allow_users'] ) ) {
			$allow_users = preg_split( '/\s*[,;\s]\s*/',sanitize_text_field( $atts['allow_users'] ) );
			if ( ! is_user_logged_in() || ! in_array( wp_get_current_user()->user_email,$allow_users ) ) {
				return 'You are not allowed to access this feature.';
			}
		}

		// Enqueue scripts and styles only when the shortcode is processed
		$this->enqueue_scripts();

		$form_elements = $this->generate_form_elements_from_parameters( $chatbot_id );

		// if chatbot_id is not empty, execute the shortcode for the ai engine chatbot overriding the default parameters with the user settings
		$ai_engine_chatbot_shortcode = '';
		if ( $chatbot_id !== '' ) {

			$user_id       = get_current_user_id();
			$user_settings = get_user_meta( $user_id,'sintacs_mwai_chatbot_settings_' . $chatbot_id,true );
			// Get original parameters
			$original_parameters = $this->get_chatbot_settings_by_chatbot_id( $chatbot_id );

			// check if user settings not empty
			if ( ! empty( $user_settings ) ) {
				$params_to_overwrite = array_intersect_key( $user_settings,$this->chatbot_shortcode_overwrite_parameters );
			} else {
				$params_to_overwrite = array_intersect_key( $original_parameters,$this->chatbot_shortcode_overwrite_parameters );
			}

			// Convert camelCase keys to snake_case for HTML attributes
			$attributes = array_map( function ( $key,$value ) {
				$snake_key = strtolower( preg_replace( '/(?<!^)[A-Z]/','_$0',$key ) );

				// Check if the value is numeric and convert "1" to true, "0" to false
				if ( is_numeric( $value ) ) {
					$value = $value == 1 ? 'true' : ( $value == 0 ? 'false' : $value );
				}

				return $snake_key . '="' . htmlentities( $value,ENT_QUOTES ) . '"';
			},array_keys( $params_to_overwrite ),$params_to_overwrite );

			// Join the attributes into a string
			$attributes_string           = implode( ' ',$attributes );
			$ai_engine_chatbot           = do_shortcode( '[mwai_chatbot id="' . $chatbot_id . '" ' . $attributes_string . ' ]' );
			$ai_engine_chatbot_shortcode = '<div class="sintacs-ai-engine-shortcode-wrap">' . $ai_engine_chatbot . '</div>';
		}

		// Check if the "Save to Original" button should be shown
		$show_save_to_original = get_option( 'sintacs_mwai_chatbot_show_save_to_original','1' );

		// Form header with close button
		$form_header = '<div class="sintacs-card-header sintacs-d-flex sintacs-justify-content-between sintacs-align-items-center">
						<h4>Chatbot Settings</h4>
						Chatbot: <strong><span id="name-info"></span></strong> - Environment:&nbsp;<strong><span id="env-info"></span></strong>
						<button type="button" id="close-form" class="sintacs-btn sintacs-btn-sm sintacs-btn-danger sintacs-close-form">X</button>
					</div>';

		// Form body
		$form_body = '<div class="sintacs-form-row">' . $form_elements . '</div>';

		// Form footer
		$form_footer = '<div class="sintacs-btn-wrapper">
                            <button type="submit" class="sintacs-btn sintacs-btn-primary sintacs-btn-sm">' . __( 'Save' ) . '</button>';
		if ( $show_save_to_original === '1' ) {
			$form_footer .= '<button type="button" id="save-to-original" class="sintacs-btn sintacs-btn-secondary sintacs-btn-sm">' . __( 'Save to Original' ) . '</button>';
		}
		$form_footer .= '<button type="button" id="reset-to-default" class="sintacs-btn sintacs-btn-warning sintacs-btn-sm">' . __( 'Reset to Original' ) . '</button>';
		$form_footer .= '</div>';

		if ( get_option( 'sintacs_mwai_chatbot_show_footer_info','1' ) === '1' ) {
			$form_footer_info = '<div class="sintacs-card-footer">' . wp_kses_post( get_option( 'sintacs_mwai_chatbot_footer_info_text','<ul>
 	<li>The blue dot icon <img draggable="false" role="img" class="emoji" alt="🔵" src="https://s.w.org/images/core/emoji/15.0.3/svg/1f535.svg"> indicates that the value of the field differs from the original value.</li>
 	<li>The "Save" button saves the settings to the user meta fields and overwrites the original values while chatting with this bot.</li>
 	<li>The "Save to Original" button saves the settings to the original values.</li>
 	<li>The "Reset to Original" button resets the field values to the original values.</li>
</ul>' ) ) . '</div>';
		}

		// Form
		$form_html = $ai_engine_chatbot_shortcode;
		$form_html .= '<button type="button" id="show-form" class="sintacs-btn sintacs-btn-primary sintacs-btn-sm">' . __( 'Edit Chatbot Settings' ) . '</button>';
		$form_html .= '<div id="sintacs-ai-engine-extension-form-wrapper" class="sintacs-container sintacs-mt-4" style="display: none;">
						<div class="sintacs-card">
							' . $form_header . '
							<div class="sintacs-card-body">
								<div id="form-success-message" style="display: none;" class="sintacs-alert sintacs-alert-success"></div>
								<form id="sintacs-ai-engine-extension-form" class="sintacs-needs-validation sintacs-bg-white sintacs-p-2" novalidate>
									' . $form_body . '
									' . $form_footer . '
								</form>
							</div>
                            ' . $form_footer_info . '
						</div>
					</div>';

		return $form_html;
	}

	public function generate_form_elements_from_parameters( $chatbot_id = '' ) {
		$form_elements = '';

		$user_id            = get_current_user_id();
		$user_settings      = get_user_meta( $user_id,'sintacs_mwai_chatbot_settings_' . $chatbot_id,true );
		$parameters_to_show = get_option( 'sintacs_mwai_chatbot_parameters_to_show',$this->parameter_to_show );

		$form_elements .= '<input type="hidden" name="botId" value="' . esc_attr( $chatbot_id ) . '" class="sintacs-form-control sintacs-form-control-sm">';
		$form_elements .= wp_nonce_field( 'sichere_handlung','security',true,false );

		if ( empty( $parameters_to_show[0] ) ) {
			return $form_elements . 'No parameters to show.';
		}

		foreach ( $parameters_to_show as $parameter_name ) {
			$readonly = in_array( $parameter_name,$this->readonly_parameters ) ? ' readonly' : '';
			$label    = esc_html( ucfirst( $this->split_camel_case( $parameter_name ) ) );
			$value    = isset( $user_settings[ $parameter_name ] ) ? esc_attr( $user_settings[ $parameter_name ] ) : '';

			// Replace trailing spaces with non-breaking spaces
			$value = str_replace( ' ','&nbsp;',$value );

			// Determine if the setting is user-defined or original
			$is_user_defined = isset( $user_settings[ $parameter_name ] );
			$icon            = $is_user_defined ? '🔵' : ''; // Blue dot for user-defined, no dot for original

			switch ( $parameter_name ) {
				case 'model':
					$form_elements .= "<div class='sintacs-form-floating'>";
					$form_elements .= "<select id='{$parameter_name}' name='{$parameter_name}' class='sintacs-form-select sintacs-form-select-sm'{$readonly}>";
					$form_elements .= "</select>";
					$form_elements .= "<label for='{$parameter_name}' id='{$parameter_name}-label'>{$label} <span>{$icon}</span></label></div>";
					break;
				case 'instructions':
				case 'context':
				case 'startSentence':
					$form_elements .= "<div class='sintacs-form-floating'>";
					$form_elements .= "<textarea id='{$parameter_name}' name='{$parameter_name}' class='sintacs-form-control sintacs-form-control-sm' placeholder='{$label}'{$readonly}>{$value}</textarea>";
					$form_elements .= "<label for='{$parameter_name}' id='{$parameter_name}-label'>{$label} <span>{$icon}</span></label></div>";
					break;
				case 'mode':
					$modes         = $this->chatbot_modes;
					$form_elements .= "<div class='sintacs-form-floating'>";
					$form_elements .= "<select id='mode' name='mode' class='sintacs-form-select sintacs-form-select-sm'{$readonly}>";
					foreach ( $modes as $mode ) {
						$selected      = $value === $mode ? 'selected' : '';
						$form_elements .= "<option value='{$mode}' {$selected}>{$mode}</option>";
					}
					$form_elements .= "</select>";
					$form_elements .= "<label for='mode' id='{$parameter_name}-label'>Mode <span>{$icon}</span></label></div>";
					break;
				case 'envId':
					$environments  = $this->get_all_mwai_options( 'ai_envs' );
					$form_elements .= "<div class='sintacs-form-floating'>";
					$form_elements .= "<select id='envId' name='envId' class='sintacs-form-select sintacs-form-select-sm'{$readonly}>";

					$form_elements .= "<option value='' selected>Default</option>";

					foreach ( $environments as $env ) {
						$selected      = $value === $env['id'] ? 'selected' : '';
						$form_elements .= "<option value='{$env['id']}' {$selected}>{$env['name']}</option>";
					}
					$form_elements .= "</select>";
					$form_elements .= "<label for='envId' id='{$parameter_name}-label'>Environment <span>{$icon}</span></label></div>";
					break;
				case 'themeId':
					$themes        = $this->get_available_themes();
					$form_elements .= "<div class='sintacs-form-floating'>";
					$form_elements .= "<select id='themeId' name='themeId' class='sintacs-form-select sintacs-form-select-sm'{$readonly}>";
					foreach ( $themes as $theme ) {
						$selected      = $value === $theme['themeId'] ? 'selected' : '';
						$form_elements .= "<option value='{$theme['themeId']}' {$selected}>{$theme['name']}</option>";
					}
					$form_elements .= "</select>";
					$form_elements .= "<label for='themeId' id='{$parameter_name}-label'>Theme <span>{$icon}</span></label></div>";
					break;
				case 'temperature':
					$form_elements .= "<div class='sintacs-form-floating temperature' style=''>";
					$form_elements .= "<label for='{$parameter_name}' id='{$parameter_name}-label'>{$label} <span>{$icon}</span></label>";
					$form_elements .= "<input type='range' id='{$parameter_name}' name='{$parameter_name}' min='0.0' max='1' step='0.1' value='{$value}' class='sintacs-form-range'{$readonly} oninput='document.getElementById(\"{$parameter_name}_value\").innerText = this.value'>";
					$form_elements .= "<span id='{$parameter_name}_value' class='sintacs-range-value'>{$value}</span>";
					$form_elements .= "</div>";
					break;
				case 'window' :
				case 'fullscreen' :
				case 'copyButton' :
				case 'localMemory' :
				case 'contentAware' :

					$form_elements .= "<div class='sintacs-form-check sintacs-form-switch sintacs-py-2'>";
					$form_elements .= "<input class='sintacs-form-check-input' type='checkbox' id='{$parameter_name}' name='{$parameter_name}' value='1' {$readonly}>";
					$form_elements .= "<label class='sintacs-form-check-label' for='{$parameter_name}' id='{$parameter_name}-label'>{$label}</label></div>";
					break;
				default:
					// if name is Default and value is default, the input field can not be changed
					$readonly      = strtolower( $parameter_name ) === 'default' && strtolower( $value ) === 'default' ? 'readonly' : '';
					$form_elements .= "<div class='sintacs-form-floating'>";
					$form_elements .= "<input type='text' id='{$parameter_name}' name='{$parameter_name}' value='{$value}' class='sintacs-form-control sintacs-form-control-sm' placeholder='{$label}'{$readonly}>";
					$form_elements .= "<label for='{$parameter_name}' id='{$parameter_name}-label'>{$label} <span>{$icon}</span></label></div>";
					break;
			}
		}

		return $form_elements;
	}

	public static function get_all_parameter_names() {
		// Return all possible parameter names
		$settings        = new SintacsMwaiFrontendChatbotSettings();
		$parameter_names = array_keys( MWAI_CHATBOT_DEFAULT_PARAMS );

		// Adds array parameter_to_show to parameter_names array at the beginning
		$parameter_names = array_merge( $settings->parameter_to_show,$parameter_names );

		// Exclude parameters in parameter_to_skip array
		$parameter_names = array_diff( $parameter_names,$settings->parameter_to_skip );

		// Remove duplicates
		$parameter_names = array_unique( $parameter_names );

		return $parameter_names;
	}

	public function ajax_get_available_models() {
		// Set chatbot id
		$this->setChatbotId( $_POST['chatbotId'] );
		$chatbot_id = $this->chatbot_id;

		// Get current user ID
		$user_id = get_current_user_id();

		// Load user-specific settings if they exist
		$user_settings = get_user_meta( $user_id,'sintacs_mwai_chatbot_settings_' . $chatbot_id,true );

		// Load default settings
		$default_settings = [];
		$chatbots         = $this->get_wp_option( 'mwai_chatbots' );

		foreach ( $chatbots as $chatbot ) {
			if ( isset( $chatbot['botId'] ) && $chatbot['botId'] === $chatbot_id ) {
				$default_settings = $chatbot;
				break;
			}
		}

		$models = $this->get_available_models();

		// Determine the environment ID to use
		$envId    = isset( $user_settings['envId'] ) ? $user_settings['envId'] : $default_settings['envId'];
		$env_name = $this->get_environment_name_by_id( $envId );

		wp_send_json_success( [
			'models'           => $models,
			'chatbot_settings' => $user_settings,
			'default_settings' => $default_settings,
			'env_name'         => $env_name
		] );
	}

	private function get_environment_name_by_id( $envId ) {
		$environments = $this->get_all_mwai_options( 'ai_envs' );
		foreach ( $environments as $env ) {
			if ( $env['id'] === $envId ) {
				return $env['name'];
			}
		}

		return 'Default';
	}

	private function get_available_models(): array {
		$options = $this->get_wp_option( 'mwai_options' );
		$models  = [];

		$models[] = [
			'model' => '',
			'name'  => 'Default'
		];

		// Retrieve the default environment ID from mwai_options
		$default_envId = isset( $options['ai_default_env'] ) ? $options['ai_default_env'] : 'mpjogv7g'; // Assuming 'mpjogv7g' is the ID for 'openai'

		// Retrieve the environment ID for the current chatbot
		$envId = $this->get_environment_id_by_chatbot_id( $this->chatbot_id );

		// Get current user ID
		$user_id = get_current_user_id();

		// Load user-specific settings if they exist
		$user_settings = get_user_meta( $user_id,'sintacs_mwai_chatbot_settings_' . $this->chatbot_id,true );

		// Check if user has set an envId
		if ( ! empty( $user_settings['envId'] ) ) {
			$envId = $user_settings['envId'];
		} else {
			// If no envId is set, use the default environment ID
			$envId = $default_envId;
		}

		// Find the environment based on the envId
		$environments = $this->get_all_mwai_options( 'ai_envs' );
		$current_env  = null;
		foreach ( $environments as $env ) {
			if ( $env['id'] === $envId ) {
				$current_env = $env;
				break;
			}
		}

		if ( ! $current_env ) {
			return $models; // Return empty if no matching environment is found
		}

		// Get the type of the current environment
		$env_type = $current_env['type'];

		// Retrieve models based on the environment type
		$type_models_key = $env_type . '_models';
		if ( ! empty( $options[ $type_models_key ] ) ) {
			$models = array_merge( $models,$options[ $type_models_key ] );
		}

		// Check if the user's model is part of the current environment's models
		if ( ! empty( $user_settings['model'] ) ) {
			$user_model_valid = false;
			foreach ( $models as $model ) {
				if ( $model['model'] === $user_settings['model'] ) {
					$user_model_valid = true;
					break;
				}
			}
			if ( ! $user_model_valid ) {
				$user_settings['model'] = $models[0]['model'];
				update_user_meta( $user_id,'sintacs_mwai_chatbot_settings_' . $this->chatbot_id,$user_settings );
			}
		} else {
			// Set the first model of the environment if no model is set by the user
			if ( ! empty( $models ) ) {
				$user_settings['model'] = $models[0]['model'];
				update_user_meta( $user_id,'sintacs_mwai_chatbot_settings_' . $this->chatbot_id,$user_settings );
			}
		}


		// Add finetunes if available
		if ( ! empty( $current_env['finetunes'] ) ) {
			foreach ( $current_env['finetunes'] as $finetune ) {
				$finetune_name = isset( $finetune['suffix'] ) ? $finetune['suffix'] : $finetune['id'];
				$models[]      = [
					'model' => $finetune['model'],
					'name'  => $finetune_name
				];
			}
		}

		return $models;
	}

	public function setChatbotId( string $chatbot_id ): void {
		$this->chatbot_id = $chatbot_id;
	}

	private function get_chatbot_settings_by_chatbot_id( $chatbotId ) {
		// Retrieve chatbots from WP options under the key 'mwai_chatbots'
		$chatbots = $this->get_wp_option( 'mwai_chatbots' );
		foreach ( $chatbots as $chatbot ) {
			if ( isset( $chatbot['botId'] ) && $chatbot['botId'] === $chatbotId ) {
				// Return the chatbot settings if the chatbot was found
				return $chatbot ?? null;

			}
		}

		// Return null if no match was found
		return null;
	}

	private function get_environment_id_by_chatbot_id( $chatbotId ) {
		// Retrieve chatbots from WP options under the key 'wai_chatbots'
		$chatbots = $this->get_wp_option( 'mwai_chatbots' );

		foreach ( $chatbots as $chatbot ) {
			if ( isset( $chatbot['botId'] ) && $chatbot['botId'] === $chatbotId ) {
				// Return the environment ID if the chatbot was found
				$envId = $chatbot['envId'] ?? null;

				return empty( $envId ) ? 'default' : $envId;
			}
		}

		// Return 'default' if no match is found
		return 'default';
	}

	private function get_wp_option( $option_name ) {
		// Ensure you use the correct option key
		return get_option( $option_name,[] );
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

	public function save_to_original() {
		if ( ! $this->current_user_has_access() ) {
			wp_send_json_error( [ 'message' => 'You are not allowed to change these settings.' ] );

			return;
		}

		parse_str( nl2br( $_POST['formData'] ),$formDataArray );
		$chatbot_id    = sanitize_text_field( $_POST['chatbotId'] );
		$user_id       = get_current_user_id();
		$user_settings = get_user_meta( $user_id,'sintacs_mwai_chatbot_settings_' . $chatbot_id,true );

		$chatbots = get_option( 'mwai_chatbots',[] );
		foreach ( $chatbots as &$chatbot ) {
			if ( $chatbot['botId'] === $chatbot_id ) {
				foreach ( $user_settings as $key => $value ) {
					$chatbot[ $key ] = $value;
				}
				break;
			}
		}
		update_option( 'mwai_chatbots',$chatbots );
		wp_send_json_success( [ 'message' => 'Settings saved to original successfully.' ] );
	}

	public static function split_camel_case( $string ) {
		return preg_replace( '/([a-z])([A-Z])/','$1 $2',$string );
	}

	public function overwrite_chatbot_params( $params ) {

		if ( $params[0] ) {
			preg_match( '/id="([^"]+)"/',$params[0],$matches );

			$chatbot_id = $matches[1];
		} elseif ( $params['botId'] ) {
			$chatbot_id = $params['botId'];
		} else {
			return $params;
		}

		$user_id       = get_current_user_id();
		$user_settings = get_user_meta( $user_id,'sintacs_mwai_chatbot_settings_' . $chatbot_id,true );

		if ( ! $user_settings ) {
			return $params;
		}

		if ( empty( $chatbot_id ) ) {
			return $params;
		}

		return array_merge( $params,$user_settings );
	}

	private function get_available_themes() {
		global $mwai_core;

		//$theme_option_name = get_theme_option_name( $mwai_core );
		return $mwai_core->get_themes();
	}
}

new SintacsMwaiFrontendChatbotSettings();