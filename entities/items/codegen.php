<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

header('Content-Type: application/json');
$response = ['success' => false];

try {
    $name = trim($_GET['name'] ?? '');
    if (!$name) throw new Exception("No name specified.");

    // Generate base code as in JS
    $words = preg_split('/[^a-zA-Z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY);
    $filteredWords = count($words) > 1 ? array_filter($words, function($w){ return strlen($w) > 2; }) : $words;
    if (empty($filteredWords)) $filteredWords = $words;

    $base = '';
    foreach ($filteredWords as $w) $base .= strtoupper($w[0]);

    // Fetch all codes with this base pattern from DB (case-insensitive)
    $stmt = $pdo->prepare("SELECT code FROM items WHERE UPPER(code) LIKE UPPER(:pattern) AND deleted_at IS NULL");
    $pattern = $base . '%';
    $stmt->execute([':pattern' => $pattern]);
    $codes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Find next available code (CSR, CSR2, CSR3, etc)
    $variantCount = 1;
    if ($codes) {
        foreach ($codes as $code) {
            // Match exact base pattern: base or base + number, nothing else
            if (preg_match('/^' . preg_quote($base, '/') . '(\d*)$/i', $code, $m)) {
                $n = isset($m[1]) && $m[1] !== '' ? intval($m[1]) : 1;
                if ($n >= $variantCount) $variantCount = $n + 1;
            }
        }
    }
    
    // Generate final code: if only one variant needed, use base alone, otherwise add number
    $finalCode = ($variantCount === 1) ? $base : $base . $variantCount;
    $response = [
        'success' => true,
        'code' => substr($finalCode, 0, 8)
    ];
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
exit;