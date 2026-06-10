<?php
// Pages/GioHang.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sửa đường dẫn nhảy ra thư mục gốc để tìm tệp Database và Header
include '../Database.php'; 
include '../includes/Header.php'; 

// Đảm bảo tương thích với biến kết nối của hệ thống ($connect hoặc $conn)
$db = isset($connect) ? $connect : $conn;

// Lấy danh sách khóa học trong giỏ hàng từ Session
$cart_items = [];
if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0) {
    $ids = implode(',', array_map('intval', $_SESSION['cart']));
    
    // Khởi tạo câu lệnh SQL truy vấn chi tiết
    $query = "SELECT KhoaHocId, Ten, Anh, Gia, TenGiangVien FROM khoahoc WHERE KhoaHocId IN ($ids) ORDER BY FIELD(KhoaHocId, $ids)";
    
    if ($db instanceof PDO) {
        $stmt = $db->query($query);
        $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $res = $db->query($query);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $cart_items[] = $row;
            }
        }
    }
}

// Tính tổng tiền giỏ hàng
$total_amount = 0;
foreach ($cart_items as $item) {
    $total_amount += $item['Gia'];
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Giỏ Hàng</title>
    <link rel="stylesheet" href="/DevMaster/assets/style.css">
</head>
<body>

<section class="cart-master-section">
    <div class="cart-container">
        <h1 class="cart-title-main">
            <i class="fa-solid fa-bag-shopping" style="color: var(--primary);"></i> Giỏ hàng của bạn
        </h1>

        <?php if (count($cart_items) > 0): ?>
            <div class="cart-layout-wrapper">
                
                <div class="cart-left-panel">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item-card" id="cart-item-row-<?php echo $item['KhoaHocId']; ?>">
                            <img src="/DevMaster/<?php echo !empty($item['Anh']) ? htmlspecialchars($item['Anh']) : 'default-course.jpg'; ?>" alt="Course Thumbnail" class="cart-item-img">
                            
                            <div class="cart-item-details">
                                <h3 class="cart-item-name"><?php echo htmlspecialchars($item['Ten']); ?></h3>
                                <p class="cart-item-author"><strong><?php echo htmlspecialchars($item['TenGiangVien']); ?></strong></p>
                            </div>

                            <div class="cart-item-price">
                                <?php echo number_format($item['Gia'], 0, ',', '.'); ?>đ
                            </div>

                            <button type="button" class="cart-btn-delete" title="Xóa khỏi giỏ hàng" onclick="removeCartItem(<?php echo $item['KhoaHocId']; ?>, 'cart-item-row-<?php echo $item['KhoaHocId']; ?>')">
                                <i class="fa-regular fa-trash-can"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="cart-right-panel">
                    <h2 class="summary-title">Tóm tắt đơn hàng</h2>
                    
                    <div class="summary-row">
                        <span>Số lượng sản phẩm:</span>
                        <span><strong id="summary-count"><?php echo count($cart_items); ?></strong> khóa học</span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Thuế & Phụ phí:</span>
                        <span style="color: #22c55e; font-weight: 600;">Miễn phí</span>
                    </div>

                    <div class="summary-total-row">
                        <span class="summary-total-label">Tổng cộng:</span>
                        <span class="summary-total-price" id="summary-total"><?php echo number_format($total_amount, 0, ',', '.'); ?>đ</span>
                    </div>

                    <a href="/DevMaster/Pages/ThanhToan.php" class="btn-checkout-primary">
                        Tiến hành mua sắm <i class="fa-solid fa-arrow-right"></i>
                    </a>

                    <a href="/DevMaster/Pages/TatCaKhoaHoc.php" class="btn-continue-secondary">
                        <i class="fa-solid fa-arrow-left"></i> Tiếp tục mua sắm
                    </a>
                </div>

            </div>
        <?php else: ?>
            <div class="cart-empty-box">
                <i class="fa-solid fa-box-open" style="font-size: 64px; color: #cbd5e1; margin-bottom: 20px; display: block;"></i>
                <h3 style="font-size: 20px; font-weight: 700; margin-bottom: 10px;">Giỏ hàng của bạn đang trống!</h3>
                <p style="color: var(--text-muted); margin-bottom: 25px;">Hãy khám phá các khóa học lập trình đỉnh cao và làm đầy giỏ hàng của bạn nhé.</p>
                <a href="/DevMaster/Pages/TatCaKhoaHoc.php" class="btn-checkout-primary" style="display: inline-flex; width: auto; padding: 12px 30px;">
                    Quay lại trang khóa học
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php include '../Includes/Footer.php'; ?>
<script src="/DevMaster/Assets/Javascript.js"></script>
</body>
</html>