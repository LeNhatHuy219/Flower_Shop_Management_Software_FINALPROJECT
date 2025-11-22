<?php

@include 'config.php';

session_start();

$user_id = $_SESSION['user_id'];

if(!isset($user_id)){
   header('location:login.php');
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>about</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/style.css">

   <style>
    .reviews .box .avatar {
        height: 7rem;
        width: 7rem;
        border-radius: 50%;
        object-fit: cover;
        border: var(--border);
        margin-bottom: 1rem;
    }
   </style>
</head>
<body>
   
<?php @include 'header.php'; ?>

<section class="heading">
    <h3>about us</h3>
    <p> <a href="home.php">home</a> / about </p>
</section>

<section class="about">
    <div class="flex">
        <div class="image">
            <img src="images/about-img-1.png" alt="">
        </div>
        <div class="content">
            <h3>why choose us?</h3>
            <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Eum odit voluptatum alias sed est in magni nihil nisi deleniti nostrum.</p>
            <a href="shop.php" class="btn">shop now</a>
        </div>
    </div>
    <div class="flex">
        <div class="content">
            <h3>what we provide?</h3>
            <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Eum odit voluptatum alias sed est in magni nihil nisi deleniti nostrum.</p>
            <a href="contact.php" class="btn">contact us</a>
        </div>
        <div class="image">
            <img src="images/about-img-2.jpg" alt="">
        </div>
    </div>
    <div class="flex">
        <div class="image">
            <img src="images/about-img-3.jpg" alt="">
        </div>
        <div class="content">
            <h3>who we are?</h3>
            <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Eum odit voluptatum alias sed est in magni nihil nisi deleniti nostrum.</p>
            <a href="#reviews" class="btn">clients reviews</a>
        </div>
    </div>
</section>

<section class="reviews" id="reviews">

    <h1 class="title">client's reviews</h1>

    <div class="box-container">

    <?php
        // (SỬA) 3: Truy vấn CSDL (Dùng LEFT JOIN)
        // Lấy tin nhắn (m) và ẢNH (u.image) từ user (u)
        $select_messages = $conn->prepare(
            "SELECT m.message, m.name, u.image 
             FROM `message` m
             LEFT JOIN `users` u ON m.user_id = u.id
             ORDER BY m.id DESC LIMIT 6"
        );
        $select_messages->execute();
        
        if($select_messages->rowCount() > 0){
            while($fetch_message = $select_messages->fetch(PDO::FETCH_ASSOC)){
                
                // (MỚI) 4: Xử lý ảnh (dùng ảnh default nếu là khách)
                // Đảm bảo bạn có 'default-avatar.png' trong 'avatars/'
                $avatar_image = $fetch_message['image'] ?? 'default-avatar.png';
                if(empty($avatar_image)) {
                    $avatar_image = 'default-avatar.png';
                }
    ?>
    
    <div class="box">
        <h3><?php echo htmlspecialchars($fetch_message['name']); ?></h3>
        <img src="avatars/<?php echo htmlspecialchars($avatar_image); ?>" alt="avatar" class="avatar">
        
        <p><?php echo htmlspecialchars($fetch_message['message']); ?></p>
    </div>
    
    <?php
            } // Kết thúc vòng lặp while
        } else {
            echo '<p class="empty">no reviews posted yet!</p>';
        }
    ?>
    </div>

</section>

<?php @include 'footer.php'; ?>

<script src="js/script.js"></script>

</body>
</html>