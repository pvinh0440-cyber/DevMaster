<?php
// Admin/QuanLyHocVien.php
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

// Xử lý xóa học viên nếu có yêu cầu gửi lên (Phương thức GET an toàn hoặc xử lý qua API)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $idToDelete = intval($_GET['id']);
    try {
        $deleteQuery = "DELETE FROM dangky WHERE STT = ?";
        if ($db instanceof PDO) {
            $stmt = $db->prepare($deleteQuery);
            $stmt->execute([$idToDelete]);
        } else {
            $stmt = $db->prepare($deleteQuery);
            $stmt->bind_param("i", $idToDelete);
            $stmt->execute();
        }
        $_SESSION['deleteMessage'] = "<div class='alert success-alert'><i class='fa-solid fa-circle-check'></i> Đã xóa học viên khỏi hệ thống!</div>";
    } catch (Exception $e) {
        $_SESSION['deleteMessage'] = "<div class='alert error-alert'><i class='fa-solid fa-circle-exclamation'></i> Lỗi hệ thống: Không thể xóa học viên này.</div>";
    }
    
    // ĐIỂM CHỐT: Điều hướng về trang sạch tham số URL giúp triệt tiêu hiện tượng lặp lại khi reload
    header("Location: QuanLyHocVien.php");
    exit;
}

// Đọc thông báo từ Session (nếu có), hiển thị xong xóa ngay lập tức
$deleteMessage = "";
if (isset($_SESSION['deleteMessage'])) {
    $deleteMessage = $_SESSION['deleteMessage'];
    unset($_SESSION['deleteMessage']); // Xóa vĩnh viễn khỏi bộ nhớ phiên làm việc ngay sau khi lấy ra
}

// Lấy danh sách toàn bộ học viên từ database
$students = [];
try {
    $selectQuery = "SELECT STT, HoTen, TenDangNhap, MatKhau, Gmail, SDT, NgayDangKy, TrangThai FROM dangky ORDER BY STT DESC";
    if ($db instanceof PDO) {
        $result = $db->query($selectQuery);
        $students = $result->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $result = $db->query($selectQuery);
        $while_row = null;
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    }
} catch (Exception $e) {
    // Xử lý ngoại lệ an toàn dữ liệu
}

function hasPendingOrder($db, $sttUser) {
    try {
        $checkQuery = "SELECT COUNT(*) as PendingCount FROM hangdadat WHERE STT = ? AND TrangThai = 0";
        if ($db instanceof PDO) {
            $stmt = $db->prepare($checkQuery);
            $stmt->execute([$sttUser]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return (intval($row['PendingCount'] ?? 0) > 0);
        } else {
            $stmt = $db->prepare($checkQuery);
            $stmt->bind_param("i", $sttUser);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            return (intval($row['PendingCount'] ?? 0) > 0);
        }
    } catch (Exception $e) {
        return false;
    }
}

// Kiểm tra trạng thái hoạt động thực tế dựa trên thời gian tương tác cuối cùng từ Database
function checkUserOnlineStatus($db, $studentRow) {
    if (!empty($studentRow['TrangThai'])) {
        $lastActivity = strtotime($studentRow['TrangThai']);
        // Nếu chuyển đổi thời gian thất bại (do chứa chữ như 'Chờ xác nhận'), trả về false để logic dưới xử lý tiếp
        if (!$lastActivity) {
            return false;
        }
        $currentTime = time();
        $timeDifference = $currentTime - $lastActivity;
        
        // Nếu thời gian tương tác cuối cùng trong vòng 5 phút (300 giây) thì coi là Online
        return $timeDifference <= 300;
    }
    return false; 
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản Lý Học Viên - DevMaster</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ==========================================================================
           1. GIỮ NGUYÊN BẢN 100% STYLE TỪ DASHBOARD GỐC CỦA BẠN
           ========================================================================== */
        :root {
            --sidebar-width: 260px;
            --admin-primary: #4f46e5;
            --admin-bg: #f8fafc;
            --admin-card: #ffffff;
            --admin-text: #1e293b;
            --admin-muted: #64748b;
        }
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--admin-bg); color: var(--admin-text); display: flex; }
        
        /* Sidebar Cố định bên trái */
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
        
        /* Highlight chính xác thanh ứng với bảng bên trái màu xanh như Dashboard */
        .menu-item.active { background: var(--admin-primary); }
        
        .sidebar-footer { display: flex; flex-direction: column; gap: 8px; border-top: 1px solid #334155; padding-top: 16px; }
        .btn-footer { display: flex; align-items: center; gap: 12px; text-decoration: none; font-size: 13px; font-weight: 600; padding: 10px; border-radius: 6px; transition: all 0.2s; }
        .btn-back { color: #cbd5e1; background: #334155; }
        .btn-logout { color: #f87171; background: rgba(239, 68, 68, 0.1); }
        .btn-back:hover { background: #475569; }
        .btn-logout:hover { background: rgba(239, 68, 68, 0.2); }

        /* Vùng nội dung chính bên phải */
        .admin-main-content {
            margin-left: var(--sidebar-width);
            flex-grow: 1;
            padding: 40px;
            box-sizing: border-box;
        }
        .dash-header { margin-bottom: 32px; display: flex; justify-content: space-between; align-items: center; }
        .dash-header h1 { font-size: 28px; font-weight: 800; margin: 0 0 6px 0; }
        .dash-header p { color: var(--admin-muted); margin: 0; font-size: 14px; }

        /* ==========================================================================
           2. PHONG CÁCH BẢNG DỮ LIỆU ĐỒNG NHẤT VÀ SỬA LỖI UI/UX TRỰC QUAN
           ========================================================================== */
        /* Thanh tìm kiếm nhanh */
        .search-container { position: relative; }
        .search-container input {
            padding: 10px 16px 10px 40px; border-radius: 8px; border: 1px solid #e2e8f0;
            outline: none; font-size: 14px; width: 280px; transition: all 0.3s;
        }
        .search-container input:focus { border-color: var(--admin-primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        .search-container i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--admin-muted); }

        /* Khung chứa bảng cao cấp */
        .table-wrapper {
            background: var(--admin-card); border-radius: 16px;
            border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            overflow: hidden; margin-top: 24px;
        }
        .student-enterprise-table { width: 100%; border-collapse: collapse; }
        
        /* HÀNG TIÊU ĐỀ: TẤT CẢ PHẢI RA GIỮA TUYỆT ĐỐI */
        .student-enterprise-table th {
            background: #f8fafc; color: #475569; font-weight: 700; font-size: 14px;
            padding: 16px 20px; border-bottom: 2px solid #e2e8f0; text-align: center;
        }
        
        /* CỘT NỘI DUNG TRONG BẢNG: ĐỒNG NHẤT KÍCH THƯỚC CHỮ */
        .student-enterprise-table td {
            padding: 16px 20px; border-bottom: 1px solid #f1f5f9;
            color: #1e293b; font-size: 15px; vertical-align: middle;
        }
        .student-enterprise-table tr:last-child td { border-bottom: none; }
        .student-enterprise-table tr:hover td { background: #f8fafc; }

        /* Quy tắc căn lề lớp định danh */
        .cell-center { text-align: center; } /* Áp dụng cho STT, Trạng thái, Thao tác */
        .cell-left { text-align: left; }     /* Áp dụng cho Họ Tên, Tên đăng nhập, Gmail/SĐT, Ngày Đăng Ký */

        /* Định vị phong cách chữ cột */
        .txt-bold { font-weight: 700; color: #0f172a; }
        .txt-username { font-weight: 500; color: #4f46e5; }
        
        /* Khắc phục: Kích thước chữ cột Gmail & SĐT to và đồng nhất 15px */
        .txt-contact { font-size: 15px; font-weight: 500; line-height: 1.5; }
        .txt-contact-sdt { font-size: 15px; color: var(--admin-muted); display: block; }

        /* Hệ thống xem/ẩn mật khẩu tương tác */
        .password-container { display: flex; align-items: center; gap: 8px; }
        .password-hidden-dots { font-weight: 600; letter-spacing: 2px; }
        .btn-eye-toggle { background: none; border: none; color: var(--admin-muted); cursor: pointer; padding: 4px; border-radius: 4px; }
        .btn-eye-toggle:hover { color: var(--admin-primary); background: #f1f5f9; }

        /* Khắc phục: Badge trạng thái hiển thị rộng rãi trên 1 dòng duy nhất, không tù túng */
        .badge-status {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 700;
            white-space: nowrap; /* Ép buộc dữ liệu nằm trên 1 hàng dọc */
        }
        .badge-status i { font-size: 7px; }
        .badge-status.online { background: #d1fae5; color: #065f46; }
        .badge-status.offline { background: #e2e8f0; color: #475569; }
        .badge-status.pending { background: #fef3c7; color: #92400e; } /* Màu vàng sang trọng cho Chờ xác nhận */

        /* Bộ nút Thao tác */
        .action-container { display: flex; gap: 8px; justify-content: center; align-items: center; }
        .action-icon-btn {
            width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center;
            justify-content: center; text-decoration: none; border: none; cursor: pointer; transition: 0.2s;
        }
        .btn-info-style { background: rgba(79, 70, 229, 0.1); color: var(--admin-primary); }
        .btn-info-style:hover { background: var(--admin-primary); color: white; }
        .btn-trash-style { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .btn-trash-style:hover { background: #ef4444; color: white; }

        /* Thông báo */
        .alert {
            min-width: 300px;
            padding: 16px 20px;
            border-radius: 8px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
            animation: slideInRight 0.4s ease-out, fadeOutBlur 0.5s ease-in 2s forwards;
        }

        .success-alert { background: #ecfdf5; color: #065f46; border-left: 4px solid #10b981; }
        .error-alert { background: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; }

        /* Hiệu ứng trượt mượt mà từ bên phải màn hình vào */
        @keyframes slideInRight {
            from { transform: translateX(110%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Hiệu ứng tự động mờ dần và thu nhỏ để biến mất từ giây thứ 3.5 */
        @keyframes fadeOutBlur {
            from { opacity: 1; transform: scale(1); }
            to { opacity: 0; transform: scale(0.9); visibility: hidden; height: 0; padding: 0; margin: 0; overflow: hidden; }
        }

        /* Style cho Badge Online/Offline */
        .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 700; display: inline-block; }
        .status-badge.online { background: #d1fae5; color: #065f46; }
        .status-badge.offline { background: #f3f4f6; color: #64748b; }

        /* Container bao quanh nút hành động để xử lý dấu chấm than tuyệt đối */
        .action-btn-container { position: relative; display: inline-block; }
        .alert-indicator {
            position: absolute; top: -6px; right: -6px; width: 16px; height: 16px;
            background: #ef4444; color: #ffffff; border-radius: 50%; font-size: 11px;
            font-weight: 900; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 0 2px #ffffff; z-index: 10; animation: pulseAlert 1.5s infinite;
        }
        @keyframes pulseAlert {
            0% { transform: scale(1); }
            50% { transform: scale(1.15); }
            100% { transform: scale(1); }
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
        <div class="dash-header">
            <div>
                <h1>Quản Lý Học Viên</h1>
                <p>Danh sách thông tin học viên đăng ký tài khoản trên hệ thống.</p>
            </div>
            <div class="search-container">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="filterInput" placeholder="Tìm kiếm học viên nhanh..." onkeyup="liveSearchTable()">
            </div>
        </div>

        <div id="toast-container" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">
            <?php if(!empty($deleteMessage)) { echo $deleteMessage; } ?>
        </div>

        <div class="table-wrapper">
            <table class="student-enterprise-table" id="dataTableMaster">
                <thead>
                    <tr>
                        <th style="width: 70px;">STT</th>
                        <th>Họ và Tên</th>
                        <th>Tên đăng nhập</th>
                        <th>Mật khẩu</th>
                        <th>Gmail / SĐT</th>
                        <th>Ngày đăng ký</th>
                        <th style="width: 150px;">Trạng thái</th>
                        <th style="width: 120px;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="8" class="cell-center" style="color: var(--admin-muted); padding: 30px;">Không tìm thấy dữ liệu học viên hợp lệ.</td>
                        </tr>
                    <?php reset($students); else: ?>
                        <?php 
                        // Khởi tạo biến đếm độc lập bắt đầu từ 1 cho giao diện hiển thị
                        $index = 1; 
                        foreach ($students as $student): 
                            // Định nghĩa màu sắc nhãn trạng thái dựa trên dữ liệu thực tế từ Database
                            $isOnline = checkUserOnlineStatus($db, $student);

                            if ($isOnline) {
                                $badgeClass = 'online';
                                $statusLabel = 'Online';
                            } else if (!empty($student['TrangThai']) && strcasecmp(trim($student['TrangThai']), 'Chờ xác nhận') == 0) {
                                // Nếu trạng thái trong DB được gán cụ thể là "Chờ xác nhận" thì ưu tiên hiển thị
                                $badgeClass = 'pending'; 
                                $statusLabel = 'Chờ xác nhận';
                            } else {
                                $badgeClass = 'offline';
                                $statusLabel = 'Offline';
                            }

                            // Ép định dạng ngày tháng năm giờ phút thân thiện
                            $formattedDate = "Chưa cập nhật";
                            if(!empty($student['NgayDangKy'])) {
                                $formattedDate = date('d/m/Y H:i', strtotime($student['NgayDangKy']));
                            }
                        ?>
                            <tr>
                                <td class="cell-center txt-bold" style="color: var(--admin-muted);">
                                    <?php echo $index++; ?>
                                </td>
                                
                                <td class="cell-left txt-bold"><?php echo htmlspecialchars($student['HoTen']); ?></td>
                                
                                <td class="cell-left txt-username"><?php echo htmlspecialchars($student['TenDangNhap']); ?></td>
                                
                                <td class="cell-left">
                                    <div class="password-container">
                                        <span class="password-hidden-dots" data-revealed="false" data-secret="<?php echo htmlspecialchars($student['MatKhau']); ?>">••••••••</span>
                                        <button type="button" class="btn-eye-toggle" onclick="togglePasswordReveal(this)">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                    </div>
                                </td>
                                
                                <td class="cell-left">
                                    <div class="txt-contact">
                                        <?php echo htmlspecialchars($student['Gmail']); ?>
                                        <span class="txt-contact-sdt"><i class="fa-solid fa-phone" style="font-size:12px; margin-right:4px;"></i><?php echo htmlspecialchars($student['SDT'] ?? 'N/A'); ?></span>
                                    </div>
                                </td>
                                
                                <td class="cell-left txt-bold" style="color: #475569;"><?php echo $formattedDate; ?></td>
                                
                                <td class="cell-center">
                                    <span class="badge-status <?php echo $badgeClass; ?>">
                                        <i class="fa-solid fa-circle"></i><?php echo $statusLabel; ?>
                                    </span>
                                </td>
                                
                                <td class="cell-center">
                                    <div class="action-container">
                                        <div class="action-btn-container">
                                            <?php if (hasPendingOrder($db, $student['STT'])): ?>
                                                <div class="alert-indicator" title="Có đơn hàng mới chờ xác nhận!">!</div>
                                            <?php endif; ?>
                                            <a href="XemChiTiet.php?id=<?php echo $student['STT']; ?>" class="action-icon-btn btn-info-style" title="Xem chi tiết khóa học sở hữu">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                        </div>
                                        <button type="button" onclick="executeDeleteRow(<?php echo $student['STT']; ?>, '<?php echo htmlspecialchars($student['HoTen']); ?>')" class="action-icon-btn btn-trash-style" title="Xóa học viên"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        // Tính năng xem và ẩn mật khẩu linh hoạt không bể dòng dữ liệu
        function togglePasswordReveal(btn) {
            const wrapper = btn.parentElement;
            const textNode = wrapper.querySelector('.password-hidden-dots');
            const iconNode = btn.querySelector('i');
            const isRevealed = textNode.getAttribute('data-revealed') === 'true';
            const secretValue = textNode.getAttribute('data-secret');

            if (isRevealed) {
                textNode.innerText = '••••••••';
                textNode.setAttribute('data-revealed', 'false');
                iconNode.className = 'fa-solid fa-eye';
            } else {
                // Giới hạn độ rộng hiển thị nếu chuỗi băm quá dài
                textNode.innerText = secretValue.length > 20 ? secretValue.substring(0, 15) + '...' : secretValue;
                textNode.setAttribute('data-revealed', 'true');
                iconNode.className = 'fa-solid fa-eye-slash';
            }
        }

        // Động cơ tìm kiếm real-time gọn nhẹ tối ưu tốc độ phản hồi
        // Động cơ tìm kiếm real-time gọn nhẹ tối ưu tốc độ phản hồi (Đã sửa lỗi hiển thị hàng trống)
        function liveSearchTable() {
            const keyword = document.getElementById('filterInput').value.toUpperCase();
            const rows = document.getElementById('dataTableMaster').getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                
                // Kiểm tra nếu hàng này là hàng thông báo lỗi rỗng (chỉ có 1 ô duy nhất trải dài)
                if (cells.length === 1) {
                    // Nếu thanh tìm kiếm trống, hiển thị lại dòng thông báo này. Nếu đang gõ tìm kiếm, tạm ẩn nó đi.
                    rows[i].style.display = (keyword === "") ? "" : "none";
                    continue; 
                }

                let found = false;
                const tdName = cells[1];
                const tdUser = cells[2];
                const tdEmail = cells[4];

                if (tdName || tdUser || tdEmail) {
                    const contentName = tdName ? (tdName.textContent || tdName.innerText) : '';
                    const contentUser = tdUser ? (tdUser.textContent || tdUser.innerText) : '';
                    const contentEmail = tdEmail ? (tdEmail.textContent || tdEmail.innerText) : '';

                    if (contentName.toUpperCase().indexOf(keyword) > -1 || 
                        contentUser.toUpperCase().indexOf(keyword) > -1 || 
                        contentEmail.toUpperCase().indexOf(keyword) > -1) {
                        found = true;
                    }
                }
                rows[i].style.display = found ? "" : "none";
            }
        }

        // Xác nhận xóa thành viên an toàn cấp hệ thống
        function executeDeleteRow(sttId, studentName) {
            if (confirm(`HỆ THỐNG XÁC THỰC QUẢN TRỊ VIÊN:\n\nBạn có chắc chắn muốn tiến hành xóa học viên [ ${studentName} ] ra khỏi hệ thống DevMaster không?`)) {
                window.location.href = `QuanLyHocVien.php?action=delete&id=${sttId}`;
            }
        }
    </script>
</body>
</html>
