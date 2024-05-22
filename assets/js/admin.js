jQuery(document).ready(function ($) {
    $('#copy-shortcode-button').click(function (e) {
        e.preventDefault();
        console.log('copy-shortcode-button clicked');
        var $shortcodeInput = $('#chatbot-shortcode');
        $shortcodeInput.select();
        document.execCommand('copy');
        alert('Shortcode copied to clipboard!');
    });
});