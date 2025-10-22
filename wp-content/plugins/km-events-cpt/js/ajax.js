jQuery(document).ready(function($) {

    // 🔹 Event Filter
    $('#event-filter').on('change', function() {
        let filterVal = $(this).val();

        $.ajax({
            url: ajaxSearch.ajax_url,
            type: 'POST',
            data: {
                action: 'filter_events',
                security: ajaxSearch.nonce,
                filter: filterVal
            },
            beforeSend: function() {
                $('#event-results').html('<p>Loading...</p>');
            },
            success: function(response) {
                $('#event-results').html(response);
            }
        });
    });

    // 🔹 Register Interest
    $(document).on('click', '.register-interest', function() {
        let eventID = $(this).data('event');

        $.ajax({
            url: ajaxSearch.ajax_url,
            type: 'POST',
            data: {
                action: 'register_event_interest',
                security: ajaxSearch.nonce,
                event_id: eventID
            },
            beforeSend: function() {
                $('#register-response').html('Processing...');
            },
            success: function(response) {
                if (response.success) {
                    $('#register-response').html('<p>' + response.data + '</p>');
                    $('.register-interest').remove();
                } else {
                    $('#register-response').html('<p>' + response.data + '</p>');
                }
            }
        });
    });

});
