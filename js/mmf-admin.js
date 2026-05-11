/* Complete Maintenance Mode - Admin JS */
(function($) {
    $(document).ready(function() {
        // End date toggle
        $('#mmf-end-date-enabled').on('change', function() {
            $('#mmf-end-date').prop('disabled', !this.checked);
        });
        // GA toggle
        $('#mmf-ga-enabled').on('change', function() {
            $('#mmf-ga-id').prop('disabled', !this.checked);
        });
    });
})(jQuery);
