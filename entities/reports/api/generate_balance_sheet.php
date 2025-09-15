<?php
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/functions.php';

$pdo = $pdo ?? require __DIR__ . '/../../../includes/db_connection.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get raw material stock value from POST data
    $raw_material_stock = isset($_POST['raw_material_stock']) ? floatval($_POST['raw_material_stock']) : 0.0;
    
    // Generate balance sheet using centralized function
    $balance_sheet_data = get_balance_sheet($pdo, $raw_material_stock);
    
    // Return successful response
    echo json_encode([
        'success' => true,
        'data' => $balance_sheet_data,
        'message' => 'Balance sheet generated successfully'
    ]);
    
} catch (Exception $e) {
    error_log('Balance sheet generation error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to generate balance sheet: ' . $e->getMessage()
    ]);
}
?>