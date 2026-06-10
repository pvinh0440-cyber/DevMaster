<?php
// includes/Database.php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // Thay đổi nếu user của bạn khác
define('DB_PASS', '');          // Thay đổi nếu mật khẩu của bạn khác
define('DB_NAME', 'devmaster'); // Tên database theo ảnh phpMyAdmin của bạn

try {
    // Khởi tạo kết nối PDO với cấu hình UTF-8 chống lỗi font Tiếng Việt
    $connect = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4")
    );
    
    // Thiết lập chế độ báo lỗi ngoại lệ để dễ dàng debug
    $connect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Thiết lập kiểu trả về dữ liệu mặc định là Mảng kết hợp (Associative Array)
    $connect->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Ngăn chặn rò rỉ thông tin hệ thống khi lỗi kết nối xảy ra
    die("Lỗi kết nối cơ sở dữ liệu hệ thống: " . $e->getMessage());
}
?>