<?php
/**
 * Dashboard Preferences API
 * Handles loading and saving user dashboard widget preferences
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/functions.php';

// Ensure user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Create preferences table if it doesn't exist
try {
    if ($pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_dashboard_prefs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                widget_id VARCHAR(50) NOT NULL,
                is_visible TINYINT(1) DEFAULT 1,
                sort_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_widget (user_id, widget_id)
            )
        ");
    }
} catch (Exception $e) {
    error_log('Error creating dashboard preferences table: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Load user preferences
    try {
        if (!$pdo) {
            // Return default preferences if no DB connection
            echo json_encode([
                'success' => true,
                'preferences' => [
                    'stats-cards' => ['visible' => true, 'order' => 1],
                    'business-metrics' => ['visible' => true, 'order' => 2],
                    'financial-metrics' => ['visible' => true, 'order' => 3],
                    'module-cards' => ['visible' => true, 'order' => 4],
                    'analytics-dashboard' => ['visible' => true, 'order' => 5]
                ]
            ]);
            exit;
        }

        $stmt = $pdo->prepare("SELECT widget_id, is_visible, sort_order FROM user_dashboard_prefs WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $prefs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $preferences = [];
        foreach ($prefs as $pref) {
            $preferences[$pref['widget_id']] = [
                'visible' => (bool)$pref['is_visible'],
                'order' => (int)$pref['sort_order']
            ];
        }

        // Set defaults for widgets not in preferences
        $default_widgets = [
            'stats-cards' => ['visible' => true, 'order' => 1],
            'business-metrics' => ['visible' => true, 'order' => 2],
            'financial-metrics' => ['visible' => true, 'order' => 3],
            'module-cards' => ['visible' => true, 'order' => 4],
            'analytics-dashboard' => ['visible' => true, 'order' => 5]
        ];

        foreach ($default_widgets as $widget_id => $default) {
            if (!isset($preferences[$widget_id])) {
                $preferences[$widget_id] = $default;
            }
        }

        echo json_encode(['success' => true, 'preferences' => $preferences]);

    } catch (Exception $e) {
        error_log('Error loading dashboard preferences: ' . $e->getMessage());
        echo json_encode(['error' => 'Failed to load preferences']);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save user preferences
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['preferences'])) {
            echo json_encode(['error' => 'Invalid input']);
            exit;
        }

        if (!$pdo) {
            echo json_encode(['error' => 'Database not available']);
            exit;
        }

        $pdo->beginTransaction();

        foreach ($input['preferences'] as $widget_id => $pref) {
            $stmt = $pdo->prepare("
                INSERT INTO user_dashboard_prefs (user_id, widget_id, is_visible, sort_order) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                is_visible = VALUES(is_visible), 
                sort_order = VALUES(sort_order),
                updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                $user_id,
                $widget_id,
                $pref['visible'] ? 1 : 0,
                $pref['order']
            ]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        if ($pdo) {
            $pdo->rollBack();
        }
        error_log('Error saving dashboard preferences: ' . $e->getMessage());
        echo json_encode(['error' => 'Failed to save preferences']);
    }

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>