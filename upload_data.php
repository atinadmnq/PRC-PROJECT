<?php
session_start();
require_once 'activity_logger.php';
include 'db_connect.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;


function normalizeExamination($input) {
    $input = strtolower(trim($input));

    $mapping = [
        'aeronautical engineers' => 'AERONAUTICAL ENGINEER',
        'aeronautical engineer' => 'AERONAUTICAL ENGINEER',
        'agriculture' => 'AGRICULTURIST',
        'agriculturists' => 'AGRICULTURIST',
        'agriculturist' => 'AGRICULTURIST',
        'argriculturist' => 'AGRICULTURIST',
        'agricultural & biosystems engineers' => 'AGRICULTURAL AND BIOSYSTEMS ENGINEER',
        'architects' => 'ARCHITECT',
        'architect' => 'ARCHITECT',
        'arch'=> 'ARCHITECT',
        'certified plant mechanics' => 'CERTIFIED PLANT MECHANIC',
        'certified plant mechanic' => 'CERTIFIED PLANT MECHANIC',
        'certified public accountants' => 'CERTIFIED PUBLIC ACCOUNTANT',
        'certified public accountant' => 'CERTIFIED PUBLIC ACCOUNTANT',
        'chemical engineers' => 'CHEMICAL ENGINEER',
        'chemical engineer' => 'CHEMICAL ENGINEER',
        'chem eng' => 'CHEMICAL ENGINEER',
        'chemical technicians' => 'CHEMICAL TECHNICIAN',
        'chemical technician' => 'CHEMICAL TECHNICIAN',
        'chemists' => 'CHEMIST',
        'chemist' => 'CHEMIST',
        'civil engineers' => 'CIVIL ENGINEER',
        'civil engineer' => 'CIVIL ENGINEER',
        'civil eng' => 'CIVIL ENGINEER',
        'criminologists' => 'CRIMINOLOGIST',
        'criminologist' => 'CRIMINOLOGIST',
        'custom brokers' => 'CUSTOMS BROKER',
        'broker' => 'CUSTOMS BROKER',
        'dental hygienists' => 'DENTAL HYGIENIST',
        'dental hygienist' => 'DENTAL HYGIENIST',
        'dental technologists' => 'DENTAL TECHNOLOGIST',
        'dental technologist' => 'DENTAL TECHNOLOGIST',
        'dentists' => 'DENTIST',
        'dentist' => 'DENTIST',
        'electronics engineers' => 'ELECTRONICS ENGINEER',
        'electronics engineer' => 'ELECTRONICS ENGINEER',
        'electronics technicians' => 'ELECTRONICS TECHNICIAN',
        'electronics technician' => 'ELECTRONICS TECHNICIAN',
        'environmental planners' => 'ENVIRONMENTAL PLANNER',
        'environmental planner' => 'ENVIRONMENTAL PLANNER',
        'foresters' => 'FORESTER',
        'forester' => 'FORESTER',
        'geologists' => 'GEOLOGIST',
        'geologist' => 'GEOLOGIST',
        'geodetic engineers' => 'GEODETIC ENGINEER',
        'geodetic engineer' => 'GEODETIC ENGINEER',
        'guidance counselors' => 'GUIDANCE COUNSELOR',
        'guidance counselor' => 'GUIDANCE COUNSELOR',
        'interior designers' => 'INTERIOR DESIGNER',
        'interior designer' => 'INTERIOR DESIGNER',
        'landscape architects' => 'LANDSCAPE ARCHITECT',
        'landscape architect' => 'LANDSCAPE ARCHITECT',
        'librarians' => 'LIBRARIAN',
        'librarian' => 'LIBRARIAN',
        'master plumbers' => 'MASTER PLUMBER',
        'master plumber' => 'MASTER PLUMBER',
        'mechanical engineers' => 'MECHANICAL ENGINEER',
        'mechanical engineer' => 'MECHANICAL ENGINEER',
        'medical technologists' => 'MEDICAL TECHNOLOGIST',
        'medical technologist' => 'MEDICAL TECHNOLOGIST',
        'med tech' => 'MEDICAL TECHNOLOGIST',
        'metallurgical engineers' => 'METALLURGICAL ENGINEER',
        'metallurgical engineer' => 'METALLURGICAL ENGINEER',
        'midwives' => 'MIDWIFE',
        'midwife' => 'MIDWIFE',
        'midwifery' => 'MIDWIFE',
        'mining engineers' => 'MINING ENGINEER',
        'mining engineer' => 'MINING ENGINEER',
        'naval architects' => 'NAVAL ARCHITECT',
        'naval architect' => 'NAVAL ARCHITECT',
        'nurses' => 'NURSE',
        'nurse' => 'NURSE',
        'nursing' => 'NURSE',
        'nutritionists' => 'NUTRITIONIST DIETITIAN',
        'nutritionist dietitian' => 'NUTRITIONIST DIETITIAN',
        'nutritionist dietitians' => 'NUTRITIONIST DIETITIAN',
        'occupational therapists' => 'OCCUPATIONAL THERAPIST',
        'occupational therapist' => 'OCCUPATIONAL THERAPIST',
        'ocular pharmacologists' => 'OCULAR PHARMACOLOGIST',
        'ocular pharmacologist' => 'OCULAR PHARMACOLOGIST',
        'optometrists' => 'OPTOMETRIST',
        'optometrist' => 'OPTOMETRIST',
        'pharmacists' => 'PHARMACIST',
        'pharmacist' => 'PHARMACIST',
        'physical therapists' => 'PHYSICAL THERAPIST',
        'physical therapist' => 'PHYSICAL THERAPIST',
        'physicians' => 'PHYSICIAN',
        'physician' => 'PHYSICIAN',
        'professional electrical engineers' => 'PROFESSIONAL ELECTRICAL ENGINEER',
        'professional electrical engineer' => 'PROFESSIONAL ELECTRICAL ENGINEER',
        'teachers' => 'PROFESSIONAL TEACHERS',
        'professional teacher' => 'PROFESSIONAL TEACHERS',
        'professional teachers' => 'PROFESSIONAL TEACHERS',
        'psychologists' => 'PSYCHOLOGIST',
        'psychologist' => 'PSYCHOLOGIST',
        'psychometricians' => 'PSYCHOMETRICIAN',
        'psychometrician' => 'PSYCHOMETRICIAN',
        'radiologic technologists' => 'RADIOLOGIC TECHNOLOGIST',
        'radiologic technologist' => 'RADIOLOGIC TECHNOLOGIST',
        'rad tech' => 'RADIOLOGIC TECHNOLOGIST',
        'real estate appraisers' => 'REAL ESTATE APPRAISER',
        'real estate appraiser' => 'REAL ESTATE APPRAISER',
        'rael estate appraiser' => 'REAL ESTATE APPRAISER',
        'real estate brokers' => 'REAL ESTATE BROKER',
        'real estate broker' => 'REAL ESTATE BROKER',
        'rael estate broker' => 'REAL ESTATE BROKER',
        'real estate consultants' => 'REAL ESTATE CONSULTANT',
        'real estate consultant' => 'REAL ESTATE CONSULTANT',
        'rael estate consultant' => 'REAL ESTATE CONSULTANT',
        'ree' => 'REGISTERED ELECTRICAL ENGINEER',
        'registered electrical eng' => 'REGISTERED ELECTRICAL ENGINEER',
        'registered electrical engineer' => 'REGISTERED ELECTRICAL ENGINEER',
        'electrical engineer' => 'REGISTERED ELECTRICAL ENGINEER',
        'rme' => 'REGISTERED MASTER ELECTRICIAN',
        'registered master electrician' => 'REGISTERED MASTER ELECTRICIAN',
        'master electrician' => 'REGISTERED MASTER ELECTRICIAN',
        'registered master electricians' => 'REGISTERED MASTER ELECTRICIAN',
        'respiratory therapists' => 'RESPIRATORY THERAPIST',
        'respiratory therapist' => 'RESPIRATORY THERAPIST',
        'rt' => 'RESPIRATORY THERAPIST',
        'sanitary engineers' => 'SANITARY ENGINEER',
        'sanitary engineer' => 'SANITARY ENGINEER',
        'social workers' => 'SOCIAL WORKER',
        'social worker' => 'SOCIAL WORKER',
        'veterinarians' => 'VETERINARIAN',
        'veterinarian' => 'VETERINARIAN',
        'veterinary medicine' => 'VETERINARIAN',
        'veterinary' => 'VETERINARIAN',
        'x-ray technologists' => 'X-RAY TECHNOLOGIST',
        'x-ray technologist' => 'X-RAY TECHNOLOGIST',
        'x-ray tech' => 'X-RAY TECHNOLOGIST',
        'x-ray' => 'X-RAY TECHNOLOGIST',
       
    ];

    return $mapping[$input] ?? strtoupper($input); // fallback: uppercase input
}

// PDO connection for activity logging
try {
    $pdo = new PDO("mysql:host=localhost;dbname=prc_release_db", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["excel_file"])) {
    $file = $_FILES["excel_file"]["tmp_name"];
    $fileName = $_FILES["excel_file"]["name"];

    if (!file_exists($file)) {
        logActivity($pdo, $_SESSION['user_id'] ?? null, $_SESSION['full_name'] ?? 'Unknown User', 'upload_ror', "Failed to upload ROR file: {$fileName} - File not found");
        $_SESSION["error"] = "File not found.";
        header("Location: uploadData_ui.php");
        exit();
    }

    $upload_timestamp = date('Y-m-d H:i:s');

    try {
        $spreadsheet = IOFactory::load($file);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        $recordsInserted = 0;
        $inserted_ids = [];

        for ($i = 3; $i < count($rows); $i++) {
            $data = $rows[$i];

            // Skip rows where all required fields are empty or whitespace
            if (
                (!isset($data[1]) || trim($data[1]) === '') &&
                (!isset($data[2]) || trim($data[2]) === '') &&
                (!isset($data[3]) || trim($data[3]) === '')
            ) {
                continue;
            }

            $name = trim($data[1]) ?? 'N/A';
            $raw_exam = trim($data[2]) ?? 'N/A';
            $examination = normalizeExamination($raw_exam);
            $exam_date = trim($data[3]) ?? 'N/A';
            $status = 'pending';

            $stmt = $conn->prepare("INSERT INTO roravailable (name, examination, exam_date, upload_timestamp, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $examination, $exam_date, $upload_timestamp, $status);

            if ($stmt->execute()) {
                $recordsInserted++;
                $inserted_ids[] = $conn->insert_id;
            }

            $stmt->close();
        }

        if ($recordsInserted > 0) {
            logRORUpload($pdo, $_SESSION['user_id'] ?? null, $_SESSION['full_name'] ?? 'Unknown User', $fileName, $recordsInserted);
            $_SESSION["message"] = "Excel file uploaded successfully! {$recordsInserted} records processed.";
            $_SESSION["last_upload_timestamp"] = $upload_timestamp;
            $_SESSION["last_upload_ids"] = $inserted_ids;
        } else {
            logActivity($pdo, $_SESSION['user_id'] ?? null, $_SESSION['full_name'] ?? 'Unknown User', 'upload_ror', "Failed to upload ROR file: {$fileName} - No records inserted");
            $_SESSION["error"] = "No records inserted.";
        }

    } catch (Exception $e) {
        logActivity($pdo, $_SESSION['user_id'] ?? null, $_SESSION['full_name'] ?? 'Unknown User', 'upload_ror', "Failed to upload ROR file: {$fileName} - Error: " . $e->getMessage());
        $_SESSION["error"] = "Error reading Excel file: " . $e->getMessage();
    }

} else {
    logActivity($pdo, $_SESSION['user_id'] ?? null, $_SESSION['full_name'] ?? 'Unknown User', 'upload_ror', "Invalid ROR upload attempt - No file uploaded");
    $_SESSION["error"] = "No file uploaded.";
}

header("Location: uploadData_ui.php");
exit();
?>