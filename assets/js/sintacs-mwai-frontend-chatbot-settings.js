jQuery(document).ready(function ($) {
    function waitForChatbot() {
        var checkExist = setInterval(function () {
            var $chatElement = $('.mwai-chatbot-container .mwai-chat');
            if ($chatElement.length && $chatElement.attr('id')) {
                console.log("Chatbot-Element gefunden.");
                clearInterval(checkExist);

                // Extrahieren der Chatbot-ID aus der ID des Child-Elements
                var chatElementId = $chatElement.attr('id');
                chatbotId = chatElementId.replace('mwai-chatbot-', '');
                $('#botId-info').text(chatbotId);
                console.log("Chatbot ID:", chatbotId);

                // Der Chatbot ist geladen, binden Sie hier das Formular-Submit-Event
                bindFormSubmitEvent();

                // Modelle basierend auf der Chatbot-ID aktualisieren
                updateModelsDropdown();
            } else {
                console.log("Kein Chatbot-Element auf der Seite gefunden.");
                // Überprüfen, ob bereits ein Versuch unternommen wurde, den Chatbot zu finden
                if ($('#ai-engine-extension-form').length) {
                    // Formular ausblenden und durch einen unsichtbaren Kommentar ersetzen
                    $('#ai-engine-extension-form').replaceWith('No chatbot found on the current page.');
                    clearInterval(checkExist);
                }
            }
        }, 100); // Überprüfen Sie alle 100ms
    }

    function toggleFormElements(disabled) {
        $('#ai-engine-extension-form').find('button[type="submit"], :input').prop('disabled', disabled);
    }

    function bindFormSubmitEvent() {
        $('#ai-engine-extension-form').submit(function (e) {
            e.preventDefault();

            // Serialisieren der Formulardaten, bevor die Felder deaktiviert werden
            var formData = $(this).serialize();
            console.log(formData);
            // Deaktivieren des Senden-Buttons und Sperren der Formularfelder
            toggleFormElements(true);

            $.ajax({
                type: "POST",
                url: aiEngineExtensionAjax.ajaxurl,
                data: {
                    action: 'save_ai_engine_parameters',
                    chatbotId: chatbotId, // Senden der Chatbot-ID mit der Anfrage
                    formData: formData
                },
                success: function (response) {
                    // Display response message as alert if exist
                    if (response.data.message) {
                        // Add Success border to the form
                        $('#ai-engine-extension-form-wrapper').addClass('border border-success bg bg-success');
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
                    // Fehlerbehandlung...
                    alert('Ein Fehler ist aufgetreten.');
                },
                complete: function () {
                    // Freigeben des Senden-Buttons und der Formularfelder unabhängig vom Erfolg oder Fehler
                    toggleFormElements(false);
                    // Erfolgshinweis nach einigen Sekunden ausblenden
                    setTimeout(function () {
                        $('#form-success-message').fadeOut();
                        $('#form-success-message').text('');
                    }, 5000); // Hinweis nach 5 Sekunden ausblenden
                }
            });
        });
    }

    function updateModelsDropdown() {
        $.ajax({
            type: "POST",
            url: aiEngineExtensionAjax.ajaxurl, // Stellen Sie sicher, dass dies zuvor korrekt definiert wurde
            data: {
                action: 'get_available_models',
                chatbotId: chatbotId // Senden der Chatbot-ID mit der Anfrage
            },
            success: function (response) {
                if (response.success && response.data['models']) {
                    var models = response.data['models'];
                    var $modelSelect = $('#model');
                    $modelSelect.empty(); // Bestehende Optionen löschen

                    // Modelle zum Dropdown hinzufügen
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
                    } else {
                        // No model selected -> choose model
                        $modelSelect.prepend($('<option></option>').attr('value', '').text('Choose model').prop('selected', true));
                        // Update select field to show selected option
                        $modelSelect.find('option:first').prop('selected', true);
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
