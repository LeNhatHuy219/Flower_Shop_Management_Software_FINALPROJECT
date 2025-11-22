<?php
@include 'config.php';
session_start();

// (SỬA) 1: Kiểm tra session (dùng 'user_type')
$admin_id = $_SESSION['user_id'] ?? null;
$admin_type = $_SESSION['role'] ?? null; // Sửa 'role' thành 'user_type'
if(!isset($admin_id) || $admin_type != 'staff'){
   header('location:login.php');
   exit();
}

// 2: KHỐI XỬ LÝ AJAX (Đã bị xóa)

// 3. Lấy danh sách users (Logic Lọc, Tìm kiếm, Phân trang)
$where_clauses = []; $params = []; $filter_link = ''; 

$filter_type = $_GET['filter_type'] ?? '';
if(!empty($filter_type)){
    $where_clauses[] = "u.role = ?"; // (SỬA) 3.1: Sửa 'role' thành 'user_type'
    $params[] = $filter_type;
    $filter_link .= '&filter_type=' . $filter_type;
}
$search_key = $_GET['search'] ?? '';
if(!empty($search_key)){
    $where_clauses[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $search_param = "%{$search_key}%";
    $params[] = $search_param; $params[] = $search_param;
    $filter_link .= '&search=' . htmlspecialchars($search_key);
}
$sql_where = "";
if(!empty($where_clauses)){ $sql_where = " WHERE " . implode(" AND ", $where_clauses); }

$per_page = 10;
$current_page = $_GET['page'] ?? 1;
$offset = ((int)$current_page - 1) * $per_page;

// (SỬA) 4: Câu truy vấn chính (THÊM ĐIỂM THƯỞNG 'total_points')
$users_sql = "
    SELECT 
        u.*, 
        e.position, e.salary, e.hire_date,
        COUNT(DISTINCT o.id) AS total_orders, 
        SUM(CASE WHEN o.payment_status = 'completed' THEN o.total_price ELSE 0 END) AS total_spent,
        (SELECT SUM(rl.points) FROM `reward_points_log` rl WHERE rl.user_id = u.id) AS total_points
    FROM 
        `users` u
    LEFT JOIN 
        `orders` o ON u.id = o.user_id
    LEFT JOIN
        `employees` e ON u.id = e.user_id
    " . $sql_where . "
    GROUP BY 
        u.id
    ORDER BY 
        u.name ASC 
    LIMIT ?, ?
";

$count_sql = "SELECT COUNT(*) FROM `users` u" . $sql_where;
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total_users_count = $count_stmt->fetchColumn();
$total_pages = ceil($total_users_count / $per_page);

$select_users = $conn->prepare($users_sql);
$param_index = 1;
foreach ($params as $param) { $select_users->bindValue($param_index, $param); $param_index++; }
$select_users->bindValue($param_index, $offset, PDO::PARAM_INT); $param_index++;
$select_users->bindValue($param_index, $per_page, PDO::PARAM_INT);
$select_users->execute();
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <title>Manage Users</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/admin_style.css">
   
   <style>
      .users .box-container { display: none; }
      .users-table-container { overflow-x: auto; }
      .users-table { width: 100%; min-width: 120rem; background: var(--white); border-collapse: collapse; box-shadow: var(--box_shadow); border-radius: .5rem; overflow: hidden; }
      .users-table th,
      .users-table td { padding: 1.2rem 1.5rem; font-size: 1.6rem; text-align: left; border-bottom: 1px solid var(--light-bg); }
      .users-table th { background-color: var(--light-bg); color: var(--black); }
      .users-table td { color: var(--light-color); }
      .users-table td .avatar { height: 5rem; width: 5rem; object-fit: cover; border-radius: 50%; }
      .users-table .actions { display: flex; gap: .5rem; flex-wrap: wrap; }
      .users-table .actions .message-btn { background-color: #3498db; padding: .8rem 1rem; font-size: 1.4rem; }
      
      .status-pill { display: inline-block; padding: .3rem 1rem; font-size: 1.3rem; border-radius: 1rem; color: var(--white); text-transform: capitalize; }
      .status-pill.user { background: var(--light-color); }
      .status-pill.admin { background: var(--pink); }
      .status-pill.staff { background: var(--orange); }

      .controls-container { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
      .filter-tabs { display: flex; gap: 1rem; border-bottom: 2px solid var(--light-bg); }
      .filter-tabs a { padding: 1rem 1.5rem; font-size: 1.6rem; color: var(--light-color); border-bottom: 2px solid transparent; }
      .filter-tabs a.active, .filter-tabs a:hover { color: var(--pink); border-bottom-color: var(--pink); }
      .search-form { display: flex; margin-bottom: 2rem; }
      .search-form .box { width: 100%; font-size: 1.6rem; padding: 1rem 1.2rem; border: var(--border); border-radius: .5rem 0 0 .5rem; }
      .search-form .btn { margin-top: 0; border-radius: 0 .5rem .5rem 0; }
      
      .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); justify-content: center; align-items: center; }
      .modal-content { background: #fff; border-radius: 10px; padding: 20px; width: 90%; max-width: 50rem; box-shadow: 0 0 20px rgba(0,0,0,0.3); text-align: center; animation: fadeIn .3s ease; }
      @keyframes fadeIn { from {opacity: 0; transform: translateY(-20px);} to {opacity: 1; transform: translateY(0);} }
      .modal .box { width: 100%; padding: 1rem 1.2rem; margin: 1rem 0; font-size: 1.6rem; border: var(--border); border-radius: .5rem; }
      .modal .btn-group { display: flex; gap: 1rem; justify-content: center; }
      .modal h3 { font-size: 2.2rem; color: var(--black); margin-bottom: 1.5rem; }
      .close { float:right; font-size:2.5rem; cursor:pointer; color: var(--light-color); }
      .pagination { text-align: center; padding: 2rem 0; }
      .pagination a { display: inline-block; padding: 1rem 1.5rem; margin: 0 .5rem; background: var(--white); color: var(--pink); border: var(--border); font-size: 1.6rem; border-radius: .5rem; }
      .pagination a:hover, .pagination a.active { background: var(--pink); color: var(--white); }
      
      .staff-fields { display: none; }
      .staff-fields.active { display: block; }
      .modal .flex { display: flex; gap: 1rem; }
      .modal .flex .inputBox { width: 50%; }

      .modal-content .message-list-container { max-height: 40vh; overflow-y: auto; background: var(--light-bg); border: var(--border); border-radius: .5rem; padding: 1.5rem; text-align: left; margin-top: 1rem; }
      .modal-content .message-list-container .message-item { font-size: 1.5rem; color: var(--light-color); padding-bottom: 1rem; border-bottom: 1px solid var(--light-white); margin-bottom: 1rem; }
      .modal-content .message-list-container .message-item:last-child { border-bottom: none; margin-bottom: 0; }
      .modal-content .message-list-container .empty { text-align: center; font-size: 1.6rem; }
   </style>
</head>
<body>

<?php @include 'staff_header.php'; ?> 
<section class="placed-orders"> 
   
   <div class="controls-container">
      <h1 class="title">User Accounts</h1>
      </div>

   <div class="filter-tabs">
        <a href="staff_users.php" class="<?php echo empty($filter_type) ? 'active' : ''; ?>">All</a>
        <a href="?filter_type=user" class="<?php echo ($filter_type == 'user') ? 'active' : ''; ?>">Users</a>
        <a href="?filter_type=staff" class="<?php echo ($filter_type == 'staff') ? 'active' : ''; ?>">Staff</a>
        <a href="?filter_type=admin" class="<?php echo ($filter_type == 'admin') ? 'active' : ''; ?>">Admins</a>
   </div>

   <form action="" method="GET" class="search-form">
       <input type="text" name="search" class="box" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search_key); ?>">
       <button type="submit" class="btn fas fa-search"></button>
   </form>
   
   <div class="users-table-container">
      <table class="users-table">
         <thead>
            <tr>
               <th>Avatar</th>
               <th>User ID</th>
               <th>Name / Email / Phone</th>
               <th>User Type</th> 
               <th>Position / Salary</th>
               <th>Total Orders</th> 
               <th>Total Spent</th> 
               <th>Point</th> </tr>
         </thead>
         <tbody>
         <?php
            if($select_users->rowCount() > 0){
               while($fetch_users = $select_users->fetch(PDO::FETCH_ASSOC)){
         ?>
            <tr>
               <td><img src="avatars/<?php echo htmlspecialchars($fetch_users['image']); ?>" alt="avatar" class="avatar"></td>
               <td><?php echo $fetch_users['id']; ?></td>
               <td>
                  <?php echo htmlspecialchars($fetch_users['name']); ?>
                  <br><span style="font-size: 1.4rem;"><?php echo htmlspecialchars($fetch_users['email']); ?></span>
                  <br><span style="font-size: 1.4rem;"><?php echo htmlspecialchars($fetch_users['phone']); ?></span>
               </td>
               <td>
                  <span class="status-pill <?php echo htmlspecialchars($fetch_users['role']); ?>">
                     <?php echo htmlspecialchars($fetch_users['role']); ?>
                  </span>
               </td>
               <td>
                  <?php if($fetch_users['role'] == 'staff'): ?>
                     <?php echo htmlspecialchars($fetch_users['position']); ?>
                     <br><span style="font-size: 1.4rem;">$<?php echo number_format($fetch_users['salary'], 2); ?></span>
                  <?php else: echo '-'; endif; ?>
               </td>
               
               <td>
                  <?php if($fetch_users['role'] == 'user'){ echo $fetch_users['total_orders']; } else { echo '-'; } ?>
               </td>
               <td>
                  <?php if($fetch_users['role'] == 'user'){ echo '$' . number_format($fetch_users['total_spent'], 2); } else { echo '-'; } ?>
               </td>
               
               <td>
                  <?php if($fetch_users['role'] == 'user'){ echo $fetch_users['total_points'] ?? 0; } else { echo '-'; } ?>
               </td>
               
            </tr>
         <?php
            }
         } else {
            echo '<tr><td colspan="8"><p class="empty">no users found!</p></td></tr>';
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

<script src="js/admin_script.js"></script>
</body>
</html>