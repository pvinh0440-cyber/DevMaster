<?php
// Admin/XuLyXoaAdmin.php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
include '../Database.php';
$db = isset($connect) ? $connect : $conn;

// Khóa bảo mật: Phải đăng nhập quyền Admin mới thực hiện được hành động này
if (!isset($_SESSION['IsAdmin']) || $_SESSION['IsAdmin'] !== true) {
    header("Location: /DevMaster/Pages/Login.php");
    exit;
}

$adminId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isSelfDelete = isset($_GET['self']) && $_GET['self'] === 'true';

if ($adminId <= 0) {
    echo "<script>alert('Mã quản trị viên không hợp lệ!'); window.location.href='QuanTriVien.php';</script>";
    exit;
}

// Lấy Gmail hiện tại để đối chiếu bảo mật chéo
$currentAdminGmail = $_SESSION['Gmail'] ?? ($_SESSION['Email'] ?? '');

// 1. Kiểm tra vai trò của người thực hiện hành động xóa
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
} catch (Exception $e) {}

// 2. Chặn bảo mật: QTV thông thường (ViTri = 0) tuyệt đối không có quyền chạy file này xóa bất kỳ ai
if ($myViTri !== 1) {
    echo "<script>alert('Trọng tội: Bạn không có quyền hạn tối cao để thực hiện hành động xóa thành viên ban quản trị!'); window.location.href='QuanTriVien.php';</script>";
    exit;
}

// 3. Thực thi câu lệnh xóa trong database
try {
    $deleteQuery = "DELETE FROM quantriadmin WHERE AdminId = ?";
    if ($db instanceof PDO) {
        $stmt = $db->prepare($deleteQuery);
        $success = $stmt->execute([$adminId]);
    } else {
        $stmt = $db->prepare($deleteQuery);
        $stmt->bind_param("i", $adminId);
        $success = $stmt->execute();
    }

    if ($success) {
        // CƠ CHẾ ĐẶC BIỆT: Nếu là Quản Lý tự xóa bản thân chính mình (self=true)
        if ($isSelfDelete) {
            // Xóa sạch toàn bộ phiên làm việc (Session)
            $_SESSION = array();
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
            
            // Redirect lập tức về trang index của hệ thống (Logout hoàn toàn)
            echo "<script>alert('Tài khoản của bạn đã được xóa thành công. Hệ thống đang tiến hành đăng xuất tự động...'); window.location.href='/DevMaster/Index.php';</script>";
            exit;
        } else {
            // Ngược lại, nếu Quản Lý tối cao xóa một Quản trị viên cấp dưới (ViTri = 0)
            echo "<script>alert('Đã xóa thành công tài khoản Quản trị viên!'); window.location.href='QuanTriVien.php';</script>";
            exit;
        }
    } else {
        echo "<script>alert('Có lỗi xảy ra, không thể xóa tài khoản này!'); window.location.href='QuanTriVien.php';</script>";
    }
} catch (Exception $e) {
    echo "<script>alert('Lỗi hệ thống: " . addslashes($e->getMessage()) . "'); window.location.href='QuanTriVien.php';</script>";
}
?>