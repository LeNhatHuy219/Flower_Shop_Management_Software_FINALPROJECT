<?php
@include 'config.php';
session_start();

// 1. Kiểm tra session Admin
$admin_user_id = $_SESSION['user_id'] ?? null;
$admin_type = $_SESSION['role'] ?? null; 

if(!isset($admin_user_id) || $admin_type != 'admin'){
   header('location:login.php');
   exit();
}

// (MỚI) Lấy trang hiện tại từ URL, mặc định là trang 1
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

// (SỬA) 2. (CREATE) Xử lý THÊM khuyến mãi
if(isset($_POST['add_promotion'])){
   $code = htmlspecialchars($_POST['code']);
   $desc = htmlspecialchars($_POST['description']);
   $type = htmlspecialchars($_POST['discount_type']);
   $value = (float)$_POST['discount_value'];
   $start = htmlspecialchars($_POST['start_date']);
   $end = htmlspecialchars($_POST['end_date']);
   $active = isset($_POST['is_active']) ? 1 : 0;

   try {
       $stmt_check = $conn->prepare("SELECT id FROM promotions WHERE code = ?");
       $stmt_check->execute([$code]);
       
       if($stmt_check->rowCount() > 0){
          $message[] = 'Mã khuyến mãi này đã tồn tại!';
       } else {
          $stmt_insert = $conn->prepare("INSERT INTO promotions (code, description, discount_type, discount_value, start_date, end_date, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
          $stmt_insert->execute([$code, $desc, $type, $value, $start, $end, $active]);
          $message[] = 'Thêm khuyến mãi mới thành công!';
          // (SỬA) Redirect về đúng trang đang xem
          header('location:admin_promotion.php?page=' . $current_page);
          exit();
       }
   } catch (Exception $e) {
       $message[] = 'Lỗi CSDL: ' . $e->getMessage();
   }
}

// (SỬA) 3. (DELETE) Xử lý XÓA khuyến mãi
if(isset($_GET['delete'])){
   $delete_id = $_GET['delete'];
   try {
       $stmt_delete = $conn->prepare("DELETE FROM promotions WHERE id = ?");
       $stmt_delete->execute([$delete_id]);
       $message[] = 'Đã xóa khuyến mãi!';
       // (SỬA) Redirect về đúng trang đang xem
       header('location:admin_promotion.php?page=' . $current_page);
       exit();
   } catch (Exception $e) {
       $message[] = 'Lỗi CSDL: ' . $e->getMessage();
   }
}

// (SỬA) 4. (UPDATE) Xử lý CẬP NHẬT khuyến mãi
if(isset($_POST['update_promotion'])){
   $update_p_id = $_POST['update_p_id'];
   $code = htmlspecialchars($_POST['code']);
   $desc = htmlspecialchars($_POST['description']);
   $type = htmlspecialchars($_POST['discount_type']);
   $value = (float)$_POST['discount_value'];
   $start = htmlspecialchars($_POST['start_date']);
   $end = htmlspecialchars($_POST['end_date']);
   $active = isset($_POST['is_active']) ? 1 : 0;

   try {
       $stmt_check = $conn->prepare("SELECT id FROM promotions WHERE code = ? AND id != ?");
       $stmt_check->execute([$code, $update_p_id]);
       
       if($stmt_check->rowCount() > 0){
          $message[] = 'Mã khuyến mãi này đã bị trùng!';
       } else {
          $stmt_update = $conn->prepare("UPDATE promotions SET code = ?, description = ?, discount_type = ?, discount_value = ?, start_date = ?, end_date = ?, is_active = ? WHERE id = ?");
          $stmt_update->execute([$code, $desc, $type, $value, $start, $end, $active, $update_p_id]);
          $message[] = 'Cập nhật khuyến mãi thành công!';
          // (SỬA) Redirect về đúng trang đang xem
          header('location:admin_promotion.php?page=' . $current_page);
          exit();
       }
   } catch (Exception $e) {
       $message[] = 'Lỗi CSDL: ' . $e->getMessage();
   }
}

// (MỚI) 5. LOGIC PHÂN TRANG (DATA)
$results_per_page = 2; // Số kết quả mỗi trang (có thể đổi thành 10, 20...)
$starting_limit_number = ($current_page - 1) * $results_per_page;

// Lấy tổng số kết quả
$stmt_count = $conn->prepare("SELECT COUNT(*) FROM promotions");
$stmt_count->execute();
$total_results = (int)$stmt_count->fetchColumn();

// Tính tổng số trang
$total_pages = ceil($total_results / $results_per_page);

// (MỚI) 6. Lấy data cho trang hiện tại (Thêm LIMIT và OFFSET)
$stmt_select = $conn->prepare("SELECT * FROM promotions ORDER BY is_active DESC, end_date DESC LIMIT :limit OFFSET :offset");
$stmt_select->bindParam(':limit', $results_per_page, PDO::PARAM_INT);
$stmt_select->bindParam(':offset', $starting_limit_number, PDO::PARAM_INT);
$stmt_select->execute();

?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <title>Manage Promotions</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/admin_style.css">
   <style>
      /* Style cho bảng (Giữ nguyên) */
      .report-table { width: 100%; background: var(--white); border-collapse: collapse; box-shadow: var(--box_shadow); border-radius: .5rem; overflow: hidden; margin-bottom: 2rem; }
      .report-table th, .report-table td { padding: 1.2rem 1.5rem; font-size: 1.6rem; text-align: left; border-bottom: 1px solid var(--light-bg); }
      .report-table th { background-color: var(--light-bg); color: var(--black); }
      .report-table td { color: var(--light-color); }
      .report-table .action-btns { display: flex; gap: .5rem; }

      /* CSS CHO MODAL (Giữ nguyên) */
      .modal-overlay {
         position: fixed;
         top: 0;
         left: 0;
         width: 100%;
         height: 100%;
         background: rgba(0,0,0,0.6);
         z-index: 1100;
         display: none;
         align-items: center;
         justify-content: center;
         padding: 2rem;
      }
      .modal-content {
         background: var(--white);
         border-radius: .5rem;
         padding: 2rem;
         width: 100%;
         max-width: 80rem;
         max-height: 90vh;
         overflow-y: auto;
         position: relative;
         box-shadow: var(--box_shadow);
      }
      .modal-close {
         position: absolute;
         top: 1.5rem;
         right: 2.5rem;
         font-size: 3.5rem;
         color: var(--light-color);
         cursor: pointer;
         line-height: 1;
      }
      .modal-close:hover {
         color: var(--red);
      }
      .add-promo-btn-container {
         padding: 1rem 2rem;
         text-align: right;
         max-width: 1200px;
         margin: 0 auto;
      }

      /* (MỚI) CSS Phân trang */
      
   </style>
</head>
<body>

<?php @include 'admin_header.php'; ?>

<section class="add-promo-btn-container">
   <a href="#" class="btn" id="addPromoBtn">Add New Promotion</a>
</section>

<section class="placed-orders">
   <h1 class="title">All Promotions</h1>
   <div class="box-container" style="display: block; max-width: 100%; overflow-x: auto;">
      <table class="report-table">
         <thead>
            <tr>
               <th>Code</th>
               <th>Description</th>
               <th>Type</th>
               <th>Value</th>
               <th>Start Date</th>
               <th>End Date</th>
               <th>Status</th>
               <th>Actions</th>
            </tr>
         </thead>
         <tbody>
            <?php
               // $stmt_select đã được thực thi ở đầu file
               if($stmt_select->rowCount() > 0){
                  while($promo = $stmt_select->fetch(PDO::FETCH_ASSOC)){
            ?>
            <tr>
               <td><?php echo htmlspecialchars($promo['code']); ?></td>
               <td><?php echo htmlspecialchars($promo['description']); ?></td>
               <td><?php echo htmlspecialchars($promo['discount_type']); ?></td>
               <td>
                  <?php 
                     if($promo['discount_type'] == 'percent') {
                        echo htmlspecialchars($promo['discount_value']) . '%';
                     } else {
                        echo '$' . htmlspecialchars(number_format($promo['discount_value'], 2));
                     }
                  ?>
               </td>
               <td><?php echo htmlspecialchars($promo['start_date']); ?></td>
               <td><?php echo htmlspecialchars($promo['end_date']); ?></td>
               <td>
                  <span style="color: <?php echo $promo['is_active'] ? 'var(--main-color)' : 'var(--red)'; ?>;">
                     <?php echo $promo['is_active'] ? 'Active' : 'Inactive'; ?>
                  </span>
               </td>
               <td class="action-btns">
                  <a href="#" class="option-btn edit-btn"
                     data-id="<?php echo $promo['id']; ?>"
                     data-code="<?php echo htmlspecialchars($promo['code']); ?>"
                     data-desc="<?php echo htmlspecialchars($promo['description']); ?>"
                     data-type="<?php echo $promo['discount_type']; ?>"
                     data-value="<?php echo htmlspecialchars($promo['discount_value']); ?>"
                     data-start="<?php echo date('Y-m-d\TH:i', strtotime($promo['start_date'])); ?>"
                     data-end="<?php echo date('Y-m-d\TH:i', strtotime($promo['end_date'])); ?>"
                     data-active="<?php echo $promo['is_active']; ?>"
                  >Edit</a>
                  
                  <a href="admin_promotion.php?delete=<?php echo $promo['id']; ?>&page=<?php echo $current_page; ?>" class="delete-btn" onclick="return confirm('Bạn có chắc muốn xóa khuyến mãi này?');">Delete</a>
               </td>
            </tr>
            <?php
                  }
               } else {
                  echo '<tr><td colspan="8"><p class="empty">Chưa có khuyến mãi nào!</p></td></tr>';
               }
            ?>
         </tbody>
      </table>

      <div class="pagination">
         <?php if($total_pages > 1): // Chỉ hiển thị nếu có nhiều hơn 1 trang ?>
            
            <?php if($current_page > 1): ?>
               <a href="admin_promotion.php?page=<?php echo $current_page - 1; ?>">« Prev</a>
            <?php endif; ?>

            <?php for($i = 1; $i <= $total_pages; $i++): ?>
               <a href="admin_promotion.php?page=<?php echo $i; ?>" class="<?php if($i == $current_page) echo 'active'; ?>">
                  <?php echo $i; ?>
               </a>
            <?php endfor; ?>
            
            <?php if($current_page < $total_pages): ?>
               <a href="admin_promotion.php?page=<?php echo $current_page + 1; ?>">Next »</a>
            <?php endif; ?>

         <?php endif; ?>
      </div>

   </div>
</section>


<div class="modal-overlay" id="addModal">
   <div class="modal-content">
      <span class="modal-close">&times;</span>
      <section class="add-products" style="padding: 0; margin: 0; box-shadow: none; border: none;">
         <h1 class="title">Add New Promotion</h1>
         <form action="admin_promotion.php?page=<?php echo $current_page; ?>" method="POST">
            <div class="flex">
               <div class="inputBox">
                  
                  <input type="text" class="box" required placeholder="e.g., SALE20" name="code">
               </div>
               <div class="inputBox">
          
                  <select name="discount_type" class="box" required>
                     <option value="percent" selected>Percent (%)</option>
                     <option value="fixed">Fixed Amount ($)</option>
                  </select>
               </div>
               <div class="inputBox">
          
                  <input type="number" step="0.01" min="0" class="box" required placeholder="e.g., 20 or 5.50" name="discount_value">
               </div>
               <div class="inputBox">
              
                  <input type="datetime-local" class="box" required name="start_date" value="<?php echo date('Y-m-d\TH:i'); ?>">
               </div>
               <div class="inputBox">
          
                  <input type="datetime-local" class="box" required name="end_date" value="<?php echo date('Y-m-d\TH:i', strtotime('+1 month')); ?>">
               </div>
               <div class="inputBox">
             
                  <textarea name="description" placeholder="Promotion details" class="box" cols="30" rows="5"></textarea>
               </div>
            </div>
            <label style="font-size: 1.6rem; color: var(--light-color);">
               <input type="checkbox" name="is_active" value="1" checked>
               Is Active
            </label>
            <input type="submit" value="Add Promotion" name="add_promotion" class="btn">
         </form>
      </section>
   </div>
</div>

<div class="modal-overlay" id="updateModal">
   <div class="modal-content">
      <span class="modal-close">&times;</span>
      <section class="add-products" style="padding: 0; margin: 0; box-shadow: none; border: none;">
         <h1 class="title">Update Promotion</h1>
         <form action="admin_promotion.php?page=<?php echo $current_page; ?>" method="POST">
            <input type="hidden" name="update_p_id" id="update_p_id" value="">
            
            <div class="flex">
               <div class="inputBox">
                  <span>Promotion Code (required)</span>
                  <input type="text" class="box" required name="code" id="update_code" value="">
               </div>
               <div class="inputBox">
                  <span>Discount Type (required)</span>
                  <select name="discount_type" class="box" required id="update_type">
                     <option value="percent">Percent (%)</option>
                     <option value="fixed">Fixed Amount ($)</option>
                  </select>
               </div>
               <div class="inputBox">
                  <span>Discount Value (required)</span>
                  <input type="number" step="0.01" min="0" class="box" required name="discount_value" id="update_value" value="">
               </div>
               <div class="inputBox">
                  <span>Start Date (required)</span>
                  <input type="datetime-local" class="box" required name="start_date" id="update_start" value="">
               </div>
               <div class="inputBox">
                  <span>End Date (required)</span>
                  <input type="datetime-local" class="box" required name="end_date" id="update_end" value="">
               </div>
               <div class="inputBox">
                  <span>Description</span>
                  <textarea name="description" class="box" cols="30" rows="5" id="update_description"></textarea>
               </div>
            </div>
            
            <label style="font-size: 1.6rem; color: var(--light-color);">
               <input type="checkbox" name="is_active" value="1" id="update_active">
               Is Active
            </label>
            
            <input type="submit" value="Update Promotion" name="update_promotion" class="btn">
         </form>
      </section>
   </div>
</div>


<script src="js/admin_script.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {

   const addModal = document.getElementById('addModal');
   const updateModal = document.getElementById('updateModal');
   const addPromoBtn = document.getElementById('addPromoBtn');
   const closeBtns = document.querySelectorAll('.modal-close');
   const editBtns = document.querySelectorAll('.edit-btn');

   addPromoBtn.addEventListener('click', (e) => {
      e.preventDefault();
      addModal.style.display = 'flex';
   });

   editBtns.forEach(btn => {
      btn.addEventListener('click', (e) => {
         e.preventDefault();
         const data = btn.dataset;
         document.getElementById('update_p_id').value = data.id;
         document.getElementById('update_code').value = data.code;
         document.getElementById('update_description').value = data.desc;
         document.getElementById('update_type').value = data.type;
         document.getElementById('update_value').value = data.value;
         document.getElementById('update_start').value = data.start;
         document.getElementById('update_end').value = data.end;
         document.getElementById('update_active').checked = (data.active == '1');
         updateModal.style.display = 'flex';
      });
   });

   closeBtns.forEach(btn => {
      btn.addEventListener('click', () => {
         btn.closest('.modal-overlay').style.display = 'none';
      });
   });

   window.addEventListener('click', (e) => {
      if (e.target.classList.contains('modal-overlay')) {
         e.target.style.display = 'none';
      }
   });

});
</script>

</body>
</html>