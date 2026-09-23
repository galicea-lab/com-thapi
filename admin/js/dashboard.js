// admin/js/dashboard.js
/**
 * ThApi Dashboard JavaScript
 */
(function($) {
    'use strict';
    
    window.ThApiDashboard = {
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            $('.thapi-refresh').on('click', this.refreshData.bind(this));
            $('.thapi-export').on('click', this.exportData.bind(this));
        },
        
        refreshData: function(e) {
            e.preventDefault();
            
            $.ajax({
                url: 'index.php?option=com_thapi&task=dashboard.getStats&format=json',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Joomla.renderMessages({'message': ['Data refreshed successfully']});
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    }
                }
            });
        },
        
        exportData: function(e) {
            e.preventDefault();
            alert('Export functionality would go here');
        }
    };
    
    $(document).ready(function() {
        ThApiDashboard.init();
    });
    
})(jQuery);