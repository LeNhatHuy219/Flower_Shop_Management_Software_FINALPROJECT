<?php
@include 'config.php';
session_start();

// (SỬA) 1: Dùng 'user_type'
$admin_id = $_SESSION['user_id'] ?? null;
$admin_type = $_SESSION['role'] ?? null; 
if(!isset($admin_id) || ($admin_type != 'admin' )){
   header('location:login.php');
   exit();
}

// (SỬA) 2: Lấy 'e.id' (employee ID)
$staff_list = $conn->query("
    SELECT e.id, u.name, e.position 
    FROM `users` u 
    JOIN `employees` e ON u.id = e.user_id 
    WHERE u.role = 'staff'
")->fetchAll(PDO::FETCH_ASSOC); // Sửa 'role' thành 'user_type'

// Xử lý thêm lịch
if(isset($_POST['add_schedule'])){
    $employee_id = $_POST['employee_id']; // Đây (e.id) là ID mà CSDL cần
    $shift_date = $_POST['shift_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    $stmt = $conn->prepare("INSERT INTO `schedules` (employee_id, shift_date, start_time, end_time) VALUES (?,?,?,?)");
    $stmt->execute([$employee_id, $shift_date, $start_time, $end_time]); // Dòng 28 sẽ chạy đúng
    $message[] = "Shift added successfully!";
}

// Xử lý xóa (Dùng PDO)
if(isset($_GET['delete'])){
    $delete_id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM `schedules` WHERE id = ?");
    $stmt->execute([$delete_id]);
    header('location:admin_schedule.php');
    exit();
}

// (SỬA) 3: JOIN bảng 'employees' trước
$schedules = $conn->query("
    SELECT s.*, u.name as staff_name 
    FROM `schedules` s
    JOIN `employees` e ON s.employee_id = e.id
    JOIN `users` u ON e.user_id = u.id
    ORDER BY s.shift_date DESC, s.start_time ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <title>Manage Schedule</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/admin_style.css">
   <style>
      .schedule-table { width: 100%; background: var(--white); border-collapse: collapse; box-shadow: var(--box_shadow); border-radius: .5rem; overflow: hidden; }
      .schedule-table th, .schedule-table td { padding: 1.2rem 1.5rem; font-size: 1.6rem; text-align: left; border-bottom: 1px solid var(--light-bg); }
      .schedule-table th { background-color: var(--light-bg); color: var(--black); }
      .schedule-table td { color: var(--light-color); }
   </style>
</head>
<body>
<?php @include 'admin_header.php'; ?>

<section class="add-products">
   <form action="" method="POST">
      <h3>Assign New Shift</h3>
      <select name="employee_id" class="box" required>
         <option value="" disabled selected>Select Staff</option>
         <?php foreach($staff_list as $staff): ?>
            <option value="<?php echo $staff['id']; ?>"><?php echo htmlspecialchars($staff['name']) . ' (' . htmlspecialchars($staff['position']) . ')'; ?></option>
         <?php endforeach; ?>
      </select>
      <input type="date" name="shift_date" class="box" value="<?php echo date('Y-m-d'); ?>" required>
      <input type="time" name="start_time" class="box" required>
      <input type="time" name="end_time" class="box" required>
      <input type="submit" value="Add Shift" name="add_schedule" class="btn">
   </form>
</section>

<section class="placed-orders">
   <h1 class="title">Upcoming Schedules</h1>
   <div class="box-container" style="display: block; max-width: 100%; overflow-x: auto;">
      <table class="schedule-table">
         <thead>
            <tr>
               <th>Staff Name</th>
               <th>Date</th>
               <th>Start Time</th>
               <th>End Time</th>
               <th>Action</th>
            </tr>
         </thead>
         <tbody>
         <?php if(empty($schedules)): ?>
            <tr><td colspan="5"><p class="empty">No schedules found!</p></td></tr>
         <?php else: ?>
            <?php foreach($schedules as $schedule): ?>
            <tr>
               <td><?php echo htmlspecialchars($schedule['staff_name']); ?></td>
               <td><?php echo $schedule['shift_date']; ?></td>
               <td><?php echo $schedule['start_time']; ?></td>
               <td><?php echo $schedule['end_time']; ?></td>
               <td>
                  <a href="admin_schedule.php?delete=<?php echo $schedule['id']; ?>" class="delete-btn" onclick="return confirm('Delete this shift?');">Delete</a>
               </td>
            </tr>
            <?php endforeach; ?>
         <?php endif; ?>
         </tbody>
      </table>
   </div>
</section>

<script src="js/admin_script.js"></script>
</body>
</html>