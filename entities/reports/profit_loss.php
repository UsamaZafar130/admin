<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Profit & Loss";
require_once __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

// Get last 8 months of data for the chart
$monthlyData = [];
for ($i = 7; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthLabel = format_datetime(date('Y-m-01', strtotime("-$i months")), get_user_timezone(), 'M Y');
    $startDate = $month . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));
    
    // Use centralized financial calculation functions with default raw material value of 0
    $financial_data = get_financial_summary($pdo, $startDate, $endDate, 0);
    
    $monthlyData[] = [
        'month' => $month,
        'label' => $monthLabel,
        'revenue' => $financial_data['revenue'],
        'purchases' => $financial_data['purchases'],
        'expenses' => $financial_data['operating_expenses'],
        'profit' => $financial_data['net_profit']
    ];
}

// Get existing P&L reports from database
$stmt = $pdo->prepare("
    SELECT id, report_month, report_date, net_profit, created_at
    FROM profit_loss 
    ORDER BY report_month DESC
");
$stmt->execute();
$existingReports = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-content-wrapper">
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><i class="fas fa-chart-line me-2"></i>Profit & Loss</h2>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-success btn-3d" data-bs-toggle="modal" data-bs-target="#pnlModal">
                    <i class="fas fa-calculator me-1"></i>Show Profit & Loss
                </button>
            </div>
        </div>

        <!-- Monthly Revenue and Profit Chart -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-area me-2"></i>Monthly Profit Trend (Last 8 Months)</h6>
            </div>
            <div class="card-body">
                <canvas id="profitChart" height="200"></canvas>
            </div>
        </div>

        <!-- Existing Reports -->
        <?php if (!empty($existingReports)): ?>
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-history me-2"></i>Previous Reports</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="reports-table">
                        <thead class="table-light">
                            <tr>
                                <th>Report Month</th>
                                <th>Report Date</th>
                                <th>Net Profit</th>
                                <th>Generated On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($existingReports as $report): ?>
                            <tr>
                                <td><?= format_datetime($report['report_month'] . '-01', get_user_timezone(), 'F Y') ?></td>
                                <td><?= format_datetime($report['report_date'], get_user_timezone(), 'M d, Y') ?></td>
                                <td class="<?= $report['net_profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= format_currency($report['net_profit']) ?>
                                </td>
                                <td><?= format_datetime($report['created_at'], get_user_timezone(), 'M d, Y g:i A') ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="showReport(<?= $report['id'] ?>)">
                                        <i class="fas fa-eye me-1"></i>View
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" onclick="printReport(<?= $report['id'] ?>)">
                                        <i class="fas fa-print me-1"></i>Print
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- P&L Generation Modal -->
<div class="modal fade" id="pnlModal" tabindex="-1" aria-labelledby="pnlModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pnlModalLabel">
                    <i class="fas fa-calculator me-2"></i>Generate Profit & Loss Report
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Show Previous Report -->
                    <div class="col-md-6">
                        <div class="card border-info">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="fas fa-history me-2"></i>Show Previous Report</h6>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">View a previously generated monthly P&L report.</p>
                                <div class="mb-3">
                                    <label class="form-label">Select Report:</label>
                                    <select class="form-select" id="previousReportSelect">
                                        <option value="">Choose a report...</option>
                                        <?php foreach ($existingReports as $report): ?>
                                        <option value="<?= $report['id'] ?>">
                                            <?= format_datetime($report['report_month'] . '-01', get_user_timezone(), 'F Y') ?> 
                                            (<?= format_currency($report['net_profit']) ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="button" class="btn btn-info" onclick="showPreviousReport()">
                                    <i class="fas fa-eye me-1"></i>Show Report
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Generate New Report -->
                    <div class="col-md-6">
                        <div class="card border-success">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="fas fa-plus me-2"></i>Generate New Report</h6>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Create a new P&L report with current data.</p>
                                
                                <div class="mb-3">
                                    <label class="form-label">Report Type:</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="reportType" id="todayReport" value="today" checked>
                                        <label class="form-check-label" for="todayReport">
                                            <strong>As of Today</strong><br>
                                            <small class="text-muted">Current snapshot (not stored)</small>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="reportType" id="monthlyReport" value="monthly">
                                        <label class="form-check-label" for="monthlyReport">
                                            <strong>Monthly P&L</strong><br>
                                            <small class="text-muted">Store for specific month</small>
                                        </label>
                                    </div>
                                </div>

                                <div id="monthSelection" class="mb-3" style="display: none;">
                                    <label class="form-label">Select Month:</label>
                                    <input type="month" class="form-control" id="reportMonth" max="<?= date('Y-m', strtotime('last month')) ?>">
                                    <small class="form-text text-muted">Only completed months are available for Monthly P&L reports</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Raw Material in Stock (Value):</label>
                                    <input type="number" class="form-control" id="rawMaterialValue" step="0.01" min="0" value="0" placeholder="Enter current stock value (0 if none)" required>
                                    <small class="form-text text-muted">Enter the current value of raw materials in stock (use 0 if nothing in hand)</small>
                                </div>

                                <button type="button" class="btn btn-success" onclick="generateNewReport()">
                                    <i class="fas fa-calculator me-1"></i>Generate Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Display Modal -->
<div class="modal fade" id="reportDisplayModal" tabindex="-1" aria-labelledby="reportDisplayModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reportDisplayModalLabel">
                    <i class="fas fa-file-invoice me-2"></i>Profit & Loss Report
                </h5>
                <div class="ms-auto">
                    <button type="button" class="btn btn-success btn-sm me-2" onclick="printCurrentReport()">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                    <button type="button" class="btn btn-info btn-sm me-2" onclick="shareOnWhatsApp()">
                        <i class="fab fa-whatsapp me-1"></i>Share
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body" id="reportContent">
                <!-- Report content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable for reports
    <?php if (!empty($existingReports)): ?>
    $('#reports-table').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[0, 'desc']]
    });
    <?php endif; ?>

    // Create profit chart
    createProfitChart();

    // Handle report type change
    $('input[name="reportType"]').change(function() {
        if ($(this).val() === 'monthly') {
            $('#monthSelection').show();
            $('#reportMonth').attr('required', true);
        } else {
            $('#monthSelection').hide();
            $('#reportMonth').attr('required', false);
        }
    });
});

function createProfitChart() {
    const monthlyData = <?= json_encode($monthlyData) ?>;
    
    const ctx = document.getElementById('profitChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: monthlyData.map(item => item.label),
            datasets: [{
                label: 'Monthly Profit',
                data: monthlyData.map(item => item.profit),
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rs. ' + value.toLocaleString();
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Profit: Rs. ' + context.raw.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

function showPreviousReport() {
    const reportId = $('#previousReportSelect').val();
    if (!reportId) {
        alert('Please select a report to view.');
        return;
    }
    
    // Load and show the report
    $.get('/entities/reports/api/get_report.php', { id: reportId })
        .done(function(data) {
            $('#reportContent').html(data);
            $('#pnlModal').modal('hide');
            $('#reportDisplayModal').modal('show');
        })
        .fail(function() {
            alert('Error loading report. Please try again.');
        });
}

function generateNewReport() {
    const reportType = $('input[name="reportType"]:checked').val();
    const rawMaterialValue = parseFloat($('#rawMaterialValue').val());
    const reportMonth = $('#reportMonth').val();
    
    if (!rawMaterialValue || isNaN(rawMaterialValue) || rawMaterialValue < 0) {
        alert('Please enter a valid raw material stock value.');
        return;
    }
    
    if (reportType === 'monthly' && !reportMonth) {
        alert('Please select a month for the monthly report.');
        return;
    }
    
    // Show loading
    const originalBtn = $(event.target);
    const originalText = originalBtn.html();
    originalBtn.html('<i class="fas fa-spinner fa-spin me-1"></i>Generating...').prop('disabled', true);
    
    // Generate report
    $.post('/entities/reports/api/generate_report.php', {
        type: reportType,
        month: reportMonth,
        raw_material_value: rawMaterialValue
    })
    .done(function(data) {
        $('#reportContent').html(data);
        $('#pnlModal').modal('hide');
        $('#reportDisplayModal').modal('show');
        
        // Refresh page if it was a monthly report (to update the list)
        if (reportType === 'monthly') {
            setTimeout(() => location.reload(), 1000);
        }
    })
    .fail(function() {
        alert('Error generating report. Please try again.');
    })
    .always(function() {
        originalBtn.html(originalText).prop('disabled', false);
    });
}

function showReport(reportId) {
    $.get('/entities/reports/api/get_report.php', { id: reportId })
        .done(function(data) {
            $('#reportContent').html(data);
            $('#reportDisplayModal').modal('show');
        })
        .fail(function() {
            alert('Error loading report. Please try again.');
        });
}

function printReport(reportId) {
    window.open('/entities/reports/print_report.php?id=' + reportId, '_blank');
}

function printCurrentReport() {
    // Get the current report data to generate a clean print window
    const reportType = $('input[name="reportType"]:checked').val();
    const rawMaterialValue = $('#rawMaterialValue').val();
    const reportMonth = $('#reportMonth').val();
    
    // Create a form to post the data to a new print window
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/entities/reports/print_current_report.php';
    form.target = '_blank';
    
    // Add form fields
    const typeField = document.createElement('input');
    typeField.type = 'hidden';
    typeField.name = 'type';
    typeField.value = reportType;
    form.appendChild(typeField);
    
    if (reportMonth) {
        const monthField = document.createElement('input');
        monthField.type = 'hidden';
        monthField.name = 'month';
        monthField.value = reportMonth;
        form.appendChild(monthField);
    }
    
    const rawMaterialField = document.createElement('input');
    rawMaterialField.type = 'hidden';
    rawMaterialField.name = 'raw_material_value';
    rawMaterialField.value = rawMaterialValue;
    form.appendChild(rawMaterialField);
    
    // Submit form to new window
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function shareOnWhatsApp() {
    // Extract report data from the modal content
    const reportContent = $('#reportContent');
    
    // Try to extract values from the report content
    let reportLabel = reportContent.find('h4').first().text() || 'Current Report';
    let revenue = '0';
    let grossProfit = '0';
    let netProfit = '0';
    let grossMargin = '0%';
    let netMargin = '0%';
    
    // Extract values from table if available
    reportContent.find('table tr').each(function() {
        const text = $(this).text();
        if (text.includes('Sales Revenue')) {
            revenue = $(this).find('td:last').text().trim();
        } else if (text.includes('GROSS PROFIT')) {
            grossProfit = $(this).find('td:last').text().trim();
        } else if (text.includes('NET PROFIT')) {
            netProfit = $(this).find('td:last').text().trim();
        }
    });
    
    // Extract margins from margin cards if available
    reportContent.find('.card-body').each(function() {
        const text = $(this).text();
        if (text.includes('GROSS MARGIN')) {
            grossMargin = $(this).find('h4').text().trim();
        } else if (text.includes('NET MARGIN')) {
            netMargin = $(this).find('h4').text().trim();
        }
    });
    
    // Determine profit status icons
    const netProfitValue = parseFloat(netProfit.replace(/[^0-9.-]+/g, ''));
    const grossProfitValue = parseFloat(grossProfit.replace(/[^0-9.-]+/g, ''));
    const profitIcon = netProfitValue >= 0 ? '📈' : '📉';
    const grossIcon = grossProfitValue >= 0 ? '💚' : '❌';
    
    const message = `🏢 *PROFIT & LOSS STATEMENT*\n` +
                  `📅 *Period:* ${reportLabel}\n` +
                  `⏰ *Generated:* ${new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' })}\n\n` +
                  `┌─────────────────────────┐\n` +
                  `│        📊 SUMMARY        │\n` +
                  `└─────────────────────────┘\n` +
                  `💰 *Revenue:* ${revenue}\n` +
                  `${grossIcon} *Gross Profit:* ${grossProfit} (${grossMargin})\n` +
                  `${profitIcon} *Net Profit:* ${netProfit} (${netMargin})\n\n` +
                  `┌─────────────────────────┐\n` +
                  `│       📈 PERFORMANCE     │\n` +
                  `└─────────────────────────┘\n` +
                  `📊 *Gross Margin:* ${grossMargin}\n` +
                  `📈 *Net Margin:* ${netMargin}\n\n` +
                  `_Generated via Admin System_`;
    
    const url = 'https://wa.me/?text=' + encodeURIComponent(message);
    window.open(url, '_blank');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>