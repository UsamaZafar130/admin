<?php
/**
 * FrozoFun Admin – Common Functions
 * For modular business/resource management app
 * 
 * Contains utility functions for database, UI, auth, permissions, formatting, AJAX, etc.
 * Project by UsamaZafar130
 */

if (!function_exists('redirect')) {
    /**
     * Redirect to a given URL and exit.
     */
    function redirect($url) {
        header('Location: ' . $url);
        exit;
    }
}

/**
 * Sanitize output for HTML
 */
function h($string) {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Format order/invoice number with FF prefix and zero padding
 */
function format_order_number($order_id) {
    return 'FF' . str_pad($order_id, 6, '0', STR_PAD_LEFT);
}

/**
 * Format customer contact for WhatsApp link
 */
function format_customer_contact($customer_name, $contact_number) {
    $whatsapp_link = '';
    if (!empty($contact_number)) {
        // Clean contact number (remove non-digits)
        $clean_number = preg_replace('/[^\d]/', '', $contact_number);
        // Add country code if not present (assuming Pakistan +92)
        if (strlen($clean_number) == 11 && substr($clean_number, 0, 1) == '0') {
            $clean_number = '92' . substr($clean_number, 1);
        } elseif (strlen($clean_number) == 10) {
            $clean_number = '92' . $clean_number;
        }
        $whatsapp_link = "https://wa.me/{$clean_number}";
    }
    
    $output = '<div class="customer-info">';
    $output .= '<div class="customer-name">' . h($customer_name) . '</div>';
    if (!empty($contact_number)) {
        $output .= '<div class="customer-contact">';
        $output .= '<a href="' . $whatsapp_link . '" target="_blank" class="whatsapp-link">';
        $output .= '<i class="fab fa-whatsapp"></i> ' . h($contact_number);
        $output .= '</a></div>';
    }
    $output .= '</div>';
    
    return $output;
}

/**
 * Format order number with batch linkage information
 */
function format_order_with_batch($order_id, $batch_id, $batch_name) {
    $order_number = format_order_number($order_id);
    
    $output = '<div class="order-info">';
    $output .= '<div class="order-number">' . h($order_number) . '</div>';
    
    if (!empty($batch_id) && !empty($batch_name)) {
        $output .= '<div class="batch-info">';
        $output .= '<a href="/entities/batches/batch.php?id=' . intval($batch_id) . '" class="batch-link">';
        $output .= '<i class="fa fa-layer-group"></i> ' . h($batch_name);
        $output .= '</a></div>';
    } else {
        $output .= '<div class="batch-info">';
        $output .= '<a href="/entities/batches/list.php" class="no-batch-link">';
        $output .= '<i class="fa fa-plus-circle"></i> No Batch Linked';
        $output .= '</a></div>';
    }
    $output .= '</div>';
    
    return $output;
}

/**
 * Format date/time for display in user timezone
 */
function format_datetime($datetime, $timezone = 'UTC', $format = 'Y-m-d H:i') {
    if (!$datetime) return '';
    try {
        $dt = new DateTime($datetime, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone($timezone));
        return $dt->format($format);
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * Auto-crop and resize image (JPEG/PNG/GIF) to a square (center) of a given size.
 * Returns the final image type (one of: jpg, png, gif) or false on failure.
 * @param string $srcPath Path to source uploaded file
 * @param string $destPath Path to save processed image
 * @param int $size Square size in px (default 300)
 * @return string|false
 */
function process_item_image($srcPath, $destPath, $size = 300) {
    $info = @getimagesize($srcPath);
    if (!$info) return false;
    [$w, $h, $type] = $info;

    switch ($type) {
        case IMAGETYPE_JPEG: $srcImg = @imagecreatefromjpeg($srcPath); $ext = 'jpg'; break;
        case IMAGETYPE_PNG:  $srcImg = @imagecreatefrompng($srcPath);  $ext = 'png'; break;
        case IMAGETYPE_GIF:  $srcImg = @imagecreatefromgif($srcPath);  $ext = 'gif'; break;
        default: return false;
    }
    if (!$srcImg) return false;

    $min = min($w, $h);
    $srcX = ($w - $min) / 2;
    $srcY = ($h - $min) / 2;

    $dstImg = imagecreatetruecolor($size, $size);

    // Preserve transparency for PNG/GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);
        $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
        imagefilledrectangle($dstImg, 0, 0, $size, $size, $transparent);
    }

    imagecopyresampled($dstImg, $srcImg, 0, 0, $srcX, $srcY, $size, $size, $min, $min);

    $success = false;
    switch ($type) {
        case IMAGETYPE_JPEG: $success = imagejpeg($dstImg, $destPath, 90); break;
        case IMAGETYPE_PNG:  $success = imagepng($dstImg, $destPath, 6); break;
        case IMAGETYPE_GIF:  $success = imagegif($dstImg, $destPath); break;
    }
    imagedestroy($srcImg);
    imagedestroy($dstImg);
    return $success ? $ext : false;
}

/**
 * Process meal image uploads with proper sizing
 * @param string $srcPath Source image path
 * @param string $destPath Destination image path
 * @param int $width Target width
 * @param int $height Target height (optional, defaults to width for square)
 * @return string|false Extension on success, false on failure
 */
function process_meal_image($srcPath, $destPath, $width = 300, $height = null) {
    $info = @getimagesize($srcPath);
    if (!$info) return false;
    [$w, $h, $type] = $info;

    switch ($type) {
        case IMAGETYPE_JPEG: $srcImg = @imagecreatefromjpeg($srcPath); $ext = 'jpg'; break;
        case IMAGETYPE_PNG:  $srcImg = @imagecreatefrompng($srcPath);  $ext = 'png'; break;
        case IMAGETYPE_GIF:  $srcImg = @imagecreatefromgif($srcPath);  $ext = 'gif'; break;
        default: return false;
    }
    if (!$srcImg) return false;

    // Use square if height not specified
    if ($height === null) $height = $width;

    // Calculate crop dimensions to maintain aspect ratio
    $srcRatio = $w / $h;
    $destRatio = $width / $height;
    
    if ($srcRatio > $destRatio) {
        // Source is wider, crop width
        $cropH = $h;
        $cropW = (int)($h * $destRatio);
        $srcX = (int)(($w - $cropW) / 2);
        $srcY = 0;
    } else {
        // Source is taller, crop height
        $cropW = $w;
        $cropH = (int)($w / $destRatio);
        $srcX = 0;
        $srcY = (int)(($h - $cropH) / 2);
    }

    $dstImg = imagecreatetruecolor($width, $height);

    // Preserve transparency for PNG/GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);
        $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
        imagefilledrectangle($dstImg, 0, 0, $width, $height, $transparent);
    }

    imagecopyresampled($dstImg, $srcImg, 0, 0, $srcX, $srcY, $width, $height, $cropW, $cropH);

    $success = false;
    switch ($type) {
        case IMAGETYPE_JPEG: $success = imagejpeg($dstImg, $destPath, 90); break;
        case IMAGETYPE_PNG:  $success = imagepng($dstImg, $destPath, 6); break;
        case IMAGETYPE_GIF:  $success = imagegif($dstImg, $destPath); break;
    }
    imagedestroy($srcImg);
    imagedestroy($dstImg);
    return $success ? $ext : false;
}
/**
 * Calculate the outstanding balance for a customer.
 * Excludes cancelled orders.
 */
function get_customer_balance($customer_id, $pdo) {
    // Total owed (orders, exclude cancelled)
    $orders = $pdo->prepare("SELECT SUM(grand_total) FROM sales_orders WHERE customer_id=? AND cancelled=0");
    $orders->execute([$customer_id]);
    $owed = $orders->fetchColumn() ?: 0;

    // Payments through customer_payments
    $payments = $pdo->prepare("SELECT SUM(amount) FROM customer_payments WHERE customer_id=?");
    $payments->execute([$customer_id]);
    $paid_customer = $payments->fetchColumn() ?: 0;

    // Payments through order_payments (join to sales_orders to confirm customer_id and not cancelled)
    $order_payments = $pdo->prepare(
        "SELECT SUM(op.amount)
         FROM order_payments op
         INNER JOIN sales_orders so ON op.order_id = so.id
         WHERE so.customer_id = ? AND so.cancelled = 0"
    );
    $order_payments->execute([$customer_id]);
    $paid_order = $order_payments->fetchColumn() ?: 0;

    // Final balance
    return round($owed - $paid_customer - $paid_order, 2);
}
/**
 * Get current user's timezone, default to UTC
 */
function get_user_timezone() {
    // You may store timezone in session or user preferences
    return $_SESSION['timezone'] ?? 'UTC';
}

/**
 * Check user role (for permissions)
 * @param array|string $roles Allowed role(s)
 * @return bool
 */
function user_has_role($roles) {
    if (!isset($_SESSION['role'])) return false;
    if (is_array($roles)) {
        return in_array($_SESSION['role'], $roles);
    }
    return $_SESSION['role'] === $roles;
}

/**
 * Check permission for a page/section/field (stub for expansion)
 * @param string $permission
 * @return bool
 */
function can($permission) {
    // TODO: Implement field-level permissions if needed
    // For now, allow all for admin
    if (user_has_role('admin')) return true;
    // Expand with actual permission logic
    return false;
}

/**
 * Generate CSRF token and store in session
 * @return string
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token from form/input
 * @param string $token
 * @return bool
 */
function validate_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Return the current logged-in user id, or null
 */
function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Return the current logged-in username, or null
 */
function current_username() {
    return $_SESSION['username'] ?? null;
}

/**
 * Flash message system (for forms, actions, errors)
 */
function set_flash($msg, $type = 'info') {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function get_flash() {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Simple AJAX response helper (JSON)
 */
function ajax_response($data = [], $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Normalize contact number to international format (e.g. +923223300130)
 */
function normalize_contact($contact) {
    $contact = trim($contact);
    $contact = preg_replace('/[^\d\+]/', '', $contact);

    // Convert 0xxxxxxxxxx to +92xxxxxxxxxx
    if (preg_match('/^0(\d{10})$/', $contact, $m)) {
        $contact = '+92' . $m[1];
    }
    // Convert 0092xxxxxxxxxx to +92xxxxxxxxxx
    if (preg_match('/^0092(\d{10})$/', $contact, $m)) {
        $contact = '+92' . $m[1];
    }
    // Ensure single plus at start
    if (preg_match('/^(\+?\d{2,4})(\d+)$/', $contact, $m)) {
        $contact = (strpos($m[1], '+') === 0 ? $m[1] : ('+' . $m[1])) . $m[2];
    }
    // Remove spaces
    $contact = str_replace(' ', '', $contact);
    return $contact;
}

/**
 * Normalize contact for duplicate checking (e.g. 3223300130 for PK, 971551234567 for others)
 */
function get_contact_normalized($contact) {
    // For +92 numbers, return last 10 digits
    if (preg_match('/^\+92(\d{10})$/', $contact, $m)) {
        return $m[1];
    }
    // For other country codes, remove plus and concatenate code+number
    if (preg_match('/^\+?(\d{2,4})(\d+)$/', $contact, $m)) {
        if ($m[1] !== '92') {
            return $m[1] . $m[2];
        }
    }
    // Fallback: return numeric only
    return preg_replace('/[^\d]/', '', $contact);
}

/**
 * Get available CSS themes from the themes directory
 * @return array
 */
function get_available_themes() {
    // Scan themes folder
    $theme_dir = __DIR__ . '/../assets/css/themes/';
    $themes = [];
    foreach (glob($theme_dir . '*.css') as $file) {
        $themes[] = basename($file, '.css');
    }
    return $themes;
}

/**
 * Load user preferences (stub)
 * @param int $user_id
 * @return array
 */
function load_user_prefs($user_id) {
    // TODO: Load prefs from DB or file
    return [
        'theme' => 'default',
        'timezone' => 'UTC'
    ];
}

/**
 * Save user preferences (stub)
 * @param int $user_id
 * @param array $prefs
 * @return bool
 */
function save_user_prefs($user_id, $prefs) {
    // TODO: Save prefs to DB or file
    return true;
}

/**
 * Persistent table/list state (stub)
 */
function save_table_state($table, $state) {
    // TODO: Save state (sort/filter/scroll) for user/table in DB or session
}
function load_table_state($table) {
    // TODO: Load state for user/table
    return [];
}

/**
 * Generate a report icon/link for entity lists
 */
function report_icon($href = "#") {
    return "<a href='" . h($href) . "' class='report-icon' title='View Report'><span class='icon'>&#128200;</span></a>";
}

/**
 * Check if the request is AJAX
 */
function is_ajax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Calculate vendor outstanding and surplus, using proper advances/surplus logic.
 *
 * Returns an array:
 *   [
 *     'outstanding' => (float), total outstanding credit,
 *     'surplus' => (float), total surplus (advance),
 *   ]
 */
function get_vendor_balance_details($vendor_id, $pdo) {
    // Outstanding: sum of unpaid purchases and expenses (credit only)
    $outstanding = 0;

    // --- Purchases (credit only) ---
    $stmt = $pdo->prepare("
        SELECT id, amount, (SELECT COALESCE(SUM(amount),0) FROM purchase_payments WHERE purchase_id = p.id AND deleted_at IS NULL) AS paid
        FROM purchases p
        WHERE vendor_id=? AND (type='credit' OR type IS NULL) AND deleted_at IS NULL
    ");
    $stmt->execute([$vendor_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $outstanding += max(0, floatval($p['amount']) - floatval($p['paid']));
    }

    // --- Expenses (credit only, if table exists) ---
    try {
        $has_expenses = $pdo->query("SHOW TABLES LIKE 'expenses'")->rowCount() > 0;
    } catch (Exception $e) {
        $has_expenses = false;
    }
    if ($has_expenses) {
        $stmt = $pdo->prepare("
            SELECT id, amount, (SELECT COALESCE(SUM(amount),0) FROM expense_payments WHERE expense_id = e.id AND deleted_at IS NULL) AS paid
            FROM expenses e
            WHERE vendor_id=? AND (type='credit' OR type IS NULL) AND deleted_at IS NULL
        ");
        $stmt->execute([$vendor_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $e) {
            $outstanding += max(0, floatval($e['amount']) - floatval($e['paid']));
        }
    }

    // --- Surplus (sum of vendor_advances.applied=0) ---
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS surplus FROM vendor_advances WHERE vendor_id=? AND applied=0");
    $stmt->execute([$vendor_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $surplus = $row ? floatval($row['surplus']) : 0;

    return [
        'outstanding' => round($outstanding, 2),
        'surplus' => round($surplus, 2)
    ];
}
/**
* Get order payment status: 'Paid', 'Partial', 'Unpaid'
*/
function get_order_payment_status($order, $pdo) {
    $paid = isset($order['paid']) ? $order['paid'] : 0;
    $grand_total = isset($order['grand_total']) ? $order['grand_total'] : 0;
    if ($order['status'] === 'cancelled') return 'Cancelled';
    if ($order['status'] === 'delivered' && $paid >= $grand_total) return 'Paid';
    if ($paid >= $grand_total) return 'Paid';
    if ($paid > 0) return 'Partial';
    return 'Unpaid';
}

/**
 * Get order's outstanding (unpaid) amount
 */
function get_order_outstanding($order, $pdo) {
    $paid = isset($order['paid']) ? $order['paid'] : 0;
    $grand_total = isset($order['grand_total']) ? $order['grand_total'] : 0;
    return max(0, $grand_total - $paid);
}

/**
 * Universal dropdown with select2 and autofocus for all entities
 */
function render_select2_dropdown($name, $options, $selected = '', $attrs = '') {
    $html = "<select name='".h($name)."' id='".h($name)."' class='select2' $attrs>";
    $html .= "<option value=''>Select...</option>";
    foreach ($options as $opt) {
        $val = is_array($opt) ? $opt['id'] : $opt;
        $label = is_array($opt) ? $opt['name'] : $opt;
        $sel = ($val == $selected) ? ' selected' : '';
        $html .= "<option value='".h($val)."'$sel>".h($label)."</option>";
    }
    $html .= "</select>";
    return $html;
}

/**
 * Update the status of a shipping batch as 1 (delivered) if all orders are delivered, else 0 (pending).
 * Also updates 'updated_at'.
 * @param PDO $pdo
 * @param int $batch_id
 */
function updateBatchStatus($pdo, $batch_id) {
    // Count all orders in the batch
    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM shipping_batch_orders WHERE batch_id = ?");
    $totalStmt->execute([$batch_id]);
    $total = (int)$totalStmt->fetchColumn();

    // Count delivered orders
    $deliveredStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM shipping_batch_orders sbo
        JOIN sales_orders so ON sbo.order_id = so.id
        WHERE sbo.batch_id = ? AND so.delivered = 1
    ");
    $deliveredStmt->execute([$batch_id]);
    $delivered = (int)$deliveredStmt->fetchColumn();

    // Determine status: 1 = delivered (all), 0 = pending/in progress
    $status = ($total > 0 && $delivered === $total) ? 1 : 0;

    $updateStmt = $pdo->prepare("UPDATE shipping_batches SET status=?, updated_at=NOW() WHERE id=?");
    $updateStmt->execute([$status, $batch_id]);
}

/**
 * Format monetary amount with Rs. currency prefix for display
 * 
 * Usage:
 * - format_currency(1234.56) returns "Rs. 1,234.56"
 * - format_currency(1234.56, false) returns "Rs. 1234.56" (no thousand separator)
 * - format_currency(0) returns "Rs. 0.00"
 * 
 * Use this function consistently throughout the application for all monetary displays
 * including invoices, orders, balances, reports, WhatsApp messages, etc.
 * 
 * @param float|int|string $amount The monetary amount to format
 * @param bool $thousands_separator Whether to include thousand separators (default: true)
 * @param int $decimals Number of decimal places (default: 2)
 * @return string Formatted amount with Rs. prefix
 */
function format_currency($amount, $thousands_separator = true, $decimals = 2) {
    $formatted_amount = number_format((float)$amount, $decimals, '.', $thousands_separator ? ',' : '');
    return 'Rs. ' . $formatted_amount;
}

/**
 * Format monetary amount in words with "Rupees" currency unit
 * 
 * Usage:
 * - format_currency_words(1234.56) returns amount in words ending with "Rupees"
 * 
 * This function is for amount-in-words display where the currency should be "Rupees"
 * instead of the abbreviated "Rs." used in numeric displays.
 * 
 * @param float|int|string $amount The monetary amount to convert to words
 * @return string Amount in words with "Rupees" unit
 */
function format_currency_words($amount) {
    // Basic implementation - can be enhanced with a full number-to-words converter
    $formatted_number = number_format((float)$amount, 2);
    return $formatted_number . ' Rupees';
}

// ============================================================================
// CENTRALIZED FINANCIAL CALCULATION FUNCTIONS
// ============================================================================

/**
 * Calculate revenue as the sum of grand_total from delivered sales orders within a date range.
 * Uses delivered_at as the delivery date for filtering.
 * 
 * @param PDO $pdo Database connection
 * @param string $start_date Start date (YYYY-MM-DD format)
 * @param string $end_date End date (YYYY-MM-DD format)
 * @return float Total revenue from delivered orders
 */
function calculate_revenue($pdo, $start_date, $end_date) {
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(grand_total), 0) as revenue
            FROM sales_orders 
            WHERE delivered = 1 
            AND delivered_at >= ? 
            AND delivered_at <= ?
        ");
        $stmt->execute([$start_date, $end_date . ' 23:59:59']);
        return (float)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log('Error calculating revenue: ' . $e->getMessage());
        return 0.0;
    }
}

/**
 * Calculate total paid purchases amount within a date range.
 * Uses paid_at from purchase_payments table for filtering as only paid purchases should be recorded.
 * 
 * @param PDO $pdo Database connection
 * @param string $start_date Start date (YYYY-MM-DD format)
 * @param string $end_date End date (YYYY-MM-DD format)
 * @return float Total paid purchases amount
 */
function calculate_purchases($pdo, $start_date, $end_date) {
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as purchases
            FROM purchase_payments 
            WHERE deleted_at IS NULL
            AND paid_at >= ? 
            AND paid_at <= ?
        ");
        $stmt->execute([$start_date, $end_date . ' 23:59:59']);
        return (float)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log('Error calculating paid purchases: ' . $e->getMessage());
        return 0.0;
    }
}

/**
 * Calculate excess stock value as: (price_per_unit * excess_qty) for items where manufactured > required.
 * Uses the same logic as the working get_excess_stock action in inventory/actions.php.
 * 
 * @param PDO $pdo Database connection
 * @return float Excess stock value
 */
function calculate_excess_stock_value($pdo) {
    try {
        // Get all undelivered, not cancelled orders (same as working inventory action)
        $orders = $pdo->query("SELECT id FROM sales_orders WHERE delivered=0 AND cancelled=0")->fetchAll(PDO::FETCH_COLUMN);
        $order_ids = $orders ?: [];
        $required_by_item = [];
        
        // Get total required for each item (only for items in current orders)
        if ($order_ids) {
            $in = str_repeat('?,', count($order_ids) - 1) . '?';
            $sql_req = "SELECT oi.item_id, SUM(oi.qty) AS required
                        FROM order_items oi
                        WHERE oi.order_id IN ($in)
                        GROUP BY oi.item_id";
            $stmt_req = $pdo->prepare($sql_req);
            $stmt_req->execute($order_ids);
            foreach ($stmt_req->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $required_by_item[$row['item_id']] = (int)$row['required'];
            }
        }
        
        // Get manufactured for all items (same as working inventory action - just SUM(qty))
        $sql_manuf = "SELECT item_id, SUM(qty) AS manufactured FROM inventory_ledger GROUP BY item_id";
        $stmt_manuf = $pdo->query($sql_manuf);
        $manufactured_by_item = [];
        foreach ($stmt_manuf->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $manufactured_by_item[$row['item_id']] = (int)$row['manufactured'];
        }
        
        // Get item prices
        $sql_items = "SELECT id, price_per_unit FROM items WHERE deleted_at IS NULL";
        $stmt_items = $pdo->query($sql_items);
        $prices_by_item = [];
        foreach ($stmt_items->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $prices_by_item[$row['id']] = (float)$row['price_per_unit'];
        }
        
        // Calculate excess stock value: sum of (price_per_unit * excess_qty) for items with excess
        $total_excess_value = 0.0;
        foreach ($manufactured_by_item as $item_id => $manufactured) {
            $required = $required_by_item[$item_id] ?? 0;
            $excess = $manufactured - $required;
            if ($excess > 0 && isset($prices_by_item[$item_id])) {
                $total_excess_value += $prices_by_item[$item_id] * $excess;
            }
        }
        
        return $total_excess_value;
    } catch (Exception $e) {
        error_log('Error calculating excess stock value: ' . $e->getMessage());
        return 0.0;
    }
}

/**
 * Calculate Cost of Goods Sold (COGS) as: Purchases - Raw Material Stock.
 * 
 * @param PDO $pdo Database connection
 * @param string $start_date Start date (YYYY-MM-DD format)
 * @param string $end_date End date (YYYY-MM-DD format)
 * @param float $raw_material_stock User-provided raw material stock value
 * @return float COGS amount
 */
function calculate_cogs($pdo, $start_date, $end_date, $raw_material_stock) {
    $purchases = calculate_purchases($pdo, $start_date, $end_date);
    return max(0, $purchases - (float)$raw_material_stock);
}

/**
 * Calculate Gross Profit as: Revenue - COGS + 45% of Excess Stock Value.
 * 
 * @param PDO $pdo Database connection
 * @param string $start_date Start date (YYYY-MM-DD format)
 * @param string $end_date End date (YYYY-MM-DD format)
 * @param float $raw_material_stock User-provided raw material stock value
 * @return array Associative array with revenue, cogs, excess_stock_value, and gross_profit
 */
function calculate_gross_profit($pdo, $start_date, $end_date, $raw_material_stock) {
    $revenue = calculate_revenue($pdo, $start_date, $end_date);
    $cogs = calculate_cogs($pdo, $start_date, $end_date, $raw_material_stock);
    $excess_stock_value = calculate_excess_stock_value($pdo);
    
    // Gross Profit = Revenue - COGS + 45% of Excess Stock Value
    $gross_profit = $revenue - $cogs + ($excess_stock_value * 0.45);
    
    return [
        'revenue' => $revenue,
        'cogs' => $cogs,
        'excess_stock_value' => $excess_stock_value,
        'excess_stock_45_percent' => $excess_stock_value * 0.45,
        'gross_profit' => $gross_profit
    ];
}

/**
 * Calculate total paid operating expenses within a date range.
 * Uses paid_at from expense_payments table for filtering as only paid expenses should be recorded.
 * 
 * @param PDO $pdo Database connection
 * @param string $start_date Start date (YYYY-MM-DD format)
 * @param string $end_date End date (YYYY-MM-DD format)
 * @return float Total paid operating expenses
 */
function calculate_operating_expenses($pdo, $start_date, $end_date) {
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as expenses
            FROM expense_payments 
            WHERE deleted_at IS NULL
            AND paid_at >= ? 
            AND paid_at <= ?
        ");
        $stmt->execute([$start_date, $end_date . ' 23:59:59']);
        return (float)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log('Error calculating paid operating expenses: ' . $e->getMessage());
        return 0.0;
    }
}

/**
 * Calculate Net Profit as: Gross Profit - Operating Expenses.
 * 
 * @param PDO $pdo Database connection
 * @param string $start_date Start date (YYYY-MM-DD format)
 * @param string $end_date End date (YYYY-MM-DD format)
 * @param float $raw_material_stock User-provided raw material stock value
 * @return array Complete financial calculation breakdown
 */
function calculate_net_profit($pdo, $start_date, $end_date, $raw_material_stock) {
    $gross_profit_data = calculate_gross_profit($pdo, $start_date, $end_date, $raw_material_stock);
    $operating_expenses = calculate_operating_expenses($pdo, $start_date, $end_date);
    $purchases = calculate_purchases($pdo, $start_date, $end_date);
    
    // Net Profit = Gross Profit - Operating Expenses
    $net_profit = $gross_profit_data['gross_profit'] - $operating_expenses;
    
    return [
        'revenue' => $gross_profit_data['revenue'],
        'purchases' => $purchases,
        'raw_material_stock' => (float)$raw_material_stock,
        'cogs' => $gross_profit_data['cogs'],
        'excess_stock_value' => $gross_profit_data['excess_stock_value'],
        'excess_stock_45_percent' => $gross_profit_data['excess_stock_45_percent'],
        'gross_profit' => $gross_profit_data['gross_profit'],
        'operating_expenses' => $operating_expenses,
        'net_profit' => $net_profit
    ];
}

/**
 * Get complete financial calculations for a date range.
 * This is the main function that should be used for all financial reporting.
 * 
 * @param PDO $pdo Database connection
 * @param string $start_date Start date (YYYY-MM-DD format)
 * @param string $end_date End date (YYYY-MM-DD format)
 * @param float $raw_material_stock User-provided raw material stock value
 * @return array Complete financial breakdown following universal rules
 */
function get_financial_summary($pdo, $start_date, $end_date, $raw_material_stock = 0.0) {
    return calculate_net_profit($pdo, $start_date, $end_date, $raw_material_stock);
}

/**
 * Calculate unpaid purchases amount (credit purchases not yet paid).
 * These represent assets as they are purchases made but not yet paid for.
 * 
 * @param PDO $pdo Database connection
 * @return float Total unpaid purchases amount
 */
function calculate_unpaid_purchases($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.amount as purchase_amount,
                COALESCE(SUM(pp.amount), 0) as paid_amount
            FROM purchases p
            LEFT JOIN purchase_payments pp ON p.id = pp.purchase_id AND pp.deleted_at IS NULL
            WHERE p.deleted_at IS NULL 
            GROUP BY p.id, p.amount
        ");
        $stmt->execute();
        
        $unpaid_total = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $unpaid_amount = max(0, floatval($row['purchase_amount']) - floatval($row['paid_amount']));
            $unpaid_total += $unpaid_amount;
        }
        
        return $unpaid_total;
    } catch (Exception $e) {
        error_log('Error calculating unpaid purchases: ' . $e->getMessage());
        return 0.0;
    }
}

/**
 * Calculate unpaid expenses amount (credit expenses not yet paid).
 * These represent liabilities as they are expenses incurred but not yet paid.
 * 
 * @param PDO $pdo Database connection
 * @return float Total unpaid expenses amount
 */
function calculate_unpaid_expenses($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                e.id,
                e.amount as expense_amount,
                COALESCE(SUM(ep.amount), 0) as paid_amount
            FROM expenses e
            LEFT JOIN expense_payments ep ON e.id = ep.expense_id AND ep.deleted_at IS NULL
            WHERE e.deleted_at IS NULL 
            GROUP BY e.id, e.amount
        ");
        $stmt->execute();
        
        $unpaid_total = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $unpaid_amount = max(0, floatval($row['expense_amount']) - floatval($row['paid_amount']));
            $unpaid_total += $unpaid_amount;
        }
        
        return $unpaid_total;
    } catch (Exception $e) {
        error_log('Error calculating unpaid expenses: ' . $e->getMessage());
        return 0.0;
    }
}

/**
 * Get complete balance sheet data.
 * This is generated each time and shows current financial position.
 * 
 * @param PDO $pdo Database connection
 * @param float $raw_material_stock User-provided raw material stock value
 * @return array Complete balance sheet breakdown
 */
function get_balance_sheet($pdo, $raw_material_stock = 0.0) {
    try {
        // Assets
        $excess_stock_value = calculate_excess_stock_value($pdo);
        $excess_stock_45_percent = $excess_stock_value * 0.45;
        $unpaid_purchases = calculate_unpaid_purchases($pdo);
        $raw_material_stock = (float)$raw_material_stock;
        
        // Total Assets
        $total_assets = $excess_stock_45_percent + $unpaid_purchases + $raw_material_stock;
        
        // Liabilities
        $unpaid_expenses = calculate_unpaid_expenses($pdo);
        
        // Total Liabilities
        $total_liabilities = $unpaid_expenses;
        
        // Net Worth (Assets - Liabilities)
        $net_worth = $total_assets - $total_liabilities;
        
        return [
            'assets' => [
                'excess_stock_value' => $excess_stock_value,
                'excess_stock_45_percent' => $excess_stock_45_percent,
                'unpaid_purchases' => $unpaid_purchases,
                'raw_material_stock' => $raw_material_stock,
                'total_assets' => $total_assets
            ],
            'liabilities' => [
                'unpaid_expenses' => $unpaid_expenses,
                'total_liabilities' => $total_liabilities
            ],
            'net_worth' => $net_worth
        ];
    } catch (Exception $e) {
        error_log('Error generating balance sheet: ' . $e->getMessage());
        return [
            'assets' => [
                'excess_stock_value' => 0.0,
                'excess_stock_45_percent' => 0.0,
                'unpaid_purchases' => 0.0,
                'raw_material_stock' => 0.0,
                'total_assets' => 0.0
            ],
            'liabilities' => [
                'unpaid_expenses' => 0.0,
                'total_liabilities' => 0.0
            ],
            'net_worth' => 0.0
        ];
    }
}

/**
 * Generate a slug from a name with frozofun prefix
 * @param string $name The name to convert to slug
 * @return string The generated slug
 */
function generate_slug($name) {
    // Convert to lowercase and replace spaces/special chars with hyphens
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    
    // Add frozofun prefix
    return 'frozofun-' . $slug;
}
?>