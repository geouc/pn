// Payment Processing JavaScript

// Payment initialization
document.addEventListener('DOMContentLoaded', function() {
    const paymentForm = document.getElementById('paymentForm');
    
    if (paymentForm) {
        const payment = new PaymentProcessor(paymentForm);
        payment.init();
    }
});

// Main Payment Processor Class
class PaymentProcessor {
    constructor(form) {
        this.form = form;
        this.paymentMethod = 'card';
        this.cardType = null;
        this.isProcessing = false;
        this.errors = {};
        
        // Stripe or other payment gateway public key
        this.publicKey = this.form.dataset.publicKey || '';
        
        // Initialize payment gateway if available
        if (window.Stripe && this.publicKey) {
            this.stripe = Stripe(this.publicKey);
            this.elements = this.stripe.elements();
        }
    }

    init() {
        this.setupFormElements();
        this.setupEventListeners();
        this.setupPaymentMethods();
        this.initializeCardElements();
        this.loadSavedPaymentMethods();
        this.setupPromoCode();
    }

    setupFormElements() {
        // Cache form elements
        this.submitBtn = this.form.querySelector('.submit-button');
        this.cardNumber = this.form.querySelector('#cardNumber');
        this.cardExpiry = this.form.querySelector('#cardExpiry');
        this.cardCvc = this.form.querySelector('#cardCvc');
        this.cardName = this.form.querySelector('#cardName');
        this.email = this.form.querySelector('#email');
        this.saveCard = this.form.querySelector('#saveCard');
        
        // Billing address fields
        this.billingFields = {
            address: this.form.querySelector('#billingAddress'),
            city: this.form.querySelector('#billingCity'),
            state: this.form.querySelector('#billingState'),
            zip: this.form.querySelector('#billingZip'),
            country: this.form.querySelector('#billingCountry')
        };
    }

    setupEventListeners() {
        // Form submission
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));

        // Card number formatting and validation
        if (this.cardNumber) {
            this.cardNumber.addEventListener('input', (e) => this.handleCardNumberInput(e));
            this.cardNumber.addEventListener('blur', (e) => this.validateCardNumber(e));
        }

        // Expiry date formatting
        if (this.cardExpiry) {
            this.cardExpiry.addEventListener('input', (e) => this.handleExpiryInput(e));
            this.cardExpiry.addEventListener('blur', (e) => this.validateExpiry(e));
        }

        // CVC validation
        if (this.cardCvc) {
            this.cardCvc.addEventListener('input', (e) => this.handleCvcInput(e));
            this.cardCvc.addEventListener('blur', (e) => this.validateCvc(e));
        }

        // Real-time validation for other fields
        const validateFields = [this.cardName, this.email, ...Object.values(this.billingFields)];
        validateFields.forEach(field => {
            if (field) {
                field.addEventListener('blur', () => this.validateField(field));
                field.addEventListener('input', () => this.clearFieldError(field));
            }
        });
    }

    setupPaymentMethods() {
        const methodOptions = this.form.querySelectorAll('.payment-method');
        
        methodOptions.forEach(option => {
            option.addEventListener('click', (e) => {
                // Update selected state
                methodOptions.forEach(opt => opt.classList.remove('selected'));
                option.classList.add('selected');
                
                // Update payment method
                const input = option.querySelector('input[type="radio"]');
                if (input) {
                    input.checked = true;
                    this.paymentMethod = input.value;
                    this.updatePaymentForm();
                }
            });
        });
    }

    initializeCardElements() {
        // If using Stripe Elements
        if (this.stripe && this.elements) {
            const style = {
                base: {
                    fontSize: '16px',
                    color: '#32325d',
                    '::placeholder': {
                        color: '#aab7c4'
                    }
                },
                invalid: {
                    color: '#fa755a',
                    iconColor: '#fa755a'
                }
            };

            // Create card element
            this.cardElement = this.elements.create('card', { style });
            const cardMount = document.getElementById('card-element');
            
            if (cardMount) {
                this.cardElement.mount('#card-element');
                
                // Handle real-time validation errors
                this.cardElement.on('change', (event) => {
                    const displayError = document.getElementById('card-errors');
                    if (event.error) {
                        displayError.textContent = event.error.message;
                    } else {
                        displayError.textContent = '';
                    }
                });
            }
        }
    }

    handleCardNumberInput(e) {
        let value = e.target.value.replace(/\s+/g, '');
        
        // Format card number with spaces
        let formatted = value.match(/.{1,4}/g)?.join(' ') || value;
        e.target.value = formatted;

        // Detect card type
        this.detectCardType(value);

        // Limit length based on card type
        const maxLength = this.cardType === 'amex' ? 17 : 19; // Including spaces
        if (formatted.length > maxLength) {
            e.target.value = formatted.substring(0, maxLength);
        }
    }

    detectCardType(number) {
        const patterns = {
            visa: /^4/,
            mastercard: /^5[1-5]/,
            amex: /^3[47]/,
            discover: /^6(?:011|5)/,
            diners: /^3(?:0[0-5]|[68])/,
            jcb: /^35/
        };

        for (const [type, pattern] of Object.entries(patterns)) {
            if (pattern.test(number)) {
                this.cardType = type;
                this.updateCardIcon(type);
                return;
            }
        }

        this.cardType = null;
        this.updateCardIcon(null);
    }

    updateCardIcon(type) {
        const icons = this.form.querySelectorAll('.card-icons img');
        icons.forEach(icon => {
            if (type && icon.alt.toLowerCase() === type) {
                icon.classList.add('active');
            } else {
                icon.classList.remove('active');
            }
        });
    }

    handleExpiryInput(e) {
        let value = e.target.value.replace(/\D/g, '');
        
        if (value.length >= 2) {
            value = value.substring(0, 2) + '/' + value.substring(2, 4);
        }
        
        e.target.value = value;
    }

    handleCvcInput(e) {
        let value = e.target.value.replace(/\D/g, '');
        const maxLength = this.cardType === 'amex' ? 4 : 3;
        e.target.value = value.substring(0, maxLength);
    }

    validateCardNumber(e) {
        const value = e.target.value.replace(/\s+/g, '');
        
        if (!value) {
            this.setFieldError(e.target, 'Card number is required');
            return false;
        }

        if (!this.isValidCardNumber(value)) {
            this.setFieldError(e.target, 'Invalid card number');
            return false;
        }

        this.clearFieldError(e.target);
        return true;
    }

    isValidCardNumber(number) {
        // Luhn algorithm validation
        let sum = 0;
        let isEven = false;
        
        for (let i = number.length - 1; i >= 0; i--) {
            let digit = parseInt(number.charAt(i), 10);
            
            if (isEven) {
                digit *= 2;
                if (digit > 9) {
                    digit -= 9;
                }
            }
            
            sum += digit;
            isEven = !isEven;
        }
        
        return sum % 10 === 0;
    }

    validateExpiry(e) {
        const value = e.target.value;
        
        if (!value) {
            this.setFieldError(e.target, 'Expiry date is required');
            return false;
        }

        const [month, year] = value.split('/').map(v => parseInt(v, 10));
        const currentDate = new Date();
        const currentYear = currentDate.getFullYear() % 100;
        const currentMonth = currentDate.getMonth() + 1;

        if (!month || month < 1 || month > 12) {
            this.setFieldError(e.target, 'Invalid month');
            return false;
        }

        if (!year || year < currentYear || (year === currentYear && month < currentMonth)) {
            this.setFieldError(e.target, 'Card has expired');
            return false;
        }

        this.clearFieldError(e.target);
        return true;
    }

    validateCvc(e) {
        const value = e.target.value;
        const requiredLength = this.cardType === 'amex' ? 4 : 3;
        
        if (!value) {
            this.setFieldError(e.target, 'CVC is required');
            return false;
        }

        if (value.length !== requiredLength) {
            this.setFieldError(e.target, `CVC must be ${requiredLength} digits`);
            return false;
        }

        this.clearFieldError(e.target);
        return true;
    }

    validateField(field) {
        const value = field.value.trim();
        const fieldName = field.getAttribute('name');
        
        if (field.hasAttribute('required') && !value) {
            this.setFieldError(field, `${this.getFieldLabel(fieldName)} is required`);
            return false;
        }

        // Email validation
        if (field.type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                this.setFieldError(field, 'Invalid email address');
                return false;
            }
        }

        // Zip code validation
        if (fieldName === 'zip' && value) {
            const zipRegex = /^\d{5}(-\d{4})?$/;
            if (!zipRegex.test(value)) {
                this.setFieldError(field, 'Invalid zip code');
                return false;
            }
        }

        this.clearFieldError(field);
        return true;
    }

    setFieldError(field, message) {
        field.classList.add('error');
        
        let errorEl = field.parentElement.querySelector('.error-message');
        if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.className = 'error-message';
            field.parentElement.appendChild(errorEl);
        }
        
        errorEl.textContent = message;
        this.errors[field.name] = message;
    }

    clearFieldError(field) {
        field.classList.remove('error');
        
        const errorEl = field.parentElement.querySelector('.error-message');
        if (errorEl) {
            errorEl.remove();
        }
        
        delete this.errors[field.name];
    }

    getFieldLabel(fieldName) {
        const labels = {
            cardNumber: 'Card number',
            cardExpiry: 'Expiry date',
            cardCvc: 'CVC',
            cardName: 'Cardholder name',
            email: 'Email',
            billingAddress: 'Address',
            billingCity: 'City',
            billingState: 'State',
            billingZip: 'Zip code',
            billingCountry: 'Country'
        };
        
        return labels[fieldName] || fieldName;
    }

    updatePaymentForm() {
        const cardFields = this.form.querySelector('.card-payment-fields');
        const paypalFields = this.form.querySelector('.paypal-payment-fields');
        const cryptoFields = this.form.querySelector('.crypto-payment-fields');
        
        // Hide all payment fields
        [cardFields, paypalFields, cryptoFields].forEach(fields => {
            if (fields) fields.style.display = 'none';
        });

        // Show relevant fields
        switch (this.paymentMethod) {
            case 'card':
                if (cardFields) cardFields.style.display = 'block';
                break;
            case 'paypal':
                if (paypalFields) paypalFields.style.display = 'block';
                this.initializePayPal();
                break;
            case 'crypto':
                if (cryptoFields) cryptoFields.style.display = 'block';
                this.initializeCrypto();
                break;
        }
    }

    async loadSavedPaymentMethods() {
        try {
            const response = await fetch('/api/payment-methods', {
                headers: {
                    'Authorization': `Bearer ${this.getAuthToken()}`
                }
            });

            if (response.ok) {
                const methods = await response.json();
                this.displaySavedMethods(methods);
            }
        } catch (error) {
            console.error('Failed to load saved payment methods:', error);
        }
    }

    displaySavedMethods(methods) {
        const container = document.getElementById('savedMethods');
        if (!container || methods.length === 0) return;

        container.innerHTML = methods.map(method => `
            <div class="saved-method" data-method-id="${method.id}">
                <input type="radio" name="savedMethod" id="method-${method.id}" value="${method.id}">
                <label for="method-${method.id}">
                    <span class="method-type">${method.brand}</span>
                    <span class="method-last4">•••• ${method.last4}</span>
                    <span class="method-expiry">${method.expMonth}/${method.expYear}</span>
                </label>
                <button type="button" class="remove-method" data-method-id="${method.id}">Remove</button>
            </div>
        `).join('');

        // Add event listeners
        container.querySelectorAll('.remove-method').forEach(btn => {
            btn.addEventListener('click', (e) => this.removeSavedMethod(e.target.dataset.methodId));
        });

        container.querySelectorAll('input[name="savedMethod"]').forEach(input => {
            input.addEventListener('change', (e) => this.selectSavedMethod(e.target.value));
        });
    }

    async removeSavedMethod(methodId) {
        if (!confirm('Are you sure you want to remove this payment method?')) return;

        try {
            const response = await fetch(`/api/payment-methods/${methodId}`, {
                method: 'DELETE',
                headers: {
                    'Authorization': `Bearer ${this.getAuthToken()}`
                }
            });

            if (response.ok) {
                this.showNotification('Payment method removed', 'success');
                this.loadSavedPaymentMethods();
            }
        } catch (error) {
            this.showNotification('Failed to remove payment method', 'error');
        }
    }

    selectSavedMethod(methodId) {
        // Disable new card fields when using saved method
        const cardFields = [this.cardNumber, this.cardExpiry, this.cardCvc];
        cardFields.forEach(field => {
            if (field) {
                field.disabled = true;
                field.removeAttribute('required');
            }
        });
    }

    setupPromoCode() {
        const promoInput = document.getElementById('promoCode');
        const applyBtn = document.getElementById('applyPromo');
        
        if (promoInput && applyBtn) {
            applyBtn.addEventListener('click', () => this.applyPromoCode(promoInput.value));
            
            promoInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.applyPromoCode(promoInput.value);
                }
            });
        }
    }

    async applyPromoCode(code) {
        if (!code.trim()) return;

        try {
            const response = await fetch('/api/promo/validate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ code })
            });

            const result = await response.json();

            if (response.ok) {
                this.updateOrderSummary(result.discount);
                this.showNotification(`Promo code applied: ${result.discount}% off`, 'success');
            } else {
                this.showNotification(result.message || 'Invalid promo code', 'error');
            }
        } catch (error) {
            this.showNotification('Failed to apply promo code', 'error');
        }
    }

    updateOrderSummary(discount) {
        const subtotal = parseFloat(document.getElementById('orderSubtotal')?.textContent.replace('$', '') || 0);
        const discountAmount = (subtotal * discount) / 100;
        const total = subtotal - discountAmount;

        const discountEl = document.getElementById('orderDiscount');
        if (discountEl) {
            discountEl.textContent = `-$${discountAmount.toFixed(2)}`;
            discountEl.parentElement.style.display = 'flex';
        }

        const totalEl = document.getElementById('orderTotal');
        if (totalEl) {
            totalEl.textContent = `$${total.toFixed(2)}`;
        }
    }

    async handleSubmit(e) {
        e.preventDefault();

        if (this.isProcessing) return;

        // Validate all fields
        if (!this.validateForm()) {
            this.showNotification('Please fix the errors in the form', 'error');
            this.shakeForm();
            return;
        }

        this.setProcessingState(true);

        try {
            let paymentResult;

            switch (this.paymentMethod) {
                case 'card':
                    paymentResult = await this.processCardPayment();
                    break;
                case 'paypal':
                    paymentResult = await this.processPayPalPayment();
                    break;
                case 'crypto':
                    paymentResult = await this.processCryptoPayment();
                    break;
                default:
                    throw new Error('Invalid payment method');
            }

            if (paymentResult.success) {
                this.handlePaymentSuccess(paymentResult);
            } else {
                this.handlePaymentError(paymentResult.error);
            }
        } catch (error) {
            this.handlePaymentError(error.message);
        } finally {
            this.setProcessingState(false);
        }
    }

    validateForm() {
        let isValid = true;
        
        // Validate based on payment method
        if (this.paymentMethod === 'card') {
            // If using saved method, skip new card validation
            const savedMethodSelected = this.form.querySelector('input[name="savedMethod"]:checked');
            
            if (!savedMethodSelected) {
                isValid = this.validateCardNumber({ target: this.cardNumber }) && isValid;
                isValid = this.validateExpiry({ target: this.cardExpiry }) && isValid;
                isValid = this.validateCvc({ target: this.cardCvc }) && isValid;
            }
        }

        // Validate common fields
        const requiredFields = [this.cardName, this.email, ...Object.values(this.billingFields)];
        requiredFields.forEach(field => {
            if (field && field.hasAttribute('required') && !field.disabled) {
                isValid = this.validateField(field) && isValid;
            }
        });

        return isValid;
    }

    async processCardPayment() {
        const formData = new FormData(this.form);
        
        // If using Stripe Elements
        if (this.stripe && this.cardElement) {
            const { token, error } = await this.stripe.createToken(this.cardElement);
            
            if (error) {
                throw new Error(error.message);
            }
            
            formData.append('stripeToken', token.id);
        }

        // Send to server
        const response = await fetch('/api/process-payment', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${this.getAuthToken()}`
            },
            body: formData
        });

        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'Payment failed');
        }

        return result;
    }

    async processPayPalPayment() {
        // PayPal payment logic
        return new Promise((resolve, reject) => {
            if (window.paypal) {
                // This would be implemented based on PayPal SDK
                resolve({ success: true, orderId: 'PAYPAL-' + Date.now() });
            } else {
                reject(new Error('PayPal not initialized'));
            }
        });
    }

    async processCryptoPayment() {
        // Cryptocurrency payment logic
        const selectedCrypto = this.form.querySelector('input[name="cryptoType"]:checked')?.value;
        
        if (!selectedCrypto) {
            throw new Error('Please select a cryptocurrency');
        }

        // This would integrate with crypto payment processor
        return { success: true, orderId: 'CRYPTO-' + Date.now() };
    }

    handlePaymentSuccess(result) {
        // Save card if requested
        if (this.saveCard?.checked) {
            this.savePaymentMethod();
        }

        // Show success message
        this.showSuccessScreen(result.orderId);

        // Redirect after delay
        setTimeout(() => {
            window.location.href = `/order-confirmation/${result.orderId}`;
        }, 3000);
    }

    handlePaymentError(error) {
        this.showNotification(error, 'error');
        this.shakeForm();
        
        // Log error for monitoring
        console.error('Payment error:', error);
        
        // Send error to analytics
        if (window.gtag) {
            gtag('event', 'payment_error', {
                error_message: error,
                payment_method: this.paymentMethod
            });
        }
    }

    showSuccessScreen(orderId) {
        const successHtml = `
            <div class="payment-success fade-in">
                <div class="success-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 6L9 17l-5-5"/>
                    </svg>
                </div>
                <h2 class="success-message">Payment Successful!</h2>
                <p class="order-number">Order #${orderId}</p>
                <p>Thank you for your purchase. You will receive a confirmation email shortly.</p>
            </div>
        `;

        this.form.innerHTML = successHtml;
    }

    setProcessingState(isProcessing) {
        this.isProcessing = isProcessing;
        
        if (this.submitBtn) {
            this.submitBtn.disabled = isProcessing;
            this.submitBtn.classList.toggle('loading', isProcessing);
            
            if (!isProcessing) {
                this.submitBtn.textContent = this.submitBtn.dataset.originalText || 'Complete Payment';
            } else {
                this.submitBtn.dataset.originalText = this.submitBtn.textContent;
                this.submitBtn.textContent = 'Processing...';
            }
        }

        // Disable form inputs
        const inputs = this.form.querySelectorAll('input, select, button');
        inputs.forEach(input => {
            if (input !== this.submitBtn) {
                input.disabled = isProcessing;
            }
        });
    }

    shakeForm() {
        this.form.classList.add('shake');
        setTimeout(() => this.form.classList.remove('shake'), 500);
    }

    showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `payment-notification ${type} fade-in`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    getAuthToken() {
        // Get auth token from cookie or localStorage
        return localStorage.getItem('authToken') || '';
    }

    savePaymentMethod() {
        // Save payment method for future use
        const methodData = {
            type: this.paymentMethod,
            last4: this.cardNumber.value.slice(-4),
            brand: this.cardType
        };

        fetch('/api/payment-methods', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.getAuthToken()}`
            },
            body: JSON.stringify(methodData)
        }).catch(error => {
            console.error('Failed to save payment method:', error);
        });
    }

    initializePayPal() {
        // PayPal SDK initialization
        if (!window.paypal) {
            const script = document.createElement('script');
            script.src = 'https://www.paypal.com/sdk/js?client-id=' + this.form.dataset.paypalClientId;
            script.addEventListener('load', () => {
                this.renderPayPalButtons();
            });
            document.head.appendChild(script);
        } else {
            this.renderPayPalButtons();
        }
    }

    renderPayPalButtons() {
        const container = document.getElementById('paypal-button-container');
        if (!container) return;

        paypal.Buttons({
            createOrder: (data, actions) => {
                return actions.order.create({
                    purchase_units: [{
                        amount: {
                            value: this.getOrderTotal()
                        }
                    }]
                });
            },
            onApprove: (data, actions) => {
                return actions.order.capture().then(details => {
                    this.handlePaymentSuccess({
                        success: true,
                        orderId: details.id
                    });
                });
            },
            onError: (err) => {
                this.handlePaymentError('PayPal payment failed');
            }
        }).render('#paypal-button-container');
    }

    initializeCrypto() {
        // Initialize cryptocurrency payment options
        const cryptoSelect = document.getElementById('cryptoSelect');
        if (!cryptoSelect) return;

        cryptoSelect.addEventListener('change', (e) => {
            this.updateCryptoAddress(e.target.value);
        });
    }

    updateCryptoAddress(crypto) {
        const addresses = {
            bitcoin: '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
            ethereum: '0x32Be343B94f860124dC4fEe278FDCBD38C102D88',
            litecoin: 'LQTpS3VaYTjCr4s9Y1t5zbeY26zevf7Fb3'
        };

        const addressEl = document.getElementById('cryptoAddress');
        const qrEl = document.getElementById('cryptoQR');
        
        if (addressEl) {
            addressEl.textContent = addresses[crypto] || '';
        }

        if (qrEl) {
            // Generate QR code for crypto address
            this.generateQRCode(addresses[crypto], qrEl);
        }
    }

    generateQRCode(text, element) {
        // This would use a QR code library like qrcode.js
        element.innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(text)}" alt="QR Code">`;
    }

    getOrderTotal() {
        const totalEl = document.getElementById('orderTotal');
        return totalEl ? totalEl.textContent.replace('$', '') : '0.00';
    }
}

// Utility functions
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

// Auto-fill test card for development
if (window.location.hostname === 'localhost') {
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey && e.shiftKey && e.key === 'T') {
            const testCards = {
                visa: '4242424242424242',
                mastercard: '5555555555554444',
                amex: '378282246310005'
            };
            
            const cardInput = document.getElementById('cardNumber');
            const expiryInput = document.getElementById('cardExpiry');
            const cvcInput = document.getElementById('cardCvc');
            const nameInput = document.getElementById('cardName');
            
            if (cardInput) cardInput.value = testCards.visa;
            if (expiryInput) expiryInput.value = '12/25';
            if (cvcInput) cvcInput.value = '123';
            if (nameInput) nameInput.value = 'Test User';
            
            console.log('Test card details filled');
        }
    });
}

// Additional Payment Features

// 3D Secure Authentication Handler
class SecureAuthenticationHandler {
    constructor(processor) {
        this.processor = processor;
        this.modal = null;
    }

    async authenticate(paymentIntent) {
        if (paymentIntent.status === 'requires_action') {
            return await this.handle3DSecure(paymentIntent);
        }
        return { success: true };
    }

    async handle3DSecure(paymentIntent) {
        return new Promise((resolve, reject) => {
            this.showAuthModal();

            this.processor.stripe.confirmCardPayment(paymentIntent.client_secret)
                .then(result => {
                    this.hideAuthModal();
                    
                    if (result.error) {
                        reject(result.error);
                    } else {
                        resolve({ success: true, paymentIntent: result.paymentIntent });
                    }
                })
                .catch(error => {
                    this.hideAuthModal();
                    reject(error);
                });
        });
    }

    showAuthModal() {
        this.modal = document.createElement('div');
        this.modal.className = 'auth-modal-overlay';
        this.modal.innerHTML = `
            <div class="auth-modal">
                <div class="auth-modal-content">
                    <h3>Authenticating Payment</h3>
                    <p>Please complete the authentication process with your bank.</p>
                    <div class="auth-spinner"></div>
                </div>
            </div>
        `;
        document.body.appendChild(this.modal);
    }

    hideAuthModal() {
        if (this.modal) {
            this.modal.remove();
            this.modal = null;
        }
    }
}

// Apple Pay Integration
class ApplePayHandler {
    constructor(processor) {
        this.processor = processor;
        this.canMakePayments = false;
    }

    async init() {
        if (window.ApplePaySession) {
            this.canMakePayments = ApplePaySession.canMakePayments();
            if (this.canMakePayments) {
                this.showApplePayButton();
            }
        }
    }

    showApplePayButton() {
        const container = document.getElementById('apple-pay-button');
        if (!container) return;

        container.innerHTML = '<button class="apple-pay-button" type="button"></button>';
        container.querySelector('.apple-pay-button').addEventListener('click', () => this.startSession());
    }

    startSession() {
        const request = {
            countryCode: 'US',
            currencyCode: 'USD',
            total: {
                label: 'Your Store',
                amount: this.processor.getOrderTotal()
            },
            supportedNetworks: ['visa', 'masterCard', 'amex'],
            merchantCapabilities: ['supports3DS']
        };

        const session = new ApplePaySession(3, request);

        session.onvalidatemerchant = async (event) => {
            const merchantValidation = await this.validateMerchant(event.validationURL);
            session.completeMerchantValidation(merchantValidation);
        };

        session.onpaymentauthorized = async (event) => {
            const result = await this.processApplePayPayment(event.payment);
            
            if (result.success) {
                session.completePayment(ApplePaySession.STATUS_SUCCESS);
                this.processor.handlePaymentSuccess(result);
            } else {
                session.completePayment(ApplePaySession.STATUS_FAILURE);
                this.processor.handlePaymentError(result.error);
            }
        };

        session.begin();
    }

    async validateMerchant(validationURL) {
        const response = await fetch('/api/apple-pay/validate-merchant', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ validationURL })
        });

        return await response.json();
    }

    async processApplePayPayment(payment) {
        const response = await fetch('/api/apple-pay/process', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.processor.getAuthToken()}`
            },
            body: JSON.stringify({ payment })
        });

        return await response.json();
    }
}

// Google Pay Integration
class GooglePayHandler {
    constructor(processor) {
        this.processor = processor;
        this.paymentsClient = null;
    }

    async init() {
        if (!window.google?.payments?.api?.PaymentsClient) {
            await this.loadGooglePayScript();
        }

        this.paymentsClient = new google.payments.api.PaymentsClient({
            environment: this.processor.form.dataset.environment || 'TEST'
        });

        const isReadyToPay = await this.paymentsClient.isReadyToPay({
            apiVersion: 2,
            apiVersionMinor: 0,
            allowedPaymentMethods: [this.getCardPaymentMethod()]
        });

        if (isReadyToPay.result) {
            this.showGooglePayButton();
        }
    }

    loadGooglePayScript() {
        return new Promise((resolve) => {
            const script = document.createElement('script');
            script.src = 'https://pay.google.com/gp/p/js/pay.js';
            script.onload = resolve;
            document.head.appendChild(script);
        });
    }

    getCardPaymentMethod() {
        return {
            type: 'CARD',
            parameters: {
                allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
                allowedCardNetworks: ['VISA', 'MASTERCARD', 'AMEX', 'DISCOVER']
            },
            tokenizationSpecification: {
                type: 'PAYMENT_GATEWAY',
                parameters: {
                    gateway: 'stripe',
                    'stripe:version': '2020-08-27',
                    'stripe:publishableKey': this.processor.publicKey
                }
            }
        };
    }

    showGooglePayButton() {
        const container = document.getElementById('google-pay-button');
        if (!container) return;

        const button = this.paymentsClient.createButton({
            onClick: () => this.startPayment()
        });

        container.appendChild(button);
    }

    async startPayment() {
        const paymentDataRequest = {
            apiVersion: 2,
            apiVersionMinor: 0,
            allowedPaymentMethods: [this.getCardPaymentMethod()],
            transactionInfo: {
                totalPriceStatus: 'FINAL',
                totalPrice: this.processor.getOrderTotal(),
                currencyCode: 'USD',
                countryCode: 'US'
            },
            merchantInfo: {
                merchantId: this.processor.form.dataset.googleMerchantId,
                merchantName: 'Your Store'
            }
        };

        try {
            const paymentData = await this.paymentsClient.loadPaymentData(paymentDataRequest);
            const result = await this.processGooglePayPayment(paymentData);
            
            if (result.success) {
                this.processor.handlePaymentSuccess(result);
            } else {
                this.processor.handlePaymentError(result.error);
            }
        } catch (error) {
            console.error('Google Pay error:', error);
        }
    }

    async processGooglePayPayment(paymentData) {
        const response = await fetch('/api/google-pay/process', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.processor.getAuthToken()}`
            },
            body: JSON.stringify({ paymentData })
        });

        return await response.json();
    }
}

// Installment Payment Calculator
class InstallmentCalculator {
    constructor(container) {
        this.container = container;
        this.amount = 0;
        this.plans = [];
    }

    init(amount) {
        this.amount = amount;
        this.loadPlans();
    }

    async loadPlans() {
        try {
            const response = await fetch(`/api/installment-plans?amount=${this.amount}`);
            this.plans = await response.json();
            this.render();
        } catch (error) {
            console.error('Failed to load installment plans:', error);
        }
    }

    render() {
        if (!this.container || this.plans.length === 0) return;

        this.container.innerHTML = `
            <div class="installment-options">
                <h4>Pay in Installments</h4>
                ${this.plans.map(plan => `
                    <label class="installment-plan">
                        <input type="radio" name="installmentPlan" value="${plan.id}">
                        <div class="plan-details">
                            <span class="plan-name">${plan.name}</span>
                            <span class="plan-amount">${plan.installments} x ${formatCurrency(plan.monthlyAmount)}</span>
                            ${plan.interestRate > 0 ? `<span class="plan-apr">${plan.interestRate}% APR</span>` : '<span class="plan-apr">0% interest</span>'}
                        </div>
                    </label>
                `).join('')}
            </div>
        `;

        // Add event listeners
        this.container.querySelectorAll('input[name="installmentPlan"]').forEach(input => {
            input.addEventListener('change', (e) => this.selectPlan(e.target.value));
        });
    }

    selectPlan(planId) {
        const plan = this.plans.find(p => p.id === planId);
        if (plan) {
            // Update UI to show selected plan details
            this.showPlanBreakdown(plan);
        }
    }

    showPlanBreakdown(plan) {
        const breakdown = document.getElementById('installmentBreakdown');
        if (!breakdown) return;

        const totalAmount = plan.monthlyAmount * plan.installments;
        const totalInterest = totalAmount - this.amount;

        breakdown.innerHTML = `
            <div class="breakdown-details">
                <h5>Payment Schedule</h5>
                <div class="breakdown-row">
                    <span>Today's payment:</span>
                    <span>${formatCurrency(plan.downPayment || plan.monthlyAmount)}</span>
                </div>
                <div class="breakdown-row">
                    <span>Monthly payment:</span>
                    <span>${formatCurrency(plan.monthlyAmount)}</span>
                </div>
                <div class="breakdown-row">
                    <span>Total interest:</span>
                    <span>${formatCurrency(totalInterest)}</span>
                </div>
                <div class="breakdown-row total">
                    <span>Total amount:</span>
                    <span>${formatCurrency(totalAmount)}</span>
                </div>
            </div>
        `;
    }
}

// Fraud Detection
class FraudDetector {
    constructor() {
        this.signals = {};
        this.startTime = Date.now();
    }

    collectSignals() {
        this.signals = {
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            language: navigator.language,
            colorDepth: screen.colorDepth,
            deviceMemory: navigator.deviceMemory,
            hardwareConcurrency: navigator.hardwareConcurrency,
            sessionDuration: Date.now() - this.startTime,
            touchSupport: 'ontouchstart' in window,
            platform: navigator.platform
        };

        return this.signals;
    }

    async checkVelocity(email) {
        try {
            const response = await fetch('/api/fraud/velocity-check', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ email, signals: this.collectSignals() })
            });

            const result = await response.json();
            return result.riskLevel || 'low';
        } catch (error) {
            console.error('Velocity check failed:', error);
            return 'unknown';
        }
    }
}

// Initialize payment enhancements
document.addEventListener('DOMContentLoaded', function() {
    const paymentForm = document.getElementById('paymentForm');
    
    if (paymentForm) {
        // Initialize main payment processor
        const processor = new PaymentProcessor(paymentForm);
        processor.init();

        // Initialize additional payment methods
        const applePayHandler = new ApplePayHandler(processor);
        applePayHandler.init();

        const googlePayHandler = new GooglePayHandler(processor);
        googlePayHandler.init();

        // Initialize 3D Secure handler
        processor.secureAuth = new SecureAuthenticationHandler(processor);

        // Initialize installment calculator
        const installmentContainer = document.getElementById('installmentOptions');
        if (installmentContainer) {
            const calculator = new InstallmentCalculator(installmentContainer);
            const orderTotal = parseFloat(processor.getOrderTotal());
            if (orderTotal >= 50) { // Minimum for installments
                calculator.init(orderTotal);
            }
        }

        // Initialize fraud detection
        processor.fraudDetector = new FraudDetector();

        // Add to window for debugging in development
        if (window.location.hostname === 'localhost') {
            window.paymentDebug = {
                processor,
                applePayHandler,
                googlePayHandler
            };
        }
    }
});

// Export for use in other modules
window.PaymentProcessor = PaymentProcessor;
window.SecureAuthenticationHandler = SecureAuthenticationHandler;
window.ApplePayHandler = ApplePayHandler;
window.GooglePayHandler = GooglePayHandler;
window.InstallmentCalculator = InstallmentCalculator;