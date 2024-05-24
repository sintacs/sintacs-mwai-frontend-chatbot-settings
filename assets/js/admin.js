jQuery(document).ready(function($) {
    // Set the default value on page load
    var defaultChatbotId = $('#chatbot-select').val();
    $('#chatbot-shortcode').val('[ai_engine_extension_form chatbot_id="' + defaultChatbotId + '"]');

    $('#chatbot-select').change(function () {
        var chatbotId = $(this).val();
        $('#chatbot-shortcode').val('[ai_engine_extension_form chatbot_id="' + chatbotId + '"]');
    });
    $('#copy-shortcode-button').click(function (e) {
        e.preventDefault();
        var $shortcodeInput = $('#chatbot-shortcode');
        $shortcodeInput.select();
        document.execCommand('copy');
        alert('Shortcode copied to clipboard!');
    });

    $("#sortable-parameters").sortable({
        placeholder: "ui-state-highlight",
        update: function(event, ui) {
            updateParametersOrder();
        }
    });
    $("#sortable-parameters").disableSelection();

    $("#select-all").click(function() {
        $("#sortable-parameters input[type=checkbox]").prop('checked', true);
        updateParametersOrder();
    });

    $("#deselect-all").click(function() {
        $("#sortable-parameters input[type=checkbox]").prop('checked', false);
        updateParametersOrder();
    });

    $("#sortable-parameters input[type=checkbox]").on('change', function() {
        updateParametersOrder();
    });

    function updateParametersOrder() {
        var order = [];
        $("#sortable-parameters li").each(function() {
            var input = $(this).find('input[type=checkbox]');
            if (input.is(':checked')) {
                order.push(input.val());
            }
        });
        $('#parameters-order').val(order.join(','));
    }

    // Initial update
    updateParametersOrder();
});