<?php
@include 'config.php';
session_start();

// (SỬA) 1: Kiểm tra session admin/staff
$admin_id = $_SESSION['user_id'] ?? null;
$admin_type = $_SESSION['role'] ?? null; 
if(!isset($admin_id) || ($admin_type != 'admin' && $admin_type != 'staff')){
   header('location:login.php');
   exit();
}

// (SỬA) 2: Logic THÊM SẢN PHẨM (dùng PDO)
if(isset($_POST['add_product'])){
   $name = $_POST['name'];
   $price = $_POST['price'];
   $details = $_POST['details'];
   $category_id = $_POST['category_id']; // (MỚI)
   $stock = $_POST['stock']; // (MỚI)

   $image = $_FILES['image']['name'];
   $image_size = $_FILES['image']['size'];
   $image_tmp_name = $_FILES['image']['tmp_name'];
   $image_folder = 'flowers/'.$image; // (SỬA) Dùng thư mục 'flowers/'

   $stmt_check = $conn->prepare("SELECT name FROM `products` WHERE name = ?");
   $stmt_check->execute([$name]);

   if($stmt_check->rowCount() > 0){
      $message[] = 'product name already exist!';
   }else{
      if($image_size > 2000000){
         $message[] = 'image size is too large!';
      }else{
         // (SỬA) Thêm category_id và stock vào CSDL
         $stmt_insert = $conn->prepare("INSERT INTO `products`(name, details, price, category_id, stock, image) VALUES(?,?,?,?,?,?)");
         $stmt_insert->execute([$name, $details, $price, $category_id, $stock, $image]);
         move_uploaded_file($image_tmp_name, $image_folder);
         $message[] = 'product added successfully!';
      }
   }
}

// (SỬA) 3: Logic XÓA SẢN PHẨM (dùng PDO)
if(isset($_GET['delete'])){
   $delete_id = $_GET['delete'];
   
   $stmt_img = $conn->prepare("SELECT image FROM `products` WHERE id = ?");
   $stmt_img->execute([$delete_id]);
   $fetch_delete_image = $stmt_img->fetch(PDO::FETCH_ASSOC);
   
   if($fetch_delete_image && file_exists('flowers/'.$fetch_delete_image['image'])){
       unlink('flowers/'.$fetch_delete_image['image']);
   }
   
   $stmt_del_prod = $conn->prepare("DELETE FROM `products` WHERE id = ?");
   $stmt_del_prod->execute([$delete_id]);
   
   $stmt_del_wish = $conn->prepare("DELETE FROM `wishlist` WHERE pid = ?");
   $stmt_del_wish->execute([$delete_id]);
   
   $stmt_del_cart = $conn->prepare("DELETE FROM `cart` WHERE pid = ?");
   $stmt_del_cart->execute([$delete_id]);
   
   header('location:admin_products.php');
   exit();
}

// (MỚI) 4: Logic CẬP NHẬT SẢN PHẨM (từ Modal)
if(isset($_POST['update_product'])){
    $pid = $_POST['pid'];
    $name = $_POST['name'];
    $price = $_POST['price'];
    $details = $_POST['details'];
    $category_id = $_POST['category_id'];
    $stock = $_POST['stock'];
    
    $stmt_update = $conn->prepare("UPDATE `products` SET name = ?, price = ?, details = ?, category_id = ?, stock = ? WHERE id = ?");
    $stmt_update->execute([$name, $price, $details, $category_id, $stock, $pid]);

    $new_image = $_FILES['new_image']['name'];
    if(!empty($new_image)){
        $image_size = $_FILES['new_image']['size'];
        $image_tmp_name = $_FILES['new_image']['tmp_name'];
        $image_folder = 'flowers/'.$new_image;
        
        if($image_size > 2000000){
            $message[] = 'Image size is too large!';
        } else {
            $stmt_update_img = $conn->prepare("UPDATE `products` SET image = ? WHERE id = ?");
            $stmt_update_img->execute([$new_image, $pid]);
            move_uploaded_file($image_tmp_name, $image_folder);
            
            $old_image = $_POST['old_image'];
            if($old_image != 'default-avatar.png' && file_exists('flowers/'.$old_image)){
                unlink('flowers/'.$old_image);
            }
        }
    }
    $message[] = 'Product updated successfully!';
}

// (MỚI) 5: Lấy danh sách Categories (dùng cho form)
$categories = $conn->query("SELECT * FROM `categories` ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// (MỚI) 6: Lọc và Tìm kiếm
$where_clauses = [];
$params = []; 
$filter_link = ''; 

$filter_category = $_GET['filter_category'] ?? '';
if(!empty($filter_category)){
    $where_clauses[] = "p.category_id = ?";
    $params[] = $filter_category;
    $filter_link .= '&filter_category=' . $filter_category;
}
$search_key = $_GET['search'] ?? '';
if(!empty($search_key)){
    $where_clauses[] = "p.name LIKE ?";
    $params[] = "%{$search_key}%";
    $filter_link .= '&search=' . htmlspecialchars($search_key);
}
$sql_where = "";
if(!empty($where_clauses)){
    $sql_where = " WHERE " . implode(" AND ", $where_clauses);
}

// (MỚI) 7: Phân trang
$per_page = 10;
$current_page = $_GET['page'] ?? 1;
$offset = ((int)$current_page - 1) * $per_page;
$count_sql = "SELECT COUNT(*) FROM `products` p" . $sql_where;
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total_products = $count_stmt->fetchColumn();
$total_pages = ceil($total_products / $per_page);

// (MỚI) 8: Câu truy vấn chính
$products_sql = "SELECT p.*, c.name as category_name 
                 FROM `products` p 
                 LEFT JOIN `categories` c ON p.category_id = c.id" 
                 . $sql_where . " ORDER BY p.name ASC LIMIT ? OFFSET ?";
$select_products = $conn->prepare($products_sql);
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
   <title>Products</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/admin_style.css">

   <style>
    /* Ẩn .add-products (Form cũ) */
    .add-products {
        display: none; 
    }
    .product-table-container { overflow-x: auto; }
    .product-table { width: 100%; min-width: 100rem; background: var(--white); border-collapse: collapse; box-shadow: var(--box_shadow); border-radius: .5rem; overflow: hidden; }
    .product-table th,
    .product-table td { padding: 1.2rem 1.5rem; font-size: 1.6rem; text-align: left; border-bottom: 1px solid var(--light-bg); }
    .product-table th { background-color: var(--light-bg); color: var(--black); }
    .product-table td { color: var(--light-color); }
    .product-table td .product-image { height: 7rem; width: 7rem; object-fit: cover; border-radius: .5rem; }
    .product-table .details { font-size: 1.4rem; }
    .product-table .actions { display: flex; gap: .5rem; }
    
    .filter-container { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
    .filter-container .box { font-size: 1.6rem; padding: 1rem 1.2rem; border: var(--border); border-radius: .5rem; background: var(--white); }
    .filter-container .search-form { display: flex; flex-grow: 1; }
    .filter-container .search-form .box { width: 100%; border-radius: .5rem 0 0 .5rem; }
    .filter-container .search-form .btn { margin-top: 0; border-radius: 0 .5rem .5rem 0; }
    
    /* (MỚI) Nút "Add Product" */
    .product-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }
    
    /* Modal */
    .modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); }
    .modal-content { background-color: var(--light-bg); margin: 5% auto; padding: 2rem; border: var(--border); width: 90%; max-width: 60rem; border-radius: .5rem; position: relative; }
    .modal-close { color: #aaa; position: absolute; top: 1rem; right: 2rem; font-size: 3rem; font-weight: bold; cursor: pointer; }
    .modal-content .box { width: 100%; font-size: 1.8rem; color:var(--black); border-radius: .5rem; background-color: var(--white); padding:1.2rem 1.4rem; border:var(--border); margin:1rem 0; }
    .modal-content h3 { font-size: 2.5rem; text-transform: uppercase; color:var(--black); margin-bottom: 1rem; text-align: center; }
    .modal-content .flex { display: flex; gap: 1rem; }
    .modal-content .flex .inputBox { width: 50%; }
    .modal-content .current-image { height: 10rem; border-radius: .5rem; margin-top: 1rem; }
    .modal-content textarea.box { height: 10rem; resize: none; }
    
    /* Phân trang */
    .pagination { text-align: center; padding: 2rem 0; }
    .pagination a { display: inline-block; padding: 1rem 1.5rem; margin: 0 .5rem; background: var(--white); color: var(--pink); border: var(--border); font-size: 1.6rem; border-radius: .5rem; }
    .pagination a:hover, .pagination a.active { background: var(--pink); color: var(--white); }
   </style>
</head>
<body>
   
<?php @include 'admin_header.php'; ?>

<section class="placed-orders"> 

   <div class="product-controls">
        <h1 class="title">shop products</h1>
        <button type="button" class="btn" id="add-product-btn">Add New Product</button>
   </div>

   <div class="filter-container">
        <form action="" method="GET" class="search-form">
            <input type="text" name="search" class="box" placeholder="Search product name..." value="<?php echo htmlspecialchars($search_key); ?>">
            <button type="submit" class="btn fas fa-search" ></button>
        </form>
        <form action="" method="GET">
            <select name="filter_category" class="box" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php if($filter_category == $cat['id']) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($cat['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
   </div>

   <div class="product-table-container">
      <table class="product-table">
         <thead>
            <tr>
               <th>Image</th>
               <th>Name</th>
               <th>Category</th>
               <th>Price</th>
               <th>Stock</th>
               <th>Details</th>
               <th>Actions</th>
            </tr>
         </thead>
         <tbody>
            <?php
               if($select_products->rowCount() > 0){
                  while($fetch_products = $select_products->fetch(PDO::FETCH_ASSOC)){
            ?>
            <tr class="product-row" 
                data-pid="<?php echo $fetch_products['id']; ?>"
                data-name="<?php echo htmlspecialchars($fetch_products['name']); ?>"
                data-price="<?php echo $fetch_products['price']; ?>"
                data-stock="<?php echo $fetch_products['stock']; ?>"
                data-category-id="<?php echo $fetch_products['category_id']; ?>"
                data-details="<?php echo htmlspecialchars($fetch_products['details']); ?>"
                data-image="<?php echo htmlspecialchars($fetch_products['image']); ?>"
                >
                
               <td><img src="flowers/<?php echo $fetch_products['image']; ?>" alt="" class="product-image"></td>
               <td><?php echo $fetch_products['name']; ?></td>
               <td><?php echo $fetch_products['category_name'] ?? 'N/A'; ?></td>
               <td>$<?php echo number_format($fetch_products['price'], 2); ?>/-</td>
               <td><?php echo $fetch_products['stock']; ?></td>
               <td class="details"><?php echo $fetch_products['details']; ?></td>
               <td class="actions">
                  <button type="button" class="option-btn update-product-btn">update</button>
                  <a href="admin_products.php?delete=<?php echo $fetch_products['id']; ?>" class="delete-btn" onclick="return confirm('delete this product?');">delete</a>
               </td>
            </tr>
            <?php
               }
            }else{
               echo '<tr><td colspan="7"><p class="empty">no products found!</p></td></tr>';
            }
            ?>
         </tbody>
      </table>
   </div>
   
   <div class="pagination">
        <?php if($total_pages > 1): ?>
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?><?php echo $filter_link; ?>" 
                   class="<?php echo ($i == $current_page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        <?php endif; ?>
    </div>

</section>

<div id="add-product-modal" class="modal">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <form action="" method="POST" enctype="multipart/form-data">
            <h3>add new product</h3>
            <input type="text" class="box" required placeholder="enter product name" name="name">
            <input type="number" min="0" step="0.01" class="box" required placeholder="enter product price" name="price">
            
            <select name="category_id" class="box" required>
                <option value="" disabled selected>select category</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                <?php endforeach; ?>
            </select>
            
            <input type="number" min="0" class="box" required placeholder="enter product stock" name="stock">
            
            <textarea name="details" class="box" required placeholder="enter product details" cols="30" rows="10"></textarea>
            <input type="file" accept="image/jpg, image/jpeg, image/png" required class="box" name="image">
            <input type="submit" value="add product" name="add_product" class="btn">
        </form>
    </div>
</div>

<div id="update-modal" class="modal">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <form action="" method="POST" enctype="multipart/form-data">
            <h3>Update Product</h3>
            <input type="hidden" name="pid" id="update_p_id">
            <input type="hidden" name="old_image" id="update_p_old_image">
            
            <img src="" alt="current image" class="current-image" id="update_p_image_preview">
            
            <div class="inputBox">
                <span>Product Name:</span>
                <input type="text" class="box" required placeholder="enter product name" name="name" id="update_p_name">
            </div>
            
            <div class="flex">
                <div class="inputBox">
                    <span>Price:</span>
                    <input type="number" min="0" step="0.01" class="box" required placeholder="enter product price" name="price" id="update_p_price">
                </div>
                <div class="inputBox">
                    <span>Stock:</span>
                    <input type="number" min="0" class="box" required placeholder="enter product stock" name="stock" id="update_p_stock">
                </div>
            </div>
            
            <div class="inputBox">
                <span>Category:</span>
                <select name="category_id" class="box" required id="update_p_category">
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="inputBox">
                <span>Details:</span>
                <textarea name="details" class="box" required placeholder="enter product details" cols="30" rows="5" id="update_p_details"></textarea>
            </div>
            
            <div class="inputBox">
                <span>Update Image (optional):</span>
                <input type="file" accept="image/jpg, image/jpeg, image/png" class="box" name="new_image">
            </div>
            
            <input type="submit" value="Update Product" name="update_product" class="btn">
        </form>
    </div>
</div>


<script src="js/admin_script.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // === Modal 1: Add Product ===
    var addModal = document.getElementById('add-product-modal');
    var addBtn = document.getElementById('add-product-btn');
    var addCloseBtn = addModal.querySelector('.modal-close');

    addBtn.onclick = function() {
        addModal.style.display = 'block';
    }
    addCloseBtn.onclick = function() {
        addModal.style.display = 'none';
    }
    
    // === Modal 2: Update Product ===
    var updateModal = document.getElementById('update-modal');
    var updateCloseBtn = updateModal.querySelector('.modal-close');

    document.querySelectorAll('.update-product-btn').forEach(button => {
        button.addEventListener('click', function() {
            var row = this.closest('.product-row');
            var data = row.dataset;

            // Điền dữ liệu vào form update
            document.getElementById('update_p_id').value = data.pid;
            document.getElementById('update_p_old_image').value = data.image;
            document.getElementById('update_p_image_preview').src = 'flowers/' + data.image;
            document.getElementById('update_p_name').value = data.name;
            document.getElementById('update_p_price').value = data.price;
            document.getElementById('update_p_stock').value = data.stock;
            document.getElementById('update_p_category').value = data.categoryId;
            document.getElementById('update_p_details').value = data.details;
            
            updateModal.style.display = 'block';
        });
    });

    updateCloseBtn.onclick = function() {
        updateModal.style.display = 'none';
    }
    
    // Đóng khi bấm ra ngoài
    window.onclick = function(event) {
        if (event.target == addModal) {
            addModal.style.display = 'none';
        }
        if (event.target == updateModal) {
            updateModal.style.display = 'none';
        }
    }
});
</script>

</body>
</html>