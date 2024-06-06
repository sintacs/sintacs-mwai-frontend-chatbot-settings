<?php

class SintacsMwaiFrontendChatbotSettingsAdmin {
	public function __construct() {
		add_action( 'admin_menu',array( $this,'add_admin_menu' ),11 );
		add_action( 'admin_init',array( $this,'settings_init' ) );
		add_action( 'admin_enqueue_scripts',array( $this,'enqueue_admin_scripts' ) );
	}

	public function add_admin_menu() {
		// Add submenu page to Meow Apps main menu
		add_submenu_page(
			'meowapps-main-menu',
			'AI Engine Frontend Chatbot Settings',
			'AI Engine Frontend Chatbot Settings',
			'manage_options',
			'chats_frontend_settings',
			[ $this,'settings_page_html' ] // function
		);
	}

	public function settings_page_html() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$chatbots         = $this->get_all_chatbots(); // Fetch chatbots
		$defaultChatbotId = ! empty( $chatbots ) ? esc_attr( $chatbots[0]['botId'] ) : ''; // Get the first chatbot ID
		?>
        <div class="wrap">
            <h1><?= esc_html( get_admin_page_title() ); ?></h1>
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <!-- main content -->
                    <div id="post-body-content">
                        <div class="meta-box-sortables ui-sortable">
                            <div class="postbox">
                                <h2 class="hndle"><span><?php _e( 'Settings','textdomain' ); ?></span></h2>
                                <div class="inside">
                                    <form action="options.php" method="post">
										<?php
										settings_fields( 'ai_engine_frontend' );
										do_settings_sections( 'ai_engine_frontend' );
										submit_button( 'Save Settings' );
										?>
                                        <input type="hidden" id="parameters-order"
                                               name="sintacs_mwai_chatbot_parameters_order"
                                               value="<?php echo esc_attr( implode( ',',get_option( 'sintacs_mwai_chatbot_parameters_to_show',[] ) ) ); ?>">
                                    </form>
                                    <h2><?php _e( 'Shortcode','textdomain' ); ?></h2>
                                    <p><?php _e( 'Use the following shortcode to insert the chatbot form into a post or page:','textdomain' ); ?></p>
                                    <select id="chatbot-select">
										<?php foreach ( $chatbots as $chatbot ): ?>
                                            <option value="<?= esc_attr( $chatbot['botId'] ); ?>"><?= esc_html( $chatbot['name'] ); ?></option>
										<?php endforeach; ?>
                                    </select>
                                    <input type="text" id="chatbot-shortcode"
                                           value="[ai_engine_extension_form chatbot_id=&quot;<?= $defaultChatbotId; ?>&quot;]"
                                           readonly style="width: 100%; max-width: 600px;">
                                    <button id="copy-shortcode-button"
                                            class="button"><?php _e( 'Copy Shortcode','textdomain' ); ?></button>
                                    <p><?php _e( 'If you do not specify a chatbot ID, it will default to the first chatbot on the current post / page.','textdomain' ); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- sidebar -->
                    <div id="postbox-container-1" class="postbox-container">
                        <div class="meta-box-sortables">
                            <div class="postbox">
                                <h2 class="hndle"><span><?php _e( 'FAQ','textdomain' ); ?></span></h2>
                                <div class="inside">
                                    <div class="faq-item">
                                        <h3><?php _e( 'How do I add a chatbot to a page?','textdomain' ); ?></h3>
                                        <p><?php _e( 'Use the shortcode provided above to insert the chatbot form into any post or page.','textdomain' ); ?></p>
                                    </div>
                                    <div class="faq-item">
                                        <h3><?php _e( 'How do I manage chatbot settings?','textdomain' ); ?></h3>
                                        <p><?php _e( 'You can manage chatbot settings from this admin page. Make sure to save your changes.','textdomain' ); ?></p>
                                    </div>
                                    <div class="faq-item">
                                        <h3><?php _e( 'What happens if I uninstall the plugin?','textdomain' ); ?></h3>
                                        <p><?php _e( 'If you select the option to delete settings on uninstall, all settings will be removed when the plugin is deleted.','textdomain' ); ?></p>
                                    </div>
                                    <div class="faq-item">
                                        <h3><?php _e( 'How do I sort the parameters?','textdomain' ); ?></h3>
                                        <p><?php _e( 'You can sort the parameters by dragging and dropping them in the desired order in the "Parameters to Show" section. Remember to save your changes after sorting.','textdomain' ); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <br class="clear">
            </div>
        </div>
		<?php
	}

	private function get_all_chatbots() {
		return get_option( 'mwai_chatbots',[] );
	}

	public function settings_init() {
		register_setting( 'ai_engine_frontend','sintacs_mwai_chatbot_frontend_allowed_roles',array(
			'sanitize_callback' => array( $this,'sanitize_allowed_roles' ),
			'default'           => array()
		) );

		register_setting( 'ai_engine_frontend','sintacs_mwai_chatbot_delete_settings_on_uninstall',array(
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '0'
		) );

		register_setting( 'ai_engine_frontend','sintacs_mwai_chatbot_show_save_to_original',array(
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '1'
		) );

		register_setting( 'ai_engine_frontend','sintacs_mwai_chatbot_parameters_to_show',array(
			'sanitize_callback' => array( $this,'sanitize_parameters_to_show' ),
			'default'           => array()
		) );

		register_setting( 'ai_engine_frontend','sintacs_mwai_chatbot_show_footer_info',array(
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '1'
		) );

		register_setting( 'ai_engine_frontend','sintacs_mwai_chatbot_footer_info_text',array(
			'sanitize_callback' => 'wp_kses_post',
			'default'           => '<ul>
 	<li>The blue dot icon <img draggable="false" role="img" class="emoji" alt="🔵" src="https://s.w.org/images/core/emoji/15.0.3/svg/1f535.svg"> indicates that the value of the field differs from the original value.</li>
 	<li>The "Save" button saves the settings to the user meta fields and overwrites the original values while chatting with this bot.</li>
 	<li>The "Save to Original" button saves the settings to the original values.</li>
 	<li>The "Reset to Original" button resets the field values to the original values.</li>
</ul>'
		) );

		add_settings_section(
			'ai_engine_frontend_section',
			__( 'AI Engine Frontend Chatbot Settings','textdomain' ),
			null,
			'ai_engine_frontend'
		);

		add_settings_field(
			'sintacs_mwai_chatbot_parameters_to_show',
			__( 'Parameters to Show','textdomain' ),
			array( $this,'parameters_to_show_field_render' ),
			'ai_engine_frontend',
			'ai_engine_frontend_section'
		);

		add_settings_field(
			'sintacs_mwai_chatbot_frontend_allowed_roles',
			__( 'Allowed Roles','textdomain' ),
			array( $this,'allowed_roles_field_render' ),
			'ai_engine_frontend',
			'ai_engine_frontend_section'
		);

		add_settings_field(
			'sintacs_mwai_chatbot_delete_settings_on_uninstall',
			__( 'Delete Settings on Uninstall','textdomain' ),
			array( $this,'delete_settings_on_uninstall_field_render' ),
			'ai_engine_frontend',
			'ai_engine_frontend_section'
		);

		add_settings_field(
			'sintacs_mwai_chatbot_show_save_to_original',
			__( 'Show "Save to Original" Button','textdomain' ),
			array( $this,'show_save_to_original_field_render' ),
			'ai_engine_frontend',
			'ai_engine_frontend_section'
		);

		add_settings_field(
			'sintacs_mwai_chatbot_show_footer_info',
			__( 'Show Footer Info','textdomain' ),
			array( $this,'show_footer_info_field_render' ),
			'ai_engine_frontend',
			'ai_engine_frontend_section'
		);

		add_settings_field(
			'sintacs_mwai_chatbot_footer_info_text',
			__( 'Footer Info Text','textdomain' ),
			array( $this,'footer_info_text_field_render' ),
			'ai_engine_frontend',
			'ai_engine_frontend_section'
		);
	}

	public function allowed_roles_field_render() {
		$options       = get_option( 'sintacs_mwai_chatbot_frontend_allowed_roles' );
		$roles         = wp_roles()->roles;
		$allowed_roles = is_array( $options ) ? $options : array();

		echo '<select name="sintacs_mwai_chatbot_frontend_allowed_roles[]" multiple class="allowed-roles-select">';
		foreach ( $roles as $role_key => $role_info ) {
			$selected = in_array( $role_key,$allowed_roles ) ? 'selected' : '';
			echo '<option value="' . esc_attr( $role_key ) . '" ' . $selected . '>' . esc_html( $role_info['name'] ) . '</option>';
		}
		echo '</select>';
	}

	public function delete_settings_on_uninstall_field_render() {
		$option = get_option( 'sintacs_mwai_chatbot_delete_settings_on_uninstall','0' );
		?>
        <input type="checkbox" name="sintacs_mwai_chatbot_delete_settings_on_uninstall"
               value="1" <?php checked( '1',$option ); ?> />
        <label for="sintacs_mwai_chatbot_delete_settings_on_uninstall"><?php _e( 'Delete all settings when the plugin is deleted','textdomain' ); ?></label>
		<?php
	}

	public function show_save_to_original_field_render() {
		$option = get_option( 'sintacs_mwai_chatbot_show_save_to_original','1' );
		?>
        <input type="checkbox" name="sintacs_mwai_chatbot_show_save_to_original"
               value="1" <?php checked( '1',$option ); ?> />
        <label for="sintacs_mwai_chatbot_show_save_to_original"><?php _e( 'Show the "Save to Original" button on the frontend','textdomain' ); ?></label>
		<?php
	}

	public function parameters_to_show_field_render() {
		$saved_options  = get_option( 'sintacs_mwai_chatbot_parameters_to_show',[] );
		$all_parameters = SintacsMwaiFrontendChatbotSettings::get_all_parameter_names();

		// Start with the parameters that are saved and checked
		$ordered_parameters = array_intersect( $saved_options,$all_parameters );
		// Add the remaining parameters that are not saved at the end
		$remaining_parameters = array_diff( $all_parameters,$saved_options );
		$ordered_parameters   = array_merge( $ordered_parameters,$remaining_parameters );

		echo '<p>';
		echo '<button type="button" id="select-all" class="button button-secondary">Select All</button> ';
		echo '<button type="button" id="deselect-all" class="button button-secondary">Deselect All</button>';
		echo '</p>';

		echo '<div class="params-to-show-wrap">';
		echo '<ul id="sortable-parameters" class="connectedSortable">';
		foreach ( $ordered_parameters as $parameter ) {
			$checked = in_array( $parameter,$saved_options ) ? 'checked' : '';
			$label   = esc_html( ucfirst( SintacsMwaiFrontendChatbotSettings::split_camel_case( $parameter ) ) );
			echo '<li class="ui-state-default"><input type="checkbox" name="sintacs_mwai_chatbot_parameters_to_show[]" value="' . esc_attr( $parameter ) . '" ' . $checked . '> ' . esc_html( $label ) . '</li>';
		}
		echo '</ul>';
		echo '</div>';
	}

	public function sanitize_parameters_to_show() {
		$ordered_input = isset( $_POST['sintacs_mwai_chatbot_parameters_order'] ) ? explode( ',',$_POST['sintacs_mwai_chatbot_parameters_order'] ) : [];

		// The $ordered_input already maintains the order, so no further action is needed
		// It's an indexed array with the correct order of keys

		return $ordered_input;
	}

	public function settings_section_callback() {
		echo __( 'Set the roles allowed to change settings.','textdomain' );
	}

	public function sanitize_allowed_roles( $input ): array {
		$valid_roles = array_keys( wp_roles()->roles );

		return array_intersect( $valid_roles,$input );
	}

	public function enqueue_admin_scripts( $hook ) {
		if ( $hook !== 'meow-apps_page_chats_frontend_settings' ) {
			return;
		}
		wp_enqueue_script( 'sintacs-admin-js',plugin_dir_url( __FILE__ ) . 'assets/js/admin.js',array( 'jquery' ),null,true );
		wp_enqueue_style( 'sintacs-admin-css',plugin_dir_url( __FILE__ ) . 'assets/css/admin.css' );
	}

	public function show_footer_info_field_render() {
		$option = get_option( 'sintacs_mwai_chatbot_show_footer_info','1' );
		?>
        <input type="checkbox" name="sintacs_mwai_chatbot_show_footer_info"
               value="1" <?php checked( '1',$option ); ?> />
        <label for="sintacs_mwai_chatbot_show_footer_info"><?php _e( 'Show the footer info on the frontend','textdomain' ); ?></label>
		<?php
	}

	public function footer_info_text_field_render() {
		$option = get_option( 'sintacs_mwai_chatbot_footer_info_text','Default footer info text.' );
		wp_editor( $option,'sintacs_mwai_chatbot_footer_info_text',array(
			'textarea_name' => 'sintacs_mwai_chatbot_footer_info_text',
			'textarea_rows' => 10,
			'media_buttons' => false,
			'teeny'         => true,
			'quicktags'     => false,
		) );
	}
}

new SintacsMwaiFrontendChatbotSettingsAdmin();

