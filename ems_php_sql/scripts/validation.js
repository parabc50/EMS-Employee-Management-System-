// Basic client-side validation helpers used across admin forms
document.addEventListener('DOMContentLoaded', function(){
    // Enforce phone fields to accept only digits and validate 10-digit length
    document.querySelectorAll('input[name="phone"]').forEach(function(input){
        // restrict input to digits
        input.addEventListener('input', function(e){
            var cleaned = this.value.replace(/[^0-9]/g, '');
            if (this.value !== cleaned) this.value = cleaned;
        });
        // set maxlength where absent
        if (!input.hasAttribute('maxlength')) input.setAttribute('maxlength','10');
    });

    // Email inputs: basic HTML5 pattern fallback for older browsers
    document.querySelectorAll('input[type="email"]').forEach(function(inp){
        if (!inp.hasAttribute('pattern')) inp.setAttribute('pattern','^[^@\s]+@[^@\s]+\.[^@\s]+$');
    });

    // Attach form submit handler to provide user-friendly validation messages
    document.querySelectorAll('form').forEach(function(form){
        form.addEventListener('submit', function(e){
            // If browser supports reportValidity, use it
            if (typeof form.reportValidity === 'function'){
                if (!form.reportValidity()) { e.preventDefault(); }
                return;
            }
            // Fallback: basic checks for phone inputs inside the form
            var phones = form.querySelectorAll('input[name="phone"]');
            for (var i=0;i<phones.length;i++){
                var v = phones[i].value.trim();
                if (v !== '' && v.length !== 10){
                    alert('Phone number must be exactly 10 digits.');
                    e.preventDefault();
                    return;
                }
            }
            // email fallback
            var emails = form.querySelectorAll('input[type="email"]');
            var emailRe = /^[^@\s]+@[^@\s]+\.[^@\s]+$/;
            for (var j=0;j<emails.length;j++){
                var ev = emails[j].value.trim();
                if (ev !== '' && !emailRe.test(ev)){
                    alert('Please enter a valid email address.');
                    e.preventDefault();
                    return;
                }
            }
        });
    });
});
