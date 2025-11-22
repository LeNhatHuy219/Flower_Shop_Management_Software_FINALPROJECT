<?php
@include 'config.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;

// ================================================================
// (KHỐI XỬ LÝ AJAX - Giữ nguyên)
// ================================================================
if (isset($_POST['action'])) {
    
    $response = ['success' => false, 'message' => 'Invalid action.'];
    if (!$user_id) {
        $response['message'] = 'User not logged in.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    try {
        switch ($_POST['action']) {
            
            // === CASE 1: UPDATE QUANTITY ===
            case 'update':
                $cart_id = $_POST['cart_id'];
                $cart_quantity = (int)$_POST['cart_quantity'];

                $stmt_check = $conn->prepare("SELECT p.stock, c.price FROM `cart` c JOIN `products` p ON c.pid = p.id WHERE c.id = ? AND c.user_id = ?");
                $stmt_check->execute([$cart_id, $user_id]);
                $item = $stmt_check->fetch(PDO::FETCH_ASSOC);

                if ($cart_quantity <= 0) {
                    $response['message'] = 'Quantity must be greater than 0.';
                } elseif ($cart_quantity > $item['stock']) {
                    $response['message'] = 'Only ' . $item['stock'] . ' items in stock!';
                } else {
                    $stmt_update = $conn->prepare("UPDATE `cart` SET quantity = ? WHERE id = ? AND user_id = ?");
                    $stmt_update->execute([$cart_quantity, $cart_id, $user_id]);
                    
                    $response['success'] = true;
                    $response['message'] = 'Quantity updated!';
                    $response['new_sub_total'] = $item['price'] * $cart_quantity;

                    $stmt_total = $conn->prepare("SELECT SUM(price * quantity) FROM `cart` WHERE user_id = ?");
                    $stmt_total->execute([$user_id]);
                    $response['new_grand_total'] = $stmt_total->fetchColumn();
                }
                break;

            // === CASE 2: DELETE ONE ITEM ===
            case 'delete_one':
                $cart_id = $_POST['cart_id'];
                $stmt = $conn->prepare("DELETE FROM `cart` WHERE id = ? AND user_id = ?");
                $stmt->execute([(int)$cart_id, $user_id]);
                
                $response['success'] = true;
                $stmt_total = $conn->prepare("SELECT SUM(price * quantity) FROM `cart` WHERE user_id = ?");
                $stmt_total->execute([$user_id]);
                $new_total = $stmt_total->fetchColumn();
                
                $response['new_grand_total'] = $new_total ?? 0;
                $response['is_empty'] = ($new_total <= 0);
                break;

            // === CASE 3: DELETE ALL ITEMS ===
            case 'delete_all':
                $stmt = $conn->prepare("DELETE FROM `cart` WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $response['success'] = true;
                break;
        }
    } catch (Exception $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// ================================================================
// (LOGIC TẢI TRANG - Giữ nguyên)
// ================================================================
if(!isset($user_id)){
   header('location:login.php');
   exit(); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>shopping cart</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/style.css">
   
   <style>
    /* CSS cho các modal Delete (Giữ nguyên) */
    .delete-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); }
    .delete-modal-content { background-color: var(--white); margin: 15% auto; padding: 3rem; border: var(--border); border-radius: .5rem; width: 90%; max-width: 50rem; text-align: center; position: relative; }
    .delete-modal-content h3 { font-size: 2.2rem; color: var(--black); margin-bottom: 2rem; }
    .delete-modal-close { color: var(--light-color); position: absolute; top: 1rem; right: 2rem; font-size: 3rem; font-weight: bold; }
    .delete-modal-close:hover, .delete-modal-close:focus { color: var(--red); text-decoration: none; cursor: pointer; }
    .delete-modal-buttons { display: flex; justify-content: center; gap: 1rem; }

    /* CSS CHO BOX NHỎ LẠI (Giữ nguyên) */
    .shopping-cart .box-container .box {
        padding: 1rem; 
        position: relative; 
    }
    .shopping-cart .box-container .box .delete-one-btn {
        position: absolute;
        top: .5rem; 
        left: .5rem; 
        height: 3rem; 
        width: 3rem; 
        line-height: 3rem;
        font-size: 1.5rem; 
    }
    .shopping-cart .box-container .box .fa-eye {
        display: none; 
    }
    /* =========================================== */
    /* (SỬA) 1: THU NHỎ HÌNH ẢNH */
    /* =========================================== */
    .shopping-cart .box-container .box .image {
        height: 8rem; /* (SỬA) Nhỏ hơn */
    }
    .shopping-cart .box-container .box .name {
        font-size: 1.7rem; 
        margin-top: .5rem;
    }
    .shopping-cart .box-container .box .price {
        font-size: 1.7rem; 
    }
    .shopping-cart .box-container .box .update-form {
        display: flex;
        justify-content: center;
        gap: .5rem; 
    }
    .shopping-cart .box-container .box .qty {
        width: 5rem; 
        font-size: 1.4rem; 
        padding: .5rem .8rem; 
        text-align: center;
    }
    .shopping-cart .box-container .box .option-btn {
        padding: .5rem 1rem; 
        font-size: 1.4rem; 
    }
    .shopping-cart .box-container .box .details,
    .shopping-cart .box-container .box .stock-warning {
        font-size: 1.2rem; 
        margin-top: .5rem;
    }
    .shopping-cart .box-container .box .sub-total {
        font-size: 1.5rem; 
        margin-top: 1rem;
    }
   </style>
</head>
<body>
   
<?php @include 'header.php'; ?>

<section class="heading">
    <h3>shopping cart</h3>
    <p> <a href="home.php">home</a> / cart </p>
</section>

<section class="shopping-cart">
    <h1 class="title">products added</h1>
    
    <div class="box-container" id="cart-box-container">
    <?php
        $grand_total = 0;
        $stmt_select = $conn->prepare("SELECT c.*, p.stock 
                                       FROM `cart` c 
                                       JOIN `products` p ON c.pid = p.id 
                                       WHERE c.user_id = ?");
        $stmt_select->execute([$user_id]);
        
        if($stmt_select->rowCount() > 0){
            while($fetch_cart = $stmt_select->fetch(PDO::FETCH_ASSOC)){
    ?>
    <div class="box" id="cart-item-box-<?php echo $fetch_cart['id']; ?>">
        
        <button type="button" class="fas fa-times delete-one-btn" 
                data-cart-id="<?php echo $fetch_cart['id']; ?>" 
                data-name="<?php echo htmlspecialchars($fetch_cart['name']); ?>"></button>
        
        <img src="flowers/<?php echo htmlspecialchars($fetch_cart['image']); ?>" alt="" class="image">
        <div class="name"><?php echo htmlspecialchars($fetch_cart['name']); ?></div>
        
        <div class="price">$<?php echo number_format($fetch_cart['price'], 2); ?>/-</div>
        
        <div class="update-form">
            <input type="hidden" class="product-stock" value="<?php echo $fetch_cart['stock']; ?>">
            <input type="number" min="1" max="<?php echo $fetch_cart['stock']; ?>" value="<?php echo $fetch_cart['quantity']; ?>" class="qty cart-quantity-input">
            <button type="button" class="option-btn update-cart-btn" 
                    data-cart-id="<?php echo $fetch_cart['id']; ?>" 
                    data-stock="<?php echo $fetch_cart['stock']; ?>"
                    data-price="<?php echo $fetch_cart['price']; ?>">Update</button>
        </div>
        
        <div class="details stock-warning" 
             style="font-weight: bold; <?php if($fetch_cart['quantity'] <= $fetch_cart['stock']) echo 'display:none;'; ?>">
            Warning: Not enough stock!
        </div>

        <div class="sub-total"> sub-total : 
            <span id="subtotal-<?php echo $fetch_cart['id']; ?>">
                $<?php echo number_format($sub_total = ($fetch_cart['price'] * $fetch_cart['quantity']), 2); ?>/-
            </span> 
        </div>
    </div>
    <?php
    $grand_total += $sub_total;
        }
    }else{
        echo '<p class="empty" id="cart-empty-msg">your cart is empty</p>';
    }
    ?>
    </div>

    <div class="more-btn">
        <button type="button" id="delete-all-btn" class="delete-btn <?php echo ($grand_total > 0)?'':'disabled' ?>">delete all</button>
    </div>

    <div class="cart-total">
        <p>grand total : <span id="grand-total-span">$<?php echo number_format($grand_total, 2); ?>/-</span></p>
        <a href="shop.php" class="option-btn">continue shopping</a>
        <a href="checkout.php" class="btn checkout-btn <?php echo ($grand_total > 0)?'':'disabled' ?>">proceed to checkout</a>
    </div>
</section>

<?php @include 'footer.php'; ?>

<div id="delete-all-modal" class="delete-modal">
    <div class="delete-modal-content">
        <span class="delete-modal-close">&times;</span>
        <h3>Delete all items from cart?</h3>
        <p>This action cannot be undone.</p>
        <div class="delete-modal-buttons">
            <button type="button" class="option-btn" id="delete-modal-cancel">Cancel</button>
            <button type="button" class="delete-btn" id="delete-modal-confirm">Delete All</button>
        </div>
    </div>
</div>
<div id="delete-one-modal" class="delete-modal">
    <div class="delete-modal-content">
        <span class="delete-modal-close">&times;</span>
        <h3>Delete this item?</h3>
        <p style="color:var(--main-color); font-size: 2rem; font-weight: bold;" id="delete-item-name"></p>
        <p>Are you sure you want to remove this item from your cart?</p>
        <div class="delete-modal-buttons">
            <button type="button" class="option-btn" id="delete-one-modal-cancel">Cancel</button>
            <button type="button" class="delete-btn" id="delete-one-modal-confirm">Delete</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    var grandTotalSpan = document.getElementById('grand-total-span');
    var cartContainer = document.getElementById('cart-box-container');
    var deleteAllBtn = document.getElementById('delete-all-btn');
    var checkoutBtn = document.querySelector('.checkout-btn');

    function updateGrandTotal(newTotal) {
        var newGrandTotal = parseFloat(newTotal);
        grandTotalSpan.textContent = '$' + newGrandTotal.toFixed(2) + '/-';
        
        if (newGrandTotal <= 0) {
            cartContainer.innerHTML = '<p class="empty" id="cart-empty-msg">your cart is empty</p>';
            deleteAllBtn.classList.add('disabled');
            checkoutBtn.classList.add('disabled');
        } else {
            deleteAllBtn.classList.remove('disabled');
            checkoutBtn.classList.remove('disabled');
        }
    }

    // === XỬ LÝ NÚT UPDATE ===
    document.querySelectorAll('.update-cart-btn').forEach(button => {
        button.addEventListener('click', function() {
            var cartId = this.dataset.cartId;
            var stock = parseInt(this.dataset.stock);
            var box = document.getElementById('cart-item-box-' + cartId);
            var quantityInput = box.querySelector('.cart-quantity-input');
            var newQuantity = parseInt(quantityInput.value);
            var stockWarning = box.querySelector('.stock-warning');

            if (newQuantity > stock) {
                alert('Chỉ còn ' + stock + ' sản phẩm trong kho!');
                quantityInput.value = stock; 
                if (stockWarning) stockWarning.style.display = 'block';
                return; 
            }
            if (newQuantity <= 0) {
                alert('Số lượng phải lớn hơn 0!');
                quantityInput.value = 1;
                if (stockWarning) stockWarning.style.display = 'none';
                return; 
            }
            if (stockWarning) {
                stockWarning.style.display = 'none';
            }
            var formData = new FormData();
            formData.append('action', 'update');
            formData.append('cart_id', cartId);
            formData.append('cart_quantity', newQuantity);
            fetch('cart.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    var subtotalElement = document.getElementById('subtotal-' + cartId);
                    var newSubTotal = parseFloat(data.new_sub_total); 
                    subtotalElement.textContent = '$' + newSubTotal.toFixed(2) + '/-';
                    updateGrandTotal(data.new_grand_total);
                } else {
                    alert(data.message); 
                }
            })
            .catch(error => console.error('Error:', error));
        });
    });

    // === XỬ LÝ MODAL "DELETE ALL" ===
    var deleteAllModal = document.getElementById('delete-all-modal');
    var closeDeleteAllModal = deleteAllModal.querySelector('.delete-modal-close');
    var cancelDeleteAllBtn = deleteAllModal.querySelector('#delete-modal-cancel');
    var confirmDeleteAllBtn = deleteAllModal.querySelector('#delete-modal-confirm');
    deleteAllBtn.onclick = function() { deleteAllModal.style.display = 'block'; }
    closeDeleteAllModal.onclick = function() { deleteAllModal.style.display = 'none'; }
    cancelDeleteAllBtn.onclick = function() { deleteAllModal.style.display = 'none'; }
    confirmDeleteAllBtn.onclick = function() {
        var formData = new FormData();
        formData.append('action', 'delete_all'); 
        fetch('cart.php', { method: 'POST', body: formData }) 
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateGrandTotal(0); 
                deleteAllModal.style.display = 'none';
            } else {
                alert(data.message);
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // === XỬ LÝ MODAL "DELETE ONE" ===
    var deleteOneModal = document.getElementById('delete-one-modal');
    var closeDeleteOneModal = deleteOneModal.querySelector('.delete-modal-close');
    var cancelDeleteOneBtn = deleteOneModal.querySelector('#delete-one-modal-cancel');
    var confirmDeleteOneBtn = deleteOneModal.querySelector('#delete-one-modal-confirm');
    var deleteItemName = deleteOneModal.querySelector('#delete-item-name');
    document.querySelectorAll('.delete-one-btn').forEach(button => {
        button.onclick = function() {
            var cartId = this.dataset.cartId;
            var name = this.dataset.name;
            deleteItemName.textContent = name; 
            confirmDeleteOneBtn.dataset.cartId = cartId; 
            deleteOneModal.style.display = 'block';
        }
    });
    function closeOneModal() { deleteOneModal.style.display = 'none'; }
    closeDeleteOneModal.onclick = closeOneModal;
    cancelDeleteOneBtn.onclick = closeOneModal;
    confirmDeleteOneBtn.onclick = function() {
        var cartId = this.dataset.cartId;
        var formData = new FormData();
        formData.append('action', 'delete_one'); 
        formData.append('cart_id', cartId); 
        fetch('cart.php', { method: 'POST', body: formData }) 
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('cart-item-box-' + cartId).remove();
                updateGrandTotal(data.new_grand_total);
                closeOneModal();
            } else {
                alert(data.message);
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // Đóng modal khi bấm ra ngoài
    window.addEventListener('click', function(event) {
        if (event.target == deleteAllModal) deleteAllModal.style.display = 'none';
        if (event.target == deleteOneModal) deleteOneModal.style.display = 'none';
    });

});
</script>

<script src="js/script.js"></script>
</body>
</html>