<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

// AJAX endpoint for fresh CSRF (for modals)
if (isset($_GET['action']) && $_GET['action'] === 'get_csrf') {
    header('Content-Type: application/json');
    echo json_encode(['csrf_token' => csrf_token()]);
    exit;
}

// Process CSRF for all POSTs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validate_csrf($_POST['csrf_token'] ?? '')) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token!']);
        exit;
    } else {
        set_flash('Invalid CSRF token!', 'danger');
        redirect('list.php');
    }
}

// Cross-module auto-apply: applies advances to both purchases and expenses (oldest first)
function auto_apply_vendor_advances_to_all($pdo, $vendor_id) {
    $stmt = $pdo->prepare("SELECT * FROM vendor_advances WHERE vendor_id=? AND applied=0 AND applied_to_purchase_id IS NULL AND applied_to_expense_id IS NULL AND amount > 0 ORDER BY recorded_at ASC, id ASC");
    $stmt->execute([$vendor_id]);
    $advances = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($advances as $advance) {
        $advance_amount = floatval($advance['amount']);
        $advance_id = $advance['id'];
        if ($advance_amount <= 0) continue;

        // Combine all outstanding credit purchases and expenses for the vendor, sorted oldest first
        $unpaid = [];
        // Purchases
        $stmt2 = $pdo->prepare(
            "SELECT id, amount, (SELECT COALESCE(SUM(amount),0) FROM purchase_payments WHERE purchase_id=p.id AND deleted_at IS NULL) as paid, 'purchase' as entry_type, date, created_at
             FROM purchases p
             WHERE vendor_id=? AND type='credit' AND deleted_at IS NULL AND amount > 0"
        );
        $stmt2->execute([$vendor_id]);
        foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['due'] = floatval($row['amount']) - floatval($row['paid']);
            if ($row['due'] > 0) $unpaid[] = $row;
        }
        // Expenses
        $stmt3 = $pdo->prepare(
            "SELECT id, amount, (SELECT COALESCE(SUM(amount),0) FROM expense_payments WHERE expense_id=e.id AND deleted_at IS NULL) as paid, 'expense' as entry_type, date, created_at
             FROM expenses e
             WHERE vendor_id=? AND type='credit' AND deleted_at IS NULL AND amount > 0"
        );
        $stmt3->execute([$vendor_id]);
        foreach ($stmt3->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['due'] = floatval($row['amount']) - floatval($row['paid']);
            if ($row['due'] > 0) $unpaid[] = $row;
        }
        // Sort all by date, created_at, id (FIFO)
        usort($unpaid, function($a, $b) {
            $cmp = strcmp($a['date'], $b['date']);
            if ($cmp !== 0) return $cmp;
            $cmp2 = strcmp($a['created_at'], $b['created_at']);
            if ($cmp2 !== 0) return $cmp2;
            return $a['id'] - $b['id'];
        });

        foreach ($unpaid as $entry) {
            if ($advance_amount <= 0) break;
            $apply_amt = min($advance_amount, $entry['due']);
            if ($apply_amt <= 0) continue;
            $user_id = current_user_id();

            if ($entry['entry_type'] === 'purchase') {
                $stmt4 = $pdo->prepare("INSERT INTO purchase_payments (purchase_id, amount, route, paid_at, created_by) VALUES (?, ?, 'advance', NOW(), ?)");
                $stmt4->execute([$entry['id'], $apply_amt, $user_id]);
                if ($apply_amt == $advance_amount) {
                    $stmt5 = $pdo->prepare("UPDATE vendor_advances SET applied=1, applied_to_purchase_id=? WHERE id=?");
                    $stmt5->execute([$entry['id'], $advance_id]);
                    $advance_amount = 0;
                    break;
                } else {
                    $stmt6 = $pdo->prepare("UPDATE vendor_advances SET amount=amount-?, applied=0 WHERE id=?");
                    $stmt6->execute([$apply_amt, $advance_id]);
                    $stmt7 = $pdo->prepare("INSERT INTO vendor_advances (vendor_id, amount, recorded_at, applied, applied_to_purchase_id, note, created_by) VALUES (?, ?, NOW(), 1, ?, 'Advance applied (partial, cross-module)', ?)");
                    $stmt7->execute([$vendor_id, $apply_amt, $entry['id'], $user_id]);
                    $advance_amount -= $apply_amt;
                }
            } else {
                $stmt4 = $pdo->prepare("INSERT INTO expense_payments (expense_id, amount, paid_at, created_by) VALUES (?, ?, NOW(), ?)");
                $stmt4->execute([$entry['id'], $apply_amt, $user_id]);
                if ($apply_amt == $advance_amount) {
                    $stmt5 = $pdo->prepare("UPDATE vendor_advances SET applied=1, applied_to_expense_id=? WHERE id=?");
                    $stmt5->execute([$entry['id'], $advance_id]);
                    $advance_amount = 0;
                    break;
                } else {
                    $stmt6 = $pdo->prepare("UPDATE vendor_advances SET amount=amount-?, applied=0 WHERE id=?");
                    $stmt6->execute([$apply_amt, $advance_id]);
                    $stmt7 = $pdo->prepare("INSERT INTO vendor_advances (vendor_id, amount, recorded_at, applied, applied_to_expense_id, note, created_by) VALUES (?, ?, NOW(), 1, ?, 'Advance applied (partial, cross-module)', ?)");
                    $stmt7->execute([$vendor_id, $apply_amt, $entry['id'], $user_id]);
                    $advance_amount -= $apply_amt;
                }
            }
        }
    }
}

// Add or Edit Purchase
if (isset($_POST['action']) && ($_POST['action'] === 'add' || $_POST['action'] === 'edit')) {
    $id = intval($_POST['id'] ?? 0);
    $vendor_id = isset($_POST['vendor_id']) && $_POST['vendor_id'] !== '' ? intval($_POST['vendor_id']) : null;
    $date = $_POST['date'];
    $type = $_POST['type'];
    $amount = floatval($_POST['amount']);
    $description = trim($_POST['description']);

    // Purchases must always have a vendor
    if (!$vendor_id) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Please select a vendor for purchases (cash or credit).']);
            exit;
        } else {
            set_flash('Please select a vendor for purchases (cash or credit).', 'danger');
            redirect('list.php');
        }
    }

    // Require note for negative amount
    if ($amount < 0 && $description === '') {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'A note is required for negative entries. Please add a reason in the Description/Note field.']);
            exit;
        } else {
            set_flash('A note is required for negative entries. Please add a reason in the Description/Note field.', 'danger');
            redirect($_POST['action'] === 'add' ? 'form.php' : 'form.php?id=' . $id);
        }
    }

    // Get old type if editing
    $old_type = null;
    if ($_POST['action'] === 'edit') {
        $stmt_old = $pdo->prepare("SELECT type FROM purchases WHERE id=?");
        $stmt_old->execute([$id]);
        $old_type = $stmt_old->fetchColumn();
    }

    if ($_POST['action'] === 'add') {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO purchases (vendor_id, date, type, amount, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$vendor_id, $date, $type, $amount, $description]);
            $purchase_id = $pdo->lastInsertId();

            if ($type === 'cash') {
                $user_id = current_user_id();
                $stmt2 = $pdo->prepare("INSERT INTO purchase_payments (purchase_id, amount, paid_at, created_by) VALUES (?, ?, NOW(), ?)");
                $stmt2->execute([$purchase_id, $amount, $user_id]);
            }

            if ($type === 'credit') {
                auto_apply_vendor_advances_to_all($pdo, $vendor_id);
            }

            $pdo->commit();
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Purchase added successfully!']);
                exit;
            } else {
                set_flash('Purchase added successfully!', 'success');
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $errMsg = 'Error adding purchase: ' . $e->getMessage();
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $errMsg]);
                exit;
            } else {
                set_flash($errMsg, 'danger');
            }
        }
    } else {
        // If editing and changing from cash to credit, delete previous payment(s)
        if ($old_type === 'cash' && $type === 'credit') {
            $stmt_del = $pdo->prepare("UPDATE purchase_payments SET deleted_at=NOW() WHERE purchase_id=? AND deleted_at IS NULL");
            $stmt_del->execute([$id]);
        }
        $stmt = $pdo->prepare("UPDATE purchases SET vendor_id=?, date=?, type=?, amount=?, description=? WHERE id=?");
        $stmt->execute([$vendor_id, $date, $type, $amount, $description, $id]);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Purchase updated successfully!']);
            exit;
        } else {
            set_flash('Purchase updated successfully!', 'success');
        }
    }
    redirect('list.php');
}

// --- SOFT DELETE LOGIC: Only block if purchase_id present in purchase_payments table (not deleted) ---
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    // Check if this purchase has any payment rows (not soft-deleted)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_payments WHERE purchase_id=? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $count = (int)$stmt->fetchColumn();

    if ($count > 0) {
        $msg = "This purchase can't be deleted because payment(s) exist—add an adjustment entry to fix errors.";
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        } else {
            set_flash($msg, 'danger');
            redirect('list.php');
        }
    }

    // If here, allow soft delete
    $stmt = $pdo->prepare("UPDATE purchases SET deleted_at=NOW() WHERE id=?");
    $stmt->execute([$id]);
    $msg = 'Purchase deleted.';
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $msg]);
        exit;
    } else {
        set_flash($msg, 'success');
        redirect('list.php');
    }
}

// Restore (from trash) with correct surplus tracking logic
if (isset($_POST['action']) && $_POST['action'] === 'restore' && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    // Always restore the purchase itself
    $stmt = $pdo->prepare("UPDATE purchases SET deleted_at=NULL WHERE id=?");
    $stmt->execute([$id]);

    // Count trashed payments for this purchase
    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM purchase_payments WHERE purchase_id=? AND deleted_at IS NOT NULL");
    $stmt2->execute([$id]);
    $trashed_payments = $stmt2->fetchColumn();

    if ($trashed_payments > 0) {
        // Restore payments (deleted with payments)
        $stmt3 = $pdo->prepare("UPDATE purchase_payments SET deleted_at=NULL WHERE purchase_id=? AND deleted_at IS NOT NULL");
        $stmt3->execute([$id]);
    } else {
        // Restore only entry (deleted and payment kept)
        // Insert a negative vendor_advance to reverse the surplus
        $stmt4 = $pdo->prepare("SELECT vendor_id, amount FROM purchases WHERE id=?");
        $stmt4->execute([$id]);
        $row = $stmt4->fetch(PDO::FETCH_ASSOC);
        $vendor_id = $row ? intval($row['vendor_id']) : 0;
        $amount = $row ? floatval($row['amount']) : 0;
        if ($vendor_id && $amount > 0) {
            $note = "Reverse advance for restored purchase #$id";
            $stmt5 = $pdo->prepare("INSERT INTO vendor_advances (vendor_id, amount, recorded_at, applied, note, created_by) VALUES (?, ?, NOW(), 1, ?, ?)");
            $stmt5->execute([
                $vendor_id,
                -1 * $amount,
                $note,
                current_user_id()
            ]);
        }
        // If type was cash, set to credit
        $stmt6 = $pdo->prepare("SELECT type FROM purchases WHERE id=?");
        $stmt6->execute([$id]);
        $old_type = $stmt6->fetchColumn();
        if ($old_type === 'cash') {
            $stmt7 = $pdo->prepare("UPDATE purchases SET type='credit' WHERE id=?");
            $stmt7->execute([$id]);
        }
    }

    // Fetch vendor_id for surplus auto-apply
    $stmt8 = $pdo->prepare("SELECT vendor_id FROM purchases WHERE id=?");
    $stmt8->execute([$id]);
    $row = $stmt8->fetch(PDO::FETCH_ASSOC);
    $vendor_id = $row ? intval($row['vendor_id']) : 0;

    if ($vendor_id) {
        // Always try to apply surplus (if any) to the newly restored purchase
        auto_apply_vendor_advances_to_all($pdo, $vendor_id);
    }

    set_flash('Purchase restored successfully!', 'success');
    redirect('list.php');
}

// Permanent delete (from trash)
if (isset($_POST['action']) && $_POST['action'] === 'delete_permanent' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $delete_payments = isset($_POST['delete_payments']) && $_POST['delete_payments'] == '1';
    if ($delete_payments) {
        $stmt = $pdo->prepare("UPDATE purchase_payments SET deleted_at=NOW() WHERE purchase_id=? AND deleted_at IS NULL");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("SELECT vendor_id FROM purchases WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $vendor_id = $row ? intval($row['vendor_id']) : 0;
        if ($vendor_id) {
            $stmt = $pdo->prepare("SELECT * FROM purchase_payments WHERE purchase_id=? AND deleted_at IS NULL");
            $stmt->execute([$id]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $pay) {
                // Mark payment as deleted
                $stmt_del = $pdo->prepare("UPDATE purchase_payments SET deleted_at=NOW() WHERE id=?");
                $stmt_del->execute([$pay['id']]);
                // Move to vendor advance
                $stmt2 = $pdo->prepare("INSERT INTO vendor_advances (vendor_id, amount, recorded_at, applied, note, created_by) VALUES (?, ?, ?, 0, ?, ?)");
                $stmt2->execute([
                    $vendor_id,
                    $pay['amount'],
                    $pay['paid_at'],
                    "Payment from permanently deleted purchase #$id",
                    $pay['created_by'] ?? 0
                ]);
            }
            auto_apply_vendor_advances_to_all($pdo, $vendor_id);
        }
    }
    $stmt = $pdo->prepare("DELETE FROM purchases WHERE id=?");
    $stmt->execute([$id]);
    set_flash('Purchase permanently deleted.', 'success');
    redirect('trash.php');
}

// Soft delete via GET (just for no-payments shortcut)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Same logic as POST delete (block if present in purchase_payments)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_payments WHERE purchase_id=? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $count = (int)$stmt->fetchColumn();

    if ($count > 0) {
        set_flash("This purchase can't be deleted because payment(s) exist—add an adjustment entry to fix errors.", 'danger');
        redirect('list.php');
    }

    $stmt = $pdo->prepare("UPDATE purchases SET deleted_at=NOW() WHERE id=?");
    $stmt->execute([$id]);
    set_flash('Purchase moved to trash.', 'success');
    redirect('list.php');
}

// Record payment (MINIMAL AJAX SUPPORT ADDED)
if (isset($_POST['action']) && $_POST['action'] === 'pay') {
    $purchase_id = intval($_POST['purchase_id']);
    $amount = floatval($_POST['pay_amount']);
    $route = $_POST['route'];
    $user_id = current_user_id();

    $stmt = $pdo->prepare("SELECT amount, (SELECT COALESCE(SUM(amount),0) FROM purchase_payments WHERE purchase_id = p.id AND deleted_at IS NULL) AS paid FROM purchases p WHERE id=? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$purchase_id]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);

    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if (!$p) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Purchase not found.']);
            exit;
        }
        set_flash('Purchase not found.', 'danger');
        redirect('list.php');
    }

    $total = floatval($p['amount']);
    $alreadyPaid = floatval($p['paid']);
    $due = $total - $alreadyPaid;
    if ($amount > $due) $amount = $due;

    if ($amount <= 0) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Nothing due or invalid amount.']);
            exit;
        }
        set_flash('Nothing due or invalid amount.', 'info');
        redirect('list.php');
    }

    $stmt = $pdo->prepare("INSERT INTO purchase_payments (purchase_id, amount, paid_at, created_by, route) VALUES (?, ?, NOW(), ?, ?)");
    $stmt->execute([$purchase_id, $amount, $user_id, $route]);

    $newPaid = $alreadyPaid + $amount;
    $newDue = $total - $newPaid;
    $status = ($newPaid >= $total) ? 'Paid' : ($newPaid > 0 ? 'Partial' : 'Unpaid');

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Payment recorded!',
            'purchase_id' => $purchase_id,
            'paid_now' => $amount,
            'new_paid_total' => $newPaid,
            'new_due' => $newDue,
            'status' => $status
        ]);
        exit;
    }

    set_flash('Payment recorded!', 'success');
    redirect('list.php');
}

// Catch-all for unknown actions
set_flash('Unknown action.', 'danger');
redirect('list.php');