<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$c = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$c) { echo "Customer not found."; exit; }

$balance = get_customer_balance($id, $pdo);

// Make location clickable if it's a valid URL or coordinates
function render_location($loc) {
    $loc = trim($loc);
    if (!$loc) return '';
    // If it's a valid URL, just link as is
    if (filter_var($loc, FILTER_VALIDATE_URL)) {
        return '<a href="' . htmlspecialchars($loc) . '" target="_blank">' . htmlspecialchars($loc) . '</a>';
    }
    // If it's a comma/space-separated coordinate (latitude,longitude)
    if (preg_match('/^(-?\d+(\.\d+)?)[\s,]+(-?\d+(\.\d+)?)/', $loc, $m)) {
        $lat = $m[1];
        $lng = $m[3];
        $href = "https://maps.google.com/?q={$lat},{$lng}";
        return '<a href="' . htmlspecialchars($href) . '" target="_blank" class="customer-coords-link" data-coords="' . htmlspecialchars($lat . ',' . $lng) . '">' . htmlspecialchars($lat . ',' . $lng) . '</a>';
    }
    // Otherwise, just show text
    return htmlspecialchars($loc);
}

// Format WhatsApp link
function whatsapp_link($contact, $customer_name) {
    $num = preg_replace('/[^\d]/', '', get_contact_normalized(normalize_contact($contact)));
    if (strlen($num) === 10) $num = '92' . $num;
    $msg = urlencode("Assalam o Alaikum $customer_name,\nHope you are doing well. I would like to get in touch regarding our services. Kindly let me know if you have any queries. Thank you!");
    return "https://wa.me/$num?text=$msg";
}
?>
<div class="modal-header">
    <h5 class="modal-title"><?= h($c['name']) ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <div class="entity-details" data-customer-id="<?= $c['id'] ?>">
        <div class="mb-3">
            <strong>Contact:</strong> 
            <a class="customer-contact" href="<?= whatsapp_link($c['contact'], $c['name']) ?>" target="_blank">
                <?= h($c['contact']) ?>
            </a>
        </div>
        <div class="mb-3">
            <strong>Address:</strong> 
            <span class="customer-address"><?= h($c['house_no']) ?>, <?= h($c['area']) ?>, <?= h($c['city']) ?></span>
        </div>
        <div class="mb-3">
            <strong>Location:</strong> 
            <span class="customer-location"><?= render_location($c['location']) ?></span>
        </div>
        <div class="mb-3">
            <strong>Balance/Surplus:</strong> 
            <span class="customer-balance badge <?= $balance > 0 ? 'badge-outstanding' : ($balance < 0 ? 'badge-surplus' : 'badge-settled') ?>"><?= abs($balance) ?></span>
        </div>
        <div class="mb-3">
            <strong>Created at:</strong> 
            <span class="customer-created"><?= date('Y-m-d', strtotime($c['created_at'])) ?></span>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button class="btn btn-primary" onclick="CustomerUI.openEditModal(<?= $c['id'] ?>, true)">Edit</button>
    <button class="btn btn-success" onclick="CustomerUI.openPaymentModal(<?= $c['id'] ?>)">Record Payment</button>
    <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>
<script>
// Smart handling for customer location links (coords open in app on mobile)
document.addEventListener('click', function(e) {
    const a = e.target.closest('.customer-coords-link');
    if (!a) return;
    e.preventDefault();
    const coords = a.dataset.coords;
    if (!coords) return;
    const [lat, lng] = coords.split(',');
    let locUrl = '';
    const isMobile = /android|iphone|ipad|ipod/i.test(navigator.userAgent);
    if (isMobile) {
        // Try opening in preferred maps app
        if (/android/i.test(navigator.userAgent)) {
            locUrl = `geo:${lat},${lng}?q=${lat},${lng}`;
        } else if (/iphone|ipad|ipod/i.test(navigator.userAgent)) {
            locUrl = `maps://maps.apple.com/?q=${lat},${lng}`;
        } else {
            locUrl = `https://maps.google.com/?q=${lat},${lng}`;
        }
        // Fallback: always open Google Maps in browser if above fail
        setTimeout(function() {
            window.open(`https://maps.google.com/?q=${lat},${lng}`, '_blank');
        }, 500);
        // Try to open app (geo: or maps://)
        window.location.href = locUrl;
    } else {
        // Desktop: just open Google Maps in new tab
        window.open(`https://maps.google.com/?q=${lat},${lng}`, '_blank');
    }
});
</script>