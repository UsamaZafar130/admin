<?php
require_once '../../includes/auth_check.php';
require_once '../../includes/db_connection.php';

require_once __DIR__ . '/../../barcode/codegen/src/Barcode.php';
require_once __DIR__ . '/../../barcode/codegen/src/BarcodeBar.php';
require_once __DIR__ . '/../../barcode/codegen/src/BarcodeGenerator.php';
require_once __DIR__ . '/../../barcode/codegen/src/BarcodeGeneratorPNG.php';
require_once __DIR__ . '/../../barcode/codegen/src/Types/TypeInterface.php';
require_once __DIR__ . '/../../barcode/codegen/src/Types/TypeCode128.php';

use Picqer\Barcode\BarcodeGeneratorPNG;

// --- Input: order_ids from GET param ---
if (!isset($_GET['order_ids']) || !preg_match('/^[\d,]+$/', $_GET['order_ids'])) {
    die('<div class="error-red-center">No orders selected for label printing. <a href="list.php" class="btn btn-sm btn-danger">Back</a></div>');
}
$order_ids = array_filter(array_map('intval', explode(',', $_GET['order_ids'])));
if (!$order_ids) {
    die('<div class="error-red-center">No valid orders selected for label printing. <a href="list.php" class="btn btn-sm btn-danger">Back</a></div>');
}
$in = implode(',', array_fill(0, count($order_ids), '?'));

// --- Fetch items in selected orders ---
$stmt = $pdo->prepare("SELECT oi.item_id, i.name as item_name, oi.pack_size, SUM(oi.qty) as qty, i.price_per_unit
    FROM order_items oi
    JOIN items i ON oi.item_id = i.id
    WHERE oi.order_id IN ($in)
    GROUP BY oi.item_id, oi.pack_size
    ORDER BY i.name, oi.pack_size");
$stmt->execute($order_ids);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Barcode logic ---
function getFirstLetter($name) {
    $words = preg_split('/\s+/', strtoupper(trim($name)));
    return isset($words[0][0]) ? $words[0][0] : 'X';
}
function getSmartShort($name) {
    $words = preg_split('/\s+/', strtoupper(trim($name)));
    if (count($words) == 1) return substr($words[0], 0, 2);
    if (count($words) == 2) return strtoupper(substr($words[1], 0, 2));
    return strtoupper(substr($words[1], 0, 1) . substr($words[2], 0, 1));
}

$labels = [];
foreach ($order_items as $item) {
    $item_id = $item['item_id'];
    $item_name = trim($item['item_name']);
    $pack_size = (int)$item['pack_size'];
    $qty = (int)$item['qty'];
    $price_per_unit = isset($item['price_per_unit']) ? (float)$item['price_per_unit'] : 0;
    if ($pack_size <= 0 || empty($item_name) || empty($item_id)) continue;
    $packs = (int)ceil($qty / $pack_size);

    $pack_code = str_pad($pack_size, 2, '0', STR_PAD_LEFT);
    $first_letter = getFirstLetter($item_name);
    $short = getSmartShort($item_name);

    $price_total = (int)round($price_per_unit * $pack_size);
    $price_code = str_pad($price_total, 4, '0', STR_PAD_LEFT);

    $stmt_code = $pdo->prepare("SELECT barcode FROM item_pack_codes WHERE item_id = ? AND pack_size = ? AND barcode LIKE ?");
    $search_pattern = "{$pack_code}{$first_letter}__{$short}{$price_code}";
    $stmt_code->execute([$item_id, $pack_size, $search_pattern]);
    $existing_code = $stmt_code->fetchColumn();

    if ($existing_code) {
        $final_code = $existing_code;
    } else {
        do {
            $random = str_pad(mt_rand(0, 99), 2, '0', STR_PAD_LEFT);
            $candidate_code = "{$pack_code}{$first_letter}{$random}{$short}{$price_code}";
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM item_pack_codes WHERE item_id = ? AND pack_size = ? AND barcode = ?");
            $stmt_check->execute([$item_id, $pack_size, $candidate_code]);
            $exists = $stmt_check->fetchColumn();
        } while ($exists);

        $stmt_insert = $pdo->prepare("INSERT INTO item_pack_codes (item_id, pack_size, barcode, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
        $stmt_insert->execute([$item_id, $pack_size, $candidate_code]);
        $final_code = $candidate_code;
    }

    for ($i = 0; $i < $packs; $i++) {
        $labels[] = [
            'barcode' => $final_code,
            'item_label' => $item_name . ' - ' . $pack_size
        ];
    }
}

// --- Generate barcode images ---
$barcodeGenerator = new BarcodeGeneratorPNG();
$labels_data = [];
foreach ($labels as $label) {
    $labels_data[] = [
        'barcode' => $label['barcode'],
        'item_label' => $label['item_label'],
        'barcode_img' => 'data:image/png;base64,' . base64_encode($barcodeGenerator->getBarcode($label['barcode'], $barcodeGenerator::TYPE_CODE_128))
    ];
}

<?php
$pageTitle = "Packing Labels";
require_once '../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="text-primary"><i class="fa fa-tag me-2"></i>Print Packing Labels</h2>
            <p class="text-muted">Configure label printing options for selected orders.</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="list.php" class="btn btn-secondary btn-3d"><i class="fa fa-arrow-left me-1"></i>Back to Inventory</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form id="labelConfigForm" autocomplete="off">
                <h5 class="card-title mb-4">🎫 Label Configuration</h5>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="type" value="a4" checked id="radio-a4">
                            <label class="form-check-label" for="radio-a4">
                                <strong>A4 Sheet</strong> <span class="text-muted">(Plain, 70x45mm)</span>
                                <div class="text-muted small">Best for office printer, cut labels after print.</div>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="type" value="barcode" id="radio-barcode">
                            <label class="form-check-label" for="radio-barcode">
                                <strong>Barcode Label Printer</strong> <span class="text-muted">(58x40mm)</span>
                                <div class="text-muted small">Best for dedicated sticker/barcode printer.</div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-primary btn-3d">
                        <i class="fa fa-print me-1"></i>Continue to Print
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const labelsData = <?php echo json_encode($labels_data); ?>;

document.addEventListener('DOMContentLoaded', function() {
    function calcItemFontSize(text, widthPx, heightPx) {
        let availableH = heightPx * 0.33, availableW = widthPx * 0.90;
        let len = text.length;
        let fontSize = availableH * 0.60;
        let charsPerLine = Math.floor(availableW / (fontSize*0.60));
        let lines = Math.ceil(len / charsPerLine);
        while (lines > 2 && fontSize > 8) {
            fontSize *= 0.95;
            charsPerLine = Math.floor(availableW / (fontSize*0.60));
            lines = Math.ceil(len / charsPerLine);
        }
        fontSize = Math.max(10, Math.min(fontSize, availableH * (1 / lines)));
        return Math.round(fontSize);
    }

    document.getElementById('labelConfigForm').addEventListener('submit', function(e) {
        e.preventDefault();
        let type = this.type.value;
        let labelWidthPx, labelHeightPx, cols, rows, labelMarginPx, title;
        if(type === "a4") {
            labelWidthPx = Math.round(70 * 3.78);
            labelHeightPx = Math.round(45 * 3.78);
            cols = Math.floor(210 / 70);
            rows = Math.floor(297 / 45);
            labelMarginPx = 0;
            title = "Print Packing Labels (A4)";
        } else {
            labelWidthPx = Math.round(58 * 3.78);
            labelHeightPx = Math.round(40 * 3.78);
            cols = 1;
            rows = 1;
            labelMarginPx = 8;
            title = "Print Packing Labels (58x40mm)";
        }

        let cssVars = `:root{--label-width:${labelWidthPx}px;--label-height:${labelHeightPx}px;--label-margin:${labelMarginPx}px;}`;
        let printHTML = `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>${title}</title>
    <style>
        ${cssVars}
        @media print { body{background:#fff;} .label{page-break-inside:avoid;} }
        body{background:#fff;margin:0;}
        .label-sheet{display:grid;grid-template-columns:repeat(${cols},1fr);}
        .label{
            background:#fff;
            border:1.2px solid #b9de7e;
            border-radius:7px;
            color:#222;
            width:var(--label-width);
            min-height:var(--label-height);
            margin:var(--label-margin);
            padding:0;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:flex-start;
            font-family:'Segoe UI',sans-serif;
            box-shadow:none;
            position:relative;
            box-sizing:border-box;
            overflow:hidden;
        }
        .barcode-code{
            font-family:'monospace';
            background:#f8fff5;
            color:#198754;
            padding:2px 7px 0 7px;
            margin:0 0 2px 0;
            border-radius:2px;
            letter-spacing:0.06em;
            overflow-wrap:anywhere;
            word-break:break-word;
            max-width:100%;
            line-height:1.15em;
            font-size:15px;
            text-align:center;
            font-weight:bold;
            min-height:22px;
            box-sizing:border-box;
        }
        .barcode-img{
            display:block;
            width:96%;
            max-width:100%;
            margin:0 auto 0 auto;
            background:#fff;
            border-radius:2px;
            object-fit:contain;
            transition:height 0.2s;
        }
        .item-label{
            font-weight:bold;
            overflow-wrap:anywhere;
            word-break:break-word;
            max-width:94%;
            line-height:1.13em;
            text-align:center;
            flex-shrink:0;
            margin:0 auto 0 auto;
            padding-bottom:2px;
        }
        .label-content{
            width:100%;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:flex-end;
            flex:1 1 auto;
            box-sizing:border-box;
        }
    </style>
</head>
<body>
`;

        function labelBlock(data) {
            const name = data.item_label;
            let nameFont = calcItemFontSize(name, labelWidthPx, labelHeightPx);
            let availableW = labelWidthPx * 0.90, availableH = labelHeightPx * 0.33;
            let charsPerLine = Math.floor(availableW / (nameFont*0.60));
            let lines = Math.ceil(name.length / charsPerLine);
            let imgH = labelHeightPx * (lines === 1 ? 0.37 : lines === 2 ? 0.30 : 0.25);
            imgH = Math.max(imgH, 26);
            let padTop = Math.round(labelHeightPx * 0.05);
            let padBot = Math.round(labelHeightPx * 0.07);

            return `
<div class="label" style="padding-top:${padTop}px;padding-bottom:${padBot}px;">
    <div class="barcode-code">${data.barcode}</div>
    <img class="barcode-img" src="${data.barcode_img}" alt="Barcode"
        style="height:${Math.round(imgH)}px;">
    <div class="item-label" style="font-size:${nameFont}px;min-height:${Math.round(availableH)}px;max-height:${Math.round(availableH*1.1)}px;display:flex;align-items:center;justify-content:center;">${name}</div>
</div>`;
        }

        if (type === "a4") {
            let perPage = cols * rows, total = labelsData.length, idx = 0;
            while (idx < total) {
                printHTML += `<div class="label-sheet" style="page-break-after: always;">`;
                for (let i = 0; i < perPage && idx < total; i++, idx++) {
                    printHTML += labelBlock(labelsData[idx]);
                }
                printHTML += `</div>`;
            }
        } else {
            printHTML += `<div class="label-sheet" style="grid-template-columns:repeat(${cols},1fr);">`;
            for (let i = 0; i < labelsData.length; i++) {
                printHTML += labelBlock(labelsData[i]);
            }
            printHTML += `</div>`;
        }

        printHTML += `
<script>
window.onload = function() {
    window.print();
    setTimeout(function(){ window.close(); }, 500);
};
<\/script>
</body>
</html>`;

        var printWin = window.open('', '_blank');
        printWin.document.write(printHTML);
        printWin.document.close();
        document.getElementById('labelConfigModal').style.display = 'none';
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>