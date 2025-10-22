<script>
// Auto-format verification code input
document.addEventListener('DOMContentLoaded', function() {
    try {
    sessionStorage.removeItem('help_first_login_shown');
    sessionStorage.removeItem('helpModalState');
    sessionStorage.removeItem('helpCurrentSession');
} catch (e) {
    // Silently fail if sessionStorage is not available
}
    const verificationInput = document.getElementById('verification-code');
    if (verificationInput) {
        verificationInput.addEventListener('input', function(e) {
            // Only allow numbers
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Auto-submit when 6 digits are entered
            if (this.value.length === 6) {
                setTimeout(() => {
                    this.form.submit();
                }, 100);
            }
        });

        // Select all text when focused
        verificationInput.addEventListener('focus', function() {
            this.select();
        });
    }
});

// reCAPTCHA v3 for login form
@if (config('services.recaptcha.key') && !session('show_verification'))
document.addEventListener('DOMContentLoaded', function() {
  const loginForm = document.getElementById('login-form');
  
  if (loginForm) {
    loginForm.addEventListener('submit', function(e) {
      e.preventDefault();
      
      grecaptcha.ready(function() {
        grecaptcha.execute('{{ config('services.recaptcha.key') }}', {action: 'login'}).then(function(token) {
          document.getElementById('g-recaptcha-response').value = token;
          loginForm.submit();
        });
      });
    });
  }
});
@endif

// Resend code function
function resendCode() {
    const btn = document.getElementById('resend-code-btn');
    btn.disabled = true;
    btn.textContent = 'Küldés...';
    
    // Create form to resend code
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("resend-2fa-code") }}';
    form.style.display = 'none';
    
    // Add CSRF token
    const csrfToken = document.createElement('input');
    csrfToken.type = 'hidden';
    csrfToken.name = '_token';
    csrfToken.value = '{{ csrf_token() }}';
    form.appendChild(csrfToken);
    
    document.body.appendChild(form);
    form.submit();
}

// Countdown timer for resend button (optional enhancement)
@if(session('show_verification'))
let countdown = 30;
const resendBtn = document.getElementById('resend-code-btn');
if (resendBtn && countdown > 0) {
    resendBtn.disabled = true;
    const originalText = resendBtn.textContent;
    
    const timer = setInterval(() => {
        resendBtn.textContent = `${originalText} (${countdown}s)`;
        countdown--;
        
        if (countdown < 0) {
            clearInterval(timer);
            resendBtn.disabled = false;
            resendBtn.textContent = originalText;
        }
    }, 1000);
}
@endif
</script>