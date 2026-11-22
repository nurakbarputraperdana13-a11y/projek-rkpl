/**
 * auth.js - Handler untuk form login, register, forgot password
 * SakuKu - Aplikasi Manajemen Keuangan Mahasiswa
 */

(function() {
    'use strict';

    // DOM Elements
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const step1Form = document.getElementById('step1Form');
    const step2Form = document.getElementById('step2Form');
    const step3Form = document.getElementById('step3Form');
    const alertContainer = document.getElementById('alertContainer');

    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', function() {
            const wrapper = this.closest('.input-wrapper');
            const input = wrapper.querySelector('input[type="password"], input[type="text"]');
            const isPassword = input.type === 'password';
            
            input.type = isPassword ? 'text' : 'password';
            this.setAttribute('aria-pressed', isPassword);
            
            const eyeIcon = this.querySelector('.icon-eye');
            const eyeOffIcon = this.querySelector('.icon-eye-off');
            if (eyeIcon && eyeOffIcon) {
                eyeIcon.style.display = isPassword ? 'none' : 'block';
                eyeOffIcon.style.display = isPassword ? 'block' : 'none';
            }
        });
    });

    // Show alert
    function showAlert(message, type = 'error') {
        if (alertContainer) {
            alertContainer.innerHTML = `
                <div class="alert alert-${type}" role="alert">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        ${type === 'error' 
                            ? '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'
                            : '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'
                        }
                    </svg>
                    <span>${escapeHtml(message)}</span>
                </div>
            `;
        } else {
            // Fallback for login/register pages
            const existingAlert = document.querySelector('.alert');
            if (existingAlert) existingAlert.remove();
            
            const form = loginForm || registerForm;
            if (form) {
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type}`;
                alertDiv.setAttribute('role', 'alert');
                alertDiv.innerHTML = `
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        ${type === 'error' 
                            ? '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'
                            : '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'
                        }
                    </svg>
                    <span>${escapeHtml(message)}</span>
                `;
                form.insertBefore(alertDiv, form.firstChild);
            }
        }
    }

    // Clear errors
    function clearErrors(form) {
        form.querySelectorAll('.form-error').forEach(el => el.textContent = '');
        form.querySelectorAll('input, select').forEach(el => el.classList.remove('input-error'));
    }

    // Set field error
    function setFieldError(fieldId, message) {
        const errorEl = document.getElementById(fieldId + 'Error');
        const inputEl = document.getElementById(fieldId);
        if (errorEl) errorEl.textContent = message;
        if (inputEl) inputEl.classList.add('input-error');
    }

    // Set button loading state
    function setButtonLoading(button, loading) {
        const btnText = button.querySelector('.btn-text');
        const btnLoader = button.querySelector('.btn-loader');
        
        if (loading) {
            button.disabled = true;
            if (btnText) btnText.style.display = 'none';
            if (btnLoader) btnLoader.hidden = false;
        } else {
            button.disabled = false;
            if (btnText) btnText.style.display = 'inline';
            if (btnLoader) btnLoader.hidden = true;
        }
    }

    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Submit form via AJAX
    async function submitForm(form, actionUrl) {
        const submitBtn = form.querySelector('button[type="submit"]');
        setButtonLoading(submitBtn, true);
        clearErrors(form);

        const formData = new FormData(form);

        try {
            const response = await fetch(actionUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (data.success) {
                showAlert(data.message, 'success');
                
                if (data.redirect) {
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                }
                
                // Handle forgot password steps
                if (form.id === 'step1Form' && data.show_question) {
                    setTimeout(() => {
                        document.getElementById('userId').value = data.user_id;
                        document.getElementById('securityQuestionDisplay').textContent = data.security_question;
                        showStep(2);
                    }, 500);
                }
            } else {
                showAlert(data.message, 'error');
                
                // Handle field-specific errors
                if (data.field_errors) {
                    Object.entries(data.field_errors).forEach(([field, message]) => {
                        setFieldError(field, message);
                    });
                }
            }
        } catch (error) {
            console.error('Form submission error:', error);
            showAlert('Terjadi kesalahan. Silakan coba lagi.', 'error');
        } finally {
            setButtonLoading(submitBtn, false);
        }
    }

    // Show specific step in forgot password
    function showStep(step) {
        [step1Form, step2Form, step3Form].forEach((f, i) => {
            if (i + 1 === step) {
                f.hidden = false;
                f.classList.remove('hidden');
            } else {
                f.hidden = true;
                f.classList.add('hidden');
            }
        });
        
        // Update step indicator
        document.querySelectorAll('.step-item').forEach((item, i) => {
            item.classList.toggle('active', i + 1 <= step);
        });
    }

    // Event Listeners
    if (loginForm) {
        loginForm.addEventListener('submit', (e) => {
            e.preventDefault();
            submitForm(loginForm, '/serius/process/auth_process.php');
        });
    }

    if (registerForm) {
        registerForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            // Validate password match
            const password = registerForm.querySelector('[name="password"]').value;
            const confirmPassword = registerForm.querySelector('[name="confirm_password"]').value;
            
            if (password !== confirmPassword) {
                setFieldError('confirm_password', 'Password tidak cocok');
                return;
            }
            
            submitForm(registerForm, '/serius/process/auth_process.php');
        });
    }

    if (step1Form) {
        step1Form.addEventListener('submit', (e) => {
            e.preventDefault();
            submitForm(step1Form, '/serius/process/auth_process.php');
        });
    }

    if (step2Form) {
        step2Form.addEventListener('submit', (e) => {
            e.preventDefault();
            submitForm(step2Form, '/serius/process/auth_process.php');
        });
        
        document.getElementById('backToStep1')?.addEventListener('click', () => showStep(1));
    }

    if (step3Form) {
        step3Form.addEventListener('submit', (e) => {
            e.preventDefault();
            
            // Validate password match
            const password = step3Form.querySelector('[name="new_password"]').value;
            const confirmPassword = step3Form.querySelector('[name="confirm_password"]').value;
            
            if (password !== confirmPassword) {
                setFieldError('confirm_new_password', 'Password tidak cocok');
                return;
            }
            
            // Copy security answer to hidden field
            document.getElementById('securityAnswerHidden').value = 
                document.getElementById('security_answer').value;
            document.getElementById('userId2').value = document.getElementById('userId').value;
            
            submitForm(step3Form, '/serius/process/auth_process.php');
        });
        
        document.getElementById('backToStep2')?.addEventListener('click', () => showStep(2));
    }

    // Real-time validation feedback
    document.querySelectorAll('input[required], select[required]').forEach(input => {
        input.addEventListener('blur', function() {
            if (this.hasAttribute('required') && !this.value.trim()) {
                this.classList.add('input-error');
            } else {
                this.classList.remove('input-error');
            }
        });
        
        input.addEventListener('input', function() {
            if (this.classList.contains('input-error') && this.value.trim()) {
                this.classList.remove('input-error');
                const errorEl = document.getElementById(this.id + 'Error');
                if (errorEl) errorEl.textContent = '';
            }
        });
    });

    // Password strength indicator (register & step 3)
    function addPasswordStrength(input) {
        const strengthDiv = document.createElement('div');
        strengthDiv.className = 'password-strength';
        strengthDiv.setAttribute('aria-live', 'polite');
        input.parentElement.parentElement.appendChild(strengthDiv);
        
        input.addEventListener('input', function() {
            const val = this.value;
            let strength = 0;
            let message = '';
            
            if (val.length >= 8) strength++;
            if (/[A-Z]/.test(val)) strength++;
            if (/[a-z]/.test(val)) strength++;
            if (/[0-9]/.test(val)) strength++;
            if (/[^A-Za-z0-9]/.test(val)) strength++;
            
            const levels = ['Sangat lemah', 'Lemah', 'Sedang', 'Kuat', 'Sangat kuat'];
            const colors = ['var(--color-destructive)', 'var(--color-warning)', 'var(--color-info)', 'var(--color-success)', 'var(--color-success)'];
            
            if (val.length > 0) {
                strengthDiv.innerHTML = `<div class="strength-bar"><div class="strength-fill" style="width: ${(strength/5)*100}%; background: ${colors[strength-1]}"></div></div><span class="strength-text" style="color: ${colors[strength-1]}">${levels[strength-1]}</span>`;
            } else {
                strengthDiv.innerHTML = '';
            }
        });
    }

    const passwordInputs = document.querySelectorAll('#password, #new_password');
    passwordInputs.forEach(addPasswordStrength);

})();