jQuery(document).ready(function ($) {
    chatbotId = $('#sintacs-ai-engine-extension-form input[name="botId"]').val();

    function waitForChatbot() {
        var checkExist = setInterval(function () {
            chatbotId = $('#sintacs-ai-engine-extension-form input[name="botId"]').val();
            var $chatElement = $('.mwai-chatbot-container .mwai-chat');
            if ($chatElement.length && $chatElement.attr('id') || chatbotId) {
                console.log("Chatbot element found or chatbot ID set.");
                clearInterval(checkExist);

                // Extracting the Chatbot ID from the ID of the child element if not already set
                if (!chatbotId) {
                    var chatElementId = $chatElement.attr('id');
                    chatbotId = chatElementId.replace('mwai-chatbot-', '');
                    $('#botId-info').text(chatbotId);
                    console.log('Chatbot_Id extracted from the Chatbot Element: ' + chatbotId);
                }

                console.log("Chatbot ID:", chatbotId);

                // Check if the form does not exist
                if (!$('#sintacs-ai-engine-extension-form input:not([type="hidden"])').length) {
                    $('.sintacs-btn-wrapper').hide();
                    return;
                }

                bindFormSubmitEvent();

                // Update models based on the Chatbot ID
                updateModelsDropdown();

                // Event listener for the textarea focus event
                $('#instructions').on('focus', function () {
                    adjustTextareaHeight(this);
                });

                // Optionally, adjust the height on input as well
                $('#instructions').on('input', function () {
                    adjustTextareaHeight(this);
                });

            } else {
                console.log("No chatbot element found on the page.");
                // Check if an attempt has already been made to find the chatbot
                if ($('#sintacs-ai-engine-extension-form').length) {
                    // Hide form and replace it with an invisible comment
                    $('#sintacs-ai-engine-extension-form').replaceWith('No chatbot found on the current page.');
                    clearInterval(checkExist);
                }
            }
        }, 100); // Check every 100ms
    }

    function toggleFormElements(disabled) {
        $('#sintacs-ai-engine-extension-form').find('button[type="submit"], :input').prop('disabled', disabled);
    }

    function bindFormSubmitEvent() {
        $('#sintacs-ai-engine-extension-form').submit(function (e) {
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
                        $('#sintacs-ai-engine-extension-form-wrapper').addClass('border border-success');
                        $('#form-success-message').text(response.data.message);
                        $('#form-success-message').show();
                        // Reload the page to take the changed settings into effect
                        location.reload();
                    } else {
                        $('#sintacs-ai-engine-extension-form-wrapper').addClass('border border-danger');
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
        console.log('Updating models dropdown... Chatbot ID: ' + chatbotId);
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
                    // if chatbot_settings name is empty, get it from the default settings
                    var chatbotName = !response.data['chatbot_settings']['name'] ? response.data['default_settings']['name'] : response.data['chatbot_settings']['name'];
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
                        // set the first option as selected
                        $('#model option:first').prop('selected', true);

                        /*
                        // No model selected -> choose model
                        $modelSelect.prepend($('<option></option>').attr('value', '').text('Choose model').prop('selected', true));
                        // Update select field to show selected option
                        $modelSelect.find('option:first').prop('selected', true);
                        */
                    }
                }

                if (response.success && response.data['default_settings']) {
                    console.log('Chatbot settings received. Updating form fields...');
                    updateFormFieldsFromChatbotSettings(response.data['chatbot_settings'], response.data['default_settings']);
                }
            }
        });
    }

    function updateFormFieldsFromChatbotSettings(chatbotSettings, defaultSettings) {

        // if the chatbot settings are empty, set the default settings
        // needs better checking, if it is empty or not
        if (Object.keys(chatbotSettings).length === 0) {
            console.log('Chatbot settings are empty. Using default settings.');
            chatbotSettings = defaultSettings;
        }

        $('#sintacs-ai-engine-extension-form').find(':input').each(function () {
            let inputName = $(this).attr('name');
            let userValue = chatbotSettings[inputName];
            let defaultValue = defaultSettings[inputName];

            if (userValue !== undefined && userValue !== false) {
                if ($(this).attr('type') === 'checkbox') {
                    $(this).prop('checked', userValue == '1' || userValue === true);
                } else {
                    var textArea = document.createElement('textarea');
                    textArea.innerHTML = userValue;
                    userValue = textArea.value;

                    var isNumeric = /^-?\d*\.?\d+$/.test(userValue);
                    if (isNumeric) {
                        userValue = parseFloat(userValue);
                    } else {
                        userValue = userValue.replace(/\u00A0/g, ' ');
                    }
                    $(this).val(userValue);
                }
                if (userValue != defaultValue) {
                    $(this).val(userValue);
                    $(this).siblings('label').find('span').text('🔵').attr('title', 'Default: ' + defaultValue);
                } else {
                    $(this).siblings('label').find('span').text('');
                }
            } else {
                if (defaultValue !== undefined) {
                    if ($(this).attr('type') === 'checkbox') {
                        $(this).prop('checked', defaultValue == '1' || defaultValue === true);
                    } else {
                        if (typeof defaultValue === 'string') {
                            defaultValue = defaultValue.replace(/&nbsp;/g, ' ');
                        }
                        $(this).val(defaultValue);
                    }
                }
                $(this).siblings('label').find('span').text('');
            }

            // if name is Default and value is default, the input field can not be changed
            if ($(this).attr('name') === 'name' && ($(this).val() === 'default') || ($(this).val() === 'Default')) {
                console.log('name is default, can not be changed');
                $(this).prop('readonly', true);
                $(this).val('Default');
            }

            // if field name is envId and value is empty, set the first option to selected
            if ($(this).attr('name') === 'envId' && $(this).val() === null) {
                console.log('envId is empty, set the first option to selected');
                $('#envId option:first').prop('selected', true);

            }

            if(defaultSettings['envId'] === null || defaultSettings['envId'] === '' || defaultSettings['envId'] === undefined) {

                $('#envId').siblings('label').find('span').text('');
            }

        });

        // Display temperature value
        displayTemperatureValue();
    }

    function encodeTrailingSpaces(value) {
        return value.replace(/ $/, '&nbsp;');
    }

    $('#save-to-original').click(function () {
        var formData = $('#sintacs-ai-engine-extension-form').serializeArray();
        formData.forEach(function (field) {
            field.value = encodeTrailingSpaces(field.value);
        });

        $.ajax({
            type: "POST",
            url: aiEngineExtensionAjax.ajaxurl,
            data: {
                action: 'save_to_original',
                chatbotId: chatbotId,
                formData: $.param(formData)
            },
            success: function (response) {
                alert(response.data.message);
            }
        });
    });

    $('#reset-to-default').click(function () {
        $.ajax({
            type: "POST",
            url: aiEngineExtensionAjax.ajaxurl,
            data: {
                action: 'get_default_settings',
                chatbotId: $('#sintacs-ai-engine-extension-form input[name="botId"]').val()
            },
            success: function (response) {
                if (response.success) {
                    updateFormFieldsFromChatbotSettings(response.data.default_settings, response.data.user_settings);
                    alert('Default settings loaded but not saved.');
                } else {
                    alert('Failed to load default settings.');
                }
            }
        });
    });

    // Ensure the temperature value is displayed correctly on load
    function displayTemperatureValue() {
        var temperatureInput = $('#temperature');
        if (temperatureInput.length) {
            var temperatureValue = $('#temperature_value');
            var inputValue = temperatureInput.val(); // Get the current value of the input
            temperatureValue.text(inputValue); // Set the text of temperatureValue to the input's value
        }
    }

    // Function to adjust the height of the textarea on focus
    function adjustTextareaHeight(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
    }

    waitForChatbot();
    displayTemperatureValue();
});
