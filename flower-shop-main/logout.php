<?php
@include 'config.php';

session_start();

// 1. Xác định trang đích mặc định (cho User)
$redirect_url = 'login.php'; 

// 2. Kiểm tra quyền TRƯỚC KHI hủy session
if (isset($_SESSION['role'])) {
    // Nếu là Admin hoặc Staff -> Về trang đăng nhập quản trị
    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'staff') {
        $redirect_url = 'admin_login.php';
    }
    // Nếu là User -> Giữ nguyên mặc định là 'login.php'
}

// 3. Xóa sạch session (Thực hiện đăng xuất)
session_unset();
session_destroy();

// 4. Chuyển hướng đến trang đã xác định
header('location:' . $redirect_url);

?>