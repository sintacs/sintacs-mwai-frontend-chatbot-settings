<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

// Check if the option to delete settings on uninstall is enabled
$delete_settings = get_option('sintacs_mwai_chatbot_delete_settings_on_uninstall', '0');

if ($delete_settings === '1') {
    // Delete the plugin options
    delete_option('sintacs_mwai_chatbot_frontend_allowed_roles');
    delete_option('sintacs_mwai_chatbot_parameters_to_show');
    delete_option('sintacs_mwai_chatbot_delete_settings_on_uninstall');

    // Delete all user chatbot settings
    $users = get_users();
    foreach ($users as $user) {
        $user_id = $user->ID;
        // Get all user meta keys for the user
        $user_meta_keys = get_user_meta($user_id);
        foreach ($user_meta_keys as $meta_key => $meta_value) {
            // Check if the meta key starts with 'sintacs_mwai_chatbot_settings_'
            if (strpos($meta_key, 'sintacs_mwai_chatbot_settings_') === 0) {
                delete_user_meta($user_id, $meta_key);
            }
        }
    }
}
