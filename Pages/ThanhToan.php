<?php
// Pages/ThanhToan.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../Database.php';
include '../includes/Header.php';

$db = isset($connect) ? $connect : $conn;

// Nếu giỏ hàng trống, quay lại trang giỏ hàng
if (!isset($_SESSION['cart']) || count($_SESSION['cart']) === 0) {
    echo "<script>alert('Giỏ hàng của bạn đang trống!'); window.location.href='/DevMaster/Pages/GioHang.php';</script>";
    exit;
}

// Lấy thông tin giỏ hàng
$ids = implode(',', array_map('intval', $_SESSION['cart']));
$query = "SELECT KhoaHocId, Ten, Anh, Gia FROM khoahoc WHERE KhoaHocId IN ($ids) ORDER BY FIELD(KhoaHocId, $ids)";
$cart_items = [];
$total_amount = 0;

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

foreach ($cart_items as $item) {
    $total_amount += $item['Gia'];
}

$is_logged_in = isset($_SESSION['UserLoggedIn']) && $_SESSION['UserLoggedIn'] === true;

// Lấy họ tên hiển thị an toàn từ key 'FullName'
$user_fullname = "Thành Viên DEVMASTER"; // Mặc định nếu chưa đăng nhập
if ($is_logged_in && isset($_SESSION['FullName']) && !empty($_SESSION['FullName'])) {
    $user_fullname = $_SESSION['FullName'];
}
?>

<link rel="stylesheet" href="/DevMaster/assets/style.css">

<main class="checkout-premium-container">
    <div class="checkout-progress-stepper">
        <div class="step-item active" id="step-1">
            <div class="step-icon"><i class="fa-solid fa-cart-shopping"></i></div>
            <div class="step-label">Giỏ hàng</div>
        </div>
        <div class="step-divider" id="divider-1"></div>
        <div class="step-item active" id="step-2">
            <div class="step-icon"><i class="fa-solid fa-credit-card"></i></div>
            <div class="step-label">Thanh toán</div>
        </div>
        <div class="step-divider" id="divider-2"></div>
        <div class="step-item" id="step-3">
            <div class="step-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div class="step-label">Hoàn tất</div>
        </div>
    </div>

    <div class="checkout-main-grid" id="checkout-form-block">
        
        <div class="checkout-left-panel">
            <div class="checkout-card">
                <?php if ($is_logged_in): ?>
                    <div class="logged-in-profile-box">
                        <div class="badge-account-status">
                            <i class="fa-solid fa-user-shield"></i> Đặt hàng với tài khoản hệ thống
                        </div>
                        <h3 class="welcome-user-title"><span><?php echo htmlspecialchars($user_fullname); ?></span></h3>
                        <input type="hidden" id="checkout_mode" value="logged_in">
                    </div>
                <?php else: ?>
                    <div class="guest-register-box">
                        <h3 class="panel-section-title"><i class="fa-solid fa-user-plus"></i> Thông tin học viên mới</h3>
                        <p class="panel-section-desc">Chưa có tài khoản? Chỉ cần điền thông tin bên dưới, hệ thống sẽ tự động tạo tài khoản và kích hoạt khóa học ngay cho bạn!</p>
                        <input type="hidden" id="checkout_mode" value="guest_register">
                        <div class="checkout-form-group">
                            <label for="bill_username">Tên đăng nhập<span class="req">*</span></label>
                            <input type="text" id="bill_username" class="form-checkout-control">
                        </div>
                        <div class="checkout-form-group">
                            <label for="bill_hoten">Họ và Tên <span class="req">*</span></label>
                            <input type="text" id="bill_hoten" class="form-checkout-control" required>
                        </div>

                        <div class="checkout-form-group">
                            <label for="bill_sdt">Số điện thoại <span class="req">*</span></label>
                            <input type="text" id="bill_sdt" class="form-checkout-control" required>
                        </div>

                        <div class="checkout-form-group">
                            <label for="bill_email">Email <span class="req">*</span></label>
                            <input type="email" id="bill_email" class="form-checkout-control" required>
                            <span class="realtime-validation-msg" id="email-error"></span>
                        </div>

                        <div class="checkout-form-group">
                            <label for="bill_password">Mật khẩu<span class="req">*</span></label>
                            <input type="password" id="bill_password" class="form-checkout-control" required>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="checkout-right-panel">
            <div class="checkout-card order-summary-card">
                <h3 class="panel-section-title"><i class="fa-solid fa-box-open"></i> Đơn hàng của bạn</h3>
                
                <div class="checkout-scroll-container">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="checkout-mini-item">
                            <img src="/DevMaster/<?php echo htmlspecialchars($item['Anh']); ?>" alt="Course" class="mini-item-thumb">
                            <div class="mini-item-details">
                                <h4 class="mini-item-name"><?php echo htmlspecialchars($item['Ten']); ?></h4>
                                <p class="mini-item-price"><?php echo number_format($item['Gia'], 0, ',', '.'); ?>đ</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="summary-divider"></div>

                <div class="summary-row-data">
                    <span>Số lượng khóa học:</span>
                    <strong id="summary-qty"><?php echo count($_SESSION['cart']); ?></strong>
                </div>
                <div class="summary-row-data total-highlight">
                    <span>Tổng cộng:</span>
                    <strong id="summary-price"><?php echo number_format($total_amount, 0, ',', '.'); ?>đ</strong>
                </div>

                <div class="summary-divider"></div>

                <div class="payment-methods-wrapper">
                    <h4 class="method-title">Phương thức thanh toán</h4>
                    <label class="payment-radio-tile">
                        <input type="radio" name="payment_method" value="QR_BANK" checked>
                        <span class="radio-content">
                            <i class="fa-solid fa-qrcode"></i>
                            <span class="radio-text">Chuyển khoản Ngân hàng qua Mã QR (Tự động)</span>
                        </span>
                    </label>
                </div>

                <button type="button" id="btn-submit-order" class="btn-checkout-primary premium-order-btn">
                    <span>Tiến Hành Đặt Hàng <i class="fa-solid fa-arrow-right"></i></span>
                </button>
            </div>
        </div>
    </div>

    <div class="checkout-success-grid" id="checkout-success-block" style="display: none;">
        <div class="success-full-card">
            <div class="success-header-celebrate">
                <div class="success-icon-bounce"><i class="fa-solid fa-circle-check"></i></div>
                <h2>Đơn Hàng Đã Được Khởi Tạo Thành Công!</h2>
                <p>Hệ thống đang chờ bạn quét mã chuyển khoản để tự động kích hoạt lớp học.</p>
            </div>
            
            <div class="success-flex-content">
                <div class="qr-code-holder-box">
                    <img id="dynamic-qr-image" src="" alt="Mã QR Thanh Toán Quốc Tế">
                    <div class="qr-scan-instruction">
                        <i class="fa-solid fa-expand"></i> Sử dụng ứng dụng Ngân hàng / Ví điện tử để quét mã
                    </div>
                </div>

                <div class="invoice-details-spec">
                    <h3><i class="fa-solid fa-receipt"></i> Thông tin chuyển khoản chi tiết</h3>
                    <div class="invoice-table">
                        <div class="invoice-row">
                            <span class="label">Mã Đơn Hàng:</span>
                            <strong id="inv-code" class="text-primary-color">...</strong>
                        </div>
                        <div class="invoice-row">
                            <span class="label">Tổng Số Tiền:</span>
                            <strong id="inv-total" style="color: #ef4444; font-size: 20px;">...</strong>
                        </div>
                        <div class="invoice-row">
                            <span class="label">Ngân Hàng Hưởng Thụ:</span>
                            <span>MB Bank</span>
                        </div>
                        <div class="invoice-row">
                            <span class="label">Số Tài Khoản:</span>
                            <strong class="copyable-text">0855190805<i class="fa-regular fa-copy"></i></strong>
                        </div>
                        <div class="invoice-row">
                            <span class="label">Chủ Tài Khoản:</span>
                            <span>PHAM QUANG VINH</span>
                        </div>
                        <div class="invoice-row">
                            <span class="label">Nội dung chuyển khoản:</span>
                            <strong id="inv-msg" class="text-accent-color">...</strong>
                        </div>
                    </div>

                    <div class="invoice-footer-actions">
                        <?php if (isset($_SESSION['UserLoggedIn']) && $_SESSION['UserLoggedIn'] === true): ?>
                            <a href="/DevMaster/Pages/DonHang.php" class="btn-checkout-primary spec-btn-history">
                                <i class="fa-solid fa-clock-rotate-left"></i> Theo dõi lịch sử đơn hàng của bạn
                            </a>
                        <?php else: ?>
                            <a href="/DevMaster/Auth/Login.php" class="btn-checkout-primary spec-btn-history">
                                <i class="fa-solid fa-clock-rotate-left"></i> Theo dõi lịch sử đơn hàng của bạn
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="../Assets/Javascript.js"></script>
<?php include '../includes/Footer.php'; ?>
</body>
</html>
