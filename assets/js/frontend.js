jQuery(document).ready(function($) {
    
    // Modal functionality
    const modal = $('#rb-booking-modal');
    const form = $('#rb-booking-form');
    const availableTablesDiv = $('#rb-available-tables');
    const tablesListDiv = $('#rb-tables-list');
    const checkAvailabilityBtn = $('#rb-check-availability');
    const submitBtn = form.find('button[type="submit"]');
    
    // Open modal
    $(document).on('click', '[data-toggle="rb-modal"]', function() {
        modal.fadeIn(300);
        $('body').addClass('rb-modal-open');
    });
    
    // Close modal
    $(document).on('click', '.rb-close, .rb-modal', function(e) {
        if (e.target === this) {
            modal.fadeOut(300);
            $('body').removeClass('rb-modal-open');
            resetForm();
        }
    });
    
    // Prevent modal close when clicking inside modal content
    $(document).on('click', '.rb-modal-content', function(e) {
        e.stopPropagation();
    });
    
    // ESC key to close modal
    $(document).keydown(function(e) {
        if (e.keyCode === 27 && modal.is(':visible')) {
            modal.fadeOut(300);
            $('body').removeClass('rb-modal-open');
            resetForm();
        }
    });
    
    // Check availability
    checkAvailabilityBtn.on('click', function() {
        const date = $('#booking_date').val();
        const time = $('#booking_time').val();
        const guestCount = $('#guest_count').val();
        
        if (!date || !time || !guestCount) {
            showMessage('Vui lòng chọn đầy đủ ngày, giờ và số lượng khách', 'error');
            return;
        }
        
        // Validate date is not in the past
        const today = new Date();
        const selectedDate = new Date(date);
        today.setHours(0, 0, 0, 0);
        
        if (selectedDate < today) {
            showMessage('Không thể đặt bàn cho ngày đã qua', 'error');
            return;
        }
        
        showLoading();
        
        $.ajax({
            url: rb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'rb_check_availability',
                nonce: rb_ajax.nonce,
                date: date,
                time: time,
                guest_count: guestCount
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    tablesListDiv.html(response.data.tables_html);
                    availableTablesDiv.show();
                    submitBtn.prop('disabled', false);
                    showMessage(response.data.message, 'success');
                } else {
                    availableTablesDiv.hide();
                    submitBtn.prop('disabled', true);
                    showMessage(response.data, 'error');
                }
            },
            error: function() {
                hideLoading();
                showMessage('Có lỗi xảy ra khi kiểm tra bàn trống', 'error');
            }
        });
    });
    
    // Form submission
    form.on('submit', function(e) {
        e.preventDefault();
        
        // Validate required fields
        const requiredFields = ['customer_name', 'customer_phone', 'customer_email', 'guest_count', 'booking_date', 'booking_time'];
        let isValid = true;
        
        requiredFields.forEach(function(fieldName) {
            const field = $(`#${fieldName}`);
            if (!field.val().trim()) {
                field.addClass('error');
                isValid = false;
            } else {
                field.removeClass('error');
            }
        });
        
        if (!isValid) {
            showMessage('Vui lòng điền đầy đủ thông tin bắt buộc', 'error');
            return;
        }
        
        // Validate email
        const email = $('#customer_email').val();
        if (!isValidEmail(email)) {
            showMessage('Email không hợp lệ', 'error');
            return;
        }
        
        // Check if table is selected
        const selectedTable = $('input[name="selected_table"]:checked');
        if (selectedTable.length === 0) {
            showMessage('Vui lòng chọn bàn', 'error');
            return;
        }
        
        showLoading();
        
        const formData = {
            action: 'rb_create_booking',
            nonce: rb_ajax.nonce,
            customer_name: $('#customer_name').val(),
            customer_phone: $('#customer_phone').val(),
            customer_email: $('#customer_email').val(),
            guest_count: $('#guest_count').val(),
            booking_date: $('#booking_date').val(),
            booking_time: $('#booking_time').val(),
            special_requests: $('#special_requests').val(),
            selected_table: selectedTable.val()
        };
        
        $.ajax({
            url: rb_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    modal.fadeOut(300);
                    $('body').removeClass('rb-modal-open');
                    resetForm();
                } else {
                    showMessage(response.data, 'error');
                }
            },
            error: function() {
                hideLoading();
                showMessage('Có lỗi xảy ra khi đặt bàn', 'error');
            }
        });
    });
    
    // Input change handlers
    $('#booking_date, #booking_time, #guest_count').on('change', function() {
        availableTablesDiv.hide();
        submitBtn.prop('disabled', true);
        $('input[name="selected_table"]').prop('checked', false);
    });
    
    // Phone number formatting
    $('#customer_phone').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        if (value.length > 10) {
            value = value.substring(0, 10);
        }
        $(this).val(value);
    });
    
    // Set minimum date to today
    $('#booking_date').attr('min', new Date().toISOString().split('T')[0]);
    
    // Utility functions
    function showLoading() {
        $('#rb-loading').fadeIn(200);
    }
    
    function hideLoading() {
        $('#rb-loading').fadeOut(200);
    }
    
    function showMessage(message, type = 'success') {
        const messageDiv = $('#rb-message');
        const messageText = messageDiv.find('.rb-message-text');
        
        messageDiv.removeClass('error success').addClass(type);
        messageText.text(message);
        messageDiv.fadeIn(300);
        
        // Auto hide after 5 seconds
        setTimeout(function() {
            messageDiv.fadeOut(300);
        }, 5000);
    }
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function resetForm() {
        form[0].reset();
        availableTablesDiv.hide();
        tablesListDiv.empty();
        submitBtn.prop('disabled', true);
        $('.error').removeClass('error');
    }
    
    // Close message
    $(document).on('click', '.rb-message-close', function() {
        $('#rb-message').fadeOut(300);
    });
    
    // Table selection
    $(document).on('change', 'input[name="selected_table"]', function() {
        if ($(this).is(':checked')) {
            submitBtn.prop('disabled', false);
        }
    });
    
    // Prevent form submission on Enter in input fields
    form.find('input').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            
            if ($(this).is('#booking_date, #booking_time, #guest_count')) {
                checkAvailabilityBtn.trigger('click');
            }
        }
    });
    
    // Mobile responsive adjustments
    function handleMobileView() {
        if ($(window).width() <= 768) {
            $('.rb-form-row').addClass('mobile');
        } else {
            $('.rb-form-row').removeClass('mobile');
        }
    }
    
    // Initialize and handle resize
    handleMobileView();
    $(window).on('resize', handleMobileView);
    
    // Prevent body scroll when modal is open
    $('body').on('mousewheel DOMMouseScroll', function(e) {
        if ($('body').hasClass('rb-modal-open')) {
            e.preventDefault();
        }
    });
    
    // Touch events for mobile
    let touchStartY = 0;
    
    modal.on('touchstart', function(e) {
        touchStartY = e.originalEvent.touches[0].clientY;
    });
    
    modal.on('touchmove', function(e) {
        const touchY = e.originalEvent.touches[0].clientY;
        const touchDiff = touchStartY - touchY;
        
        if (touchDiff > 100) {
            // Swipe up - could add functionality here
        } else if (touchDiff < -100) {
            // Swipe down - could close modal
        }
    });
});

// Add CSS for body when modal is open
jQuery(document).ready(function($) {
    $('<style>')
        .text('.rb-modal-open { overflow: hidden; } .rb-modal-open .rb-modal-content { overflow-y: auto; }')
        .appendTo('head');
});
