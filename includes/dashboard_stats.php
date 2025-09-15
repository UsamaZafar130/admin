<?php
// Dashboard Statistics Helper Functions

/**
 * Get entity counts for dashboard statistics
 */
function getDashboardStats($pdo) {
    $stats = [];
    
    try {
        // Get total items count
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM items");
        $stmt->execute();
        $stats['items'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $stats['items'] = 0;
    }
    
    try {
        // Get total customers count
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM customers");
        $stmt->execute();
        $stats['customers'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $stats['customers'] = 0;
    }
    
    try {
        // Get total orders count
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sales_orders");
        $stmt->execute();
        $stats['orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $stats['orders'] = 0;
    }
    
    try {
        // Get total vendors count
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM vendors");
        $stmt->execute();
        $stats['vendors'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $stats['vendors'] = 0;
    }
    
    try {
        // Get total batches count
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM shipping_batches");
        $stmt->execute();
        $stats['batches'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $stats['batches'] = 0;
    }
    
    try {
        // Get total purchases count
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM purchases");
        $stmt->execute();
        $stats['purchases'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $stats['purchases'] = 0;
    }
    
    try {
        // Get total expenses count
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM expenses");
        $stmt->execute();
        $stats['expenses'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $stats['expenses'] = 0;
    }
    
    try {
        // Get total users count
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users");
        $stmt->execute();
        $stats['users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $stats['users'] = 0;
    }
    
    return $stats;
}

/**
 * Get recent activity summary and business metrics
 */
function getRecentActivity($pdo) {
    $activity = [];
    
    try {
        // Get orders created today
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sales_orders WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $activity['orders_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $activity['orders_today'] = 0;
    }
    
    try {
        // Get pending orders (undelivered and uncancelled)
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sales_orders WHERE delivered = 0 AND cancelled = 0");
        $stmt->execute();
        $activity['pending_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $activity['pending_orders'] = 0;
    }
    
    try {
        // Get current batch total (sum of grand totals for current batch orders)
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(so.grand_total), 0) as total
            FROM shipping_batches sb
            JOIN shipping_batch_orders sbo ON sb.id = sbo.batch_id
            JOIN sales_orders so ON sbo.order_id = so.id
            WHERE sb.batch_date >= CURDATE() 
            AND so.delivered = 0 AND so.cancelled = 0
            ORDER BY sb.batch_date ASC, sb.id ASC 
            LIMIT 1
        ");
        $stmt->execute();
        $activity['current_batch_total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (Exception $e) {
        $activity['current_batch_total'] = 0;
    }
    
    try {
        // Get total receivables (unpaid delivered orders)
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(grand_total), 0) as total FROM sales_orders WHERE paid = 0 AND delivered = 1");
        $stmt->execute();
        $activity['total_receivables'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (Exception $e) {
        $activity['total_receivables'] = 0;
    }
    
    try {
        // Get total payables from purchases (unpaid purchases)
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(p.amount - COALESCE(paid.amount, 0)), 0) as total
            FROM purchases p
            LEFT JOIN (
                SELECT purchase_id, SUM(amount) as amount 
                FROM purchase_payments 
                WHERE deleted_at IS NULL 
                GROUP BY purchase_id
            ) paid ON p.id = paid.purchase_id
            WHERE p.deleted_at IS NULL
        ");
        $stmt->execute();
        $purchase_payables = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (Exception $e) {
        $purchase_payables = 0;
    }
    
    try {
        // Get total payables from expenses (unpaid expenses)
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(e.amount - COALESCE(paid.amount, 0)), 0) as total
            FROM expenses e
            LEFT JOIN (
                SELECT expense_id, SUM(amount) as amount 
                FROM expense_payments 
                WHERE deleted_at IS NULL 
                GROUP BY expense_id
            ) paid ON e.id = paid.expense_id
            WHERE e.deleted_at IS NULL
        ");
        $stmt->execute();
        $expense_payables = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (Exception $e) {
        $expense_payables = 0;
    }
    
    $activity['total_payables'] = $purchase_payables + $expense_payables;
    
    return $activity;
}

/**
 * Get stock requirements for current batch (top items)
 */
function getCurrentBatchStockRequirements($pdo, $limit = 3) {
    try {
        // Get current batch
        $stmt = $pdo->prepare("SELECT id FROM shipping_batches WHERE batch_date >= CURDATE() ORDER BY batch_date ASC, id ASC LIMIT 1");
        $stmt->execute();
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$batch) {
            return ['items' => [], 'batch_id' => null];
        }
        
        // Get stock requirements for current batch
        $stmt = $pdo->prepare("
            SELECT 
                i.name,
                SUM(oi.qty) as required_qty,
                COALESCE(stock.manufactured, 0) as manufactured_qty,
                GREATEST(0, SUM(oi.qty) - COALESCE(stock.manufactured, 0)) as shortfall
            FROM shipping_batch_orders sbo
            JOIN sales_orders so ON sbo.order_id = so.id
            JOIN order_items oi ON so.id = oi.order_id
            JOIN items i ON oi.item_id = i.id
            LEFT JOIN (
                SELECT item_id, SUM(qty) as manufactured
                FROM inventory_ledger
                GROUP BY item_id
            ) stock ON i.id = stock.item_id
            WHERE sbo.batch_id = ? 
            AND so.delivered = 0 AND so.cancelled = 0
            GROUP BY i.id, i.name, stock.manufactured
            HAVING shortfall > 0
            ORDER BY shortfall DESC
            LIMIT ?
        ");
        $stmt->execute([$batch['id'], $limit]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return ['items' => $items, 'batch_id' => $batch['id']];
        
    } catch (Exception $e) {
        return ['items' => [], 'batch_id' => null];
    }
}
?>