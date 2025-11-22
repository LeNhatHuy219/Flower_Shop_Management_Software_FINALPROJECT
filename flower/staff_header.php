<?php
if(isset($message)){
   foreach($message as $message){
      echo '
      <div class="message">
         <span>'.$message.'</span>
         <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
      </div>
      ';
   }

   if (isset($_POST['update_profile'])) {

    $new_name = $_POST['update_name'];

    // Avatar mới
    $avatar_name = $_FILES['update_avatar']['name'];
    $avatar_tmp = $_FILES['update_avatar']['tmp_name'];

    // Nếu có upload avatar
    if (!empty($avatar_name)) {

        $ext = pathinfo($avatar_name, PATHINFO_EXTENSION);
        $new_avatar = "avatar_" . $admin_id . "." . $ext;

        // Lưu file
        move_uploaded_file($avatar_tmp, "uploaded_img/" . $new_avatar);

        // Query update có avatar
        $sql = "UPDATE users SET name = :name, avatar = :avatar WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':name', $new_name);
        $stmt->bindParam(':avatar', $new_avatar);
        $stmt->bindParam(':id', $admin_id, PDO::PARAM_INT);

    } else {
        // Không đổi avatar
        $sql = "UPDATE users SET name = :name WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':name', $new_name);
        $stmt->bindParam(':id', $admin_id, PDO::PARAM_INT);
    }

    $stmt->execute();

    header("Location: profile.php");
    exit();
}
}


?>

<header class="sidebar">

    <a href="staff_dashboard.php" class="logo">Staff<span>Panel</span></a>

    <nav class="navbar">
        <a href="staff_dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="staff_products.php"><i class="fas fa-box"></i> <span>Products</span></a>
        <a href="staff_orders.php"><i class="fas fa-shopping-cart"></i> <span>Orders</span></a>
        <a href="staff_users.php"><i class="fas fa-users"></i> <span>Users</span></a>
        <a href="staff_timesheet.php"><i class="fas fa-clock"></i> <span>Timesheet</span></a>
        <a href="staff_payroll.php"><i class="fas fa-dollar-sign"></i> <span>Payroll</span></a>
        <a href="staff_contacts.php"><i class="fas fa-envelope"></i> <span>Messages</span></a>
    </nav>
   
    <div class="account-box">
        <div class="avatar-container">
            <img src="avatars/<?php echo htmlspecialchars($_SESSION['user_image'] ?? 'default-avatar.png'); ?>" alt="avatar" class="avatar">
            <a href="staff_update_profile.php" class="change-avatar-btn">Change Avatar</a>
        </div>
        <p>
            <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Staff'); ?></span>
            (<?php echo htmlspecialchars($_SESSION['role'] ?? 'Guest'); ?>)
        </p>
        <a href="logout.php" class="delete-btn" style="margin-top: 1rem; width: 100%;">Logout</a>
    </div>

</header>


<div id="menu-btn-mobile" class="fas fa-bars"></div>