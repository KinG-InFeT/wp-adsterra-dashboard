jQuery(document).ready(function ($) {

    $('#adsterra_dashboard_widget_filter_month').on('change', function () {

        var filterMonth = $(this).val();

        $.ajax({
            type: "POST",
            url: adsterra_ajax_object.ajax_url,
            data: {
                action: "adsterra_update_month_filter",
                filter_month: filterMonth,
                nonce: adsterra_ajax_object.nonce
            },
            success: function (response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    console.error('Error updating filter:', response.data);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('Error updating filter. Please try again.');
            }
        });

    });

    $('#adsterra_refresh_cache').on('click', function () {
        var $button = $(this);
        var originalHtml = $button.html();

        // Disable button and show loading state
        $button.prop('disabled', true);
        $button.html('<span class="dashicons dashicons-update spin" style="vertical-align: middle;"></span> Refreshing...');

        $.ajax({
            type: "POST",
            url: adsterra_ajax_object.ajax_url,
            data: {
                action: "adsterra_refresh_cache",
                nonce: adsterra_ajax_object.refresh_nonce
            },
            success: function (response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    console.error('Error refreshing cache:', response.data);
                    $button.prop('disabled', false);
                    $button.html(originalHtml);
                    alert('Error refreshing data. Please try again.');
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', error);
                $button.prop('disabled', false);
                $button.html(originalHtml);
                alert('Error refreshing data. Please try again.');
            }
        });
    });
});