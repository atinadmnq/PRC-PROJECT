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

// Enhanced checkbox functionality with proper synchronization
document.addEventListener('DOMContentLoaded', function() {
    const selectAllMain = document.getElementById('selectAll');
    const selectAllTable = document.getElementById('selectAllTable');
    const recordCheckboxes = document.querySelectorAll('.record-checkbox');
    const bulkReleaseBtn = document.getElementById('bulkReleaseBtn');

    // Function to update bulk release button state
    function updateBulkReleaseButton() {
        const checkedBoxes = document.querySelectorAll('.record-checkbox:checked');
        if (bulkReleaseBtn) {
            bulkReleaseBtn.disabled = checkedBoxes.length === 0;
        }
        console.log(`${checkedBoxes.length} checkboxes selected`); // Debug log
    }

    // Function to update select all checkboxes based on individual selections
    function updateSelectAllState() {
        const totalCheckboxes = recordCheckboxes.length;
        const checkedCheckboxes = document.querySelectorAll('.record-checkbox:checked').length;
        
        const allChecked = totalCheckboxes > 0 && checkedCheckboxes === totalCheckboxes;
        
        if (selectAllMain) selectAllMain.checked = allChecked;
        if (selectAllTable) selectAllTable.checked = allChecked;
    }

    // Select all main functionality
    if (selectAllMain) {
        selectAllMain.addEventListener('change', function() {
            console.log('Main select all clicked:', this.checked); // Debug log
            recordCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            if (selectAllTable) selectAllTable.checked = this.checked;
            updateBulkReleaseButton();
        });
    }

    // Select all table functionality
    if (selectAllTable) {
        selectAllTable.addEventListener('change', function() {
            console.log('Table select all clicked:', this.checked); // Debug log
            recordCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            if (selectAllMain) selectAllMain.checked = this.checked;
            updateBulkReleaseButton();
        });
    }

    // Individual checkbox functionality
    recordCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            console.log('Individual checkbox clicked:', this.value, this.checked); // Debug log
            updateSelectAllState();
            updateBulkReleaseButton();
        });
    });

    // Initialize button state
    updateBulkReleaseButton();
});

// Enhanced bulk release functionality with better error handling
function bulkRelease() {
    const checkedBoxes = document.querySelectorAll('.record-checkbox:checked');
    
    console.log('Bulk release triggered, selected boxes:', checkedBoxes.length); // Debug log
    
    if (checkedBoxes.length === 0) {
        alert('Please select at least one record to release.');
        return;
    }

    // Get selected record details for confirmation
    const selectedRecords = [];
    checkedBoxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const nameElement = row.querySelector('td:nth-child(3) span');
        const examElement = row.querySelector('td:nth-child(4)');
        
        if (nameElement && examElement) {
            const name = nameElement.textContent.trim();
            const examination = examElement.textContent.trim();
            selectedRecords.push({
                id: checkbox.value,
                name: name,
                examination: examination
            });
        }
    });

    console.log('Selected records:', selectedRecords); // Debug log

    // Check if Bootstrap modal exists, use it; otherwise use confirm dialog
    const modal = document.getElementById('confirmReleaseModal');
    if (modal) {
        showBulkReleaseModal(selectedRecords, checkedBoxes);
    } else {
        // Fallback to confirm dialog
        const confirmMessage = `Are you sure you want to release ${selectedRecords.length} record(s)?\n\nThis action cannot be undone.\n\nRecords to be released:\n${selectedRecords.slice(0, 5).map(r => `- ${r.name} (${r.examination})`).join('\n')}${selectedRecords.length > 5 ? `\n... and ${selectedRecords.length - 5} more` : ''}`;
        
        if (confirm(confirmMessage)) {
            submitBulkReleaseForm(checkedBoxes);
        }
    }
}

// Enhanced modal function with better UI
function showBulkReleaseModal(selectedRecords, checkedBoxes) {
    const modal = new bootstrap.Modal(document.getElementById('confirmReleaseModal'));
    const content = document.getElementById('releaseConfirmationContent');
    
    let html = `<div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>${selectedRecords.length}</strong> record(s) selected for release:
    </div>
    <div class="list-group" style="max-height: 200px; overflow-y: auto;">`;
    
    selectedRecords.slice(0, 10).forEach(record => {
        html += `<div class="list-group-item">
            <strong>${record.name}</strong><br>
            <small class="text-muted">${record.examination}</small>
        </div>`;
    });
    
    if (selectedRecords.length > 10) {
        html += `<div class="list-group-item text-center text-muted">
            ... and ${selectedRecords.length - 10} more record(s)
        </div>`;
    }
    
    html += '</div>';
    content.innerHTML = html;
    
    // Set up confirm button
    document.getElementById('confirmReleaseBtn').onclick = function() {
        modal.hide();
        submitBulkReleaseForm(checkedBoxes);
    };
    
    modal.show();
}

// Enhanced form submission with better error handling
function submitBulkReleaseForm(checkedBoxes) {
    // Use the existing bulkForm if available
    const bulkForm = document.getElementById('bulkForm');
    if (bulkForm) {
        // Add bulk release input
        const bulkInput = document.createElement('input');
        bulkInput.type = 'hidden';
        bulkInput.name = 'bulk_release';
        bulkInput.value = '1';
        bulkForm.appendChild(bulkInput);
        
        console.log('Submitting bulk form with', checkedBoxes.length, 'selected records'); // Debug log
        bulkForm.submit();
    } else {
        console.error('Bulk form not found'); // Debug log
        // Fallback: create new form
        createAndSubmitBulkForm(checkedBoxes);
    }
}

// Fallback function to create form if bulkForm doesn't exist
function createAndSubmitBulkForm(checkedBoxes) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    // Add bulk release indicator
    const bulkInput = document.createElement('input');
    bulkInput.type = 'hidden';
    bulkInput.name = 'bulk_release';
    bulkInput.value = '1';
    form.appendChild(bulkInput);
    
    // Add selected record IDs
    checkedBoxes.forEach(checkbox => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_records[]';
        input.value = checkbox.value;
        form.appendChild(input);
        console.log('Added checkbox value to form:', checkbox.value); // Debug log
    });
    
    document.body.appendChild(form);
    console.log('Submitting fallback form'); // Debug log
    form.submit();
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
    // Copy values from the visible form
    document.getElementById('exportExam').value = document.getElementById('examSelect').value;
    document.getElementById('exportSearch').value = document.getElementById('searchName').value;
    document.getElementById('exportForm').submit();
}


// Enhanced Pagination with better error handling
document.addEventListener('DOMContentLoaded', function () {
    // Get table body rows (excluding header)
    const tableBody = document.querySelector('tbody');
    if (!tableBody) return; // Exit if no table body found
    
    const rows = tableBody.querySelectorAll('tr');
    const rowsPerPageSelect = document.getElementById('rowsPerPageSelect');
    
    // Create pagination controls if they don't exist
    let paginationContainer = document.getElementById('paginationContainer');
    if (!paginationContainer) {
        // Create pagination container
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
        
        // Insert pagination container after the table card
        const tableCard = document.querySelector('.table-responsive').closest('.card');
        if (tableCard) {
            tableCard.parentNode.insertBefore(paginationContainer, tableCard.nextSibling);
        }
    }
    
    const pageInfo = document.getElementById('pageInfo');
    const prevBtn = document.getElementById('prevPage');
    const nextBtn = document.getElementById('nextPage');
    
    let currentPage = 1;
    let rowsPerPage = parseInt(rowsPerPageSelect?.value || 10);
    let totalPages = Math.ceil(rows.length / rowsPerPage);

    function showPage(page) {
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;

        rows.forEach((row, index) => {
            if (index >= start && index < end) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });

        totalPages = Math.ceil(rows.length / rowsPerPage);
        
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
        
        console.log(`Displaying page ${page} of ${totalPages}`); // Debug log
    }

    // Event listeners
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
            currentPage = 1; // Reset to first page
            totalPages = Math.ceil(rows.length / rowsPerPage);
            showPage(currentPage);
            console.log(`Rows per page changed to: ${rowsPerPage}`); // Debug log
        });
    }

    // Initial setup
    if (rows.length > 0) {
        showPage(currentPage);
        if (paginationContainer) paginationContainer.style.display = 'flex';
    } else {
        if (paginationContainer) paginationContainer.style.display = 'none';
    }

    // Update pagination when filters change (for dynamic content)
    const observer = new MutationObserver(() => {
        const newRows = tableBody.querySelectorAll('tr');
        if (newRows.length !== rows.length) {
            // Rows have changed, reinitialize
            console.log('Table rows changed, reinitializing pagination'); // Debug log
            location.reload(); // Simple approach, or you could update the rows variable
        }
    });

    observer.observe(tableBody, { childList: true });
});