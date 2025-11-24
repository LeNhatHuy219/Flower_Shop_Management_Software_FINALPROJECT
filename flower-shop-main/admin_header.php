
<header class="sidebar">

    <a href="admin_dashboard.php" class="logo">Admin<span>Panel</span></a>

    <nav class="navbar">
        <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="admin_products.php"><i class="fas fa-box"></i> <span>Products</span></a>
        <a href="admin_orders.php"><i class="fas fa-shopping-cart"></i> <span>Orders</span></a>
        <a href="admin_users.php"><i class="fas fa-users"></i> <span>Users</span></a>
        <a href="admin_promotion.php"><i class="fas fa-gift"></i> <span>Promotion</span></a>
        <a href="admin_schedule.php"><i class="fas fa-calendar-alt"></i> <span>Schedule</span></a>
        <a href="admin_contacts.php"><i class="fas fa-envelope"></i> <span>Messages</span></a>
    </nav>
   
    <div class="account-box">
        <div class="avatar-container">
            <img src="avatars/<?php echo htmlspecialchars($_SESSION['user_image'] ?? 'default-avatar.png'); ?>" alt="avatar" class="avatar">
            <a href="admin_update_profile.php" class="change-avatar-btn">Change Avatar</a>
        </div>
        <p>
            <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
            (<?php echo htmlspecialchars($_SESSION['role'] ?? 'Guest'); ?>)
        </p>
        <a href="logout.php" class="delete-btn" style="margin-top: 1rem; width: 100%;">Logout</a>
    </div>

</header>

<div id="menu-btn-mobile" class="fas fa-bars"></div>