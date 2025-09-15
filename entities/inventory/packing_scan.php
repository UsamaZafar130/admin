<?php
include_once __DIR__ . '/../../includes/auth_check.php';
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="text-primary"><i class="fa fa-barcode me-2"></i> Scan Packs</h2>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-success btn-3d" id="btn-open-camera-scan">
                <i class="fa fa-camera me-1"></i> Open Camera
            </button>
        </div>
    </div>
    <div class="alert alert-info mb-4">
        <strong>Barcode Scanning for Packing Management.</strong> Use the barcode field below to scan or manually enter product codes.<br>
        Camera scanning is available for mobile devices and webcam-enabled browsers. All scanned items are tracked in real-time.
    </div>
    <div class="mb-3">
        <label for="barcode-input" class="form-label">Scan Barcode</label>
        <div class="row">
            <div class="col-md-8">
                <input type="text" id="barcode-input" class="form-control" autocomplete="off" placeholder="Scan or enter barcode here" autofocus>
            </div>
        </div>
    </div>
    <div class="modal fade" id="camera-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="cameraModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header py-2">
            <h5 class="modal-title modal-title-camera" id="cameraModalLabel"><i class="fa fa-camera"></i> Camera Scan</h5>
            <button type="button" class="btn-close" id="btn-close-camera" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body modal-body-camera">
            <div id="camera-container" class="camera-container">
                <video id="barcode-video" width="280" height="120"
                    class="camera-video"
                    autoplay playsinline></video>
                <div id="scan-feedback-overlay"
                    class="camera-overlay">
                    <div id="scan-feedback"
                        class="alert"
                        style="position:absolute; left:50%; top:50%; transform:translate(-50%,-50%);
                            min-width:200px; max-width:95vw; text-align:center; font-size:1.2em;
                            background:#fff; box-shadow:0 0 18px 3px #2223; color:#222; pointer-events:auto; border-radius:10px;">
                    </div>
                </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-4">
        <table class="entity-table table table-striped table-hover table-consistent" id="scan-list-table">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Item & Pack Size</th>
                    <th>Time Scanned</th>
                    <th>Packs Scanned (Session)</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <div class="form-actions" style="margin-top:18px;">
        <a href="/entities/inventory/" class="btn btn-secondary">Back</a>
    </div>
</div>

<!-- ZXing barcode scanner -->
<script src="https://unpkg.com/@zxing/browser@0.1.1"></script>

<script>
(function() {
    let serial = 1;
    let scanTable = null;
    // Track scanned barcodes for session: barcode => {count, itemName, packSize, time}
    let scannedPacks = {};

    // PAUSE LOGIC: Prevent scanning new codes during the feedback display (e.g. 2 seconds)
    let scanPaused = false;
    let scanPauseTimeout = null;

    function initDataTable() {
        if ($.fn.DataTable.isDataTable('#scan-list-table')) {
            return $('#scan-list-table').DataTable();
        }
        scanTable = $('#scan-list-table').DataTable({
            paging: true,
            searching: true,
            info: true,
            pageLength: 50,
            ordering: false,
            language: {
                emptyTable: "No data available in table",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                infoEmpty: "Showing 0 to 0 of 0 entries"
            },
            columnDefs: [
                { targets: 3, orderable: false }
            ]
        });
        return scanTable;
    }

    $(document).ready(function() {
        scanTable = initDataTable();
    });

    // Add/update scan in the list (session only)
    function addOrUpdateScanInList(barcode, itemName, packSize) {
        let now = new Date().toLocaleTimeString();
        if (scannedPacks[barcode]) {
            // Already scanned, update count and time
            scannedPacks[barcode].count++;
            scannedPacks[barcode].time = now;
            let $table = $('#scan-list-table');
            let $tbody = $table.find('tbody');
            let rows = $tbody.find('tr');
            rows.each(function() {
                let $row = $(this);
                if ($row.data('barcode') === barcode) {
                    $row.find('td').eq(2).text(now); // Update time scanned
                    $row.find('td').eq(3).text(scannedPacks[barcode].count); // Update packs scanned
                }
            });
        } else {
            scannedPacks[barcode] = {
                count: 1,
                itemName: itemName,
                packSize: packSize,
                time: now,
                serial: serial
            };
            let $table = $('#scan-list-table');
            if ($.fn.DataTable.isDataTable($table)) {
                $table.DataTable().destroy();
            }
            let $tbody = $table.find('tbody');
            $tbody.append(
                `<tr data-barcode="${barcode}">
                    <td>${serial}</td>
                    <td>${itemName} ${packSize}</td>
                    <td>${now}</td>
                    <td>1</td>
                </tr>`
            );
            serial++;
            scanTable = $table.DataTable({
                paging: true,
                searching: true,
                info: true,
                pageLength: 50,
                ordering: false,
                language: {
                    emptyTable: "No data available in table",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries"
                },
                columnDefs: [
                    { targets: 3, orderable: false }
                ]
            });
        }
    }

    function postBarcode(barcode) {
        if (!barcode) return;
        hideScanFeedback();
        $.post('actions.php', {action: 'scan_pack_barcode', barcode: barcode}, function(resp) {
            if (resp.success) {
                addOrUpdateScanInList(barcode, resp.item_name, resp.pack_size);
                showScanFeedback(resp.item_name + ' ' + resp.pack_size + ' Scanned', false);
            } else {
                showScanFeedback(resp.error || 'Error logging pack!', true);
            }
        }, 'json');
    }

    function showScanFeedback(msg, isError) {
        let $overlay = $('#scan-feedback-overlay');
        let $box = $('#scan-feedback');
        if(isError){
            $box.removeClass('alert-success').addClass('alert-danger');
            $overlay.css('background', '#fff');
        }else{
            $box.removeClass('alert-danger').addClass('alert-success');
            $overlay.css('background', '#fff');
        }
        $box.text(msg);
        $overlay.show();
        // PAUSE SCANNING for 2 seconds (success) or 4 seconds (error)
        scanPaused = true;
        if(scanPauseTimeout) clearTimeout(scanPauseTimeout);
        scanPauseTimeout = setTimeout(function() {
            scanPaused = false;
            hideScanFeedback();
        }, isError ? 4000 : 2000);
    }
    function hideScanFeedback() {
        $('#scan-feedback-overlay').hide();
    }

    $('#barcode-input').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            if(scanPaused) return; // Prevent input if paused
            let barcode = $(this).val().trim();
            if (!barcode) return;
            postBarcode(barcode);
            $(this).val('');
        }
    });

    // Camera modal logic
    $('#btn-open-camera-scan').on('click', function() {
        $('#camera-modal').modal({backdrop:'static', keyboard:false});
        setTimeout(startCameraScanner, 400);
        window.__suppressModalRefresh = false;
    });

    $('#btn-close-camera').on('click', function(e) {
        e.preventDefault();
        stopCameraScanner();
        window.__suppressModalRefresh = true; // Suppress refresh
        $('#camera-modal').modal('hide');
        setTimeout(function() {
            $('#barcode-input').focus();
            window.__suppressModalRefresh = false; // Reset after focus
        }, 350);
    });

    $('#camera-modal').on('hidden.bs.modal', function(e) {
        if (window.__suppressModalRefresh) {
            e.stopImmediatePropagation();
            return false;
        }
    });

    let lastBarcode = "";
    let codeReader = null;
    let videoInputDeviceId = null;
    let scannerActive = false;

    async function startCameraScanner() {
        if (scannerActive) return;
        scannerActive = true;

        const { BrowserMultiFormatReader } = ZXingBrowser;
        codeReader = new BrowserMultiFormatReader();

        try {
            const videoInputDevices = await BrowserMultiFormatReader.listVideoInputDevices();
            let backCam = videoInputDevices[0]?.deviceId || null;
            for (let i = 0; i < videoInputDevices.length; i++) {
                let label = (videoInputDevices[i].label || '').toLowerCase();
                if (label.includes('back') || label.includes('environment')) {
                    backCam = videoInputDevices[i].deviceId;
                    break;
                }
            }
            videoInputDeviceId = backCam;
            codeReader.decodeFromVideoDevice(videoInputDeviceId, 'barcode-video', (result, err, controls) => {
                if (result) {
                    let barcode = result.getText();
                    if (barcode && barcode !== lastBarcode && !scanPaused) {
                        lastBarcode = barcode;
                        postBarcode(barcode);
                        // Prevent further scans until pause is over
                        scanPaused = true;
                        // After pause, allow next scan
                        if(scanPauseTimeout) clearTimeout(scanPauseTimeout);
                        scanPauseTimeout = setTimeout(function() {
                            scanPaused = false;
                            lastBarcode = ""; // allow same barcode again after pause
                        }, 2000);
                    }
                }
            });
        } catch (e) {
            showScanFeedback('Could not start camera: ' + e, true);
            scannerActive = false;
        }
    }

    function stopCameraScanner() {
        if (codeReader && typeof codeReader.stopContinuousDecode === 'function') {
            codeReader.stopContinuousDecode();
        } else if (codeReader && typeof codeReader.reset === 'function') {
            codeReader.reset();
        } else if (codeReader && typeof codeReader.stopDecoding === 'function') {
            codeReader.stopDecoding();
        }
        codeReader = null;
        scannerActive = false;
        let video = document.getElementById('barcode-video');
        if (video && video.srcObject) {
            let tracks = video.srcObject.getTracks();
            tracks.forEach(track => track.stop());
            video.srcObject = null;
        }
    }

    setTimeout(function(){ $('#barcode-input').focus(); }, 500);

    let style = document.createElement('style');
    style.innerHTML = `
        .scanned-focus { background: #e4ffe4 !important; transition: background 0.5s; }
        #barcode-input:focus { border-color:#09c; box-shadow:0 0 2px #09c; }
        #camera-container { border: 2px solid #3de13d; border-radius: 8px; box-shadow: 0 0 14px #3de13d22; }
        #btn-close-camera:hover { color:#a00; background:#fff4; }
        @media (max-width: 350px) {
            #camera-container, #barcode-video { width:98vw !important; min-width:90vw !important; }
        }
    `;
    document.head.appendChild(style);

    // Initialize DataTables
    if ($('#scan-list-table').length && window.UnifiedTables) {
        UnifiedTables.init('#scan-list-table', 'scan');
    }
})();
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>