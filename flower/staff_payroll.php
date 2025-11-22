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
$position = 'N/A';
$monthly_salary = 0.00;
$total_hours_this_month = '0 hrs 00 mins';
$completed_shifts = null; 

try {
    // 3. Lấy 'employee_id' và thông tin lương
    $stmt_get_emp = $conn->prepare("SELECT * FROM `employees` WHERE user_id = ?");
    $stmt_get_emp->execute([$staff_user_id]);
    $employee = $stmt_get_emp->fetch(PDO::FETCH_ASSOC);
    
    if($employee) {
        $staff_employee_id = $employee['id'];
        $position = $employee['position'];
        $monthly_salary = (float)$employee['salary']; 
    } else {
        $message[] = "Không tìm thấy hồ sơ nhân viên!";
    }
} catch (Exception $e) {
     $message[] = "Lỗi CSDL khi tìm nhân viên: " . $e->getMessage();
}

// 4. Chỉ thực hiện nếu đã tìm thấy employee_id
if($staff_employee_id) {
    try {
        // 4.1. Tính toán tổng giờ tháng này (Logic này ĐÃ ĐÚNG)
        $current_month = date('Y-m');
        
        $stmt_hours = $conn->prepare("
            SELECT SUM(TIME_TO_SEC(TIMEDIFF(clock_out, clock_in))) AS total_seconds
            FROM timesheets
            WHERE employee_id = ?
              AND clock_out IS NOT NULL
              AND DATE_FORMAT(clock_in, '%Y-%m') = ?
        ");
        $stmt_hours->execute([$staff_employee_id, $current_month]);
        $hours_data = $stmt_hours->fetch(PDO::FETCH_ASSOC);

        if ($hours_data && $hours_data['total_seconds'] > 0) {
            $total_seconds = (int)$hours_data['total_seconds'];
            
            $total_hours = floor($total_seconds / 3600);
            $total_minutes = floor(($total_seconds % 3600) / 60);
            $total_hours_this_month = sprintf('%d hrs %02d mins', $total_hours, $total_minutes);
        }

        // 4.2. Lấy 10 ca làm đã hoàn thành gần nhất
        $completed_shifts = $conn->prepare("
            SELECT * FROM `timesheets` 
            WHERE employee_id = ? AND clock_out IS NOT NULL
            ORDER BY clock_in DESC 
            LIMIT 10
        ");
        $completed_shifts->execute([$staff_employee_id]);

    } catch (Exception $e) {
        $message[] = "Lỗi CSDL khi tính lương: " . $e->getMessage();
    }
}

// Xử lý dự phòng
if(is_null($completed_shifts)){
    $completed_shifts = $conn->prepare("SELECT * FROM `timesheets` WHERE 1=0");
    $completed_shifts->execute();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <title>My Payroll</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/admin_style.css">
   <style>
      .report-table { width: 100%; background: var(--white); border-collapse: collapse; box-shadow: var(--box_shadow); border-radius: .5rem; overflow: hidden; margin-bottom: 2rem; }
      .report-table th, .report-table td { padding: 1.2rem 1.5rem; font-size: 1.6rem; text-align: left; border-bottom: 1px solid var(--light-bg); }
      .report-table th { background-color: var(--light-bg); color: var(--black); }
      .report-table td { color: var(--light-color); }
      
      .payroll-summary {
          background: var(--white);
          border: var(--border);
          border-radius: .5rem;
          padding: 2.5rem;
          text-align: center;
          max-width: 70rem;
          margin: 0 auto 2rem auto;
          box-shadow: var(--box_shadow);
      }
      .payroll-summary h3 {
          font-size: 2rem;
          color: var(--black);
          margin-bottom: 1.5rem;
          line-height: 1.4;
      }
      .payroll-summary h3 span {
          font-weight: normal;
          color: var(--main-color);
          margin-left: .5rem;
      }
      .payroll-summary .total-pay {
          font-size: 2.4rem;
          color: var(--red);
          font-weight: bold;
      }
      .payroll-summary .total-pay span {
          color: var(--red);
      }
   </style>
</head>
<body>
<?php @include 'staff_header.php'; ?>

<section class="payroll-summary">
   <h1 class="title" style="margin-bottom: 2.5rem;">Payroll Summary</h1>
   
   <h3>Position: <span><?php echo htmlspecialchars($position); ?></span></h3>
   <h3 class="total-pay">Monthly Salary: <span>$<?php echo htmlspecialchars(number_format($monthly_salary, 2)); ?></span></h3>
   <hr style="margin: 1.5rem 0; border-top: 1px solid var(--light-bg); border-bottom: 0;">
   
   <h3>Hours Worked (This Month): <span><?php echo htmlspecialchars($total_hours_this_month); ?></span></h3>
</section>

<section class="placed-orders">
   <h1 class="title">Recent Completed Shifts</h1>
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
         <?php if($completed_shifts->rowCount() == 0): ?>
            <tr><td colspan="3"><p class="empty">No completed shifts found!</p></td></tr>
         <?php else: ?>
            <?php while($entry = $completed_shifts->fetch(PDO::FETCH_ASSOC)): 
                
                // --- (SỬA) BẮT ĐẦU LOGIC SỬA LỖI TÍNH GIỜ ---
                $hours = '-';
                if($entry['clock_out']){
                    try {
                        $clock_in_time = new DateTime($entry['clock_in']);
                        $clock_out_time = new DateTime($entry['clock_out']);
                        
                        // Tính tổng số giây chênh lệch
                        $total_seconds_shift = $clock_out_time->getTimestamp() - $clock_in_time->getTimestamp();
                        
                        // Quy đổi về giờ và phút (giống hệt logic tổng ở trên)
                        $total_hours_shift = floor($total_seconds_shift / 3600);
                        $total_minutes_shift = floor(($total_seconds_shift % 3600) / 60);
                        
                        $hours = sprintf('%d hrs %02d mins', $total_hours_shift, $total_minutes_shift);

                    } catch (Exception $e) {
                        $hours = 'Error';
                    }
                }
                // --- (SỬA) KẾT THÚC LOGIC SỬA LỖI ---
            ?>
            <tr>
               <td><?php echo htmlspecialchars($entry['clock_in']); ?></td>
               <td><?php echo htmlspecialchars($entry['clock_out']); ?></td>
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