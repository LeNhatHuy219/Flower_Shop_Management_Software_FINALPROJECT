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

            // Kiểm tra mật khẩu
            $password_matched = false;
            if (password_verify($pass, $row['password'])) {
                $password_matched = true;
            } elseif ($row['password'] === md5($pass)) {
                $password_matched = true;
            } elseif ($row['password'] === $pass) { 
                $password_matched = true;
            }

            if ($password_matched) {
                // --- LOGIC PHÂN QUYỀN MỚI ---
                // Chỉ cho phép role 'user' đăng nhập tại đây
                if ($row['role'] === 'user') {
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['user_name'] = $row['name'];
                    $_SESSION['role'] = $row['role']; 
                    $_SESSION['user_image'] = $row['image'];
                    
                    header('Location: home.php');
                    exit;
                } else {
                    // Nếu là Admin hoặc Staff mà đăng nhập ở đây -> Báo lỗi
                    $message[] = 'Trang này chỉ dành cho Khách hàng. Admin vui lòng truy cập trang quản trị!';
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
   <title>User Login</title>
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
      <h3>User Login</h3>
      <input type="email" name="email" class="box" placeholder="Enter your email" required>
      <input type="password" name="pass" class="box" placeholder="Enter your password" required>
      <input type="submit" class="btn" name="submit" value="Login Now">
      
      <p>Don't have an account? <a href="register.php">Register now</a></p>
      
      <p style="margin-top: 1rem; font-size: 1.4rem;">Are you an Admin? <a href="admin_login.php" style="color: var(--main-color);">Login here</a></p>
   </form>
</section>

</body>
</html>