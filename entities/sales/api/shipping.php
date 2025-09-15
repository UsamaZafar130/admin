<?php
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/db_connection.php';
require_once __DIR__ . '/../../../includes/functions.php';

header('Content-Type: text/html; charset=UTF-8');
$action = $_REQUEST['action'] ?? '';

function fetch_orders($ids) {
    global $pdo;
    $in = str_repeat('?,', count($ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT o.*, c.name as customer_name, c.contact, c.house_no, c.area, c.city, c.location 
        FROM sales_orders o JOIN customers c ON o.customer_id = c.id WHERE o.id IN ($in)");
    $stmt->execute($ids);
    $orders = [];
    while($row = $stmt->fetch()) $orders[$row['id']] = $row;
    return $orders;
}

switch ($action) {
    case 'labels':
        $orders = json_decode($_POST['orders'] ?? '[]', true);
        $ids = array_column($orders, 'id');
        $orders_data = fetch_orders($ids);

        echo '<html><head><title>Shipping Labels</title><style>
        .label{border:1px solid #333;padding:10px;margin:10px;width:340px;height:140px;float:left;font-size:14px;}
        .label strong{font-size:16px;}
        </style></head><body>';
        foreach($orders as $o){
            $order = $orders_data[$o['id']];
            echo '<div class="label">';
            echo '<strong>Pack #:' . htmlspecialchars($o['pack_no']) . '</strong><br>';
            echo 'Name: ' . htmlspecialchars($order['customer_name']) . '<br>';
            echo 'Contact: ' . htmlspecialchars($order['contact']) . '<br>';
            echo 'Address: ' . htmlspecialchars(trim($order['house_no'].' '.$order['area'].' '.$order['city'])) . '<br>';
            echo 'Collection: <b>' . format_currency(floatval($o['collection_amount'])) . '</b>';
            echo '</div>';
        }
        echo '<div style="clear:both"></div></body></html>';
        break;

    case 'dispatch_list':
        $orders = json_decode($_POST['orders'] ?? '[]', true);
        $ids = array_column($orders, 'id');
        $orders_data = fetch_orders($ids);
        $rider = htmlspecialchars($_POST['rider'] ?? '');

        echo '<html><head><title>Dispatch List</title><style>
        body{font-family:Arial;}
        table{border-collapse:collapse;width:100%;}
        th,td{border:1px solid #222;padding:8px;text-align:left;}
        </style></head><body>';
        echo '<h3>Dispatch List (Rider: '.$rider.')</h3><table><tr>
        <th>Pack #</th><th>Name</th><th>Items</th></tr>';
        foreach($orders as $o){
            $order = $orders_data[$o['id']];
            // Items:
            $stmt = $pdo->prepare("SELECT oi.qty, oi.pack_size, i.name FROM order_items oi JOIN items i ON oi.item_id=i.id WHERE oi.order_id=?");
            $stmt->execute([$o['id']]);
            $itemstr = '';
            while($item = $stmt->fetch()){
                $packs = floor($item['qty'] / $item['pack_size']);
                $itemstr .= htmlspecialchars($item['name'].' x '.$packs.' ('.$item['pack_size'].'s)<br>');
            }
            echo '<tr>';
            echo '<td>'.htmlspecialchars($o['pack_no']).'</td>';
            echo '<td>'.htmlspecialchars($order['customer_name']).'</td>';
            echo '<td>'.$itemstr.'</td>';
            echo '</tr>';
        }
        echo '</table></body></html>';
        break;

    case 'delivery_sheet':
        $orders = json_decode($_POST['orders'] ?? '[]', true);
        $ids = array_column($orders, 'id');
        $orders_data = fetch_orders($ids);
        $rider = htmlspecialchars($_POST['rider'] ?? '');

        echo '<html><head><title>Delivery Sheet</title><style>
        body{font-family:Arial;}
        table{border-collapse:collapse;width:100%;}
        th,td{border:1px solid #222;padding:8px;text-align:left;}
        a{color:blue;text-decoration:underline;}
        </style></head><body>';
        echo '<h3>Delivery Sheet (Rider: '.$rider.')</h3><table><tr>
        <th>Pack #</th><th>Name</th><th>Address</th><th>Contact #</th><th>Location</th><th>Mark Delivered</th></tr>';
        foreach($orders as $o){
            $order = $orders_data[$o['id']];
            $address = htmlspecialchars(trim($order['house_no'].' '.$order['area'].' '.$order['city']));
            $location = trim($order['location']);
            echo '<tr>';
            echo '<td>'.htmlspecialchars($o['pack_no']).'</td>';
            echo '<td>'.htmlspecialchars($order['customer_name']).'</td>';
            echo '<td>'.$address.'</td>';
            echo '<td>'.htmlspecialchars($order['contact']).'</td>';
            echo '<td>';
            if($location) echo '<a href="'.htmlspecialchars($location).'" target="_blank">Map</a>';
            echo '</td>';
            echo '<td><a href="javascript:void(0);" onclick="markDelivered('.$o['id'].')">Mark Delivered</a></td>';
            echo '</tr>';
        }
        echo '</table>
        <script>
        function markDelivered(id){
            showConfirm("Mark order "+id+" as delivered?", "Confirm Delivery", function() {
                fetch("../api/orders.php?action=mark_delivered", {
                    method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"},
                    body:"id="+id
                }).then(r=>r.json()).then(resp=>{
                    if(resp.status==="success"){
                        showSuccess("Order marked delivered. Page will reload to update.");
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showError(resp.message || "Failed to mark order as delivered");
                    }
                }).catch(error => {
                    showError("Network error occurred while marking order as delivered");
                });
            });
        }
        </script></body></html>';
        break;
}