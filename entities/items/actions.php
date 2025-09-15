<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

header('Content-Type: application/json');
$response = ['success' => false];

try {
    $action = $_POST['action'] ?? '';
    // ADD/EDIT
    if ($action === 'add' || $action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $is_edit = $action === 'edit';
        $fields = [
            'code' => trim($_POST['code'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'price_per_unit' => floatval($_POST['price_per_unit'] ?? 0),
            'default_pack_size' => intval($_POST['default_pack_size'] ?? 1),
            'category_id' => $_POST['category_id'] ? intval($_POST['category_id']) : null,
            'seo_title' => trim($_POST['seo_title'] ?? ''),
            'seo_short_description' => trim($_POST['seo_short_description'] ?? ''),
            'seo_description' => trim($_POST['seo_description'] ?? ''),
            'is_public' => intval($_POST['is_public'] ?? 1),
        ];

        // Auto-generate slug from name
        $fields['slug'] = generate_slug($fields['name']);

        // Image upload and processing
        $upload_dir = __DIR__ . '/../../uploads/items/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $image = null;
        if (!empty($_FILES['image']['name'])) {
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
            $orig_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($orig_ext, $allowed_exts)) {
                throw new Exception("Invalid image file type.");
            }
            $tmp_path = $_FILES['image']['tmp_name'];
            // Ensure actually an image
            $img_info = @getimagesize($tmp_path);
            if (!$img_info) throw new Exception("Uploaded file is not a valid image.");

            // Use process_item_image to crop/resize and get real extension
            $unique = uniqid('item_', true);
            $dest_filename = $unique . '.'.$orig_ext;
            $dest_path = $upload_dir . $dest_filename;

            $processed_ext = process_item_image($tmp_path, $dest_path, 300);
            if (!$processed_ext) throw new Exception("Image upload/processing failed.");

            // If extension changed (e.g., user uploaded .jpg but it's actually a .png)
            if ($processed_ext !== $orig_ext) {
                $dest_filename = $unique . '.' . $processed_ext;
                $dest_path2 = $upload_dir . $dest_filename;
                rename($dest_path, $dest_path2);
                $dest_path = $dest_path2;
            }
            $image = $dest_filename;

            // Remove old image if editing
            if ($is_edit && !empty($id)) {
                $old_img = $pdo->prepare("SELECT image FROM items WHERE id=?");
                $old_img->execute([$id]);
                $old = $old_img->fetchColumn();
                if ($old && file_exists($upload_dir . $old)) @unlink($upload_dir . $old);
            }
        }

        // Validation: code must be unique (case-insensitive to match code generation logic)
        $dup_stmt = $pdo->prepare("SELECT id FROM items WHERE UPPER(code)=UPPER(?) AND " . ($is_edit ? "id!=?" : "1=1"));
        $dup_stmt->execute($is_edit ? [$fields['code'], $id] : [$fields['code']]);
        if ($dup_stmt->fetch()) throw new Exception("Item code already exists!");

        if ($is_edit) {
            $sql = "UPDATE items SET code=?, name=?, price_per_unit=?, default_pack_size=?, category_id=?, seo_title=?, seo_short_description=?, seo_description=?, slug=?, is_public=?" . ($image ? ", image=?" : "") . " WHERE id=?";
            $params = [$fields['code'], $fields['name'], $fields['price_per_unit'], $fields['default_pack_size'], $fields['category_id'], $fields['seo_title'], $fields['seo_short_description'], $fields['seo_description'], $fields['slug'], $fields['is_public']];
            if ($image) $params[] = $image;
            $params[] = $id;
            $pdo->prepare($sql)->execute($params);
            $response['id'] = $id;
        } else {
            $sql = "INSERT INTO items (code, name, price_per_unit, default_pack_size, category_id, seo_title, seo_short_description, seo_description, slug, is_public, image, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $pdo->prepare($sql)->execute([
                $fields['code'], $fields['name'], $fields['price_per_unit'],
                $fields['default_pack_size'], $fields['category_id'], $fields['seo_title'], 
                $fields['seo_short_description'], $fields['seo_description'], $fields['slug'], 
                $fields['is_public'], $image
            ]);
            $response['id'] = $pdo->lastInsertId();
        }
        $response['success'] = true;
    }
    // DELETE (to trash)
    elseif ($action === 'delete') {
        $id = intval($_POST['id']);

        // BLOCK deletion if in pending sales order
        $q = "SELECT oi.id 
                FROM order_items oi
                INNER JOIN sales_orders so ON oi.order_id = so.id
                WHERE oi.item_id = ? 
                  AND so.cancelled = 0 
                  AND so.delivered = 0
                LIMIT 1";
        $stmt = $pdo->prepare($q);
        $stmt->execute([$id]);
        if ($stmt->fetch()) {
            throw new Exception("This item cannot be deleted because it exists in a pending sales order.");
        }

        $pdo->prepare("UPDATE items SET deleted_at=NOW(), is_public=0 WHERE id=?")->execute([$id]);
        $response['success'] = true;
    }
    // RESTORE from trash
    elseif ($action === 'restore') {
        $id = intval($_POST['id']);
        $pdo->prepare("UPDATE items SET deleted_at=NULL WHERE id=?")->execute([$id]);
        $response['success'] = true;
    }
    // PERMANENT DELETE
    elseif ($action === 'delete_permanent') {
        $id = intval($_POST['id']);
        // Remove image file if exists
        $upload_dir = __DIR__ . '/../../uploads/items/';
        $img = $pdo->prepare("SELECT image FROM items WHERE id=?");
        $img->execute([$id]);
        $file = $img->fetchColumn();
        if ($file) {
            $fpath = $upload_dir . $file;
            if (file_exists($fpath)) @unlink($fpath);
        }
        $pdo->prepare("DELETE FROM items WHERE id=?")->execute([$id]);
        $response['success'] = true;
    }
    else {
        throw new Exception("Unknown action.");
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
exit;