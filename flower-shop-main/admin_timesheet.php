<?php
@include 'config.php';
session_start();

$admin_id = $_SESSION['user_id'] ?? null;
$admin_type = $_SESSION['role'] ?? null; 
if(!isset($admin_id) || ($admin_type != 'admin' && $admin_type != 'staff')){
   header('location:login.php');
   exit();
}

// (Logic PHP lấy $staff_list, xử lý POST, GET, Tính lương $payroll_data, lấy $timesheets - Giữ nguyên)
// ... (Toàn bộ khối PHP ở đầu tệp của bạn giữ nguyên) ...
$staff_list = $conn->query("
    SELECT e.id, u.name, e.position 
    FROM `users` u 
    JOIN `employees` e ON u.id = e.user_id 
    WHERE u.role = 'staff'
")->fetchAll(PDO::FETCH_ASSOC);

if(isset($_POST['add_timesheet'])){
    $employee_id = $_POST['employee_id']; 
    $clock_in = $_POST['clock_in_datetime'];
    $clock_out = $_POST['clock_out_datetime'];
    $notes = $_POST['notes'];
    
    $stmt = $conn->prepare("INSERT INTO `timesheets` (employee_id, clock_in, clock_out, notes) VALUES (?,?,?,?)");
    $stmt->execute([$employee_id, $clock_in, $clock_out, $notes]);
    $message[] = "Timesheet entry added!";
}

if(isset($_GET['delete'])){
    $delete_id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM `timesheets` WHERE id = ?");
    $stmt->execute([$delete_id]);
    header('location:admin_timesheet.php');
    exit();
}

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$payroll_data = [];
try {
    $assumed_monthly_hours = 173; 
    $payroll_stmt = $conn->prepare("
        SELECT 
            u.name, 
            e.salary AS monthly_salary,
            (e.salary / ?) AS hourly_rate,
            SUM(TIMESTAMPDIFF(MINUTE, t.clock_in, t.clock_out)) / 60 AS total_hours,
            (SUM(TIMESTAMPDIFF(MINUTE, t.clock_in, t.clock_out)) / 60) * (e.salary / ?) AS total_pay
        FROM `timesheets` t
        JOIN `employees` e ON t.employee_id = e.id
        JOIN `users` u ON e.user_id = u.id
        WHERE 
            t.clock_in >= ? AND t.clock_out <= ?
            AND t.clock_out IS NOT NULL
        GROUP BY 
            t.employee_id, u.name, e.salary
        ORDER BY 
            u.name
    ");
    $payroll_stmt->execute([
        $assumed_monthly_hours, 
        $assumed_monthly_hours, 
        $start_date . ' 00:00:00', 
        $end_date . ' 23:59:59'
    ]);
    $payroll_data = $payroll_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $message[] = "Payroll calculation failed: " . $e->getMessage();
}

$timesheets = $conn->query("
    SELECT t.*, u.name as staff_name 
    FROM `timesheets` t
    JOIN `employees` e ON t.employee_id = e.id
    JOIN `users` u ON e.user_id = u.id
    ORDER BY t.clock_in DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <title>Manage Timesheet</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/admin_style.css">
   <style>
      .report-table { width: 100%; background: var(--white); border-collapse: collapse; box-shadow: var(--box_shadow); border-radius: .5rem; overflow: hidden; margin-bottom: 2rem; }
      .report-table th, .report-table td { padding: 1.2rem 1.5rem; font-size: 1.6rem; text-align: left; border-bottom: 1px solid var(--light-bg); }
      .report-table th { background-color: var(--light-bg); color: var(--black); }
      .report-table td { color: var(--light-color); }
      .report-table .currency { color: var(--main-color); font-weight: 500; }
      
      .date-filter-form { background: var(--bg-color); padding: 2rem; border: var(--border); border-radius: .5rem; margin: 0 auto 2rem auto; max-width: 1200px; display: flex; align-items: flex-end; justify-content: center; gap: 1.5rem; }
      .date-filter-form .input-group { display: flex; flex-direction: column; }
      .date-filter-form label { font-size: 1.6rem; color: var(--light-color); margin-bottom: .5rem; }
      
      .add-products form { max-width: 50rem; }
      .add-products form .input-group { text-align: left; margin: 1.5rem 0; }
      .add-products form .input-group label { display: block; font-size: 1.6rem; color: var(--light-color); margin-bottom: .5rem; }
      .add-products form .box { width: 100%; }

      /* =========================================== */
      /* (MỚI) 2: CSS ĐỂ ẨN FORM (MODAL) */
      /* =========================================== */
      .modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); }
    .modal-content { background-color: var(--light-bg); margin: 5% auto; padding: 2rem; border: var(--border); width: 90%; max-width: 60rem; border-radius: .5rem; position: relative; }
    .modal-close { color: #aaa; position: absolute; top: 1rem; right: 2rem; font-size: 3rem; font-weight: bold; cursor: pointer; }
    .modal-content .box { width: 100%; font-size: 1.8rem; color:var(--black); border-radius: .5rem; background-color: var(--white); padding:1.2rem 1.4rem; border:var(--border); margin:1rem 0; }
    .modal-content h3 { font-size: 2.5rem; text-transform: uppercase; color:var(--black); margin-bottom: 1rem; text-align: center; }
    .modal-content .flex { display: flex; gap: 1rem; }
    .modal-content .flex .inputBox { width: 50%; }
    .modal-content .current-image { height: 10rem; border-radius: .5rem; margin-top: 1rem; }
    .modal-content textarea.box { height: 10rem; resize: none; }
   </style>
</head>
<body>
<?php @include 'admin_header.php'; ?>

<section class="placed-orders">
   <div class="controls-container">
      <h1 class="title" style="margin-bottom: 0;">Payroll Report</h1>
      <button type="button" class="btn" id="show-add-form-btn">Add Timesheet Entry</button>
   </div>
   
   <div class="box-container" style="display: block; max-width: 100%; overflow-x: auto;">
      <table class="report-table">
         <thead>
            <tr>
               <th>Staff Name</th>
               <th>Monthly Salary</th>
               <th>Hourly Rate (Est.)</th>
               <th>Total Hours Worked</th>
               <th>Total Pay (Est.)</th>
            </tr>
         </thead>
         <tbody>
         <?php if(empty($payroll_data)): ?>
            <tr><td colspan="5"><p class="empty">No payroll data found for this period.</p></td></tr>
         <?php else: ?>
            <?php foreach($payroll_data as $row): ?>
            <tr>
               <td><?php echo htmlspecialchars($row['name']); ?></td>
               <td class="currency">$<?php echo number_format($row['monthly_salary'], 2); ?></td>
               <td class="currency">$<?php echo number_format($row['hourly_rate'], 2); ?>/hr</td>
               <td><?php echo number_format($row['total_hours'], 2); ?> hrs</td>
               <td class="currency">$<?php echo number_format($row['total_pay'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
         <?php endif; ?>
         </tbody>
      </table>
   </div>
</section>

<div id="add-timesheet-modal" class="modal">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <section class="add-products"> <form action="" method="POST">
              <h3>Add Timesheet Entry</h3>
              
              <select name="employee_id" class="box" required>
                 <option value="" disabled selected>Select Staff</option>
                 <?php foreach($staff_list as $staff): ?>
                    <option value="<?php echo $staff['id']; ?>"><?php echo htmlspecialchars($staff['name']); ?></option>
                 <?php endforeach; ?>
              </select>
              
              <div class="input-group">
                 <label>Clock In:</label>
                 <input type="datetime-local" name="clock_in_datetime" class="box" required>
              </div>
              
              <div class="input-group">
                 <label>Clock Out:</label>
                 <input type="datetime-local" name="clock_out_datetime" class="box">
              </div>
              
              <div class="input-group">
                 <label>Notes (optional):</label>
                 <textarea name="notes" class="box" placeholder="Notes (optional)" rows="3"></textarea>
              </div>
              
              <input type="submit" value="Add Entry" name="add_timesheet" class="btn">
           </form>
        </section>
    </div>
</div>

<section class="placed-orders">
   <h1 class="title">Recent Timesheets</h1>
   <div class="box-container" style="display: block; max-width: 100%; overflow-x: auto;">
      <table class="report-table">
         <thead>
            <tr>
               <th>Staff Name</th>
               <th>Clock In</th>
               <th>Clock Out</th>
               <th>Notes</th>
               <th>Action</th>
            </tr>
         </thead>
         <tbody>
         <?php if(empty($timesheets)): ?>
            <tr><td colspan="5"><p class="empty">No timesheet entries found!</p></td></tr>
         <?php else: ?>
            <?php foreach($timesheets as $entry): ?>
            <tr>
               <td><?php echo htmlspecialchars($entry['staff_name']); ?></td>
               <td><?php echo $entry['clock_in']; ?></td>
               <td><?php echo $entry['clock_out']; ?></td>
               <td><?php echo htmlspecialchars($entry['notes']); ?></td>
               <td>
                  <a href="admin_timesheet.php?delete=<?php echo $entry['id']; ?>" class="delete-btn" onclick="return confirm('Delete this entry?');">Delete</a>
               </td>
            </tr>
            <?php endforeach; ?>
         <?php endif; ?>
         </tbody>
      </table>
   </div>
</section>

<script src="js/admin_script.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var addModal = document.getElementById('add-timesheet-modal');
    var showBtn = document.getElementById('show-add-form-btn');
    var closeBtn = addModal.querySelector('.modal-close');

    // Hiển thị modal
    showBtn.onclick = function() {
        addModal.style.display = 'block';
    }
    
    // Ẩn modal
    closeBtn.onclick = function() {
        addModal.style.display = 'none';
    }
    
    window.onclick = function(event) {
        if (event.target == addModal) {
            addModal.style.display = 'none';
        }
    }
});
</script>
</body>
</html>