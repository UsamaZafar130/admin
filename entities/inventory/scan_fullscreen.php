<?php require_once '../../includes/auth_check.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Barcode Scan</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        html, body { margin:0; padding:0; height:100%; width:100%; background:#000; color:#fff; }
        #video-container { width:100vw; height:100vh; display:flex; flex-direction:column; justify-content:center; align-items:center; }
        video { width:100vw !important; max-width:100vw !important; height:100vh !important; object-fit:cover; }
        #scan-feedback { position:fixed; left:0; right:0; top:10px; text-align:center; z-index:10; font-size:1.2em; }
        #close-btn { position:fixed; top:12px; right:12px; z-index:20; background:#fff4; color:#fff; border:none; border-radius:8px; padding:8px 16px; font-size:1.1em; }
    </style>
</head>
<body>
    <button id="close-btn" onclick="window.history.back()">Close</button>
    <div id="scan-feedback"></div>
    <div id="video-container">
        <video id="barcode-video" autoplay playsinline></video>
    </div>
    <script src="https://unpkg.com/@zxing/browser@0.1.1"></script>
    <script>
        let lastBarcode = "";
        let debounceTimeout = null;
        let codeReader = null;
        let scannerActive = false;

        function showFeedback(msg, success) {
            const el = document.getElementById('scan-feedback');
            el.style.background = success ? "#0a06" : "#a006";
            el.textContent = msg;
            el.style.display = 'block';
            setTimeout(() => { el.style.display = 'none'; }, 1500);
        }

        async function startScanner() {
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

                codeReader.decodeFromVideoDevice(backCam, 'barcode-video', (result, err, controls) => {
                    if (result) {
                        let barcode = result.getText();
                        if (barcode && barcode !== lastBarcode) {
                            lastBarcode = barcode;
                            // AJAX to record barcode
                            fetch('actions.php', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                body: 'action=scan_pack_barcode&barcode=' + encodeURIComponent(barcode)
                            })
                            .then(r=>r.json())
                            .then(resp => {
                                if (resp.success) {
                                    showFeedback("✅ " + resp.item_name + " " + resp.pack_size + " logged", true);
                                } else {
                                    showFeedback(resp.error || "Error logging pack!", false);
                                }
                            });
                            if (debounceTimeout) clearTimeout(debounceTimeout);
                            debounceTimeout = setTimeout(() => { lastBarcode = ""; }, 1500);
                        }
                    }
                });
            } catch (e) {
                showFeedback('Could not start camera: ' + e, false);
            }
        }

        window.onload = startScanner;
        window.onpageshow = startScanner;
    </script>
</body>
</html>