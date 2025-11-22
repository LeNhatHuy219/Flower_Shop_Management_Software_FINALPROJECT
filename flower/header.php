<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
@include_once 'config.php'; 

$user_id = $_SESSION['user_id'] ?? null;
$is_logged_in = isset($user_id); 

// (Logic lấy wishlist/cart/points giữ nguyên)


$cart_num_rows = 0;
$total_reward_points = 0; 
if($is_logged_in){
   
    
    $stmt_cart = $conn->prepare("SELECT COUNT(*) FROM `cart` WHERE user_id = ?"); 
    $stmt_cart->execute([$user_id]);
    $cart_num_rows = $stmt_cart->fetchColumn();
    $stmt_points = $conn->prepare("SELECT SUM(points) FROM `reward_points_log` WHERE user_id = ?");
    $stmt_points->execute([$user_id]);
    $total_reward_points = $stmt_points->fetchColumn() ?? 0; 
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="css/style.css">

<style>
.account-box {
    text-align: center; 
}

/* (MỚI) Container cho avatar và nút */
.account-box .avatar-container {
    position: relative; /* Quan trọng để định vị */
    width: 10rem; /* Kích thước lớn hơn 1 chút */
    height: 10rem;
    border-radius: 50%;
    margin: 0 auto 1rem auto; /* Căn giữa */
    overflow: hidden; /* Ẩn các phần thừa */
    border: var(--border);
}

.account-box .avatar {
    height: 100%; 
    width: 100%;
    object-fit: cover;
    transition: opacity .2s linear;
}

/* (MỚI) Nút "Change Avatar" (nằm đè lên ảnh) */
.account-box .change-avatar-btn {
    position: absolute;
    top: 0; left: 0;
    height: 100%;
    width: 100%;
    
    /* Ẩn mặc định */
    opacity: 0;
    visibility: hidden;
    
    /* Căn giữa chữ */
    display: flex;
    align-items: center;
    justify-content: center;
    
    /* Hiệu ứng mờ */
    background-color: rgba(0, 0, 0, 0.5);
    color: var(--white);
    font-size: 1.4rem;
    font-weight: bold;
    text-decoration: none;
    
    transition: opacity .2s linear;
}

/* (MỚI) Khi hover vào container, hiển thị nút */
.account-box .avatar-container:hover .change-avatar-btn {
    opacity: 1;
    visibility: visible;
}
/* (MỚI) Khi hover, avatar mờ đi (tùy chọn, vì đã có nền đen) */
.account-box .avatar-container:hover .avatar {
    opacity: 0.7;
}
</style>

<header class="header">
    <div class="flex">
        <a href="home.php" class="logo">flowers.</a>
        
        <nav class="navbar">
            <ul>
                <li><a href="home.php">Home</a></li> 
                <li><a href="shop.php">Shop</a></li>
                <li><a href="about.php">About</a></li>
                <li><a href="contact.php">Contact</a></li>
                <?php if($is_logged_in): ?>
                    <li><a href="orders.php">My Orders</a></li>
                <?php endif; ?>
                
                
            </ul>
        </nav>
        
        <div class="icons">
            <div id="menu-btn" class="fas fa-bars"></div>
            <?php if($is_logged_in): ?>
       
                
                <a href="cart.php"><i class="fas fa-shopping-cart"></i><span>(<?php echo $cart_num_rows; ?>)</span></a>
            <?php endif; ?>
            <div id="user-btn" class="fas fa-user"></div>
        </div>

        <div class="account-box">
            <?php if($is_logged_in): ?>
                
                <div class="avatar-container">
                    <img src="avatars/<?php echo htmlspecialchars($_SESSION['user_image']); ?>" alt="avatar" class="avatar">
                    <a href="update_profile.php" class="change-avatar-btn">Change Avatar</a>
                </div>
                
                <p>username : <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span></p>
                <p>points : <span><?php echo $total_reward_points; ?></span></p>
                <a href="logout.php" class="delete-btn">Logout</a>
                
            <?php else: ?>
                <p>username : <span>Guest</span></p>
                <p>You are not logged in.</p>
                <a href="login.php" class="btn">Login</a>
                <div style="margin-top: 1rem; font-size: 1.5rem; color: var(--light-color);">
                    New here? <a href="register.php" style="text-decoration: underline;">Register now</a>
                </div>
            <?php endif; ?>
        </div>

    </div>
</header>