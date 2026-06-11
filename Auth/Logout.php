<?php
session_start();

// --- ĐOẠN CHÈN MỚI: CẬP NHẬT TRẠNG THÁI OFFLINE VÀO DATABASE ---
if (isset($_SESSION['UserLoggedIn']) && $_SESSION['UserLoggedIn'] === true) {
    try {
        // Tự khởi tạo kết nối database bằng PDO đồng bộ với cấu trúc hệ thống của bạn
        $conn = new PDO("mysql:host=localhost;dbname=devmaster;charset=utf8mb4", "root", "");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        if (isset($_SESSION['IsAdmin']) && $_SESSION['IsAdmin'] === true) {
            // Nếu là Admin thì cập nhật bảng quantriadmin thành 'off'
            $updateAdminOfflineSql = "UPDATE quantriadmin SET TrangThai = 'off' WHERE AdminId = ?";
            $stmtAdminOffline = $conn->prepare($updateAdminOfflineSql);
            $stmtAdminOffline->execute([$_SESSION['UserId']]);
        } else {
            // Nếu là học viên thường thì giữ nguyên logic cũ
            $updateOfflineSql = "UPDATE dangky SET TrangThai = NULL WHERE STT = ?";
            $stmtOffline = $conn->prepare($updateOfflineSql);
            $stmtOffline->execute([$_SESSION['UserId']]);
        }
    } catch (PDOException $e) {
        // Bỏ qua lỗi kết nối nếu có để không làm gián đoạn quá trình đăng xuất của user
    }
}

// Xóa bỏ cờ hoạt động trước khi xóa toàn bộ session
if(isset($_SESSION['UserActiveName'])) {
    unset($_SESSION['UserActiveName']);
}
session_unset();
session_destroy();
header("Location: ../index.php");
exit();
?>
