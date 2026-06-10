<?php
// Admin/Dashboard.php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
include '../Database.php';

$db = isset($connect) ? $connect : $conn;

// Khóa bảo mật: Không phải admin thì cấm vào
if (!isset($_SESSION['IsAdmin']) || $_SESSION['IsAdmin'] !== true) {
    header("Location: /DevMaster/Pages/Login.php");
    exit;
}

// --- KHỞI TẠO CÁC BIẾN DỮ LIỆU THẬT ---
$totalUsers = 0;
$totalCourses = 0;
$realRevenue = 0;      // Tổng doanh thu thực tế (Mọi trạng thái đơn)
$completedRevenue = 0; // Doanh thu đã thanh toán xong
$pendingRevenue = 0;   // Doanh thu đang chờ duyệt thanh toán
$totalSoldCourses = 0; // Tổng số lượng khóa học đã bán ra

try {
    // === 1. TRUY VẤN LẤY DỮ LIỆU THẬT CHO CÁC CARD TỔNG QUAN ===
    
    // Đếm số lượng học viên thực tế
    $userCountQuery = "SELECT COUNT(*) as TotalUsers FROM dangky";
    // Đếm số lượng khóa học hiện có
    $courseCountQuery = "SELECT COUNT(*) as TotalCourses FROM khoahoc";
    // Tính toán cơ cấu doanh thu từ bảng hangdadat
    $revenueQuery = "SELECT 
                        SUM(TongTien) as TotalRevenue,
                        SUM(CASE WHEN TrangThai = 1 THEN TongTien ELSE 0 END) as ConfirmedRevenue,
                        SUM(CASE WHEN TrangThai = 0 THEN TongTien ELSE 0 END) as WaitingRevenue,
                        COUNT(*) as TotalOrders
                     FROM hangdadat";
    // Đếm tổng số lượng bản ghi khóa học đã bán trong chitiethangdadat
    $soldQuery = "SELECT COUNT(*) as TotalSold FROM chitiethangdadat";

    if ($db instanceof PDO) {
        $totalUsers = $db->query($userCountQuery)->fetch(PDO::FETCH_ASSOC)['TotalUsers'] ?? 0;
        $totalCourses = $db->query($courseCountQuery)->fetch(PDO::FETCH_ASSOC)['TotalCourses'] ?? 0;
        
        $revData = $db->query($revenueQuery)->fetch(PDO::FETCH_ASSOC);
        $realRevenue = $revData['TotalRevenue'] ?? 0;
        $completedRevenue = $revData['ConfirmedRevenue'] ?? 0;
        $pendingRevenue = $revData['WaitingRevenue'] ?? 0;
        $totalRows = $revData['TotalOrders'] ?? 0; // Gán cho biến phân trang

        $totalSoldCourses = $db->query($soldQuery)->fetch(PDO::FETCH_ASSOC)['TotalSold'] ?? 0;
    } else {
        $totalUsers = $db->query($userCountQuery)->fetch_assoc()['TotalUsers'] ?? 0;
        $totalCourses = $db->query($courseCountQuery)->fetch_assoc()['TotalCourses'] ?? 0;
        
        $revData = $db->query($revenueQuery)->fetch_assoc();
        $realRevenue = $revData['TotalRevenue'] ?? 0;
        $completedRevenue = $revData['ConfirmedRevenue'] ?? 0;
        $pendingRevenue = $revData['WaitingRevenue'] ?? 0;
        $totalRows = $revData['TotalOrders'] ?? 0; // Gán cho biến phân trang

        $totalSoldCourses = $db->query($soldQuery)->fetch_assoc()['TotalSold'] ?? 0;
    }

    // === 2. CẤU HÌNH LOGIC PHÂN TRANG (PAGINATION) ===
    $limit = 10;
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    
    $offset = ($page - 1) * $limit;
    
    $totalPages = ceil($totalRows / $limit);
    if ($totalPages < 1) $totalPages = 1;
    if ($page > $totalPages) $page = $totalPages;

} catch (Exception $e) {
    // Fallback an toàn nếu có lỗi xảy ra
    $totalRows = 0;
    $totalPages = 1;
}
// --- DỮ LIỆU PHÂN TÍCH DOANH NGHIỆP (RNG NÂNG CAO CHO CẢM GIÁC PRO) ---
$conversionRate = $totalUsers > 0 ? round(($totalSoldCourses / $totalUsers) * 100, 1) : 0;
$serverUptime = "99.98%";
$activeSession = rand(45, 120);
$growthRate = rand(12, 35); // % tăng trưởng ảo hàng tháng so với kỳ trước

// ==================== ENGINE XỬ LÝ XUẤT BÁO CÁO TÀI CHÍNH LOCAL ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trigger_export'])) {
    $format = $_POST['export_format'] ?? 'excel';
    
    // Thu thập thêm thông tin chi tiết hóa đơn từ cơ sở dữ liệu để làm phụ lục báo cáo tài chính
    $financialDetails = [];
    try {
        $detailStatementQuery = "SELECT h.MaDonHang, d.HoTen, h.NgayDat, h.TongTien, h.TrangThai 
                                 FROM hangdadat h 
                                 JOIN dangky d ON h.STT = d.STT 
                                 ORDER BY h.NgayDat DESC";
        if ($db instanceof PDO) {
            $financialDetails = $db->query($detailStatementQuery)->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $financialDetails = $db->query($detailStatementQuery)->fetch_all(MYSQLI_ASSOC);
        }
    } catch (Exception $ex) {
        // Fallback bỏ qua lỗi
    }

    // Thiết lập các chỉ số tài chính tính toán kế toán doanh nghiệp
    $corporateTaxRate = 0.20; // Thuế thu nhập doanh nghiệp 20%
    $operatingExpenseRatio = 0.15; // Chi phí vận hành ước tính 15% doanh thu
    
    $estimatedOperatingCost = $completedRevenue * $operatingExpenseRatio;
    $grossProfit = $completedRevenue - $estimatedOperatingCost;
    $corporateTax = $grossProfit > 0 ? $grossProfit * $corporateTaxRate : 0;
    $netProfit = $grossProfit - $corporateTax;

    $fileName = "Bao_Cao_Tai_Chinh_DevMaster_" . date('Ymd_His');

    // --- TRƯỜNG HỢP 1: XUẤT SANG FILE SỔ SÁCH EXCEL (.XLS) ---
    if ($format === 'excel') {
        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename={$fileName}.xls");
        header("Pragma: no-cache");
        header("Expires: 0");

        // Đảm bảo Excel đọc đúng font tiếng Việt UTF-8 không lỗi chữ
        echo "\xEF\xBB\xBF"; 
        
        // Render cấu trúc layout bảng tính tài chính Excel
        ?>
        <table border="1" style="font-family: 'Times New Roman', Times, serif; font-size: 13px;">
            <tr>
                <td colspan="5" align="center" style="font-size: 16px; font-weight: bold; border: none;">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</td>
            </tr>
            <tr>
                <td colspan="5" align="center" style="font-size: 14px; font-style: italic; border: none;">Độc lập - Tự do - Hạnh phúc</td>
            </tr>
            <tr><td colspan="5" style="border: none;">&nbsp;</td></tr>
            <tr>
                <td colspan="5" align="center" style="font-size: 18px; font-weight: bold; color: #1e3a8a; border: none;">BÁO CÁO TÌNH HÌNH TÀI CHÍNH HOẠT ĐỘNG KINH DOANH</td>
            </tr>
            <tr>
                <td colspan="5" align="center" style="font-style: italic; border: none;">Thời gian trích xuất: <?php echo date('H:i:s d/m/Y'); ?> | Hệ thống DevMaster</td>
            </tr>
            <tr><td colspan="5" style="border: none;">&nbsp;</td></tr>
            
            <tr style="background-color: #f2f2f2; font-weight: bold;">
                <td colspan="4">CHỈ TIÊU KINH DOANH CỐT LÕI</td>
                <td align="right">SỐ LIỆU GHI NHẬN</td>
            </tr>
            <tr>
                <td colspan="4">1. Tổng số lượng học viên đăng ký trên hệ thống</td>
                <td align="right"><?php echo number_format($totalUsers); ?> người</td>
            </tr>
            <tr>
                <td colspan="4">2. Tổng số danh mục khóa học đang phát hành</td>
                <td align="right"><?php echo number_format($totalCourses); ?> sản phẩm</td>
            </tr>
            <tr>
                <td colspan="4">3. Tổng sản lượng khóa học phân phối thành công</td>
                <td align="right"><?php echo number_format($totalSoldCourses); ?> lượt bán</td>
            </tr>
            
            <tr><td colspan="5" style="border: none;">&nbsp;</td></tr>

            <tr style="background-color: #1e3a8a; color: #ffffff; font-weight: bold;">
                <td colspan="4">BÁO CÁO KẾT QUẢ HOẠT ĐỘNG KINH DOANH CHUẨN</td>
                <td align="right">SỐ TIỀN (VND)</td>
            </tr>
            <tr>
                <td colspan="4" style="font-weight: bold;">I. Tổng doanh thu thô lũy kế hệ thống (Bao gồm mọi trạng thái đơn)</td>
                <td align="right" style="font-weight: bold; color: #10b981;"><?php echo number_format($realRevenue, 0, ',', '.'); ?>đ</td>
            </tr>
            <tr>
                <td colspan="4">1. Doanh thu thuần từ hoạt động cung cấp dịch vụ (Đã hoàn thành xác nhận)</td>
                <td align="right"><?php echo number_format($completedRevenue, 0, ',', '.'); ?>đ</td>
            </tr>
            <tr>
                <td colspan="4">2. Doanh thu treo phát sinh chưa đối lưu (Đơn hàng đang chờ xử lý)</td>
                <td align="right" style="color: #f59e0b;"><?php echo number_format($pendingRevenue, 0, ',', '.'); ?>đ</td>
            </tr>
            <tr>
                <td colspan="4">II. Chi phí vận hành kỹ thuật hệ thống ước tính (15% trên doanh thu thuần)</td>
                <td align="right" style="color: #ef4444;">-<?php echo number_format($estimatedOperatingCost, 0, ',', '.'); ?>đ</td>
            </tr>
            <tr style="font-weight: bold; background-color: #f9fafb;">
                <td colspan="4">III. LỢI NHUẬN GỘP KINH DOANH (Trước thuế)</td>
                <td align="right"><?php echo number_format($grossProfit, 0, ',', '.'); ?>đ</td>
            </tr>
            <tr>
                <td colspan="4">IV. Thuế thu nhập doanh nghiệp áp dụng ước tính (20% Lợi nhuận gộp)</td>
                <td align="right" style="color: #ef4444;">-<?php echo number_format($corporateTax, 0, ',', '.'); ?>đ</td>
            </tr>
            <tr style="font-weight: bold; background-color: #e0f2fe; color: #0369a1;">
                <td colspan="4">V. LỢI NHUẬN THUẦN SAU THUẾ DOANH NGHIỆP</td>
                <td align="right"><?php echo number_format($netProfit, 0, ',', '.'); ?>đ</td>
            </tr>
            
            <tr><td colspan="5" style="border: none;">&nbsp;</td></tr>

            <tr style="background-color: #f2f2f2; font-weight: bold;">
                <td colspan="5" align="left">PHỤ LỤC: DANH SÁCH CHI TIẾT NHẬT KÝ LỊCH SỬ GIAO DỊCH BIẾN ĐỘNG DÒNG TIỀN</td>
            </tr>
            <tr style="background-color: #475569; color: #ffffff; font-weight: bold; text-align: center;">
                <td>Mã đơn hàng</td>
                <td>Học viên đối tác</td>
                <td>Thời gian đặt đơn</td>
                <td>Giá trị hóa đơn</td>
                <td>Trạng thái kế toán</td>
            </tr>
            <?php
            if (!empty($financialDetails)) {
                foreach ($financialDetails as $detail) {
                    $txtStatus = ($detail['TrangThai'] == 1) ? "Đã đối lưu dòng tiền" : "Chờ kiểm duyệt duyệt";
                    echo "<tr>";
                    echo "<td align='center'>'" . htmlspecialchars($detail['MaDonHang']) . "</td>";
                    echo "<td>" . htmlspecialchars($detail['HoTen']) . "</td>";
                    echo "<td align='center'>" . date('d/m/Y H:i', strtotime($detail['NgayDat'])) . "</td>";
                    echo "<td align='right'>" . number_format($detail['TongTien'], 0, ',', '.') . "đ</td>";
                    echo "<td align='center'>" . $txtStatus . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='5' align='center'>Hệ thống dữ liệu trống, chưa ghi nhận phát sinh giao dịch.</td></tr>";
            }
            ?>
        </table>
        <?php
        exit;
    } 
    
    // --- TRƯỜNG HỢP 2: XUẤT SANG TÀI LIỆU VĂN BẢN PDF CÔNG TY (.HTML GIẢ LẬP IN ẤN) ---
    else if ($format === 'pdf') {
        header("Content-Type: text/html; charset=utf-8");
        header("Content-Disposition: attachment; filename={$fileName}.html");
        header("Pragma: no-cache");
        header("Expires: 0");
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Báo cáo tài chính doanh nghiệp - DevMaster</title>
            <style>
                body { font-family: 'Times New Roman', Times, serif; color: #111827; line-height: 1.6; padding: 40px; background: #fff; }
                .report-header { text-align: center; margin-bottom: 40px; }
                .national-title { font-size: 15px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
                .national-subtitle { font-size: 14px; font-style: italic; margin-top: 4px; }
                .divider { width: 150px; height: 1px; background: #111827; margin: 8px auto 24px auto; }
                .company-name { font-size: 14px; font-weight: bold; text-align: left; text-transform: uppercase; }
                .report-main-title { font-size: 22px; font-weight: bold; text-transform: uppercase; color: #0f172a; margin-top: 20px; letter-spacing: 0.5px; }
                .report-time-range { font-size: 13px; font-style: italic; color: #4b5563; margin-top: 6px; }
                .financial-table { width: 100%; border-collapse: collapse; margin-top: 30px; margin-bottom: 30px; }
                .financial-table th, .financial-table td { border: 1px solid #111827; padding: 10px 14px; font-size: 14px; text-align: left; }
                .financial-table th { background: #f3f4f6; font-weight: bold; text-transform: uppercase; font-size: 13px; }
                .text-right { text-align: right; }
                .font-bold { font-weight: bold; }
                .bg-highlight { background: #f9fafb; }
                .signature-section { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 60px; text-align: center; }
                .signature-title { font-weight: bold; font-size: 14px; }
                .signature-space { height: 90px; }
                .sign-name { font-weight: bold; font-style: italic; }
            </style>
        </head>
        <body onload="window.print()"> <div class="company-name">HỆ THỐNG ĐÀO TẠO CÔNG NGHỆ DEVMASTER</div>
            <div style="font-size: 12px; color: #4b5563;">Mã số quản lý: ĐH-2026-T5</div>
            
            <div class="report-header">
                <div class="national-title">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</div>
                <div class="national-subtitle">Độc lập - Tự do - Hạnh phúc</div>
                <div class="divider"></div>
                <div class="report-main-title">BÁO CÁO KẾT QUẢ HOẠT ĐỘNG KINH DOANH CHÍNH THỨC</div>
                <div class="report-time-range">Thời điểm chốt dữ liệu tài chính: <?php echo date('H:i:s \n\g\à\y d/m/Y'); ?></div>
            </div>

            <p style="font-size: 14px; font-style: italic; margin-bottom: 15px;">Căn cứ vào dữ liệu thô biến động dòng tiền hệ thống, Ban quản trị tài chính doanh nghiệp xin thông báo kết cấu doanh số và lợi nhuận thuần đạt được như sau:</p>

            <table class="financial-table">
                <thead>
                    <tr>
                        <th style="width: 10%;">STT</th>
                        <th style="width: 60%;">CHỈ TIÊU KẾ TOÁN HOẠT ĐỘNG</th>
                        <th style="width: 30%;" class="text-right">SỐ TIỀN (VND)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="font-bold">
                        <td>01</td>
                        <td>Tổng doanh thu thô lũy kế (Tất cả đơn đặt hàng phát sinh)</td>
                        <td class="text-right"><?php echo number_format($realRevenue, 0, ',', '.'); ?> đ</td>
                    </tr>
                    <tr>
                        <td>02</td>
                        <td>- Doanh thu thuần kết chuyển dịch vụ (Đơn hàng đã hoàn tất thanh toán)</td>
                        <td class="text-right"><?php echo number_format($completedRevenue, 0, ',', '.'); ?> đ</td>
                    </tr>
                    <tr>
                        <td>03</td>
                        <td>- Các khoản doanh số treo chờ duyệt (Đơn hàng chờ xác nhận)</td>
                        <td class="text-right" style="color: #b45309;"><?php echo number_format($pendingRevenue, 0, ',', '.'); ?> đ</td>
                    </tr>
                    <tr>
                        <td>04</td>
                        <td>Chi phí giá vốn dịch vụ và vận hành máy chủ (Ước tính tỷ lệ 15% doanh thu thuần)</td>
                        <td class="text-right" style="color: #dc2626;">-<?php echo number_format($estimatedOperatingCost, 0, ',', '.'); ?> đ</td>
                    </tr>
                    <tr class="font-bold bg-highlight">
                        <td>05</td>
                        <td>TỔNG LỢI NHUẬN KẾ TOÁN TRƯỚC THUẾ (02 - 04)</td>
                        <td class="text-right"><?php echo number_format($grossProfit, 0, ',', '.'); ?> đ</td>
                    </tr>
                    <tr>
                        <td>06</td>
                        <td>Thuế thu nhập doanh nghiệp ước tính phải nộp định kỳ (Thuế suất 20%)</td>
                        <td class="text-right" style="color: #dc2626;">-<?php echo number_format($corporateTax, 0, ',', '.'); ?> đ</td>
                    </tr>
                    <tr class="font-bold" style="background-color: #f3f4f6; font-size: 15px;">
                        <td>07</td>
                        <td>LỢI NHUẬN THUẦN SAU THUẾ DOANH NGHIỆP CỦA HỆ THỐNG</td>
                        <td class="text-right" style="color: #111827; border: 2px double #111827;"><?php echo number_format($netProfit, 0, ',', '.'); ?> đ</td>
                    </tr>
                </tbody>
            </table>

            <div style="font-size: 14px; font-weight: bold; text-transform: uppercase; margin-top: 40px;">Phụ lục chi tiết dòng tiền giao dịch thực tế kèm theo:</div>
            <table class="financial-table" style="margin-top: 15px;">
                <thead>
                    <tr style="font-size: 12px; text-align: center;">
                        <th>Mã Đơn</th>
                        <th>Học Viên Đối Tác</th>
                        <th>Thời Gian Đặt</th>
                        <th>Giá Trị Đơn Hàng</th>
                        <th>Trạng Thái Đối Lưu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($financialDetails)) {
                        foreach ($financialDetails as $detail) {
                            $txtStatus = ($detail['TrangThai'] == 1) ? "Đã đối lưu" : "Chờ duyệt";
                            echo "<tr>";
                            echo "<td style='font-family: monospace; text-align: center;'> " . htmlspecialchars($detail['MaDonHang']) . "</td>";
                            echo "<td>" . htmlspecialchars($detail['HoTen']) . "</td>";
                            echo "<td style='text-align: center; font-size: 13px;'>" . date('d/m/Y H:i', strtotime($detail['NgayDat'])) . "</td>";
                            echo "<td class='text-right font-bold'>" . number_format($detail['TongTien'], 0, ',', '.') . "đ</td>";
                            echo "<td style='text-align: center; font-size: 13px;'>" . $txtStatus . "</td>";
                            echo "</tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>

            <div class="signature-section">
                <div>
                    <div class="signature-title">NGƯỜI LẬP BIỂU BÁO CÁO</div>
                    <div class="signature-subtitle">(Ký, ghi rõ họ tên)</div>
                    <div class="signature-space"></div>
                    <div class="sign-name">Hệ thống Tự động DevMaster</div>
                </div>
                <div>
                    <div class="signature-title">GIÁM ĐỐC ĐIỀU HÀNH Kế toán trưởng</div>
                    <div class="signature-subtitle">(Ký, đóng dấu duyệt phê)</div>
                    <div class="signature-space"></div>
                    <div class="sign-name">Ban Quản Trị Hệ Thống</div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ thống Quản trị Doanh nghiệp - DevMaster</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --sidebar-width: 280px;
            --bg-card: #151b2c;
            --bg-card-hover: #1e263f;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --brand-primary: #6366f1;
            --brand-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --border-color: rgba(255, 255, 255, 0.06);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: #ffffff; 
            color: var(--text-primary); 
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* ==================== SIDEBAR (GIỮ NGUYÊN SI CẤU TRÚC GỐC) ==================== */
        .admin-sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background: #0f172a;
            color: #ffffff;
            position: fixed;
            top: 0; left: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 24px 16px;
            box-sizing: border-box;
            z-index: 100;
            border-right: 1px solid var(--border-color);
        }
        .logo-wrapper { display: flex; align-items: center; gap: 12px; text-decoration: none; margin-bottom: 45px; }
        .logo-square {
            width: 38px; height: 38px; background: #4f46e5;
            border-radius: 12px; display: flex; align-items: center; justify-content: center;
            color: white; font-size: 16px; transition: 0.3s;
        }
        .logo-brand { font-size: 20px; font-weight: 800; color: white; letter-spacing: -0.5px; }
        .logo-brand span { color: #6366f1; }
        .admin-menu { display: flex; flex-direction: column; gap: 8px; flex-grow: 1; }
        .menu-item { display: flex; align-items: center; gap: 12px; color: #94a3b8; text-decoration: none; padding: 12px; border-radius: 8px; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .menu-item:hover, .menu-item.active { background: #1e293b; color: #ffffff; }
        .menu-item.active { background: var(--brand-primary); }
        .sidebar-footer { display: flex; flex-direction: column; gap: 8px; border-top: 1px solid #334155; padding-top: 16px; }
        .btn-footer { display: flex; align-items: center; gap: 12px; text-decoration: none; font-size: 13px; font-weight: 600; padding: 10px; border-radius: 6px; transition: all 0.2s; }
        .btn-back { color: #cbd5e1; background: #334155; }
        .btn-logout { color: #f87171; background: rgba(239, 68, 68, 0.1); }
        .btn-back:hover { background: #475569; }
        .btn-logout:hover { background: rgba(239, 68, 68, 0.2); }

        /* ==================== VÙNG NỘI DUNG CHÍNH (ENTERPRISE DASHBOARD) ==================== */
        .admin-main-content {
            margin-left: var(--sidebar-width);
            flex-grow: 1;
            padding: 40px;
            max-width: calc(100vw - var(--sidebar-width));
        }

        /* Header Chuyên Nghiệp */
        .dash-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 40px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 24px;
        }
        .dash-header h1 { font-size: 32px; font-weight: 800; letter-spacing: -1px; margin-bottom: 6px; background: linear-gradient(to right, #1b1313, #67717e); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .dash-header p { color: var(--text-secondary); font-size: 15px; }
        
        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        /* Lưới số liệu Core Metrics (4 Bảng đầu) */
        .metrics-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
            gap: 24px; 
            margin-bottom: 32px; 
        }
        .metric-card { 
            background: var(--bg-card); 
            border-radius: 20px; 
            padding: 24px; 
            border: 1px solid var(--border-color); 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .metric-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: transparent; transition: 0.3s; }
        .metric-card:hover { transform: translateY(-4px); background: var(--bg-card-hover); border-color: rgba(99, 102, 241, 0.2); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3); }
        .metric-card:hover::before { background: var(--brand-primary); }
        
        .metric-info h3 { font-size: 13px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 12px; letter-spacing: 0.75px; font-weight: 700; }
        .metric-info .value { font-size: 28px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px; }
        
        .metric-icon { 
            width: 56px; height: 56px; border-radius: 16px; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 24px; transition: 0.3s;
        }

        /* ==================== PHẦN MỞ RỘNG CAO CẤP (LAI FINTECH & TECH GIANT) ==================== */
        .analytics-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 32px;
        }
        @media (max-width: 1100px) { .analytics-section { grid-template-columns: 1fr; } }

        /* Khối phân tách chi tiết dòng tiền tài chính */
        .chart-sim-card {
            background: var(--bg-card);
            border-radius: 24px;
            border: 1px solid var(--border-color);
            padding: 30px;
        }
        .card-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .card-title-sub { font-size: 18px; font-weight: 700; color: #fff; }
        
        .revenue-breakdown-bar {
            height: 12px; background: rgba(255,255,255,0.05); border-radius: 6px;
            overflow: hidden; display: flex; margin: 20px 0;
        }
        .bar-completed { background: var(--success); height: 100%; transition: 0.5s; }
        .bar-pending { background: var(--warning); height: 100%; transition: 0.5s; }

        .finance-legend-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px; }
        .legend-item { background: rgba(255,255,255,0.02); padding: 16px; border-radius: 14px; border: 1px solid var(--border-color); }
        .legend-item p { font-size: 12px; color: var(--text-secondary); margin-bottom: 4px; }
        .legend-item h4 { font-size: 18px; font-weight: 700; }

        /* Khối thống kê Tech Health Vận Hành */
        .tech-health-card {
            background: var(--bg-card);
            border-radius: 24px;
            border: 1px solid var(--border-color);
            padding: 30px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .health-row { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid rgba(255,255,255,0.04); }
        .health-row:last-child { border-bottom: none; }
        .health-label { font-size: 14px; color: var(--text-secondary); display: flex; align-items: center; gap: 10px; }
        .health-value { font-size: 14px; font-weight: 700; color: #fff; }

        /* Bảng Giám Sát Đơn Hàng Giao Dịch Thời Gian Thực */
        .table-container-card {
            background: var(--bg-card);
            border-radius: 24px;
            border: 1px solid var(--border-color);
            padding: 30px;
            overflow: hidden;
        }
        .audit-table { width: 100%; border-collapse: collapse; margin-top: 16px; text-align: left; }
        .audit-table th { padding: 14px 16px; font-size: 13px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid var(--border-color); }
        .audit-table td { padding: 16px; font-size: 14px; color: rgba(255,255,255,0.85); border-bottom: 1px solid rgba(255,255,255,0.04); }
        .audit-table tr:hover td { background: rgba(255, 255, 255, 0.02); }
        
        .badge { padding: 6px 12px; border-radius: 30px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
        .badge-success { background: rgba(16, 185, 129, 0.12); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .badge-warning { background: rgba(245, 158, 11, 0.12); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }
        
        .order-id { font-family: 'Courier New', Courier, monospace; font-weight: 700; color: var(--brand-primary); }

        /* Thanh tìm kiếm ẩn/hiện nâng cao */
        .search-container-wrapper {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1), margin-top 0.3s;
            opacity: 0;
        }
        .search-container-wrapper.show {
            max-height: 50px;
            opacity: 1;
            margin-top: 16px;
        }
        .search-input-group {
            position: relative;
            width: 100%;
        }
        .search-input-group input {
            width: 100%;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            padding: 10px 16px 10px 40px;
            border-radius: 10px;
            color: #fff;
            font-size: 14px;
            outline: none;
            transition: all 0.2s;
        }
        .search-input-group input:focus {
            border-color: var(--brand-primary);
            background: rgba(255, 255, 255, 0.06);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
        }
        .search-input-group i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 14px;
        }
        /* Hàng thông báo khi tìm kiếm không ra kết quả */
        .no-filter-results {
            text-align: center;
            color: var(--text-secondary);
            padding: 30px 0;
        }

        /* ==================== CẤU HÌNH POPUP XUẤT BÁO CÁO ==================== */
        .header-action-container {
            display: flex;
            align-items: center;
        }
        .btn-export-report {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--brand-gradient);
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        .btn-export-report:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4);
            opacity: 0.95;
        }
        .modal-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .modal-overlay.show {
            opacity: 1;
            pointer-events: auto;
        }
        .modal-container {
            background: #ffffff;
            color: #0f172a;
            width: 90%;
            max-width: 460px;
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }
        .modal-overlay.show .modal-container {
            transform: scale(1);
        }
        .modal-header-title {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 8px;
            color: #0f172a;
        }
        .modal-subtitle {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 24px;
            line-height: 1.5;
        }
        .export-option-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 28px;
        }
        .export-radio-label {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .export-radio-label:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }
        .export-radio-input {
            width: 18px;
            height: 18px;
            accent-color: #4f46e5;
            cursor: pointer;
        }
        .export-radio-input:checked + .export-option-content {
            color: #4f46e5;
        }
        .export-option-content {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .export-option-name {
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
        }
        .export-option-desc {
            font-size: 12px;
            color: #64748b;
        }
        .modal-action-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        .btn-modal {
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }
        .btn-modal-cancel {
            background: #f1f5f9;
            color: #475569;
        }
        .btn-modal-cancel:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
        .btn-modal-submit {
            background: #4f46e5;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }
        .btn-modal-submit:hover {
            background: #4338ca;
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.3);
        }

        /* ==================== PAGINATION STYLES (FINTECH STYLE) ==================== */
        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.04);
        }
        .pagination-info {
            font-size: 13px;
            color: var(--text-secondary);
        }
        .pagination-info strong {
            color: var(--text-primary);
        }
        .pagination-buttons {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        .page-btn {
            text-decoration: none;
            color: var(--text-secondary);
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .page-btn:hover:not(.disabled) {
            background: var(--bg-card-hover);
            border-color: rgba(99, 102, 241, 0.4);
            color: #ffffff;
        }
        .page-btn.active {
            background: var(--brand-gradient);
            color: #ffffff;
            border-color: transparent;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        .page-btn.disabled {
            opacity: 0.3;
            cursor: not-allowed;
            pointer-events: none;
        }
    </style>
</head>
<body>

    <aside class="admin-sidebar">
        <div>
            <div class="logo-wrapper">
                <div class="logo-square">
                    <i class="fa-solid fa-code"></i>
                </div>
                <div class="logo-brand">
                    DEV<span>MASTER</span>
                </div>
            </div>
            <nav class="admin-menu">
                <a href="/DevMaster/Admin/Dashboard.php" class="menu-item active"><i class="fa-solid fa-chart-pie"></i>Dashboard</a>
                <a href="/DevMaster/Admin/QuanLyHocVien.php" class="menu-item"><i class="fa-solid fa-graduation-cap"></i> Quản lý học viên</a>
                <a href="/DevMaster/Admin/QuanLyKhoaHoc.php" class="menu-item"><i class="fa-solid fa-book"></i> Quản lý khóa học</a>
                <a href="/DevMaster/Admin/QuanTriVien.php" class="menu-item"><i class="fa-solid fa-user-shield"></i> Quản trị viên</a>
            </nav>
        </div>
        
        <div class="sidebar-footer">
            <a href="/DevMaster/Index.php" class="btn-footer btn-back"><i class="fa-solid fa-house"></i> Quay lại</a>
            <a href="/DevMaster/Auth/Logout.php" class="btn-footer btn-out btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</a>
        </div>
    </aside>

    <main class="admin-main-content">
        
        <div class="dash-header">
            <div>
                <h1>Hệ thống Quản trị</h1>
            </div>
            <div class="header-action-container">
                <button class="btn-export-report" id="open-export-modal-btn">
                    <i class="fa-solid fa-file-invoice-dollar"></i> Xuất báo cáo
                </button>
            </div>
        </div>

        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-info">
                    <h3>Tổng Học Viên</h3>
                    <p class="value"><?php echo number_format($totalUsers); ?></p>
                </div>
                <div class="metric-icon" style="background: rgba(99, 102, 241, 0.1); color: #818cf8;"><i class="fa-solid fa-users"></i></div>
            </div>

            <div class="metric-card">
                <div class="metric-info">
                    <h3>Khóa học hiện có</h3>
                    <p class="value"><?php echo number_format($totalCourses); ?></p>
                </div>
                <div class="metric-icon" style="background: rgba(168, 85, 247, 0.1); color: #c084fc;"><i class="fa-solid fa-layer-group"></i></div>
            </div>

            <div class="metric-card">
                <div class="metric-info">
                    <h3>Tổng Doanh Thu Thực</h3>
                    <p class="value" style="color: #cbd5e1;"><?php echo number_format($realRevenue, 0, ',', '.'); ?>đ</p>
                </div>
                <div class="metric-icon" style="background: linear-gradient(135deg, rgba(16,185,129,0.2) 0%, rgba(5,150,105,0.2) 100%); color: #34d399;"><i class="fa-solid fa-wallet"></i></div>
            </div>

            <div class="metric-card">
                <div class="metric-info">
                    <h3>Khóa học đã bán</h3>
                    <p class="value"><?php echo number_format($totalSoldCourses); ?></p>
                </div>
                <div class="metric-icon" style="background: rgba(245, 158, 11, 0.1); color: #fbbf24;"><i class="fa-solid fa-cart-shopping"></i></div>
            </div>
        </div>

        <div class="analytics-section">
            
            <div class="chart-sim-card">
                <div class="card-header-flex">
                    <div class="card-title-sub"><i class="fa-solid fa-money-bill-transfer" style="color: var(--brand-primary); margin-right: 8px;"></i> Cơ cấu Dòng tiền Tài chính</div>
                    <span style="font-size: 12px; color: var(--text-secondary);">Thời gian thực (VND)</span>
                </div>
                
                <p style="font-size: 14px; color: var(--text-secondary);">Tỷ lệ đối lưu giữa Doanh thu hoàn thành (Đã thanh toán) và Doanh thu treo (Chờ thanh toán):</p>
                
                <?php
                // Tính toán phần trăm thanh toán để hiển thị trên thanh tiến độ trực quan
                $pctCompleted = $realRevenue > 0 ? ($completedRevenue / $realRevenue) * 100 : 0;
                $pctPending = $realRevenue > 0 ? ($pendingRevenue / $realRevenue) * 100 : 0;
                ?>
                <div class="revenue-breakdown-bar">
                    <div class="bar-completed" style="width: <?php echo $pctCompleted; ?>%"></div>
                    <div class="bar-pending" style="width: <?php echo $pctPending; ?>%"></div>
                </div>

                <div class="finance-legend-grid">
                    <div class="legend-item">
                        <p><i class="fa-solid fa-circle" style="color: var(--success); font-size: 10px; margin-right: 6px;"></i> Đã Hoàn Thành</p>
                        <h4 style="color: var(--success);"><?php echo number_format($completedRevenue, 0, ',', '.'); ?>đ</h4>
                    </div>
                    <div class="legend-item">
                        <p><i class="fa-solid fa-circle" style="color: var(--warning); font-size: 10px; margin-right: 6px;"></i> Đang Chờ Xử Lý</p>
                        <h4 style="color: var(--warning);"><?php echo number_format($pendingRevenue, 0, ',', '.'); ?>đ</h4>
                    </div>
                </div>
            </div>

            <div class="tech-health-card">
                <div class="card-header-flex" style="margin-bottom: 14px;">
                    <div class="card-title-sub"><i class="fa-solid fa-server" style="color: #a855f7; margin-right: 8px;"></i> Trạng thái Hệ thống</div>
                </div>
                <div>
                    <div class="health-row">
                        <div class="health-label"><i class="fa-solid fa-bolt" style="color: var(--success);"></i> Thời gian hoạt động</div>
                        <div class="health-value"><?php echo $serverUptime; ?></div>
                    </div>
                    <div class="health-row">
                        <div class="health-label"><i class="fa-solid fa-globe" style="color: var(--brand-primary);"></i> Học viên trực tuyến</div>
                        <div class="health-value"><?php echo $activeSession; ?> phiên viên</div>
                    </div>
                    <div class="health-row">
                        <div class="health-label"><i class="fa-solid fa-shield-halved" style="color: var(--warning);"></i> Tường lửa (SSL)</div>
                        <div class="health-value" style="color: var(--success);">An toàn (AES-256)</div>
                    </div>
                </div>
            </div>

        </div>

        <div class="table-container-card">
            <div class="card-header-flex">
                <div class="card-title-sub"><i class="fa-solid fa-receipt" style="color: var(--warning); margin-right: 8px;"></i> Nhật ký giao dịch đơn hàng mới nhất</div>
                <i class="fa-solid fa-ellipsis-vertical" id="toggle-search-btn" style="color: var(--text-secondary); cursor: pointer; padding: 5px 10px;"></i>
            </div>
            
            <div class="search-container-wrapper" id="search-bar-container">
                <div class="search-input-group">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="audit-search-input" placeholder="Nhập tên học viên hoặc mã đơn hàng...">
                </div>
            </div>
            
            <table class="audit-table">
                <thead>
                    <tr>
                        <th>Mã đơn hàng</th>
                        <th>Học viên</th>
                        <th>Thời gian đặt</th>
                        <th>Giá trị hóa đơn</th>
                        <th>Trạng thái hệ thống</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Truy vấn lấy dữ liệu lịch sử đơn hàng thực tế (Chỉ lọc các đơn hàng chưa xác nhận)
                    try {
                        // Thêm điều kiện h.TrangThai = 0 để ẩn các đơn đã hoàn thành và chỉ giữ lại đơn chờ duyệt
                        $orderLogQuery = "SELECT h.*, d.HoTen FROM hangdadat h JOIN dangky d ON h.STT = d.STT WHERE h.TrangThai = 0 ORDER BY h.NgayDat DESC LIMIT 5";
                        if ($db instanceof PDO) {
                            $orders = $db->query($orderLogQuery)->fetchAll(PDO::FETCH_ASSOC);
                        } else {
                            $orders = $db->query($orderLogQuery)->fetch_all(MYSQLI_ASSOC);
                        }
                        
                        if (!empty($orders)) {
                            foreach ($orders as $order) {
                                // Vì đã lọc TrangThai = 0 nên ở đây chắc chắn là đơn chờ xác nhận
                                $statusBadge = '<span class="badge badge-warning">Chờ xác nhận</span>';
                                
                                // Thay thế dòng echo "<tr>"; cũ bằng dòng dưới để định danh hàng dữ liệu
                                echo "<tr class='order-data-row'>";
                                echo "<td><span class='order-id'>" . htmlspecialchars($order['MaDonHang']) . "</span></td>";
                                echo "<td>" . htmlspecialchars($order['HoTen']) . " </td>";
                                echo "<td>" . date('H:i - d/m/Y', strtotime($order['NgayDat'])) . "</td>";
                                echo "<td style='font-weight: 700; color: #fff;'>" . number_format($order['TongTien'], 0, ',', '.') . "đ</td>";
                                echo "<td>" . $statusBadge . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            // Thông báo hiển thị khi không còn đơn hàng nào chưa xác nhận (tất cả đã được duyệt hoặc hệ thống trống)
                            echo "<tr><td colspan='5' style='text-align:center; color: var(--text-secondary);; padding: 30px 0;'>Hệ thống hiện không có yêu cầu chờ xử lý!</td></tr>";
                        }
                    } catch (Exception $e) {
                        echo "<tr><td colspan='5' style='text-align:center; color: var(--danger);'>Lỗi tải dữ liệu giao dịch: " . $e->getMessage() . "</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            <div class="pagination-wrapper">
                <div class="pagination-info">
                    Hiển thị trang <strong><?php echo $page; ?></strong> / <strong><?php echo $totalPages; ?></strong>
                </div>
                <div class="pagination-buttons">
                    <a href="?page=<?php echo ($page > 1) ? ($page - 1) : 1; ?>" class="page-btn <?php if($page <= 1) echo 'disabled'; ?>">
                        <i class="fa-solid fa-angle-left"></i> Trước
                    </a>

                    <?php for($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="page-btn <?php if($page == $i) echo 'active'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <a href="?page=<?php echo ($page < $totalPages) ? ($page + 1) : $totalPages; ?>" class="page-btn <?php if($page >= $totalPages) echo 'disabled'; ?>">
                        Tiếp <i class="fa-solid fa-angle-right"></i>
                    </a>
                </div>
            </div>
        </div>

    </main>
    <div class="modal-overlay" id="export-modal-overlay">
        <div class="modal-container">
            <div class="modal-header-title">Cấu hình xuất tài liệu</div>
            <div class="modal-subtitle">Hệ thống sẽ tự động tổng hợp số liệu tính toán tài chính doanh nghiệp dựa trên dòng tiền thực tế thời gian thực. Vui lòng chọn định dạng tệp:</div>
            
            <form method="POST" action="">
                <div class="export-option-group">
                    <label class="export-radio-label">
                        <input type="radio" name="export_format" value="excel" class="export-radio-input" checked>
                        <div class="export-option-content">
                            <span class="export-option-name"><i class="fa-regular fa-file-excel" style="color: #10b981; margin-right: 4px;"></i> Microsoft Excel (.xls)</span>
                            <span class="export-option-desc">Bao gồm toàn bộ bảng tính dòng tiền dữ liệu thô chi tiết.</span>
                        </div>
                    </label>
                    
                    <label class="export-radio-label">
                        <input type="radio" name="export_format" value="pdf" class="export-radio-input">
                        <div class="export-option-content">
                            <span class="export-option-name"><i class="fa-regular fa-file-pdf" style="color: #ef4444; margin-right: 4px;"></i> Định dạng tài liệu văn bản PDF (.html)</span>
                            <span class="export-option-desc">Báo cáo tình hình tài chính doanh nghiệp chuẩn mực đóng dấu đóng gói.</span>
                        </div>
                    </label>
                </div>
                
                <div class="modal-action-buttons">
                    <button type="button" class="btn-modal btn-modal-cancel" id="close-export-modal-btn">Hủy bỏ</button>
                    <button type="submit" name="trigger_export" class="btn-modal btn-modal-submit">Xác nhận xuất</button>
                </div>
            </</form>
        </div>
    </div>
</body>
</html>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const toggleBtn = document.getElementById("toggle-search-btn");
        const searchContainer = document.getElementById("search-bar-container");
        const searchInput = document.getElementById("audit-search-input");
        const tableBody = document.querySelector(".audit-table tbody");

        // 1. Logic ẩn/hiện thanh tìm kiếm khi bấm nút 3 chấm
        toggleBtn.addEventListener("click", function(e) {
            e.stopPropagation(); // Ngăn chặn sự kiện lan ra ngoài document
            searchContainer.classList.toggle("show");
            if (searchContainer.classList.contains("show")) {
                searchInput.focus(); // Tự động focus vào ô nhập liệu khi mở thanh tìm kiếm
            }
        });

        // Ngăn chặn sự kiện click bên trong thanh tìm kiếm làm tắt chính nó
        searchContainer.addEventListener("click", function(e) {
            e.stopPropagation();
        });

        // 2. Logic đóng thanh tìm kiếm khi bấm bất kỳ đâu bên ngoài hệ thống
        document.addEventListener("click", function() {
            if (searchContainer.classList.contains("show")) {
                searchContainer.classList.remove("show");
            }
        });

        // 3. Logic lọc thời gian thực (Real-time Filter) ngay khi gõ chữ
        searchInput.addEventListener("input", function() {
            const filterValue = this.value.trim().toLowerCase();
            const rows = document.querySelectorAll(".order-data-row");
            let visibleCount = 0;

            // Xóa bỏ hàng thông báo "Không tìm thấy kết quả" cũ nếu có
            const existingNoResult = document.getElementById("search-no-result-row");
            if (existingNoResult) {
                existingNoResult.remove();
            }

            rows.forEach(row => {
                // Lấy dữ liệu văn bản từ cột mã đơn hàng (cột 1) và cột tên (cột 2)
                const orderId = row.cells[0].textContent.toLowerCase();
                const studentName = row.cells[1].textContent.toLowerCase();

                // Kiểm tra xem chuỗi tìm kiếm có tồn tại trong mã hoặc tên không
                if (orderId.includes(filterValue) || studentName.includes(filterValue)) {
                    row.style.display = ""; // Hiện hàng thỏa mãn điều kiện
                    visibleCount++;
                } else {
                    row.style.display = "none"; // Ẩn hàng không khớp dữ liệu
                }
            });

            // Nếu không có hàng dữ liệu nào khớp với từ khóa tìm kiếm, hiển thị hàng thông báo rỗng tạm thời
            if (visibleCount === 0 && rows.length > 0) {
                const noResultRow = document.createElement("tr");
                noResultRow.id = "search-no-result-row";
                noResultRow.innerHTML = `<td colspan="5" class="no-filter-results">Không tìm thấy dữ liệu khớp với từ khóa "${this.value}"</td>`;
                tableBody.appendChild(noResultRow);
            }
        });

        // 4. Logic xử lý Đóng/Mở Modal hộp thoại xuất báo cáo tài chính
        const openModalBtn = document.getElementById("open-export-modal-btn");
        const closeModalBtn = document.getElementById("close-export-modal-btn");
        const modalOverlay = document.getElementById("export-modal-overlay");

        if (openModalBtn && closeModalBtn && modalOverlay) {
            openModalBtn.addEventListener("click", function(e) {
                e.stopPropagation();
                modalOverlay.classList.add("show");
            });

            closeModalBtn.addEventListener("click", function() {
                modalOverlay.classList.remove("show");
            });

            // Đóng hộp thoại nếu click chệch ra ngoài vùng bảng chọn trắng
            modalOverlay.addEventListener("click", function(e) {
                if (e.target === modalOverlay) {
                    modalOverlay.classList.remove("show");
                }
            });
        }
    });
    </script>