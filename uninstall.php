<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

$delete_settings = get_option('sintacs_mwai_chatbot_delete_settings_on_uninstall', '0');

if ($delete_settings === '1') {
    delete_option('sintacs_mwai_chatbot_frontend_allowed_roles');
    delete_option('sintacs_mwai_chatbot_delete_settings_on_uninstall');
}