// Dashboard JavaScript

$(document).ready(function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
});

// Global AJAX setup
$.ajaxSetup({
    headers: {
        'X-Requested-With': 'XMLHttpRequest'
    }
});

// Format date for display
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString(undefined, options);
}

// Format time for display
function formatTime(timeString) {
    const options = { hour: '2-digit', minute: '2-digit' };
    return new Date('2000-01-01T' + timeString).toLocaleTimeString(undefined, options);
}

// Show loading spinner
function showLoading(selector) {
    $(selector).html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
}

// Show error message
function showError(selector, message) {
    $(selector).html('<div class="alert alert-danger">' + message + '</div>');
}

// Confirmation dialog
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Toast notification
function showToast(message, type = 'success') {
    const toastHtml = `
        <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    $('#toastContainer').append(toastHtml);
    const toastElement = $('#toastContainer .toast').last();
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
    
    // Remove toast after it's hidden
    toastElement.on('hidden.bs.toast', function() {
        $(this).remove();
    });
}

// Add toast container to body if not exists
if (!$('#toastContainer').length) {
    $('body').append('<div id="toastContainer" class="position-fixed bottom-0 end-0 p-3" style="z-index: 11"></div>');
}

// Search functionality
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

// Form validation
function validateForm(formId) {
    let isValid = true;
    $(formId + ' [required]').each(function() {
        if (!$(this).val()) {
            $(this).addClass('is-invalid');
            isValid = false;
        } else {
            $(this).removeClass('is-invalid');
        }
    });
    return isValid;
}

// AJAX form submission
$(document).on('submit', '.ajax-form', function(e) {
    e.preventDefault();
    
    if (!validateForm(this)) {
        return false;
    }
    
    const form = $(this);
    const url = form.attr('action');
    const method = form.attr('method') || 'POST';
    const data = form.serialize();
    
    $.ajax({
        url: url,
        type: method,
        data: data,
        dataType: 'json',
        beforeSend: function() {
            form.find('button[type="submit"]').prop('disabled', true);
        },
        success: function(response) {
            if (response.success) {
                showToast(response.message || 'Operation completed successfully');
                if (response.redirect) {
                    window.location.href = response.redirect;
                }
            } else {
                showToast(response.message || 'An error occurred', 'danger');
            }
        },
        error: function(xhr, status, error) {
            showToast('An error occurred: ' + error, 'danger');
        },
        complete: function() {
            form.find('button[type="submit"]').prop('disabled', false);
        }
    });
});

// Print function
function printElement(elementId) {
    const printContents = document.getElementById(elementId).innerHTML;
    const originalContents = document.body.innerHTML;
    
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
    window.location.reload();
}

// Export to CSV
function exportToCSV(data, filename) {
    const csv = convertToCSV(data);
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    window.URL.revokeObjectURL(url);
}

function convertToCSV(objArray) {
    const array = typeof objArray !== 'object' ? JSON.parse(objArray) : objArray;
    let str = '';
    
    for (let i = 0; i < array.length; i++) {
        let line = '';
        for (let index in array[i]) {
            if (line !== '') line += ',';
            line += array[i][index];
        }
        str += line + '\r\n';
    }
    return str;
}

// Date picker initialization
function initDatePickers() {
    $('.datepicker').each(function() {
        $(this).attr('type', 'date');
    });
}

// Time picker initialization
function initTimePickers() {
    $('.timepicker').each(function() {
        $(this).attr('type', 'time');
    });
}

// Initialize on document ready
$(document).ready(function() {
    initDatePickers();
    initTimePickers();
});