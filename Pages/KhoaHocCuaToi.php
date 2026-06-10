<?php
// Pages/KhoaHocCuaToi.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../Database.php';
include '../includes/Header.php';

// Đồng bộ biến kết nối giữa các file hệ thống (ưu tiên sử dụng kết nối chuẩn PDO từ file Database)
$db = isset($connect) ? $connect : $conn;

$stt_user = isset($_SESSION['UserId']) ? intval($_SESSION['UserId']) : 0;

// 2. Cấu hình phân trang chuẩn phong cách quốc tế
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12; // Giới hạn tối đa 12 khóa học (3 cột x 4 hàng) trên 1 trang như yêu cầu
$offset = ($page - 1) * $limit;

try {
    // --- BƯỚC A: ĐẾM TỔNG SỐ KHÓA HỌC ĐÃ MUA THÀNH CÔNG (TrangThai đơn hàng = 1) ---
    $countQuery = "SELECT COUNT(DISTINCT cthdd.KhoaHocId) AS Total 
                   FROM chitiethangdadat cthdd
                   INNER JOIN hangdadat hdd ON cthdd.HangDaDatId = hdd.HangDaDatId
                   WHERE hdd.STT = ? AND hdd.TrangThai = 1";
                   
    if ($db instanceof PDO) {
        $stmtCount = $db->prepare($countQuery);
        $stmtCount->execute([$stt_user]);
        $totalCourses = $stmtCount->fetch(PDO::FETCH_ASSOC)['Total'] ?? 0;
    } else {
        $stmtCount = $db->prepare($countQuery);
        $stmtCount->bind_param("i", $stt_user);
        $stmtCount->execute();
        $totalCourses = $stmtCount->get_result()->fetch_assoc()['Total'] ?? 0;
    }
    
    $totalPages = ceil($totalCourses / $limit);

    // --- BƯỚC B: TRUY VẤN DỮ LIỆU ĐA TẦNG (Khóa học + Tiến độ % + Ngày Mua) ---
    // Thuật toán gộp subquery tính toán tiến độ tương tự VaoHocNgay.php giúp tối ưu hóa bộ nhớ RAM server
    $query = "SELECT 
                kh.KhoaHocId, kh.Ten, kh.Anh, nkh.TenNhom, hdd.NgayDat,
                (SELECT COUNT(bh.BaiHocId) FROM baihoc bh WHERE bh.KhoaHocId = kh.KhoaHocId) AS TongSoBai,
                (SELECT COUNT(td.BaiHocId) FROM tiendohocvien td 
                 INNER JOIN baihoc bh ON td.BaiHocId = bh.BaiHocId 
                 WHERE bh.KhoaHocId = kh.KhoaHocId AND td.STT = ? AND td.TrangThai = 1) AS BaiDaXong
              FROM chitiethangdadat cthdd
              INNER JOIN hangdadat hdd ON cthdd.HangDaDatId = hdd.HangDaDatId
              INNER JOIN khoahoc kh ON cthdd.KhoaHocId = kh.KhoaHocId
              LEFT JOIN nhomkhoahoc nkh ON kh.NhomKhoaHocId = nkh.NhomKhoaHocId
              WHERE hdd.STT = ? AND hdd.TrangThai = 1
              GROUP BY kh.KhoaHocId
              ORDER BY hdd.NgayDat DESC
              LIMIT ? OFFSET ?";

    $my_courses = [];
    if ($db instanceof PDO) {
        $stmt = $db->prepare($query);
        // Với PDO, LIMIT và OFFSET cần ép kiểu dữ liệu chuẩn hoặc bindParam để không lỗi SQL mode strict
        $stmt->bindValue(1, $stt_user, PDO::PARAM_INT);
        $stmt->bindValue(2, $stt_user, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->bindValue(4, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $my_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->prepare($query);
        $stmt->bind_param("iiii", $stt_user, $stt_user, $limit, $offset);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $my_courses[] = $row;
        }
    }
} catch (Exception $e) {
    die("Lỗi hệ thống cơ sở dữ liệu: " . $e->getMessage());
}
?>

<style>
    :root {
        --premium-gradient: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        --shadow-smooth: 0 10px 30px rgba(0,0,0,0.04), 0 1px 8px rgba(0,0,0,0.02);
        --shadow-hover: 0 20px 40px rgba(79, 70, 229, 0.12);
        --text-dark: #0f172a;
        --text-muted: #64748b;
    }

    body {
        background-color: #f8fafc;
    }

    .my-courses-container {
        max-width: 1400px;
        margin: 50px auto;
        padding: 0 24px;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }

    /* --- Bố cục Header đỉnh cao kết hợp chiều cao cân đối --- */
    .my-courses-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 40px;
        background: #ffffff;
        padding: 24px 32px;
        border-radius: 20px;
        box-shadow: var(--shadow-smooth);
    }

    .header-left-block {
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .header-title-main {
        font-size: 32px;
        font-weight: 800;
        color: var(--text-dark);
        margin: 0 0 6px 0;
        letter-spacing: -0.5px;
    }

    .header-subtitle-stats {
        font-size: 16px;
        color: #4f46e5;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .header-right-block {
        height: 100%;
        display: flex;
        align-items: center;
    }

    /* Nút khám phá có độ dài dọc ngang bằng cả 2 dòng tiêu đề cộng lại */
    .btn-explore-premium {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--premium-gradient);
        color: #ffffff !important;
        font-weight: 700;
        font-size: 16px;
        padding: 16px 36px;
        border-radius: 14px;
        text-decoration: none !important;
        box-shadow: 0 8px 20px rgba(79, 70, 229, 0.25);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: none;
        gap: 8px;
    }

    .btn-explore-premium:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 28px rgba(79, 70, 229, 0.35);
        filter: brightness(1.1);
    }

    /* --- Grid Layout chuẩn chỉnh 3 cột khổng lồ --- */
    .courses-giant-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 32px;
        margin-bottom: 50px;
    }

    /* --- Ô vuông tròn khóa học cao cấp --- */
    .giant-course-card {
        background: #ffffff;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: var(--shadow-smooth);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        border: 1px solid rgba(0, 0, 0, 0.03);
        position: relative;
    }

    .giant-course-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--shadow-hover);
        border-color: rgba(79, 70, 229, 0.15);
    }

    /* Nửa đầu là Ảnh kích thước khổng lồ phóng khoáng */
    .card-half-thumb {
        position: relative;
        width: 100%;
        padding-top: 56.25%; /* Tỷ lệ vàng khung hình ảnh 16:9 chuẩn cinema */
        overflow: hidden;
        background-color: #e2e8f0;
    }

    .card-half-thumb img {
        position: absolute;
        top: 0; left: 0;
        width: 100%; height: 100%;
        object-fit: cover;
        transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .giant-course-card:hover .card-half-thumb img {
        transform: scale(1.06);
    }

    /* Lớp phủ sang trọng thúc giục vào học */
    .premium-card-overlay {
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(15, 23, 42, 0.6);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: 2;
        backdrop-filter: blur(3px);
    }

    .giant-course-card:hover .premium-card-overlay {
        opacity: 1;
    }

    .btn-action-study {
        background: #ffffff;
        color: var(--text-dark) !important;
        font-weight: 700;
        padding: 12px 24px;
        border-radius: 12px;
        text-decoration: none !important;
        font-size: 15px;
        transform: translateY(15px);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .giant-course-card:hover .btn-action-study {
        transform: translateY(0);
    }

    .btn-action-study:hover {
        background: #4f46e5;
        color: #ffffff !important;
    }

    /* Nửa sau chứa toàn bộ Thông tin khóa học tinh khiết */
    .card-half-info {
        padding: 28px;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }

    .course-title-giant {
        font-size: 20px;
        font-weight: 700;
        color: var(--text-dark);
        line-height: 1.4;
        margin: 0 0 8px 0;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 56px;
    }

    .course-category-mini {
        font-size: 13px;
        color: var(--text-muted);
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
        margin-bottom: 24px;
        display: inline-block;
        background: #f1f5f9;
        padding: 4px 10px;
        border-radius: 6px;
        width: fit-content;
    }

    /* Khối metadata Tiến độ trái - Ngày mua phải */
    .course-meta-bottom-wrapper {
        margin-top: auto; /* Đẩy sát xuống đáy card */
        border-top: 1px dashed #e2e8f0;
        padding-top: 20px;
    }

    .meta-labels-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        font-size: 13px;
        font-weight: 600;
    }

    .label-progress-left {
        color: #4f46e5;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .label-date-right {
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 5px;
    }

    /* Thanh tiến độ bo viền CSS mượt mà */
    .progress-track-bar {
        width: 100%;
        height: 8px;
        background: #e2e8f0;
        border-radius: 999px;
        overflow: hidden;
    }

    .progress-fill-active {
        height: 100%;
        background: var(--premium-gradient);
        border-radius: 999px;
        transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* --- Khối hiển thị khi trống rỗng --- */
    .empty-courses-state {
        text-align: center;
        padding: 80px 40px;
        background: #ffffff;
        border-radius: 24px;
        box-shadow: var(--shadow-smooth);
    }
    .empty-icon {
        font-size: 64px;
        color: #cbd5e1;
        margin-bottom: 20px;
    }

    /* --- Hệ thống phân trang đồng bộ chuẩn --- */
    .pagination-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        margin-top: 40px;
    }

    .page-pill-item {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 44px;
        height: 44px;
        padding: 0 6px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 15px;
        color: var(--text-dark);
        background: #ffffff;
        text-decoration: none !important;
        border: 1px solid rgba(0,0,0,0.05);
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }

    .page-pill-item:hover:not(.disabled-pill) {
        background: #4f46e5;
        color: #ffffff !important;
        transform: translateY(-2px);
    }

    .page-pill-item.active-pill {
        background: var(--premium-gradient);
        color: #ffffff !important;
        border-color: transparent;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
    }

    .page-pill-item.disabled-pill {
        opacity: 0.4;
        cursor: not-allowed;
        background: #f1f5f9;
    }

    .btn-completed-status {
        background: #10b981 !important;
        color: #ffffff !important;
        border: 1px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4) !important;
        transform: translateY(15px);
    }

    .giant-course-card:hover .btn-completed-status {
        transform: translateY(0);
    }

    .btn-completed-status:hover {
        background: #059669 !important; /* Tối màu một chút khi hover trên nền xanh */
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.6) !important;
    }

    .btn-completed-status i {
        margin-right: 8px;
        animation: pulseCheck 1.8s infinite ease-in-out;
    }

    /* Hiệu ứng đập nhịp nhẹ nhàng cho icon tích chọn tạo điểm nhấn */
    @keyframes pulseCheck {
        0% { transform: scale(1); opacity: 0.9; }
        50% { transform: scale(1.2); opacity: 1; text-shadow: 0 0 8px rgba(255,255,255,0.6); }
        100% { transform: scale(1); opacity: 0.9; }
    }

    /* Responsive cho màn hình vừa và nhỏ */
    @media (max-width: 1024px) {
        .courses-giant-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 640px) {
        .my-courses-header { flex-direction: column; align-items: flex-start; gap: 20px; }
        .btn-explore-premium { width: 100%; }
        .courses-giant-grid { grid-template-columns: 1fr; }
    }
</style>

<main class="my-courses-container">
    <header class="my-courses-header">
        <div class="header-left-block">
            <h1 class="header-title-main">Khóa học của tôi</h1>
            <p class="header-subtitle-stats">
                <i class="fa-solid fa-graduation-cap"></i>
                <span><?php echo $totalCourses; ?> khóa học đã mua</span>
            </p>
        </div>
        <div class="header-right-block">
            <a href="/DevMaster/Pages/TatCaKhoaHoc.php" class="btn-explore-premium">
                <i class="fa-solid fa-compass fa-spin-hover"></i> Khám phá thêm
            </a>
        </div>
    </header>

    <?php if (empty($my_courses)): ?>
        <div class="empty-courses-state">
            <div class="empty-icon"><i class="fa-solid fa-folder-open"></i></div>
            <h3 style="font-weight: 700; color: var(--text-dark);">Kho lưu trữ trống rỗng!</h3>
            <p style="color: var(--text-muted); margin-bottom: 24px;">Bạn chưa sở hữu bất kỳ khóa học cao cấp nào của chúng tôi.</p>
            <a href="/DevMaster/Pages/TatCaKhoaHoc.php" class="btn-explore-premium" style="padding: 12px 28px; font-size: 14px;">Mua khóa học ngay đầu tiên</a>
        </div>
    <?php else: ?>
        <div class="courses-giant-grid">
            <?php foreach ($my_courses as $course): 
                // Xử lý tính toán % tiến độ chính xác từ dữ liệu thật
                $tongSoBai = intval($course['TongSoBai']);
                $baiDaXong = intval($course['BaiDaXong']);
                $percent = 0;
                if ($tongSoBai > 0) {
                    $percent = round(($baiDaXong / $tongSoBai) * 100);
                    if ($percent > 100) $percent = 100;
                }
                
                // Định dạng định dạng ngày mua d/m/y 
                $ngayMua = !empty($course['NgayDat']) ? date('d/m/Y', strtotime($course['NgayDat'])) : '---';
                
                // Fallback nếu ảnh rỗng
                $imgSrc = !empty($course['Anh']) ? htmlspecialchars($course['Anh']) : '/DevMaster/Assets/Images-Videos/default-course.jpg';
            ?>
                <article class="giant-course-card">
                    <div class="card-half-thumb">
                        <img src="/DevMaster/<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($course['Ten']); ?>" loading="lazy">
                        
                        <div class="premium-card-overlay <?php echo ($percent >= 100) ? 'is-completed-overlay' : ''; ?>">
                            <?php if ($percent >= 100): ?>
                                <a href="/DevMaster/Pages/VaoHocNgay.php?id=<?php echo $course['KhoaHocId']; ?>" class="btn-action-study btn-completed-status">
                                    <i class="fa-solid fa-circle-check"></i> Hoàn thành
                                </a>
                            <?php else: ?>
                                <a href="/DevMaster/Pages/VaoHocNgay.php?id=<?php echo $course['KhoaHocId']; ?>" class="btn-action-study">
                                    Vào học ngay <i class="fa-solid fa-circle-play"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-half-info">
                        <h2 class="course-title-giant" title="<?php echo htmlspecialchars($course['Ten']); ?>">
                            <?php echo htmlspecialchars($course['Ten']); ?>
                        </h2>
                        <span class="course-category-mini">
                            <i class="fa-regular fa-bookmark"></i> <?php echo htmlspecialchars($course['TenNhom'] ?? 'Chưa phân nhóm'); ?>
                        </span>
                        
                        <div class="course-meta-bottom-wrapper">
                            <div class="meta-labels-row">
                                <span class="label-progress-left">
                                    <i class="fa-solid fa-chart-line"></i> Tiến độ: <?php echo $percent; ?>%
                                </span>
                                <span class="label-date-right">
                                    <i class="fa-regular fa-calendar-days"></i> Mua ngày: <?php echo $ngayMua; ?>
                                </span>
                            </div>
                            <div class="progress-track-bar">
                                <div class="progress-fill-active" style="width: <?php echo $percent; ?>%;"></div>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination-wrapper" aria-label="Page navigation">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo ($page - 1); ?>" class="page-pill-item" aria-label="Previous">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="page-pill-item disabled-pill"><i class="fa-solid fa-chevron-left"></i></span>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="page-pill-item active-pill"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>" class="page-pill-item"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo ($page + 1); ?>" class="page-pill-item" aria-label="Next">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="page-pill-item disabled-pill"><i class="fa-solid fa-chevron-right"></i></span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
        
    <?php endif; ?>
</main>
<script src="/DevMaster/assets/Javascript.js"></script> 
<?php 
include '../includes/Footer.php'; 
?>