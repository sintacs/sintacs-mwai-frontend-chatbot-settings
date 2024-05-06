<?php
class SintacsMwaiFrontendChatbotSettingsAdmin {
	public function __construct() {
		add_action('admin_menu', array($this, 'add_admin_menu'));
		add_action('admin_init', array($this, 'settings_init'));
	}

	public function add_admin_menu() {
/*
		add_menu_page(
			'AI Engine Frontend Chatbot Settings',
			'AI Engine Chatbot Frontend Settings',
			'manage_options',
			'ai-engine-frontend',
			[$this,'settings_page_html'] // function
		);
*/
		// Add submenu page to Meow Apps main menu
		add_submenu_page(
			'meowapps-main-menu',
			'AI Engine Frontend Chatbot Settings',
			'AI Engine Frontend Chatbot Settings',
			'manage_options',
			'chats_frontend_settings',
			[$this,'settings_page_html'] // function
		);

		// Add to tools page
		add_management_page(
			'AI Engine Frontend Chatbot Settings', // page_title
			'AI Engine Frontend Chatbot Settings', // menu_title
			'manage_options', // capability
			'chats_frontend_settings', // menu_slug
			[$this,'settings_page_html'] // function
		);

	}

	public function settings_page_html() {
		if (!current_user_can('manage_options')) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?= esc_html(get_admin_page_title()); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields('ai_engine_frontend');
				do_settings_sections('ai_engine_frontend');
				submit_button('Save Settings');
				?>
			</form>
		</div>
		<?php
	}

	public function settings_init() {
		register_setting('ai_engine_frontend', 'sintacs_mwai_chatbot_frontend_allowed_roles', array(
			'sanitize_callback' => array($this, 'sanitize_allowed_roles'),
			'default' => array()
		));
	
		add_settings_section(
			'ai_engine_frontend_section',
			__('AI Engine Frontend Chatbot Settings', 'textdomain'),
			null,
			'ai_engine_frontend'
		);
	
		add_settings_field(
			'sintacs_mwai_chatbot_frontend_allowed_roles',
			__('Allowed Roles', 'textdomain'),
			array($this, 'allowed_roles_field_render'),
			'ai_engine_frontend',
			'ai_engine_frontend_section'
		);
	}
	
	public function allowed_roles_field_render() {
		$options = get_option('sintacs_mwai_chatbot_frontend_allowed_roles');
		$roles = wp_roles()->roles;
		$allowed_roles = is_array($options) ? $options : array();
	
		echo '<select name="sintacs_mwai_chatbot_frontend_allowed_roles[]" multiple class="allowed-roles-select">';
		foreach ($roles as $role_key => $role_info) {
			$selected = in_array($role_key, $allowed_roles) ? 'selected' : '';
			echo '<option value="' . esc_attr($role_key) . '" ' . $selected . '>' . esc_html($role_info['name']) . '</option>';
		}
		echo '</select>';
	}
	
	public function settings_section_callback() {
		echo __('Set the roles allowed to change settings.', 'textdomain');
	}

	public function sanitize_allowed_roles($input) {
		$valid_roles = array_keys(wp_roles()->roles);
		$output = array_intersect($valid_roles, $input);
		return $output;
	}
}

new SintacsMwaiFrontendChatbotSettingsAdmin();