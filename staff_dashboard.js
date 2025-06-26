// Staff Dashboard JavaScript Functionality

// Navigation functionality
document.addEventListener('DOMContentLoaded', function() {
    // Handle navigation links with data-section attribute
    document.querySelectorAll('.nav-link[data-section]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all nav links
            document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
            
            // Add active class to clicked link
            this.classList.add('active');
            
            // Hide all content sections
            document.querySelectorAll('.content-section').forEach(section => section.classList.remove('active'));
            
            // Show target section
            const targetSection = this.getAttribute('data-section');
            document.getElementById(targetSection).classList.add('active');
        });
    });

    // Handle buttons with data-section attribute (for quick actions and other buttons)
    document.querySelectorAll('button[data-section]').forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all nav links
            document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
            
            // Add active class to corresponding nav link
            const targetSection = this.getAttribute('data-section');
            const correspondingNavLink = document.querySelector(`.nav-link[data-section="${targetSection}"]`);
            if (correspondingNavLink) {
                correspondingNavLink.classList.add('active');
            }
            
            // Hide all content sections
            document.querySelectorAll('.content-section').forEach(section => section.classList.remove('active'));
            
            // Show target section
            document.getElementById(targetSection).classList.add('active');
        });
    });

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

