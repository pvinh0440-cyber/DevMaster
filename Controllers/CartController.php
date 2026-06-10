<?php
// 1. Cấu hình xử lý lỗi an toàn cho API: Ghi log ẩn chứ không in ra màn hình làm hỏng JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 2. Ép kiểu dữ liệu trả về là JSON sạch ngay từ dòng đầu tiên
header('Content-Type: application/json; charset=utf-8');

// 3. Khởi chạy Session an toàn
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Khởi tạo giỏ hàng nếu chưa tồn tại
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// 4. Kết nối Cơ sở dữ liệu đồng bộ qua PDO bằng file Database.php
$databasePath = '';

// Kịch bản A: Nếu CartController.php nằm trong thư mục con (ví dụ: Controllers/ hoặc API/)
if (file_exists(__DIR__ . '/../Database.php')) {
    $databasePath = __DIR__ . '/../Database.php';
// Kịch bản B: Nếu CartController.php nằm ngay tại thư mục gốc cùng cấp với Database.php
} elseif (file_exists(__DIR__ . '/Database.php')) {
    $databasePath = __DIR__ . '/Database.php';
// Kịch bản C: Phương án dự phòng tính từ gốc Document Root của Server
} elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/DevMaster/Database.php')) {
    $databasePath = $_SERVER['DOCUMENT_ROOT'] . '/DevMaster/Database.php';
}

if (!empty($databasePath)) {
    require_once $databasePath;
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Không thể tìm thấy tệp cấu hình kết nối hệ thống (Database.php).'
    ]);
    exit;
}

// Gán biến kết nối an toàn
$db = isset($connect) ? $connect : null;

if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Kết nối dữ liệu thất bại']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// ==========================================
// 1. CHỨC NĂNG THÊM KHÓA HỌC
// ==========================================
if ($action === 'add') {
    $khoahocId = isset($_POST['KhoaHocId']) ? intval($_POST['KhoaHocId']) : 0;
    
    if ($khoahocId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Mã khóa học không hợp lệ']);
        exit;
    }

    if (!in_array($khoahocId, $_SESSION['cart'])) {
        $_SESSION['cart'][] = $khoahocId;
    }

    echo json_encode([
        'success' => true,
        'cart_count' => count($_SESSION['cart']),
        'message' => 'Đã thêm khóa học vào giỏ hàng thành công!'
    ]);
    exit;
}

// ==========================================
// 2. CHỨC NĂNG XÓA KHÓA HỌC (Đã sửa lỗi đồng bộ)
// ==========================================
if ($action === 'remove') {
    $khoahocId = isset($_POST['KhoaHocId']) ? intval($_POST['KhoaHocId']) : 0;
    
    if (($key = array_search($khoahocId, $_SESSION['cart'])) !== false) {
        unset($_SESSION['cart'][$key]);
        $_SESSION['cart'] = array_values($_SESSION['cart']); // Reset lại chỉ mục mảng
        
        $totalPrice = 0;
        
        // Nếu giỏ hàng vẫn còn sản phẩm, tính toán lại tổng tiền
        if (count($_SESSION['cart']) > 0) {
            $ids = implode(',', array_map('intval', $_SESSION['cart']));
            $query = "SELECT SUM(Gia) as total FROM khoahoc WHERE KhoaHocId IN ($ids)";
            
            try {
                // Tự động tương thích với cả hai chế độ kết nối (PDO hoặc MySQLi)
                if ($db instanceof PDO) {
                    $stmt = $db->query($query);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $totalPrice = isset($row['total']) ? $row['total'] : 0;
                } else {
                    // Chế độ dành cho MySQLi truyền thống
                    $res = $db->query($query);
                    if ($res) {
                        $row = $res->fetch_assoc();
                        $totalPrice = isset($row['total']) ? $row['total'] : 0;
                    }
                }
            } catch (Exception $e) {
                $totalPrice = 0; // Đảm bảo an toàn hệ thống nếu SQL lỗi
            }
        }

        echo json_encode([
            'success' => true,
            'cart_count' => count($_SESSION['cart']),
            'total_price' => number_format($totalPrice, 0, ',', '.') . 'đ',
            'total_price_raw' => $totalPrice
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Khóa học không tồn tại trong giỏ hàng']);
    }
    exit;
}

// ==========================================
// 3. KIỂM TRA TRẠNG THÁI
// ==========================================
if ($action === 'check') {
    echo json_encode([
        'success' => true,
        'cart_count' => count($_SESSION['cart']),
        'items' => $_SESSION['cart']
    ]);
    exit;
}

// Nếu không khớp action nào
echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);
exit;