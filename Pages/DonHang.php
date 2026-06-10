<?php
// Pages/DonHang.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../Database.php';
include '../includes/Header.php';

$db = isset($connect) ? $connect : $conn;

// Kiểm tra quyền truy cập ép buộc đăng nhập
if (!isset($_SESSION['UserLoggedIn']) || $_SESSION['UserLoggedIn'] !== true) {
    echo "<script>alert('Vui lòng đăng nhập để xem đơn hàng!'); window.location.href='/DevMaster/Pages/Login.php';</script>";
    exit;
}

$stt_user = isset($_SESSION['UserId']) ? intval($_SESSION['UserId']) : 0;

// Lấy danh sách toàn bộ hóa đơn của người dùng này
$orderQuery = "SELECT HangDaDatId, MaDonHang, NgayDat, TongTien, TrangThai FROM hangdadat WHERE STT = ? ORDER BY NgayDat DESC";
$orders = [];

if ($db instanceof PDO) {
    $stmt = $db->prepare($orderQuery);
    $stmt->execute([$stt_user]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $db->prepare($orderQuery);
    $stmt->bind_param("i", $stt_user);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $orders[] = $row;
    }
}
?>
<link rel="stylesheet" href="/DevMaster/assets/style.css">
<main class="order-history-container" style="max-width: 1200px; margin: 40px auto; padding: 0 20px; font-family: 'Inter', sans-serif;">
    <h2 style="font-size: 28px; font-weight: 800; margin-bottom: 8px; color: var(--text-dark);"><i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i> Quản lý đơn hàng của bạn</h2>
    <p style="color: var(--text-muted); margin-bottom: 30px;">Theo dõi tiến độ duyệt khóa học và xem lại hóa đơn giao dịch của bạn.</p>

    <?php if (count($orders) === 0): ?>
        <div style="text-align: center; padding: 60px 20px; background: #ffffff; border-radius: 16px; box-shadow: var(--shadow-premium);">
            <i class="fa-solid fa-folder-open" style="font-size: 54px; color: #cbd5e1; margin-bottom: 15px;"></i>
            <h3>Bạn chưa có lịch sử đơn hàng nào!</h3>
            <a href="/DevMaster/Pages/TatCaKhoaHoc.php" class="btn-checkout-primary" style="display:inline-flex; width:auto; padding:10px 25px; margin-top:15px;">Khám phá khóa học ngay</a>
        </div>
    <?php else: ?>
        <div style="display: grid; gap: 20px;">
            <?php foreach ($orders as $order): 
                // 1. Lấy thông tin chi tiết các khóa học trong đơn hàng này (Bao gồm Ảnh, Tên, Giá)
                $detailQuery = "SELECT kh.KhoaHocId, kh.Ten, kh.Anh, kh.Gia 
                                FROM chitiethangdadat ct 
                                JOIN khoahoc kh ON ct.KhoaHocId = kh.KhoaHocId 
                                WHERE ct.HangDaDatId = ?";
                $details = [];
                if ($db instanceof PDO) {
                    $stmt2 = $db->prepare($detailQuery);
                    $stmt2->execute([$order['HangDaDatId']]);
                    $details = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $stmt2 = $db->prepare($detailQuery);
                    $stmt2->bind_param("i", $order['HangDaDatId']);
                    $stmt2->execute();
                    $res2 = $stmt2->get_result();
                    while ($r2 = $res2->fetch_assoc()) { $details[] = $r2; }
                }
                $totalCourses = count($details);
            ?>
                <div class="order-history-card" data-order-id="<?php echo $order['HangDaDatId']; ?>" style="background: #ffffff; border-radius: 16px; border: 1px solid var(--border-color); padding: 24px; display: flex; flex-direction: column; gap: 0;">
                    
                    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px; width: 100%;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 6px;">
                                <span style="font-weight: 800; font-size: 18px; color: var(--text-dark);"><?php echo htmlspecialchars($order['MaDonHang']); ?></span>
                                
                                <?php if ($order['TrangThai'] == 0): ?>
                                    <span style="background: #fef3c7; color: #d97706; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                        Chờ Thanh Toán
                                    </span>
                                <?php else: ?>
                                    <span style="background: #dcfce7; color: #15803d; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa-solid fa-circle-check"></i> Hoàn Thành
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-muted); font-weight: 500;">
                                <span><?php echo date('H:i', strtotime($order['NgayDat'])); ?> - <?php echo date('d/m/Y', strtotime($order['NgayDat'])); ?></span>
                                <span style="width: 4px; height: 4px; background: #94a3b8; border-radius: 50%; display: inline-block;"></span>
                                <span><?php echo $totalCourses; ?> Khóa học</span>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 20px;">
                            <div style="text-align: right;">
                                <strong style="font-size: 20px; color: var(--text-dark); font-weight: 800; display: block; line-height: 1.2;">
                                    <?php echo number_format($order['TongTien'], 0, ',', '.'); ?>đ
                                </strong>
                                <span style="font-size: 12px; color: var(--text-muted); font-weight: 500;">Chuyển khoản</span>
                            </div>

                            <button class="toggle-order-details-btn" style="background: #f1f5f9; border: none; width: 36px; height: 36px; border-radius: 50%; color: var(--text-dark); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; outline: none;">
                                <i class="fa-solid fa-chevron-down" style="transition: transform 0.3s ease;"></i>
                            </button>
                        </div>
                    </div>

                    <div class="order-expandable-details" style="display: none; width: 100%; border-top: 1px dashed #e2e8f0; margin-top: 20px; padding-top: 20px;">
                        
                        <div style="display: flex; flex-wrap: wrap; gap: 30px;">
                            
                            <div style="flex: 1.5; min-width: 300px; display: flex; flex-direction: column; gap: 16px;">
                                <?php foreach ($details as $course): ?>
                                    <div style="display: flex; align-items: center; justify-content: space-between; background: #f8fafc; border-radius: 12px; padding: 12px 16px; border: 1px solid #f1f5f9;">
                                        <div style="display: flex; align-items: center; gap: 14px;">
                                            <img src="/DevMaster/<?php echo htmlspecialchars($course['Anh']); ?>" alt="Course Image" style="width: 65px; height: 42px; object-fit: cover; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                                            <div>
                                                <h4 style="margin: 0 0 4px 0; font-size: 14px; font-weight: 700; color: var(--text-dark); max-width: 280px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($course['Ten']); ?></h4>
                                                <span style="font-size: 13px; color: var(--primary); font-weight: 700;"><?php echo number_format($course['Gia'], 0, ',', '.'); ?>đ</span>
                                            </div>
                                        </div>
                                        
                                        <?php if ($order['TrangThai'] != 0): ?>
                                            <button class="btn-goto-study" onclick="window.location.href='/DevMaster/Pages/VaoHocNgay.php?id=<?php echo $course['KhoaHocId']; ?>';" style="background: var(--primary); color: #ffffff; border: none; padding: 8px 16px; font-size: 12px; font-weight: 700; border-radius: 8px; cursor: pointer; transition: all 0.2s ease;">
                                                Vào học <i class="fa-solid fa-arrow-right" style="font-size: 11px; margin-left: 4px;"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($order['TrangThai'] == 0): ?>
                                <div style="display: flex; gap: 20px; flex-wrap: wrap; background: #f8fafc; border-radius: 16px; padding: 24px; border: 1px solid #e2e8f0; margin-top: 20px; align-items: center; box-sizing: border-box; width: 100%;">
                                    <div style="flex: 1.5; min-width: 280px; display: flex; flex-direction: column; justify-content: space-between; gap: 16px;">
                                        <div>
                                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 14px;">
                                                <span style="display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; background: rgba(245, 158, 11, 0.15); color: var(--accent); border-radius: 50%; font-size: 12px; font-weight: bold;">
                                                    <i class="fa-solid fa-exclamation"></i>
                                                </span>
                                                <h4 style="margin: 0; font-size: 15px; font-weight: 800; color: var(--text-dark);">Thông tin thanh toán</h4>
                                            </div>

                                            <p style="margin: 0 0 10px 0; font-size: 13px; font-weight: 600; color: var(--text-main);">Chuyển khoản đến:</p>
                                            
                                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                                <div style="display: flex; justify-content: space-between; align-items: center; background: #ffffff; border-radius: 10px; padding: 12px 16px; border: 1px solid #edf2f7; font-size: 13px; box-sizing: border-box;">
                                                    <span style="color: var(--text-muted); font-weight: 500;">Ngân Hàng:</span>
                                                    <strong style="color: var(--text-dark); font-weight: 700;">MB Bank</strong>
                                                </div>

                                                <div style="display: flex; justify-content: space-between; align-items: center; background: #ffffff; border-radius: 10px; padding: 12px 16px; border: 1px solid #edf2f7; font-size: 13px; box-sizing: border-box;">
                                                    <span style="color: var(--text-muted); font-weight: 500;">Số Tài Khoản:</span>
                                                    <strong style="color: var(--text-dark); font-weight: 700; font-family: monospace; font-size: 14px;">0855190805</strong>
                                                </div>

                                                <div style="display: flex; justify-content: space-between; align-items: center; background: #ffffff; border-radius: 10px; padding: 12px 16px; border: 1px solid #edf2f7; font-size: 13px; box-sizing: border-box;">
                                                    <span style="color: var(--text-muted); font-weight: 500;">Chủ Tài Khoản:</span>
                                                    <strong style="color: var(--text-dark); font-weight: 700; font-size: 12px; text-transform: uppercase;">PHAM QUANG VINH</strong>
                                                </div>

                                                <div style="display: flex; justify-content: space-between; align-items: center; background: #ffffff; border-radius: 10px; padding: 12px 16px; border: 1px solid #edf2f7; font-size: 13px; box-sizing: border-box;">
                                                    <span style="color: var(--text-muted); font-weight: 500;">Số tiền:</span>
                                                    <strong style="color: var(--text-dark); font-weight: 800; font-size: 15px; color: var(--primary);"><?php echo number_format($order['TongTien'], 0, ',', '.'); ?>đ</strong>
                                                </div>

                                                <div style="display: flex; justify-content: space-between; align-items: center; background: #fff5f5; border-radius: 10px; padding: 12px 16px; border: 1px solid #fed7d7; font-size: 13px; box-sizing: border-box;">
                                                    <span style="color: #c53030; font-weight: 600;">Nội dung CK:</span>
                                                    <strong style="color: #ef4444; font-family: 'Courier New', monospace; font-size: 15px; font-weight: 800; letter-spacing: 0.5px;"><?php echo $order['MaDonHang']; ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="flex: 1; min-width: 240px; display: flex; flex-direction: column; align-items: center; justify-content: center; border-left: 1px dashed #e2e8f0; padding-left: 20px; box-sizing: border-box;">
                                        <?php 
                                            $maDonHangHienTai = $order['MaDonHang'];
                                            $soTienHienTai = $order['TongTien'];
                                            $noiDungChuyenKhoan = $maDonHangHienTai;
                                            $qrUrlReal = "https://img.vietqr.io/image/MBBank-0855190805-compact2.png?amount=" . $soTienHienTai . "&addInfo=" . urlencode($noiDungChuyenKhoan) . "&accountName=DEVMASTER";
                                        ?>
                                        
                                        <div style="background: #ffffff; padding: 12px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.06); border: 1px solid #edf2f7; display: inline-flex; justify-content: center; align-items: center; margin-bottom: 12px;">
                                            <img src="<?php echo $qrUrlReal; ?>" alt="Mã QR Thanh Toán" style="width: 190px; height: 190px; object-fit: contain;">
                                        </div>
                                        
                                        <p style="font-size: 13px; color: var(--text-dark); font-weight: 700; margin: 0 0 4px 0;">Quét QR để thanh toán</p>
                                        <p style="font-size: 11px; color: var(--text-muted); margin: 0; max-width: 200px; line-height: 1.4; text-align: center;">Hệ thống tự động kích hoạt khóa học ngay sau khi nhận được tiền.</p>
                                    </div>

                                </div>
                            <?php endif; ?>

                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php include '../Includes/Footer.php'; ?>
<script src="/DevMaster/assets/Javascript.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // 1. Lấy mã ID đơn hàng cần kích hoạt từ URL (?auto_open=...)
        const urlParams = new URLSearchParams(window.location.search);
        const autoOpenOrderId = urlParams.get('auto_open');

        if (autoOpenOrderId) {
            // 2. Tìm chính xác khối đơn hàng theo thuộc tính data-order-id vừa bổ sung
            const targetOrderBlock = document.querySelector(`[data-order-id="${autoOpenOrderId}"]`);
            
            if (targetOrderBlock) {
                // 3. Định vị chính xác vùng chi tiết mở rộng dựa vào class thực tế của dự án
                const dropdownBody = targetOrderBlock.querySelector('.order-expandable-details');
                const toggleIcon = targetOrderBlock.querySelector('.fa-chevron-down');

                if (dropdownBody) {
                    // Kích hoạt hiển thị vùng chi tiết hóa đơn/QR Code
                    dropdownBody.style.display = 'block'; 
                    dropdownBody.classList.add('show', 'active');
                    
                    // Hiệu ứng xoay ngược icon mũi tên 180 độ 
                    if (toggleIcon) {
                        toggleIcon.style.transform = 'rotate(180deg)';
                    }

                    // 4. Trải nghiệm UX mượt mà: Cuộn màn hình đưa vùng QR Code vào trung tâm tầm mắt học viên
                    setTimeout(() => {
                        targetOrderBlock.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }, 400);
                }
            }
        }
    });
</script>
</body>
</html>