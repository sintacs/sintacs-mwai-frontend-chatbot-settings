jQuery(document).ready(function($) {
    // Set the default value on page load
    var defaultChatbotId = $('#chatbot-select').val();
    var defaultUsers = $('#user-select').val();
    updateShortcode();

    $('#chatbot-select').change(function () {
        updateShortcode();
    });

    $('#user-select').change(function () {
        updateShortcode();
    });

    $('#copy-shortcode-button').click(function (e) {
        e.preventDefault();
        var $shortcodeInput = $('#chatbot-shortcode');
        $shortcodeInput.select();
        document.execCommand('copy');
        alert('Shortcode copied to clipboard!');
    });

    $('#reset-shortcode-button').click(function (e) {
        e.preventDefault();
        $('#chatbot-select').val(defaultChatbotId);
        $('#user-select').val(defaultUsers);
        updateShortcode();
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

    function updateShortcode() {
        var chatbotId = $('#chatbot-select').val();
        var userSelect = $('#user-select');
        var selectedUsers = [];
        userSelect.find('option:selected').each(function() {
            selectedUsers.push($(this).val());
        });
        var allowUsers = selectedUsers.join(',');
        $('#chatbot-shortcode').val('[ai_engine_extension_form chatbot_id="' + chatbotId + '" allow_users="' + allowUsers + '"]');
    }

    // Initial update
    updateParametersOrder();
});