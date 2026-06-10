<?php
// Admin/XuLyThemAdmin.php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
include '../Database.php';
$db = isset($connect) ? $connect : $conn;

if (!isset($_SESSION['IsAdmin']) || $_SESSION['IsAdmin'] !== true) {
    header("Location: /DevMaster/Pages/Login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenAdmin = trim($_POST['txtTenAdmin'] ?? '');
    $gmail = trim($_POST['txtGmail'] ?? '');
    $matKhau = trim($_POST['txtMatKhau'] ?? '');
    $viTri = isset($_POST['txtViTri']) ? (int)$_POST['txtViTri'] : 0;
    
    if (empty($tenAdmin) || empty($gmail) || empty($matKhau)) {
        echo "<script>alert('Vui lòng điền đầy đủ thông tin!'); window.history.back();</script>";
        exit;
    }

    // Kiểm tra trùng lặp Gmail trước khi chèn mới để tránh lỗi Database
    try {
        $checkDup = "SELECT AdminId FROM quantriadmin WHERE Gmail = ?";
        $exists = false;
        if ($db instanceof PDO) {
            $st = $db->prepare($checkDup); $st->execute([$gmail]);
            if ($st->fetch()) $exists = true;
        } else {
            $st = $db->prepare($checkDup); $st->bind_param("s", $gmail); $st->execute();
            if ($st->get_result()->fetch_assoc()) $exists = true;
        }
        
        if ($exists) {
            echo "<script>alert('Địa chỉ Gmail này đã tồn tại trong ban quản trị!'); window.history.back();</script>";
            exit;
        }

        // Thực hiện thêm mới, mặc định tài khoản mới tạo sẽ có TrangThai = 'off'
        $insertQuery = "INSERT INTO quantriadmin (TenAdmin, Gmail, MatKhau, TrangThai, ViTri) VALUES (?, ?, ?, 'off', ?)";
        if ($db instanceof PDO) {
            $stmt = $db->prepare($insertQuery);
            $stmt->execute([$tenAdmin, $gmail, $matKhau, $viTri]);
        } else {
            $stmt = $db->prepare($insertQuery);
            $stmt->bind_param("sssi", $tenAdmin, $gmail, $matKhau, $viTri);
            $stmt->execute();
        }

        echo "<script>alert('Khởi tạo tài khoản quản trị mới thành công!'); window.location.href='QuanTriVien.php';</script>";
    } catch (Exception $e) {
        echo "<script>alert('Lỗi: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }
}
?>