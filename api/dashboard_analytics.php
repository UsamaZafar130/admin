<?php
header('Content-Type: application/json');

// Include database connection
// Set $_SERVER variable to ensure API detection works
$_SERVER['PHP_SELF'] = '/api/dashboard_analytics.php';
// Require login
require_once __DIR__ . '/../includes/auth_check.php';
// Capture any error output that might interfere with JSON
ob_start();
try {
    $pdo = include __DIR__ . '/../includes/db_connection.php';
} catch (Exception $e) {
    $pdo = null;
}
// Discard any error output that was captured
ob_end_clean();

// Initialize data arrays
$top10Items = [];
$monthlySales = [];

try {
    if ($pdo) {
        // Get Top 10 Items by Quantity (This Month) - same logic as top_selling.php
        $firstOfMonth = date('Y-m-01');
        $today = date('Y-m-d');
        
        $stmt = $pdo->prepare("
            SELECT 
                i.id,
                i.name,
                i.price_per_unit,
                SUM(oi.qty) as total_sold,
                SUM(oi.total) as revenue,
                AVG(oi.price_per_unit) as avg_price,
                COUNT(DISTINCT so.customer_id) as unique_customers,
                COUNT(DISTINCT oi.order_id) as order_count
            FROM order_items oi
            JOIN sales_orders so ON oi.order_id = so.id
            JOIN items i ON oi.item_id = i.id
            WHERE so.cancelled = 0 AND so.delivered = 1 
                AND so.order_date >= ? 
                AND so.order_date <= ?
            GROUP BY i.id, i.name, i.price_per_unit
            ORDER BY total_sold DESC
            LIMIT 10
        ");
        $stmt->execute([$firstOfMonth, $today]);
        $top10Items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get Monthly Sales for Last 6 Months
        $currentMonth = date('n'); // 1-12
        $currentYear = date('Y');
        
        for ($i = 5; $i >= 0; $i--) {
            $targetMonth = $currentMonth - $i;
            $targetYear = $currentYear;
            
            // Handle year rollover
            if ($targetMonth <= 0) {
                $targetMonth += 12;
                $targetYear--;
            }
            
            $monthName = date('F Y', mktime(0, 0, 0, $targetMonth, 1, $targetYear));
            $monthKey = sprintf('%04d-%02d', $targetYear, $targetMonth);
            
            // Query actual sales data for this month
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(grand_total), 0) as revenue
                FROM sales_orders 
                WHERE YEAR(order_date) = ? 
                    AND MONTH(order_date) = ? 
                    AND cancelled = 0 
                    AND delivered = 1
            ");
            $stmt->execute([$targetYear, $targetMonth]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $revenue = $result['revenue'] ?? 0;
            
            $monthlySales[] = [
                'month' => $monthKey,
                'month_name' => $monthName,
                'revenue' => (string)$revenue
            ];
        }
        
    } else {
        // Fallback data when database is not available
        // Calculate the last 6 months from current date
        $currentMonth = date('n'); // 1-12
        $currentYear = date('Y');
        
        for ($i = 5; $i >= 0; $i--) {
            $targetMonth = $currentMonth - $i;
            $targetYear = $currentYear;
            
            // Handle year rollover
            if ($targetMonth <= 0) {
                $targetMonth += 12;
                $targetYear--;
            }
            
            $monthName = date('F Y', mktime(0, 0, 0, $targetMonth, 1, $targetYear));
            $monthKey = sprintf('%04d-%02d', $targetYear, $targetMonth);
            
            $monthlySales[] = [
                'month' => $monthKey,
                'month_name' => $monthName,
                'revenue' => '0'
            ];
        }
        
        // No dummy data - empty arrays when database unavailable
        $top10Items = [];
    }
} catch (Exception $e) {
    error_log('Dashboard analytics error: ' . $e->getMessage());
    
    // Fallback data on error
    $currentMonth = date('n');
    $currentYear = date('Y');
    
    for ($i = 5; $i >= 0; $i--) {
        $targetMonth = $currentMonth - $i;
        $targetYear = $currentYear;
        
        if ($targetMonth <= 0) {
            $targetMonth += 12;
            $targetYear--;
        }
        
        $monthName = date('F Y', mktime(0, 0, 0, $targetMonth, 1, $targetYear));
        $monthKey = sprintf('%04d-%02d', $targetYear, $targetMonth);
        
        $monthlySales[] = [
            'month' => $monthKey,
            'month_name' => $monthName,
            'revenue' => '0'
        ];
    }
    
    $top10Items = [];
}

$analytics = [
    'top10Items' => $top10Items,
    'monthlySales' => $monthlySales
];

echo json_encode($analytics);
?>