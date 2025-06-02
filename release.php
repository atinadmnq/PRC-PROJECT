<?php
session_start();

// Check if user is logged in first
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Database connection - Updated for your database
$host = 'localhost';
$dbname = 'prc_release_db';
$username = 'root'; 
$password = ''; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Include or define the logActivity function
function logActivity($username, $action, $details = '', $clientName = null, $additionalInfo = null, $releaseId = null) {
    global $pdo;
    try {
        // Build the details string with additional context
        $fullDetails = $details;
        if ($clientName) {
            $fullDetails .= " - Client: " . $clientName;
        }
        if ($releaseId) {
            $fullDetails .= " - Release ID: " . $releaseId;
        }
        if ($additionalInfo) {
            $fullDetails .= " - " . $additionalInfo;
        }
        
        $stmt = $pdo->prepare("INSERT INTO activity_log (username, action, details, timestamp) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$username, $action, $fullDetails]);
    } catch (PDOException $e) {
        error_log("Activity logging failed: " . $e->getMessage());
    }
}

// Log page access
logActivity($_SESSION['account_name'] ?? $_SESSION['email'], 'page_access', 'Release page accessed');

// Handle AJAX search request
if (isset($_POST['action']) && $_POST['action'] == 'search') {
    header('Content-Type: application/json');
   
    $profession = trim($_POST['profession'] ?? '');
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
   
    // Log search activity
    $searchCriteria = [];
    if (!empty($profession)) $searchCriteria[] = "Profession: $profession";
    if (!empty($firstname)) $searchCriteria[] = "First Name: $firstname";
    if (!empty($lastname)) $searchCriteria[] = "Last Name: $lastname";
    
    $searchDetails = "Client search performed - " . implode(', ', $searchCriteria);
    logActivity($_SESSION['account_name'] ?? $_SESSION['email'], 'client_search', $searchDetails);
   
    try {
        // Build the query dynamically based on search criteria
        $sql = "SELECT * FROM client WHERE 1=1";
        $params = [];
       
        if (!empty($profession)) {
            $sql .= " AND profession LIKE ?";
            $params[] = "%$profession%";
        }
       
        if (!empty($firstname)) {
            $sql .= " AND firstname LIKE ?";
            $params[] = "%$firstname%";
        }
       
        if (!empty($lastname)) {
            $sql .= " AND lastname LIKE ?";
            $params[] = "%$lastname%";
        }
       
        // Add ordering
        $sql .= " ORDER BY lastname, firstname";
       
        // Prepare and execute the query
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
       
        // Log search results count
        logActivity($_SESSION['account_name'] ?? $_SESSION['email'], 'search_results', 
                   "Search returned " . count($results) . " results");
       
        // Format the results to ensure consistent field names
        $formattedResults = [];
        foreach ($results as $row) {
            $formattedResults[] = [
                'id' => $row['id'] ?? $row['client_id'] ?? 0,
                'profession' => $row['profession'] ?? '',
                'firstname' => $row['firstname'] ?? $row['first_name'] ?? '',
                'lastname' => $row['lastname'] ?? $row['last_name'] ?? '',
                'email' => $row['email'] ?? '',
                'phone' => $row['phone'] ?? $row['phone_number'] ?? '',
                'address' => $row['address'] ?? '',
                'date_of_birth' => $row['date_of_birth'] ?? $row['dob'] ?? '',
                'registration_date' => $row['registration_date'] ?? $row['created_at'] ?? '',
                'status' => $row['status'] ?? 'Active'
            ];
        }
       
        echo json_encode($formattedResults);
       
    } catch(PDOException $e) {
        // Log search error
        logActivity($_SESSION['account_name'] ?? $_SESSION['email'], 'search_error', 
                   "Database query failed: " . $e->getMessage());
        
        // Return error response
        http_response_code(500);
        echo json_encode(['error' => 'Database query failed: ' . $e->getMessage()]);
    }
   
    exit();
}

// Handle release generation request
if (isset($_POST['action']) && $_POST['action'] == 'generate_release') {
    header('Content-Type: application/json');
    
    $clientId = $_POST['client_id'] ?? 0;
    $clientName = $_POST['client_name'] ?? '';
    
    try {
        // Generate a unique release ID
        $releaseId = 'REL-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // For now, we'll just simulate the process
        sleep(2); // Simulate processing time
        
        // Log the release activity
        logActivity($_SESSION['account_name'] ?? $_SESSION['email'], 'release_generated', 
                   'Release generated successfully', $clientName, null, $releaseId);
        
        echo json_encode([
            'success' => true,
            'release_id' => $releaseId,
            'message' => 'Release generated successfully'
        ]);
        
    } catch (Exception $e) {
        // Log release error
        logActivity($_SESSION['account_name'] ?? $_SESSION['email'], 'release_error', 
                   'Release generation failed: ' . $e->getMessage(), $clientName);
        
        http_response_code(500);
        echo json_encode(['error' => 'Release generation failed: ' . $e->getMessage()]);
    }
    
    exit();
}

// Get all professions for the dropdown (optional - for dynamic dropdown)
try {
    $professionStmt = $pdo->prepare("SELECT DISTINCT profession FROM client WHERE profession IS NOT NULL AND profession != '' ORDER BY profession");
    $professionStmt->execute();
    $professions = $professionStmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    $professions = ['CERTIFIED PLANT MECHANIC', 'CHEMICAL ENGINEER', 'CHEMICAL TECHNICIAN', 'CHEMIST', 'CIVIL ENGINEER', 'CRIMONOLOGIST', 'DENTIST', 'ELECTRONICS ENGINEER', 'ELECTRONICS TECHNICIAN', 'FORESTER', 
     'GUIDANCE COUNSELOR', 'LIBRARIAN', 'MASTER PLUMBER', 'MEDICAL TECHNOLOGIST', 'MECHANICAL ENGINEER', 'MINING ENGINEER', 'MIDWIFE', 'NURSES', 'NUTRITIONIST DEITITIANS', 
     'OCCUPATIONAL THERAPIST', 'PHYSICIAN', 'PHYSICAL THERAPIST', 'PROFESSIONAL TEACHERS', 'RESPIRATORY THERAPIST', 'REAL ESTATE APPRAISER', 'REAL ESTATE BROKER', 'SOCIAL WORKER ', 'VERTERINARIANS',]; // Fallback
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Release</title>
    <link rel="icon" type="image/x-icon" href="img/rilis-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
        }
       
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(135deg, rgb(41, 63, 161) 0%, rgb(49, 124, 210) 100%);
            color: white;
            z-index: 1000;
            transition: all 0.3s ease;
        }
       
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
       
        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            text-decoration: none;
            color: white;
        }
       
        .sidebar-brand:hover {
            color: white;
        }
       
        .user-info {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
       
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 1.5rem;
        }
       
        .nav-menu {
            padding: 20px 0;
        }
       
        .nav-item {
            margin-bottom: 5px;
        }
       
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }
       
        .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
       
        .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
            border-right: 3px solid white;
        }
       
        .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
       
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            padding: 30px;
        }
       
        .page-header {
            margin-bottom: 30px;
        }
       
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
        }
       
        .search-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
       
        .client-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
       
        .client-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #007bff;
        }
       
        .client-card.selected {
            border-color: #007bff;
            background: #f8f9ff;
        }
       
        .biodata-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            display: none;
        }
       
        .biodata-container.show {
            display: block;
            animation: slideIn 0.3s ease;
        }
       
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
       
        .back-button {
            margin-bottom: 20px;
        }
       
        .search-results {
            max-height: 400px;
            overflow-y: auto;
        }
       
        .no-results {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
       
        .loading {
            text-align: center;
            padding: 40px;
        }
       
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border: 1px solid #f5c6cb;
        }
       
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
           
            .sidebar.mobile-open {
                transform: translateX(0);
            }
           
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar Header -->
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand">
                <i class="fas fa-shield-alt me-2"></i>
                PRC Release
            </a>
        </div>
       
        <!-- User Info -->
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['email'] ?? 'User'); ?></div>
            <small class="text-light">Administrator</small>
        </div>
       
        <!-- Navigation Menu -->
        <nav class="nav-menu">
            <ul class="list-unstyled">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="release.php" class="nav-link active">
                        <i class="fas fa-search"></i>
                        Client Search
                    </a>
                </li>
                <li class="nav-item">
                    <a href="upload.php" class="nav-link">
                        <i class="fas fa-upload"></i>
                        Upload Files
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?logout=1" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </li>
            </ul>
        </nav>
    </div>
   
    <!-- Main Content -->
    <div class="main-content">
        <!-- Back Button -->
        <div class="back-button">
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>
                Back to Dashboard
            </a>
        </div>
       
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-search me-3"></i>
                Client Search
            </h1>
            <p class="text-muted">Search for clients by profession and name</p>
        </div>
       
        <!-- Search Container -->
        <div class="search-container">
            <form id="searchForm">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="profession" class="form-label">Profession</label>
                        <select class="form-select" id="profession" name="profession">
                            <option value="">All Professions</option>
                            <?php foreach ($professions as $prof): ?>
                                <option value="<?php echo htmlspecialchars($prof); ?>"><?php echo htmlspecialchars($prof); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="firstname" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="firstname" name="firstname" placeholder="Enter first name">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="lastname" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="lastname" name="lastname" placeholder="Enter last name">
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>
                        Search
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="clearBtn">
                        <i class="fas fa-times me-2"></i>
                        Clear
                    </button>
                </div>
            </form>
        </div>
       
        <!-- Search Results -->
        <div id="searchResults" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Search Results
                </h5>
                <span id="resultsCount" class="badge bg-primary"></span>
            </div>
           
            <div class="row">
                <div class="col-md-6">
                    <div class="search-results" id="resultsContainer">
                        <!-- Search results will be populated here -->
                    </div>
                </div>
                <div class="col-md-6">
                    <!-- Client Biodata -->
                    <div class="biodata-container" id="biodataContainer">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0">
                                <i class="fas fa-user me-2"></i>
                                Client Information
                            </h5>
                            <button class="btn btn-success" id="releaseBtn">
                                <i class="fas fa-certificate me-2"></i>
                                Generate Release
                            </button>
                        </div>
                       
                        <div id="clientBiodata">
                            <!-- Client biodata will be populated here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedClient = null;
       
        // Search form submission
        document.getElementById('searchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            performSearch();
        });
       
        // Clear button
        document.getElementById('clearBtn').addEventListener('click', function() {
            document.getElementById('searchForm').reset();
            document.getElementById('searchResults').style.display = 'none';
            selectedClient = null;
        });
       
        function performSearch() {
            const formData = new FormData(document.getElementById('searchForm'));
            formData.append('action', 'search');
           
            // Show loading
            const resultsContainer = document.getElementById('resultsContainer');
            resultsContainer.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Searching...</p></div>';
            document.getElementById('searchResults').style.display = 'block';
           
            // Make AJAX request to search database
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                displaySearchResults(data);
            })
            .catch(error => {
                console.error('Error:', error);
                resultsContainer.innerHTML = `
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Error:</strong> ${error.message}
                    </div>
                `;
            });
        }
       
        function displaySearchResults(results) {
            const resultsContainer = document.getElementById('resultsContainer');
            const resultsCount = document.getElementById('resultsCount');
           
            resultsCount.textContent = `${results.length} found`;
           
            if (results.length === 0) {
                resultsContainer.innerHTML = '<div class="no-results"><i class="fas fa-search fa-2x text-muted"></i><p class="mt-2">No clients found matching your criteria</p></div>';
                return;
            }
           
            let html = '';
            results.forEach(client => {
                html += `
                    <div class="client-card" onclick="selectClient(${client.id}, ${JSON.stringify(client).replace(/"/g, '&quot;')})">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">${client.firstname} ${client.lastname}</h6>
                                <p class="mb-1 text-muted">${client.profession}</p>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-success">${client.status}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
           
            resultsContainer.innerHTML = html;
        }
       
        function selectClient(clientId, clientData) {
            // Remove previous selection
            document.querySelectorAll('.client-card').forEach(card => {
                card.classList.remove('selected');
            });
           
            // Select current client
            event.target.closest('.client-card').classList.add('selected');
            selectedClient = clientData;
           
            // Display biodata
            displayClientBiodata(clientData);
        }
       
        function displayClientBiodata(client) {
            const biodataContainer = document.getElementById('biodataContainer');
            const clientBiodata = document.getElementById('clientBiodata');
           
            // Format dates properly
            const formatDate = (dateString) => {
                if (!dateString) return 'N/A';
                try {
                    return new Date(dateString).toLocaleDateString();
                } catch (e) {
                    return dateString;
                }
            };
           
            const biodataHtml = `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Full Name</label>
                        <p class="form-control-plaintext">${client.firstname || ''} ${client.lastname || ''}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Profession</label>
                        <p class="form-control-plaintext">${client.profession || 'N/A'}</p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Status</label>
                        <p class="form-control-plaintext"><span class="badge bg-success">${client.status || 'Active'}</span></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <p class="form-control-plaintext">${client.email || 'N/A'}</p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Phone</label>
                        <p class="form-control-plaintext">${client.phone || 'N/A'}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Date of Birth</label>
                        <p class="form-control-plaintext">${formatDate(client.date_of_birth)}</p>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Address</label>
                    <p class="form-control-plaintext">${client.address || 'N/A'}</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Registration Date</label>
                    <p class="form-control-plaintext">${formatDate(client.registration_date)}</p>
                </div>
            `;
           
            clientBiodata.innerHTML = biodataHtml;
            biodataContainer.classList.add('show');
        }
       
        // Release button functionality
        document.getElementById('releaseBtn').addEventListener('click', function() {
            if (!selectedClient) {
                alert('Please select a client first');
                return;
            }
           
            const confirmRelease = confirm(`Generate release for ${selectedClient.firstname} ${selectedClient.lastname}?`);
           
            if (confirmRelease) {
                // Prepare form data for release generation
                const formData = new FormData();
                formData.append('action', 'generate_release');
                formData.append('client_id', selectedClient.id);
                formData.append('client_name', `${selectedClient.firstname} ${selectedClient.lastname}`);
                
                // Update button state
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating...';
                this.disabled = true;
                
                // Make AJAX request to generate release
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Release generated successfully!\nRelease ID: ${data.release_id}\n\nFor client: ${selectedClient.firstname} ${selectedClient.lastname}`);
                    } else {
                        throw new Error(data.error || 'Unknown error occurred');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert(`Error generating release: ${error.message}`);
                })
                .finally(() => {
                    // Reset button state
                    this.innerHTML = '<i class="fas fa-certificate me-2"></i>Generate Release';
                    this.disabled = false;
                });
            }
        });
    </script>
</body>
</html>