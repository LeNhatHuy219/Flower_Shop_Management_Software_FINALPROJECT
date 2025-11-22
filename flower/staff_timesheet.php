<?php
@include 'config.php';
session_start();

// 1. Kiểm tra session user
$staff_user_id = $_SESSION['user_id'] ?? null;
$staff_type = $_SESSION['role'] ?? null; 

if(!isset($staff_user_id) || $staff_type != 'staff'){
   header('location:login.php');
   exit();
}

// 2. Khởi tạo biến
$staff_employee_id = null;
$schedules = null;
$timesheets = null;
$is_clocked_in = false; // Trạng thái: Đang check-in hay không
$current_timesheet_id = null;
$has_shift_today = false; // (MỚI) Biến kiểm tra ca làm hôm nay

try {
    // 3. Lấy 'employee_id' từ 'user_id'
    $stmt_get_emp_id = $conn->prepare("SELECT id FROM `employees` WHERE user_id = ?");
    $stmt_get_emp_id->execute([$staff_user_id]);
    $employee = $stmt_get_emp_id->fetch();
    
    if($employee) {
        $staff_employee_id = $employee['id'];
    } else {
        $message[] = "Không tìm thấy hồ sơ nhân viên!";
    }
} catch (Exception $e) {
     $message[] = "Lỗi CSDL khi tìm nhân viên: " . $e->getMessage();
}

// 4. Chỉ thực hiện nếu đã tìm thấy employee_id
if($staff_employee_id) {
    try {
        
        // (MỚI) 4.1: Kiểm tra xem hôm nay có ca làm không?
        $stmt_check_schedule = $conn->prepare("SELECT id FROM schedules WHERE employee_id = ? AND shift_date = CURDATE()");
        $stmt_check_schedule->execute([$staff_employee_id]);
        if($stmt_check_schedule->fetch()) {
            $has_shift_today = true;
        }

        // 4.2: Kiểm tra trạng thái check-in hiện tại
        $stmt_check = $conn->prepare("SELECT id FROM `timesheets` WHERE employee_id = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1");
        $stmt_check->execute([$staff_employee_id]);
        $open_shift = $stmt_check->fetch();
        
        if($open_shift) {
            $is_clocked_in = true;
            $current_timesheet_id = $open_shift['id'];
        }

        // (SỬA) 4.3: Chỉ xử lý POST nếu CÓ CA LÀM HÔM NAY
        if ($has_shift_today) {
            
            // Xử lý Check In
            if(isset($_POST['clock_in'])){
                if($is_clocked_in) {
                    $message[] = "Bạn đang trong ca làm, không thể check-in lại!";
                } else {
                    $stmt_in = $conn->prepare("INSERT INTO `timesheets` (employee_id, clock_in) VALUES (?, NOW())");
                    $stmt_in->execute([$staff_employee_id]);
                    $message[] = "Check-in thành công!";
                    $is_clocked_in = true;
                    $current_timesheet_id = $conn->lastInsertId();
                }
            }

            // Xử lý Check Out
            if(isset($_POST['clock_out'])){
                if(!$is_clocked_in) {
                    $message[] = "Bạn chưa check-in!";
                } else {
                    // Kiểm tra giờ kết thúc ca (logic cũ vẫn giữ)
                    $stmt_shift_check = $conn->prepare(
                        "SELECT end_time FROM schedules 
                         WHERE employee_id = ? 
                         AND shift_date = CURDATE() 
                         AND start_time <= TIME(NOW()) 
                         AND end_time > TIME(NOW())"
                    );
                    $stmt_shift_check->execute([$staff_employee_id]);

                    if ($stmt_shift_check->fetch()) {
                        $message[] = "Chưa đến giờ kết thúc ca. Bạn không thể check-out!";
                    } else {
                        $stmt_out = $conn->prepare("UPDATE `timesheets` SET clock_out = NOW() WHERE id = ?");
                        $stmt_out->execute([$current_timesheet_id]);
                        $message[] = "Check-out thành công!";
                        $is_clocked_in = false;
                    }
                }
            }

        } else {
            // (MỚI) Nếu không có ca mà vẫn bấm nút (trường hợp hiếm)
            if(isset($_POST['clock_in']) || isset($_POST['clock_out'])) {
                $message[] = "Bạn không có ca làm nào được phân công hôm nay!";
            }
        }


        // 4.4: Lấy Lịch làm việc (Schedule) - Hiển thị các ca còn lại
        $schedules = $conn->prepare("
            SELECT * FROM `schedules` 
            WHERE employee_id = ? 
            AND (
                (shift_date = CURDATE() AND end_time > TIME(NOW())) 
                OR 
                (shift_date > CURDATE())
            )
            ORDER BY shift_date ASC, start_time ASC
        ");
        $schedules->execute([$staff_employee_id]);

        // 4.5: Lấy Lịch sử chấm công (Timesheet)
        $timesheets = $conn->prepare("
            SELECT * FROM `timesheets` 
            WHERE employee_id = ? 
            ORDER BY clock_in DESC 
            LIMIT 10
        ");
        $timesheets->execute([$staff_employee_id]);

    } catch (Exception $e) {
        $message[] = "Lỗi CSDL: " . $e->getMessage();
    }
}

// 5. Xử lý dự phòng
if(is_null($schedules)){
    $schedules = $conn->prepare("SELECT * FROM `schedules` WHERE 1=0");
    $schedules->execute();
}
if(is_null($timesheets)){
    $timesheets = $conn->prepare("SELECT * FROM `timesheets` WHERE 1=0");
    $timesheets->execute();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <title>My Timesheet</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/admin_style.css">
   <style>
      .report-table { width: 100%; background: var(--white); border-collapse: collapse; box-shadow: var(--box_shadow); border-radius: .5rem; overflow: hidden; margin-bottom: 2rem; }
      .report-table th, .report-table td { padding: 1.2rem 1.5rem; font-size: 1.6rem; text-align: left; border-bottom: 1px solid var(--light-bg); }
      .report-table th { background-color: var(--light-bg); color: var(--black); }
      .report-table td { color: var(--light-color); }
      
      .clock-section {
          background: var(--white);
          border: var(--border);
          border-radius: .5rem;
          padding: 2rem;
          text-align: center;
          max-width: 50rem;
          margin: 0 auto 2rem auto;
      }
      .clock-section h3 {
          font-size: 2.2rem;
          color: var(--black);
          margin-bottom: 1rem;
      }
      .clock-section form {
          display: flex;
          gap: 1rem;
          justify-content: center;
      }
      .clock-section .btn, .clock-section .delete-btn {
          width: 50%;
      }
   </style>
</head>
<body>
<?php @include 'staff_header.php'; ?>

<?php if($staff_employee_id): ?>
<section class="clock-section">
   <h3>My Attendance</h3>
   
   <?php if ($has_shift_today): ?>
      <form method="POST" action="">
         <?php if ($is_clocked_in): ?>
            <input type="submit" name="clock_out" value="Clock Out" class="delete-btn">
         <?php else: ?>
            <input type="submit" name="clock_in" value="Clock In" class="btn">
         <?php endif; ?>
      </form>
   <?php else: ?>
      <p class="empty" style="margin: 0;">Bạn không có ca làm nào hôm nay.</p>
   <?php endif; ?>

</section>
<?php endif; ?>


<section class="placed-orders">
   <h1 class="title">My Upcoming Shifts</h1>
   <div class="box-container" style="display: block; max-width: 100%; overflow-x: auto;">
      <table class="report-table">
         <thead>
            <tr>
               <th>Date</th>
               <th>Start Time</th>
               <th>End Time</th>
            </tr>
         </thead>
         <tbody>
         <?php if($schedules->rowCount() == 0): ?>
            <tr><td colspan="3"><p class="empty">You have no upcoming shifts assigned.</p></td></tr>
         <?php else: ?>
            <?php while($shift = $schedules->fetch(PDO::FETCH_ASSOC)): ?>
            <tr>
               <td><?php echo htmlspecialchars($shift['shift_date']); ?></td>
               <td><?php echo htmlspecialchars($shift['start_time']); ?></td>
               <td><?php echo htmlspecialchars($shift['end_time']); ?></td>
            </tr>
            <?php endwhile; ?>
         <?php endif; ?>
         </tbody>
      </table>
   </div>
</section>

<section class="placed-orders">
   <h1 class="title">My Recent Activity</h1>
   <div class="box-container" style="display: block; max-width: 100%; overflow-x: auto;">
      <table class="report-table">
         <thead>
            <tr>
               <th>Clock In</th>
               <th>Clock Out</th>
               <th>Total Hours</th>
            </tr>
         </thead>
         <tbody>
         <?php if($timesheets->rowCount() == 0): ?>
            <tr><td colspan="3"><p class="empty">No timesheet entries found!</p></td></tr>
         <?php else: ?>
            <?php while($entry = $timesheets->fetch(PDO::FETCH_ASSOC)): 
                // Tính giờ
                $hours = '-';
                if($entry['clock_out']){
                    try {
                        $clock_in_time = new DateTime($entry['clock_in']);
                        $clock_out_time = new DateTime($entry['clock_out']);
                        $interval = $clock_in_time->diff($clock_out_time);
                        $hours = $interval->format('%h hrs %i mins');
                    } catch (Exception $e) {
                        $hours = 'Error';
                    }
                }
            ?>
            <tr>
               <td><?php echo htmlspecialchars($entry['clock_in']); ?></td>
               <td><?php echo $entry['clock_out'] ? htmlspecialchars($entry['clock_out']) : '<i>(Still clocked in)</i>'; ?></td>
               <td><?php echo $hours; ?></td>
            </tr>
            <?php endwhile; ?>
         <?php endif; ?>
         </tbody>
      </table>
   </div>
</section>

<script src="js/admin_script.js"></script>
</body>
</html>