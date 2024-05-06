# Sintacs Mwai Frontend Chatbot Settings

## Description
This WordPress plugin allows users to change chatbot parameters directly from the frontend. It is designed to work with the AI Engine or AI Engine Pro plugins.

## Version
1.0

## Author
Dirk Krölls, Sintacs

## Installation
1. Ensure that either the ai-engine or ai-engine-pro plugin is installed and activated.
2. Upload the `sintacs-mwai-frontend-chatbot-settings` folder to the `/wp-content/plugins/` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress.

## New Features
- Admin page to configure allowed roles:
  - Navigate to the 'AI Engine Frontend Chatbot Settings' menu in your WordPress admin panel.
  - Use the 'Allowed Roles' setting to specify which user roles are permitted to modify chatbot settings.
  - This setting ensures that only authorized users can make changes to the chatbot configurations.

## Usage
- Use the shortcode `[ai_engine_extension_form]` to display the chatbot settings form on any page.
- Only defined user roles can change settings, depending on the configuration in the new admin settings page.

## Features
- Dynamic parameter handling based on defined chatbot settings.
- AJAX-based form submission for seamless user experience.
- Security checks to prevent unauthorized access.
- Admin page to configure which roles can manage chatbot settings.

## Dependencies
- AI Engine or AI Engine Pro plugin must be active.

## Changelog
### 1.0
- Initial release.
- Added admin page for role-based access control.

## Support
For support, please contact the plugin author at `info@sintacs.com`.

## License
This plugin is licensed under the GPL-2.0+ license.