<?php
$localhost = "localhost";
$db_user = "root";
$db_password = "";
$db_name = "flower_shop";

try {
    // Kết nối tới MySQL bằng PDO
    $conn = new PDO("mysql:host=$localhost;dbname=$db_name;charset=utf8", $db_user, $db_password);

    // Thiết lập chế độ báo lỗi
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // (Tùy chọn) Tự động trả kết quả dưới dạng associative array
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // echo "✅ Connected successfully";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
