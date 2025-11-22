<?php
@include 'config.php'; 
session_start();

$staff_id = $_SESSION['user_id'] ?? null;
$staff_type = $_SESSION['role'] ?? null; 

if(!isset($staff_id) || ($staff_type !='staff')){
   header('location:login.php');
   exit();
}

// ================================================================
// LOGIC LẤY DỮ LIỆU THỐNG KÊ (Đầy đủ)
// ================================================================
try {
    // 1. Dữ liệu cho Thẻ KPI (Giữ nguyên)
    $total_sales_raw = $conn->query("SELECT SUM(total_price) FROM `orders` WHERE payment_status = 'completed'")->fetchColumn();
    $total_sales = $total_sales_raw ?? 0;
    $total_orders = $conn->query("SELECT COUNT(*) FROM `orders`")->fetchColumn();
    $total_users = $conn->query("SELECT COUNT(*) FROM `users` WHERE role = 'user'")->fetchColumn();
    $total_products = $conn->query("SELECT COUNT(*) FROM `products`")->fetchColumn();

    // 2. Dữ liệu cho Biểu đồ 1 (Tổng SẢN PHẨM bán ra) (Giữ nguyên)
    $totalProductsSoldData = ['labels' => [], 'values' => []];
    $stmt_products_monthly = $conn->prepare("
        SELECT DATE_FORMAT(o.placed_on, '%Y-%m') as month, SUM(od.quantity) as total_products
        FROM `order_details` od
        JOIN `orders` o ON od.order_id = o.id
        WHERE o.payment_status = 'completed' AND o.placed_on >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");
    $stmt_products_monthly->execute();
    while($row = $stmt_products_monthly->fetch(PDO::FETCH_ASSOC)){
        $totalProductsSoldData['labels'][] = $row['month'];
        $totalProductsSoldData['values'][] = $row['total_products'];
    }

    // 3. Dữ liệu cho Biểu đồ 2 (Tổng ĐƠN HÀNG) (Giữ nguyên)
    $totalOrdersData = ['labels' => [], 'values' => []];
    $stmt_orders_monthly = $conn->prepare("
        SELECT DATE_FORMAT(placed_on, '%Y-%m') as month, COUNT(*) as total_orders
        FROM `orders` 
        WHERE placed_on >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");
    $stmt_orders_monthly->execute();
    while($row = $stmt_orders_monthly->fetch(PDO::FETCH_ASSOC)){
        $totalOrdersData['labels'][] = $row['month'];
        $totalOrdersData['values'][] = $row['total_orders'];
    }

    // (SỬA) 4. Dữ liệu cho Biểu đồ 3 (Top 5 Sản phẩm)
    $topProductsData = ['labels' => [], 'values' => []];
    $top_selling_products = $conn->query("
        SELECT p.name, SUM(od.quantity) as total_quantity_sold
        FROM `order_details` od
        JOIN `products` p ON od.product_id = p.id
        GROUP BY p.name
        ORDER BY total_quantity_sold DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($top_selling_products as $product) {
        $topProductsData['labels'][] = $product['name'];
        $topProductsData['values'][] = $product['total_quantity_sold'];
    }

    // 5. Dữ liệu Top 5 Khách hàng (Giữ nguyên)
    $top_customers = $conn->query("
        SELECT u.name, SUM(o.total_price) as total_spent
        FROM `orders` o
        JOIN `users` u ON o.user_id = u.id
        WHERE o.payment_status = 'completed'
        GROUP BY u.name
        ORDER BY total_spent DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
    // Đặt giá trị mặc định nếu lỗi
    $total_sales = 0; $total_orders = 0; $total_users = 0; $total_products = 0;
    $totalProductsSoldData = []; $totalOrdersData = []; $topProductsData = [];
    $top_customers = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Dashboard</title>
   
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/admin_style.css"> 
   <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

   <style>
        /* CSS KPI Cards (Giữ nguyên) */
        .dashboard .overview-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(24rem, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        .dashboard .overview-cards .card {
            background-color: var(--white);
            padding: 2.5rem;
            border-radius: .5rem;
            box-shadow: var(--box-shadow);
            border: var(--border);
        }
        .dashboard .overview-cards .card h3 {
            font-size: 3.5rem;
            color: var(--main-color);
            margin-bottom: 0.5rem;
        }
        .dashboard .overview-cards .card p {
            font-size: 1.8rem;
            color: var(--light-color);
            text-transform: capitalize;
        }
        
        /* (SỬA) 1: CSS Layout cho Biểu đồ (linh hoạt hơn) */
        .dashboard .chart-container {
            display: grid;
            /* Tự động chia cột, tối thiểu 40rem */
            grid-template-columns: repeat(auto-fit, minmax(40rem, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        .dashboard .chart-box {
            background-color: var(--white);
            padding: 2rem;
            border-radius: .5rem;
            box-shadow: var(--box-shadow);
            border: var(--border);
            height: 40rem; 
        }
        .dashboard .chart-box h3 {
            font-size: 2.2rem;
            color: var(--black);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        /* (SỬA) 2: CSS Layout cho Danh sách (chỉ còn Top Customers) */
        .dashboard .quick-stats {
            display: grid;
            grid-template-columns: 1fr; /* 1 cột */
            gap: 2rem;
            margin-top: 3rem;
        }
        .dashboard .stats-box {
            background-color: var(--white);
            padding: 2rem;
            border-radius: .5rem;
            box-shadow: var(--box-shadow);
            border: var(--border);
        }
        .dashboard .stats-box h3 {
            font-size: 2rem;
            color: var(--black);
            margin-bottom: 1.5rem;
            text-align: center;
            border-bottom: var(--border);
            padding-bottom: 1rem;
        }
        .dashboard .stats-box ul { list-style: none; padding: 0; margin: 0; }
        .dashboard .stats-box ul li { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px solid var(--light-bg); font-size: 1.6rem; color: var(--light-color); }
        .dashboard .stats-box ul li:last-child { border-bottom: none; }
        .dashboard .stats-box ul li span { color: var(--main-color); font-weight: bold; }
        
        /* Responsive (Giữ nguyên) */
        @media (max-width:991px){
            .dashboard .chart-container,
            .dashboard .quick-stats {
                grid-template-columns: 1fr;
            }
        }
   </style>
</head>
<body>
   
<?php @include 'staff_header.php'; ?> 


<section class="dashboard">

    <h1 class="title">dashboard overview</h1>
    
    <?php if(isset($error_message)): ?>
        <div class="message error"><span><?php echo $error_message; ?></span></div>
    <?php endif; ?>

    <div class="overview-cards">
        <div class="card">
            <h3>$<?php echo number_format($total_sales, 2); ?></h3>
            <p>Net Sales</p>
        </div>
        <div class="card">
            <h3><?php echo $total_orders; ?></h3>
            <p>Total Orders</p>
        </div>
        <div class="card">
            <h3><?php echo $total_users; ?></h3>
            <p>Active Customers</p>
        </div>
        <div class="card">
            <h3><?php echo $total_products; ?></h3>
            <p>Total Products</p>
        </div>
    </div>

    <div class="chart-container">
        <div class="chart-box">
            <h3>Top 5 Selling Products</h3>
            <canvas id="topProductsChart"></canvas>
        </div>
    </div>
    
    <div class="quick-stats">
        <div class="stats-box">
            <h3>Top 5 Customers by Spending</h3>
            <ul>
                <?php if(!empty($top_customers)): ?>
                    <?php foreach($top_customers as $customer): ?>
                        <li><?php echo htmlspecialchars($customer['name']); ?> <span>$<?php echo number_format($customer['total_spent'], 2); ?></span></li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No customer spending data.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

</section>

        
<script src="js/admin_script.js"></script> 

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dữ liệu từ PHP
    const productsSoldLabels = <?php echo json_encode($totalProductsSoldData['labels'] ?? []); ?>;
    const productsSoldValues = <?php echo json_encode($totalProductsSoldData['values'] ?? []); ?>;

    const totalOrdersLabels = <?php echo json_encode($totalOrdersData['labels'] ?? []); ?>;
    const totalOrdersValues = <?php echo json_encode($totalOrdersData['values'] ?? []); ?>;

    // (MỚI) Dữ liệu Top 5 Products
    const topProductsLabels = <?php echo json_encode($topProductsData['labels'] ?? []); ?>;
    const topProductsValues = <?php echo json_encode($topProductsData['values'] ?? []); ?>;

    // Biểu đồ 1: SẢN PHẨM BÁN RA (Line Chart)
    if (document.getElementById('productsSoldChart')) {
        new Chart(document.getElementById('productsSoldChart'), {
            type: 'line', 
            data: {
                labels: productsSoldLabels,
                datasets: [{
                    label: 'Products Sold',
                    data: productsSoldValues,
                    backgroundColor: 'rgba(232, 67, 147, 0.2)', 
                    borderColor: 'rgba(232, 67, 147, 1)', 
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { 
                    y: { 
                        beginAtZero: true,
                        title: { display: true, text: 'Units Sold' }
                    } 
                }
            }
        });
    }

    // Biểu đồ 2: TỔNG ĐƠN HÀNG (Line Chart)
    if (document.getElementById('totalOrdersChart')) {
        new Chart(document.getElementById('totalOrdersChart'), {
            type: 'line',
            data: {
                labels: totalOrdersLabels,
                datasets: [{
                    label: 'Total Orders',
                    data: totalOrdersValues,
                    backgroundColor: 'rgba(136, 84, 208, 0.2)', 
                    borderColor: 'rgba(136, 84, 208, 1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { 
                    y: { 
                        beginAtZero: true,
                        title: { display: true, text: 'Number of Orders' }
                    } 
                }
            }
        });
    }

    // (MỚI) Biểu đồ 3: TOP 5 SẢN PHẨM (Bar Chart)
    if (document.getElementById('topProductsChart')) {
        new Chart(document.getElementById('topProductsChart'), {
            type: 'bar', // (SỬA) Đổi thành 'bar'
            data: {
                labels: topProductsLabels,
                datasets: [{
                    label: 'Total Sold',
                    data: topProductsValues,
                    backgroundColor: 'rgba(243, 156, 18, 0.6)', // Màu cam
                    borderColor: 'rgba(243, 156, 18, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { 
                    y: { 
                        beginAtZero: true,
                        title: { display: true, text: 'Units Sold' }
                    } 
                }
            }
        });
    }
});
</script>

</body>
</html>