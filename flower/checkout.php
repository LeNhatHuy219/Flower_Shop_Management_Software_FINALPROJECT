<?php
@include 'config.php';
session_start();
$user_id = $_SESSION['user_id'];

if(!isset($user_id)){
   header('location:login.php');
   exit();
}

// ================================================================
// 1: LẤY THÔNG TIN USER (KHÔNG THAY ĐỔI)
// ================================================================
try {
    $user_info_stmt = $conn->prepare("SELECT name, email, phone FROM `users` WHERE id = ?");
    $user_info_stmt->execute([$user_id]);
    $user_info = $user_info_stmt->fetch(PDO::FETCH_ASSOC);
    
    $current_name = $user_info['name'] ?? '';
    $current_email = $user_info['email'] ?? '';
    $current_phone = $user_info['phone'] ?? '';

} catch (Exception $e) {
    $message[] = 'Error fetching user data: ' . $e->getMessage();
    $current_name = ''; $current_email = ''; $current_phone = '';
}


// ================================================================
// (SỬA) 2: KHỐI XỬ LÝ AJAX "APPLY VOUCHER"
// ================================================================
if (isset($_POST['action']) && $_POST['action'] == 'calculate_discounts') {
    $response = ['success' => false, 'message' => ''];
    $voucher_code = trim($_POST['voucher_code']);
    $points_to_use = (int)($_POST['points_to_use'] ?? 0);
    
    try {
        $cart_total = 0;
        $cart_query = $conn->prepare("SELECT price, quantity FROM `cart` WHERE user_id = ?");
        $cart_query->execute([$user_id]);
        $cart_items = $cart_query->fetchAll(PDO::FETCH_ASSOC);
        if (count($cart_items) == 0) { throw new Exception('Your cart is empty.'); }
        foreach ($cart_items as $item) { $cart_total += ($item['price'] * $item['quantity']); }
        $response['original_total'] = $cart_total;
        $total_after_voucher = $cart_total;
        $final_total = $cart_total;

        // Khởi tạo biến theo dõi giảm giá
        $discount_applied_voucher = 0;
        $discount_applied_points = 0;

        if (!empty($voucher_code)) {
            $promo_stmt = $conn->prepare("SELECT * FROM `promotions` WHERE code = ? AND is_active = 1 AND NOW() BETWEEN start_date AND end_date");
            $promo_stmt->execute([$voucher_code]);
            $promo = $promo_stmt->fetch(PDO::FETCH_ASSOC);
            if ($promo) {
                $promo_id = $promo['id'];
                $check_usage_stmt = $conn->prepare("SELECT id FROM orders WHERE user_id = ? AND promotion_id = ?");
                $check_usage_stmt->execute([$user_id, $promo_id]);
                if ($check_usage_stmt->rowCount() > 0) { throw new Exception('You have already used this voucher.'); }
                if ($promo['discount_type'] == 'percent') { $discount_applied_voucher = $cart_total * ($promo['discount_value'] / 100); } else { $discount_applied_voucher = $promo['discount_value']; }
                if ($discount_applied_voucher > $cart_total) $discount_applied_voucher = $cart_total;
                $total_after_voucher = $cart_total - $discount_applied_voucher;
            } else { $response['message'] = 'Invalid voucher code. '; }
        }
        $final_total = $total_after_voucher;
        if ($points_to_use > 0) {
            if ($points_to_use < 100) { throw new Exception('You must use at least 100 points.'); }
            $points_stmt = $conn->prepare("SELECT SUM(points) as total_points FROM `reward_points_log` WHERE user_id = ?");
            $points_stmt->execute([$user_id]);
            $available_points = $points_stmt->fetchColumn() ?? 0;
            if ($points_to_use > $available_points) { $response['message'] .= 'You only have ' . $available_points . ' points.'; } else {
                $discount_applied_points = ($points_to_use / 100) * 10; 
                if ($discount_applied_points > $total_after_voucher) { $discount_applied_points = $total_after_voucher; $response['points_spent'] = ceil(($discount_applied_points / 10) * 100); } else { $response['points_spent'] = $points_to_use; }
                $final_total = $total_after_voucher - $discount_applied_points;
            }
        }

        // Gán lại giá trị vào response
        $response['discount_amount_voucher'] = $discount_applied_voucher;
        $response['discount_amount_points'] = $discount_applied_points;

        $response['success'] = true;
        $response['final_total'] = $final_total;
        
        // --- (SỬA) CHỈ THÔNG BÁO KHI CÓ GIẢM GIÁ ---
        if (empty($response['message'])) {
            if ($discount_applied_voucher > 0 || $discount_applied_points > 0) {
                $response['message'] = 'Discounts applied!';
            }
            // Nếu cả 2 đều = 0 (và không có lỗi), message sẽ rỗng -> JS sẽ không hiển thị modal
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        $response['final_total'] = $cart_total ?? $total_after_voucher ?? 0;
    }
    header('Content-Type: application/json'); echo json_encode($response); exit;
}

// ================================================================
// 3: KHỐI XỬ LÝ ĐẶT HÀNG "ORDER NOW" (KHÔNG THAY ĐỔI)
// ================================================================
if(isset($_POST['order'])){
    // (Toàn bộ logic PHP xử lý đơn hàng của bạn giữ nguyên)
    $name = $_POST['name'];
    $number = $_POST['number'];
    $email = $_POST['email'];
    $method = $_POST['method'];
    $voucher_code = trim($_POST['voucher_code_hidden']);
    $points_to_use = (int)($_POST['points_to_use_hidden'] ?? 0);
    $address = 'flat no. '. $_POST['flat'].', '. $_POST['city'];
    $conn->beginTransaction();
    try {
        $cart_query = $conn->prepare("SELECT c.*, p.stock FROM `cart` c JOIN `products` p ON c.pid = p.id WHERE c.user_id = ?");
        $cart_query->execute([$user_id]);
        if($cart_query->rowCount() == 0){ throw new Exception('Your cart is empty!'); }
        $cart_items = $cart_query->fetchAll(PDO::FETCH_ASSOC);
        $cart_total = 0;
        foreach($cart_items as $item){ if($item['quantity'] > $item['stock']){ throw new Exception('Product "' . $item['name'] . '" is out of stock!'); } $cart_total += ($item['price'] * $item['quantity']); }
        $discount_amount_voucher = 0; $promotion_id = NULL; 
        $total_after_voucher = $cart_total;
        if(!empty($voucher_code)){
            $promo_stmt = $conn->prepare("SELECT * FROM `promotions` WHERE code = ? AND is_active = 1 AND NOW() BETWEEN start_date AND end_date");
            $promo_stmt->execute([$voucher_code]);
            $promo = $promo_stmt->fetch(PDO::FETCH_ASSOC);
            if($promo){
                $promotion_id = $promo['id'];
                $check_usage_stmt = $conn->prepare("SELECT id FROM orders WHERE user_id = ? AND promotion_id = ?");
                $check_usage_stmt->execute([$user_id, $promotion_id]);
                if ($check_usage_stmt->rowCount() > 0) { throw new Exception('You have already used this voucher.'); }
                if($promo['discount_type'] == 'percent'){ $discount_amount_voucher = $cart_total * ($promo['discount_value'] / 100); } else { $discount_amount_voucher = $promo['discount_value']; }
                if($discount_amount_voucher > $cart_total) $discount_amount_voucher = $cart_total;
                $total_after_voucher = $cart_total - $discount_amount_voucher;
            } else { throw new Exception('Invalid voucher code or expired.'); }
        }
        $discount_amount_points = 0; $points_spent = 0; 
        if($points_to_use > 0) {
            if ($points_to_use < 100) { throw new Exception('You must use at least 100 points.'); }
            $points_stmt = $conn->prepare("SELECT SUM(points) as total_points FROM `reward_points_log` WHERE user_id = ?");
            $points_stmt->execute([$user_id]);
            $available_points = $points_stmt->fetchColumn() ?? 0;
            if ($points_to_use > $available_points) { throw new Exception('Invalid points amount.'); }
            $discount_amount_points = ($points_to_use / 100) * 10;
            if ($discount_amount_points > $total_after_voucher) { $discount_amount_points = $total_after_voucher; $points_spent = ceil(($discount_amount_points / 10) * 100); } else { $points_spent = $points_to_use; }
        }
        $final_total = $total_after_voucher - $discount_amount_points;
        $total_discount = $discount_amount_voucher + $discount_amount_points;
        $order_stmt = $conn->prepare("INSERT INTO `orders`(user_id, name, number, email, method, address, total_price, placed_on, promotion_id, discount_amount, points_spent) VALUES(?,?,?,?,?,?,?, NOW(), ?, ?, ?)");
        $order_stmt->execute([$user_id, $name, $number, $email, $method, $address, $final_total, $promotion_id, $total_discount, $points_spent]);
        $order_id = $conn->lastInsertId();
        $detail_stmt = $conn->prepare("INSERT INTO `order_details`(order_id, product_id, quantity, price_at_purchase) VALUES(?,?,?,?)");
        $stock_stmt = $conn->prepare("UPDATE `products` SET stock = stock - ? WHERE id = ?");
        foreach($cart_items as $item){ $detail_stmt->execute([$order_id, $item['pid'], $item['quantity'], $item['price']]); $stock_stmt->execute([$item['quantity'], $item['pid']]); }
        $clear_cart_stmt = $conn->prepare("DELETE FROM `cart` WHERE user_id = ?");
        $clear_cart_stmt->execute([$user_id]);
        if ($points_spent > 0) { $points_log_stmt = $conn->prepare("INSERT INTO `reward_points_log` (user_id, order_id, points, reason) VALUES (?, ?, ?, ?)"); $points_log_stmt->execute([$user_id, $order_id, -$points_spent, "Used on order #" . $order_id]); }
        $points_to_add = 10; $reason = "Completed order #" . $order_id;
        $points_stmt = $conn->prepare("INSERT INTO `reward_points_log` (user_id, order_id, points, reason) VALUES (?, ?, ?, ?)");
        $points_stmt->execute([$user_id, $order_id, $points_to_add, $reason]);
        $conn->commit();
        $message[] = 'Order placed successfully! You earned 10 points.';
    } catch (Exception $e) {
        $conn->rollBack();
        $message[] = 'Failed to place order: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>checkout</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/style.css">

   <style>
    /* (CSS Giữ nguyên) */
    .display-order { display: flex; flex-direction: column; align-items: center; gap: 1rem; }
    .display-order .product-list { width: 100%; max-width: 70rem; margin: 0 auto; }
    .display-order .product-item { display: flex; align-items: center; gap: 1.5rem; background: var(--white); border: var(--border); border-radius: .5rem; padding: 1.5rem; margin-bottom: 1rem; }
    .display-order .product-item img { height: 10rem; width: 10rem; object-fit: cover; border-radius: .5rem; }
    .display-order .product-item .info { flex-grow: 1; text-align: left; font-size: 1.8rem; color: var(--black); }
    .display-order .product-item .info span { font-size: 1.6rem; color: var(--light-color); }
    .display-order .product-item .price { font-size: 1.8rem; color: var(--main-color); font-weight: bold; }
    .display-order .grand-total { margin-top: 1rem; text-align: right; width: 100%; max-width: 70rem; padding: 0 1rem; font-size: 2rem; }
    .checkout form .order-summary-box { display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; gap: 2rem; margin-top: 2rem; border-top: var(--border); padding-top: 2rem; }
    .checkout form .order-summary-box .grand-total { font-size: 2.2rem; color: var(--black); }
    .checkout form .order-summary-box .grand-total span { color: var(--main-color); font-weight: bold; }
    .checkout form .flex .discount-box { flex: 1 1 100%; display: flex; align-items: flex-end; gap: 1rem; }
    .checkout form .flex .discount-box .input-wrapper { flex-grow: 1; width: 100%; }
    .checkout form .flex .discount-box .option-btn { width: auto; flex-shrink: 0; margin-top: 0; }

    /* (CSS MODAL GIỮ NGUYÊN) */
    .message-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.6);
        z-index: 11000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }
    .message-modal-content {
        background: var(--white);
        border-radius: .5rem;
        padding: 3rem;
        width: 100%;
        max-width: 45rem;
        position: relative;
        text-align: center;
        box-shadow: var(--box_shadow);
    }
    .message-modal-content p {
        font-size: 1.8rem;
        color: var(--black);
        line-height: 1.5;
        margin-bottom: 2rem;
        white-space: pre-line;
    }
    .message-modal-close {
        margin-top: 1rem;
    }
   </style>
</head>
<body>

<div class="message-modal-overlay" id="messageModal">
   <div class="message-modal-content">
      <p id="messageModalText">This is a message.</p>
      <button type="button" class="btn message-modal-close">OK</button>
   </div>
</div>
   
<?php @include 'header.php'; ?>

<section class="heading">
    <h3>checkout order</h3>
    <p> <a href="home.php">home</a> / checkout </p>
</section>

<section class="display-order">
    <div class="product-list">
    <?php
        $grand_total = 0;
        $select_cart = $conn->prepare("SELECT * FROM `cart` WHERE user_id = ?");
        $select_cart->execute([$user_id]);
        if($select_cart->rowCount() > 0){
            while($fetch_cart = $select_cart->fetch(PDO::FETCH_ASSOC)){
            $total_price = ($fetch_cart['price'] * $fetch_cart['quantity']);
            $grand_total += $total_price;
    ?>    
    <div class="product-item">
        <img src="flowers/<?php echo htmlspecialchars($fetch_cart['image']); ?>" alt="">
        <div class="info">
            <?php echo htmlspecialchars($fetch_cart['name']); ?>
            <span>(x <?php echo $fetch_cart['quantity']; ?>)</span>
        </div>
        <div class="price">$<?php echo number_format($total_price, 2); ?>/-</div>
    </div>
    <?php
        }
        }else{
            echo '<p class="empty">your cart is empty</p>';
        }
    ?>
    </div> 
    <div class="grand-total">grand total : <span id="grand-total-display">$<?php echo number_format($grand_total, 2); ?>/-</span></div>
</section>

<section class="checkout">
    <form action="" method="POST">
        <h3>place your order</h3>
        <div class="flex">
            <div class="inputBox">
                <span>your name :</span>
                <input type="text" name="name" value="<?php echo htmlspecialchars($current_name); ?>" placeholder="enter your name" required>
            </div>
            <div class="inputBox">
                <span>your number :</span>
                <input type="number" name="number" value="<?php echo htmlspecialchars($current_phone); ?>" min="0" placeholder="enter your number" required>
            </div>
            <div class="inputBox">
                <span>your email :</span>
                <input type="email" name="email" value="<?php echo htmlspecialchars($current_email); ?>" placeholder="enter your email" required>
            </div>
            <div class="inputBox">
                <span>payment method :</span>
                <select name="method">
                    <option value="cash on delivery">cash on delivery</option>
                    <option value="credit card">credit card</option>
                    <option value="paypal">paypal</option>
                </select>
            </div>
            <div class="inputBox">
                <span>address line 01 :</span>
                <input type="text" name="flat" placeholder="e.g. flat no." required>
            </div>
            <div class="inputBox">
                <span>city :</span>
                <input type="text" name="city" placeholder="e.g. mumbai" required>
            </div>
            <div class="inputBox">
                <span>points to use :</span>
                <input type="number" id="points-to-use-input" name="points_to_use_input" placeholder="e.g. 100 (min 100)" min="0">
            </div>
            <div class="inputBox discount-box">
                <div class="input-wrapper"> 
                    <span>voucher code :</span>
                    <input type="text" id="voucher-code-input" name="voucher_code_input" placeholder="enter voucher code">
                </div>
                <button type="button" id="apply-discounts-btn" class="option-btn">Apply</button>
            </div>
        </div>
        <input type="hidden" name="voucher_code_hidden" id="voucher-code-hidden" value="">
        <input type="hidden" name="points_to_use_hidden" id="points-to-use-hidden" value="0">
        <div class="order-summary-box">
            <div class="grand-total">
                grand total : <span id="grand-total-display-bottom">$<?php echo number_format($grand_total, 2); ?>/-</span>
            </div>
            <input type="submit" name="order" value="order now" class="btn" <?php if($grand_total == 0) echo 'disabled'; ?>>
        </div>
    </form>
</section>

<?php @include 'footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {

    const messageModal = document.getElementById('messageModal');
    const messageModalText = document.getElementById('messageModalText');
    const messageModalClose = document.querySelector('.message-modal-close');

    function showModal(text) {
        messageModalText.textContent = text;
        messageModal.style.display = 'flex';
    }

    function closeModal() {
        messageModal.style.display = 'none';
    }
    messageModalClose.addEventListener('click', closeModal);
    messageModal.addEventListener('click', function(e) {
        if (e.target === messageModal) {
            closeModal();
        }
    });

    var applyBtn = document.getElementById('apply-discounts-btn');
    var voucherInput = document.getElementById('voucher-code-input');
    var pointsInput = document.getElementById('points-to-use-input');
    var hiddenVoucherInput = document.getElementById('voucher-code-hidden');
    var hiddenPointsInput = document.getElementById('points-to-use-hidden');
    var grandTotalDisplayTop = document.getElementById('grand-total-display');
    var grandTotalDisplayBottom = document.getElementById('grand-total-display-bottom'); 
    
    var originalTotal = <?php echo $grand_total; ?>;
    var originalTotalText = '$' + parseFloat(originalTotal).toFixed(2) + '/-';

    applyBtn.addEventListener('click', function() {
        var code = voucherInput.value.trim();
        var points = pointsInput.value.trim() || '0';
        var formData = new FormData();
        formData.append('action', 'calculate_discounts'); 
        formData.append('voucher_code', code);
        formData.append('points_to_use', points); 

        fetch('checkout.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            
            // (SỬA) Chỉ hiển thị modal nếu có message
            if (data.message && data.message.trim() !== '') {
                showModal(data.message);
            }
           
            if (data.success) {
                var finalTotalText = '$' + parseFloat(data.final_total).toFixed(2) + '/-';
                grandTotalDisplayTop.textContent = finalTotalText;
                grandTotalDisplayBottom.textContent = finalTotalText;
                
                hiddenVoucherInput.value = code; 
                hiddenPointsInput.value = data.points_spent || '0';

            } else {
                grandTotalDisplayTop.textContent = originalTotalText;
                grandTotalDisplayBottom.textContent = originalTotalText;
                
                hiddenVoucherInput.value = ''; 
                hiddenPointsInput.value = '0';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showModal('An unexpected error occurred. Please try again.');
            
            grandTotalDisplayTop.textContent = originalTotalText;
            grandTotalDisplayBottom.textContent = originalTotalText;
        });
    });

    <?php
        if(!empty($message)){
            $modal_message_content = implode("\n", $message);
            echo "showModal(" . json_encode($modal_message_content) . ");";
        }
    ?>

});
</script>

<script src="js/script.js"></script>
</body>
</html>