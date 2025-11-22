-- ----------------------------------------------------------------
-- 1. TẠO DATABASE
-- ----------------------------------------------------------------
DROP DATABASE IF EXISTS flower_shop;
CREATE DATABASE flower_shop;
USE flower_shop;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ----------------------------------------------------------------
-- 2. TẠO CẤU TRÚC BẢNG (PHIÊN BẢN CUỐI CÙNG)
-- ----------------------------------------------------------------

--
-- Table structure for table `users`
--
CREATE TABLE `users` (
  `id` int(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin','staff') NOT NULL DEFAULT 'user',
  `image` varchar(100) NOT NULL DEFAULT 'default-avatar.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `categories`
--
CREATE TABLE `categories` (
  `id` int(100) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `products`
--
CREATE TABLE `products` (
  `id` int(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `details` varchar(500) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(100) NOT NULL DEFAULT 10,
  `category_id` int(100) DEFAULT NULL,
  `image` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `employees`
--
CREATE TABLE `employees` (
  `id` int(100) NOT NULL,
  `user_id` int(100) NOT NULL COMMENT 'Liên kết với Bảng `users`',
  `position` varchar(100) NOT NULL COMMENT 'Vị trí (vd: Florist, Delivery, Manager)',
  `hire_date` date NOT NULL,
  `salary` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `promotions`
-- (Đã bao gồm UNIQUE KEY `code` ngay khi tạo)
--
CREATE TABLE `promotions` (
  `id` int(100) NOT NULL,
  `code` varchar(50) NOT NULL UNIQUE COMMENT 'Mã giảm giá (vd: HELLO2025)',
  `description` text DEFAULT NULL,
  `discount_type` enum('percent','fixed') NOT NULL COMMENT 'Loại giảm: % hay tiền mặt',
  `discount_value` decimal(10,2) NOT NULL COMMENT 'Giá trị giảm',
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `orders`
--
CREATE TABLE `orders` (
  `id` int(100) NOT NULL,
  `user_id` int(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `number` varchar(12) NOT NULL,
  `email` varchar(100) NOT NULL,
  `method` varchar(50) NOT NULL,
  `address` varchar(500) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `placed_on` datetime NOT NULL DEFAULT current_timestamp(),
  `payment_status` varchar(20) NOT NULL DEFAULT 'pending',
  `delivery_status` enum('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `payment_id` varchar(255) DEFAULT NULL COMMENT 'Mã giao dịch từ cổng thanh toán',
  `invoice_url` varchar(255) DEFAULT NULL COMMENT 'Đường dẫn đến hóa đơn',
  `promotion_id` int(100) DEFAULT NULL COMMENT 'ID khuyến mãi đã dùng',
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Số tiền đã giảm',
  `points_spent` int(100) NOT NULL DEFAULT 0 COMMENT 'Số điểm thưởng đã dùng'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `order_details`
--
CREATE TABLE `order_details` (
  `id` int(100) NOT NULL,
  `order_id` int(100) NOT NULL COMMENT 'Liên kết tới bảng orders',
  `product_id` int(100) NOT NULL COMMENT 'Liên kết tới bảng products',
  `quantity` int(100) NOT NULL,
  `price_at_purchase` decimal(10,2) NOT NULL COMMENT 'Giá tại thời điểm mua'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `cart`
--
CREATE TABLE `cart` (
  `id` int(100) NOT NULL,
  `user_id` int(100) NOT NULL,
  `pid` int(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(100) NOT NULL,
  `image` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `message`
--
CREATE TABLE `message` (
  `id` int(100) NOT NULL,
  `user_id` int(100) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `number` varchar(12) NOT NULL,
  `message` varchar(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `reward_points_log`
--
CREATE TABLE `reward_points_log` (
  `id` int(100) NOT NULL,
  `user_id` int(100) NOT NULL,
  `order_id` int(100) DEFAULT NULL COMMENT 'Điểm từ đơn hàng nào',
  `points` int(100) NOT NULL COMMENT 'Số điểm (dương là +, âm là -)',
  `reason` varchar(255) NOT NULL COMMENT 'Lý do: Mua hàng, dùng điểm...',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `schedules`
--
CREATE TABLE `schedules` (
  `id` int(100) NOT NULL,
  `employee_id` int(100) NOT NULL,
  `shift_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `timesheets`
--
CREATE TABLE `timesheets` (
  `id` int(100) NOT NULL,
  `employee_id` int(100) NOT NULL,
  `clock_in` datetime NOT NULL,
  `clock_out` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ----------------------------------------------------------------
-- 3. CHÈN DỮ LIỆU (INSERT DATA)
-- ----------------------------------------------------------------

--
-- Dumping data for table `users`
--
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `image`) VALUES
(10, 'admin A', 'admin01@gmail.com', '123', 'admin', 'default-avatar.png'),
(12, 'staff A', 'staff01@gmail.com', '123456', 'staff', 'default-avatar.png'),
(14, 'user A', 'user01@gmail.com', '123', 'user', 'default-avatar.png'),
(15, 'user B', 'user02@gmail.com', '698d51a19d8a121ce5814997b701668', 'user', 'default-avatar.png');
  
--
-- Dumping data for table `categories`
--
INSERT INTO `categories` (`id`, `name`) VALUES
(1, 'Carnations'),
(2, 'Daisies'),
(3, 'Chrysanthemums'),
(4, 'Gerberas'),
(5, 'Sunflowers'),
(6, 'Iris'),
(7, 'Lisianthus'),
(8, 'Rose');

--
-- Dumping data for table `products`
--
INSERT INTO `products` (`id`, `name`, `details`, `price`, `stock`, `category_id`, `image`) VALUES
(1, 'Hoa Cẩm Chướng Trắng', 'Biểu trưng cho lòng biết ơn và tình yêu thương, thường tặng mẹ và thầy cô.', '15.00', 50, 5, 'hoa cẩm chướng 1.jpg'),
(2, 'Hoa Hồng', 'Biểu tượng tình yêu, đa dạng màu sắc, dễ phối; loại hoa phổ biến nhất trong các dịp tặng.', '20.00', 50, 8, 'hoa hồng 3.jpg'),
(3, 'Bó Cúc Họa Mi', 'Nhỏ, trắng tinh khôi, tượng trưng cho sự ngây thơ, trong sáng; nở rộ vào mùa thu Hà Nội.', '25.00', 50, 6, 'hoa cúc họa mi 2.jpg'),
(4, 'Bó Hoa Cát Tường Trắng', 'Dáng mềm mại, nhiều màu, biểu trưng cho tình yêu nhẹ nhàng và chân thành.', '45.00', 30, 3, 'hoa cát tường 3.jpg'),
(5, 'Hoa Cúc Mẫu Đơn Hồng Phai', 'Bông to, lâu tàn, tượng trưng cho sự trường thọ và cao quý.', '18.00', 50, 7, 'hoa cúc mẫu đơn 1.jpg'),
(6, 'Hoa Baby Xanh Nhạt', 'Hoa phụ nhẹ nhàng, thường kết hợp trong bó hoa cưới hoặc hoa tặng sinh nhật.', '50.00', 20, 3, 'hoa baby 1.jpg'),
(7, 'Hoa Đồng Tiền Hồng', 'Đại diện cho tài lộc và thành công, thường dùng trong dịp khai trương, chúc mừng.', '12.00', 50, 4, 'hoa đồng tiền 3.jpg'),
(8, 'Bình Hoa Diên Vĩ Tím', 'Hoa nhỏ, cánh sáp, bền màu; thường dùng làm nền trong các bó hoa hiện đại.', '55.00', 15, 6, 'hoa diên vĩ 1.jpg'),
(9, 'Hoa Hướng Dương Rực Rỡ', 'Màu vàng rực rỡ tượng trưng cho niềm tin, hy vọng và năng lượng tích cực.', '14.00', 50, 5, 'hoa hướng dương 2.jpg'),
(10, 'Bó Hồng Ecuador', 'Hoa hồng nhập khẩu cao cấp, bông cực to, cánh dày, thường dùng cho bó sang trọng.', '150.00', 10, 8, 'hoa hồng ecuador 1.jpg');

--
-- Dumping data for table `employees`
--
INSERT INTO `employees` (`id`, `user_id`, `position`, `hire_date`, `salary`) VALUES
(1, 12, 'Florist', '2025-01-15', '3000.00');

--
-- Dumping data for table `promotions`
--
INSERT INTO `promotions` (`id`, `code`, `description`, `discount_type`, `discount_value`, `start_date`, `end_date`, `is_active`) VALUES
(1, 'WELCOME10', 'Giảm 10% cho đơn hàng đầu tiên', 'percent', '10.00', '2025-01-01 00:00:00', '2025-12-31 23:59:59', 1),
(2, 'FREESHIP', 'Giảm 5$ (phí ship)', 'fixed', '5.00', '2025-11-01 00:00:00', '2025-11-30 23:59:59', 1),
(3, 'PASTPROMO', 'Khuyến mãi đã hết hạn', 'percent', '50.00', '2024-01-01 00:00:00', '2024-01-31 23:59:59', 0);

--
-- Dumping data for table `orders`
--
INSERT INTO `orders` (`id`, `user_id`, `name`, `number`, `email`, `method`, `address`, `total_price`, `placed_on`, `payment_status`, `delivery_status`) VALUES
(17, 14, 'shaikh anas', '0987654321', 'shaikh@gmail.com', 'credit card', 'flat no. 321, jogeshwari, mumbai, india - 654321', '80.00', '2022-03-11 00:00:00', 'pending', 'pending'),
(18, 14, 'shaikh anas', '1234567899', 'shaikh@gmail.com', 'paypal', 'flat no. 321, jogeshwari, mumbai, india - 654321', '40.00', '2022-03-11 00:00:00', 'completed', 'pending'),
(19, 14, 'user A', '0987654321', 'user01@gmail.com', 'credit card', 'flat no. 123, Binh Thanh, HCMC', '43.20', '2025-11-15 10:30:00', 'completed', 'delivered');

--
-- Dumping data for table `order_details`
--
INSERT INTO `order_details` (`id`, `order_id`, `product_id`, `quantity`, `price_at_purchase`) VALUES
(1, 19, 2, 1, '20.00'),
(2, 19, 9, 2, '14.00');

--
-- Dumping data for table `cart`
--
INSERT INTO `cart` (`id`, `user_id`, `pid`, `name`, `price`, `quantity`, `image`) VALUES
(129, 14, 16, 'lavendor rose', '13.00', 1, 'lavendor rose.jpg'),
(130, 14, 18, 'red tulipa', '11.00', 1, 'red tulipa.jpg'),
(131, 14, 15, 'cottage rose', '15.00', 1, 'cottage rose.jpg'),
(132, 15, 13, 'pink rose', '10.00', 1, 'pink roses.jpg'),
(133, 15, 15, 'cottage rose', '15.00', 1, 'cottage rose.jpg'),
(134, 15, 16, 'lavendor rose', '13.00', 3, 'lavendor rose.jpg');

--
-- Dumping data for table `message`
--
INSERT INTO `message` (`id`, `user_id`, `name`, `email`, `number`, `message`) VALUES
(13, 14, 'shaikh anas', 'shaikh@gmail.com', '0987654321', 'hi, how are you?');

--
-- Dumping data for table `reward_points_log`
--
INSERT INTO `reward_points_log` (`id`, `user_id`, `order_id`, `points`, `reason`) VALUES
(1, 14, NULL, 150, 'Sign-up bonus');

--
-- Dumping data for table `schedules`
--
INSERT INTO `schedules` (`id`, `employee_id`, `shift_date`, `start_time`, `end_time`) VALUES
(1, 1, '2025-11-16', '09:00:00', '17:00:00'),
(2, 1, '2025-11-17', '09:00:00', '13:00:00'),
(3, 1, '2025-11-18', '13:00:00', '21:00:00');

--
-- Dumping data for table `timesheets`
--
INSERT INTO `timesheets` (`id`, `employee_id`, `clock_in`, `clock_out`) VALUES
(1, 1, '2025-11-15 09:01:15', '2025-11-15 17:03:30'),
(2, 1, '2025-11-14 09:00:30', '2025-11-14 17:01:05');

--
-- Dumping data for table `wishlist`
--


-- ----------------------------------------------------------------
-- 4. CHỈ MỤC (INDEXES) VÀ AUTO_INCREMENT
-- ----------------------------------------------------------------

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`);
  -- (Dòng UNIQUE KEY `code` đã bị xóa vì nó được tạo trong CREATE TABLE)

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_order_promo` (`promotion_id`);

--
-- Indexes for table `order_details`
--
ALTER TABLE `order_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `pid` (`pid`);

--
-- Indexes for table `message`
--
ALTER TABLE `message`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `reward_points_log`
--
ALTER TABLE `reward_points_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `timesheets`
--
ALTER TABLE `timesheets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `wishlist`
--


--
-- AUTO_INCREMENT cho các bảng
--
ALTER TABLE `users`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;
ALTER TABLE `categories`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
ALTER TABLE `products`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
ALTER TABLE `employees`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `promotions`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `orders`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;
ALTER TABLE `order_details`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `cart`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=135;
ALTER TABLE `message`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;
ALTER TABLE `reward_points_log`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `schedules`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `timesheets`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;


-- ----------------------------------------------------------------
-- 5. RÀNG BUỘC KHÓA NGOẠI (FOREIGN KEY CONSTRAINTS)
-- ----------------------------------------------------------------

ALTER TABLE `employees`
  ADD CONSTRAINT `fk_employees_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `order_details`
  ADD CONSTRAINT `fk_order_details_orders` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_details_products` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT;

ALTER TABLE `orders`
  ADD CONSTRAINT `fk_order_promo` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

ALTER TABLE `reward_points_log`
  ADD CONSTRAINT `fk_points_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `schedules`
  ADD CONSTRAINT `fk_schedule_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

ALTER TABLE `timesheets`
  ADD CONSTRAINT `fk_timesheet_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

ALTER TABLE `users`
ADD COLUMN `phone` VARCHAR(20) NULL DEFAULT NULL
AFTER `email`;
COMMIT;