<?php
// Admin/XuLySuaAdmin.php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
include '../Database.php';
$db = isset($connect) ? $connect : $conn;

// Khóa bảo mật: Phải đăng nhập admin mới được thao tác
if (!isset($_SESSION['IsAdmin']) || $_SESSION['IsAdmin'] !== true) {
    header("Location: /DevMaster/Pages/Login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminId = isset($_POST['txtAdminId']) ? (int)$_POST['txtAdminId'] : 0;
    $tenAdmin = trim($_POST['txtTenAdmin'] ?? '');
    $gmail = trim($_POST['txtGmail'] ?? '');
    $matKhau = trim($_POST['txtMatKhau'] ?? '');
    
    if ($adminId <= 0 || empty($tenAdmin) || empty($gmail) || empty($matKhau)) {
        echo "<script>alert('Vui lòng điền đầy đủ thông tin!'); window.history.back();</script>";
        exit;
    }

    // 1. Xác định vai trò của người ĐANG THAO TÁC (từ Session Gmail)
    $currentAdminGmail = $_SESSION['Gmail'] ?? ($_SESSION['Email'] ?? '');
    $myViTri = 0;
    try {
        $checkMyRoleQuery = "SELECT ViTri FROM quantriadmin WHERE Gmail = ?";
        if ($db instanceof PDO) {
            $stmt = $db->prepare($checkMyRoleQuery);
            $stmt->execute([$currentAdminGmail]);
            $myRoleRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($myRoleRow) $myViTri = (int)$myRoleRow['ViTri'];
        } else {
            $stmt = $db->prepare($checkMyRoleQuery);
            $stmt->bind_param("s", $currentAdminGmail);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($myRoleRow = $res->fetch_assoc()) $myViTri = (int)$myRoleRow['ViTri'];
        }
    } catch (Exception $e) {
        // Xử lý ngoại lệ an toàn
    }

    // 2. Kiểm tra tài khoản ĐƯỢC SỬA có phải chính mình hay không
    $isEditingSelf = false;
    try {
        $checkTargetQuery = "SELECT Gmail FROM quantriadmin WHERE AdminId = ?";
        if ($db instanceof PDO) {
            $stmt = $db->prepare($checkTargetQuery);
            $stmt->execute([$adminId]);
            $targetRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($targetRow && trim($targetRow['Gmail']) === trim($currentAdminGmail)) {
                $isEditingSelf = true;
            }
        } else {
            $stmt = $db->prepare($checkTargetQuery);
            $stmt->bind_param("i", $adminId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($targetRow = $res->fetch_assoc()) {
                if (trim($targetRow['Gmail']) === trim($currentAdminGmail)) $isEditingSelf = true;
            }
        }
    } catch (Exception $e) {}

    // 3. Tiến hành kiểm tra phân quyền & thực thi Update
    // Nếu là Quản Lý tối cao (myViTri == 1) và đang đi sửa người khác thì mới cho phép đổi vai trò (ViTri)
    if ($myViTri === 1 && !$isEditingSelf && isset($_POST['txtViTri'])) {
        $newViTri = (int)$_POST['txtViTri'];
        $updateQuery = "UPDATE quantriadmin SET TenAdmin = ?, Gmail = ?, MatKhau = ?, ViTri = ? WHERE AdminId = ?";
        $params = [$tenAdmin, $gmail, $matKhau, $newViTri, $adminId];
    } else {
        // QTV tự sửa chính mình hoặc Quản Lý tự sửa chính mình -> Không được phép thay đổi cấu trúc vai trò của mình tại form này
        $updateQuery = "UPDATE quantriadmin SET TenAdmin = ?, Gmail = ?, MatKhau = ? WHERE AdminId = ?";
        $params = [$tenAdmin, $gmail, $matKhau, $adminId];
    }

    try {
        if ($db instanceof PDO) {
            $stmt = $db->prepare($updateQuery);
            $success = $stmt->execute($params);
        } else {
            $stmt = $db->prepare($updateQuery);
            if (count($params) === 5) {
                $stmt->bind_param("sssii", $params[0], $params[1], $params[2], $params[3], $params[4]);
            } else {
                $stmt->bind_param("sssi", $params[0], $params[1], $params[2], $params[3]);
            }
            $success = $stmt->execute();
        }

        if ($success) {
            // Nếu tự sửa thông tin cá nhân thành công, cập nhật luôn Session để hiển thị chính xác mà không cần login lại
            if ($isEditingSelf) {
                $_SESSION['Gmail'] = $gmail;
                $_SESSION['Email'] = $gmail; // Đồng bộ nếu dùng cả 2 biến
            }
            echo "<script>alert('Cập nhật thông tin quản trị viên thành công!'); window.location.href='QuanTriVien.php';</script>";
        } else {
            echo "<script>alert('Không có thay đổi nào được thực hiện hoặc có lỗi xảy ra.'); window.location.href='QuanTriVien.php';</script>";
        }
    } catch (Exception $e) {
        echo "<script>alert('Lỗi hệ thống: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }
} else {
    header("Location: QuanTriVien.php");
    exit;
}
?>