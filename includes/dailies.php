<?php
// includes/dailies.php
// Daily Journal modal + Daily Report modal + lightweight AJAX endpoints.
// This file is INCLUDED by index.php to render modals.
// It can also be called directly with ?action=... to serve JSON for the modals.

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    try {
        // DB
        $pdo = null;
        @include __DIR__ . 'db_connection.php';
        if (!$pdo) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database connection unavailable']);
            exit;
        }

        // Helpers
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $now = date('Y-m-d H:i:s');

        $asDecimal = function($val) {
            if ($val === null || $val === '') return null;
            $n = preg_replace('/[^\d.\-]/', '', (string)$val);
            if ($n === '' || $n === '.' || $n === '-' || $n === '-.') return null;
            return number_format((float)$n, 2, '.', '');
        };
        $asInt = function($val) {
            return ($val === null || $val === '') ? null : (int)$val;
        };
        $asDate = function($val) {
            if (!$val) return null;
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) return $val;
            $t = strtotime($val);
            return $t ? date('Y-m-d', $t) : null;
        };
        $asDateTime = function($val, $fallbackDate = null) {
            if ($val && strtotime($val)) return date('Y-m-d H:i:s', strtotime($val));
            if ($fallbackDate) return $fallbackDate . ' ' . date('H:i:s');
            return date('Y-m-d H:i:s');
        };

        $action = $_GET['action'];

        // Load dropdown options and outstanding lists
        if ($action === 'load-options') {
            // Vendors (active)
            $vendors = $pdo->query("SELECT id, name FROM vendors WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

            // Purchases with due > 0
            $purchases = $pdo->query("
                SELECT p.id, p.vendor_id, v.name AS vendor_name, p.type, p.amount,
                       COALESCE(SUM(pp.amount),0) AS paid,
                       (p.amount - COALESCE(SUM(pp.amount),0)) AS due,
                       p.date, LEFT(COALESCE(p.description,''), 150) AS description
                FROM purchases p
                LEFT JOIN purchase_payments pp ON pp.purchase_id = p.id AND pp.deleted_at IS NULL
                LEFT JOIN vendors v ON v.id = p.vendor_id
                WHERE p.deleted_at IS NULL
                GROUP BY p.id
                HAVING due > 0
                ORDER BY p.date DESC, p.id DESC
                LIMIT 200
            ")->fetchAll(PDO::FETCH_ASSOC);

            // Expenses with due > 0
            $expenses = $pdo->query("
                SELECT e.id, e.vendor_id, v.name AS vendor_name, e.type, e.amount,
                       COALESCE(SUM(ep.amount),0) AS paid,
                       (e.amount - COALESCE(SUM(ep.amount),0)) AS due,
                       e.date, LEFT(COALESCE(e.description,''), 150) AS description
                FROM expenses e
                LEFT JOIN expense_payments ep ON ep.expense_id = e.id AND ep.deleted_at IS NULL
                LEFT JOIN vendors v ON v.id = e.vendor_id
                WHERE e.deleted_at IS NULL
                GROUP BY e.id
                HAVING due > 0
                ORDER BY e.date DESC, e.id DESC
                LIMIT 200
            ")->fetchAll(PDO::FETCH_ASSOC);

            // Unpaid or partial orders
            $orders = $pdo->query("
                SELECT o.id, o.customer_id, c.name AS customer_name, o.order_date, o.grand_total, o.paid,
                       COALESCE(SUM(op.amount),0) AS paid_amount,
                       (o.grand_total - COALESCE(SUM(op.amount),0)) AS due
                FROM sales_orders o
                LEFT JOIN order_payments op ON op.order_id = o.id
                LEFT JOIN customers c ON c.id = o.customer_id
                WHERE o.cancelled = 0
                GROUP BY o.id
                HAVING (o.paid IN (0,2)) OR due > 0
                ORDER BY o.order_date DESC, o.id DESC
                LIMIT 200
            ")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'vendors' => $vendors,
                'purchases_outstanding' => $purchases,
                'expenses_outstanding' => $expenses,
                'orders_unpaid' => $orders
            ]);
            exit;
        }

        // Save the journal payload and create actual rows in tables per schema
        if ($action === 'save-journal') {
            $payload = json_decode(file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
                exit;
            }

            $journalDate = $asDate($payload['journal_date'] ?? date('Y-m-d')) ?: date('Y-m-d');

            $purchases = $payload['purchases'] ?? [];
            $purchasePayments = $payload['purchase_payments'] ?? [];
            $expenses = $payload['expenses'] ?? [];
            $expensePayments = $payload['expense_payments'] ?? [];
            $orderPayments = $payload['order_payments'] ?? [];

            $summary = [
                'purchases' => ['count' => 0, 'total' => 0.00],
                'purchase_payments' => ['count' => 0, 'total' => 0.00],
                'expenses' => ['count' => 0, 'total' => 0.00],
                'expense_payments' => ['count' => 0, 'total' => 0.00],
                'order_payments' => ['count' => 0, 'total' => 0.00],
            ];

            try {
                $pdo->beginTransaction();

                // Purchases
                if (!empty($purchases)) {
                    $stmtPurch = $pdo->prepare("
                        INSERT INTO purchases (vendor_id, description, type, amount, date, created_at)
                        VALUES (:vendor_id, :description, :type, :amount, :date, :created_at)
                    ");
                    foreach ($purchases as $row) {
                        $vendorId = $asInt($row['vendor_id'] ?? null);
                        $type = (isset($row['type']) && $row['type'] === 'credit') ? 'credit' : 'cash';
                        $amount = $asDecimal($row['amount'] ?? null);
                        $desc = trim((string)($row['description'] ?? ''));

                        // purchases.vendor_id is NOT NULL per schema; enforce
                        if (!$vendorId || $amount === null || $amount <= 0) {
                            continue;
                        }

                        $stmtPurch->execute([
                            ':vendor_id' => $vendorId,
                            ':description' => ($desc !== '' ? $desc : null),
                            ':type' => $type,
                            ':amount' => $amount,
                            ':date' => $journalDate,
                            ':created_at' => $now
                        ]);

                        $summary['purchases']['count']++;
                        $summary['purchases']['total'] += (float)$amount;
                    }
                }

                // Purchase Payments
                if (!empty($purchasePayments)) {
                    $stmtPP = $pdo->prepare("
                        INSERT INTO purchase_payments (purchase_id, amount, route, paid_at, created_by, deleted_at, description)
                        VALUES (:purchase_id, :amount, :route, :paid_at, :created_by, NULL, :description)
                    ");
                    foreach ($purchasePayments as $row) {
                        $purchaseId = $asInt($row['purchase_id'] ?? null);
                        $amount = $asDecimal($row['amount'] ?? null);
                        $route = strtolower(trim((string)($row['route'] ?? 'cash')));
                        if (!in_array($route, ['cash','bank'], true)) $route = 'cash';
                        $paidAt = $asDateTime($row['paid_at'] ?? null, $journalDate);
                        $desc = trim((string)($row['description'] ?? ''));

                        if (!$purchaseId || $amount === null || $amount <= 0) continue;

                        $stmtPP->execute([
                            ':purchase_id' => $purchaseId,
                            ':amount' => $amount,
                            ':route' => $route,
                            ':paid_at' => $paidAt,
                            ':created_by' => $userId,
                            ':description' => ($desc !== '' ? $desc : null)
                        ]);

                        $summary['purchase_payments']['count']++;
                        $summary['purchase_payments']['total'] += (float)$amount;
                    }
                }

                // Expenses
                if (!empty($expenses)) {
                    $stmtExp = $pdo->prepare("
                        INSERT INTO expenses (vendor_id, description, type, amount, date, created_at, deleted_at)
                        VALUES (:vendor_id, :description, :type, :amount, :date, :created_at, NULL)
                    ");
                    foreach ($expenses as $row) {
                        $vendorId = $asInt($row['vendor_id'] ?? null); // nullable per schema
                        $type = (isset($row['type']) && $row['type'] === 'credit') ? 'credit' : 'cash';
                        $amount = $asDecimal($row['amount'] ?? null);
                        $desc = trim((string)($row['description'] ?? ''));

                        if ($amount === null || $amount <= 0) continue;

                        $stmtExp->execute([
                            ':vendor_id' => $vendorId,
                            ':description' => ($desc !== '' ? $desc : null),
                            ':type' => $type,
                            ':amount' => $amount,
                            ':date' => $journalDate,
                            ':created_at' => $now
                        ]);

                        $summary['expenses']['count']++;
                        $summary['expenses']['total'] += (float)$amount;
                    }
                }

                // Expense Payments
                if (!empty($expensePayments)) {
                    $stmtEP = $pdo->prepare("
                        INSERT INTO expense_payments (expense_id, amount, paid_at, created_by, deleted_at, route, description)
                        VALUES (:expense_id, :amount, :paid_at, :created_by, NULL, :route, :description)
                    ");
                    foreach ($expensePayments as $row) {
                        $expenseId = $asInt($row['expense_id'] ?? null);
                        $amount = $asDecimal($row['amount'] ?? null);
                        $route = strtolower(trim((string)($row['route'] ?? 'cash')));
                        if (!in_array($route, ['cash','bank'], true)) $route = 'cash';
                        $paidAt = $asDateTime($row['paid_at'] ?? null, $journalDate);
                        $desc = trim((string)($row['description'] ?? ''));

                        if (!$expenseId || $amount === null || $amount <= 0) continue;

                        $stmtEP->execute([
                            ':expense_id' => $expenseId,
                            ':amount' => $amount,
                            ':paid_at' => $paidAt,
                            ':created_by' => $userId,
                            ':route' => $route,
                            ':description' => ($desc !== '' ? $desc : null)
                        ]);

                        $summary['expense_payments']['count']++;
                        $summary['expense_payments']['total'] += (float)$amount;
                    }
                }

                // Order Payments (+ update sales_orders.paid 0/2/1)
                if (!empty($orderPayments)) {
                    $stmtOP = $pdo->prepare("
                        INSERT INTO order_payments (order_id, amount, paid_at, payment_method, created_by)
                        VALUES (:order_id, :amount, :paid_at, :payment_method, :created_by)
                    ");
                    $stmtSum = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM order_payments WHERE order_id = ?");
                    $stmtOrder = $pdo->prepare("SELECT grand_total FROM sales_orders WHERE id = ?");
                    $stmtUpdatePaid = $pdo->prepare("UPDATE sales_orders SET paid = :paid WHERE id = :id");

                    foreach ($orderPayments as $row) {
                        $orderId = $asInt($row['order_id'] ?? null);
                        $amount = $asDecimal($row['amount'] ?? null);
                        $method = strtolower(trim((string)($row['payment_method'] ?? 'cash')));
                        if (!in_array($method, ['cash','bank'], true)) $method = 'cash';
                        $paidAt = $asDateTime($row['paid_at'] ?? null, $journalDate);

                        if (!$orderId || $amount === null || $amount <= 0) continue;

                        $stmtOP->execute([
                            ':order_id' => $orderId,
                            ':amount' => $amount,
                            ':paid_at' => $paidAt,
                            ':payment_method' => $method,
                            ':created_by' => $userId
                        ]);

                        // Update paid flag
                        $stmtOrder->execute([$orderId]);
                        $rowOrder = $stmtOrder->fetch(PDO::FETCH_ASSOC);
                        if ($rowOrder) {
                            $grand = (float)$rowOrder['grand_total'];
                            $stmtSum->execute([$orderId]);
                            $paidSoFar = (float)$stmtSum->fetchColumn();
                            $newStatus = 0; // unpaid
                            if ($paidSoFar <= 0.0) $newStatus = 0;
                            elseif ($paidSoFar >= $grand) $newStatus = 1; // paid
                            else $newStatus = 2; // partial
                            $stmtUpdatePaid->execute([':paid' => $newStatus, ':id' => $orderId]);
                        }

                        $summary['order_payments']['count']++;
                        $summary['order_payments']['total'] += (float)$amount;
                    }
                }

                $pdo->commit();

                foreach ($summary as $k => $v) {
                    $summary[$k]['total'] = number_format((float)$v['total'], 2, '.', '');
                }

                echo json_encode(['success' => true, 'summary' => $summary, 'journal_date' => $journalDate]);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
        }

        // Daily report for a date
        if ($action === 'report') {
            $date = $asDate($_GET['date'] ?? $_POST['date'] ?? date('Y-m-d')) ?: date('Y-m-d');
            $opening = (float)($_GET['opening'] ?? $_POST['opening'] ?? 0);

            // Totals by cash/bank/credit
            $sum = function($sql, $params = []) use ($pdo) {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                return (float)$stmt->fetchColumn();
            };

            $totals = [
                'purchases_cash' => $sum("SELECT COALESCE(SUM(amount),0) FROM purchases WHERE date = ? AND type = 'cash' AND deleted_at IS NULL", [$date]),
                'purchases_credit' => $sum("SELECT COALESCE(SUM(amount),0) FROM purchases WHERE date = ? AND type = 'credit' AND deleted_at IS NULL", [$date]),
                'purchase_payments_cash' => $sum("SELECT COALESCE(SUM(amount),0) FROM purchase_payments WHERE DATE(paid_at) = ? AND deleted_at IS NULL AND route = 'cash'", [$date]),
                'purchase_payments_bank' => $sum("SELECT COALESCE(SUM(amount),0) FROM purchase_payments WHERE DATE(paid_at) = ? AND deleted_at IS NULL AND route = 'bank'", [$date]),
                'expenses_cash' => $sum("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE date = ? AND type = 'cash' AND deleted_at IS NULL", [$date]),
                'expenses_credit' => $sum("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE date = ? AND type = 'credit' AND deleted_at IS NULL", [$date]),
                'expense_payments_cash' => $sum("SELECT COALESCE(SUM(amount),0) FROM expense_payments WHERE DATE(paid_at) = ? AND deleted_at IS NULL AND route = 'cash'", [$date]),
                'expense_payments_bank' => $sum("SELECT COALESCE(SUM(amount),0) FROM expense_payments WHERE DATE(paid_at) = ? AND deleted_at IS NULL AND route = 'bank'", [$date]),
                'order_payments_cash' => $sum("SELECT COALESCE(SUM(amount),0) FROM order_payments WHERE DATE(paid_at) = ? AND payment_method = 'cash'", [$date]),
                'order_payments_bank' => $sum("SELECT COALESCE(SUM(amount),0) FROM order_payments WHERE DATE(paid_at) = ? AND payment_method = 'bank'", [$date]),
            ];

            $cashInflows = $totals['order_payments_cash'];
            $cashOutflows = $totals['purchases_cash'] + $totals['purchase_payments_cash'] + $totals['expenses_cash'] + $totals['expense_payments_cash'];
            $cashInHand = (float)$opening + $cashInflows - $cashOutflows;

            // Detail lists for the date from DB
            $details = [
                'purchases' => [],
                'purchase_payments' => [],
                'expenses' => [],
                'expense_payments' => [],
                'order_payments' => [],
            ];

            // Purchases (date column)
            $stmt = $pdo->prepare("
                SELECT p.id, p.type, p.amount, p.description, p.date, v.name AS vendor_name
                FROM purchases p
                LEFT JOIN vendors v ON v.id = p.vendor_id
                WHERE p.date = ? AND p.deleted_at IS NULL
                ORDER BY p.id DESC
                LIMIT 200
            ");
            $stmt->execute([$date]);
            $details['purchases'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Purchase payments (paid_at date)
            $stmt = $pdo->prepare("
                SELECT pp.id, pp.amount, pp.route, pp.paid_at, p.id AS purchase_id, v.name AS vendor_name
                FROM purchase_payments pp
                LEFT JOIN purchases p ON p.id = pp.purchase_id
                LEFT JOIN vendors v ON v.id = p.vendor_id
                WHERE DATE(pp.paid_at) = ? AND pp.deleted_at IS NULL
                ORDER BY pp.id DESC
                LIMIT 200
            ");
            $stmt->execute([$date]);
            $details['purchase_payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Expenses (date column)
            $stmt = $pdo->prepare("
                SELECT e.id, e.type, e.amount, e.description, e.date, v.name AS vendor_name
                FROM expenses e
                LEFT JOIN vendors v ON v.id = e.vendor_id
                WHERE e.date = ? AND e.deleted_at IS NULL
                ORDER BY e.id DESC
                LIMIT 200
            ");
            $stmt->execute([$date]);
            $details['expenses'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Expense payments (paid_at date)
            $stmt = $pdo->prepare("
                SELECT ep.id, ep.amount, ep.route, ep.paid_at, e.id AS expense_id, v.name AS vendor_name
                FROM expense_payments ep
                LEFT JOIN expenses e ON e.id = ep.expense_id
                LEFT JOIN vendors v ON v.id = e.vendor_id
                WHERE DATE(ep.paid_at) = ? AND ep.deleted_at IS NULL
                ORDER BY ep.id DESC
                LIMIT 200
            ");
            $stmt->execute([$date]);
            $details['expense_payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Order payments (paid_at date)
            $stmt = $pdo->prepare("
                SELECT op.id, op.amount, op.payment_method, op.paid_at, o.id AS order_id, c.name AS customer_name
                FROM order_payments op
                LEFT JOIN sales_orders o ON o.id = op.order_id
                LEFT JOIN customers c ON c.id = o.customer_id
                WHERE DATE(op.paid_at) = ?
                ORDER BY op.id DESC
                LIMIT 200
            ");
            $stmt->execute([$date]);
            $details['order_payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($totals as $k => $v) $totals[$k] = number_format($v, 2, '.', '');

            echo json_encode([
                'success' => true,
                'date' => $date,
                'opening' => number_format((float)$opening, 2, '.', ''),
                'totals' => $totals,
                'cash_inflows' => number_format((float)$cashInflows, 2, '.', ''),
                'cash_outflows' => number_format((float)$cashOutflows, 2, '.', ''),
                'cash_in_hand' => number_format((float)$cashInHand, 2, '.', ''),
                'details' => $details
            ]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}
?>

<!-- Daily Journal Modal -->
<div class="modal fade" id="dailyJournalModal" tabindex="-1" aria-labelledby="dailyJournalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-light">
        <h5 class="modal-title" id="dailyJournalLabel"><i class="fas fa-book me-2"></i>Daily Journal Entry</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">

        <div class="row g-3 align-items-end mb-3">
            <div class="col-sm-6 col-md-4">
                <label class="form-label">Journal Date</label>
                <input type="date" class="form-control" id="journal-date" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-sm-6 col-md-8 text-end">
                <button type="button" class="btn btn-outline-secondary" id="refresh-dailies-options">
                    <i class="fas fa-sync-alt me-1"></i>Refresh Lists
                </button>
            </div>
        </div>

        <ul class="nav nav-tabs" id="journalTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="purchases-tab" data-bs-toggle="tab" data-bs-target="#tab-purchases" type="button" role="tab">Purchases</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="purchase-payments-tab" data-bs-toggle="tab" data-bs-target="#tab-purchase-payments" type="button" role="tab">Purchase Payments</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="expenses-tab" data-bs-toggle="tab" data-bs-target="#tab-expenses" type="button" role="tab">Expenses</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="expense-payments-tab" data-bs-toggle="tab" data-bs-target="#tab-expense-payments" type="button" role="tab">Expense Payments</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="order-payments-tab" data-bs-toggle="tab" data-bs-target="#tab-order-payments" type="button" role="tab">Order Payments</button>
          </li>
        </ul>

        <div class="tab-content border border-top-0 p-3">
          <!-- Purchases -->
          <div class="tab-pane fade show active" id="tab-purchases" role="tabpanel" aria-labelledby="purchases-tab">
            <div class="mb-2 text-end">
                <button class="btn btn-sm btn-outline-primary" id="add-purchase-row"><i class="fas fa-plus me-1"></i>Add Row</button>
            </div>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead class="table-light">
                  <tr>
                    <th style="min-width: 220px;">Vendor</th>
                    <th style="min-width: 120px;">Type</th>
                    <th style="min-width: 140px;">Amount</th>
                    <th style="min-width: 280px;">Description</th>
                    <th style="width: 40px;"></th>
                  </tr>
                </thead>
                <tbody id="purchases-body"></tbody>
              </table>
            </div>
            <div class="small text-muted">Note: Vendor is required for purchases (schema: purchases.vendor_id NOT NULL).</div>
          </div>

          <!-- Purchase Payments -->
          <div class="tab-pane fade" id="tab-purchase-payments" role="tabpanel" aria-labelledby="purchase-payments-tab">
            <div class="mb-2 text-end">
                <button class="btn btn-sm btn-outline-primary" id="add-purchase-payment-row"><i class="fas fa-plus me-1"></i>Add Row</button>
            </div>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead class="table-light">
                  <tr>
                    <th style="min-width: 320px;">Purchase (Outstanding)</th>
                    <th style="min-width: 120px;">Route</th>
                    <th style="min-width: 140px;">Amount</th>
                    <th style="min-width: 180px;">Paid At</th>
                    <th style="min-width: 240px;">Description</th>
                    <th style="width: 40px;"></th>
                  </tr>
                </thead>
                <tbody id="purchase-payments-body"></tbody>
              </table>
            </div>
          </div>

          <!-- Expenses -->
          <div class="tab-pane fade" id="tab-expenses" role="tabpanel" aria-labelledby="expenses-tab">
            <div class="mb-2 text-end">
                <button class="btn btn-sm btn-outline-primary" id="add-expense-row"><i class="fas fa-plus me-1"></i>Add Row</button>
            </div>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead class="table-light">
                  <tr>
                    <th style="min-width: 220px;">Vendor (optional)</th>
                    <th style="min-width: 120px;">Type</th>
                    <th style="min-width: 140px;">Amount</th>
                    <th style="min-width: 280px;">Description</th>
                    <th style="width: 40px;"></th>
                  </tr>
                </thead>
                <tbody id="expenses-body"></tbody>
              </table>
            </div>
          </div>

          <!-- Expense Payments -->
          <div class="tab-pane fade" id="tab-expense-payments" role="tabpanel" aria-labelledby="expense-payments-tab">
            <div class="mb-2 text-end">
                <button class="btn btn-sm btn-outline-primary" id="add-expense-payment-row"><i class="fas fa-plus me-1"></i>Add Row</button>
            </div>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead class="table-light">
                  <tr>
                    <th style="min-width: 320px;">Expense (Outstanding)</th>
                    <th style="min-width: 120px;">Route</th>
                    <th style="min-width: 140px;">Amount</th>
                    <th style="min-width: 180px;">Paid At</th>
                    <th style="min-width: 240px;">Description</th>
                    <th style="width: 40px;"></th>
                  </tr>
                </thead>
                <tbody id="expense-payments-body"></tbody>
              </table>
            </div>
          </div>

          <!-- Order Payments -->
          <div class="tab-pane fade" id="tab-order-payments" role="tabpanel" aria-labelledby="order-payments-tab">
            <div class="mb-2 text-end">
                <button class="btn btn-sm btn-outline-primary" id="add-order-payment-row"><i class="fas fa-plus me-1"></i>Add Row</button>
            </div>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead class="table-light">
                  <tr>
                    <th style="min-width: 320px;">Order (Unpaid/Partial)</th>
                    <th style="min-width: 120px;">Method</th>
                    <th style="min-width: 140px;">Amount</th>
                    <th style="min-width: 180px;">Paid At</th>
                    <th style="width: 40px;"></th>
                  </tr>
                </thead>
                <tbody id="order-payments-body"></tbody>
              </table>
            </div>
          </div>
        </div>

        <div id="journal-alert" class="alert d-none mt-3" role="alert"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary" id="save-journal-btn">
            <i class="fas fa-save me-1"></i>Save Journal
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Daily Report Modal -->
<div class="modal fade" id="dailyReportModal" tabindex="-1" aria-labelledby="dailyReportLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-light">
        <h5 class="modal-title" id="dailyReportLabel"><i class="fas fa-file-invoice-dollar me-2"></i>Daily Report</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3 align-items-end">
            <div class="col-sm-4">
                <label class="form-label">Report Date</label>
                <input type="date" class="form-control" id="report-date" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-sm-4">
                <label class="form-label">Cash Opening Balance</label>
                <input type="number" step="0.01" class="form-control" id="report-opening" placeholder="0.00">
            </div>
            <div class="col-sm-4 text-end">
                <button class="btn btn-primary" id="generate-report-btn">
                    <i class="fas fa-calculator me-1"></i>Generate
                </button>
            </div>
        </div>

        <hr class="my-3">

        <div id="report-summary" class="row g-3"></div>
        <div id="report-details" class="mt-4"></div>
        <div id="report-alert" class="alert d-none mt-3" role="alert"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-outline-success d-none" id="print-report-btn">
            <i class="fas fa-print me-1"></i>Print
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// State caches
const DAILIES = { vendors: [], purchasesOutstanding: [], expensesOutstanding: [], ordersUnpaid: [] };

function money(n) {
    const num = parseFloat(n || 0);
    return 'Rs. ' + num.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
}
function todayDate() {
    const d = new Date();
    const m = String(d.getMonth()+1).padStart(2,'0');
    const day = String(d.getDate()).padStart(2,'0');
    return d.getFullYear() + '-' + m + '-' + day;
}
function escapeHtml(s) {
    return (s || '').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}
function showJournalAlert(type, msg) {
    const el = document.getElementById('journal-alert');
    el.className = 'alert alert-' + type;
    el.textContent = msg;
    el.classList.remove('d-none');
    setTimeout(() => el.classList.add('d-none'), 4000);
}
function showReportAlert(type, msg) {
    const el = document.getElementById('report-alert');
    el.className = 'alert alert-' + type;
    el.textContent = msg;
    el.classList.remove('d-none');
    setTimeout(() => el.classList.add('d-none'), 4000);
}

function vendorOptionsHtml(allowBlank = true) {
    let html = allowBlank ? '<option value="">-- Select Vendor --</option>' : '';
    DAILIES.vendors.forEach(v => { html += `<option value="${v.id}">${escapeHtml(v.name)}</option>`; });
    return html;
}
function purchasesOptionsHtml() {
    let html = '<option value="">-- Select Purchase --</option>';
    DAILIES.purchasesOutstanding.forEach(p => {
        const vname = p.vendor_name ? ` (${escapeHtml(p.vendor_name)})` : '';
        html += `<option value="${p.id}">#${p.id} - ${escapeHtml(p.description || 'Purchase')}${vname} - Due: ${money(p.due)}</option>`;
    });
    return html;
}
function expensesOptionsHtml() {
    let html = '<option value="">-- Select Expense --</option>';
    DAILIES.expensesOutstanding.forEach(e => {
        const vname = e.vendor_name ? ` (${escapeHtml(e.vendor_name)})` : '';
        html += `<option value="${e.id}">#${e.id} - ${escapeHtml(e.description || 'Expense')}${vname} - Due: ${money(e.due)}</option>`;
    });
    return html;
}
function ordersOptionsHtml() {
    let html = '<option value="">-- Select Order --</option>';
    DAILIES.ordersUnpaid.forEach(o => {
        const cname = o.customer_name ? ` (${escapeHtml(o.customer_name)})` : '';
        html += `<option value="${o.id}">#${o.id}${cname} - Due: ${money(o.due)}</option>`;
    });
    return html;
}

function loadDailiesOptions() {
    return fetch('/includes/dailies.php?action=load-options', {credentials:'same-origin'})
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.error || 'Failed to load lists');
            DAILIES.vendors = data.vendors || [];
            DAILIES.purchasesOutstanding = data.purchases_outstanding || [];
            DAILIES.expensesOutstanding = data.expenses_outstanding || [];
            DAILIES.ordersUnpaid = data.orders_unpaid || [];
            refreshAllSelects();
        })
        .catch(err => {
            console.error(err);
            showJournalAlert('warning', 'Could not refresh lists. You can still enter manual data.');
        });
}
function refreshAllSelects() {
    document.querySelectorAll('#purchases-body select.vendor-id').forEach(sel => sel.innerHTML = vendorOptionsHtml(false));
    document.querySelectorAll('#expenses-body select.vendor-id').forEach(sel => sel.innerHTML = vendorOptionsHtml(true));
    document.querySelectorAll('#purchase-payments-body select.purchase-id').forEach(sel => sel.innerHTML = purchasesOptionsHtml());
    document.querySelectorAll('#expense-payments-body select.expense-id').forEach(sel => sel.innerHTML = expensesOptionsHtml());
    document.querySelectorAll('#order-payments-body select.order-id').forEach(sel => sel.innerHTML = ordersOptionsHtml());
}

function addPurchaseRow() {
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><select class="form-select form-select-sm vendor-id" required>${vendorOptionsHtml(false)}</select></td>
        <td>
            <select class="form-select form-select-sm type">
                <option value="cash">Cash</option>
                <option value="credit">Credit</option>
            </select>
        </td>
        <td><input type="number" step="0.01" class="form-control form-control-sm amount" placeholder="0.00"></td>
        <td><input type="text" class="form-control form-control-sm description" placeholder="Description (optional)"></td>
        <td class="text-center"><button class="btn btn-sm btn-outline-danger remove-row" title="Remove"><i class="fas fa-times"></i></button></td>
    `;
    document.getElementById('purchases-body').appendChild(tr);
}
function addPurchasePaymentRow() {
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><select class="form-select form-select-sm purchase-id">${purchasesOptionsHtml()}</select></td>
        <td>
            <select class="form-select form-select-sm route">
                <option value="cash">Cash</option>
                <option value="bank">Bank</option>
            </select>
        </td>
        <td><input type="number" step="0.01" class="form-control form-control-sm amount" placeholder="0.00"></td>
        <td><input type="datetime-local" class="form-control form-control-sm paid-at" value="${todayDate()}T12:00"></td>
        <td><input type="text" class="form-control form-control-sm description" placeholder="Description (optional)"></td>
        <td class="text-center"><button class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
    `;
    document.getElementById('purchase-payments-body').appendChild(tr);
}
function addExpenseRow() {
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><select class="form-select form-select-sm vendor-id">${vendorOptionsHtml(true)}</select></td>
        <td>
            <select class="form-select form-select-sm type">
                <option value="cash">Cash</option>
                <option value="credit">Credit</option>
            </select>
        </td>
        <td><input type="number" step="0.01" class="form-control form-control-sm amount" placeholder="0.00"></td>
        <td><input type="text" class="form-control form-control-sm description" placeholder="Description (optional)"></td>
        <td class="text-center"><button class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
    `;
    document.getElementById('expenses-body').appendChild(tr);
}
function addExpensePaymentRow() {
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><select class="form-select form-select-sm expense-id">${expensesOptionsHtml()}</select></td>
        <td>
            <select class="form-select form-select-sm route">
                <option value="cash">Cash</option>
                <option value="bank">Bank</option>
            </select>
        </td>
        <td><input type="number" step="0.01" class="form-control form-control-sm amount" placeholder="0.00"></td>
        <td><input type="datetime-local" class="form-control form-control-sm paid-at" value="${todayDate()}T12:00"></td>
        <td><input type="text" class="form-control form-control-sm description" placeholder="Description (optional)"></td>
        <td class="text-center"><button class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
    `;
    document.getElementById('expense-payments-body').appendChild(tr);
}
function addOrderPaymentRow() {
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><select class="form-select form-select-sm order-id">${ordersOptionsHtml()}</select></td>
        <td>
            <select class="form-select form-select-sm method">
                <option value="cash">Cash</option>
                <option value="bank">Bank</option>
            </select>
        </td>
        <td><input type="number" step="0.01" class="form-control form-control-sm amount" placeholder="0.00"></td>
        <td><input type="datetime-local" class="form-control form-control-sm paid-at" value="${todayDate()}T12:00"></td>
        <td class="text-center"><button class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
    `;
    document.getElementById('order-payments-body').appendChild(tr);
}

function collectJournalPayload() {
    const journalDate = document.getElementById('journal-date').value || todayDate();
    const num = v => (isNaN(parseFloat(v)) ? 0 : parseFloat(v));

    const purchases = [];
    document.querySelectorAll('#purchases-body tr').forEach(tr => {
        const amount = num(tr.querySelector('.amount')?.value);
        const vendor = tr.querySelector('.vendor-id')?.value;
        if (amount > 0 && vendor) {
            purchases.push({
                vendor_id: vendor,
                type: tr.querySelector('.type')?.value || 'cash',
                amount: amount,
                description: tr.querySelector('.description')?.value || ''
            });
        }
    });

    const purchase_payments = [];
    document.querySelectorAll('#purchase-payments-body tr').forEach(tr => {
        const amount = num(tr.querySelector('.amount')?.value);
        const purchaseId = tr.querySelector('.purchase-id')?.value;
        if (amount > 0 && purchaseId) {
            purchase_payments.push({
                purchase_id: purchaseId,
                route: tr.querySelector('.route')?.value || 'cash',
                amount: amount,
                paid_at: tr.querySelector('.paid-at')?.value || (journalDate + 'T12:00'),
                description: tr.querySelector('.description')?.value || ''
            });
        }
    });

    const expenses = [];
    document.querySelectorAll('#expenses-body tr').forEach(tr => {
        const amount = num(tr.querySelector('.amount')?.value);
        if (amount > 0) {
            expenses.push({
                vendor_id: tr.querySelector('.vendor-id')?.value || null,
                type: tr.querySelector('.type')?.value || 'cash',
                amount: amount,
                description: tr.querySelector('.description')?.value || ''
            });
        }
    });

    const expense_payments = [];
    document.querySelectorAll('#expense-payments-body tr').forEach(tr => {
        const amount = num(tr.querySelector('.amount')?.value);
        const expenseId = tr.querySelector('.expense-id')?.value;
        if (amount > 0 && expenseId) {
            expense_payments.push({
                expense_id: expenseId,
                route: tr.querySelector('.route')?.value || 'cash',
                amount: amount,
                paid_at: tr.querySelector('.paid-at')?.value || (journalDate + 'T12:00'),
                description: tr.querySelector('.description')?.value || ''
            });
        }
    });

    const order_payments = [];
    document.querySelectorAll('#order-payments-body tr').forEach(tr => {
        const amount = num(tr.querySelector('.amount')?.value);
        const orderId = tr.querySelector('.order-id')?.value;
        if (amount > 0 && orderId) {
            order_payments.push({
                order_id: orderId,
                payment_method: tr.querySelector('.method')?.value || 'cash',
                amount: amount,
                paid_at: tr.querySelector('.paid-at')?.value || (journalDate + 'T12:00')
            });
        }
    });

    return { journal_date: journalDate, purchases, purchase_payments, expenses, expense_payments, order_payments };
}

function clearJournalForm() {
    ['purchases-body','purchase-payments-body','expenses-body','expense-payments-body','order-payments-body'].forEach(id => {
        const tbody = document.getElementById(id);
        if (tbody) tbody.innerHTML = '';
    });
    addPurchaseRow();
    addPurchasePaymentRow();
    addExpenseRow();
    addExpensePaymentRow();
    addOrderPaymentRow();
}

function renderReportSummary(data) {
    const wrap = document.getElementById('report-summary');
    if (!data.success) {
        wrap.innerHTML = '';
        showReportAlert('danger', data.error || 'Failed to generate report');
        return;
    }
    const t = data.totals;
    const cashInflows = parseFloat(data.cash_inflows || 0);
    const cashOutflows = parseFloat(data.cash_outflows || 0);
    const cashInHand = parseFloat(data.cash_in_hand || 0);

    wrap.innerHTML = `
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-body">
            <h6 class="text-muted mb-2">Cash Opening</h6>
            <div class="h4">${money(data.opening)}</div>
            <hr>
            <h6 class="text-muted mb-2">Cash Inflows</h6>
            <div>Order Payments (Cash): <strong>${money(t.order_payments_cash)}</strong></div>
            <div class="small text-muted">Order Payments (Bank): ${money(t.order_payments_bank)}</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-body">
            <h6 class="text-muted mb-2">Cash Outflows</h6>
            <div>Cash Purchases: <strong>${money(t.purchases_cash)}</strong></div>
            <div>Purchase Payments (Cash): <strong>${money(t.purchase_payments_cash)}</strong></div>
            <div>Cash Expenses: <strong>${money(t.expenses_cash)}</strong></div>
            <div>Expense Payments (Cash): <strong>${money(t.expense_payments_cash)}</strong></div>
            <hr>
            <div class="small text-muted">Bank Purch. Payments: ${money(t.purchase_payments_bank)}</div>
            <div class="small text-muted">Bank Expense Payments: ${money(t.expense_payments_bank)}</div>
            <div class="small text-muted">Credit Purchases: ${money(t.purchases_credit)}</div>
            <div class="small text-muted">Credit Expenses: ${money(t.expenses_credit)}</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-body">
            <h6 class="text-muted mb-2">Cash Position</h6>
            <div>Inflows: <strong>${money(cashInflows)}</strong></div>
            <div>Outflows: <strong>${money(cashOutflows)}</strong></div>
            <hr>
            <div class="h4">Cash In Hand: ${money(cashInHand)}</div>
            <div class="small text-muted">Date: ${escapeHtml(data.date)}</div>
          </div>
        </div>
      </div>
    `;
}

function renderReportDetails(data) {
    const wrap = document.getElementById('report-details');
    const d = data.details || {};
    const section = (title, rows, renderer, emptyText='No records') => {
        const listHtml = (rows && rows.length)
            ? `<ul class="list-group list-group-flush">${rows.map(renderer).join('')}</ul>`
            : `<div class="text-muted p-3">${emptyText}</div>`;
        return `<div class="card mb-3"><div class="card-header"><strong>${escapeHtml(title)}</strong></div>${listHtml}</div>`;
    };

    wrap.innerHTML = `
      <div class="row">
        <div class="col-lg-6">
          ${section('Purchases', d.purchases, r => `
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                #${r.id} - ${escapeHtml(r.description || 'Purchase')}
                ${r.vendor_name ? `<span class="text-muted">(${escapeHtml(r.vendor_name)})</span>` : ''}
                <div class="small text-muted">Type: ${escapeHtml(r.type)} | Date: ${escapeHtml(r.date)}</div>
              </div>
              <div class="fw-bold">${money(r.amount)}</div>
            </li>
          `)}
        </div>
        <div class="col-lg-6">
          ${section('Purchase Payments', d.purchase_payments, r => `
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                #${r.id} - Purchase #${r.purchase_id}
                ${r.vendor_name ? `<span class="text-muted">(${escapeHtml(r.vendor_name)})</span>` : ''}
                <div class="small text-muted">Route: ${escapeHtml(r.route)} | Paid At: ${escapeHtml(r.paid_at)}</div>
              </div>
              <div class="fw-bold">${money(r.amount)}</div>
            </li>
          `)}
        </div>
        <div class="col-lg-6">
          ${section('Expenses', d.expenses, r => `
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                #${r.id} - ${escapeHtml(r.description || 'Expense')}
                ${r.vendor_name ? `<span class="text-muted">(${escapeHtml(r.vendor_name)})</span>` : ''}
                <div class="small text-muted">Type: ${escapeHtml(r.type)} | Date: ${escapeHtml(r.date)}</div>
              </div>
              <div class="fw-bold">${money(r.amount)}</div>
            </li>
          `)}
        </div>
        <div class="col-lg-6">
          ${section('Expense Payments', d.expense_payments, r => `
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                #${r.id} - Expense #${r.expense_id}
                ${r.vendor_name ? `<span class="text-muted">(${escapeHtml(r.vendor_name)})</span>` : ''}
                <div class="small text-muted">Route: ${escapeHtml(r.route)} | Paid At: ${escapeHtml(r.paid_at)}</div>
              </div>
              <div class="fw-bold">${money(r.amount)}</div>
            </li>
          `)}
        </div>
        <div class="col-12">
          ${section('Order Payments', d.order_payments, r => `
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                #${r.id} - Order #${r.order_id}
                ${r.customer_name ? `<span class="text-muted">(${escapeHtml(r.customer_name)})</span>` : ''}
                <div class="small text-muted">Method: ${escapeHtml(r.payment_method)} | Paid At: ${escapeHtml(r.paid_at)}</div>
              </div>
              <div class="fw-bold">${money(r.amount)}</div>
            </li>
          `)}
        </div>
      </div>
    `;
}

// Bindings
(function(){
    document.addEventListener('DOMContentLoaded', function() {
        if (!document.getElementById('purchases-body').hasChildNodes()) {
            clearJournalForm();
        }
    });

    document.getElementById('dailyJournalModal')?.addEventListener('shown.bs.modal', () => {
        loadDailiesOptions();
    });
    document.getElementById('refresh-dailies-options')?.addEventListener('click', () => {
        loadDailiesOptions();
    });

    document.getElementById('add-purchase-row')?.addEventListener('click', addPurchaseRow);
    document.getElementById('add-purchase-payment-row')?.addEventListener('click', addPurchasePaymentRow);
    document.getElementById('add-expense-row')?.addEventListener('click', addExpenseRow);
    document.getElementById('add-expense-payment-row')?.addEventListener('click', addExpensePaymentRow);
    document.getElementById('add-order-payment-row')?.addEventListener('click', addOrderPaymentRow);

    document.addEventListener('click', function(ev){
        if (ev.target.closest && ev.target.closest('.remove-row')) {
            ev.preventDefault();
            const tr = ev.target.closest('tr');
            if (tr) tr.remove();
        }
    });

    document.getElementById('save-journal-btn')?.addEventListener('click', function() {
        const payload = collectJournalPayload();
        const totalCount = payload.purchases.length + payload.purchase_payments.length + payload.expenses.length + payload.expense_payments.length + payload.order_payments.length;
        if (totalCount === 0) {
            showJournalAlert('warning', 'Please add at least one entry before saving.');
            return;
        }
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';

        fetch('/includes/dailies.php?action=save-journal', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.error || 'Save failed');
            const s = data.summary || {};
            showJournalAlert('success',
                `Saved! Entries -> Purchases: ${s.purchases?.count || 0} (${money(s.purchases?.total || 0)}), ` +
                `Purchase Payments: ${s.purchase_payments?.count || 0} (${money(s.purchase_payments?.total || 0)}), ` +
                `Expenses: ${s.expenses?.count || 0} (${money(s.expenses?.total || 0)}), ` +
                `Expense Payments: ${s.expense_payments?.count || 0} (${money(s.expense_payments?.total || 0)}), ` +
                `Order Payments: ${s.order_payments?.count || 0} (${money(s.order_payments?.total || 0)})`
            );
            // Refresh option lists so dues reflect newly saved entries
            loadDailiesOptions();
        })
        .catch(err => {
            console.error(err);
            showJournalAlert('danger', err.message || 'Error saving journal');
        })
        .finally(() => {
            const btn = document.getElementById('save-journal-btn');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Journal';
        });
    });

    document.getElementById('generate-report-btn')?.addEventListener('click', function() {
        const date = document.getElementById('report-date').value || todayDate();
        const opening = parseFloat(document.getElementById('report-opening').value || '0') || 0;
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Calculating...';

        fetch(`/includes/dailies.php?action=report&date=${encodeURIComponent(date)}&opening=${encodeURIComponent(opening)}`, {credentials:'same-origin'})
            .then(r => r.json())
            .then(data => {
                renderReportSummary(data);
                renderReportDetails(data);
                if (data.success) {
                    document.getElementById('print-report-btn')?.classList.remove('d-none');
                }
            })
            .catch(err => {
                console.error(err);
                showReportAlert('danger', err.message || 'Failed to generate report');
            })
            .finally(() => {
                const btn = document.getElementById('generate-report-btn');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-calculator me-1"></i>Generate';
            });
    });

    document.getElementById('print-report-btn')?.addEventListener('click', function() {
        const summary = document.getElementById('report-summary').innerHTML;
        const details = document.getElementById('report-details').innerHTML;
        const date = document.getElementById('report-date').value || todayDate();

        const win = window.open('', 'printReport', 'width=1024,height=768');
        win.document.write(`
            <html>
            <head>
              <title>Daily Report - ${date}</title>
              <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
              <style>body{padding:20px}.card{margin-bottom:12px}</style>
            </head>
            <body>
              <h3>Daily Report - ${date}</h3>
              <div class="row g-3">${summary}</div>
              <hr>
              <div>${details}</div>
              <script>window.onload=function(){window.print();}</script>
            </body>
            </html>
        `);
        win.document.close();
    });
})();
</script>