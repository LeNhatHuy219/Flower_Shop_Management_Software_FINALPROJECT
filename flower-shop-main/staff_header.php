
<header class="sidebar">

    <a href="staff_dashboard.php" class="logo">Admin<span>Panel</span></a>

    <nav class="navbar">
        <a href="staff_dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="staff_products.php"><i class="fas fa-box"></i> <span>Products</span></a>
        <a href="staff_orders.php"><i class="fas fa-shopping-cart"></i> <span>Orders</span></a>
        <a href="staff_users.php"><i class="fas fa-users"></i> <span>Users</span></a>
        <a href="staff_timesheet.php"><i class="fas fa-calendar-alt"></i> <span>TimeSheet</span></a>
        <a href="staff_contacts.php"><i class="fas fa-envelope"></i> <span>Messages</span></a>
    </nav>
   
    <div class="account-box">
        <div class="avatar-container">
            <img src="avatars/<?php echo htmlspecialchars($_SESSION['user_image'] ?? 'default-avatar.png'); ?>" alt="avatar" class="avatar">
            <a href="staff_update_profile.php" class="change-avatar-btn">Change Avatar</a>
        </div>
        <p>
            <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
            (<?php echo htmlspecialchars($_SESSION['role'] ?? 'Guest'); ?>)
        </p>
        <a href="logout.php" class="delete-btn" style="margin-top: 1rem; width: 100%;">Logout</a>
    </div>

</header>

<div id="menu-btn-mobile" class="fas fa-bars"></div>