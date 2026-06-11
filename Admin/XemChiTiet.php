<?php
// Admin/XemChiTiet.php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
include '../Database.php';
$db = isset($connect) ? $connect : $conn;

if (!isset($_SESSION['IsAdmin']) || $_SESSION['IsAdmin'] !== true) {
    header("Location: /DevMaster/Auth/Login.php");
    exit;
}

$studentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 1. Lấy tên học viên hiển thị trên Header
$studentInfo = null;
try {
    $studentQuery = "SELECT HoTen FROM dangky WHERE STT = ?";
    if ($db instanceof PDO) {
        $stmt = $db->prepare($studentQuery);
        $stmt->execute([$studentId]);
        $studentInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->prepare($studentQuery);
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $studentInfo = $stmt->get_result()->fetch_assoc();
    }
} catch (Exception $e) {}

if (!$studentInfo) {
    header("Location: QuanLyHocVien.php");
    exit;
}

// 2. Xử lý Cập nhật Trạng thái Đơn hàng vĩnh viễn sang Hoàn thành (Khi Admin bấm Xác nhận)
if (isset($_GET['action']) && $_GET['action'] === 'confirm_order' && isset($_GET['order_id'])) {
    $orderIdToConfirm = intval($_GET['order_id']);
    try {
        $updateOrderSql = "UPDATE hangdadat SET TrangThai = 1 WHERE HangDaDatId = ? AND STT = ?";
        if ($db instanceof PDO) {
            $stmt = $db->prepare($updateOrderSql);
            $stmt->execute([$orderIdToConfirm, $studentId]);
        } else {
            $stmt = $db->prepare($updateOrderSql);
            $stmt->bind_param("ii", $orderIdToConfirm, $studentId);
            $stmt->execute();
        }
        header("Location: XemChiTiet.php?id=" . $studentId);
        exit;
    } catch (Exception $e) {}
}

// 2b. Xử lý Xóa đơn hàng chờ xác nhận theo yêu cầu hủy của học viên
if (isset($_GET['action']) && $_GET['action'] === 'delete_order' && isset($_GET['order_id'])) {
    $orderIdToDelete = intval($_GET['order_id']);
    try {
        // Định nghĩa câu lệnh xóa chi tiết đơn hàng trước để tránh lỗi ràng buộc khóa ngoại (Foreign Key)
        $deleteDetailsSql = "DELETE FROM chitiethangdadat WHERE HangDaDatId = ?";
        // Định nghĩa câu lệnh xóa đơn hàng chính trong bảng hangdadat (ảnh hưởng trực tiếp đến hiển thị tại DonHang.php)
        $deleteOrderSql = "DELETE FROM hangdadat WHERE HangDaDatId = ? AND STT = ? AND TrangThai = 0";
        
        if ($db instanceof PDO) {
            // Thực thi xóa trên driver PDO
            $stmt1 = $db->prepare($deleteDetailsSql);
            $stmt1->execute([$orderIdToDelete]);
            
            $stmt2 = $db->prepare($deleteOrderSql);
            $stmt2->execute([$orderIdToDelete, $studentId]);
        } else {
            // Thực thi xóa trên driver MySQLi
            $stmt1 = $db->prepare($deleteDetailsSql);
            $stmt1->bind_param("i", $orderIdToDelete);
            $stmt1->execute();
            
            $stmt2 = $db->prepare($deleteOrderSql);
            $stmt2->bind_param("ii", $orderIdToDelete, $studentId);
            $stmt2->execute();
        }
        // Chuyển hướng tải lại trang ngay lập tức mà không hiển thị thông báo alert
        header("Location: XemChiTiet.php?id=" . $studentId);
        exit;
    } catch (Exception $e) {}
}

// 3. Lấy danh sách toàn bộ đơn hàng của học viên này
$ordersList = [];
$grandTotal = 0;
try {
    $ordersQuery = "SELECT HangDaDatId, MaDonHang, NgayDat, TongTien, TrangThai FROM hangdadat WHERE STT = ? ORDER BY NgayDat DESC";
    if ($db instanceof PDO) {
        $stmt = $db->prepare($ordersQuery);
        $stmt->execute([$studentId]);
        $ordersList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->prepare($ordersQuery);
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $ordersList[] = $row;
        }
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chi Tiết Học Viên - DevMaster</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 260px;
            --admin-primary: #4f46e5;
            --admin-bg: #f8fafc;
            --admin-card: #ffffff;
            --admin-text: #1e293b;
            --admin-muted: #64748b;
        }
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--admin-bg); color: var(--admin-text); display: flex; }
        
        .admin-sidebar {
            width: var(--sidebar-width); height: 100vh; background: #0f172a; color: #ffffff;
            position: fixed; top: 0; left: 0; display: flex; flex-direction: column;
            justify-content: space-between; padding: 24px 16px; box-sizing: border-box; z-index: 100;
        }
        .logo-wrapper { display: flex; align-items: center; gap: 12px; text-decoration: none; margin-bottom: 45px; }
        .logo-square { width: 38px; height: 38px; background: #4f46e5; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 16px; }
        .logo-brand { font-size: 20px; font-weight: 800; color: white; letter-spacing: -0.5px; }
        .logo-brand span { color: #6366f1; }
        .admin-menu { display: flex; flex-direction: column; gap: 8px; flex-grow: 1; }
        .menu-item { display: flex; align-items: center; gap: 12px; color: #94a3b8; text-decoration: none; padding: 12px; border-radius: 8px; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .menu-item:hover, .menu-item.active { background: #1e293b; color: #ffffff; }
        .menu-item.active { background: var(--admin-primary); }
        .sidebar-footer { display: flex; flex-direction: column; gap: 8px; border-top: 1px solid #334155; padding-top: 16px; }
        .btn-footer { display: flex; align-items: center; gap: 12px; text-decoration: none; font-size: 13px; font-weight: 600; padding: 10px; border-radius: 6px; }
        .btn-back { color: #cbd5e1; background: #334155; }
        .btn-logout { color: #f87171; background: rgba(239, 68, 68, 0.1); }

        .admin-main-content { margin-left: var(--sidebar-width); flex-grow: 1; padding: 40px; box-sizing: border-box; }
        
        .detail-navigation-header { display: flex; align-items: flex-start; gap: 20px; margin-bottom: 25px; }
        .btn-circle-return {
            width: 44px; height: 44px; border-radius: 50%; background: #ffffff; border: 1px solid #e2e8f0;
            display: flex; align-items: center; justify-content: center; color: var(--admin-text);
            text-decoration: none; font-size: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); margin-top: 2px;
        }
        .dash-header h1 { font-size: 28px; font-weight: 800; margin: 0 0 8px 0; }
        .student-subtitle { font-size: 16px; color: #4f46e5; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 6px; }

        /* Khối tổng số tiền toàn bộ đặt lên đầu trang */
        .grand-total-container {
            background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px;
            padding: 16px 24px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
        }
        .grand-total-label { font-size: 15px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; }
        .grand-total-value { font-size: 24px; font-weight: 900; color: #10b981; }

        /* Định dạng bảng riêng biệt theo từng đơn hàng */
        .order-block { margin-bottom: 30px; }
        .table-wrapper {
            background: var(--admin-card); border-radius: 16px;
            border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            overflow: visible; /* Cần thiết để Sticky hoạt động bên trong */
        }
        .detail-course-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .detail-course-table th {
            background: #f8fafc; color: #475569; font-weight: 700; font-size: 14px;
            padding: 18px 24px; border-bottom: 2px solid #e2e8f0; text-align: center;
        }
        .detail-course-table td { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; color: #1e293b; font-size: 15px; vertical-align: middle; }
        
        .composite-course-card { display: flex; align-items: center; gap: 20px; }
        .composite-course-card img { width: 120px; height: 75px; border-radius: 10px; object-fit: cover; border: 1px solid #e2e8f0; flex-shrink: 0; }
        .composite-course-details { display: flex; flex-direction: column; gap: 6px; }
        .composite-course-title { font-weight: 800; color: #0f172a; font-size: 17px; margin: 0; }
        .composite-course-price { font-size: 15px; font-weight: 700; color: #4f46e5; margin: 0; }

        .progress-container { width: 100%; max-width: 200px; display: inline-block; text-align: left; }
        .progress-bar-bg { width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; margin-bottom: 6px; }
        .progress-bar-fill { height: 100%; background: #4f46e5; border-radius: 4px; width: 0%; }
        .progress-percentage { font-size: 13px; font-weight: 700; color: #64748b; }

        /* ==========================================================================
           LOGIC UI STICKY CHO CỘT TRẠNG THÁI ĐƠN HÀNG VÀ CHỮ TRƯỢT THEO KHUNG Ô
           ========================================================================== */
        .sticky-status-cell {
            position: relative;
            vertical-align: top !important;
            padding: 0 !important; /* Xóa padding ô để thành phần con bám sát biên */
        }
        .sticky-status-wrapper {
            position: -webkit-sticky;
            position: sticky;
            top: 20px; /* Khoảng cách neo khi cuộn màn hình xuống */
            padding: 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        /* Giao diện Dropdown tùy biến không có dấu mũi tên xấu xí */
        .custom-dropdown { position: relative; display: inline-block; }
        .badge-pending-status {
            padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 700;
            background: #fef3c7; color: #d97706; cursor: pointer; border: 1px solid #fde68a;
            user-select: none; transition: 0.2s; text-align: center; display: inline-block;
        }
        .badge-pending-status:hover { background: #fde68a; }
        
        .dropdown-content-menu {
            display: none; position: absolute; top: 105%; left: 50%; transform: translateX(-50%);
            background-color: #ffffff; min-width: 120px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            border-radius: 6px; border: 1px solid #e2e8f0; z-index: 50; overflow: hidden;
        }
        .dropdown-content-menu a {
            color: #111827; padding: 10px 16px; text-decoration: none; display: block;
            font-size: 13px; font-weight: 600; text-align: center; transition: 0.2s;
        }
        .dropdown-content-menu a:hover { background-color: #f3f4f6; color: #10b981; }
        .custom-dropdown.open .dropdown-content-menu { display: block; }

        .badge-completed-status {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 700;
            background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0;
        }

        .table-summary-row td { background: #f8fafc; border-top: 2px solid #e2e8f0; padding: 18px 24px; }
        .summary-flex-container { display: flex; justify-content: space-between; align-items: center; width: 100%; }
        .summary-label-text { font-size: 14px; font-weight: 700; color: #475569; }
        .summary-value-amount { font-size: 18px; font-weight: 800; color: #0f172a; }
    </style>
</head>
<body>

    <aside class="admin-sidebar">
        <div>
            <div class="logo-wrapper">
                <div class="logo-square"><i class="fa-solid fa-code"></i></div>
                <div class="logo-brand">DEV<span>MASTER</span></div>
            </div>
            <nav class="admin-menu">
                <a href="/DevMaster/Admin/Dashboard.php" class="menu-item"><i class="fa-solid fa-chart-pie"></i>Dashboard</a>
                <a href="/DevMaster/Admin/QuanLyHocVien.php" class="menu-item active"><i class="fa-solid fa-graduation-cap"></i> Quản lý học viên</a>
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
        <div class="detail-navigation-header">
            <a href="QuanLyHocVien.php" class="btn-circle-return" title="Quay lại"><i class="fa-solid fa-arrow-left"></i></a>
            <div class="dash-header">
                <h1>Chi Tiết Khóa Học Đã Đăng Ký</h1>
                <p class="student-subtitle"><i class="fa-solid fa-user-graduate"></i> Học Viên: <?php echo htmlspecialchars($studentInfo['HoTen']); ?></p>
            </div>
        </div>

        <?php
        // Tính toán trước tổng tiền toàn bộ hệ thống
        foreach ($ordersList as $ord) {
            $grandTotal += floatval($ord['TongTien']);
        }
        ?>
        <div class="grand-total-container">
            <span class="grand-total-label"><i class="fa-solid fa-money-bill-wave" style="margin-right: 8px; color: #10b981;"></i>Tổng tiền toàn bộ của học viên:</span>
            <span class="grand-total-value"><?php echo number_format($grandTotal, 0, ',', '.'); ?>đ</span>
        </div>

        <?php if (empty($ordersList)): ?>
            <div class="table-wrapper" style="padding: 40px; text-align: center; color: var(--admin-muted);">
                Học viên này chưa có bất kỳ đơn hàng nào trên hệ thống.
            </div>
        <?php else: ?>
            <?php foreach ($ordersList as $index => $order): 
                // Lấy các khóa học tương ứng thuộc đơn hàng này
                $coursesInOrder = [];
                try {
                    // 🔥 ĐÃ SỬA: Tính toán tiến độ động từ bảng tiendohocvien theo đúng STT học viên
                    $cQuery = "
                    SELECT 
                        kh.KhoaHocId,
                        kh.Ten,
                        kh.Anh,
                        kh.Gia,
                        -- Subquery tính toán phần trăm hoàn thành khóa học động
                        (
                            SELECT IFNULL(ROUND(
                                (SUM(CASE WHEN tdhv.TrangThai = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100
                            ), 0)
                            FROM baihoc bh
                            LEFT JOIN tiendohocvien tdhv 
                                ON bh.BaiHocId = tdhv.BaiHocId AND tdhv.STT = ?
                            WHERE bh.KhoaHocId = kh.KhoaHocId
                        ) AS TienDo

                    FROM chitiethangdadat ct
                    INNER JOIN khoahoc kh 
                        ON ct.KhoaHocId = kh.KhoaHocId
                    WHERE ct.HangDaDatId = ?
                    ";

                    if ($db instanceof PDO) {
                        $cStmt = $db->prepare($cQuery);
                        // Truyền cả 2 tham số: studentId để tính tiến độ cá nhân, và HangDaDatId để lọc đơn hàng
                        $cStmt->execute([$studentId, $order['HangDaDatId']]);
                        $coursesInOrder = $cStmt->fetchAll(PDO::FETCH_ASSOC);
                    } else {
                        $cStmt = $db->prepare($cQuery);
                        // Bind song song 2 biến số nguyên "ii"
                        $cStmt->bind_param("ii", $studentId, $order['HangDaDatId']);
                        $cStmt->execute();
                        $cRes = $cStmt->get_result();
                        while($r = $cRes->fetch_assoc()) { $coursesInOrder[] = $r; }
                    }
                } catch (Exception $e) {}
                
                $rowCount = count($coursesInOrder);
                if ($rowCount === 0) continue;
            ?>
                <div class="order-block">
                    <div class="table-wrapper">
                        <table class="detail-course-table">
                            <thead>
                                <tr>
                                    <th>Thông tin khóa học</th>
                                    <th style="width: 240px;">Tiến độ học tập</th>
                                    <th style="width: 200px;">Trạng Thái Đơn Hàng</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($coursesInOrder as $cIdx => $course): 
                                    $currentProgress = !empty($course['TienDo']) ? intval($course['TienDo']) : 0;
                                    $progressColor = ($currentProgress === 100) ? '#10b981' : '#4f46e5';
                                ?>
                                <tr>
                                    <td>
                                        <div class="composite-course-card">
                                            <img src="/DevMaster/<?php echo htmlspecialchars($course['Anh'] ?? 'default-course.png'); ?>" alt="Course Image">
                                            <div class="composite-course-details">
                                                <h3 class="composite-course-title"><?php echo htmlspecialchars($course['Ten']); ?></h3>
                                                <p class="composite-course-price"><?php echo number_format($course['Gia'], 0, ',', '.'); ?>đ</p>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td style="text-align: center;">
                                        <div class="progress-container">
                                            <div class="progress-bar-bg">
                                                <div class="progress-bar-fill" style="width: <?php echo $currentProgress; ?>%; background: <?php echo $progressColor; ?>;"></div>
                                            </div>
                                            <span class="progress-percentage" style="color: <?php echo $progressColor; ?>;">
                                                <?php echo $currentProgress; ?>% Hoàn thành
                                            </span>
                                        </div>
                                    </td>

                                    <?php if ($cIdx === 0): ?>
                                    <td class="sticky-status-cell" rowspan="<?php echo $rowCount; ?>">
                                        <div class="sticky-status-wrapper">
                                            <?php if (intval($order['TrangThai']) === 1): ?>
                                                <span class="badge-completed-status"><i class="fa-solid fa-circle-check"></i> Hoàn thành</span>
                                            <?php else: ?>
                                                <div class="custom-dropdown" id="dropdown-order-<?php echo $order['HangDaDatId']; ?>">
                                                    <div class="badge-pending-status" onclick="toggleStatusDropdown(<?php echo $order['HangDaDatId']; ?>)">
                                                        Chờ xác nhận
                                                    </div>
                                                    <div class="dropdown-content-menu">
                                                        <a href="javascript:void(0);" onclick="confirmAction(<?php echo $order['HangDaDatId']; ?>)">Xác nhận</a>
                                                        <a href="javascript:void(0);" onclick="deleteAction(<?php echo $order['HangDaDatId']; ?>)" style="color: #ef4444; border-top: 1px solid #f1f5f9;">Xóa</a>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>

                                <tr class="table-summary-row">
                                    <td colspan="2">
                                        <div class="summary-flex-container">
                                            <span class="summary-label-text"><i class="fa-solid fa-receipt" style="margin-right: 6px;"></i> Mã đơn: <?php echo htmlspecialchars($order['MaDonHang']); ?> (Đặt ngày: <?php echo date('d/m/Y', strtotime($order['NgayDat'])); ?>)</span>
                                            <span class="summary-label-text">Tổng số tiền đã đặt:</span>
                                        </div>
                                    </td>
                                    <td style="text-align: center; font-weight: 800; color: #0f172a;">
                                        <?php echo number_format($order['TongTien'], 0, ',', '.'); ?>đ
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <script>
        function toggleStatusDropdown(orderId) {
            const dropdown = document.getElementById('dropdown-order-' + orderId);
            if (dropdown) {
                dropdown.classList.toggle('open');
            }
            // Đóng các dropdown khác nếu đang mở
            document.querySelectorAll('.custom-dropdown').forEach(function(el) {
                if (el.id !== 'dropdown-order-' + orderId) {
                    el.classList.remove('open');
                }
            });
        }

        window.onclick = function(event) {
            if (!event.target.matches('.badge-pending-status')) {
                document.querySelectorAll('.custom-dropdown').forEach(function(el) {
                    el.classList.remove('open');
                });
            }
        }

        function confirmAction(orderId) {
            window.location.href = `XemChiTiet.php?id=<?php echo $studentId; ?>&action=confirm_order&order_id=${orderId}`;
        }
        // Hàm xử lý chuyển hướng xóa đơn hàng ngay lập tức mà không đưa ra hộp thoại thông báo xác nhận
        function deleteAction(orderId) {
            window.location.href = `XemChiTiet.php?id=<?php echo $studentId; ?>&action=delete_order&order_id=${orderId}`;
        }
    </script>
</body>
</html>
