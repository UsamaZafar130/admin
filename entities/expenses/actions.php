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
    if ($_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token!']);
        exit;
    } else {
        set_flash('Invalid CSRF token!', 'danger');
        redirect('list.php');
    }
}

// Cross-module auto-apply: applies advances to both expenses and purchases (oldest first)
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

// Add or Edit Expense
if (isset($_POST['action']) && ($_POST['action'] === 'add' || $_POST['action'] === 'edit')) {
    $id = intval($_POST['id'] ?? 0);
    $vendor_id = $_POST['vendor_id'] !== '' ? intval($_POST['vendor_id']) : null;
    $date = $_POST['date'];
    $type = $_POST['type'];
    $amount = floatval($_POST['amount']);
    $description = trim($_POST['description']);

    // Require note for negative amount
    if ($amount < 0 && $description === '') {
        if ($_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
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
        $stmt_old = $pdo->prepare("SELECT type FROM expenses WHERE id=?");
        $stmt_old->execute([$id]);
        $old_type = $stmt_old->fetchColumn();
    }

    if ($type === 'credit' && !$vendor_id) {
        if ($_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Please select a vendor for credit expenses.']);
            exit;
        } else {
            set_flash('Please select a vendor for credit expenses.', 'danger');
            redirect('list.php');
        }
    }

    if ($_POST['action'] === 'add') {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO expenses (vendor_id, date, type, amount, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$vendor_id, $date, $type, $amount, $description]);
            $expense_id = $pdo->lastInsertId();

            if ($type === 'cash') {
                $user_id = current_user_id();
                $stmt2 = $pdo->prepare("INSERT INTO expense_payments (expense_id, amount, paid_at, created_by) VALUES (?, ?, NOW(), ?)");
                $stmt2->execute([$expense_id, $amount, $user_id]);
            }

            if ($type === 'credit' && $vendor_id) {
                auto_apply_vendor_advances_to_all($pdo, $vendor_id);
            }

            $pdo->commit();
            if ($_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Expense added successfully!']);
                exit;
            } else {
                set_flash('Expense added successfully!', 'success');
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $errMsg = 'Error adding expense: ' . $e->getMessage();
            if ($_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
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
            $stmt_del = $pdo->prepare("UPDATE expense_payments SET deleted_at=NOW() WHERE expense_id=? AND deleted_at IS NULL");
            $stmt_del->execute([$id]);
        }
        $stmt = $pdo->prepare("UPDATE expenses SET vendor_id=?, date=?, type=?, amount=?, description=? WHERE id=?");
        $stmt->execute([$vendor_id, $date, $type, $amount, $description, $id]);
        if ($_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Expense updated successfully!']);
            exit;
        } else {
            set_flash('Expense updated successfully!', 'success');
        }
    }
    redirect('list.php');
}

// --- SOFT DELETE LOGIC: Only block if expense_id present in expense_payments table (not deleted) ---
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    // Check if this expense has any payment rows (not soft-deleted)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM expense_payments WHERE expense_id=? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $count = (int)$stmt->fetchColumn();

    if ($count > 0) {
        $msg = "This expense can't be deleted because payment(s) exist—add an adjustment entry to fix errors.";
        if ($_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        } else {
            set_flash($msg, 'danger');
            redirect('list.php');
        }
    }

    // If here, allow soft delete
    $stmt = $pdo->prepare("UPDATE expenses SET deleted_at=NOW() WHERE id=?");
    $stmt->execute([$id]);
    $msg = 'Expense deleted.';
    if ($_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
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

    // Always restore the expense itself
    $stmt = $pdo->prepare("UPDATE expenses SET deleted_at=NULL WHERE id=?");
    $stmt->execute([$id]);

    // Count trashed payments for this expense
    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM expense_payments WHERE expense_id=? AND deleted_at IS NOT NULL");
    $stmt2->execute([$id]);
    $trashed_payments = $stmt2->fetchColumn();

    if ($trashed_payments > 0) {
        // Restore payments (deleted with payments)
        $stmt3 = $pdo->prepare("UPDATE expense_payments SET deleted_at=NULL WHERE expense_id=? AND deleted_at IS NOT NULL");
        $stmt3->execute([$id]);
    } else {
        // Restore only entry (deleted and payment kept)
        // Insert a negative vendor_advance to reverse the surplus
        $stmt4 = $pdo->prepare("SELECT vendor_id, amount FROM expenses WHERE id=?");
        $stmt4->execute([$id]);
        $row = $stmt4->fetch(PDO::FETCH_ASSOC);
        $vendor_id = $row ? intval($row['vendor_id']) : 0;
        $amount = $row ? floatval($row['amount']) : 0;
        if ($vendor_id && $amount > 0) {
            $note = "Reverse advance for restored expense #$id";
            $stmt5 = $pdo->prepare("INSERT INTO vendor_advances (vendor_id, amount, recorded_at, applied, note, created_by) VALUES (?, ?, NOW(), 1, ?, ?)");
            $stmt5->execute([
                $vendor_id,
                -1 * $amount,
                $note,
                current_user_id()
            ]);
        }
        // If type was cash, set to credit
        $stmt6 = $pdo->prepare("SELECT type FROM expenses WHERE id=?");
        $stmt6->execute([$id]);
        $old_type = $stmt6->fetchColumn();
        if ($old_type === 'cash') {
            $stmt7 = $pdo->prepare("UPDATE expenses SET type='credit' WHERE id=?");
            $stmt7->execute([$id]);
        }
    }

    // Fetch vendor_id for surplus auto-apply
    $stmt8 = $pdo->prepare("SELECT vendor_id FROM expenses WHERE id=?");
    $stmt8->execute([$id]);
    $row = $stmt8->fetch(PDO::FETCH_ASSOC);
    $vendor_id = $row ? intval($row['vendor_id']) : 0;

    if ($vendor_id) {
        // Always try to apply surplus (if any) to the newly restored expense
        auto_apply_vendor_advances_to_all($pdo, $vendor_id);
    }

    set_flash('Expense restored successfully!', 'success');
    redirect('list.php');
}

// Permanent delete (from trash)
if (isset($_POST['action']) && $_POST['action'] === 'delete_permanent' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $delete_payments = isset($_POST['delete_payments']) && $_POST['delete_payments'] == '1';
    if ($delete_payments) {
        $stmt = $pdo->prepare("UPDATE expense_payments SET deleted_at=NOW() WHERE expense_id=? AND deleted_at IS NULL");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("SELECT vendor_id FROM expenses WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $vendor_id = $row ? intval($row['vendor_id']) : 0;
        if ($vendor_id) {
            $stmt = $pdo->prepare("SELECT * FROM expense_payments WHERE expense_id=? AND deleted_at IS NULL");
            $stmt->execute([$id]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $pay) {
                // Mark payment as deleted
                $stmt_del = $pdo->prepare("UPDATE expense_payments SET deleted_at=NOW() WHERE id=?");
                $stmt_del->execute([$pay['id']]);
                // Move to vendor advance
                $stmt2 = $pdo->prepare("INSERT INTO vendor_advances (vendor_id, amount, recorded_at, applied, note, created_by) VALUES (?, ?, ?, 0, ?, ?)");
                $stmt2->execute([
                    $vendor_id,
                    $pay['amount'],
                    $pay['paid_at'],
                    "Payment from permanently deleted expense #$id",
                    $pay['created_by'] ?? 0
                ]);
            }
            auto_apply_vendor_advances_to_all($pdo, $vendor_id);
        }
    }
    $stmt = $pdo->prepare("DELETE FROM expenses WHERE id=?");
    $stmt->execute([$id]);
    set_flash('Expense permanently deleted.', 'success');
    redirect('trash.php');
}

// Soft delete via GET (just for no-payments shortcut)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Same logic as POST delete (block if present in expense_payments)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM expense_payments WHERE expense_id=? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $count = (int)$stmt->fetchColumn();

    if ($count > 0) {
        set_flash("This expense can't be deleted because payment(s) exist—add an adjustment entry to fix errors.", 'danger');
        redirect('list.php');
    }

    $stmt = $pdo->prepare("UPDATE expenses SET deleted_at=NOW() WHERE id=?");
    $stmt->execute([$id]);
    set_flash('Expense moved to trash.', 'success');
    redirect('list.php');
}

// Record payment
if (isset($_POST['action']) && $_POST['action'] === 'pay') {
    $expense_id = intval($_POST['expense_id']);
    $amount = floatval($_POST['pay_amount']);
    $route = $_POST['route'];
    $user_id = current_user_id();

    $stmt = $pdo->prepare("SELECT amount, (SELECT COALESCE(SUM(amount),0) FROM expense_payments WHERE expense_id = e.id AND deleted_at IS NULL) AS paid FROM expenses e WHERE id=? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$expense_id]);
    $e = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$e) {
        set_flash('Expense not found.', 'danger');
        redirect('list.php');
    }
    $due = floatval($e['amount']) - floatval($e['paid']);
    if ($amount > $due) $amount = $due;

    if ($amount > 0) {
        $stmt = $pdo->prepare("INSERT INTO expense_payments (expense_id, amount, paid_at, created_by) VALUES (?, ?, NOW(), ?)");
        $stmt->execute([$expense_id, $amount, $user_id]);
        set_flash('Payment recorded!', 'success');
    }
    redirect('list.php');
}

// Catch-all for unknown actions
set_flash('Unknown action.', 'danger');
redirect('list.php');