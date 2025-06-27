// Password validation
document.addEventListener('DOMContentLoaded', () => {
    const pwField = document.getElementById('password');
    const pwHelp = document.getElementById('passwordHelp');
    
    pwField?.addEventListener('input', function() {
        const pw = this.value;
        const valid = /[A-Z]/.test(pw) && /[a-z]/.test(pw) && /[0-9]/.test(pw) && /[!@#$%^&*(),.?":{}|<>]/.test(pw) && pw.length >= 8;
        
        pwHelp.className = valid ? 'text-success' : 'text-danger';
        pwHelp.textContent = valid ? 'Strong password ✓' : 'Must contain uppercase, lowercase, number and special character (min 8 chars)';
    });
});

// Navigation functionality
document.querySelectorAll('.nav-link[data-section]').forEach(link => {
    link.addEventListener('click', e => {
        e.preventDefault();
        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
        link.classList.add('active');
        document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
        document.getElementById(link.getAttribute('data-section')).classList.add('active');
    });
});

document.querySelectorAll('button[data-section]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
        const target = btn.getAttribute('data-section');
        document.querySelector(`.nav-link[data-section="${target}"]`).classList.add('active');
        document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
        document.getElementById(target).classList.add('active');
    });
});

// Request filtering
document.querySelectorAll('[data-filter]').forEach(btn => {
    btn.addEventListener('click', function() {
        const filter = this.getAttribute('data-filter');
        document.querySelectorAll('[data-filter]').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        document.querySelectorAll('.request-card').forEach(card => {
            card.style.display = (filter === 'all' || card.getAttribute('data-type') === filter) ? 'block' : 'none';
        });
    });
});

// Activity log filtering
document.getElementById('activityFilter')?.addEventListener('change', function() {
    const filter = this.value;
    document.querySelectorAll('.activity-row').forEach(row => {
        row.style.display = (filter === 'all' || row.getAttribute('data-action') === filter) ? '' : 'none';
    });
});

// Form confirmations
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', e => {
        const isApprove = form.querySelector('[name="approve_request"]');
        const isReject = form.querySelector('[name="reject_request"]');
        const requestId = form.querySelector('[name="request_id"]');
        if (requestId) {
            const id = requestId.value;
            if (isApprove && !confirm(`Are you sure you want to APPROVE request #${id}?`)) e.preventDefault();
            else if (isReject && !confirm(`Are you sure you want to REJECT request #${id}?`)) e.preventDefault();
        }
    });
});
document.addEventListener('DOMContentLoaded', function() {
            // Navigation functionality
            const navLinks = document.querySelectorAll('.nav-link[data-section]');
            const contentSections = document.querySelectorAll('.content-section');
            
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    const targetSection = this.getAttribute('data-section');
                    
                    // Remove active class from all nav links and content sections
                    navLinks.forEach(nav => nav.classList.remove('active'));
                    contentSections.forEach(section => section.classList.remove('active'));
                    
                    // Add active class to clicked nav link and corresponding content section
                    this.classList.add('active');
                    document.getElementById(targetSection).classList.add('active');
                });
            });
            
            // Password validation
            const passwordField = document.getElementById('password');
            const passwordHelp = document.getElementById('passwordHelp');
            
            if (passwordField && passwordHelp) {
                passwordField.addEventListener('input', function() {
                    const password = this.value;
                    const hasUpper = /[A-Z]/.test(password);
                    const hasLower = /[a-z]/.test(password);
                    const hasNumber = /[0-9]/.test(password);
                    const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
                    const isLongEnough = password.length >= 8;
                    
                    if (hasUpper && hasLower && hasNumber && hasSpecial && isLongEnough) {
                        passwordHelp.className = 'text-success';
                        passwordHelp.textContent = 'Strong password ✓';
                    } else {
                        passwordHelp.className = 'text-danger';
                        passwordHelp.textContent = 'Must contain uppercase, lowercase, number and special character (min 8 chars)';
                    }
                });
            }
            
            // Activity log filtering functionality
            const activityFilter = document.getElementById('activityFilter');
            if (activityFilter) {
                activityFilter.addEventListener('change', function() {
                    const filter = this.value;
                    const activityRows = document.querySelectorAll('.activity-row');
                    
                    activityRows.forEach(row => {
                        const rowAction = row.getAttribute('data-action');
                        
                        // Show row if filter is 'all' or matches the row's action
                        if (filter === 'all' || rowAction === filter) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }
        });