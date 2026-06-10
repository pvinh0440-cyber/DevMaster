<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 0. Đồng bộ cấu hình tài khoản (Admin / Học viên) tương tự như trang Index
$isAdminAccount = isset($_SESSION['IsAdmin']) && $_SESSION['IsAdmin'] === true;
$currentUserId = isset($_SESSION['UserId']) ? intval($_SESSION['UserId']) : 0;

$conn = new mysqli("localhost", "root", "", "devmaster");
$conn->set_charset("utf8mb4");

// Kiểm tra nếu kết nối lỗi thì báo ngay
if ($conn->connect_error) {
    die("Kết nối database thất bại: " . $conn->connect_error);
}

// 1. Nhận các tham số lọc và phân trang từ URL / Request (Hỗ trợ cả tải trang đầu và AJAX)
$danhmuc_filter = isset($_GET['danhmuc_id']) ? intval($_GET['danhmuc_id']) : 0;
$nhom_filter = isset($_GET['nhom_id']) ? intval($_GET['nhom_id']) : 0;
$sort_filter = isset($_GET['sort']) ? $_GET['sort'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12; 
$offset = ($page - 1) * $limit;

// Đón nhận tham số Tìm kiếm từ Header hoặc tham số Click trực tiếp từ Dropdown
$keyword_filter = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$click_id_filter = isset($_GET['click_id']) ? intval($_GET['click_id']) : 0;

$banner_title = "Tất cả khóa học";
$banner_subtitle = "";

// LOGIC XỬ LÝ BANNER ĐỘNG VÀ BỘ LỌC ÉP BUỘC KHI CLICK TRỰC TIẾP TỪ DROPDOWN 8 KẾT QUẢ
if ($click_id_filter > 0) {
    // Lấy chi tiết đầy đủ tên Danh mục và tên Nhóm của khóa học đó để làm link quay lại
    $stmtClick = $conn->prepare("SELECT kh.Ten, kh.NhomKhoaHocId, nkh.TenNhom, nkh.DanhMucId, dm.TenDanhMuc 
                                 FROM khoahoc kh 
                                 JOIN nhomkhoahoc nkh ON kh.NhomKhoaHocId = nkh.NhomKhoaHocId 
                                 JOIN danhmuc dm ON nkh.DanhMucId = dm.DanhMucId
                                 WHERE kh.KhoaHocId = ?");
    $stmtClick->bind_param("i", $click_id_filter);
    $stmtClick->execute();
    $resClick = $stmtClick->get_result()->fetch_assoc();
    if ($resClick) {
        $banner_title = $resClick['Ten']; 
        // Tạo liên kết điều hướng ngược thông minh cho cả Danh mục và Nhóm
        $banner_subtitle = '<a href="/DevMaster/Pages/TatCaKhoaHoc.php?danhmuc_id=' . $resClick['DanhMucId'] . '" class="banner-breadcrumb-link">' . htmlspecialchars($resClick['TenDanhMuc']) . '</a> &gt; <a href="/DevMaster/Pages/TatCaKhoaHoc.php?nhom_id=' . $resClick['NhomKhoaHocId'] . '" class="banner-breadcrumb-link">' . htmlspecialchars($resClick['TenNhom']) . '</a>';
        
        $nhom_filter = intval($resClick['NhomKhoaHocId']);
        $danhmuc_filter = 0;
    }
    $stmtClick->close();
} elseif (!empty($keyword_filter)) {
    // Nếu đi từ luồng Tìm kiếm thông thường
    $banner_title = 'Kết quả tìm kiếm: "' . htmlspecialchars($keyword_filter) . '"';
    $banner_subtitle = "Tìm kiếm khóa học";
} elseif ($nhom_filter > 0) {
    // Truy vấn lấy thêm cả DanhMucId để dựng link quay lại danh mục cha
    $stmtNhom = $conn->prepare("SELECT nkh.TenNhom, dm.DanhMucId, dm.TenDanhMuc FROM nhomkhoahoc nkh JOIN danhmuc dm ON nkh.DanhMucId = dm.DanhMucId WHERE nkh.NhomKhoaHocId = ?");
    $stmtNhom->bind_param("i", $nhom_filter);
    $stmtNhom->execute();
    $resNhom = $stmtNhom->get_result()->fetch_assoc();
    if ($resNhom) {
        $banner_title = $resNhom['TenNhom'];
        // Biến tên danh mục cha thành đường link bấm được hướng về chính trang TatCaKhoaHoc.php?danhmuc_id=...
        $banner_subtitle = '<a href="/DevMaster/Pages/TatCaKhoaHoc.php?danhmuc_id=' . $resNhom['DanhMucId'] . '" class="banner-breadcrumb-link">' . htmlspecialchars($resNhom['TenDanhMuc']) . '</a> &gt; ' . htmlspecialchars($resNhom['TenNhom']);
    }
    $stmtNhom->close();
} elseif ($danhmuc_filter > 0) {
    $stmtDm = $conn->prepare("SELECT TenDanhMuc FROM danhmuc WHERE DanhMucId = ?");
    $stmtDm->bind_param("i", $danhmuc_filter);
    $stmtDm->execute();
    $resDm = $stmtDm->get_result()->fetch_assoc();
    if ($resDm) {
        $banner_title = $resDm['TenDanhMuc'];
    }
    $stmtDm->close();
}

// 2. Xây dựng câu lệnh điều kiện WHERE động có tích hợp Tìm kiếm từ khóa
$whereClauses = [];

if ($click_id_filter > 0) {
    // Nếu click đích danh, chỉ hiện duy nhất khóa học đó
    $whereClauses[] = "kh.KhoaHocId = " . $click_id_filter;
} else {
    if (!empty($keyword_filter)) {
        // Bảo mật an toàn dữ liệu chuỗi tìm kiếm trước khi nối SQL
        $safeKeyword = $conn->real_escape_string($keyword_filter);
        $whereClauses[] = "kh.Ten LIKE '%" . $safeKeyword . "%'";
    }
    if ($nhom_filter > 0) {
        $whereClauses[] = "kh.NhomKhoaHocId = " . $nhom_filter;
    } elseif ($danhmuc_filter > 0) {
        $whereClauses[] = "kh.NhomKhoaHocId IN (SELECT NhomKhoaHocId FROM nhomkhoahoc WHERE DanhMucId = " . $danhmuc_filter . ")";
    }
}
$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Xây dựng câu lệnh ORDER BY động
$orderSql = "ORDER BY kh.KhoaHocId DESC"; // Mặc định
if ($sort_filter === 'popular') {
    $orderSql = "ORDER BY kh.IsFeatured DESC, kh.KhoaHocId DESC";
} elseif ($sort_filter === 'price_high_low') {
    $orderSql = "ORDER BY kh.Gia DESC, kh.KhoaHocId DESC";
} elseif ($sort_filter === 'price_low_high') {
    $orderSql = "ORDER BY kh.Gia ASC, kh.KhoaHocId DESC";
}

// 3. Tính tổng số lượng khóa học sau khi lọc để phân trang
$countQuery = "SELECT COUNT(*) as total FROM khoahoc kh $whereSql";
$countResult = $conn->query($countQuery);
$filteredTotalCourses = ($countResult) ? $countResult->fetch_assoc()['total'] : 0;
$totalPages = ceil($filteredTotalCourses / $limit);

// 4. Lấy danh sách Sidebar (Danh mục & Nhóm) đồng thời đếm số lượng khóa học
$sqlSidebar = "SELECT 
            dm.DanhMucId, 
            dm.TenDanhMuc, 
            nkh.NhomKhoaHocId, 
            nkh.TenNhom,
            (SELECT COUNT(*) FROM khoahoc kh WHERE kh.NhomKhoaHocId = nkh.NhomKhoaHocId) as SoLuongKhoaHocTrongNhom
        FROM danhmuc dm
        LEFT JOIN nhomkhoahoc nkh ON dm.DanhMucId = nkh.DanhMucId
        ORDER BY dm.DanhMucId ASC, nkh.NhomKhoaHocId ASC";

$sidebarResult = $conn->query($sqlSidebar);
$sidebarData = [];
if ($sidebarResult && $sidebarResult->num_rows > 0) {
    while ($row = $sidebarResult->fetch_assoc()) {
        $dmId = $row['DanhMucId'];
        if (!isset($sidebarData[$dmId])) {
            $sidebarData[$dmId] = [
                'TenDanhMuc' => $row['TenDanhMuc'],
                'TongSoKhuaHoc' => 0,
                'Nhom' => []
            ];
        }
        if ($row['TenNhom'] !== null) {
            $sidebarData[$dmId]['Nhom'][] = [
                'NhomKhoaHocId' => $row['NhomKhoaHocId'],
                'TenNhom' => $row['TenNhom'],
                'Count' => (int)$row['SoLuongKhoaHocTrongNhom']
            ];
            $sidebarData[$dmId]['TongSoKhuaHoc'] += (int)$row['SoLuongKhoaHocTrongNhom'];
        }
    }
}

// 5. Lấy tổng số lượng tất cả khóa học ban đầu cho việc Reset trạng thái
$totalAllCoursesQuery = "SELECT COUNT(*) as total FROM khoahoc";
$totalAllCoursesResult = $conn->query($totalAllCoursesQuery);
$globalTotalCourses = ($totalAllCoursesResult) ? $totalAllCoursesResult->fetch_assoc()['total'] : 0;

// Nếu là một yêu cầu gọi AJAX từ Client, trả về JSON rồi ngắt luồng xử lý để tối ưu hiệu năng
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    // Lấy danh sách khóa học phân trang
    $coursesQuery = "SELECT kh.KhoaHocId, kh.Ten, kh.Anh, kh.Gia, kh.TenGiangVien, nkh.TenNhom 
                     FROM khoahoc kh
                     LEFT JOIN nhomkhoahoc nkh ON kh.NhomKhoaHocId = nkh.NhomKhoaHocId
                     $whereSql
                     $orderSql
                     LIMIT $limit OFFSET $offset";
    $coursesResult = $conn->query($coursesQuery);
    
    ob_start();
    if ($coursesResult && $coursesResult->num_rows > 0): 
        while ($course = $coursesResult->fetch_assoc()):
            $hasOwned = false;
            $isPendingOrder = false; // Bổ sung biến đánh dấu chờ thanh toán cho trang đầu
            $pendingOrderId = 0;    // Bổ sung biến lưu ID đơn hàng

            if ($currentUserId > 0 && !$isAdminAccount) {
                // 1. Kiểm tra đã sở hữu chưa
                $checkOwnedQuery = "SELECT hdd.TrangThai FROM chitiethangdadat cthdd
                                    JOIN hangdadat hdd ON cthdd.HangDaDatId = hdd.HangDaDatId
                                    WHERE hdd.STT = ? AND cthdd.KhoaHocId = ? AND hdd.TrangThai = 1 LIMIT 1";
                if ($stmtCheck = $conn->prepare($checkOwnedQuery)) {
                    $stmtCheck->bind_param("ii", $currentUserId, $course['KhoaHocId']);
                    $stmtCheck->execute();
                    $resCheck = $stmtCheck->get_result();
                    if ($resCheck && $resCheck->num_rows > 0) { 
                        $hasOwned = true; 
                        
                        // TÍNH TOÁN TIẾN ĐỘ % HOÀN THÀNH (Chia sẻ logic tinh hoa quốc tế từ Index / KhoaHocCuaToi)
                        $progressPercent = 0;
                        $progressQuery = "SELECT 
                                            (SELECT COUNT(*) FROM tiendohocvien td INNER JOIN baihoc bh ON td.BaiHocId = bh.BaiHocId WHERE bh.KhoaHocId = ? AND td.STT = ? AND td.TrangThai = 1) as CompletedLessons, 
                                            (SELECT COUNT(*) FROM baihoc WHERE KhoaHocId = ?) as TotalLessons";
                        if ($stmtProgress = $conn->prepare($progressQuery)) {
                            $stmtProgress->bind_param("iii", $course['KhoaHocId'], $currentUserId, $course['KhoaHocId']);
                            $stmtProgress->execute();
                            $resProgress = $stmtProgress->get_result()->fetch_assoc();
                            if ($resProgress && $resProgress['TotalLessons'] > 0) {
                                $progressPercent = round(($resProgress['CompletedLessons'] / $resProgress['TotalLessons']) * 100);
                            }
                            $stmtProgress->close();
                        }
                    }
                    $stmtCheck->close();
                }

                // 2. Nếu chưa sở hữu, kiểm tra xem có đơn hàng nào đang "Chờ thanh toán" không
                if (!$hasOwned) {
                    $checkPendingQuery = "SELECT hdd.HangDaDatId FROM chitiethangdadat cthdd
                                        JOIN hangdadat hdd ON cthdd.HangDaDatId = hdd.HangDaDatId
                                        WHERE hdd.STT = ? AND cthdd.KhoaHocId = ? AND hdd.TrangThai = 0 
                                        ORDER BY hdd.NgayDat DESC LIMIT 1";
                    if ($stmtPending = $conn->prepare($checkPendingQuery)) {
                        $stmtPending->bind_param("ii", $currentUserId, $course['KhoaHocId']);
                        $stmtPending->execute();
                        $resPending = $stmtPending->get_result();
                        if ($resPending && $resPending->num_rows > 0) {
                            $pendingRow = $resPending->fetch_assoc();
                            $isPendingOrder = true;
                            $pendingOrderId = $pendingRow['HangDaDatId'];
                        }
                        $stmtPending->close();
                    }
                }
            }
            $image_path = !empty($course['Anh']) ? '/DevMaster/' . ltrim($course['Anh'], '/') : '/DevMaster/assets/Images-Videos/default-course.png';
            ?>
            <div id="course-card-<?php echo $course['KhoaHocId']; ?>" class="mini-card-premium <?php echo $hasOwned ? 'course-owned' : ''; ?>">
                <div class="mini-thumb-wrap">
                    <img src="<?php echo htmlspecialchars($image_path); ?>" alt="<?php echo htmlspecialchars($course['Ten']); ?>" onerror="this.src='https://images.unsplash.com/photo-1587620962725-abab7fe55159?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60';">
                    <span class="badge-tag-mini"><?php echo htmlspecialchars($course['TenNhom'] ?? 'Khóa học'); ?></span>
                    <?php if ($hasOwned): ?>
                        <div class="owned-overlay-hover <?php echo ($progressPercent >= 100) ? 'is-completed' : ''; ?>" onclick="window.location.href='/DevMaster/Pages/VaoHocNgay.php?id=<?php echo $course['KhoaHocId']; ?>';">
                            <?php if ($progressPercent >= 100): ?>
                                <span class="play-action-btn completed-btn"><i class="fa-solid fa-circle-check"></i> Hoàn thành</span>
                            <?php else: ?>
                                <span class="play-action-btn"><i class="fa-solid fa-circle-play"></i> Vào học ngay</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="mini-card-body">
                    <h3 class="mini-card-title" title="<?php echo htmlspecialchars($course['Ten']); ?>"><?php echo htmlspecialchars($course['Ten']); ?></h3>
                    <p class="mini-card-author"><?php echo htmlspecialchars($course['TenGiangVien']); ?></p>
                    <div class="mini-card-rating">
                        <span class="rating-num">4.6</span>
                        <div class="rating-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i></div>
                        <span class="rating-count">(<?php echo number_format(rand(1000, 5000)); ?>)</span>
                    </div>
                    <div class="mini-card-price-row">
                        <?php if ($isAdminAccount): ?>
                            <span class="price-admin-tag"><i class="fa-solid fa-user-gear"></i> Chế độ quản trị</span>
                        <?php elseif ($hasOwned): ?>
                            <span class="price-owned-label">Đã sở hữu</span>
                        <?php else: ?>
                            <span class="price-original"><?php echo number_format($course['Gia'], 0, ',', '.'); ?>đ</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mini-card-footer">
                    <?php if ($isAdminAccount): ?>
                        <button type="button" class="btn-add-to-cart btn-admin-edit" onclick="window.location.href='/DevMaster/Admin/QuanLyKhoaHoc.php?edit_id=<?php echo $course['KhoaHocId']; ?>';">
                            <i class="fa-solid fa-pen-to-square"></i> Chỉnh sửa khóa học
                        </button>
                    <?php elseif ($hasOwned): ?>
                        <button type="button" class="btn-add-to-cart btn-goto-study-now" onclick="window.location.href='/DevMaster/Pages/VaoHocNgay.php?id=<?php echo $course['KhoaHocId']; ?>';">
                            <i class="fa-solid fa-circle-play"></i> Vào học ngay
                        </button>
                    <?php elseif ($isPendingOrder): ?>
                        <button type="button" class="btn-add-to-cart btn-pay-pending" style="background: linear-gradient(135deg, #f59e0b, #ea580c) !important; color: white !important; font-weight: 600;" onclick="window.location.href='/DevMaster/Pages/DonHang.php?auto_open=<?php echo $pendingOrderId; ?>';">
                            <i class="fa-solid fa-credit-card"></i> Thanh toán ngay
                        </button>
                    <?php else: ?>
                        <?php $is_in_cart = isset($_SESSION['cart']) && in_array($course['KhoaHocId'], $_SESSION['cart']); ?>
                        <?php if ($is_in_cart): ?>
                            <button type="button" class="btn-add-to-cart btn-view-cart-active" onclick="window.location.href='/DevMaster/Pages/GioHang.php';">
                                <i class="fa-solid fa-arrow-right-to-bracket"></i> Xem Giỏ Hàng
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn-add-to-cart" data-id="<?php echo $course['KhoaHocId']; ?>" onclick="handleCartAction(this)">
                                <i class="fas fa-shopping-cart"></i> Thêm vào giỏ hàng
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="text-center w-100 py-4" style="grid-column: span 3; text-align: center; color: #94a3b8; padding: 40px 0;">
            <p><i class="fa-solid fa-box-open" style="font-size: 24px; margin-bottom: 8px;"></i> Hiện tại không có dữ liệu khóa học nào phù hợp bộ lọc.</p>
        </div>
    <?php endif; 
    $htmlContent = ob_get_clean();

    // Tạo HTML phân trang chuẩn quốc tế
    ob_start();
    if ($totalPages > 1): ?>
        <nav class="pagination-nav-wrapper">
            <ul class="custom-pagination">
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <button class="page-link" data-page="<?php echo $page - 1; ?>"><i class="fa-solid fa-chevron-left"></i></button>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <button class="page-link" data-page="<?php echo $i; ?>"><?php echo $i; ?></button>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                    <button class="page-link" data-page="<?php echo $page + 1; ?>"><i class="fa-solid fa-chevron-right"></i></button>
                </li>
            </ul>
        </nav>
    <?php endif;
    $paginationContent = ob_get_clean();

    header('Content-Type: application/json');
    echo json_encode([
        'html' => $htmlContent,
        'pagination' => $paginationContent,
        'total' => $filteredTotalCourses,
        'banner_title' => $banner_title,
        'banner_subtitle' => $banner_subtitle
    ]);
    exit;
}

// Chuẩn bị dữ liệu cho lần tải trang đầu tiên (Non-AJAX)
$coursesQuery = "SELECT kh.KhoaHocId, kh.Ten, kh.Anh, kh.Gia, kh.TenGiangVien, nkh.TenNhom 
                 FROM khoahoc kh
                 LEFT JOIN nhomkhoahoc nkh ON kh.NhomKhoaHocId = nkh.NhomKhoaHocId
                 $whereSql
                 $orderSql
                 LIMIT $limit OFFSET $offset";
$coursesResult = $conn->query($coursesQuery);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tất Cả Khóa Học - DEV MASTER</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/DevMaster/Assets/Style.css">
</head>
<body>

<?php include '../Includes/Header.php'; ?>

<div class="courses-hero-banner">
    <div class="banner-content">
        <div class="banner-breadcrumbs-route" id="dynamic-banner-route"><?php echo $banner_subtitle; ?></div>
        <h1 id="dynamic-banner-title"><?php echo htmlspecialchars($banner_title); ?></h1>
        <p class="course-counter"><span id="dynamic-total-counter"><?php echo $filteredTotalCourses; ?></span> khóa học được tìm thấy</p>
    </div>
</div>

<div class="courses-page-container">
    <div class="courses-layout-inner">
        
        <aside class="courses-sidebar">
            <div class="sidebar-box">
                <h3 class="sidebar-title">Danh mục</h3>
                
                <div class="sidebar-search-wrapper">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" id="category-search-input" placeholder="Tìm danh mục...">
                </div>

                <div class="sidebar-filter-sort-section">
                    <div class="filter-group">
                        <label class="filter-label"><i class="fa-solid fa-arrow-down-short-wide"></i> Sắp xếp theo</label>
                        <div class="custom-select-wrapper">
                            <select id="filter-sort-select" class="elegant-select">
                                <option value="">-- Mặc định --</option>
                                <option value="popular" <?php echo ($sort_filter === 'popular') ? 'selected' : ''; ?>>Phổ biến nhất</option>
                                <option value="price_high_low" <?php echo ($sort_filter === 'price_high_low') ? 'selected' : ''; ?>>Giá: Cao -> Thấp</option>
                                <option value="price_low_high" <?php echo ($sort_filter === 'price_low_high') ? 'selected' : ''; ?>>Giá: Thấp -> Cao</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-action-buttons">
                        <button type="button" id="btn-apply-filters" class="btn-filter-apply">Áp dụng bộ lọc</button>
                        <button type="button" id="btn-reset-filters" class="btn-filter-reset">Xóa bộ lọc</button>
                    </div>
                </div>

                <div class="sidebar-tree-container" style="margin-top: 20px;">
                    <?php foreach ($sidebarData as $dmId => $data): ?>
                        <?php                         
                        $isDmActive = ($danhmuc_filter === $dmId);
                        $hasActiveChild = false;
                        if ($nhom_filter > 0) {
                            foreach ($data['Nhom'] as $n) {
                                if ($n['NhomKhoaHocId'] === $nhom_filter) { $hasActiveChild = true; break; }
                            }
                        }
                        $isCollapsed = !($isDmActive || $hasActiveChild);
                        ?>
                        <div class="tree-danhmuc-block <?php echo $isCollapsed ? 'collapsed' : ''; ?> <?php echo ($isDmActive && !$hasActiveChild) ? 'active-highlight' : ''; ?>" 
                             data-danhmuc-id="<?php echo $dmId; ?>" 
                             data-danhmuc-name="<?php echo htmlspecialchars(mb_strtolower($data['TenDanhMuc'], 'UTF-8')); ?>">
                            
                            <div class="tree-danhmuc-header">
                                <div class="header-left-info">
                                    <span class="txt-danhmuc-title"><?php echo htmlspecialchars($data['TenDanhMuc']); ?></span>
                                </div>
                                <div class="header-right-actions" style="display: flex; align-items: center; gap: 8px;">
                                    <span class="badge-count-courses">(<?php echo $data['TongSoKhuaHoc']; ?>)</span>
                                    <button class="btn-tree-toggle" type="button">
                                        <i class="fa-solid fa-chevron-down"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="tree-nhom-list">
                                <?php if (!empty($data['Nhom'])): ?>
                                    <?php foreach ($data['Nhom'] as $nhom): ?>
                                        <?php $isNhomActive = ($nhom_filter === $nhom['NhomKhoaHocId']); ?>
                                        <div class="tree-nhom-item <?php echo $isNhomActive ? 'active-highlight' : ''; ?>" 
                                             data-nhom-id="<?php echo $nhom['NhomKhoaHocId']; ?>" 
                                             data-nhom-name="<?php echo htmlspecialchars(mb_strtolower($nhom['TenNhom'], 'UTF-8')); ?>">
                                            <div class="nhom-title-left" style="display: flex; align-items: center; gap: 8px;">
                                                <i class="fa-solid fa-turn-down icon-enter-indicator" style="display: inline-block; transform: scaleX(-1) rotate(90deg); margin-right: 4px;"></i>
                                                <span class="txt-nhom-title"><?php echo htmlspecialchars($nhom['TenNhom']); ?></span>
                                            </div>
                                            <span class="badge-count-courses-sub">(<?php echo $nhom['Count']; ?>)</span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="tree-nhom-empty">Trống</div>
                                <?php endif; ?>
                            </div>

                        </div>
                    <?php endforeach; ?>
                    
                    <div id="search-no-results" style="display: none; padding: 20px; text-align: center; color: #94a3b8; font-style: italic; font-size: 14px;">
                        Không tìm thấy danh mục phù hợp...
                    </div>
                </div>

            </div>
        </aside>

        <main class="courses-main-content">
            <div class="courses-grid-layout" id="dynamic-courses-grid">
                <?php if ($coursesResult && $coursesResult->num_rows > 0): ?>
                    <?php while ($course = $coursesResult->fetch_assoc()): ?>
                        <?php
                        $hasOwned = false;
                        $isPendingOrder = false; // BỔ SUNG BIẾN NÀY
                        $pendingOrderId = 0;     // BỔ SUNG BIẾN NÀY

                        if ($currentUserId > 0 && !$isAdminAccount) {
                            // 1. Kiểm tra đã sở hữu chưa
                            $checkOwnedQuery = "SELECT hdd.TrangThai 
                                                FROM chitiethangdadat cthdd
                                                JOIN hangdadat hdd ON cthdd.HangDaDatId = hdd.HangDaDatId
                                                WHERE hdd.STT = ? AND cthdd.KhoaHocId = ? AND hdd.TrangThai = 1 
                                                LIMIT 1";
                            if ($stmtCheck = $conn->prepare($checkOwnedQuery)) {
                                $stmtCheck->bind_param("ii", $currentUserId, $course['KhoaHocId']);
                                $stmtCheck->execute();
                                $resCheck = $stmtCheck->get_result();
                                if ($resCheck && $resCheck->num_rows > 0) { 
                                    $hasOwned = true; 
                                    
                                    // TÍNH TOÁN TIẾN ĐỘ % HOÀN THÀNH (Chia sẻ logic tinh hoa quốc tế từ Index / KhoaHocCuaToi)
                                    $progressPercent = 0;
                                    $progressQuery = "SELECT 
                                                        (SELECT COUNT(*) FROM tiendohocvien td INNER JOIN baihoc bh ON td.BaiHocId = bh.BaiHocId WHERE bh.KhoaHocId = ? AND td.STT = ? AND td.TrangThai = 1) as CompletedLessons, 
                                                        (SELECT COUNT(*) FROM baihoc WHERE KhoaHocId = ?) as TotalLessons";
                                    if ($stmtProgress = $conn->prepare($progressQuery)) {
                                        $stmtProgress->bind_param("iii", $course['KhoaHocId'], $currentUserId, $course['KhoaHocId']);
                                        $stmtProgress->execute();
                                        $resProgress = $stmtProgress->get_result()->fetch_assoc();
                                        if ($resProgress && $resProgress['TotalLessons'] > 0) {
                                            $progressPercent = round(($resProgress['CompletedLessons'] / $resProgress['TotalLessons']) * 100);
                                        }
                                        $stmtProgress->close();
                                    }
                                }
                                $stmtCheck->close();
                            }

                            // 2. BỔ SUNG: Nếu chưa sở hữu, kiểm tra đơn hàng "Chờ thanh toán" (TrangThai = 0)
                            if (!$hasOwned) {
                                $checkPendingQuery = "SELECT hdd.HangDaDatId FROM chitiethangdadat cthdd
                                                    JOIN hangdadat hdd ON cthdd.HangDaDatId = hdd.HangDaDatId
                                                    WHERE hdd.STT = ? AND cthdd.KhoaHocId = ? AND hdd.TrangThai = 0 
                                                    ORDER BY hdd.NgayDat DESC LIMIT 1";
                                if ($stmtPending = $conn->prepare($checkPendingQuery)) {
                                    $stmtPending->bind_param("ii", $currentUserId, $course['KhoaHocId']);
                                    $stmtPending->execute();
                                    $resPending = $stmtPending->get_result();
                                    if ($resPending && $resPending->num_rows > 0) {
                                        $pendingRow = $resPending->fetch_assoc();
                                        $isPendingOrder = true;
                                        $pendingOrderId = $pendingRow['HangDaDatId'];
                                    }
                                    $stmtPending->close();
                                }
                            }
                        }
                        $image_path = !empty($course['Anh']) ? '/DevMaster/' . ltrim($course['Anh'], '/') : '/DevMaster/assets/Images-Videos/default-course.png';
                        ?>
                        
                        <div id="course-card-<?php echo $course['KhoaHocId']; ?>" class="mini-card-premium <?php echo $hasOwned ? 'course-owned' : ''; ?>">
                            <div class="mini-thumb-wrap">
                                <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                     alt="<?php echo htmlspecialchars($course['Ten']); ?>" 
                                     onerror="this.src='https://images.unsplash.com/photo-1587620962725-abab7fe55159?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60';">
                                <span class="badge-tag-mini"><?php echo htmlspecialchars($course['TenNhom'] ?? 'Khóa học'); ?></span>
                                <?php if ($hasOwned): ?>
                                    <div class="owned-overlay-hover <?php echo ($progressPercent >= 100) ? 'is-completed' : ''; ?>" onclick="window.location.href='/DevMaster/Pages/VaoHocNgay.php?id=<?php echo $course['KhoaHocId']; ?>';">
                                        <?php if ($progressPercent >= 100): ?>
                                            <span class="play-action-btn completed-btn"><i class="fa-solid fa-circle-check"></i> Hoàn thành</span>
                                        <?php else: ?>
                                            <span class="play-action-btn"><i class="fa-solid fa-circle-play"></i> Vào học ngay</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mini-card-body">
                                <h3 class="mini-card-title" title="<?php echo htmlspecialchars($course['Ten']); ?>">
                                    <?php echo htmlspecialchars($course['Ten']); ?>
                                </h3>
                                <p class="mini-card-author"><?php echo htmlspecialchars($course['TenGiangVien']); ?></p>
                                <div class="mini-card-rating">
                                    <span class="rating-num">4.6</span>
                                    <div class="rating-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i></div>
                                    <span class="rating-count">(<?php echo number_format(rand(1000, 5000)); ?>)</span>
                                </div>
                                <div class="mini-card-price-row">
                                    <?php if ($isAdminAccount): ?>
                                        <span class="price-admin-tag"><i class="fa-solid fa-user-gear"></i> Chế độ quản trị</span>
                                    <?php elseif ($hasOwned): ?>
                                        <span class="price-owned-label">Đã sở hữu</span>
                                    <?php else: ?>
                                        <span class="price-original"><?php echo number_format($course['Gia'], 0, ',', '.'); ?>đ</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mini-card-footer">
                                <?php if ($isAdminAccount): ?>
                                    <button type="button" class="btn-add-to-cart btn-admin-edit" onclick="window.location.href='/DevMaster/Admin/QuanLyKhoaHoc.php?edit_id=<?php echo $course['KhoaHocId']; ?>';">
                                        <i class="fa-solid fa-pen-to-square"></i> Chỉnh sửa khóa học
                                    </button>
                                <?php elseif ($hasOwned): ?>
                                    <button type="button" class="btn-add-to-cart btn-goto-study-now" onclick="window.location.href='/DevMaster/Pages/VaoHocNgay.php?id=<?php echo $course['KhoaHocId']; ?>';">
                                        <i class="fa-solid fa-circle-play"></i> Vào học ngay
                                    </button>
                                    
                                <?php elseif ($isPendingOrder): ?>
                                    <button type="button" class="btn-add-to-cart btn-pay-pending" style="background: linear-gradient(135deg, #f59e0b, #ea580c) !important; color: white !important; font-weight: 600;" onclick="window.location.href='/DevMaster/Pages/DonHang.php?auto_open=<?php echo $pendingOrderId; ?>';">
                                        <i class="fa-solid fa-credit-card"></i> Thanh toán ngay
                                    </button>
                                    
                                <?php else: ?>
                                    <?php $is_in_cart = isset($_SESSION['cart']) && in_array($course['KhoaHocId'], $_SESSION['cart']); ?>
                                    <?php if ($is_in_cart): ?>
                                        <button type="button" class="btn-add-to-cart btn-view-cart-active" data-id="<?php echo $course['KhoaHocId']; ?>" onclick="handleCartAction(this)">
                                            <i class="fa-solid fa-arrow-right-to-bracket"></i> Xem Giỏ Hàng
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn-add-to-cart" data-id="<?php echo $course['KhoaHocId']; ?>" onclick="handleCartAction(this)">
                                            <i class="fas fa-shopping-cart"></i> Thêm vào giỏ hàng
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div> 
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center w-100 py-4" style="grid-column: span 3; text-align: center; color: #94a3b8; padding: 40px 0;">
                        <p><i class="fa-solid fa-box-open" style="font-size: 24px; margin-bottom: 8px;"></i> Hiện tại hệ thống chưa có dữ liệu khóa học nào.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div id="dynamic-pagination-container" style="margin-top: 40px;">
                <?php if ($totalPages > 1): ?>
                    <nav class="pagination-nav-wrapper">
                        <ul class="custom-pagination">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <button class="page-link" data-page="<?php echo $page - 1; ?>"><i class="fa-solid fa-chevron-left"></i></button>
                            </li>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <button class="page-link" data-page="<?php echo $i; ?>"><?php echo $i; ?></button>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                <button class="page-link" data-page="<?php echo $page + 1; ?>"><i class="fa-solid fa-chevron-right"></i></button>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </main>

    </div>
</div>

<?php include '../Includes/Footer.php'; ?>
<script src="/DevMaster/assets/Javascript.js"></script> 
<script>
    document.addEventListener("DOMContentLoaded", function() {
        let currentFilters = {
            danhmuc_id: <?php echo $danhmuc_filter; ?>,
            nhom_id: <?php echo $nhom_filter; ?>,
            sort: '<?php echo $sort_filter; ?>',
            page: <?php echo $page; ?>,
            // Thêm 2 thuộc tính lưu trạng thái tìm kiếm nâng cao
            keyword: '<?php echo $conn->real_escape_string($keyword_filter); ?>',
            click_id: <?php echo $click_id_filter; ?>
        };

        // Đồng bộ đẩy tham số keyword và click_id vào hàm fetch AJAX cốt lõi
        function fetchFilteredCourses(resetScroll = true) {
            let urlParams = new URLSearchParams();
            urlParams.append('ajax', '1');
            if (currentFilters.danhmuc_id > 0) urlParams.append('danhmuc_id', currentFilters.danhmuc_id);
            if (currentFilters.nhom_id > 0) urlParams.append('nhom_id', currentFilters.nhom_id);
            if (currentFilters.sort) urlParams.append('sort', currentFilters.sort);
            if (currentFilters.keyword) urlParams.append('keyword', currentFilters.keyword);
            if (currentFilters.click_id > 0) urlParams.append('click_id', currentFilters.click_id);
            urlParams.append('page', currentFilters.page);

            fetch(`TatCaKhoaHoc.php?${urlParams.toString()}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('dynamic-courses-grid').innerHTML = data.html;
                document.getElementById('dynamic-pagination-container').innerHTML = data.pagination;
                document.getElementById('dynamic-total-counter').innerText = data.total;
                document.getElementById('dynamic-banner-title').innerText = data.banner_title;
                
                const routeElement = document.getElementById('dynamic-banner-route');
                if (data.banner_subtitle) {
                    routeElement.innerHTML = data.banner_subtitle;
                    routeElement.style.display = 'block';
                } else {
                    routeElement.style.display = 'none';
                }
                
                // Kích hoạt lại hiệu ứng Jumpy nếu kết quả AJAX trả về có chứa thẻ card đích danh
                triggerJumpyAnimation();
            });
        }

        // HÀM HIỆU ỨNG ĐỈNH CAO: TỰ ĐỘNG JUMP HIGHLIGHT VÀ SMOOTH SCROLL TO TARGET
        function triggerJumpyAnimation() {
            if (currentFilters.click_id > 0) {
                const targetCard = document.getElementById(`course-card-${currentFilters.click_id}`);
                if (targetCard) {
                    // Thêm class tạo hiệu ứng rung lắc nhấp nhô nhảy nẩy 
                    targetCard.classList.add('jumpy-highlight-premium');
                    
                    // Cuộn mượt mà đưa thẻ khóa học này vào chính giữa khung nhìn của học viên
                    targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    // Sau 3 giây tự động gỡ bỏ class hiệu ứng để phục hồi UX ban đầu
                    setTimeout(() => {
                        targetCard.classList.remove('jumpy-highlight-premium');
                    }, 3000);
                }
            }
        }

        // Chạy kích hoạt hiệu ứng ngay lần đầu tiên tải trang tĩnh xong
        triggerJumpyAnimation();

        const searchInput = document.getElementById('category-search-input');
        const danhmucBlocks = document.querySelectorAll('.tree-danhmuc-block');
        const noResultsMsg = document.getElementById('search-no-results');

        // --- FUNCTION LÕI: GỌI DỮ LIỆU BẰNG THUẬT TOÁN AJAX KHÔNG REFRESH TRANG ---
        function fetchFilteredCourses(resetScroll = true, isPopState = false) {
            let urlParams = new URLSearchParams();
            urlParams.append('ajax', '1');
            if (currentFilters.danhmuc_id > 0) urlParams.append('danhmuc_id', currentFilters.danhmuc_id);
            if (currentFilters.nhom_id > 0) urlParams.append('nhom_id', currentFilters.nhom_id);
            if (currentFilters.sort) urlParams.append('sort', currentFilters.sort);
            if (currentFilters.keyword) urlParams.append('keyword', currentFilters.keyword);
            if (currentFilters.click_id > 0) urlParams.append('click_id', currentFilters.click_id);
            urlParams.append('page', currentFilters.page);

            fetch(`TatCaKhoaHoc.php?${urlParams.toString()}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('dynamic-courses-grid').innerHTML = data.html;
                document.getElementById('dynamic-pagination-container').innerHTML = data.pagination;
                document.getElementById('dynamic-total-counter').innerText = data.total;
                document.getElementById('dynamic-banner-title').innerText = data.banner_title;
                
                const routeElement = document.getElementById('dynamic-banner-route');
                if (data.banner_subtitle) {
                    routeElement.innerHTML = data.banner_subtitle;
                    routeElement.style.display = 'block';
                } else {
                    routeElement.innerHTML = '';
                    routeElement.style.display = 'none';
                }

                // CHỈ pushState khi người dùng click chủ động, KHÔNG pushState khi đang bấm nút Back/Forward (popstate)
                if (!isPopState) {
                    let pushParams = new URLSearchParams(urlParams.toString());
                    pushParams.delete('ajax'); // Xóa bỏ tham số ajax một cách an toàn và sạch sẽ
                    
                    let newUrl = window.location.pathname + (pushParams.toString() ? '?' + pushParams.toString() : '');
                    window.history.pushState({ filters: Object.assign({}, currentFilters) }, '', newUrl);
                }

                if (resetScroll) {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
                
                // Gọi an toàn hàm trigger hiệu ứng nẩy thẻ nếu có click_id
                if (typeof triggerJumpyAnimation === "function") {
                    triggerJumpyAnimation();
                }
                if (typeof calculateStickySidebar === "function") {
                    calculateStickySidebar();
                }
            })
            .catch(error => console.error('Lỗi thực thi AJAX:', error));
        }

        // --- XỬ LÝ SỰ KIỆN CLICK VÀO THÂN DANH MỤC CHA ---
        document.querySelectorAll('.tree-danhmuc-header').forEach(header => {
            header.addEventListener('click', function(e) {
                // Nếu click trúng vào mũi tên toggle tròn thì dừng lại, nhường cho sự kiện của mũi tên
                if (e.target.closest('.btn-tree-toggle')) return;

                const block = this.closest('.tree-danhmuc-block');
                const dmId = parseInt(block.getAttribute('data-danhmuc-id'));

                // Xóa highlight toàn hệ thống
                danhmucBlocks.forEach(b => b.classList.remove('active-highlight'));
                document.querySelectorAll('.tree-nhom-item').forEach(item => item.classList.remove('active-highlight'));

                // Mở toang khối được chọn
                block.classList.remove('collapsed');
                block.classList.add('active-highlight');

                // Cập nhật bộ lọc
                currentFilters.danhmuc_id = dmId;
                currentFilters.nhom_id = 0; // Reset nhóm con khi chọn danh mục cha
                currentFilters.page = 1;

                fetchFilteredCourses();
            });
        });

        // --- XỬ LÝ SỰ KIỆN CLICK VÀO MŨI TÊN CHUYỂN ĐỘNG TRÒN ---
        document.querySelectorAll('.btn-tree-toggle').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation(); // Tuyệt đối chặn nổi bọt
                const block = this.closest('.tree-danhmuc-block');
                block.classList.toggle('collapsed');
            });
        });

        // --- XỬ LÝ SỰ KIỆN CLICK VÀO NHÓM KHÓA HỌC CON ---
        document.querySelectorAll('.tree-nhom-item').forEach(item => {
            item.addEventListener('click', function() {
                const block = this.closest('.tree-danhmuc-block');
                const nhomId = parseInt(this.getAttribute('data-nhom-id'));

                // Xóa highlight của tất cả danh mục cha và các nhóm khác
                danhmucBlocks.forEach(b => b.classList.remove('active-highlight'));
                document.querySelectorAll('.tree-nhom-item').forEach(i => i.classList.remove('active-highlight'));

                // Áp dụng highlight duy nhất cho nhóm con hiện tại
                this.classList.add('active-highlight');

                // Giữ cho danh mục cha mở toang ra để biểu diễn cấu trúc nhóm
                block.classList.remove('collapsed');

                // Thiết lập bộ lọc
                currentFilters.nhom_id = nhomId;
                currentFilters.danhmuc_id = 0;
                currentFilters.page = 1;

                fetchFilteredCourses();
            });
        });

        // --- LẮNG NGHE SỰ KIỆN PHÂN TRANG ĐỘNG ---
        document.getElementById('dynamic-pagination-container').addEventListener('click', function(e) {
            const targetBtn = e.target.closest('.page-link');
            if (!targetBtn) return;
            
            const parentLi = targetBtn.parentElement;
            if (parentLi.classList.contains('disabled') || parentLi.classList.contains('active')) return;

            const targetPage = parseInt(targetBtn.getAttribute('data-page'));
            if (targetPage > 0) {
                currentFilters.page = targetPage;
                fetchFilteredCourses(true);
            }
        });

        // --- KÍCH HOẠT NÚT ÁP DỤNG BỘ LỌC SẮP XẾP ---
        document.getElementById('btn-apply-filters').addEventListener('click', function() {
            const sortValue = document.getElementById('filter-sort-select').value;
            currentFilters.sort = sortValue;
            currentFilters.page = 1; // Quay về trang 1 khi lọc mới
            fetchFilteredCourses();
        });

        // --- TERMINATE BỘ LỌC (QUAY LẠI TRẠNG THÁI BAN ĐẦU) ---
        document.getElementById('btn-reset-filters').addEventListener('click', function() {
            document.getElementById('filter-sort-select').value = '';
            if(searchInput) searchInput.value = '';
            
            // Clear toàn bộ highlight & đóng các nhánh cây
            danhmucBlocks.forEach(b => {
                b.classList.add('collapsed');
                b.classList.remove('active-highlight');
                b.style.display = 'block';
                b.classList.remove('only-show-nhom');
            });
            document.querySelectorAll('.tree-nhom-item').forEach(i => {
                i.classList.remove('active-highlight');
                i.style.display = 'flex';
            });
            if (noResultsMsg) noResultsMsg.style.display = 'none';

            currentFilters = {
                danhmuc_id: 0,
                nhom_id: 0,
                sort: '',
                page: 1
            };
            fetchFilteredCourses();
        });

        // --- THUẬT TOÁN TÌM KIẾM DÂN GIAN TRONG SIDEBAR TREE ---
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const keyword = this.value.trim().toLowerCase();
                let hasAnyResult = false;

                if (keyword === '') {
                    danhmucBlocks.forEach(block => {
                        block.style.display = 'block';
                        block.classList.remove('only-show-nhom');
                        block.classList.add('collapsed');
                        block.querySelectorAll('.tree-nhom-item').forEach(item => item.style.display = 'flex');
                    });
                    if (noResultsMsg) noResultsMsg.style.display = 'none';
                    calculateStickySidebar();
                    return;
                }

                danhmucBlocks.forEach(block => {
                    const danhmucName = block.getAttribute('data-danhmuc-name') || '';
                    const nhomItems = block.querySelectorAll('.tree-nhom-item');
                    let matchDanhMuc = danhmucName.includes(keyword);
                    let matchNhomCount = 0;

                    nhomItems.forEach(item => {
                        const nhomName = item.getAttribute('data-nhom-name') || '';
                        if (nhomName.includes(keyword)) {
                            item.style.display = 'flex';
                            matchNhomCount++;
                        } else {
                            item.style.display = 'none';
                        }
                    });

                    if (!matchDanhMuc && matchNhomCount > 0) {
                        block.style.display = 'block';
                        block.classList.add('only-show-nhom');
                        block.classList.remove('collapsed'); 
                        hasAnyResult = true;
                    } else if (matchDanhMuc) {
                        block.style.display = 'block';
                        block.classList.remove('only-show-nhom');
                        block.classList.remove('collapsed');
                        nhomItems.forEach(item => item.style.display = 'flex');
                        hasAnyResult = true;
                    } else {
                        block.style.display = 'none';
                        block.classList.remove('only-show-nhom');
                        block.classList.add('collapsed');
                    }
                });

                if (noResultsMsg) noResultsMsg.style.display = hasAnyResult ? 'none' : 'block';
                calculateStickySidebar();
            });
            // --- TỰ ĐỘNG SỔ DANH MỤC VÀ HIGHLIGHT NHÓM KHI VÀO TỪ URL (?nhom_id=...) ---
            if (currentFilters.nhom_id > 0) {
                // Tìm chính xác phần tử nhóm khóa học có id trùng với URL
                const targetNhomItem = document.querySelector(`.tree-nhom-item[data-nhom-id="${currentFilters.nhom_id}"]`);
                if (targetNhomItem) {
                    // Xóa hết highlight cũ của danh mục nếu có
                    danhmucBlocks.forEach(b => b.classList.remove('active-highlight'));
                    
                    // Highlight trực tiếp nhóm khóa học này
                    targetNhomItem.classList.add('active-highlight');
                    
                    // Tìm block danh mục cha của nó và ép mở ra (xóa class collapsed)
                    const parentBlock = targetNhomItem.closest('.tree-danhmuc-block');
                    if (parentBlock) {
                        parentBlock.classList.remove('collapsed');
                        // Cuộn mượt sidebar đến vùng hiển thị của danh mục này
                        setTimeout(() => {
                            parentBlock.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }, 300);
                    }
                }
            } else {
                // Khôi phục lại logic cuộn mượt mặc định cho danh mục cha nếu vào từ ?danhmuc_id=...
                const activeHighlightEl = document.querySelector('.sidebar-tree-container .active-highlight');
                if (activeHighlightEl) {
                    const parentBlock = activeHighlightEl.closest('.tree-danhmuc-block');
                    if (parentBlock) {
                        parentBlock.classList.remove('collapsed');
                    }
                    setTimeout(() => {
                        activeHighlightEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }, 300);
                }
            }
        }
        // --- TỰ ĐỘNG SỔ DANH MỤC VÀ HIGHLIGHT KHI ĐI VÀO TỪ URL FOOTER (?danhmuc_id=...) ---
        if (currentFilters.danhmuc_id > 0 && currentFilters.nhom_id === 0) {
            // Tìm chính xác khối danh mục cha có id trùng với tham số truyền từ Footer
            const targetDmBlock = document.querySelector(`.tree-danhmuc-block[data-danhmuc-id="${currentFilters.danhmuc_id}"]`);
            if (targetDmBlock) {
                // Xóa sạch tất cả các trạng thái highlight cũ trên cây thư mục sidebar
                danhmucBlocks.forEach(b => b.classList.remove('active-highlight'));
                document.querySelectorAll('.tree-nhom-item').forEach(i => i.classList.remove('active-highlight'));
                
                // Kích hoạt mở bung (xóa collapsed) và thêm hiệu ứng highlight danh mục được chọn
                targetDmBlock.classList.remove('collapsed');
                targetDmBlock.classList.add('active-highlight');
                
                // Cuộn mượt màn hình (Smooth Scroll) đưa sidebar tới vùng hiển thị của danh mục này
                setTimeout(() => {
                    targetDmBlock.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 300);
            }
        }
        // --- ĐỒNG BỘ HOÀN HẢO KHI BẤM NÚT BACK/FORWARD CỦA TRÌNH DUYỆT (POPSTATE ENGINE) ---
        window.addEventListener('popstate', function(event) {
            const newParams = new URLSearchParams(window.location.search);
            
            currentFilters.danhmuc_id = parseInt(newParams.get('danhmuc_id')) || 0;
            currentFilters.nhom_id    = parseInt(newParams.get('nhom_id')) || 0;
            currentFilters.sort       = newParams.get('sort') || '';
            currentFilters.keyword    = newParams.get('keyword') || '';
            currentFilters.click_id   = parseInt(newParams.get('click_id')) || 0;
            currentFilters.page       = parseInt(newParams.get('page')) || 1;
            
            const sortSelect = document.getElementById('filter-sort-select');
            if (sortSelect) {
                sortSelect.value = currentFilters.sort;
            }

            // --- TIẾN HÀNH ĐỒNG BỘ LẠI GIAO DIỆN CÂY SIDEBAR THEO URL MỚI CẬP NHẬT ---
            danhmucBlocks.forEach(b => {
                b.classList.remove('active-highlight');
                b.classList.add('collapsed'); // Mặc định đóng lại hết
            });
            document.querySelectorAll('.tree-nhom-item').forEach(i => i.classList.remove('active-highlight'));

            if (currentFilters.nhom_id > 0) {
                const targetNhomItem = document.querySelector(`.tree-nhom-item[data-nhom-id="${currentFilters.nhom_id}"]`);
                if (targetNhomItem) {
                    targetNhomItem.classList.add('active-highlight');
                    const parentBlock = targetNhomItem.closest('.tree-danhmuc-block');
                    if (parentBlock) parentBlock.classList.remove('collapsed');
                }
            } else if (currentFilters.danhmuc_id > 0) {
                const targetDmBlock = document.querySelector(`.tree-danhmuc-block[data-danhmuc-id="${currentFilters.danhmuc_id}"]`);
                if (targetDmBlock) {
                    targetDmBlock.classList.remove('collapsed');
                    targetDmBlock.classList.add('active-highlight');
                }
            }

            // Truyền tham số thứ 2 là `true` (isPopState = true) để chặn vòng lặp pushState vô tận
            fetchFilteredCourses(true, true); 
        });
    });
</script>
</body>
</html>