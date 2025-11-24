<?php
@include 'config.php';
session_start();

//  1: Kiểm tra session staff 
$staff_id = $_SESSION['user_id'] ?? null;
$staff_type = $_SESSION['role'] ?? null; 
if(!isset($staff_id) || $staff_type != 'staff'){
   header('location:login.php');
   exit();
}

//  2: Xử lý xóa (dùng PDO)
if(isset($_GET['delete'])){
   $delete_id = $_GET['delete'];
   $stmt = $conn->prepare("DELETE FROM `message` WHERE id = ?");
   $stmt->execute([$delete_id]);
   header('location:staff_contact.php');
   exit();
}

// 3: Logic Lọc, Tìm kiếm, Phân trang
$where_clauses = [];
$params = [];
$filter_link = ''; 

// Lọc theo Search
$search_key = $_GET['search'] ?? '';
if(!empty($search_key)){
    // Tìm kiếm trong tên, email, hoặc nội dung tin nhắn
    $where_clauses[] = "(name LIKE ? OR email LIKE ? OR message LIKE ?)";
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

// Phân trang
$per_page = 9; // 9 tin nhắn mỗi trang (lưới 3x3)
$current_page = $_GET['page'] ?? 1;
$offset = ((int)$current_page - 1) * $per_page;

// Đếm tổng số tin nhắn
$count_sql = "SELECT COUNT(*) FROM `message`" . $sql_where;
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total_messages = $count_stmt->fetchColumn();
$total_pages = ceil($total_messages / $per_page);

// Câu truy vấn chính
$message_sql = "SELECT * FROM `message`" . $sql_where . " ORDER BY id DESC LIMIT ? OFFSET ?";
$select_message = $conn->prepare($message_sql);
$param_index = 1;
foreach ($params as $param) { $select_message->bindValue($param_index, $param); $param_index++; }
$select_message->bindValue($param_index, $per_page, PDO::PARAM_INT); $param_index++;
$select_message->bindValue($param_index, $offset, PDO::PARAM_INT);
$select_message->execute();

?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Messages</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/admin_style.css">
   
   <style>
      .search-form { display: flex; margin: 0 auto 2rem auto; max-width: 1200px; }
      .search-form .box { width: 100%; font-size: 1.6rem; padding: 1rem 1.2rem; border: var(--border); border-radius: .5rem 0 0 .5rem; }
      .search-form .btn { margin-top: 0; border-radius: 0 .5rem .5rem 0; }
      
      .pagination { text-align: center; padding: 2rem 0; }
      .pagination a { display: inline-block; padding: 1rem 1.5rem; margin: 0 .5rem; background: var(--white); color: var(--pink); border: var(--border); font-size: 1.6rem; border-radius: .5rem; }
      .pagination a:hover, .pagination a.active { background: var(--pink); color: var(--white); }
   </style>
</head>
<body>
   
<?php @include 'staff_header.php'; ?>

<section class="messages">

   <h1 class="title">messages</h1>

   <form action="" method="GET" class="search-form">
        <input type="text" name="search" class="box" placeholder="Search by name, email, or message..." value="<?php echo htmlspecialchars($search_key); ?>">
        <button type="submit" class="btn fas fa-search"></button>
   </form>

   <div class="box-container">

      <?php
       // (SỬA) 4: Dùng $select_message (PDO)
       if($select_message->rowCount() > 0){
          while($fetch_message = $select_message->fetch(PDO::FETCH_ASSOC)){
      ?>
      <div class="box">
         <p>user id : <span><?php echo $fetch_message['user_id'] ?? 'Guest'; ?></span> </p>
         <p>name : <span><?php echo htmlspecialchars($fetch_message['name']); ?></span> </p>
         <p>number : <span><?php echo htmlspecialchars($fetch_message['number']); ?></span> </p>
         <p>email : <span><?php echo htmlspecialchars($fetch_message['email']); ?></span> </p>
         <p>message : <span><?php echo htmlspecialchars($fetch_message['message']); ?></span> </p>
         <a href="staff_contact.php?delete=<?php echo $fetch_message['id']; ?>" onclick="return confirm('delete this message?');" class="delete-btn">delete</a>
      </div>
      <?php
         }
      }else{
         echo '<p class="empty">you have no messages!</p>';
      }
      ?>
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