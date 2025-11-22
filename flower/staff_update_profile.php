<?php
@include 'config.php';
session_start();
$user_id = $_SESSION['user_id'];

if(!isset($user_id)){
   header('location:login.php');
   exit();
}

// (Xử lý PHP update_profile - Giữ nguyên)
if(isset($_POST['update_profile'])){

    $update_name = $_POST['update_name'];
    
    $stmt_update_name = $conn->prepare("UPDATE `users` SET name = ? WHERE id = ?");
    $stmt_update_name->execute([$update_name, $user_id]);
    
    $_SESSION['user_name'] = $update_name;
    $message[] = 'Tên đã được cập nhật!';

    $update_image = $_FILES['update_image']['name'];
    $update_image_size = $_FILES['update_image']['size'];
    $update_image_tmp_name = $_FILES['update_image']['tmp_name'];
    $update_image_folder = 'avatars/'; 
    $update_image_path = $update_image_folder . $update_image;

    $old_image = $_SESSION['user_image']; 

    if(!empty($update_image)){
        if($update_image_size > 2000000){
            $message[] = 'Ảnh quá lớn, vui lòng chọn ảnh < 2MB.';
        } else {
            $image_update_query = $conn->prepare("UPDATE `users` SET image = ? WHERE id = ?");
            $image_update_query->execute([$update_image, $user_id]);
            
            if($image_update_query){
                move_uploaded_file($update_image_tmp_name, $update_image_path);
                if($old_image != 'default-avatar.png' && file_exists($update_image_folder . $old_image)){
                    unlink($update_image_folder . $old_image);
                }
                $_SESSION['user_image'] = $update_image;
                $message[] = 'Avatar đã được cập nhật!';
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
   <title>Update Profile</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/style.css">
   
   <style>
    .update-profile-container {
        display: flex;
        justify-content: center;
        padding: 2rem;
    }
    .update-profile-container form {
        width: 100%;
        max-width: 50rem;
        padding: 2rem;
        background-color: var(--white);
        border: var(--border);
        border-radius: .5rem;
        box-shadow: var(--box-shadow);
        text-align: center; /* Căn giữa avatar và h3 */
    }
    .update-profile-container form .avatar {
        height: 15rem; 
        width: 15rem;
        border-radius: 50%;
        object-fit: cover;
        margin-bottom: .5rem; /* Giảm margin */
        border: var(--border);
    }
    .update-profile-container form h3 {
        font-size: 2rem;
        color: var(--black);
        margin-bottom: 2rem; /* Thêm margin dưới tên */
    }
    .update-profile-container form .inputBox {
        text-align: left; /* Căn trái các label */
        margin-bottom: 1.5rem;
    }
    .update-profile-container form .inputBox span {
        font-size: 1.6rem;
        color: var(--light-color);
        display: block; /* Cho label nằm trên */
        margin-bottom: .5rem;
    }
    .update-profile-container form .inputBox .box {
        width: 100%;
    }
    /* (MỚI) CSS cho 2 nút bấm */
    .update-profile-container form .button-container {
        display: flex;
        justify-content: center;
        gap: 1rem;
        margin-top: 2rem;
    }
    /* Ghi đè style mặc định của .btn và .option-btn */
    .update-profile-container form .button-container .btn,
    .update-profile-container form .button-container .option-btn {
        margin-top: 0; 
        width: 48%; /* Chia đôi không gian */
    }
   </style>
</head>
<body>

<?php @include 'staff_header.php'; ?>
<link rel="stylesheet" href="css/admin_style.css">
<section class="heading">
    <h3>Update Profile</h3>
</section>

<section class="update-profile-container">

    <form action="" method="post" enctype="multipart/form-data">
        
        <img src="avatars/<?php echo htmlspecialchars($_SESSION['user_image']); ?>" alt="avatar" class="avatar">
        
        <h3><?php echo htmlspecialchars($_SESSION['user_name']); ?></h3>

        <div class="inputBox">
            <span>Update Your Name :</span>
            <input type="text" name="update_name" value="<?php echo htmlspecialchars($_SESSION['user_name']); ?>" class="box" required>
        </div>
        
        <div class="inputBox">
            <span>Update Your Avatar :</span>
            <input type="file" name="update_image" class="box" accept="image/jpg, image/jpeg, image/png">
        </div>
        
        <div class="button-container">
            <input type="submit" value="Update Profile" name="update_profile" class="btn">
            <a href="staff_dashboard.php" class="option-btn">Go Back</a>
        </div>
        
    </form>

</section>



<script src="js/script.js"></script>

</body>
</html>