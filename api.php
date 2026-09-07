<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database Credentials
$host = 'localhost';
$db   = 'profile_cost_calculator';
$user = 'root';
$pass = 'root';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Parse Request Body
$input = json_decode(file_get_contents('php://input'), true) ?? $_REQUEST;
$action = $input['action'] ?? '';

// Helper to generate 32-char hex GUID
function generateGuid() {
    return bin2hex(random_bytes(16));
}

switch ($action) {

    // --- READ / FETCH ALL ---
    case 'fetch_all':
        $normalStmt = $pdo->query("SELECT * FROM normal_rates ORDER BY cost_type ASC, item ASC");
        $thickStmt  = $pdo->query("SELECT * FROM thickness_rates ORDER BY seq ASC, thickness_mm ASC");
        
        echo json_encode([
            'success' => true,
            'normal_rates' => $normalStmt->fetchAll(),
            'thickness_rates' => $thickStmt->fetchAll()
        ]);
        break;

    // --- SAVE / EDIT NORMAL RATE ---
    case 'save_normal_rate':
        $guid      = trim($input['guid'] ?? '');
        $cost_type = trim($input['cost_type'] ?? '');
        $item      = trim($input['item'] ?? '');
        $rate      = floatval($input['rate'] ?? 0);
        $is_active = intval($input['is_active'] ?? 1);
        $updated_at   = NOW();

        if (empty($cost_type) || empty($item)) {
            echo json_encode(['success' => false, 'message' => 'Cost Type and Item Name are required.']);
            exit();
        }

        if (!empty($guid)) {
            // Update
            $stmt = $pdo->prepare("UPDATE normal_rates SET cost_type = ?, item = ?, rate = ?, is_active = ?, updated_at = NOW() WHERE guid = ?");
            $stmt->execute([$cost_type, $item, $rate, $is_active, $guid]);
        } else {
            // Insert
            $guid = generateGuid();
            $stmt = $pdo->prepare("INSERT INTO normal_rates (guid, cost_type, item, rate, is_active, updated_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$guid, $cost_type, $item, $rate, $is_active]);
        }

        echo json_encode(['success' => true, 'message' => 'Normal rate saved successfully.']);
        break;

    // --- SAVE / EDIT THICKNESS RATE ---
    case 'save_thickness_rate':
        $guid         = trim($input['guid'] ?? '');
        $thickness_mm = intval($input['thickness_mm'] ?? 0);
        $seq          = intval($input['seq'] ?? 1);
        $item         = trim($input['item'] ?? '');
        $flat_rate    = floatval($input['flat_rate'] ?? 0);
        $profile_rate = floatval($input['profile_rate'] ?? 0);
        $is_active    = intval($input['is_active'] ?? 1);
        $updated_at   = NOW();

        if ($thickness_mm <= 0 || empty($item)) {
            echo json_encode(['success' => false, 'message' => 'Valid Thickness (mm) and Item Name are required.']);
            exit();
        }

        if (!empty($guid)) {
            // Update
            $stmt = $pdo->prepare("UPDATE thickness_rates SET seq = ?, thickness_mm = ?, item = ?, flat_rate = ?, profile_rate = ?, is_active = ?, updated_at = NOW() WHERE guid = ?");
            $stmt->execute([$seq, $thickness_mm, $item, $flat_rate, $profile_rate, $is_active, $guid]);
        } else {
            // Insert
            $guid = generateGuid();
            $stmt = $pdo->prepare("INSERT INTO thickness_rates (guid, seq, thickness_mm, item, flat_rate, profile_rate, is_active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$guid, $seq, $thickness_mm, $item, $flat_rate, $profile_rate, $is_active]);
        }

        echo json_encode(['success' => true, 'message' => 'Thickness rate saved successfully.']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
        break;
}
?>
