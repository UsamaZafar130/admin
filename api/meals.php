<?php
// Disable display errors for clean JSON output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';
$pdo = require __DIR__ . '/../includes/db_connection.php';

header('Content-Type: application/json');

function error_json($msg) {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

function success_json($extra = []) {
    echo json_encode(['success' => true] + $extra);
    exit;
}

// Set error handler to catch any unexpected errors
set_error_handler(function($severity, $message, $file, $line) {
    error_json("PHP Error: $message in $file on line $line");
});

// Set exception handler to catch any unexpected exceptions
set_exception_handler(function($exception) {
    error_json("Exception: " . $exception->getMessage());
});

// Handle case when database is not available
if (!$pdo) {
    error_json('Database connection failed. Please check your database configuration.');
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

switch ($action) {
    case 'list':
        // List all active meals with item counts
        $stmt = $pdo->query("
            SELECT m.id, m.name, m.price, m.active,
                   COUNT(mi.id) as item_count,
                   m.created_at, m.updated_at
            FROM meals m
            LEFT JOIN meal_items mi ON m.id = mi.meal_id
            GROUP BY m.id
            ORDER BY m.created_at DESC
        ");
        $meals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        success_json(['meals' => $meals]);
        break;

    case 'get':
        $meal_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$meal_id) error_json("Meal ID required");

        // Get meal details
        $stmt = $pdo->prepare("SELECT * FROM meals WHERE id=?");
        $stmt->execute([$meal_id]);
        $meal = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$meal) error_json("Meal not found");

        // Get meal items with item names
        $stmt = $pdo->prepare("
            SELECT mi.id, mi.item_id, mi.qty, mi.pack_size, 
                   i.name as item_name, i.price_per_unit
            FROM meal_items mi
            JOIN items i ON mi.item_id = i.id
            WHERE mi.meal_id = ?
            ORDER BY i.name
        ");
        $stmt->execute([$meal_id]);
        $meal_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $meal['items'] = $meal_items;
        success_json(['meal' => $meal]);
        break;

    case 'add':
        // Handle FormData input instead of JSON
        if (empty($_POST['name'])) error_json("Meal name is required");
        if (!isset($_POST['price']) || $_POST['price'] < 0) error_json("Valid meal price is required");
        if (empty($_POST['items'])) error_json("Meal items are required");

        $name = trim($_POST['name']);
        $price = floatval($_POST['price']);
        $active = isset($_POST['active']) && $_POST['active'] === '1' ? 1 : 0;
        $seo_title = trim($_POST['seo_title'] ?? '');
        $seo_short_description = trim($_POST['seo_short_description'] ?? '');
        $seo_description = trim($_POST['seo_description'] ?? '');
        $is_public = isset($_POST['is_public']) ? intval($_POST['is_public']) : 1;
        
        // Debug logging for troubleshooting
        error_log("Meal Add Debug - Active POST: " . (isset($_POST["active"]) ? $_POST["active"] : "NOT SET") . ", Computed: " . $active);
        error_log("Meal Add Debug - is_public POST: " . (isset($_POST["is_public"]) ? $_POST["is_public"] : "NOT SET") . ", Computed: " . $is_public);
        // Parse items from JSON string
        $items = json_decode($_POST['items'], true);
        if (!$items || !is_array($items)) error_json("Invalid meal items format");
        
        // Auto-generate slug from name
        $slug = generate_slug($name);

        // Handle image uploads
        $display_image = null;
        $banner_image = null;
        
        // Display image upload
        if (!empty($_FILES['display_image']['name'])) {
            $upload_dir = __DIR__ . '/../uploads/meals/img/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
            $orig_ext = strtolower(pathinfo($_FILES['display_image']['name'], PATHINFO_EXTENSION));
            if (!in_array($orig_ext, $allowed_exts)) {
                error_json("Invalid display image file type.");
            }
            
            $tmp_path = $_FILES['display_image']['tmp_name'];
            $img_info = @getimagesize($tmp_path);
            if (!$img_info) error_json("Display image file is not a valid image.");

            $unique = uniqid('meal_display_', true);
            $dest_filename = $unique . '.' . $orig_ext;
            $dest_path = $upload_dir . $dest_filename;

            $processed_ext = process_meal_image($tmp_path, $dest_path, 400, 400);
            if (!$processed_ext) error_json("Display image upload/processing failed.");

            if ($processed_ext !== $orig_ext) {
                $dest_filename = $unique . '.' . $processed_ext;
                $dest_path2 = $upload_dir . $dest_filename;
                rename($dest_path, $dest_path2);
            }
            $display_image = $dest_filename;
        }
        
        // Banner image upload
        if (!empty($_FILES['banner_image']['name'])) {
            $upload_dir = __DIR__ . '/../uploads/meals/banner/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
            $orig_ext = strtolower(pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
            if (!in_array($orig_ext, $allowed_exts)) {
                error_json("Invalid banner image file type.");
            }
            
            $tmp_path = $_FILES['banner_image']['tmp_name'];
            $img_info = @getimagesize($tmp_path);
            if (!$img_info) error_json("Banner image file is not a valid image.");

            $unique = uniqid('meal_banner_', true);
            $dest_filename = $unique . '.' . $orig_ext;
            $dest_path = $upload_dir . $dest_filename;

            $processed_ext = process_meal_image($tmp_path, $dest_path, 800, 400);
            if (!$processed_ext) error_json("Banner image upload/processing failed.");

            if ($processed_ext !== $orig_ext) {
                $dest_filename = $unique . '.' . $processed_ext;
                $dest_path2 = $upload_dir . $dest_filename;
                rename($dest_path, $dest_path2);
            }
            $banner_image = $dest_filename;
        }

        try {
            $pdo->beginTransaction();

            // Insert meal with images
            $stmt = $pdo->prepare("INSERT INTO meals (name, display_image, banner_image, price, active, seo_title, seo_short_description, seo_description, slug, is_public, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$name, $display_image, $banner_image, $price, $active, $seo_title, $seo_short_description, $seo_description, $slug, $is_public]);
            $meal_id = $pdo->lastInsertId();

            // Insert meal items
            $item_stmt = $pdo->prepare("INSERT INTO meal_items (meal_id, item_id, qty, pack_size, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            foreach ($items as $item) {
                if (!is_array($item) || empty($item['item_id']) || !isset($item['qty']) || $item['qty'] <= 0) continue;
                
                $item_id = intval($item['item_id']);
                $qty = floatval($item['qty']);
                $pack_size = floatval($item['pack_size'] ?? 1);
                
                $item_stmt->execute([$meal_id, $item_id, $qty, $pack_size]);
            }

            $pdo->commit();
            success_json(['meal_id' => $meal_id]);
        } catch (Exception $e) {
            $pdo->rollBack();
            // Clean up uploaded images if database insert fails
            if ($display_image && file_exists(__DIR__ . '/../uploads/meals/img/' . $display_image)) {
                @unlink(__DIR__ . '/../uploads/meals/img/' . $display_image);
            }
            if ($banner_image && file_exists(__DIR__ . '/../uploads/meals/banner/' . $banner_image)) {
                @unlink(__DIR__ . '/../uploads/meals/banner/' . $banner_image);
            }
            error_json("Failed to add meal: " . $e->getMessage());
        }
        break;

    case 'edit':
        // Handle FormData input instead of JSON
        if (empty($_POST['meal_id'])) error_json("Meal ID is required");
        if (empty($_POST['name'])) error_json("Meal name is required");
        if (!isset($_POST['price']) || $_POST['price'] < 0) error_json("Valid meal price is required");
        if (empty($_POST['items'])) error_json("Meal items are required");

        $meal_id = intval($_POST['meal_id']);
        $name = trim($_POST['name']);
        $price = floatval($_POST['price']);
        $active = isset($_POST['active']) && $_POST['active'] === '1' ? 1 : 0;
        $seo_title = trim($_POST['seo_title'] ?? '');
        $seo_short_description = trim($_POST['seo_short_description'] ?? '');
        $seo_description = trim($_POST['seo_description'] ?? '');
        $is_public = isset($_POST['is_public']) ? intval($_POST['is_public']) : 1;
        
        // Parse items from JSON string
        $items = json_decode($_POST['items'], true);
        if (!$items || !is_array($items)) error_json("Invalid meal items format");
        
        // Auto-generate slug from name
        $slug = generate_slug($name);

        // Get current images for cleanup
        $current_meal = $pdo->prepare("SELECT display_image, banner_image FROM meals WHERE id=?");
        $current_meal->execute([$meal_id]);
        $current = $current_meal->fetch(PDO::FETCH_ASSOC);
        if (!$current) error_json("Meal not found");

        // Handle image uploads
        $display_image = $current['display_image']; // Keep current if no new image
        $banner_image = $current['banner_image']; // Keep current if no new image
        $old_display_image = null;
        $old_banner_image = null;
        
        // Display image upload
        if (!empty($_FILES['display_image']['name'])) {
            $upload_dir = __DIR__ . '/../uploads/meals/img/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
            $orig_ext = strtolower(pathinfo($_FILES['display_image']['name'], PATHINFO_EXTENSION));
            if (!in_array($orig_ext, $allowed_exts)) {
                error_json("Invalid display image file type.");
            }
            
            $tmp_path = $_FILES['display_image']['tmp_name'];
            $img_info = @getimagesize($tmp_path);
            if (!$img_info) error_json("Display image file is not a valid image.");

            $unique = uniqid('meal_display_', true);
            $dest_filename = $unique . '.' . $orig_ext;
            $dest_path = $upload_dir . $dest_filename;

            $processed_ext = process_meal_image($tmp_path, $dest_path, 400, 400);
            if (!$processed_ext) error_json("Display image upload/processing failed.");

            if ($processed_ext !== $orig_ext) {
                $dest_filename = $unique . '.' . $processed_ext;
                $dest_path2 = $upload_dir . $dest_filename;
                rename($dest_path, $dest_path2);
            }
            
            $old_display_image = $display_image; // Store old image for cleanup
            $display_image = $dest_filename;
        }
        
        // Banner image upload
        if (!empty($_FILES['banner_image']['name'])) {
            $upload_dir = __DIR__ . '/../uploads/meals/banner/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
            $orig_ext = strtolower(pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
            if (!in_array($orig_ext, $allowed_exts)) {
                error_json("Invalid banner image file type.");
            }
            
            $tmp_path = $_FILES['banner_image']['tmp_name'];
            $img_info = @getimagesize($tmp_path);
            if (!$img_info) error_json("Banner image file is not a valid image.");

            $unique = uniqid('meal_banner_', true);
            $dest_filename = $unique . '.' . $orig_ext;
            $dest_path = $upload_dir . $dest_filename;

            $processed_ext = process_meal_image($tmp_path, $dest_path, 800, 400);
            if (!$processed_ext) error_json("Banner image upload/processing failed.");

            if ($processed_ext !== $orig_ext) {
                $dest_filename = $unique . '.' . $processed_ext;
                $dest_path2 = $upload_dir . $dest_filename;
                rename($dest_path, $dest_path2);
            }
            
            $old_banner_image = $banner_image; // Store old image for cleanup
            $banner_image = $dest_filename;
        }

        try {
            $pdo->beginTransaction();

            // Update meal with images
            $stmt = $pdo->prepare("UPDATE meals SET name=?, display_image=?, banner_image=?, price=?, active=?, seo_title=?, seo_short_description=?, seo_description=?, slug=?, is_public=?, updated_at=NOW() WHERE id=?");
            $result = $stmt->execute([$name, $display_image, $banner_image, $price, $active, $seo_title, $seo_short_description, $seo_description, $slug, $is_public, $meal_id]);
            
            if (!$result || $stmt->rowCount() == 0) {
                throw new Exception("Meal not found or could not be updated");
            }

            // Delete existing meal items
            $pdo->prepare("DELETE FROM meal_items WHERE meal_id=?")->execute([$meal_id]);

            // Insert new meal items
            $item_stmt = $pdo->prepare("INSERT INTO meal_items (meal_id, item_id, qty, pack_size, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            foreach ($items as $item) {
                if (!is_array($item) || empty($item['item_id']) || !isset($item['qty']) || $item['qty'] <= 0) continue;
                
                $item_id = intval($item['item_id']);
                $qty = floatval($item['qty']);
                $pack_size = floatval($item['pack_size'] ?? 1);
                
                $item_stmt->execute([$meal_id, $item_id, $qty, $pack_size]);
            }

            $pdo->commit();
            
            // Clean up old images after successful update
            if ($old_display_image && file_exists(__DIR__ . '/../uploads/meals/img/' . $old_display_image)) {
                @unlink(__DIR__ . '/../uploads/meals/img/' . $old_display_image);
            }
            if ($old_banner_image && file_exists(__DIR__ . '/../uploads/meals/banner/' . $old_banner_image)) {
                @unlink(__DIR__ . '/../uploads/meals/banner/' . $old_banner_image);
            }
            
            success_json(['meal_id' => $meal_id]);
        } catch (Exception $e) {
            $pdo->rollBack();
            // Clean up new uploaded images if database update fails
            if (!empty($_FILES['display_image']['name']) && $display_image !== $old_display_image && file_exists(__DIR__ . '/../uploads/meals/img/' . $display_image)) {
                @unlink(__DIR__ . '/../uploads/meals/img/' . $display_image);
            }
            if (!empty($_FILES['banner_image']['name']) && $banner_image !== $old_banner_image && file_exists(__DIR__ . '/../uploads/meals/banner/' . $banner_image)) {
                @unlink(__DIR__ . '/../uploads/meals/banner/' . $banner_image);
            }
            error_json("Failed to edit meal: " . $e->getMessage());
        }
        break;

    case 'delete':
        $meal_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$meal_id) error_json("Meal ID required");

        try {
            // Soft delete by setting deleted_at and is_public = 0
            $stmt = $pdo->prepare("UPDATE meals SET deleted_at=NOW(), is_public=0 WHERE id=?");
            $result = $stmt->execute([$meal_id]);
            
            if (!$result || $stmt->rowCount() == 0) {
                error_json("Meal not found");
            }

            success_json();
        } catch (Exception $e) {
            error_json("Failed to delete meal: " . $e->getMessage());
        }
        break;

    case 'delete_permanent':
        $meal_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$meal_id) error_json("Meal ID required");

        try {
            // Get images for cleanup before deletion
            $img_stmt = $pdo->prepare("SELECT display_image, banner_image FROM meals WHERE id=?");
            $img_stmt->execute([$meal_id]);
            $images = $img_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$images) error_json("Meal not found");

            // Delete the meal record
            $pdo->prepare("DELETE FROM meals WHERE id=?")->execute([$meal_id]);
            
            // Clean up image files
            if ($images['display_image']) {
                $display_path = __DIR__ . '/../uploads/meals/img/' . $images['display_image'];
                if (file_exists($display_path)) @unlink($display_path);
            }
            if ($images['banner_image']) {
                $banner_path = __DIR__ . '/../uploads/meals/banner/' . $images['banner_image'];
                if (file_exists($banner_path)) @unlink($banner_path);
            }

            success_json();
        } catch (Exception $e) {
            error_json("Failed to permanently delete meal: " . $e->getMessage());
        }
        break;

    case 'get_meal_items':
        // Get items for a specific meal (used by order form)
        $meal_id = isset($_GET['meal_id']) ? intval($_GET['meal_id']) : 0;
        if (!$meal_id) error_json("Meal ID required");

        $stmt = $pdo->prepare("
            SELECT mi.item_id, mi.qty, mi.pack_size, 
                   i.name as item_name, i.price_per_unit as default_price,
                   m.price as meal_price
            FROM meal_items mi
            JOIN items i ON mi.item_id = i.id
            JOIN meals m ON mi.meal_id = m.id
            WHERE mi.meal_id = ? AND m.active = 1
            ORDER BY i.name
        ");
        $stmt->execute([$meal_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) error_json("Meal not found or has no items");

        success_json(['items' => $items, 'meal_id' => $meal_id]);
        break;

    // NEW: render HTML for modal form (moved from entities/meals/actions.php)
    case 'load_form':
        try {
            $type = $_POST['type'] ?? $_GET['type'] ?? 'add';
            $meal_id = isset($_POST['meal_id']) ? intval($_POST['meal_id']) : (isset($_GET['meal_id']) ? intval($_GET['meal_id']) : 0);

            if ($type === 'edit' && $meal_id) {
                $stmt = $pdo->prepare("SELECT * FROM meals WHERE id = ?");
                $stmt->execute([$meal_id]);
                $mealData = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$mealData) {
                    error_json('Meal not found');
                }
                // form.php expects $mealData in scope (same as actions.php)
            } else {
                // $mealData remains undefined for "add"
            }

            ob_start();
            include __DIR__ . '/../entities/meals/form.php';
            $html = ob_get_clean();
            success_json(['html' => $html]);
        } catch (Exception $e) {
            error_json('Error loading meal: ' . $e->getMessage());
        }
        break;

    // NEW: render HTML for details modal (moved from entities/meals/actions.php)
    case 'load_details':
        try {
            $meal_id = isset($_POST['meal_id']) ? intval($_POST['meal_id']) : (isset($_GET['meal_id']) ? intval($_GET['meal_id']) : 0);
            if (!$meal_id) {
                error_json('Meal ID required');
            }

            // Get meal with item count
            $stmt = $pdo->prepare("
                SELECT m.*, 
                       COUNT(mi.id) as item_count
                FROM meals m
                LEFT JOIN meal_items mi ON m.id = mi.meal_id
                WHERE m.id = ?
                GROUP BY m.id
            ");
            $stmt->execute([$meal_id]);
            $meal = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$meal) {
                error_json('Meal not found');
            }

            // Get meal items for details view
            $stmt = $pdo->prepare("
                SELECT mi.qty, mi.pack_size, i.name as item_name, i.price_per_unit
                FROM meal_items mi
                JOIN items i ON mi.item_id = i.id
                WHERE mi.meal_id = ?
                ORDER BY i.name
            ");
            $stmt->execute([$meal_id]);
            $meal_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // details.php expects $meal and $meal_items in scope (same as actions.php)
            ob_start();
            include __DIR__ . '/../entities/meals/details.php';
            $html = ob_get_clean();
            success_json(['html' => $html]);
        } catch (Exception $e) {
            error_json('Error loading meal details: ' . $e->getMessage());
        }
        break;

    default:
        http_response_code(400);
        error_json('Invalid action');
}