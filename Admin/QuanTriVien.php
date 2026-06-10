<?php
// Admin/QuanTriVien.php
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

// Lấy Gmail của Admin hiện tại từ Session để định danh và phân quyền thực tế
$currentAdminGmail = $_SESSION['Gmail'] ?? ($_SESSION['Email'] ?? '');

// Tìm thông tin vai trò (ViTri) của chính người đang đăng nhập từ DB dựa vào Gmail
$myViTri = 0; // Mặc định là QTV nếu không tìm thấy
try {
    $checkQuery = "SELECT ViTri FROM quantriadmin WHERE Gmail = ?";
    if ($db instanceof PDO) {
        $stmt = $db->prepare($checkQuery);
        $stmt->execute([$currentAdminGmail]);
        $myRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($myRow) $myViTri = (int)$myRow['ViTri'];
    } else {
        $stmt = $db->prepare($checkQuery);
        $stmt->bind_param("s", $currentAdminGmail);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($myRow = $res->fetch_assoc()) $myViTri = (int)$myRow['ViTri'];
    }
} catch (Exception $e) {
    // Fallback an toàn
}

$admins = [];  // Danh sách QTV (ViTri = 0)
$managers = []; // Danh sách Quản Lý (ViTri = 1)

// Tải toàn bộ dữ liệu phân loại theo ViTri
try {
    $adminQuery = "SELECT AdminId, TenAdmin, Gmail, MatKhau, TrangThai, ViTri FROM quantriadmin ORDER BY AdminId DESC";
    if ($db instanceof PDO) {
        $stmt = $db->query($adminQuery);
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $result = $db->query($adminQuery);
        $all = [];
        while ($row = $result->fetch_assoc()) { $all[] = $row; }
    }

    foreach ($all as $row) {
        if ((int)$row['ViTri'] === 1) {
            $managers[] = $row;
        } else {
            $admins[] = $row;
        }
    }
} catch (Exception $e) {
    // Tránh lộ lỗi bảo mật
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản Lý Ban Quản Trị - DevMaster</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 260px;
            --admin-primary: #4f46e5;
            --admin-primary-hover: #4338ca;
            --admin-bg: #f8fafc;
            --admin-card: #ffffff;
            --admin-text: #1e293b;
            --admin-muted: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--admin-bg); color: var(--admin-text); display: flex; }
        
        /* Sidebar Cố định */
        .admin-sidebar {
            width: var(--sidebar-width); height: 100vh; background: #0f172a; color: #ffffff;
            position: fixed; top: 0; left: 0; display: flex; flex-direction: column;
            justify-content: space-between; padding: 24px 16px; box-sizing: border-box; z-index: 100;
        }
        .logo-wrapper { display: flex; align-items: center; gap: 12px; text-decoration: none; margin-bottom: 45px; }
        .logo-square {
            width: 38px; height: 38px; background: #4f46e5; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; color: white; font-size: 16px;
        }
        .logo-brand { font-size: 20px; font-weight: 800; color: white; letter-spacing: -0.5px; }
        .logo-brand span { color: #6366f1; }
        .admin-menu { display: flex; flex-direction: column; gap: 8px; flex-grow: 1; }
        .menu-item { display: flex; align-items: center; gap: 12px; color: #94a3b8; text-decoration: none; padding: 12px; border-radius: 8px; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .menu-item:hover, .menu-item.active { background: #1e293b; color: #ffffff; }
        .menu-item.active { background: var(--admin-primary); }
        .sidebar-footer { display: flex; flex-direction: column; gap: 8px; border-top: 1px solid #334155; padding-top: 16px; }
        .btn-footer { display: flex; align-items: center; gap: 12px; text-decoration: none; font-size: 13px; font-weight: 600; padding: 10px; border-radius: 6px; transition: all 0.2s; }
        .btn-back { color: #cbd5e1; background: #334155; }
        .btn-logout { color: #f87171; background: rgba(239, 68, 68, 0.1); }
        .btn-back:hover { background: #475569; }
        .btn-logout:hover { background: rgba(239, 68, 68, 0.2); }

        /* Nội dung chính */
        .admin-main-content {
            margin-left: var(--sidebar-width); flex-grow: 1; padding: 40px;
            box-sizing: border-box; max-width: calc(100vw - var(--sidebar-width));
        }
        .dash-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
        .dash-header h1 { font-size: 28px; font-weight: 800; margin: 0 0 6px 0; }
        .dash-header p { color: var(--admin-muted); margin: 0; font-size: 14px; }
        .header-actions { display: flex; gap: 12px; }

        .btn-action-primary {
            background: var(--admin-primary); color: white; padding: 12px 20px; border-radius: 10px;
            text-decoration: none; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25); transition: all 0.2s; border: none; cursor: pointer;
        }
        .btn-action-primary:hover { background: var(--admin-primary-hover); transform: translateY(-1px); }
        
        .btn-action-secondary {
            background: #ffffff; color: #334155; padding: 12px 20px; border-radius: 10px;
            text-decoration: none; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px;
            border: 1px solid #e2e8f0; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: all 0.2s; cursor: pointer;
        }
        .btn-action-secondary:hover { background: #f8fafc; border-color: #cbd5e1; }

        /* Toolbar & Bảng */
        .table-toolbar {
            background: var(--admin-card); padding: 16px 24px; border-radius: 16px 16px 0 0;
            border: 1px solid #e2e8f0; border-bottom: none; display: flex; align-items: center; justify-content: space-between;
        }
        .search-box { position: relative; display: flex; align-items: center; }
        .search-box i { position: absolute; left: 14px; color: var(--admin-muted); font-size: 14px; }
        .search-box input {
            padding: 10px 16px 10px 40px; border: 1px solid #e2e8f0; border-radius: 8px;
            font-size: 14px; outline: none; width: 280px; transition: all 0.2s;
        }
        .search-box input:focus { border-color: var(--admin-primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }

        .table-wrapper {
            background: var(--admin-card); border-radius: 0 0 16px 16px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; overflow: hidden;
        }
        .admin-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        .admin-table th {
            background: #f8fafc; color: var(--admin-muted); padding: 16px 24px; font-weight: 600;
            text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0;
        }
        .admin-table td { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
        .admin-table tr:last-child td { border-bottom: none; }
        .admin-table tr:hover td { background: #f8fafc; }

        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 99px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .badge-on { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .badge-off { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

        .actions-cell { text-align: right; width: 140px; }
        .btn-action {
            background: none; border: none; padding: 6px; color: var(--admin-muted); cursor: pointer;
            border-radius: 4px; transition: all 0.2s; margin-left: 4px; text-decoration: none; display: inline-block;
        }
        .btn-edit:hover { color: var(--admin-primary); background: rgba(79, 70, 229, 0.05); }
        .btn-delete:hover { color: var(--danger); background: rgba(239, 68, 68, 0.05); }
        .badge-self { background: #e2e8f0; color: #334155; font-size: 11px; padding: 2px 6px; border-radius: 4px; margin-left: 6px; font-weight: 600; }

        /* Modals Phong cách Cao cấp */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(6px);
            z-index: 999; display: flex; align-items: center; justify-content: center;
            opacity: 0; visibility: hidden; transition: all 0.3s ease;
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        
        .modal-card {
            background: var(--admin-card); width: 100%; max-width: 520px;
            border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            border: 1px solid #e2e8f0; overflow: hidden; transform: scale(0.95); transition: all 0.3s ease;
        }
        .modal-card.wide-card { max-width: 850px; }
        .modal-overlay.active .modal-card { transform: scale(1); }
        
        .modal-header { padding: 20px 24px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; font-size: 18px; font-weight: 800; color: #0f172a; }
        .btn-close-modal { background: none; border: none; color: var(--admin-muted); cursor: pointer; font-size: 18px; }
        .btn-close-modal:hover { color: #0f172a; }
        
        .modal-body { padding: 24px; max-height: 70vh; overflow-y: auto; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 8px; }
        .form-group input, .form-group select {
            width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px;
            font-size: 14px; color: #0f172a; outline: none; box-sizing: border-box; transition: all 0.2s;
        }
        .form-group input:focus, .form-group select:focus { border-color: var(--admin-primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        
        .modal-footer { padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; }
        .btn-modal { padding: 10px 18px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; }
        .btn-modal-cancel { background: #e2e8f0; color: #334155; }
        .btn-modal-cancel:hover { background: #cbd5e1; }
        .btn-modal-save { background: var(--admin-primary); color: white; }
        .btn-modal-save:hover { background: var(--admin-primary-hover); }

        .empty-state { padding: 40px; text-align: center; color: var(--admin-muted); }
        .empty-state i { font-size: 40px; margin-bottom: 12px; color: #cbd5e1; }
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
                <a href="/DevMaster/Admin/QuanLyHocVien.php" class="menu-item"><i class="fa-solid fa-graduation-cap"></i> Quản lý học viên</a>
                <a href="/DevMaster/Admin/QuanLyKhoaHoc.php" class="menu-item"><i class="fa-solid fa-book"></i> Quản lý khóa học</a>
                <a href="/DevMaster/Admin/QuanTriVien.php" class="menu-item active"><i class="fa-solid fa-user-shield"></i> Quản trị viên</a>
            </nav>
        </div>
        <div class="sidebar-footer">
            <a href="/DevMaster/Index.php" class="btn-footer btn-back"><i class="fa-solid fa-house"></i> Quay lại</a>
            <a href="/DevMaster/Auth/Logout.php" class="btn-footer btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</a>
        </div>
    </aside>

    <main class="admin-main-content">
        <div class="dash-header">
            <div>
                <h1>Phân Quyền Hệ Thống</h1>
                <p>
                    <?php if($myViTri === 1): ?>
                        <span style="color: var(--warning); font-weight: 700;"><i class="fa-solid fa-crown"></i> Quyền hạn: Quản Lý.</span> Bạn có toàn quyền sửa/xóa thành viên ban quản trị.
                    <?php else: ?>
                        <span style="color: var(--admin-primary); font-weight: 700;"><i class="fa-solid fa-user-shield"></i> Quyền hạn: Quản trị viên.</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="header-actions">
                <?php if($myViTri === 1): ?>
                    <button class="btn-action-secondary" onclick="openManagersModal()">
                        <i class="fa-solid fa-crown" style="color: #f59e0b;"></i> Hội đồng Quản Lý (<?php echo count($managers); ?>)
                    </button>
                    <button class="btn-action-primary" onclick="openAddModal()">
                        <i class="fa-solid fa-user-plus"></i> Thêm Admin Mới
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-toolbar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="adminSearch" placeholder="Tìm kiếm tên hoặc Gmail quản trị viên...">
            </div>
            <div style="font-size: 13px; color: var(--admin-muted); font-weight: 500;">
                Danh sách: <strong><?php echo count($admins); ?></strong> Quản trị viên
            </div>
        </div>

        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 70px;">STT</th>
                        <th>Họ và Tên</th>
                        <th>Địa chỉ Gmail</th>
                        <th>Trạng thái</th>
                        <th class="actions-cell">Thao tác</th>
                    </tr>
                </thead>
                <tbody id="adminTableBody">
                    <?php if (!empty($admins)): ?>
                        <?php 
                        $stt = 1; 
                        foreach ($admins as $admin): 
                            $firstLetter = mb_substr($admin['TenAdmin'], 0, 1, 'UTF-8');
                            $isOnline = (strtolower(trim($admin['TrangThai'])) === 'on');
                            $isSelf = (trim($admin['Gmail']) === trim($currentAdminGmail));
                        ?>
                            <tr>
                                <td><strong><?php echo $stt++; ?></strong></td>
                                <td>
                                    <div>
                                        <div style="font-weight: 600; color: #0f172a;">
                                            <?php echo htmlspecialchars($admin['TenAdmin']); ?>
                                            <?php if($isSelf): ?><span class="badge-self">Bạn</span><?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="font-family: monospace; font-size: 13px; color: #475569;">
                                    <?php echo htmlspecialchars($admin['Gmail']); ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $isOnline ? 'badge-on' : 'badge-off'; ?>">
                                        <?php echo $isOnline ? 'Online' : 'Offline'; ?>
                                    </span>
                                </td>
                                <td class="actions-cell">
                                    <?php if ($myViTri === 1): ?>
                                        <button type="button" class="btn-action btn-edit" title="Chỉnh sửa tài khoản này"
                                                onclick="openEditModal('<?php echo $admin['AdminId']; ?>', '<?php echo htmlspecialchars($admin['TenAdmin'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($admin['Gmail'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($admin['MatKhau'], ENT_QUOTES); ?>', 0, true)">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <a href="XuLyXoaAdmin.php?id=<?php echo $admin['AdminId']; ?>" class="btn-action btn-delete" title="Xóa tài khoản này" 
                                           onclick="return confirm('Bạn có chắc chắn muốn xóa Quản trị viên này không?')">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </a>
                                    <?php else: ?>
                                        <?php if ($isSelf): ?>
                                            <button type="button" class="btn-action btn-edit" title="Chỉnh sửa tài khoản của bạn"
                                                    onclick="openEditModal('<?php echo $admin['AdminId']; ?>', '<?php echo htmlspecialchars($admin['TenAdmin'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($admin['Gmail'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($admin['MatKhau'], ENT_QUOTES); ?>', 0, false)">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                        <?php else: ?>
                                            <span style="color: #cbd5e1; font-size: 12px; font-style: italic;">Không có quyền</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="fa-solid fa-user-slash"></i>
                                    <p>Không có tài khoản Quản trị viên nào.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div class="modal-overlay" id="managersModal">
        <div class="modal-card wide-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-crown" style="color: #f59e0b;"></i> Danh Sách Ban Quản Lý</h3>
                <button type="button" class="btn-close-modal" onclick="closeManagersModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body" style="padding: 0;">
                <table class="admin-table">
                    <thead>
                        <tr style="background: #f1f5f9;">
                            <th style="width: 70px; padding: 12px 24px;">STT</th>
                            <th style="padding: 12px 24px;">Họ và Tên</th>
                            <th style="padding: 12px 24px;">Địa chỉ Gmail</th>
                            <th class="actions-cell" style="padding: 12px 24px;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $mStt = 1;
                        foreach ($managers as $mng): 
                            $mLetter = mb_substr($mng['TenAdmin'], 0, 1, 'UTF-8');
                            $isMngSelf = (trim($mng['Gmail']) === trim($currentAdminGmail));
                        ?>
                            <tr>
                                <td style="padding: 14px 24px;"><strong><?php echo $mStt++; ?></strong></td>
                                <td style="padding: 14px 24px;">
                                    <div>
                                        <div style="font-weight: 600; color: #0f172a;">
                                            <?php echo htmlspecialchars($mng['TenAdmin']); ?>
                                            <?php if($isMngSelf): ?><span class="badge-self">Bạn</span><?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="font-family: monospace; font-size: 13px; padding: 14px 24px;"><?php echo htmlspecialchars($mng['Gmail']); ?></td>
                                <td class="actions-cell" style="padding: 14px 24px;">
                                    <?php if($isMngSelf): ?>
                                        <button type="button" class="btn-action btn-edit" title="Sửa thông tin của bạn"
                                                onclick="closeManagersModal(); openEditModal('<?php echo $mng['AdminId']; ?>', '<?php echo htmlspecialchars($mng['TenAdmin'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($mng['Gmail'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($mng['MatKhau'], ENT_QUOTES); ?>', 1, false)">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <a href="XuLyXoaAdmin.php?id=<?php echo $mng['AdminId']; ?>&self=true" class="btn-action btn-delete" title="Xóa tài khoản của bạn và đăng xuất" 
                                           onclick="return confirm('CẢNH BÁO: Bạn đang thực hiện xóa tài khoản Quản Lý của CHÍNH MÌNH. Hệ thống sẽ tự động đăng xuất và đưa bạn về trang chủ ngay lập tức. Xác nhận?')">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #cbd5e1; font-size: 12px; font-style: italic;">Bảo mật chéo</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal btn-modal-cancel" onclick="closeManagersModal()">Đóng bảng</button>
            </div>
        </div>
    </div>

    <?php if($myViTri === 1): ?>
    <div class="modal-overlay" id="addAdminModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-user-plus"></i> Đăng Ký Tài Khoản Quản Trị</h3>
                <button type="button" class="btn-close-modal" onclick="closeAddModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form action="XuLyThemAdmin.php" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="addTenAdmin">Họ và Tên</label>
                        <input type="text" name="txtTenAdmin" id="addTenAdmin" required placeholder="Nhập họ và tên...">
                    </div>
                    <div class="form-group">
                        <label for="addGmail">Địa chỉ Gmail</label>
                        <input type="email" name="txtGmail" id="addGmail" required placeholder="example@gmail.com">
                    </div>
                    <div class="form-group">
                        <label for="addMatKhau">Mật khẩu</label>
                        <input type="text" name="txtMatKhau" id="addMatKhau" required>
                    </div>
                    <div class="form-group">
                        <label for="addViTri">Vai trò phân quyền</label>
                        <select name="txtViTri" id="addViTri">
                            <option value="0" selected>Quản trị viên (Quyền hạn cơ bản)</option>
                            <option value="1">Quản Lý (Toàn quyền hệ thống)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal btn-modal-cancel" onclick="closeAddModal()">Hủy</button>
                    <button type="submit" class="btn-modal btn-modal-save">Khởi Tạo Tài Khoản</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="modal-overlay" id="editAdminModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="editModalTitle">Cập Nhật Thông Tin</h3>
                <button type="button" class="btn-close-modal" onclick="closeEditModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form action="XuLySuaAdmin.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="txtAdminId" id="modalAdminId">
                    
                    <div class="form-group">
                        <label for="modalTenAdmin">Họ và Tên</label>
                        <input type="text" name="txtTenAdmin" id="modalTenAdmin" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="modalGmail">Địa chỉ Gmail</label>
                        <input type="email" name="txtGmail" id="modalGmail" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="modalMatKhau">Mật khẩu</label>
                        <input type="text" name="txtMatKhau" id="modalMatKhau" required>
                    </div>

                    <div class="form-group" id="modalViTriContainer" style="display: none;">
                        <label for="modalViTri">Vai trò</label>
                        <select name="txtViTri" id="modalViTri">
                            <option value="0">Quản trị viên</option>
                            <option value="1">Quản Lý</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal btn-modal-cancel" onclick="closeEditModal()">Hủy bỏ</button>
                    <button type="submit" class="btn-modal btn-modal-save">Lưu Thay Đổi</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal Quản Lý (Mờ nền)
        function openManagersModal() {
            document.getElementById('managersModal').classList.add('active');
        }
        function closeManagersModal() {
            document.getElementById('managersModal').classList.remove('active');
        }

        // Modal Thêm Admin mới
        function openAddModal() {
            const modal = document.getElementById('addAdminModal');
            if(modal) modal.classList.add('active');
        }
        function closeAddModal() {
            const modal = document.getElementById('addAdminModal');
            if(modal) modal.classList.remove('active');
        }

        // Modal Chỉnh sửa linh hoạt (Truyền thêm tham số vai trò hiện tại và cấu hình hiển thị dropdown thay đổi quyền)
        function openEditModal(id, name, gmail, password, currentViTri, showRoleDropdown) {
            document.getElementById('modalAdminId').value = id;
            document.getElementById('modalTenAdmin').value = name;
            document.getElementById('modalGmail').value = gmail;
            document.getElementById('modalMatKhau').value = password;
            
            const roleContainer = document.getElementById('modalViTriContainer');
            const roleSelect = document.getElementById('modalViTri');
            const title = document.getElementById('editModalTitle');

            if (showRoleDropdown) {
                title.innerText = "Quản Lý Chỉnh Sửa Tài Khoản Cấp Dưới";
                roleContainer.style.display = 'block';
                roleSelect.value = currentViTri;
            } else {
                title.innerText = "Chỉnh Sửa Hồ Sơ Cá Nhân";
                roleContainer.style.display = 'none';
            }
            
            document.getElementById('editAdminModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editAdminModal').classList.remove('active');
        }

        // Đóng các modal khi bấm ra ngoài vùng trung tâm để tăng trải nghiệm UX
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });

        // Thanh Tìm kiếm thời gian thực (Real-time Filter)
        const searchInput = document.getElementById('adminSearch');
        if(searchInput) {
            searchInput.addEventListener('keyup', function() {
                const value = this.value.toLowerCase().normalize("NCD").replace(/[\u0300-\u036f]/g, "");
                const rows = document.querySelectorAll('#adminTableBody tr');
                
                rows.forEach(row => {
                    if(row.querySelector('.empty-state')) return;
                    const text = row.textContent.toLowerCase().normalize("NCD").replace(/[\u0300-\u036f]/g, "");
                    row.style.display = text.includes(value) ? '' : 'none';
                });
            });
        }
    </script>
</body>
</html>