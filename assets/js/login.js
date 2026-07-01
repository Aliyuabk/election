
    (function() {
        // Preloader
        const preloader = document.getElementById('preloader');
        window.addEventListener('load', function() {
            preloader.classList.add('hidden');
        });
        setTimeout(() => { preloader.classList.add('hidden'); }, 2200);

        // Simple form validation demo (no actual submission)
        const form = document.querySelector('.login-form');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Basic validation feedback
            let isValid = true;
            
            if (!emailInput.value.trim()) {
                emailInput.style.borderColor = '#EF4444';
                isValid = false;
            } else {
                emailInput.style.borderColor = '#10B981';
            }
            
            if (!passwordInput.value.trim()) {
                passwordInput.style.borderColor = '#EF4444';
                isValid = false;
            } else {
                passwordInput.style.borderColor = '#10B981';
            }
            
            if (isValid) {
                // Simulate login success
                const btn = form.querySelector('.btn-login');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Authenticating...';
                btn.disabled = true;
                
                setTimeout(() => {
                    btn.innerHTML = '<i class="fas fa-check-circle"></i> Redirecting...';
                    btn.style.background = '#10B981';
                    
                    setTimeout(() => {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                        btn.style.background = '';
                        // Reset fields
                        emailInput.value = '';
                        passwordInput.value = '';
                        emailInput.style.borderColor = '#E2E8F0';
                        passwordInput.style.borderColor = '#E2E8F0';
                        alert('Login successful! (Demo)');
                    }, 800);
                }, 1200);
            }
        });

        // Clear error state on focus
        emailInput.addEventListener('focus', function() {
            this.style.borderColor = '#2563EB';
        });
        passwordInput.addEventListener('focus', function() {
            this.style.borderColor = '#2563EB';
        });
 
 
    })();