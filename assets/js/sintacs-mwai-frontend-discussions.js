var currentPage = 1;

jQuery(document).ready(function() {
    populateSelectFields();
});

function populateSelectFields() {
    jQuery.ajax({
        url: aiEngineExtensionAjax.ajaxurl,
        type: 'POST',
        data: {
            action: 'sintacs_mwai_get_filter_data'
        },
        success: function(response) {
            if (response.success) {
                var users = response.data.users;
                var botIds = response.data.botIds;
                var chatIds = response.data.chatIds;
                var tags = response.data.tags;

                var userSelect = jQuery('#filter-user');
                var botIdSelect = jQuery('#filter-bot-id');
                var chatIdSelect = jQuery('#filter-chat-id');
                var tagsSelect = jQuery('#filter-tags');

                userSelect.empty();
                botIdSelect.empty();
                chatIdSelect.empty();
                tagsSelect.empty();

                users.forEach(function(user) {
                    userSelect.append(new Option(user, user));
                });

                botIds.forEach(function(botId) {
                    botIdSelect.append(new Option(botId, botId));
                });

                chatIds.forEach(function(chatId) {
                    chatIdSelect.append(new Option(chatId, chatId));
                });

                tags.forEach(function(tag) {
                    tagsSelect.append(new Option(tag, tag));
                });
            }
        }
    });
}

function toggleFilters() {
    var filterOptions = jQuery('#filter-options');
    var toggleButton = jQuery('#toggle-filters-button');
    if (filterOptions.is(':visible')) {
        filterOptions.hide();
        toggleButton.text('Show Filters');
    } else {
        filterOptions.show();
        toggleButton.text('Hide Filters');
    }
}

function applyFilters() {
    var users = jQuery('#filter-user').val();
    var botIds = jQuery('#filter-bot-id').val();
    var chatIds = jQuery('#filter-chat-id').val();
    var startDate = jQuery('#filter-time-frame-start').val();
    var endDate = jQuery('#filter-time-frame-end').val();
    var tags = jQuery('#filter-tags').val();

    searchChats(1, {
        users: users,
        botIds: botIds,
        chatIds: chatIds,
        startDate: startDate,
        endDate: endDate,
        tags: tags
    });
}

function searchChats(page = 1, filters = {}) {
    var searchText = document.getElementById('chat-search-input').value;
    jQuery.ajax({
        url: aiEngineExtensionAjax.ajaxurl,
        type: 'POST',
        headers: {
            'Authorization': 'Bearer ' + aiEngineExtensionAjax.access_token
        },
        data: {
            action: 'sintacs_mwai_search_chats',
            search_text: searchText,
            page: page,
            filters: filters
        },
        xhrFields: {
            withCredentials: true
        },
        success: function(response) {
            document.getElementById('chat-list').innerHTML = response.data.chats;
            currentPage = response.data.page;
            totalPages = response.data.total_pages;
            totalChats = response.data.total_chats;
            totalMessages = response.data.total_messages;
            document.getElementById('prev-page').disabled = !response.data.has_prev;
            document.getElementById('next-page').disabled = !response.data.has_next;
            document.getElementById('pagination-info').innerText = `Page ${currentPage} of ${totalPages}`;
            document.getElementById('total-info').innerText = `Total Chats: ${totalChats}, Total Messages: ${totalMessages}`;

            // scroll only if it is a filter or search
            if (filters.users || filters.botIds || filters.chatIds || filters.startDate || filters.endDate || searchText) {
                scrollToContainer();
            }
        }
    });
}

function scrollToContainer() {
    var container = document.getElementById('chat-list');
    if (container) {
        container.scrollIntoView({ behavior: 'smooth' });
    }
}

function changePage(offset) {
    searchChats(currentPage + offset);
}

function deleteChat(chatId) {
    if (confirm('Are you sure you want to delete this chat?')) {
        jQuery.ajax({
            url: aiEngineExtensionAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'sintacs_mwai_delete_chat',
                chat_id: chatId
            },
            success: function(response) {
                searchChats(currentPage); // Refresh the list after deletion
            }
        });
    }
}

function favoriteChat(chatId) {
    jQuery.ajax({
        url: aiEngineExtensionAjax.ajaxurl,
        type: 'POST',
        data: {
            action: 'sintacs_mwai_favorite_chat',
            chat_id: chatId
        },
        success: function(response) {
            searchChats(currentPage); // Refresh the list after marking as favorite
        }
    });
}

function addTag(chatId, tags) {
    var tagArray = tags.split(/[\s,]+/).filter(Boolean); // Split by space or comma and remove empty values
    jQuery.ajax({
        url: aiEngineExtensionAjax.ajaxurl,
        type: 'POST',
        data: {
            action: 'sintacs_mwai_add_tag',
            chat_id: chatId,
            tags: tagArray.join(',')
        },
        success: function(response) {
            searchChats(currentPage); // Refresh the list after adding tag
        }
    });
}

// Initial load
searchChats();
