# Sintacs Mwai Frontend Chatbot Settings

## Description
This WordPress plugin allows users to change chatbot parameters directly from the frontend. It is designed to work with the AI Engine or AI Engine Pro plugins.
It allows you to edit existing chatbot settings in the frontend and save settings per user with the option to update the original chatbot settings. It requires having the chatbot previously created.

## Version
1.2

## Author
Dirk Krölls, Sintacs

## Installation
1. Ensure that either the ai-engine or ai-engine-pro plugin is installed and activated.
2. Upload the `sintacs-mwai-frontend-chatbot-settings` folder to the `/wp-content/plugins/` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress.

## Admin page settings:
- Navigate to the 'AI Engine Frontend Chatbot Settings' menu in your WordPress admin panel.
- Use the 'Allowed Roles' setting to specify which user roles are permitted to modify chatbot settings. This setting ensures that only authorized users can make changes to the chatbot configurations.
- Use the 'Show Save to Original' Button to save the chatbot user settings to the original chatbot overwrite settings).
- Set the 'Parameters to Show' setting to specify which parameters should be shown in the chatbot settings form. This setting ensures that only the selected parameters are shown in the form.
- Use Drag and Drop to reorder the 'Parameters to Show'.

## Usage
- Use the shortcode `[ai_engine_extension_form chatbot_id="your_chatbot_id"]` to display the chatbot settings form on any page.
- The `chatbot_id` attribute is optional. If not provided, the plugin will attempt to determine the chatbot ID dynamically.
- Only defined user roles can change settings, depending on the configuration in the new admin settings page.
- The chatbot settings will be saved for each user and take only effect for the current user.
- Use the 'Save to Original' Button to save the user settings to the original chatbot, so that it will effect every user of this chatbot.
- Use the 'Reset to Original' Button to load the original chatbot settings into the form. Needs save to take effect for the current user.

## Features
- **Dynamic Parameter Handling**: Adjusts parameters based on defined chatbot settings.
- **AJAX-based Form Submission**: Ensures a seamless user experience without page reloads.
- **Security Checks**: Prevents unauthorized access to chatbot configurations.
- **Admin Page Configuration**: Allows configuration of roles that can manage chatbot settings.
- **Frontend Configuration**: Allows configuration of which parameters should be shown and in which order in the chatbot settings form.
- **The blue icon**: Indicates that the setting differs from the original.

### Limitations
- **Only one** chatbot can be edited on one page. That means you can only use the shortcode once on a page/post.

## Dependencies
- AI Engine or AI Engine Pro plugin must be active.

## Changelog

### 1.2
New
Frontend
- Chatbot name and environment is now displayed in the form header
- If chatbot_id set via shortcode, the chat is displayed above the form by executing the shortcode [mwai_chatbot] by adding the attribute id to chatbot_id and the attributes aiName, userName, themeId, textInputPlaceholder, textSend, textClear, textInputMaxLength, textCompliance to reflect the user settings in the chatbot.
- Button to show/hide the edit form
- Environment to the chatbot settings header
- Select option "Default" for the environment setting to reflect the backend options
- Form footer with hint for the blue dot icon

Admin
- Function to choose a chatbot and copy the shortcode

### 1.1
## New
Admin
- Added "Delete Settings on Uninstall" option to allow clean removal of plugin data when uninstalled.
- Added "Show 'Save to Original' Button" setting for better control over frontend display.
- Implemented sortable parameters feature for customizing the display order of chatbot settings.

Frontend
- Added 'Save to Original' Button to save the user settings to the original chatbot.
- Added 'Reset to Original' Button to reset the settings to the chatbot original.
- Changed temperature parameter from text input to range slider.

- Various layout and style optimizations

### 1.0
- Initial release.
- Added admin page for role-based access control.

## Support
For support, please contact the plugin author at `info@sintacs.de`.

## License
This plugin is licensed under the GPL-2.0+ license.