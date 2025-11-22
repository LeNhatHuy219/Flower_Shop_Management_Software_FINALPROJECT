<?php
@include 'config.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? null;
$message = [];
if(!isset($user_id) || $role != 'user'){
   header('location:login.php');
   exit();
}

// ================================================================
// (MỚI) KHỐI XỬ LÝ AJAX (CHO NÚT "VIEW DETAILS")
// ================================================================
if (isset($_POST['action']) && $_POST['action'] == 'get_details') {
    
    $response = ['success' => false, 'message' => 'Invalid Request', 'product' => null];
    $product_id = $_POST['product_id'] ?? null;

    if ($product_id) {
        try {
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
    exit;
}

// ===========================================
// LOGIC "ADD TO CART" (UC06) - Giữ nguyên
// ===========================================
if(isset($_POST['add_to_cart'])){
    if(!$user_id){
        header('location:login.php');
        exit();
    }
    $product_id = $_POST['product_id'];
    $product_name = $_POST['product_name'];
    $product_price = $_POST['product_price'];
    $product_image = $_POST['product_image'];
    $product_quantity = (int)$_POST['product_quantity'];
    $product_stock = (int)$_POST['product_stock']; 

    if($product_quantity > $product_stock){
         $message[] = 'Không đủ hàng tồn kho!';
    } else {
        $stmt_check_cart = $conn->prepare("SELECT * FROM `cart` WHERE user_id = ? AND pid = ?");
        $stmt_check_cart->execute([$user_id, $product_id]);
        
        if($stmt_check_cart->rowCount() > 0){
            $stmt_update_cart = $conn->prepare("UPDATE `cart` SET quantity = quantity + ? WHERE user_id = ? AND pid = ?");
            $stmt_update_cart->execute([$product_quantity, $user_id, $product_id]);
        } else {
            $stmt_insert_cart = $conn->prepare("INSERT INTO `cart`(user_id, pid, name, price, quantity, image) VALUES(?,?,?,?,?,?)");
            $stmt_insert_cart->execute([$user_id, $product_id, $product_name, $product_price, $product_quantity, $product_image]);
        }
        $message[] = 'Sản phẩm đã được thêm vào giỏ!';
    }
    $redirect_url = "shop.php?";
    if(isset($_GET['category_id'])) $redirect_url .= "category_id=" . $_GET['category_id'] . "&";
    if(isset($_GET['min_price'])) $redirect_url .= "min_price=" . $_GET['min_price'] . "&";
    if(isset($_GET['max_price'])) $redirect_url .= "max_price=" . $_GET['max_price'] . "&";
    if(isset($_GET['page'])) $redirect_url .= "page=" . $_GET['page'];
    
    header('location:' . $redirect_url); 
    exit();
}

// ===========================================
// LOGIC LỌC VÀ PHÂN TRANG (Giữ nguyên)
// ===========================================
$per_page = 6; 
$current_page = $_GET['page'] ?? 1;
$current_page = (int)$current_page;
$offset = ($current_page - 1) * $per_page;
$category_id = $_GET['category_id'] ?? null;
$category_id = $category_id ? (int)$category_id : null;
$category_name = "All Products"; 
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$sql_where_parts = []; 
$params = []; 
$query_string = ""; 
if($category_id){
    $sql_where_parts[] = "category_id = ?";
    $params[] = $category_id;
    $query_string .= "&category_id=" . $category_id;
    $stmt_cat_name = $conn->prepare("SELECT name FROM `categories` WHERE id = ?");
    $stmt_cat_name->execute([$category_id]);
    $category_name = $stmt_cat_name->fetchColumn() ?? "Products";
}
if(!empty($min_price) && !empty($max_price)){
    $sql_where_parts[] = "price BETWEEN ? AND ?";
    $params[] = (float)$min_price;
    $params[] = (float)$max_price;
    $query_string .= "&min_price=" . htmlspecialchars($min_price) . "&max_price=" . htmlspecialchars($max_price);
}
$sql_where = "";
if (!empty($sql_where_parts)) {
    $sql_where = "WHERE " . implode(" AND ", $sql_where_parts);
}
$count_sql = "SELECT COUNT(*) FROM `products` " . $sql_where;
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total_products = $count_stmt->fetchColumn();
$total_pages = ceil($total_products / $per_page);
$sql = "SELECT * FROM `products` " . $sql_where . " LIMIT ? OFFSET ?";
$select_products = $conn->prepare($sql);
$param_index = 1;
foreach ($params as $param) {
    $select_products->bindValue($param_index, $param);
    $param_index++;
}
$select_products->bindValue($param_index, $per_page, PDO::PARAM_INT);
$param_index++;
$select_products->bindValue($param_index, $offset, PDO::PARAM_INT);
$select_products->execute();
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Shop</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/style.css">

   <style>
    /* CSS Phân trang (Giữ nguyên) */
    .pagination { text-align: center; padding: 2rem 0; }
    .pagination a, .pagination a:visited { display: inline-block; padding: 1rem 1.5rem; margin: 0 .5rem; background: var(--white); color: var(--pink); border: var(--border); font-size: 1.6rem; border-radius: .5rem; text-decoration: none; }
    .pagination a:hover { background: var(--pink); color: var(--white); }
    .pagination a.active, .pagination a.active:visited { background: var(--pink); color: var(--white); cursor: default; pointer-events: none; }
    .pagination a.disabled { background: var(--bg-color); color: var(--light-color); pointer-events: none; }
    
    /* CSS Bộ lọc ngang (Giữ nguyên) */
    .filter-form { background: var(--bg-color); padding: 2rem; border: var(--border); border-radius: .5rem; margin-bottom: 2rem; display: flex; align-items: flex-end; justify-content: center; gap: 1.5rem; flex-wrap: wrap; }
    .filter-form .filter-group { display: flex; flex-direction: column; }
    .filter-form label { font-size: 1.6rem; color: var(--light-color); margin-bottom: .5rem; }
    .filter-form .box { font-size: 1.6rem; padding: .8rem 1rem; border: var(--border); border-radius: .5rem; background: var(--white); }
    .filter-form .price-inputs { display: flex; gap: .5rem; }
    .filter-form .price-inputs .box { width: 10rem; }
    .filter-form .btn { margin-top: 0; }
    
    /* =========================================== */
    /* (MỚI) CSS CHO MODAL "VIEW DETAILS" */
    /* =========================================== */
    .product-detail-modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.7); }
    .product-detail-modal-content { background-color: var(--white); margin: 5% auto; padding: 2rem; border: var(--border); border-radius: .5rem; width: 90%; max-width: 60rem; position: relative; display: flex; flex-wrap: wrap; gap: 2rem; align-items: flex-start; }
    .product-detail-modal-close { color: #aaa; position: absolute; top: 10px; right: 25px; font-size: 35px; font-weight: bold; }
    .product-detail-modal-close:hover, .product-detail-modal-close:focus { color: black; text-decoration: none; cursor: pointer; }
    .product-detail-modal-image { flex: 1 1 25rem; text-align: center; }
    .product-detail-modal-image img { max-width: 100%; height: auto; border-radius: .5rem; }
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

<section class="heading">
    <h3>Our Shop</h3>
    <p> <a href="home.php">home</a> / shop </p>
</section>

<section class="filter-form-container">
    <form action="shop.php" method="GET" class="filter-form">
        <div class="filter-group">
            <label for="category_id">Category:</label>
            <select name="category_id" id="category_id" class="box">
                <option value="">All Categories</option>
                <?php
                    $stmt_categories = $conn->prepare("SELECT * FROM `categories` ORDER BY name");
                    $stmt_categories->execute();
                    while($fetch_cat = $stmt_categories->fetch(PDO::FETCH_ASSOC)){
                        $selected = ($category_id == $fetch_cat['id']) ? 'selected' : '';
                        echo '<option value="'.$fetch_cat['id'].'" '.$selected.'>'.htmlspecialchars($fetch_cat['name']).'</option>';
                    }
                ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Price Range:</label>
            <div class="price-inputs">
                <input type="number" name="min_price" class="box" value="<?php echo htmlspecialchars($min_price); ?>" placeholder="Min $">
                <input type="number" name="max_price" class="box" value="<?php echo htmlspecialchars($max_price); ?>" placeholder="Max $">
            </div>
        </div>
        <div class="filter-group">
            <input type="submit" value="Apply Filters" class="btn">
        </div>
    </form>
</section>

<section class="products">
   <h1 class="title"><?php echo htmlspecialchars($category_name); ?></h1>
   
   <div class="box-container">
      <?php
         if($select_products->rowCount() > 0){
            while($fetch_products = $select_products->fetch(PDO::FETCH_ASSOC)){
      ?>
      <form action="" method="POST" class="box">
         <div class="price">$<?php echo $fetch_products['price']; ?>/-</div>
         <img src="flowers/<?php echo $fetch_products['image']; ?>" alt="" class="image">
         <div class="name"><?php echo $fetch_products['name']; ?></div>
         <div class="details">Stock: <?php echo $fetch_products['stock'] ?? 0; ?></div>
         
         <?php if(($fetch_products['stock'] ?? 0) > 0): ?>
            <input type="number" name="product_quantity" value="1" min="1" max="<?php echo $fetch_products['stock']; ?>" class="qty">
            <input type="hidden" name="product_id" value="<?php echo $fetch_products['id']; ?>">
            <input type="hidden" name="product_name" value="<?php echo $fetch_products['name']; ?>">
            <input type="hidden" name="product_price" value="<?php echo $fetch_products['price']; ?>">
            <input type="hidden" name="product_image" value="<?php echo $fetch_products['image']; ?>">
            <input type="hidden" name="product_stock" value="<?php echo $fetch_products['stock']; ?>">
            <input type="submit" value="add to cart" name="add_to_cart" class="btn">
         <?php else: ?>
            <p class="empty" style="background-color: var(--red); color:var(--white); margin-top: 1rem; padding: 1rem;">Out of Stock</p>
         <?php endif; ?>
         
         <button type="button" class="option-btn view-product-details-btn" 
                 data-product-id="<?php echo $fetch_products['id']; ?>" 
                 style="margin-top: 1rem;">View Details</button>
      </form>
      <?php
         }
      }else{
         echo '<p class="empty">No products found matching your criteria!</p>';
      }
      ?>
   </div>

   <div class="pagination">
        <?php if($total_pages > 1): ?>
            <?php if($current_page > 1): ?>
                <a href="?page=<?php echo $current_page - 1; ?><?php echo $query_string; ?>">&laquo; Prev</a>
            <?php endif; ?>
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?><?php echo $query_string; ?>" 
                   class="<?php echo ($i == $current_page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if($current_page < $total_pages): ?>
                <a href="?page=<?php echo $current_page + 1; ?><?php echo $query_string; ?>">Next &raquo;</a>
            <?php endif; ?>
        <?php endif; ?>
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
            
            // Hiển thị modal và text loading
            productModal.style.display = 'block';
            productModalBody.innerHTML = '<p class="product-detail-modal-loading">Loading...</p>';
            
            var formData = new FormData();
            formData.append('action', 'get_details');
Check:
            formData.append('product_id', productId); 

            // Gửi AJAX đến chính tệp shop.php
            fetch('shop.php', { 
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
                            <div class="price">$${p.price}/-</div>
                            <div class="stock">Stock: ${p.stock}</div>
                            <div class="details">${p.details}</div>
                        </div>
                    `;
                    productModalBody.innerHTML = html;
                    
                    // Thêm sự kiện đóng cho nút (x) mới
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