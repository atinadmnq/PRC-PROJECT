// Navigation functionality
document.querySelectorAll('.nav-link[data-section]').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
        this.classList.add('active');
        document.querySelectorAll('.content-section').forEach(section => section.classList.remove('active'));
        document.getElementById(this.getAttribute('data-section')).classList.add('active');
    });
});

document.querySelectorAll('button[data-section]').forEach(button => {
    button.addEventListener('click', function() {
        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
        const targetSection = this.getAttribute('data-section');
        document.querySelector(`.nav-link[data-section="${targetSection}"]`).classList.add('active');
        document.querySelectorAll('.content-section').forEach(section => section.classList.remove('active'));
        document.getElementById(targetSection).classList.add('active');
    });
});

// Activity log filtering
if (document.getElementById('activityFilter')) {
    document.getElementById('activityFilter').addEventListener('change', function() {
        const filter = this.value;
        document.querySelectorAll('.activity-row').forEach(row => {
            row.style.display = (filter === 'all' || row.getAttribute('data-action') === filter) ? '' : 'none';
        });
    });
}

// Enhanced confirmation function for individual releases
function confirmRelease(examineeName) {
    return confirm(`Are you sure you want to release (delete) the record for "${examineeName}"?\n\nThis action cannot be undone.`);
}

// Global variables for pagination and checkbox state management
let checkboxStates = {};
let currentPage = 1;
let rowsPerPage = 10;

// Enhanced checkbox functionality with proper synchronization
document.addEventListener('DOMContentLoaded', function() {
    const selectAllMain = document.getElementById('selectAll');
    const selectAllTable = document.getElementById('selectAllTable');
    const bulkReleaseBtn = document.getElementById('bulkReleaseBtn');

    // Function to get all record checkboxes (including hidden ones due to pagination)
    function getAllRecordCheckboxes() {
        return document.querySelectorAll('.record-checkbox');
    }

    // Function to get visible record checkboxes only
    function getVisibleRecordCheckboxes() {
        return document.querySelectorAll('.record-checkbox:not([style*="display: none"])');
    }

    // Function to update bulk release button state
    function updateBulkReleaseButton() {
        const allCheckboxes = getAllRecordCheckboxes();
        let checkedCount = 0;
        
        // Count checked boxes from both visible and stored states
        allCheckboxes.forEach(checkbox => {
            if (checkbox.checked || checkboxStates[checkbox.value]) {
                checkedCount++;
            }
        });

        if (bulkReleaseBtn) {
            bulkReleaseBtn.disabled = checkedCount === 0;
        }
        console.log(`${checkedCount} checkboxes selected (including across pages)`);
    }

    // Function to update select all checkboxes based on individual selections
    function updateSelectAllState() {
        const allCheckboxes = getAllRecordCheckboxes();
        let checkedCount = 0;
        
        // Count all checked boxes (visible + stored states)
        allCheckboxes.forEach(checkbox => {
            if (checkbox.checked || checkboxStates[checkbox.value]) {
                checkedCount++;
            }
        });
        
        const allChecked = allCheckboxes.length > 0 && checkedCount === allCheckboxes.length;
        
        if (selectAllMain) selectAllMain.checked = allChecked;
        if (selectAllTable) selectAllTable.checked = allChecked;
    }

    // Function to save checkbox states
    function saveCheckboxStates() {
        const checkboxes = getAllRecordCheckboxes();
        checkboxes.forEach(checkbox => {
            checkboxStates[checkbox.value] = checkbox.checked;
        });
    }

    // Function to restore checkbox states
    function restoreCheckboxStates() {
        const checkboxes = getAllRecordCheckboxes();
        checkboxes.forEach(checkbox => {
            if (checkboxStates.hasOwnProperty(checkbox.value)) {
                checkbox.checked = checkboxStates[checkbox.value];
            }
        });
    }

    // Select all main functionality
    if (selectAllMain) {
        selectAllMain.addEventListener('change', function() {
            console.log('Main select all clicked:', this.checked);
            
            // Update all checkboxes across all pages
            const allCheckboxes = getAllRecordCheckboxes();
            allCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
                checkboxStates[checkbox.value] = this.checked;
            });
            
            if (selectAllTable) selectAllTable.checked = this.checked;
            updateBulkReleaseButton();
        });
    }

    // Select all table functionality
    if (selectAllTable) {
        selectAllTable.addEventListener('change', function() {
            console.log('Table select all clicked:', this.checked);
            
            // Update all checkboxes across all pages
            const allCheckboxes = getAllRecordCheckboxes();
            allCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
                checkboxStates[checkbox.value] = this.checked;
            });
            
            if (selectAllMain) selectAllMain.checked = this.checked;
            updateBulkReleaseButton();
        });
    }

    // Function to initialize individual checkbox functionality
    function initializeCheckboxes() {
        const recordCheckboxes = getAllRecordCheckboxes();
        recordCheckboxes.forEach(checkbox => {
            if (!checkbox.hasEventListener) {
                checkbox.addEventListener('change', function() {
                    console.log('Individual checkbox clicked:', this.value, this.checked);
                    checkboxStates[this.value] = this.checked;
                    updateSelectAllState();
                    updateBulkReleaseButton();
                });
                checkbox.hasEventListener = true;
            }
        });
    }

    // Initialize checkboxes
    initializeCheckboxes();
    restoreCheckboxStates();
    updateBulkReleaseButton();
    updateSelectAllState();

    // Setup pagination with checkbox state preservation
    setupPagination();
});

// Fixed bulk release functionality
function bulkRelease() {
    // Get all selected checkboxes from both visible and stored states
    const allCheckboxes = document.querySelectorAll('.record-checkbox');
    const selectedCheckboxes = [];
    
    allCheckboxes.forEach(checkbox => {
        if (checkbox.checked || checkboxStates[checkbox.value]) {
            selectedCheckboxes.push(checkbox);
        }
    });
    
    console.log('Bulk release triggered, selected boxes:', selectedCheckboxes.length);
    
    if (selectedCheckboxes.length === 0) {
        alert('Please select at least one record to release.');
        return;
    }

    // Get selected record details for confirmation
    const selectedRecords = [];
    selectedCheckboxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        if (row) {
            const nameElement = row.querySelector('td:nth-child(3)') || row.querySelector('td:nth-child(2)');
            const examElement = row.querySelector('td:nth-child(4)') || row.querySelector('td:nth-child(3)');
            
            if (nameElement && examElement) {
                const name = nameElement.textContent.trim();
                const examination = examElement.textContent.trim();
                selectedRecords.push({
                    id: checkbox.value,
                    name: name,
                    examination: examination
                });
            }
        }
    });

    console.log('Selected records:', selectedRecords);

    // Show confirmation dialog
    const confirmMessage = `Are you sure you want to release ${selectedRecords.length} record(s)?\n\nThis action cannot be undone.\n\nRecords to be released:\n${selectedRecords.slice(0, 5).map(r => `- ${r.name} (${r.examination})`).join('\n')}${selectedRecords.length > 5 ? `\n... and ${selectedRecords.length - 5} more` : ''}`;
    
    if (confirm(confirmMessage)) {
        submitBulkReleaseForm(selectedCheckboxes);
    }
}

// Enhanced form submission with better error handling
function submitBulkReleaseForm(selectedCheckboxes) {
    // Try to use existing bulk form first
    let bulkForm = document.getElementById('bulkForm');
    
    if (bulkForm) {
        // Clear any existing hidden inputs for bulk release
        const existingBulkInputs = bulkForm.querySelectorAll('input[name="bulk_release"], input[name="selected_records[]"]');
        existingBulkInputs.forEach(input => input.remove());
        
        // Add bulk release indicator
        const bulkInput = document.createElement('input');
        bulkInput.type = 'hidden';
        bulkInput.name = 'bulk_release';
        bulkInput.value = '1';
        bulkForm.appendChild(bulkInput);
        
        // Add all selected record IDs
        selectedCheckboxes.forEach(checkbox => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_records[]';
            input.value = checkbox.value;
            bulkForm.appendChild(input);
        });
        
        console.log('Submitting bulk form with', selectedCheckboxes.length, 'selected records');
        bulkForm.submit();
    } else {
        console.warn('Bulk form not found, creating fallback form');
        createAndSubmitBulkForm(selectedCheckboxes);
    }
}

// Fallback function to create form if bulkForm doesn't exist
function createAndSubmitBulkForm(selectedCheckboxes) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = window.location.href; // Use current page URL
    form.style.display = 'none';
    
    // Add bulk release indicator
    const bulkInput = document.createElement('input');
    bulkInput.type = 'hidden';
    bulkInput.name = 'bulk_release';
    bulkInput.value = '1';
    form.appendChild(bulkInput);
    
    // Add selected record IDs
    selectedCheckboxes.forEach(checkbox => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_records[]';
        input.value = checkbox.value;
        form.appendChild(input);
        console.log('Added checkbox value to form:', checkbox.value);
    });
    
    document.body.appendChild(form);
    console.log('Submitting fallback form with', selectedCheckboxes.length, 'records');
    form.submit();
}

// Enhanced Pagination with Checkbox State Preservation
function setupPagination() {
    const tableBody = document.querySelector('tbody');
    if (!tableBody) return;
    
    const rows = Array.from(tableBody.querySelectorAll('tr'));
    const rowsPerPageSelect = document.getElementById('rowsPerPageSelect');
    
    if (rowsPerPageSelect) {
        rowsPerPage = parseInt(rowsPerPageSelect.value) || 10;
    }
    
    // Create pagination controls if they don't exist
    let paginationContainer = document.getElementById('paginationContainer');
    if (!paginationContainer && rows.length > rowsPerPage) {
        paginationContainer = document.createElement('div');
        paginationContainer.id = 'paginationContainer';
        paginationContainer.className = 'd-flex justify-content-between align-items-center mt-3';
        paginationContainer.innerHTML = `
            <div id="pageInfo" class="text-muted">Page 1 of 1</div>
            <div id="paginationControls" class="d-flex align-items-center gap-2">
                <button id="prevPage" class="btn btn-outline-secondary btn-sm" disabled>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <button id="nextPage" class="btn btn-outline-secondary btn-sm">
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        `;
        
        const tableCard = document.querySelector('.table-responsive').closest('.card');
        if (tableCard) {
            tableCard.parentNode.insertBefore(paginationContainer, tableCard.nextSibling);
        }
    }
    
    const pageInfo = document.getElementById('pageInfo');
    const prevBtn = document.getElementById('prevPage');
    const nextBtn = document.getElementById('nextPage');
    
    let totalPages = Math.ceil(rows.length / rowsPerPage);

    function showPage(page) {
        // Save current checkbox states before changing page
        const currentCheckboxes = document.querySelectorAll('.record-checkbox');
        currentCheckboxes.forEach(checkbox => {
            checkboxStates[checkbox.value] = checkbox.checked;
        });
        
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;

        rows.forEach((row, index) => {
            if (index >= start && index < end) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });

        // Update page info
        if (pageInfo) {
            if (rows.length === 0) {
                pageInfo.textContent = 'No records to display';
            } else {
                const displayStart = start + 1;
                const displayEnd = Math.min(end, rows.length);
                pageInfo.textContent = `Showing ${displayStart}-${displayEnd} of ${rows.length} entries`;
            }
        }
        
        // Update button states
        if (prevBtn) prevBtn.disabled = page === 1;
        if (nextBtn) nextBtn.disabled = page === totalPages || rows.length === 0;
        
        // Restore checkbox states after page change
        setTimeout(() => {
            const newCheckboxes = document.querySelectorAll('.record-checkbox');
            newCheckboxes.forEach(checkbox => {
                if (checkboxStates.hasOwnProperty(checkbox.value)) {
                    checkbox.checked = checkboxStates[checkbox.value];
                }
                // Ensure event listeners are attached
                if (!checkbox.hasEventListener) {
                    checkbox.addEventListener('change', function() {
                        console.log('Individual checkbox clicked:', this.value, this.checked);
                        checkboxStates[this.value] = this.checked;
                        updateSelectAllState();
                        updateBulkReleaseButton();
                    });
                    checkbox.hasEventListener = true;
                }
            });
            updateSelectAllState();
            updateBulkReleaseButton();
        }, 50);
    }

    // Event listeners for pagination
    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                showPage(currentPage);
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            if (currentPage < totalPages) {
                currentPage++;
                showPage(currentPage);
            }
        });
    }

    if (rowsPerPageSelect) {
        rowsPerPageSelect.addEventListener('change', () => {
            rowsPerPage = parseInt(rowsPerPageSelect.value);
            currentPage = 1;
            totalPages = Math.ceil(rows.length / rowsPerPage);
            showPage(currentPage);
        });
    }

    // Initial setup
    if (rows.length > 0) {
        showPage(currentPage);
        if (paginationContainer) paginationContainer.style.display = 'flex';
    }
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (alert.classList.contains('alert-dismissible')) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    });
}, 5000);

// Export form submission
function submitExportForm() {
    document.getElementById('exportExam').value = document.getElementById('examSelect').value;
    document.getElementById('exportSearch').value = document.getElementById('searchName').value;
    document.getElementById('exportForm').submit();
}