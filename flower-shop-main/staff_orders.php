<?php
@include 'config.php';
session_start();

// 1. Kiểm tra đăng nhập Staff
$staff_id = $_SESSION['user_id'] ?? null;
$staff_type = $_SESSION['role'] ?? null; 
if(!isset($staff_id) || $staff_type != 'staff'){
   header('location:login.php');
   exit();
}

// 2. Xử lý UPDATE (Giữ nguyên)
if(isset($_POST['update_order'])){
   $order_id = $_POST['order_id'];
   $update_payment = $_POST['update_payment'];
   $update_delivery = $_POST['update_delivery']; 

   $stmt = $conn->prepare("UPDATE `orders` SET payment_status = ?, delivery_status = ? WHERE id = ?");
   $stmt->execute([$update_payment, $update_delivery, $order_id]);
   
   $message[] = 'Order status has been updated!';
}

// 3. Xử lý DELETE (Giữ nguyên)
if(isset($_GET['delete'])){
   $delete_id = $_GET['delete'];
   $stmt = $conn->prepare("DELETE FROM `orders` WHERE id = ?");
   $stmt->execute([$delete_id]);
   header('location:staff_orders.php');
   exit();
}

// 4. (MỚI) Xử lý LỌC và TÌM KIẾM
$where_clauses = [];
$params = []; // (SỬA) Mảng này CHỈ dành cho WHERE
$filter_link = ''; 

if(isset($_GET['filter_payment'])){
    if($_GET['filter_payment'] == 'pending'){
        $where_clauses[] = "payment_status = ?";
        $params[] = 'pending';
        $filter_link .= '&filter_payment=pending';
    }
}

$search_key = $_GET['search'] ?? '';
if(!empty($search_key)){
    $where_clauses[] = "(name LIKE ? OR email LIKE ? OR id LIKE ?)";
    $search_param = "%{$search_key}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $filter_link .= '&search=' . htmlspecialchars($search_key);
}
$sql_where = "";
if(!empty($where_clauses)){
    $sql_where = " WHERE " . implode(" AND ", $where_clauses);
}

// 5. (MỚI) Xử lý Phân trang
$per_page = 10; 
$current_page = $_GET['page'] ?? 1;
$offset = ((int)$current_page - 1) * $per_page;

// Đếm tổng số đơn
$count_sql = "SELECT COUNT(*) FROM `orders`" . $sql_where;
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params); // Chỉ dùng params của WHERE
$total_orders = $count_stmt->fetchColumn();
$total_pages = ceil($total_orders / $per_page);

// ===========================================
// (SỬA) 6: KHẮC PHỤC LỖI PDO VỚI LIMIT/OFFSET
// ===========================================
$orders_sql = "SELECT * FROM `orders`" . $sql_where . " ORDER BY placed_on DESC LIMIT ? OFFSET ?";
$select_orders = $conn->prepare($orders_sql);

// Bind các giá trị WHERE (nếu có)
$param_index = 1;
foreach ($params as $param) {
    $select_orders->bindValue($param_index, $param);
    $param_index++;
}

// (SỬA) Bind LIMIT và OFFSET bằng tay (với kiểu INT)
// $param_index bây giờ sẽ là 1 (nếu ko lọc) hoặc 2, 3, 4 (nếu có lọc)
$select_orders->bindValue($param_index, $per_page, PDO::PARAM_INT);
$param_index++;
$select_orders->bindValue($param_index, $offset, PDO::PARAM_INT);

// (SỬA) Thực thi
$select_orders->execute(); // Đây là dòng 91

?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Orders</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/admin_style.css">

   <style>
    .placed-orders .box-container { display: block; max-width: 100%; overflow-x: auto; }
    .filter-tabs { display: flex; gap: 1rem; margin-bottom: 1.5rem; border-bottom: 2px solid var(--light-bg); }
    .filter-tabs a { padding: 1rem 1.5rem; font-size: 1.6rem; color: var(--light-color); border-bottom: 2px solid transparent; }
    .filter-tabs a.active, .filter-tabs a:hover { color: var(--pink); border-bottom-color: var(--pink); }
    .search-form { display: flex; margin-bottom: 2rem; }
    .search-form .box { width: 100%; font-size: 1.6rem; padding: 1rem 1.2rem; border: var(--border); border-radius: .5rem 0 0 .5rem; }
    .search-form .btn { margin-top: 0; border-radius: 0 .5rem .5rem 0; }
    .orders-table { width: 100%; min-width: 100rem; background: var(--white); border-collapse: collapse; box-shadow: var(--box_shadow); border-radius: .5rem; overflow: hidden; }
    .orders-table th, .orders-table td { padding: 1.2rem 1.5rem; font-size: 1.6rem; text-align: left; border-bottom: 1px solid var(--light-bg); }
    .orders-table th { background-color: var(--light-bg); color: var(--black); }
    .orders-table td { color: var(--light-color); }
    .orders-table .order-id { color: var(--pink); font-weight: 500; }
    .status-pill { display: inline-block; padding: .3rem 1rem; font-size: 1.3rem; border-radius: 1rem; color: var(--white); background: var(--light-color); text-transform: capitalize; }
    .status-pill.pending { background-color: var(--orange); }
    .status-pill.processing { background-color: var(--blue, #3498db); }
    .status-pill.completed, .status-pill.delivered { background-color: var(--green, #2ecc71); }
    .status-pill.cancelled { background-color: var(--red); }
    .modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); }
    .modal-content { background-color: var(--light-bg); margin: 5% auto; padding: 2rem; border: var(--border); width: 90%; max-width: 60rem; border-radius: .5rem; position: relative; }
    .modal-close { color: #aaa; position: absolute; top: 1rem; right: 2rem; font-size: 3rem; font-weight: bold; cursor: pointer; }
    .modal-content .box { background: var(--white); padding:2rem; border:var(--border); box-shadow: var(--box-shadow); border-radius: .5rem; }
    .modal-content .box p { margin-bottom: 1rem; font-size: 1.8rem; color:var(--light-color); }
    .modal-content .box p span { color:var(--pink); }
    .modal-content .box form { margin-top: 1rem; text-align: left; }
    .modal-content .box form select { width: 100%; border:var(--border); padding:1rem 1.2rem; font-size: 1.6rem; border-radius: .5rem; margin:.5rem 0; }
    .modal-content .box form .btn-group { display: flex; gap: 1rem; }
    .pagination { text-align: center; padding: 2rem 0; }
    .pagination a { display: inline-block; padding: 1rem 1.5rem; margin: 0 .5rem; background: var(--white); color: var(--pink); border: var(--border); font-size: 1.6rem; border-radius: .5rem; }
    .pagination a:hover, .pagination a.active { background: var(--pink); color: var(--white); }
   </style>
</head>
<body>
   
<?php @include 'staff_header.php'; ?>

<section class="placed-orders">

   <h1 class="title">placed orders</h1>

   <div class="filter-tabs">
        <a href="staff_orders.php" class="<?php echo (!isset($_GET['filter_payment']) && !isset($_GET['filter_delivery'])) ? 'active' : ''; ?>">All</a>
        <a href="?filter_payment=pending" class="<?php echo (isset($_GET['filter_payment']) && $_GET['filter_payment']=='pending') ? 'active' : ''; ?>">Unpaid</a>
        
   </div>

   <form action="" method="GET" class="search-form">
        <input type="text" name="search" class="box" placeholder="Search by customer name, email, or order ID..." value="<?php echo htmlspecialchars($search_key); ?>">
        <button type="submit" class="btn fas fa-search"></button>
   </form>

   <div class="box-container">
        <table class="orders-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Email</th>
                    <th>Total Price</th>
                    <th>Payment Status</th>
                    <th>Delivery Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if($select_orders->rowCount() > 0){
                    while($fetch_orders = $select_orders->fetch(PDO::FETCH_ASSOC)){
                        $order_id = $fetch_orders['id'];
                        $products_query = $conn->prepare("SELECT p.name, od.quantity FROM `order_details` od JOIN `products` p ON od.product_id = p.id WHERE od.order_id = ?");
                        $products_query->execute([$order_id]);
                        $product_list = '';
                        while($product = $products_query->fetch(PDO::FETCH_ASSOC)){
                            $product_list .= $product['name'] . ' (x' . $product['quantity'] . '), ';
                        }
                        $product_list = rtrim($product_list, ', ');
                ?>
                <tr>
                    <td><span class="order-id">#<?php echo $fetch_orders['id']; ?></span></td>
                    <td><?php echo date('Y-m-d', strtotime($fetch_orders['placed_on'])); ?></td>
                    <td><?php echo htmlspecialchars($fetch_orders['name']); ?></td>
                    <td><?php echo htmlspecialchars($fetch_orders['email']); ?></td>
                    <td>$<?php echo number_format($fetch_orders['total_price'], 2); ?></td>
                    <td>
                        <span class="status-pill <?php echo htmlspecialchars($fetch_orders['payment_status']); ?>">
                            <?php echo htmlspecialchars($fetch_orders['payment_status']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-pill <?php echo htmlspecialchars($fetch_orders['delivery_status']); ?>">
                            <?php echo htmlspecialchars($fetch_orders['delivery_status']); ?>
                        </span>
                    </td>
                    <td>
                        <button type="button" class="option-btn view-details-btn"
                                data-order-id="<?php echo $fetch_orders['id']; ?>"
                                data-name="<?php echo htmlspecialchars($fetch_orders['name']); ?>"
                                data-number="<?php echo htmlspecialchars($fetch_orders['number']); ?>"
                                data-email="<?php echo htmlspecialchars($fetch_orders['email']); ?>"
                                data-address="<?php echo htmlspecialchars($fetch_orders['address']); ?>"
                                data-products="<?php echo htmlspecialchars($product_list); ?>"
                                data-total="<?php echo number_format($fetch_orders['total_price'], 2); ?>"
                                data-method="<?php echo $fetch_orders['method']; ?>"
                                data-payment-status="<?php echo $fetch_orders['payment_status']; ?>"
                                data-delivery-status="<?php echo $fetch_orders['delivery_status']; ?>">
                            View
                        </button>
                    </td>
                </tr>
                <?php
                    }
                }else{
                    echo '<tr><td colspan="8"><p class="empty">no orders found!</p></td></tr>';
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

<div id="details-modal" class="modal">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <div class="box">
            <h3 style="font-size: 2.2rem; margin-bottom: 1.5rem; text-align: center;">Order Details #<span id="modal_order_id"></span></h3>
            
            <p> name : <span id="modal_name"></span> </p>
            <p> number : <span id="modal_number"></span> </p>
            <p> email : <span id="modal_email"></span> </p>
            <p> address : <span id="modal_address"></span> </p>
            <p> total products : <span id="modal_products"></span> </p>
            <p> total price : <span>$</span><span id="modal_total"></span><span>/-</span> </p>
            <p> payment method : <span id="modal_method"></span> </p>
            
            <form action="" method="post">
                <input type="hidden" name="order_id" id="modal_hidden_order_id" value="">
                
                <label for="update_payment" style="font-size: 1.6rem; color:var(--light-color);">Payment Status:</label>
                <select name="update_payment" id="modal_payment_status">
                    <option value="pending">pending</option>
                    <option value="completed">completed</option>
                </select>
                
                <label for="update_delivery" style="font-size: 1.6rem; color:var(--light-color); margin-top: 1rem; display: block;">Delivery Status:</label>
                <select name="update_delivery" id="modal_delivery_status">
                    <option value="pending">pending</option>
                    <option value="processing">processing</option>
                    <option value="shipped">shipped</option>
                    <option value="delivered">delivered</option>
                    <option value="cancelled">cancelled</option>
                </select>
                
                <div class="btn-group" style="margin-top: 2rem;">
                    <input type="submit" name="update_order" value="Update" class="option-btn">
                    <a href="#" id="modal_delete_link" class="delete-btn" onclick="return confirm('delete this order?');">Delete</a>
                </div>
            </form>
        </div>
    </div>
</div>


<script src="js/admin_script.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('details-modal');
    var closeBtn = modal.querySelector('.modal-close');

    document.querySelectorAll('.view-details-btn').forEach(button => {
        button.addEventListener('click', function() {
            var data = this.dataset;
            document.getElementById('modal_order_id').innerText = data.orderId;
            document.getElementById('modal_name').innerText = data.name;
            document.getElementById('modal_number').innerText = data.number;
            document.getElementById('modal_email').innerText = data.email;
            document.getElementById('modal_address').innerText = data.address;
            document.getElementById('modal_products').innerText = data.products;
            document.getElementById('modal_total').innerText = data.total;
            document.getElementById('modal_method').innerText = data.method;
            document.getElementById('modal_hidden_order_id').value = data.orderId;
            document.getElementById('modal_payment_status').value = data.paymentStatus;
            document.getElementById('modal_delivery_status').value = data.deliveryStatus;
            document.getElementById('modal_delete_link').href = 'staff_orders.php?delete=' + data.orderId;
            modal.style.display = 'block';
        });
    });

    closeBtn.onclick = function() {
        modal.style.display = 'none';
    }
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
});
</script>

</body>
</html>