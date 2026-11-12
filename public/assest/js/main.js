$(document).ready(function() {
    // Navigation Drawer
    $('#menuToggle').click(function() {
        $('#navigationDrawer').addClass('open');
        $('#drawerOverlay').addClass('active');
    });
    
    $('#drawerOverlay').click(function() {
        $('#navigationDrawer').removeClass('open');
        $('#drawerOverlay').removeClass('active');
    });
    
    // Smooth page transitions
    $('a:not([target="_blank"])').click(function(e) {
        if ($(this).attr('href').startsWith('?')) {
            e.preventDefault();
            $('.main-content').fadeOut(300, function() {
                window.location.href = $(this).attr('href');
            }.bind(this));
        }
    });
    
    // Initialize tooltips
    $('[data-tooltip]').each(function() {
        $(this).hover(function() {
            const text = $(this).data('tooltip');
            $(this).append(`<div class="tooltip">${text}</div>`);
        }, function() {
            $(this).find('.tooltip').remove();
        });
    });
    
    // Loading indicator for AJAX requests
    $(document).ajaxStart(function() {
        $('#loadingIndicator').show();
    }).ajaxStop(function() {
        $('#loadingIndicator').hide();
    });
    
    // Add loading indicator to body
    $('body').append('<div id="loadingIndicator" class="loading-spinner" style="display: none;"><i class="fas fa-circle-notch fa-spin"></i></div>');
});

// Toast notification function
function showToast(message, type = 'success') {
    const toast = $(`<div class="toast toast-${type}">${message}</div>`);
    $('body').append(toast);
    
    setTimeout(() => {
        toast.fadeOut(300, function() {
            $(this).remove();
        });
    }, 3000);
}

// Format code function
function formatCode(code, language) {
    if (language === 'cpp' || language === 'c') {
        // C++ formatting logic
        return code;
    } else if (language === 'python') {
        // Python formatting logic
        return code;
    }
    return code;
}

// Debounce function for performance
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}