<?php
@include 'config.php';
session_start();

// Chuẩn bị mảng phản hồi
$response = [];

// 1. Kiểm tra đăng nhập
$user_id = $_SESSION['user_id'] ?? null;
if(!$user_id){
   $response['error'] = 'You must log in to shop.';
   header('Content-Type: application/json');
   echo json_encode($response);
   exit;
}

// 2. Lấy ID đơn hàng từ URL (vd: get_order_details.php?id=17)
if(!isset($_GET['id'])){
   $response['error'] = 'No order ID provided.';
   header('Content-Type: application/json');
   echo json_encode($response);
   exit;
}
$order_id = $_GET['id'];

try {
    // 3. Lấy thông tin chính của đơn hàng
    // (Kiểm tra xem user_id có khớp không để bảo mật)
    $stmt_order = $conn->prepare("SELECT * FROM `orders` WHERE id = ? AND user_id = ?");
    $stmt_order->execute([$order_id, $user_id]);

    if($stmt_order->rowCount() == 0){
        $response['error'] = 'Order not found or you do not hlogin.';
    } else {
        $response['order'] = $stmt_order->fetch(PDO::FETCH_ASSOC);

        // 4. Lấy thông tin chi tiết các sản phẩm
        $stmt_details = $conn->prepare(
            "SELECT p.name, p.image, od.quantity, od.price_at_purchase 
             FROM `order_details` od
             JOIN `products` p ON od.product_id = p.id
             WHERE od.order_id = ?"
        );
        $stmt_details->execute([$order_id]);
        
        $response['details'] = $stmt_details->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $response['error'] = 'Database error: ' . $e->getMessage();
}

// 5. Trả về kết quả dưới dạng JSON
header('Content-Type: application/json');
echo json_encode($response);
exit;

?>