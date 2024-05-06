jQuery(document).ready(function ($) {
    function waitForChatbot() {
        var checkExist = setInterval(function () {
            var $chatElement = $('.mwai-chatbot-container .mwai-chat');
            if ($chatElement.length && $chatElement.attr('id')) {
                console.log("Chatbot element found.");
                clearInterval(checkExist);

                // Extracting the Chatbot ID from the ID of the child element
                var chatElementId = $chatElement.attr('id');
                chatbotId = chatElementId.replace('mwai-chatbot-', '');
                $('#botId-info').text(chatbotId);
                console.log("Chatbot ID:", chatbotId);

                // The chatbot is loaded, bind the form submit event here
                bindFormSubmitEvent();

                // Update models based on the Chatbot ID
                updateModelsDropdown();
            } else {
                console.log("No chatbot element found on the page.");
                // Check if an attempt has already been made to find the chatbot
                if ($('#ai-engine-extension-form').length) {
                    // Hide form and replace it with an invisible comment
                    $('#ai-engine-extension-form').replaceWith('No chatbot found on the current page.');
                    clearInterval(checkExist);
                }
            }
        }, 100); // Check every 100ms
    }

    function toggleFormElements(disabled) {
        $('#ai-engine-extension-form').find('button[type="submit"], :input').prop('disabled', disabled);
    }

    function bindFormSubmitEvent() {
        $('#ai-engine-extension-form').submit(function (e) {
            e.preventDefault();

            // Serialize the form data before disabling the fields
            var formData = $(this).serialize();

            // Disable the submit button and lock the form fields
            toggleFormElements(true);

            $.ajax({
                type: "POST",
                url: aiEngineExtensionAjax.ajaxurl,
                data: {
                    action: 'save_ai_engine_parameters',
                    chatbotId: chatbotId, // Sending the Chatbot ID with the request
                    formData: formData
                },
                success: function (response) {
                    // Display response message as alert if exist
                    if (response.data.message) {
                        // Add Success border to the form
                        $('#ai-engine-extension-form-wrapper').addClass('border border-success');
                        $('#form-success-message').text(response.data.message);
                        $('#form-success-message').show();
                        // Reload the page to take the changed settings into effect
                        location.reload();
                    } else {
                        $('#ai-engine-extension-form-wrapper').addClass('border border-danger');
                        alert('Something went wrong. Please try again.');
                    }
                },
                error: function (response) {
                    // Error handling...
                    alert('An error occurred.');
                },
                complete: function () {
                    // Release the submit button and form fields regardless of success or failure
                    toggleFormElements(false);
                    // Fade out success notice after a few seconds
                    setTimeout(function () {
                        $('#form-success-message').fadeOut();
                        $('#form-success-message').text('');
                    }, 5000); // Fade out notice after 5 seconds
                }
            });
        });
    }

    function updateModelsDropdown() {
        $.ajax({
            type: "POST",
            url: aiEngineExtensionAjax.ajaxurl,
            data: {
                action: 'get_available_models',
                chatbotId: chatbotId // Sending the Chatbot ID with the request
            },
            success: function (response) {
                if (response.success && response.data['models']) {
                    var models = response.data['models'];
                    var $modelSelect = $('#model');
                    $modelSelect.empty(); // Delete existing options

                    // Add models to the dropdown
                    models.forEach(function (model) {
                        $modelSelect.append($('<option></option>').attr('value', model.model).text(model.name));
                    });

                    // Set chatbot name
                    var chatbotName = response.data['chatbot_settings']['name'];
                    $('#name-info').text(chatbotName);
                    console.log('name: ' + chatbotName);

                    // Set current model
                    var currentModel = response.data['chatbot_settings']['model'];
                    // check if currentModel is in the list of available models
                    var modelFound = false;
                    models.forEach(function (model) {
                        if (model.model === currentModel) {
                            modelFound = true;
                        }
                    });

                    if (currentModel && modelFound) {
                        $('#model option[value="' + currentModel + '"]').attr('selected', 'selected');
                    }

                    if (!modelFound) {
                        console.log('model found: ' + modelFound);
                        // No model selected -> choose model
                        $modelSelect.prepend($('<option></option>').attr('value', '').text('Choose model').prop('selected', true));
                        // Update select field to show selected option
                        $modelSelect.find('option:first').prop('selected', true);
                    }

                }

                if (response.success && response.data['chatbot_settings']) {
                    updateFormFieldsFromChatbotSettings(response.data['chatbot_settings']);
                }
            }
        });
    }

    function updateFormFieldsFromChatbotSettings(chatbotSettings) {
        $('#ai-engine-extension-form').find(':input').each(function () {
            var inputName = $(this).attr('name');
            if (chatbotSettings[inputName] !== undefined) {
                // Checkboxes
                if ($(this).attr('type') === 'checkbox') {
                    if (chatbotSettings[inputName] === '1') {
                        $(this).prop('checked', chatbotSettings[inputName]);
                    } else {
                        $(this).prop('checked', false);
                    }
                } else {
                    $(this).val(chatbotSettings[inputName]);
                }
            }
        });
    }

    waitForChatbot();
});
