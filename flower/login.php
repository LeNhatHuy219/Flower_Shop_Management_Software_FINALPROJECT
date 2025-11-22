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

            // Logic kiểm tra mật khẩu linh hoạt
            $password_matched = false;
            if (password_verify($pass, $row['password'])) {
                $password_matched = true;
            } elseif ($row['password'] === md5($pass)) {
                $password_matched = true;
            } elseif ($row['password'] === $pass) { // Chấp nhận mật khẩu '123'
                $password_matched = true;
            }

            if ($password_matched) {
                // (SỬA) 1: Đọc từ 'user_type' và lưu vào 'user_type'
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_name'] = $row['name'];
                $_SESSION['role'] = $row['role']; 
                $_SESSION['user_image'] = $row['image'];

                // (SỬA) 2: Kiểm tra 'user_type' (từ CSDL), không phải 'role'
                if ($row['role'] === 'user') {
                    header('Location: home.php'); // Đi đến trang chủ
                    exit;
                } 
                // (SỬA) 3: Kiểm tra 'user_type'
                elseif ($row['role'] === 'admin') {
                    
                    // Thử tìm thông tin nhân viên (không bắt buộc)
                    $stmt_staff = $conn->prepare("SELECT * FROM `employees` WHERE user_id = ?");
                    $stmt_staff->execute([$row['id']]);
                    if ($stmt_staff->rowCount() > 0) {
                        $staff_info = $stmt_staff->fetch(PDO::FETCH_ASSOC);
                        $_SESSION['employee_id'] = $staff_info['id']; 
                    }
                    
                    header('Location: admin_dashboard.php'); // Đi đến trang admin
                    exit;

                } elseif ($row['role'] === 'staff') {
                    header('Location: staff_dashboard.php'); // Đi đến trang staff
                    exit;
                } else {
                    $message[] = 'Không rõ loại tài khoản!';
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
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Login</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/style.css">
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
      <h3>Login Now</h3>
      <input type="email" name="email" class="box" placeholder="Enter your email" required>
      <input type="password" name="pass" class="box" placeholder="Enter your password" required>
      <input type="submit" class="btn" name="submit" value="Login Now">
      <p>Don't have an account? <a href="register.php">Register now</a></p>
   </form>
</section>
</body>
</html>