<?php
@include 'config.php';
session_start();

$message = [];

if(isset($_POST['submit'])){
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone']; 
    $pass = $_POST['pass'];
    $cpass = $_POST['cpass'];

    if($pass != $cpass){
        $message[] = 'Confirm password not matched!';
    } else {
        $stmt_check = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt_check->execute([$email]);

        if($stmt_check->rowCount() > 0){
            $message[] = 'Email already exists!';
        } else {
            try {
                $hashed_pass = password_hash($pass, PASSWORD_BCRYPT);
                $role = 'user'; // Sửa từ 'role'
                
                // ===========================================
                // (SỬA) ĐỔI THÀNH THƯ MỤC 'avatars/'
                // ===========================================
                $image_dir = 'avatars/'; // Thư mục chứa ảnh avatar
                
                $images = glob($image_dir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
                
                // Đảm bảo bạn có ảnh 'default-avatar.jpg' trong thư mục 'avatars/'
                $random_image_name = 'default-avatar.jpg'; 
                
                if (!empty($images)) {
                    $random_image_path = $images[array_rand($images)];
                    $random_image_name = basename($random_image_path);
                }
                // ===========================================
                
                $stmt_register = $conn->prepare("INSERT INTO users (name, email, phone, password, role, image) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_register->execute([$name, $email, $phone, $hashed_pass, $role, $random_image_name]);
                
                $message[] = 'Registered successfully! Please login.';
                header('location:login.php');
                exit();

            } catch (Exception $e) {
                $message[] = 'Registration failed: ' . $e->getMessage();
            }
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
   <title>Register</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php
if(isset($message)){
   foreach($message as $msg){
      echo '
      <div class="message">
         <span>'.htmlspecialchars($msg).'</span>
         <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
      </div>
      ';
   }
}
?>
<section class="form-container">
   <form action="" method="post">
    <h3>Register Now</h3>
    <input type="text" name="name" class="box" placeholder="Enter your full name" required>
    <input type="email" name="email" class="box" placeholder="Enter your email" required>
    <input type="tel" name="phone" class="box" placeholder="Enter your phone" required> 
    <input type="password" name="pass" class="box" placeholder="Enter your password" required>
    <input type="password" name="cpass" class="box" placeholder="Confirm your password" required>
    <input type="submit" class="btn" name="submit" value="register now">
    <p>Already have an account? <a href="login.php">Login now</a></p>
</form>

</section>
</body>
</html>