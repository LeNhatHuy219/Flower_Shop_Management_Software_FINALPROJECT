<?php
@include 'config.php';
session_start();
$user_id = $_SESSION['user_id'];
if(!isset($user_id)){ header('location:login.php'); }

// (LOGIC PHP PHÂN TRANG VÀ LỌC GIỮ NGUYÊN)
// 1. Cài đặt phân trang
$per_page = 6; 
$current_page = 1; 
if (isset($_POST['filter_dates'])) {
    $current_page = 1;
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
} else {
    if(isset($_GET['page'])){
        $current_page = (int)$_GET['page'];
    }
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
}
$offset = ($current_page - 1) * $per_page;

// 2. Xây dựng câu truy vấn
$sql_where = "WHERE user_id = ?";
$params = [$user_id]; 
$date_query_string = ""; 
if (!empty($start_date) && !empty($end_date)) {
    $sql_where .= " AND placed_on BETWEEN ? AND ?";
    $params[] = $start_date . ' 00:00:00';
    $params[] = $end_date . ' 23:59:59';
    // (SỬA) Đây là biến quan trọng để giữ filter
    $date_query_string = "&start_date=" . htmlspecialchars($start_date) . "&end_date=" . htmlspecialchars($end_date);
}

// 3. Đếm tổng số đơn hàng
$count_sql = "SELECT COUNT(*) FROM `orders` " . $sql_where;
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total_orders = $count_stmt->fetchColumn();
$total_pages = ceil($total_orders / $per_page);

// 4. Lấy đơn hàng cho trang hiện tại
$sql = "SELECT * FROM `orders` " . $sql_where . " ORDER BY placed_on DESC LIMIT ? OFFSET ?";
$select_orders = $conn->prepare($sql);
$param_index = 1;
foreach ($params as $param) {
    $select_orders->bindValue($param_index, $param);
    $param_index++;
}
$select_orders->bindValue($param_index, $per_page, PDO::PARAM_INT);
$param_index++;
$select_orders->bindValue($param_index, $offset, PDO::PARAM_INT);
$select_orders->execute(); 

?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>orders</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/style.css">
   <style>
    /* CSS Form Lọc (Giữ nguyên) */
    .date-filter-form { background: var(--bg-color); padding: 2rem; border: var(--border); border-radius: .5rem; margin-bottom: 2rem; display: flex; align-items: center; justify-content: center; gap: 1.5rem; flex-wrap: wrap; }
    .date-filter-form h3 { font-size: 2rem; color: var(--black); width: 100%; text-align: center; }
    .date-filter-form .input-group { display: flex; align-items: center; gap: .5rem; }
    .date-filter-form label { font-size: 1.6rem; color: var(--light-color); }
    .date-filter-form .box { font-size: 1.6rem; padding: .8rem 1rem; }

    /* CSS Modal (Giữ nguyên) */
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); padding-top: 60px; }
    .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 700px; border-radius: .5rem; position: relative; }
    .modal-close { color: #aaa; position: absolute; top: 10px; right: 25px; font-size: 35px; font-weight: bold; }
    .modal-close:hover, .modal-close:focus { color: black; text-decoration: none; cursor: pointer; }
    .modal-body { max-height: 70vh; overflow-y: auto; padding-top: 2rem; }
    .modal-body .info-box { background: var(--bg-color); padding: 1.5rem; border-radius: .5rem; margin-bottom: 2rem; }
    .modal-body .info-box p { font-size: 1.8rem; color: var(--light-color); line-height: 1.8; }
    .modal-body .info-box p span { color: var(--black); }
    .modal-body .product-box { border: var(--border); border-radius: .5rem; padding: 2rem; text-align: center; background: var(--white); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 1.5rem; }
    .modal-body .product-box img { height: 10rem; }
    .modal-body .product-box .details { text-align: left; }
    .modal-body .product-box .name { font-size: 1.8rem; color: var(--black); }
    .modal-body .product-box .price { font-size: 1.6rem; color: var(--light-color); }
    .modal-body .loading-text { font-size: 2rem; text-align: center; padding: 3rem; color: var(--light-color); }
    .modal-body .totals-box { text-align: left; margin-top: 2rem; padding-top: 2rem; border-top: var(--border); }
    .modal-body .totals-box p { font-size: 1.8rem; color: var(--light-color); margin: 1rem 0; }
    .modal-body .totals-box .grand-total { font-size: 2.2rem; color: var(--main-color); font-weight: bold; text-align: right; }
    .placed-orders .empty { font-size: 1.6rem; padding: 1rem; margin: 1rem auto; width: fit-content; background-color: var(--white); }

    /* =========================================== */
    /* (SỬA) THÊM LẠI CSS PHÂN TRANG */
    /* =========================================== */
    .pagination {
        text-align: center;
        padding: 2rem 0;
    }
    .pagination a, .pagination a:visited {
        display: inline-block;
        padding: 1rem 1.5rem;
        margin: 0 .5rem;
        background: var(--white);
        color: var(--pink); /* Chữ màu hồng */
        border: var(--border);
        font-size: 1.6rem;
        border-radius: .5rem;
        text-decoration: none;
    }
    .pagination a:hover {
        background: var(--pink);
        color: var(--white);
    }
    .pagination a.active, .pagination a.active:visited { 
        background: var(--pink); /* Nền màu hồng */
        color: var(--white); /* Chữ màu trắng */
        cursor: default;
        pointer-events: none;
    }
    .pagination a.disabled {
        background: var(--bg-color);
        color: var(--light-color);
        pointer-events: none;
    }
   </style>
</head>
<body>
   
<?php @include 'header.php'; ?>

<section class="heading">
    <h3>your orders</h3>
    <p> <a href="home.php">home</a> / order </p>
</section>

<section class="placed-orders">

    <h1 class="title">placed orders</h1>

    <form action="orders.php" method="post" class="date-filter-form">
       <h3>Filter by Date</h3>
       <div class="input-group"> <label>From:</label> <input type="date" name="start_date" class="box" value="<?php echo htmlspecialchars($start_date); ?>"> </div>
       <div class="input-group"> <label>To:</label> <input type="date" name="end_date" class="box" value="<?php echo htmlspecialchars($end_date); ?>"> </div>
       <input type="submit" name="filter_dates" value="Filter" class="btn">
    </form>
 
    <div class="box-container">
    <?php
        if($select_orders->rowCount() > 0){
            while($fetch_orders = $select_orders->fetch(PDO::FETCH_ASSOC)){
    ?>
    <div class="box">
        <p> placed on : <span><?php echo date('Y-m-d', strtotime($fetch_orders['placed_on'])); ?></span> </p>
        <p> your orders : <span>
        <?php
            $order_id = $fetch_orders['id'];
            $select_order_details = $conn->prepare(
                "SELECT p.name, od.quantity 
                 FROM `order_details` od
                 JOIN `products` p ON od.product_id = p.id
                 WHERE od.order_id = ?"
            );
            $select_order_details->execute([$order_id]);
            $product_list = '';
            while($fetch_details = $select_order_details->fetch(PDO::FETCH_ASSOC)){
                $product_list .= $fetch_details['name'] . ' (x' . $fetch_details['quantity'] . '), ';
            }
            echo rtrim($product_list, ', '); 
        ?>
        </span></p>
        <p> total price : <span>$<?php echo $fetch_orders['total_price']; ?>/-</span> </p>
        <p> payment status : <span style="color:<?php if($fetch_orders['payment_status'] == 'pending'){echo 'tomato'; }else{echo 'green';} ?>"><?php echo $fetch_orders['payment_status']; ?></span> </p>
        <p> delivery status : <span style="color:<?php if($fetch_orders['delivery_status'] == 'pending' || $fetch_orders['delivery_status'] == 'processing'){echo 'tomato'; } elseif($fetch_orders['delivery_status'] == 'shipped'){echo 'orange';} else{echo 'green';} ?>"><?php echo $fetch_orders['delivery_status']; ?></span> </p>
        <button type="button" class="option-btn view-details-btn" data-order-id="<?php echo $fetch_orders['id']; ?>" style="margin-top: 1rem;">View Details</button>
    </div>
    <?php
            } 
        }
    ?>
    </div>

    <?php
        if($select_orders->rowCount() == 0){
            echo '<p class="empty">no orders placed yet!</p>';
        }
    ?>

    <div class="pagination">
        <?php if($total_pages > 1): ?>
            
            <?php if($current_page > 1): ?>
                <a href="?page=<?php echo $current_page - 1; ?><?php echo $date_query_string; ?>">&laquo; Prev</a>
            <?php endif; ?>

            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?><?php echo $date_query_string; ?>" 
                   class="<?php echo ($i == $current_page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if($current_page < $total_pages): ?>
                <a href="?page=<?php echo $current_page + 1; ?><?php echo $date_query_string; ?>">Next &raquo;</a>
            <?php endif; ?>
               
        <?php endif; ?>
    </div>

</section>

<div id="order-details-modal" class="modal">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <div class="modal-body">
            <p class="loading-text">Loading...</p>
        </div>
    </div>
</div>
<?php @include 'footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('order-details-modal');
    var modalBody = modal.querySelector('.modal-body');
    var closeBtn = modal.querySelector('.modal-close');
    document.querySelectorAll('.view-details-btn').forEach(button => {
        button.addEventListener('click', function() {
            var orderId = this.dataset.orderId;
            modal.style.display = 'block';
            modalBody.innerHTML = '<p class="loading-text">Loading...</p>';
            
            // (SỬA) Tên file đúng là 'get_order_details.php'
            fetch('order_detail.php?id=' + orderId)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        modalBody.innerHTML = '<p class="empty">' + data.error + '</p>';
                    } else {
                        var order = data.order;
                        var details = data.details;
                        var html = '<h1 class="title">Details for Order #' + order.id + '</h1>';
                        html += '<div class="info-box">';
                        var placedOnDate = order.placed_on.split(' ')[0];
                        html += '<p> placed on : <span>' + placedOnDate + '</span> </p>';
                        html += '<p> name : <span>' + order.name + '</span> </p>';
                        html += '<p> number : <span>' + order.number + '</span> </p>';
                        html += '<p> email : <span>' + order.email + '</span> </p>';
                        html += '<p> address : <span>' + order.address + '</span> </p>';
                        html += '<p> payment method : <span>' + order.method + '</span> </p>';
                        html += '<p> payment status : <span style="color:' + (order.payment_status == 'pending' ? 'tomato' : 'green') + '">' + order.payment_status + '</span> </p>';
                        html += '<p> delivery status : <span style="color:' + (order.delivery_status.includes('pending') || order.delivery_status.includes('processing') ? 'tomato' : (order.delivery_status.includes('shipped') ? 'orange' : 'green')) + '">' + order.delivery_status + '</span> </p>';
                        html += '</div>';
                        html += '<h2 class="title" style="margin-top: 2rem;">Products Purchased</h2>';
                        details.forEach(item => {
                            html += '<div class="product-box">';
                            html += '<img src="flowers/' + item.image + '" alt="">';
                            html += '<div class="details">';
                            html += '  <div class="name">' + item.name + '</div>';
                            html += '  <div class="price">$' + parseFloat(item.price_at_purchase).toFixed(2) + ' (x ' + item.quantity + ')</div>';
                            html += '</div>';
                            html += '</div>';
                        });
                        html += '<div class="totals-box">';
                        if (order.discount_amount > 0) {
                            html += '<p> discount : <span>-$' + parseFloat(order.discount_amount).toFixed(2) + '</span> </p>';
                        }
                        if (order.points_spent > 0) {
                            html += '<p> points used : <span>' + order.points_spent + '</span> </p>';
                        }
                        // (SỬA) Sửa lỗi HTML bị hỏng
                        html += '<p class="grand-total"> total price : <span>$' + order.total_price;
                        html += '</div>';
                        modalBody.innerHTML = html;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalBody.innerHTML = '<p class="empty">Could not load order details.</p>';
                });
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
<script src="js/script.js"></script>
</body>
</html>