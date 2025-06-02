/**
 * Multi-Merchant Payment Orchestrator - Frontend JavaScript
 * Handles payment form enhancements and merchant dashboard functionality
 * 
 * @package Multi-Merchant Payment Orchestrator
 * @version 1.0.0
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize all frontend functionality
    MMPO_Frontend.init();
});

/**
 * Main frontend object
 */
var MMPO_Frontend = {
    
    /**
     * Initialize frontend functionality
     */
    init: function() {
        this.initPaymentForm();
        this.initMerchantDashboard();
        this.initGeneralEnhancements();
    },
    
    /**
     * Initialize payment form enhancements
     */
    initPaymentForm: function() {
        var $ = jQuery;
        
        // Only run on checkout pages with our payment form
        if (!$('.mmpo-payment-form').length && !$('.wc-credit-card-form').length) {
            return;
        }
        
        // Format card number with spaces
        $(document).on('input', '.wc-credit-card-form-card-number', function() {
            var value = $(this).val().replace(/\s/g, '').replace(/[^0-9]/gi, '');
            var formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            
            if (formattedValue !== $(this).val()) {
                $(this).val(formattedValue);
            }
            
            // Detect and display card type
            var cardType = MMPO_Frontend.detectCardType(value);
            $(this).siblings('.card-type').text(cardType.toUpperCase());
            
            // Add card type class for styling
            $(this).removeClass('visa mastercard amex discover diners jcb unknown')
                   .addClass(cardType);
        });
        
        // Format expiry date (MM/YY)
        $(document).on('input', '.wc-credit-card-form-card-expiry', function() {
            var value = $(this).val().replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            $(this).val(value);
            
            // Validate expiry date
            if (value.length === 5) {
                MMPO_Frontend.validateExpiry($(this), value);
            }
        });
        
        // CVC - numbers only
        $(document).on('input', '.wc-credit-card-form-card-cvc', function() {
            var value = $(this).val().replace(/[^0-9]/g, '');
            $(this).val(value);
            
            // Limit CVC length based on card type
            var cardNumber = $('.wc-credit-card-form-card-number').val().replace(/\s/g, '');
            var cardType = MMPO_Frontend.detectCardType(cardNumber);
            var maxLength = (cardType === 'amex') ? 4 : 3;
            
            if (value.length > maxLength) {
                $(this).val(value.substring(0, maxLength));
            }
        });
        
        // Real-time validation
        $(document).on('blur', '.wc-credit-card-form-card-number', function() {
            MMPO_Frontend.validateCardNumber($(this));
        });
        
        // Form submission validation
        $(document).on('submit', 'form.woocommerce-checkout', function() {
            if ($('input[name="payment_method"]:checked').val() === 'mmpo_dynamic_nmi') {
                return MMPO_Frontend.validatePaymentForm();
            }
        });
    },
    
    /**
     * Initialize merchant dashboard functionality
     */
    initMerchantDashboard: function() {
        var $ = jQuery;
        
        if (!$('#mmpo-merchant-dashboard').length) {
            return;
        }
        
        // Credentials form submission
        $('#mmpo-credentials-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var originalText = $submitBtn.text();
            
            // Disable form during submission
            $submitBtn.prop('disabled', true).text('Saving...');
            
            var formData = {
                action: 'mmpo_save_credentials',
                nonce: mmpo_ajax.nonce,
                nmi_username: $('#nmi_username').val(),
                nmi_password: $('#nmi_password').val(),
                nmi_api_key: $('#nmi_api_key').val()
            };
            
            $.post(mmpo_ajax.ajax_url, formData)
                .done(function(response) {
                    if (response.success) {
                        MMPO_Frontend.showMessage('Credentials saved successfully!', 'success');
                    } else {
                        MMPO_Frontend.showMessage('Error: ' + response.data, 'error');
                    }
                })
                .fail(function() {
                    MMPO_Frontend.showMessage('Network error. Please try again.', 'error');
                })
                .always(function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                });
        });
        
        // Test credentials
        $('#test-credentials').on('click', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var originalText = $btn.text();
            
            var credentials = {
                username: $('#nmi_username').val(),
                password: $('#nmi_password').val(),
                api_key: $('#nmi_api_key').val()
            };
            
            if (!credentials.username || !credentials.password) {
                MMPO_Frontend.showMessage('Please enter username and password first.', 'error');
                return;
            }
            
            $btn.prop('disabled', true).text('Testing...');
            
            $.post(mmpo_ajax.ajax_url, {
                action: 'mmpo_test_connection',
                nonce: mmpo_ajax.nonce,
                credentials: credentials
            })
            .done(function(response) {
                if (response.success) {
                    MMPO_Frontend.showMessage('Connection successful!', 'success');
                } else {
                    MMPO_Frontend.showMessage('Connection failed: ' + response.data, 'error');
                }
            })
            .fail(function() {
                MMPO_Frontend.showMessage('Network error during test.', 'error');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        });
        
        // Refresh dashboard data
        $('.mmpo-refresh-data').on('click', function(e) {
            e.preventDefault();
            location.reload();
        });
        
        // Export functionality
        $('.mmpo-export-data').on('click', function(e) {
            e.preventDefault();
            
            if (confirm('Export your sales data as CSV?')) {
                window.location.href = $(this).attr('href');
            }
        });
    },
    
    /**
     * Initialize general enhancements
     */
    initGeneralEnhancements: function() {
        var $ = jQuery;
        
        // Animate statistics counters
        $('.mmpo-stat-value').each(function() {
            var $this = $(this);
            var text = $this.text();
            var number = parseFloat(text.replace(/[^0-9.-]/g, ''));
            
            if (!isNaN(number)) {
                MMPO_Frontend.animateCounter($this, 0, number, 1000);
            }
        });
        
        // Tooltips for help text
        $('.mmpo-help-tip').hover(
            function() {
                var tooltip = $('<div class="mmpo-tooltip">' + $(this).data('tip') + '</div>');
                $('body').append(tooltip);
                
                var pos = $(this).offset();
                tooltip.css({
                    position: 'absolute',
                    top: pos.top - tooltip.outerHeight() - 5,
                    left: pos.left + ($(this).outerWidth() / 2) - (tooltip.outerWidth() / 2)
                }).fadeIn(200);
            },
            function() {
                $('.mmpo-tooltip').fadeOut(200, function() {
                    $(this).remove();
                });
            }
        );
        
        // Responsive table handling
        $('.mmpo-products-table').each(function() {
            if ($(this).width() > $(this).parent().width()) {
                $(this).addClass('mmpo-table-responsive');
            }
        });
    },
    
    /**
     * Detect credit card type from number
     */
    detectCardType: function(number) {
        var patterns = {
            visa: /^4/,
            mastercard: /^5[1-5]/,
            amex: /^3[47]/,
            discover: /^6(?:011|5)/,
            diners: /^3[0689]/,
            jcb: /^(?:2131|1800|35)/
        };
        
        for (var type in patterns) {
            if (patterns[type].test(number)) {
                return type;
            }
        }
        return 'unknown';
    },
    
    /**
     * Validate credit card number using Luhn algorithm
     */
    validateCardNumber: function($input) {
        var number = $input.val().replace(/\s/g, '');
        var isValid = this.luhnCheck(number);
        
        $input.toggleClass('mmpo-invalid', !isValid);
        
        if (!isValid && number.length >= 13) {
            this.showFieldError($input, 'Invalid card number');
        } else {
            this.clearFieldError($input);
        }
        
        return isValid;
    },
    
    /**
     * Validate expiry date
     */
    validateExpiry: function($input, value) {
        var parts = value.split('/');
        var month = parseInt(parts[0], 10);
        var year = parseInt('20' + parts[1], 10);
        var now = new Date();
        var currentYear = now.getFullYear();
        var currentMonth = now.getMonth() + 1;
        
        var isValid = month >= 1 && month <= 12 && 
                     (year > currentYear || (year === currentYear && month >= currentMonth));
        
        $input.toggleClass('mmpo-invalid', !isValid);
        
        if (!isValid) {
            this.showFieldError($input, 'Card has expired');
        } else {
            this.clearFieldError($input);
        }
        
        return isValid;
    },
    
    /**
     * Luhn algorithm for card validation
     */
    luhnCheck: function(cardNumber) {
        if (!/^\d+$/.test(cardNumber) || cardNumber.length < 13 || cardNumber.length > 19) {
            return false;
        }
        
        var sum = 0;
        var alternate = false;
        
        for (var i = cardNumber.length - 1; i >= 0; i--) {
            var n = parseInt(cardNumber.charAt(i), 10);
            
            if (alternate) {
                n *= 2;
                if (n > 9) {
                    n = (n % 10) + 1;
                }
            }
            
            sum += n;
            alternate = !alternate;
        }
        
        return (sum % 10) === 0;
    },
    
    /**
     * Validate entire payment form
     */
    validatePaymentForm: function() {
        var $ = jQuery;
        var isValid = true;
        
        // Validate card number
        var $cardNumber = $('.wc-credit-card-form-card-number');
        if (!this.validateCardNumber($cardNumber)) {
            isValid = false;
        }
        
        // Validate expiry
        var $expiry = $('.wc-credit-card-form-card-expiry');
        var expiryValue = $expiry.val();
        if (expiryValue.length === 5) {
            if (!this.validateExpiry($expiry, expiryValue)) {
                isValid = false;
            }
        } else {
            this.showFieldError($expiry, 'Please enter expiry date');
            isValid = false;
        }
        
        // Validate CVC
        var $cvc = $('.wc-credit-card-form-card-cvc');
        var cvcValue = $cvc.val();
        if (cvcValue.length < 3) {
            this.showFieldError($cvc, 'Please enter security code');
            isValid = false;
        } else {
            this.clearFieldError($cvc);
        }
        
        return isValid;
    },
    
    /**
     * Show field error
     */
    showFieldError: function($field, message) {
        this.clearFieldError($field);
        
        var $error = $('<div class="mmpo-field-error">' + message + '</div>');
        $field.after($error);
        $field.addClass('mmpo-invalid');
    },
    
    /**
     * Clear field error
     */
    clearFieldError: function($field) {
        $field.removeClass('mmpo-invalid');
        $field.siblings('.mmpo-field-error').remove();
    },
    
    /**
     * Show message in dashboard
     */
    showMessage: function(message, type) {
        var $ = jQuery;
        var messageDiv = $('#mmpo-message');
        
        if (!messageDiv.length) {
            messageDiv = $('<div id="mmpo-message" class="mmpo-message"></div>');
            $('.mmpo-credentials').append(messageDiv);
        }
        
        messageDiv.removeClass('success error info warning')
                  .addClass(type)
                  .text(message)
                  .fadeIn();
        
        setTimeout(function() {
            messageDiv.fadeOut();
        }, 5000);
    },
    
    /**
     * Animate counter
     */
    animateCounter: function($element, start, end, duration) {
        var $ = jQuery;
        var startTime = Date.now();
        var originalText = $element.text();
        var prefix = originalText.replace(/[0-9.,]/g, '');
        
        function updateCounter() {
            var elapsed = Date.now() - startTime;
            var progress = Math.min(elapsed / duration, 1);
            var current = Math.floor(start + (end - start) * progress);
            
            $element.text(prefix + current.toLocaleString());
            
            if (progress < 1) {
                requestAnimationFrame(updateCounter);
            }
        }
        
        if (window.requestAnimationFrame) {
            updateCounter();
        }
    }
};

// Make MMPO_Frontend globally available
window.MMPO_Frontend = MMPO_Frontend;