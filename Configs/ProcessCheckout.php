<?php
// Pages/ProcessCheckout.php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../Database.php';
$db = isset($connect) ? $connect : $conn;

// Kiểm tra giỏ hàng hợp lệ
if (!isset($_SESSION['cart']) || count($_SESSION['cart']) === 0) {
    echo json_encode(['success' => false, 'message' => 'Giỏ hàng rỗng, không thể đặt hàng!']);
    exit;
}

$mode = $_POST['mode'] ?? '';

// Định danh STT tài khoản người dùng mua hàng
$stt_user = null;
if (isset($_SESSION['UserLoggedIn']) && $_SESSION['UserLoggedIn'] === true) {
    // Lấy đúng key UserId đồng bộ từ Login.php
    $stt_user = isset($_SESSION['UserId']) ? intval($_SESSION['UserId']) : null;
}

if ($mode === 'guest_register') {
    // Luồng tự động đăng ký cho học viên mới
    $hoTen       = trim($_POST['HoTen'] ?? '');
    $sdt         = trim($_POST['SDT'] ?? '');
    $tenDangNhap = trim($_POST['TenDangNhap'] ?? '');
    $gmail       = trim($_POST['Gmail'] ?? '');
    $matKhau     = $_POST['MatKhau'] ?? '';

    if (empty($hoTen) || empty($sdt) || empty($gmail) || empty($matKhau) || empty($tenDangNhap)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đầy đủ tất cả các trường bắt buộc!']);
        exit;
    }

    // Kiểm tra xem Email đã tồn tại chưa
    $checkQuery = "SELECT STT FROM dangky WHERE Gmail = ? LIMIT 1";
    if ($db instanceof PDO) {
        $stmt = $db->prepare($checkQuery);
        $stmt->execute([$gmail]);
        $exists = $stmt->fetch();
    } else {
        $stmt = $db->prepare($checkQuery);
        $stmt->bind_param("s", $gmail);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res->fetch_assoc();
    }

    if ($exists) {
        echo json_encode(['success' => false, 'message' => 'Email này đã tồn tại trong hệ thống. Vui lòng đăng nhập hoặc dùng Email khác!']);
        exit;
    }

    // Tiến hành chèn user mới vào bảng `dangky`
    if ($db instanceof PDO) {
        $insertUserQuery = "INSERT INTO dangky (TenDangNhap, MatKhau, HoTen, Gmail, SDT, NgayDangKy, TrangThai) VALUES (?, ?, ?, ?, ?, NOW(), 'Chờ thanh toán')";
        $stmt = $db->prepare($insertUserQuery);
        $stmt->execute([$tenDangNhap, $matKhau, $hoTen, $gmail, $sdt]);
        $stt_user = $db->lastInsertId();
    } else {
        $insertUserQuery = "INSERT INTO dangky (TenDangNhap, MatKhau, HoTen, Gmail, SDT, NgayDangKy, TrangThai) VALUES (?, ?, ?, ?, ?, NOW(), 'Chờ thanh toán')";
        $stmt = $db->prepare($insertUserQuery);
        $stmt->bind_param("sssssss", $tenDangNhap, $matKhau, $hoTen, $gmail, $sdt);
        $stmt->execute();
        $stt_user = $db->insert_id;
    }

    // TỰ ĐỘNG ĐĂNG NHẬP NGAY LẬP TỨC CHO GUEST
    $_SESSION['UserLoggedIn'] = true;
    $_SESSION['UserId'] = $stt_user; // Đồng bộ key UserId
    $_SESSION['Username'] = $tenDangNhap;
    $_SESSION['FullName'] = $hoTen;
    $_SESSION['Email'] = $gmail;
    $_SESSION['UserActiveName'] = $tenDangNhap; // Đồng bộ UserActiveName
    $_SESSION['IsAdmin'] = false; // Người dùng thường, không có quyền admin

} elseif ($mode === 'logged_in') {
    // Đã sửa đổi: Không dùng $_SESSION['STT'] bị rỗng nữa, dùng luôn $stt_user đã check ở trên
    if (empty($stt_user)) {
        echo json_encode(['success' => false, 'message' => 'Lỗi phiên đăng nhập hệ thống, vui lòng thử lại!']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Chế độ đặt hàng không hợp lệ!']);
    exit;
}

if ($mode === 'logged_in') {
    $hoTen = $_SESSION['FullName'] ?? 'Học viên';
    $gmail = $_SESSION['Email'] ?? '';
}

// LẤY THÔNG TIN GIỎ HÀNG ĐỂ TÍNH TOÁN TIỀN CHÍNH XÁC TỪ SERVER
$ids = implode(',', array_map('intval', $_SESSION['cart']));
$query = "SELECT KhoaHocId, Gia FROM khoahoc WHERE KhoaHocId IN ($ids)";
$total_amount = 0;
$items_to_insert = [];

if ($db instanceof PDO) {
    $stmt = $db->query($query);
    $items_to_insert = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $res = $db->query($query);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $items_to_insert[] = $row;
        }
    }
}

foreach ($items_to_insert as $item) {
    $total_amount += $item['Gia'];
}

// SINH MÃ ĐƠN HÀNG NGẪU NHIÊN CHUẨN ĐỊNH DẠNG (RNG)
$maDonHang = "DM" . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
$ngayDat = date('Y-m-d H:i:s');
$trangThai = 0; // 0: Chờ thanh toán

// Thêm hóa đơn vào bảng `hangdadat`
$insertOrderQuery = "INSERT INTO hangdadat (STT, NgayDat, TongTien, TrangThai, MaDonHang) VALUES (?, ?, ?, ?, ?)";
$hangDaDatId = null;

if ($db instanceof PDO) {
    $stmt = $db->prepare($insertOrderQuery);
    // Thứ tự mảng truyền vào khớp hoàn toàn: STT, NgayDat, TongTien, TrangThai, MaDonHang
    $stmt->execute([$stt_user, $ngayDat, $total_amount, $trangThai, $maDonHang]);
    $hangDaDatId = $db->lastInsertId();
} else {
    $stmt = $db->prepare($insertOrderQuery);
    // Sửa lại chuỗi định dạng bind_param và thứ tự biến cho chuẩn xác: i = int, s = string, d = double
    // Thứ tự chuẩn: STT (i), NgayDat (s), TongTien (d), TrangThai (i), MaDonHang (s) -> "isdis"
    $stmt->bind_param("isdis", $stt_user, $ngayDat, $total_amount, $trangThai, $maDonHang);
    $stmt->execute();
    $hangDaDatId = $db->insert_id;
}

// Thêm chi tiết các khóa học vào bảng `chitiethangdadat`
$insertDetailQuery = "INSERT INTO chitiethangdadat (HangDaDatId, KhoaHocId) VALUES (?, ?)";

if ($db instanceof PDO) {
    $stmt = $db->prepare($insertDetailQuery);
    foreach ($items_to_insert as $course) {
        $stmt->execute([$hangDaDatId, $course['KhoaHocId']]);
    }
} else {
    $stmt = $db->prepare($insertDetailQuery);
    foreach ($items_to_insert as $course) {
        $stmt->bind_param("ii", $hangDaDatId, $course['KhoaHocId']);
        $stmt->execute();
    }
}

// Giải phóng giỏ hàng ngay lập tức để tránh thanh toán trùng lặp

// ==================== ĐOẠN CODE ĐÃ SỬA ĐỔI CHUẨN HOÁ ====================

// 1. XỬ LÝ TIẾN ĐỘ HỌC VIÊN (Chạy khi giỏ hàng vẫn còn nguyên vẹn dữ liệu)
if (isset($_SESSION['cart']) && is_array($_SESSION['cart']) && count($_SESSION['cart']) > 0) {
    foreach ($_SESSION['cart'] as $cId) {
        $cId = intval($cId);
        
        // Lấy toàn bộ bài học của khóa học vừa mua
        $getLessonsQuery = "SELECT BaiHocId FROM baihoc WHERE KhoaHocId = $cId";
        $lessons = [];
        
        try {
            if ($db instanceof PDO) {
                $stmtLessons = $db->prepare($getLessonsQuery);
                $stmtLessons->execute();
                $lessons = $stmtLessons->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $resLessons = $db->query($getLessonsQuery);
                if ($resLessons) {
                    while ($lRow = $resLessons->fetch_assoc()) {
                        $lessons[] = $lRow;
                    }
                }
            }
            
            // Thêm các bài học đó vào bảng tiendohocvien với trạng thái mặc định bằng 0
            foreach ($lessons as $lesson) {
                $bId = intval($lesson['BaiHocId']);
                $initProgressQuery = "INSERT IGNORE INTO tiendohocvien (STT, BaiHocId, TrangThai) VALUES (?, ?, 0)";
                if ($db instanceof PDO) {
                    $db->prepare($initProgressQuery)->execute([$stt_user, $bId]);
                } else {
                    $stmtInit = $db->prepare($initProgressQuery);
                    $stmtInit->bind_param("ii", $stt_user, $bId);
                    $stmtInit->execute();
                    $stmtInit->close();
                }
            }
        } catch (Exception $e) {
            // Ghi nhận lỗi nội bộ âm thầm, không phá vỡ cấu trúc JSON đầu ra
        }
    }
}

// 2. XÁC ĐỊNH CHÍNH XÁC EMAIL NGƯỜI NHẬN
if ($mode === 'logged_in') {
    $hoTen = $_SESSION['FullName'] ?? 'Học viên';
    $gmail = $_SESSION['Email'] ?? '';
    
    // Nếu Session mất email, truy vấn khẩn cấp từ Database để cứu vớt dữ liệu
    if (empty($gmail)) {
        $emailQuery = "SELECT Gmail FROM dangky WHERE STT = ? LIMIT 1";
        if ($db instanceof PDO) {
            $stmtEmail = $db->prepare($emailQuery);
            $stmtEmail->execute([$stt_user]);
            $gmail = $stmtEmail->fetchColumn();
        } else {
            $stmtEmail = $db->prepare($emailQuery);
            $stmtEmail->bind_param("i", $stt_user);
            $stmtEmail->execute();
            $stmtEmail->bind_result($gmail);
            $stmtEmail->fetch();
            $stmtEmail->close();
        }
    }
} elseif ($mode === 'guest_register') {
    $hoTen = trim($_POST['HoTen'] ?? 'Học viên');
    $gmail = trim($_POST['Gmail'] ?? '');
}

// 3. GIẢI PHÓNG GIỎ HÀNG (Chỉ thực hiện một lần duy nhất tại đây khi mọi công việc mảng đã xong xuôi)
unset($_SESSION['cart']); 

// 4. ÉP GỬI MAIL XÁC NHẬN ĐƠN HÀNG
$mailSent = false;
if (!empty($gmail)) {
    $to = $gmail;
    $subject = "=?UTF-8?B?".base64_encode("[DEVMASTER] Xác nhận đơn hàng #" . $maDonHang)."?=";
    
    // Khởi tạo các tham số Headers chuẩn hóa để tránh bị các bộ lọc Mail từ chối
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: DEVMASTER Academy <no-reply@devmaster.edu.vn>" . "\r\n";
    $headers .= "Reply-To: cskh@devmaster.edu.vn" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    // Nội dung Email dạng HTML
    $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px;'>
            <div style='text-align: center; background-color: #0284c7; padding: 15px;'>
                <h2 style='color: #ffffff; margin: 0;'>CẢM ƠN BẠN ĐÃ ĐẶT HÀNG!</h2>
            </div>
            <div style='padding: 20px; color: #333333;'>
                <p>Xin chào <strong>" . htmlspecialchars($hoTen) . "</strong>,</p>
                <p>Hệ thống DEVMASTER đã tiếp nhận yêu cầu đăng ký khóa học của bạn với mã đơn hàng dưới đây:</p>
                
                <p><strong>Mã đơn hàng:</strong> <span style='color: #0284c7; font-weight: bold;'>" . $maDonHang . "</span></p>
                <p><strong>Tổng thanh toán:</strong> <span style='color: #ef4444; font-weight: bold;'>" . number_format($total_amount, 0, ',', '.') . "đ</span></p>
                
                <div style='background-color: #f8fafc; padding: 15px; border-left: 4px solid #0284c7; margin: 20px 0;'>
                    <h4 style='margin-top:0;'>Thông tin chuyển khoản:</h4>
                    <p><strong>Ngân hàng:</strong> MB Bank | <strong>STK:</strong> 0855190805</p>
                    <p><strong>Chủ tài khoản:</strong> PHAM QUANG VINH</p>
                    <p><strong>Nội dung CK:</strong> DEVMASTER " . $maDonHang . "</p>
                </div>

                <div style='text-align: center; margin-top: 30px;'>
                    <a href='http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/DevMaster/Pages/DonHang.php' style='background-color: #0284c7; color: white; padding: 12px 25px; text-decoration: none; font-weight: bold; border-radius: 4px; display: inline-block;'>Xem Đơn Hàng Của Tôi</a>
                </div>
            </div>
        </div>
    ";

    // Thực hiện lệnh gửi thư
    if (@mail($to, $subject, $message, $headers)) {
        $mailSent = true;
    }
}

// 5. TẠO ĐƯỜNG DẪN QR CODE THEO TIÊU CHUẨN VIETQR (Tự điền STK, Số tiền, Nội dung chuyển khoản)
// Sử dụng API miễn phí của VietQR để sinh ảnh trực tiếp
$qr_url = "https://img.vietqr.io/image/MBBank-0855190805-compact2.png?amount=" . $total_amount . "&addInfo=" . urlencode("DEVMASTER " . $maDonHang) . "&accountName=PHAM%20QUANG%20VINH";

// 6. TRẢ VỀ JSON DUY NHẤT MỘT LẦN VỀ CLIENT JS
echo json_encode([
    'success'    => true,
    'ma_don_hang'=> $maDonHang,
    'tong_tien'  => number_format($total_amount, 0, ',', '.') . 'đ',
    'total_raw'  => $total_amount,
    'noi_dung_ck'=> $maDonHang,
    'qr_code_url'=> $qr_url, // Trả link này ra giao diện Front-end để thẻ <img src="..."> nạp vào
    'mail_sent'  => $mailSent
]);
exit;