<script>
document.addEventListener('DOMContentLoaded', function() {
  const passwordInput = document.getElementById('ps-password');
  const confirmInput = document.getElementById('ps-password-confirmation');
  const submitBtn = document.getElementById('submit-btn');
  const matchIndicator = document.getElementById('password-match');
  
  // Translation strings
  const translations = {
    matchSuccess: @json(__('password-setup.match_success')),
    matchFail: @json(__('password-setup.match_fail')),
    validationAlert: @json(__('password-setup.validation_alert'))
  };
  
  // Common/weak passwords to check against (basic check, backend does comprehensive check)
  const commonPasswords = [
    '123456', 'password', '12345678', 'qwerty', '123456789', '12345',
    '1234', '111111', '1234567', 'dragon', '123123', 'baseball', 'iloveyou',
    'trustno1', '1234567890', 'superman', 'qwerty123', 'welcome', 'monkey'
  ];
  
  // Validation state
  let validationState = {
    length: false,
    letter: false,
    number: false,
    notCommon: false,
    match: false
  };
  
  // Update requirement UI
  function updateRequirement(id, isValid) {
    const element = document.getElementById(id);
    const icon = element.querySelector('i');
    
    if (isValid) {
      element.classList.remove('invalid');
      element.classList.add('valid');
      icon.classList.remove('fa-times');
      icon.classList.add('fa-check');
    } else {
      element.classList.remove('valid');
      element.classList.add('invalid');
      icon.classList.remove('fa-check');
      icon.classList.add('fa-times');
    }
  }
  
  // Check if password is common
  function isCommonPassword(password) {
    const lowerPassword = password.toLowerCase();
    return commonPasswords.some(common => lowerPassword.includes(common));
  }
  
  // Validate password
  function validatePassword() {
    const password = passwordInput.value;
    
    // Check length (12+ characters)
    validationState.length = password.length >= 12;
    updateRequirement('req-length', validationState.length);
    
    // Check for at least one letter
    validationState.letter = /[a-zA-Z]/.test(password);
    updateRequirement('req-letter', validationState.letter);
    
    // Check for at least one number
    validationState.number = /\d/.test(password);
    updateRequirement('req-number', validationState.number);
    
    // Check if not common password
    validationState.notCommon = !isCommonPassword(password);
    updateRequirement('req-not-common', validationState.notCommon);
    
    // Check password match
    checkPasswordMatch();
    
    // Enable/disable submit button
    updateSubmitButton();
  }
  
  // Check if passwords match
  function checkPasswordMatch() {
    const password = passwordInput.value;
    const confirm = confirmInput.value;
    
    if (confirm.length === 0) {
      matchIndicator.style.display = 'none';
      validationState.match = false;
      return;
    }
    
    if (password === confirm) {
      matchIndicator.textContent = translations.matchSuccess;
      matchIndicator.className = 'password-match-indicator match';
      validationState.match = true;
    } else {
      matchIndicator.textContent = translations.matchFail;
      matchIndicator.className = 'password-match-indicator no-match';
      validationState.match = false;
    }
    
    updateSubmitButton();
  }
  
  // Update submit button state
  function updateSubmitButton() {
    const allValid = validationState.length && 
                     validationState.letter && 
                     validationState.number && 
                     validationState.notCommon && 
                     validationState.match;
    
    submitBtn.disabled = !allValid;
  }
  
  // Event listeners
  passwordInput.addEventListener('input', validatePassword);
  confirmInput.addEventListener('input', checkPasswordMatch);
  
  // Prevent form submission if validation fails
  document.getElementById('password-setup-form').addEventListener('submit', function(e) {
    const allValid = validationState.length && 
                     validationState.letter && 
                     validationState.number && 
                     validationState.notCommon && 
                     validationState.match;
    
    if (!allValid) {
      e.preventDefault();
      alert(translations.validationAlert);
    }
  });
});
</script>