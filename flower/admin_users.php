<?php
@include 'config.php';
session_start();

// 1. Kiểm tra session admin/staff
$admin_id = $_SESSION['user_id'] ?? null;
$admin_type = $_SESSION['role'] ?? null; 
if(!isset($admin_id) || ($admin_type != 'admin' && $admin_type != 'staff')){
   header('location:login.php');
   exit();
}

// ================================================================
// 2: KHỐI XỬ LÝ AJAX (Thêm 'add_user')
// ================================================================
if (isset($_POST['action'])) {
    
    $response = ['success' => false, 'message' => 'Invalid action.'];
    if (!$admin_id) { 
        $response['message'] = 'User not logged in.';
        header('Content-Type: application/json'); echo json_encode($response); exit;
    }

    try {
        switch ($_POST['action']) {
            
            // === CASE 1: THÊM USER/STAFF (MỚI) ===
            case 'add_user':
                if($admin_type != 'admin') { // Chỉ admin được thêm
                    $response['message'] = 'Only admins can add new users.';
                    break;
                }
                
                $name = $_POST['name'];
                $email = $_POST['email'];
                $phone = $_POST['phone'];
                $pass = $_POST['password'];
                $type = $_POST['role']; // 'user' hoặc 'staff'
                
                // Kiểm tra email
                $stmt_check = $conn->prepare("SELECT id FROM `users` WHERE email = ?");
                $stmt_check->execute([$email]);
                if($stmt_check->rowCount() > 0) {
                    $response['message'] = 'Email already exists.';
                    break;
                }
                
                // Băm mật khẩu
                $hashed_pass = password_hash($pass, PASSWORD_BCRYPT);
                // Lấy avatar ngẫu nhiên
                $image_dir = 'avatars/'; 
                $images = glob($image_dir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
                $random_image_name = 'default-avatar.png'; 
                if (!empty($images)) {
                    $random_image_name = basename($images[array_rand($images)]);
                }

                // Thêm vào bảng users
                $stmt_add = $conn->prepare("INSERT INTO `users` (name, email, phone, password, role, image) VALUES (?,?,?,?,?,?)");
                $stmt_add->execute([$name, $email, $phone, $hashed_pass, $type, $random_image_name]);
                $new_user_id = $conn->lastInsertId();
                
                // (MỚI) Nếu là staff, thêm vào bảng employees
                if($type == 'staff' && $new_user_id) {
                    $position = $_POST['position'];
                    $salary = $_POST['salary'];
                    $hire_date = $_POST['hire_date'];
                    
                    $stmt_emp = $conn->prepare("INSERT INTO `employees` (user_id, position, hire_date, salary) VALUES (?,?,?,?)");
                    $stmt_emp->execute([$new_user_id, $position, $hire_date, $salary]);
                }

                if($type == 'admin' && $new_user_id) {
                    
                    $stmt_add = $conn->prepare("INSERT INTO `user` (name, email, phone, password, role, image) VALUES (?,?,?,?,?,?)");
                    $stmt_add->execute([$name, $email, $phone, $hashed_pass, $type, $random_image_name]);
                }
                
                $response['success'] = true;
                $response['message'] = 'User added successfully!';
                break;

            // === CASE 2: CẬP NHẬT USER/STAFF ===
            case 'update_user':
                if($admin_type != 'admin') {
                    $response['message'] = 'Only admins can edit users.';
                    break;
                }
                $update_id = $_POST['update_id'];
                $update_name = $_POST['update_name'];
                $update_email = $_POST['update_email'];
                $update_phone = $_POST['update_phone']; 
                $update_type = $_POST['update_type'];
                
                $stmt = $conn->prepare("UPDATE `users` SET name = ?, email = ?, phone = ?, role = ? WHERE id = ?");
                $stmt->execute([$update_name, $update_email, $update_phone, $update_type, $update_id]);

                // (MỚI) Cập nhật hoặc Thêm thông tin nhân viên
                if($update_type == 'staff') {
                    $position = $_POST['position'];
                    $salary = $_POST['salary'];
                    
                    // Kiểm tra xem đã có trong bảng employees chưa
                    $stmt_check_emp = $conn->prepare("SELECT id FROM `employees` WHERE user_id = ?");
                    $stmt_check_emp->execute([$update_id]);
                    if($stmt_check_emp->rowCount() > 0) {
                        // Cập nhật
                        $stmt_up_emp = $conn->prepare("UPDATE `employees` SET position = ?, salary = ? WHERE user_id = ?");
                        $stmt_up_emp->execute([$position, $salary, $update_id]);
                    } else {
                        // Thêm mới (nếu chuyển từ user -> staff)
                        $stmt_add_emp = $conn->prepare("INSERT INTO `employees` (user_id, position, salary, hire_date) VALUES (?,?,?,NOW())");
                        $stmt_add_emp->execute([$update_id, $position, $salary]);
                    }
                }
                $new_pass = $_POST['new_password'];
                 if(!empty($new_pass)){
                   $hashed_pass = password_hash($new_pass, PASSWORD_BCRYPT);
                    $stmt_pass = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                     $stmt_pass->execute([$hashed_pass, $update_id]);
                  }
                $response['success'] = true;
                $response['message'] = 'User updated successfully.';
                break;
                
            // === CASE 3: XÓA USER ===
            case 'delete_user':
                if($admin_type != 'admin') {
                    $response['message'] = 'Only admins can delete users.';
                    break;
                }
                $delete_id = $_POST['delete_id'];
                if($delete_id == $admin_id){ 
                    $response['message'] = 'You cannot delete yourself!';
                } else {
                    $stmt = $conn->prepare("DELETE FROM `users` WHERE id = ?");
                    $stmt->execute([$delete_id]);
                    $response['success'] = true;
                    $response['message'] = 'User deleted successfully.';
                }
                break;

            
        }
    } catch (Exception $e) {
        $response['message'] = 'Database error: '. $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// 3. Lấy danh sách users (Logic Lọc, Tìm kiếm, Phân trang)
$where_clauses = []; $params = []; $filter_link = ''; 

$filter_type = $_GET['filter_type'] ?? '';
if(!empty($filter_type)){
    $where_clauses[] = "u.role = ?"; 
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

// (SỬA) 4: Câu truy vấn chính (JOIN với employees)
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
      
      /* (MỚI) Ẩn/Hiện các trường Staff */
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

<?php @include 'admin_header.php'; ?>

<section class="placed-orders"> 
   
   <div class="controls-container">
      <h1 class="title">User Accounts</h1>
      <?php if($admin_type == 'admin'): ?>
         <button type="button" class="btn" onclick="openAddModal()">Add New User</button>
      <?php endif; ?>
   </div>

   <div class="filter-tabs">
        <a href="admin_users.php" class="<?php echo empty($filter_type) ? 'active' : ''; ?>">All</a>
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
               <th>Points</th>
               <th>Actions</th>
            </tr>
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
               <td class="actions">
                     <button class="option-btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($fetch_users), ENT_QUOTES, 'UTF-8'); ?>)">Edit</button>
                     <button class="delete-btn" onclick="openDeleteModal(<?php echo $fetch_users['id']; ?>)">Delete</button>  
               </td>
            </tr>
         <?php
               }
         } else {
            echo '<tr><td colspan="9"><p class="empty">no users found!</p></td></tr>'; // (SỬA) 8: Tăng colspan
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

<div id="addModal" class="modal">
   <div class="modal-content">
      <span class="close" onclick="closeModal('addModal')">&times;</span>
      <h3>Add New User</h3>
      <form method="POST" action="">
         <input type="text" name="name" class="box" placeholder="Username" required>
         <input type="email" name="email" class="box" placeholder="Email" required>
         <input type="tel" name="phone" class="box" placeholder="Phone Number">
         <input type="password" name="password" class="box" placeholder="Password" required>
         <select name="role" class="box" id="add_role" onchange="toggleStaffFields('add')">
            <option value="user" selected>User</option>
            <option value="staff">Staff</option>
            <option value="admin">Admin</option>
         </select>
         
         <div class="staff-fields" id="add-staff-fields">
            <input type="text" name="position" class="box" placeholder="Position (e.g. Florist)">
            <input type="number" step="0.01" name="salary" class="box" placeholder="Salary">
            <input type="date" name="hire_date" class="box" value="<?php echo date('Y-m-d'); ?>">
         </div>
         
         <div class="btn-group">
            <button type="button" class="btn" onclick="submitAddForm()">Add User</button>
         </div>
      </form>
   </div>
</div>

<div id="editModal" class="modal">
   <div class="modal-content">
      <span class="close" onclick="closeModal('editModal')">&times;</span>
      <h3>Edit User</h3>
      <form method="POST" action="">
         <input type="hidden" name="update_id" id="edit_id">
         <input type="text" name="update_name" id="edit_name" class="box" placeholder="Username" required>
         <input type="email" name="update_email" id="edit_email" class="box" placeholder="Email" required>
         <input type="tel" name="update_phone" id="edit_phone" class="box" placeholder="Phone Number">
         
         <select name="update_type" id="edit_type" class="box" onchange="toggleStaffFields('edit')">
            <option value="user">User</option>
            <option value="staff">Staff</option>
            <option value="admin">Admin</option>
         </select>
         
         <div class="staff-fields" id="edit-staff-fields">
             <input type="text" name="position" id="edit_position" class="box" placeholder="Position (e.g. Florist)">
             <input type="number" step="0.01" name="salary" id="edit_salary" class="box" placeholder="Salary">
         </div>
         
         <input type="password" name="new_password" class="box" placeholder="New Password (optional)">
         
         <div class="btn-group">
            <button type="button" class="btn" onclick="submitEditForm()">Update User</button>
         </div>
      </form>
   </div>
</div>

<div id="deleteModal" class="modal">
   <div class="modal-content">
      <span class="close" onclick="closeModal('deleteModal')">&times;</span>
      <h3>Are you sure you want to delete this user?</h3>
      <form method="POST" action="">
         <input type="hidden" name="delete_id" id="delete_id">
         <div class="btn-group">
            <button type="button" class="delete-btn" onclick="submitDeleteForm()">Yes, Delete</button>
            <button type="button" class="option-btn" onclick="closeModal('deleteModal')">Cancel</button>
         </div>
      </form>
   </div>
</div>

<div id="messageModal" class="modal">
   <div class="modal-content">
      <span class="close" onclick="closeModal('messageModal')">&times;</span>
      <h3>Messages from <span id="message_user_name">...</span></h3>
      <div class="message-list-container" id="message_list">
         <p class="empty">Loading...</p>
      </div>
   </div>
</div>

<script>
// === (MỚI) 11.1: Hàm Ẩn/Hiện trường Staff ===
function toggleStaffFields(prefix) {
    var userTypeSelect = document.getElementById(prefix + '_role');
    var staffFields = document.getElementById(prefix + '-staff-fields');
    if (userTypeSelect.value == 'staff') {
        staffFields.style.display = 'block';
    } else {
        staffFields.style.display = 'none';
    }
}

// === (MỚI) 11.2: Mở Modal Add ===
function openAddModal(){
   document.getElementById('addModal').style.display = 'flex';
   toggleStaffFields('add'); // Reset
}

// === (MỚI) 11.3: Mở Modal Edit (cập nhật) ===
function openEditModal(user){
   document.getElementById('edit_id').value = user.id;
   document.getElementById('edit_name').value = user.name;
   document.getElementById('edit_email').value = user.email;
   document.getElementById('edit_phone').value = user.phone || ''; 
   document.getElementById('edit_type').value = user.role; 
   
   // (MỚI) Điền thông tin staff
   document.getElementById('edit_position').value = user.position || '';
   document.getElementById('edit_salary').value = user.salary || '';
   
   document.getElementById('editModal').style.display = 'flex';
   toggleStaffFields('edit'); // Hiển thị nếu là staff
}

// Mở Modal Delete (Giữ nguyên)
function openDeleteModal(id){
   document.getElementById('delete_id').value = id;
   document.getElementById('deleteModal').style.display = 'flex';
}
// Đóng Modal (Giữ nguyên)
function closeModal(id){
   document.getElementById(id).style.display = 'none';
}

// === (MỚI) 11.4: XỬ LÝ SUBMIT AJAX ===
function handleAjaxResponse(data) {
    if(data.success){
        alert(data.message);
        window.location.reload();
    } else {
        alert(data.message);
    }
}

function submitAddForm() {
    var form = document.getElementById('addModal').querySelector('form');
    var formData = new FormData(form);
    formData.append('action', 'add_user'); 
    fetch('admin_users.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(handleAjaxResponse)
    .catch(error => console.error('Error:', error));
}

function submitEditForm() {
    var form = document.getElementById('editModal').querySelector('form');
    var formData = new FormData(form);
    formData.append('action', 'update_user'); 
    fetch('admin_users.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(handleAjaxResponse)
    .catch(error => console.error('Error:', error));
}

function submitDeleteForm() {
    var form = document.getElementById('deleteModal').querySelector('form');
    var formData = new FormData(form);
    formData.append('action', 'delete_user'); 
    fetch('admin_users.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(handleAjaxResponse)
    .catch(error => console.error('Error:', error));
}

// (JS Mở Modal Messages - Giữ nguyên)
var messageModal = document.getElementById('messageModal');
var messageListName = document.getElementById('message_user_name');
var messageList = document.getElementById('message_list');
document.querySelectorAll('.message-btn').forEach(button => {
    button.onclick = function() {
        var userId = this.dataset.userId;
        var userName = this.dataset.userName;
        messageListName.textContent = userName;
        messageList.innerHTML = '<p class="empty">Loading...</p>';
        messageModal.style.display = 'flex';
        var formData = new FormData();
        formData.append('action', 'get_messages');
        formData.append('user_id', userId);
        fetch('admin_users.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.messages.length > 0) {
                messageList.innerHTML = ''; 
                data.messages.forEach(msg => {
                    var msgElement = document.createElement('p');
                    msgElement.className = 'message-item';
                    msgElement.textContent = msg.message;
                    messageList.appendChild(msgElement);
                });
            } else {
                messageList.innerHTML = '<p class="empty">' + data.message + '</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            messageList.innerHTML = '<p class="empty">Error loading messages.</p>';
        });
    }
});

// Đóng modal khi click ra ngoài
window.onclick = function(e){
   const modals = ['editModal', 'deleteModal', 'addModal', 'messageModal']; // (SỬA) Thêm addModal
   modals.forEach(m=>{
      const modal = document.getElementById(m);
      if(e.target == modal){
         modal.style.display = 'none';
      }
   });
}
</script>

<script src="js/admin_script.js"></script>
</body>
</html>