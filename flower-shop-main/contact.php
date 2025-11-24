<?php
@include 'config.php';
session_start();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? null;
if(!isset($user_id) || $role != 'user'){
   header('location:login.php');
   exit();
}

// (SỬA) 1: Đổi tên biến $message để header không bắt được
$contact_message = []; // Dùng biến riêng

if(isset($_POST['send'])){

    $msg = $_POST['message'];

    if(empty($msg)){
        // (SỬA) 1: Đổi tên biến
        $contact_message[] = 'Please enter a message!';
    } else {
        $stmt_get_user = $conn->prepare("SELECT name, email, phone FROM `users` WHERE id = ?");
        $stmt_get_user->execute([$user_id]);
        $user_info = $stmt_get_user->fetch(PDO::FETCH_ASSOC);
        
        if($user_info){
            $name = $user_info['name'];
            $email = $user_info['email'];
            $number = $user_info['phone'] ?? 'N/A'; 

            $stmt_insert = $conn->prepare("INSERT INTO `message`(user_id, name, email, number, message) VALUES(?,?,?,?,?)");
            $stmt_insert->execute([$user_id, $name, $email, $number, $msg]);
            
            // (SỬA) 1: Đổi tên biến
            $contact_message[] = 'Message sent successfully!';
        } else {
            // (SỬA) 1: Đổi tên biến
            $contact_message[] = 'Could not find user information.';
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
   <title>contact</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/style.css">

</head>
<body>
   
<?php @include 'header.php'; ?> <section class="heading">
    <h3>contact us</h3>
    <p> <a href="home.php">home</a> / contact </p>
</section>

<section class="contact">
    <form action="" method="POST">
        <h3>send us message!</h3>
        <textarea name="message" class="box" placeholder="enter your message" required cols="30" rows="10"></textarea>
        <input type="submit" value="send message" name="send" class="btn">
    </form>
</section>


<?php @include 'footer.php'; ?>

<div id="contact-success-modal" class="contact-modal">
    <div class="contact-modal-content">
        <span class="contact-modal-close">&times;</span>
        <p>
        <?php 
            if(!empty($contact_message)){
                echo $contact_message[0]; // Hiển thị tin nhắn đầu tiên
            }
        ?>
        </p>
        <a href="contact.php" class="btn">OK</a>
    </div>
</div>

<script src="js/script.js"></script>

<?php
// Nếu có thông báo, in ra script để hiển thị modal
if(!empty($contact_message)){
    echo "
    <script>
        var modal = document.getElementById('contact-success-modal');
        var closeBtn = modal.querySelector('.contact-modal-close');
        
        // Hiển thị modal
        modal.style.display = 'block';

        // Đóng khi bấm (x)
        closeBtn.onclick = function() {
            modal.style.display = 'none';
        }

        // Đóng khi bấm ra ngoài
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
    ";
}
?>

</body>
</html>