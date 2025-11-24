<?php
@include 'config.php';
session_start();

$message = [];

if (isset($_POST['submit'])) {
    $email = trim($_POST['email']); 
    $pass = $_POST['pass'];

    if (empty($email) || empty($pass)) {
        $message[] = 'Vui lòng nhập email và mật khẩu!';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // 1. Kiểm tra mật khẩu (Hỗ trợ cả MD5 cũ và Plain text test)
            $password_matched = false;
            if (password_verify($pass, $row['password'])) {
                $password_matched = true; // Mật khẩu chuẩn (Hash)
            } elseif ($row['password'] === md5($pass)) {
                $password_matched = true; // Mật khẩu cũ (MD5)
            } elseif ($row['password'] === $pass) { 
                $password_matched = true; // Mật khẩu test (Plain text - vd: 123456)
            }

            if ($password_matched) {
                
                // 2. Kiểm tra quyền (Chỉ Admin hoặc Staff)
                if ($row['role'] === 'admin' || $row['role'] === 'staff') {
                    
                    // Thiết lập Session cơ bản
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['user_name'] = $row['name'];
                    $_SESSION['role'] = $row['role']; 
                    $_SESSION['user_image'] = $row['image'];

                    // 3. Nếu là Staff, lấy thêm employee_id
                    if ($row['role'] === 'staff') {
                        $stmt_staff = $conn->prepare("SELECT id FROM `employees` WHERE user_id = ?");
                        $stmt_staff->execute([$row['id']]);
                        if ($stmt_staff->rowCount() > 0) {
                            $staff_info = $stmt_staff->fetch(PDO::FETCH_ASSOC);
                            $_SESSION['employee_id'] = $staff_info['id']; 
                        }
                    }

                    // 4. Điều hướng
                    if ($row['role'] === 'admin') {
                        header('Location: admin_dashboard.php');
                        exit;
                    } elseif ($row['role'] === 'staff') {
                        // Chuyển hướng Staff đến trang chấm công hoặc dashboard của họ
                        header('Location: staff_dashboard.php'); 
                        exit;
                    }

                } else {
                    // Nếu là User thường -> Báo lỗi
                    $message[] = 'Tài khoản khách hàng không được truy cập trang này!';
                }

            } else {
                $message[] = 'Sai email hoặc mật khẩu!';
            }
        } else {
            $message[] = 'Sai email hoặc mật khẩu!';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <title>Admin Login</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/style.css">
   <style>
       /* Giao diện khác biệt một chút cho trang Admin */
       body { 
           background-color: #eee; 
           background-image: linear-gradient(45deg, #eee 25%, transparent 25%, transparent 75%, #eee 75%, #eee), linear-gradient(45deg, #eee 25%, transparent 25%, transparent 75%, #eee 75%, #eee);
           background-size: 20px 20px;
           background-position: 0 0, 10px 10px;
       }
       .form-container form h3 { color: var(--red); text-transform: uppercase; }
       .form-container form .btn { background-color: var(--red); }
       .form-container form .btn:hover { background-color: var(--black); }
   </style>
</head>
<body>

<?php if (!empty($message)): ?>
   <?php foreach ($message as $msg): ?>
      <div class="message">
         <span><?= htmlspecialchars($msg) ?></span>
         <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
      </div>
   <?php endforeach; ?>
<?php endif; ?>

<section class="form-container">
   <form action="" method="post">
      <h3>Admin / Staff Login</h3>
      <input type="email" name="email" class="box" placeholder="Enter admin email" required>
      <input type="password" name="pass" class="box" placeholder="Enter password" required>
      <input type="submit" class="btn" name="submit" value="Login to Dashboard">
      
      <p style="margin-top: 1rem; font-size: 1.4rem;">Are you a customer? <a href="login.php" style="color: var(--main-color);">Shop Login</a></p>
   </form>
</section>

</body>
</html>