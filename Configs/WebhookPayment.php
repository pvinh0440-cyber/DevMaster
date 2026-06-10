<?php
// Pages/WebhookPayment.php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

include '../Database.php';
$db = isset($connect) ? $connect : $conn;

// 1. Nhận dữ liệu JSON từ dịch vụ WebHook (Ví dụ cấu trúc chuẩn mã nguồn mở của PayOS hoặc Casso)
$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Không có dữ liệu đầu vào']);
    exit;
}

/*
  Giả định cấu trúc nhận diện mã đơn hàng từ nội dung chuyển khoản:
  Người dùng quét QR chuyển khoản với nội dung: "DEVMASTER DM83A7C12F"
  Ta cần bóc tách chuỗi để lấy ra mã đơn hàng chính xác "DM83A7C12F"
*/
$transactionDescription = $data['data']['description'] ?? $data['description'] ?? ''; 
$maDonHang = '';

if (preg_match('/DM[A-Z0-9]{8}/i', $transactionDescription, $matches)) {
    $maDonHang = strtoupper($matches[0]);
}

if (empty($maDonHang)) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy mã đơn hàng hợp lệ trong nội dung chuyển khoản']);
    exit;
}

try {
    // 2. Kiểm tra xem đơn hàng này có tồn tại và đang ở trạng thái "Chờ thanh toán" (TrangThai = 0) hay không
    $checkQuery = "SELECT HangDaDatId, TrangThai FROM hangdadat WHERE MaDonHang = ? LIMIT 1";
    if ($db instanceof PDO) {
        $stmt = $db->prepare($checkQuery);
        $stmt->execute([$maDonHang]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->prepare($checkQuery);
        $stmt->bind_param("s", $maDonHang);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
    }

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Đơn hàng không tồn tại trên hệ thống']);
        exit;
    }

    if (intval($order['TrangThai']) === 1) {
        echo json_encode(['success' => true, 'message' => 'Đơn hàng này đã được kích hoạt từ trước']);
        exit;
    }

    // 3. TỰ ĐỘNG CẬP NHẬT TRẠNG THÁI ĐƠN HÀNG THÀNH HOÀN THÀNH (TrangThai = 1)
    $updateQuery = "UPDATE hangdadat SET TrangThai = 1 WHERE MaDonHang = ?";
    if ($db instanceof PDO) {
        $stmt = $db->prepare($updateQuery);
        $stmt->execute([$maDonHang]);
    } else {
        $stmt = $db->prepare($updateQuery);
        $stmt->bind_param("s", $maDonHang);
        $stmt->execute();
    }

    echo json_encode(['success' => true, 'message' => 'Kích hoạt khóa học tự động thành công cho đơn hàng ' . $maDonHang]);
    exit;

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
    exit;
}