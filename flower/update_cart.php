<?php
@include 'config.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;
// Mảng phản hồi (response)
$response = [
    'success' => false, 
    'message' => '',
    'new_sub_total' => 0,
    'new_grand_total' => 0
];

if (!$user_id) {
    $response['message'] = 'User not logged in.';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Lấy dữ liệu POST từ JavaScript (fetch)
$cart_id = $_POST['cart_id'] ?? null;
$cart_quantity = $_POST['cart_quantity'] ?? null;

if (!$cart_id || $cart_quantity === null) {
    $response['message'] = 'Invalid data.';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$cart_quantity = (int)$cart_quantity;

try {
    // 1. Lấy thông tin sản phẩm (stock và price)
    $stmt_check = $conn->prepare("SELECT c.pid, p.stock, c.price 
                                  FROM `cart` c 
                                  JOIN `products` p ON c.pid = p.id 
                                  WHERE c.id = ? AND c.user_id = ?");
    $stmt_check->execute([$cart_id, $user_id]);
    $item = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        $response['message'] = 'Item not found in cart.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    $product_stock = (int)$item['stock'];
    $product_price = (float)$item['price'];

    // 2. Xác thực số lượng (Validation)
    if ($cart_quantity <= 0) {
        $response['message'] = 'Quantity must be greater than 0.';
    } elseif ($cart_quantity > $product_stock) {
        $response['message'] = 'Only ' . $product_stock . ' items in stock!';
    } else {
        // 3. Cập nhật giỏ hàng
        $stmt_update = $conn->prepare("UPDATE `cart` SET quantity = ? WHERE id = ? AND user_id = ?");
        $stmt_update->execute([$cart_quantity, $cart_id, $user_id]);

        $response['success'] = true;
        $response['message'] = 'Quantity updated!';
        
        // 4. Tính toán sub-total mới
        $response['new_sub_total'] = $product_price * $cart_quantity;

        // 5. Tính lại grand-total (Tổng của cả giỏ hàng)
        $stmt_total = $conn->prepare("SELECT SUM(price * quantity) FROM `cart` WHERE user_id = ?");
        $stmt_total->execute([$user_id]);
        $response['new_grand_total'] = $stmt_total->fetchColumn();
    }

} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

// Trả về kết quả (dưới dạng JSON) cho JavaScript
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>