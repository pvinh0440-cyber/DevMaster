<?php
// AjaxUpdateProgress.php
session_start();
$conn = new mysqli("localhost", "root", "", "devmaster");
$conn->set_charset("utf8mb4");

if (isset($_POST['action']) && $_POST['action'] === 'complete_lesson') {
    $baiHocId = intval($_POST['baihoc_id']);
    $khoaHocId = intval($_POST['khoahoc_id']);
    $tienDoTong = intval($_POST['tiendo_tong']);
    // Nhận ID học viên từ Ajax truyền lên
    $hocVienId = isset($_POST['hocvien_id']) ? intval($_POST['hocvien_id']) : (isset($_SESSION['UserId']) ? intval($_SESSION['UserId']) : 10);
    
    // Đánh dấu hoàn thành bài học (Nếu chưa có dòng dữ liệu thì INSERT, nếu có rồi thì UPDATE thành 1)
    $sql1 = "INSERT INTO tiendohocvien (STT, BaiHocId, TrangThai) 
             VALUES (?, ?, 1) 
             ON DUPLICATE KEY UPDATE TrangThai = 1";
    $stmt1 = $conn->prepare($sql1);
    $stmt1->bind_param("ii", $hocVienId, $baiHocId);
    $stmt1->execute();
    
    // Ghi chú: Cột `TienDo` trong bảng `khoahoc` đã xóa hoàn toàn.
    // Tiến độ tổng thể (%) bây giờ sẽ được tính real-time thông qua câu lệnh SQL toán học ở Đoạn 1 mỗi khi load trang.
    // Do đó, ta không cần thực hiện UPDATE bảng `khoahoc` ở đây nữa để tránh sinh lỗi cột không tồn tại!
    
    echo json_encode(['status' => 'success', 'message' => 'Cập nhật tiến độ học viên thành công!']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ']);
}
?>