<?php
@include 'config.php';
session_start();

// ================================================================
// (MỚI) 1: KHỐI XỬ LÝ AJAX (CHO NÚT "VIEW DETAILS")
// ================================================================
if (isset($_POST['action']) && $_POST['action'] == 'get_details') {
    
    $response = ['success' => false, 'message' => 'Invalid Request', 'product' => null];
    $product_id = $_POST['product_id'] ?? null;

    if ($product_id) {
        try {
            // (Lưu ý: Chúng ta cần kết nối $conn, đã có ở trên)
            $stmt = $conn->prepare("SELECT * FROM `products` WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                $response['success'] = true;
                $response['message'] = 'Product details fetched.';
                $response['product'] = $product;
            } else {
                $response['message'] = 'Product not found.';
            }
        } catch (Exception $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit; // Dừng script sau khi trả về JSON
}
// ================================================================
// (LOGIC TẢI TRANG BÌNH THƯỜNG - Giữ nguyên)
// ================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Home</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/style.css">
   
   <style>
    .product-detail-modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.7); }
    .product-detail-modal-content { background-color: var(--white); margin: 5% auto; padding: 2rem; border: var(--border); border-radius: .5rem; width: 90%; max-width: 60rem; position: relative; display: flex; flex-wrap: wrap; gap: 2rem; align-items: flex-start; }
    .product-detail-modal-close { color: #aaa; position: absolute; top: 10px; right: 25px; font-size: 35px; font-weight: bold; }
    .product-detail-modal-close:hover, .product-detail-modal-close:focus { color: black; text-decoration: none; cursor: pointer; }
    .product-detail-modal-image { flex: 1 1 25rem; text-align: center; }
    .product-detail-modal-image img { max-width: 50%; height: auto; border-radius: .5rem; }
    .product-detail-modal-info { flex: 1 1 30rem; text-align: left; }
    .product-detail-modal-info .name { font-size: 2.5rem; color: var(--black); margin-bottom: 1rem; }
    .product-detail-modal-info .price { font-size: 2.2rem; color: var(--main-color); margin-bottom: 1rem; }
    .product-detail-modal-info .details { font-size: 1.6rem; color: var(--light-color); line-height: 1.8; margin-bottom: 1.5rem; }
    .product-detail-modal-info .stock { font-size: 1.6rem; color: var(--black); margin-bottom: 1rem; }
    .product-detail-modal-loading { font-size: 2rem; text-align: center; padding: 3rem; color: var(--light-color); }
   </style>
</head>
<body>
<?php @include 'header.php'; ?>

<section class="home">
   <div class="content">
      <h3>Best Sellers</h3>
      <span>Beautiful flowers for every occasion</span>
      <p>Discover our most loved arrangements, handcrafted with care and passion by our expert florists.</p>
      <a href="shop.php" class="btn">Shop Now</a>
   </div>
</section>

<section class="products">
   <h1 class="title">Featured Products</h1>
   <div class="box-container">
      <?php
         $stmt_select = $conn->prepare("SELECT * FROM `products` LIMIT 6");
         $stmt_select->execute();
         if($stmt_select->rowCount() > 0){
            while($fetch_products = $stmt_select->fetch(PDO::FETCH_ASSOC)){
      ?>
      <div class="box">
         <div class="price">$<?php echo number_format($fetch_products['price'], 2); ?>/-</div>
         <img src="flowers/<?php echo $fetch_products['image']; ?>" alt="" class="image">
         <div class="name"><?php echo $fetch_products['name']; ?></div>
         <div class="details">Stock: <?php echo $fetch_products['stock'] ?? 0; ?></div>
         <button type="button" class="option-btn view-product-details-btn" 
                 data-product-id="<?php echo $fetch_products['id']; ?>">View Details</button>
      </div>
      <?php
         }
      }else{ echo '<p class="empty">No products added yet!</p>'; }
      ?>
   </div>
   <div class="load-more" style="text-align: center; margin-top: 2rem;">
       <a href="shop.php" class="option-btn">View All Products</a>
   </div>
</section>

<section class="products">
   <h1 class="title">Our Best Sellers</h1>
   <div class="box-container">
      <?php
         $stmt_bestsellers = $conn->prepare("
            SELECT p.id, p.name, p.price, p.image, p.stock, SUM(od.quantity) AS total_sold
            FROM `order_details` od
            JOIN `products` p ON od.product_id = p.id
            GROUP BY od.product_id ORDER BY total_sold DESC LIMIT 4
         ");
         $stmt_bestsellers->execute();
         if($stmt_bestsellers->rowCount() > 0){
            while($fetch_bestsellers = $stmt_bestsellers->fetch(PDO::FETCH_ASSOC)){
      ?>
      <div class="box">
         <div class="price">$<?php echo number_format($fetch_bestsellers['price'], 2); ?>/-</div>
         <img src="flowers/<?php echo $fetch_bestsellers['image']; ?>" alt="" class="image">
         <div class="name"><?php echo $fetch_bestsellers['name']; ?></div>
         <div class="details" style="color:var(--red);">Sold: <?php echo $fetch_bestsellers['total_sold']; ?></div>
         <button type="button" class="option-btn view-product-details-btn" 
                 data-product-id="<?php echo $fetch_bestsellers['id']; ?>">View Details</button>
      </div>
      <?php
         }
      }else{ echo '<p class="empty">No sales data found yet!</p>'; }
      ?>
   </div>
</section>

<div id="product-detail-modal" class="product-detail-modal">
    <div class="product-detail-modal-content" id="product-detail-modal-body">
        <p class="product-detail-modal-loading">Loading...</p>
    </div>
</div>


<?php @include 'footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    var productModal = document.getElementById('product-detail-modal');
    var productModalBody = document.getElementById('product-detail-modal-body');

    document.querySelectorAll('.view-product-details-btn').forEach(button => {
        button.onclick = function() {
            var productId = this.dataset.productId;
            
            productModal.style.display = 'block';
            productModalBody.innerHTML = '<p class="product-detail-modal-loading">Loading...</p>';
            
            var formData = new FormData();
            formData.append('action', 'get_details');
            formData.append('product_id', productId); 

            // (SỬA) Gửi AJAX đến 'home.php'
            fetch('home.php', { 
                method: 'POST', 
                body: formData 
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.product) {
                    var p = data.product;
                    var html = `
                        <span class="product-detail-modal-close">&times;</span>
                        <div class="product-detail-modal-image">
                            <img src="flowers/${p.image}" alt="">
                        </div>
                        <div class="product-detail-modal-info">
                            <div class="name">${p.name}</div>
                            <div class="price">$${parseFloat(p.price).toFixed(2)}/-</div>
                            <div class="stock">Stock: ${p.stock}</div>
                            <div class="details">${p.details}</div>
                        </div>
                    `;
                    productModalBody.innerHTML = html;
                    
                    productModalBody.querySelector('.product-detail-modal-close').onclick = function() {
                        productModal.style.display = 'none';
                    }
                } else {
                    productModalBody.innerHTML = '<p class="product-detail-modal-loading">Error: ' + data.message + '</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                productModalBody.innerHTML = '<p class="product-detail-modal-loading">Error loading details.</p>';
            });
        }
    });

    // Đóng modal "View Details" khi bấm ra ngoài
    window.addEventListener('click', function(event) {
        if (event.target == productModal) {
            productModal.style.display = 'none';
        }
    });

});
</script>

<script src="js/script.js"></script>
</body>
</html>