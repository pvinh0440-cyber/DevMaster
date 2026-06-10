<?php 
include 'includes/Header.php'; 
include 'Database.php';

// Đồng bộ biến kết nối giữa các file hệ thống (ưu tiên sử dụng kết nối chuẩn PDO từ file Database)
$db = isset($connect) ? $connect : $conn;

try {
    // 1. Lấy tối đa 10 khóa học Featured để đảm bảo tốc độ tải trang và độ mượt của Hero Slider cũ (nếu dùng)
    $query = "SELECT kh.KhoaHocId, kh.Ten, kh.Anh, kh.Gia, kh.TenGiangVien, nkh.TenNhom 
              FROM khoahoc kh
              INNER JOIN nhomkhoahoc nkh ON kh.NhomKhoaHocId = nkh.NhomKhoaHocId
              WHERE kh.IsFeatured = 1 
              ORDER BY kh.KhoaHocId DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $list_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. LẤY TOÀN BỘ DANH MỤC VÀ KHÓA HỌC THUỘC DANH MỤC ĐÓ (Tối đa 12 khóa mỗi danh mục)
    $catQuery = "SELECT * FROM danhmuc ORDER BY DanhMucId ASC";
    $catStmt = $db->prepare($catQuery);
    $catStmt->execute();
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    $catalog_data = [];
    foreach ($categories as $cat) {
        $courseQuery = "SELECT kh.KhoaHocId, kh.Ten, kh.Anh, kh.Gia, kh.TenGiangVien, nkh.TenNhom 
                        FROM khoahoc kh
                        INNER JOIN nhomkhoahoc nkh ON kh.NhomKhoaHocId = nkh.NhomKhoaHocId
                        WHERE nkh.DanhMucId = :catId
                        ORDER BY kh.KhoaHocId DESC 
                        LIMIT 12";
        $courseStmt = $db->prepare($courseQuery);
        $courseStmt->execute([':catId' => $cat['DanhMucId']]);
        $courses = $courseStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Chỉ push vào danh sách hiển thị nếu danh mục đó thực sự có chứa khóa học
        if (!empty($courses)) {
            $catalog_data[] = [
                'id' => $cat['DanhMucId'],
                'name' => $cat['TenDanhMuc'],
                'courses' => $courses
            ];
        }
    }

} catch (PDOException $e) {
    echo "Không thể tải danh sách khóa học: " . $e->getMessage();
    $list_courses = [];
    $catalog_data = [];
}

// Khởi tạo các cấu hình chung trước khi vào giao diện hiển thị
$isAdminAccount = isset($_SESSION['IsAdmin']) && $_SESSION['IsAdmin'] === true;
$currentUserId = isset($_SESSION['UserId']) ? intval($_SESSION['UserId']) : 0;

// Mảng chứa các class Gradient ngẫu nhiên tạo điểm nhấn hiện đại cho tiêu đề Danh mục
$gradient_classes = ['gradient-text-blue', 'gradient-text-purple', 'gradient-text-orange', 'gradient-text-green'];
?>

<link rel="stylesheet" href="/DevMaster/assets/style.css">

<section class="hero-master">
    <div class="hero-bg-shapes">
        <div class="shape s1"></div>
        <div class="shape s2"></div>
    </div>
    
    <div class="container-fluid">
        <div class="hero-wrapper">
            <div class="hero-text-content">
                <div class="hero-badge">
                    <span class="pulsating-dot"></span>
                    Hơn 15.000 học viên đã bắt đầu hôm nay
                </div>
                <h1 class="hero-title">
                    Chinh phục <span class="gradient-text">Thế giới Code</span><br>
                    Trở thành Master sau 6 tháng.
                </h1>
                <p class="hero-subtitle">
                    Học lập trình không chỉ là học ngôn ngữ, đó là học cách giải quyết vấn đề. 
                    Lộ trình thực chiến từ con số 0 đến khi có việc làm tại các tập đoàn toàn cầu.
                </p>
                
                <div class="hero-cta-group">
                    <a href="#pathway" class="btn-hero-primary">
                        Bắt đầu lộ trình ngay
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <div class="hero-visual">
                <div class="image-stack">
                    <img src="https://img.freepik.com/free-photo/programming-background-with-person-working-with-codes-computer_23-2150010125.jpg" alt="Master Coding" class="main-img">
                    <div class="floating-card c1">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <strong>98%</strong>
                            <span>Hoàn thành khóa học</span>
                        </div>
                    </div>
                    <div class="floating-card c2">
                        <i class="fas fa-briefcase"></i>
                        <div>
                            <strong>200+</strong>
                            <span>Đối tác tuyển dụng</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="pathway" class="pathway-advanced">
    <div class="container">
        <div class="section-heading modern-stacked-heading">
            <div class="top-mini-badge" data-aos="fade-up">
                <span class="live-status-dot"></span>
                Khám phá hàng ngàn khóa học chất lượng cao
            </div>
            <h2 class="main-title" data-aos="fade-up" data-aos-delay="100">
                <span class="title-line-white">Đầu tư kiến thức</span>
                <span class="title-line-gradient">định hình tương lai</span>
            </h2>
            <p class="desc" data-aos="fade-up" data-aos-delay="200">
                Tuyển chọn khóa học chất lượng cao từ các chuyên gia hàng đầu. 
                Kiến thức chuẩn quốc tế, liên tục cập nhật.
            </p>
        </div>

        <div class="pathway-main-actions" data-aos="fade-up" data-aos-delay="100" style="display: flex; justify-content: center; gap: 15px;">
            <?php if (!$isAdminAccount): ?>
                <a href="/DevMaster/Pages/KhoaHocCuaToi.php" class="action-btn purple">
                    <i class="fas fa-graduation-cap"></i> 
                    Khóa học của bạn
                </a>
            <?php endif; ?>
            <a href="Pages/TatCaKhoaHoc.php" class="action-btn green">
                <i class="fas fa-th-large"></i> 
                Tất cả khóa học
            </a>
            <a href="javascript:void(0);" class="action-btn orange" id="aiChatTrigger">
                <i class="fas fa-wand-magic-sparkles"></i> 
                AI gợi ý khóa học
            </a>
        </div>

        <div class="features-row">
            <div class="feature-item" data-aos="fade-right">
                <div class="f-icon-wrap"><i class="fas fa-closed-captioning"></i></div>
                <div class="f-content">
                    <h4>Phụ đề Tiếng Việt chất lượng</h4>
                    <p>Được biên dịch bởi đội ngũ kỹ thuật chuyên nghiệp, đảm bảo thuật ngữ chính xác 100%.</p>
                </div>
            </div>
            
            <div class="feature-item" data-aos="fade-up">
                <div class="f-icon-wrap" style="color: #fbbf24;"><i class="fas fa-user-check"></i></div>
                <div class="f-content">
                    <h4>Khóa học từ Chuyên gia</h4>
                    <p>Mọi nội dung đều được kiểm duyệt bởi các Senior Developer có ít nhất 10 năm kinh nghiệm.</p>
                </div>
            </div>
            
            <div class="feature-item" data-aos="fade-left">
                <div class="f-icon-wrap" style="color: #10b981;"><i class="fas fa-video"></i></div>
                <div class="f-content">
                    <h4>Video 4K Song ngữ</h4>
                    <p>Trải nghiệm học tập đỉnh cao với chất lượng hình ảnh sắc nét, hỗ trợ song ngữ Anh - Việt.</p>
                </div>
            </div>
        </div>

        <div class="tool-banner-card mt-4" data-aos="zoom-in">
            <div class="tool-icon"><i class="fas fa-cloud-download-alt"></i></div>
            <div class="tool-info">
                <h4>Công cụ tải khóa học Udemy độc quyền</h4>
                <p>Hỗ trợ học viên tải tài liệu offline, đầy đủ phụ đề và mã nguồn thực hành nhanh chóng.</p>
            </div>
        </div>

        <div class="featured-ribbon-wrapper">
            <div class="featured-ribbon-header">
                <h2 class="ribbon-main-title">Khóa Học Nổi Bật</h2>
                <p class="ribbon-desc">Những lộ trình tinh hoa được săn đón nhất, thúc đẩy sự nghiệp công nghệ của bạn vượt giới hạn.</p>
            </div>

            <div class="featured-ribbon-container">
                <div class="featured-ribbon-track" id="featuredRibbonTrack">
                    <?php 
                    if (!empty($list_courses)): 
                        // Nhân bản danh sách (x2) để tạo vòng lặp cuộn vô tận mượt mà không vết cắt
                        $double_list = array_merge($list_courses, $list_courses); 
                        foreach ($double_list as $course):
                            $hasOwned = false;        // Đã kích hoạt học
                            $isPendingOrder = false;  // Đang chờ thanh toán (TrangThai = 0)
                            $pendingOrderId = 0;

                            if ($currentUserId > 0 && !$isAdminAccount) {
                                try {
                                    // 1. Kiểm tra đơn hàng xem đã mua thành công hay chưa
                                    $checkCourseStatusQuery = "SELECT hdd.HangDaDatId, hdd.TrangThai 
                                                            FROM chitiethangdadat cthdd
                                                            JOIN hangdadat hdd ON cthdd.HangDaDatId = hdd.HangDaDatId
                                                            WHERE hdd.STT = :userId AND cthdd.KhoaHocId = :courseId 
                                                            ORDER BY hdd.NgayDat DESC LIMIT 1";
                                    $stmtCheck = $db->prepare($checkCourseStatusQuery);
                                    $stmtCheck->execute([':userId' => $currentUserId, ':courseId' => $course['KhoaHocId']]);
                                    $orderStatus = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                                    $progressPercent = 0; // Khởi tạo tiến độ mặc định

                                    if ($orderStatus) {
                                        if ($orderStatus['TrangThai'] == '1') {
                                            $hasOwned = true; // Đã mua thành công

                                            // 2. TÍNH TOÁN TIẾN ĐỘ % (Đồng bộ logic từ KhoaHocCuaToi / VaoKhoaHoc)
                                            // ĐỒNG BỘ CHUẨN: Tính toán tiến độ từ bảng tiendohocvien theo cột STT
                                            $progressQuery = "SELECT 
                                                (SELECT COUNT(*) FROM tiendohocvien td 
                                                INNER JOIN baihoc bh ON td.BaiHocId = bh.BaiHocId 
                                                WHERE bh.KhoaHocId = :courseId AND td.STT = :userId AND td.TrangThai = 1) as CompletedLessons,
                                                (SELECT COUNT(*) FROM baihoc WHERE KhoaHocId = :courseId) as TotalLessons";
                                            
                                            $stmtProgress = $db->prepare($progressQuery);
                                            $stmtProgress->execute([':userId' => $currentUserId, ':courseId' => $course['KhoaHocId']]);
                                            $progressData = $stmtProgress->fetch(PDO::FETCH_ASSOC);

                                            if ($progressData && $progressData['TotalLessons'] > 0) {
                                                $progressPercent = round(($progressData['CompletedLessons'] / $progressData['TotalLessons']) * 100);
                                            }
                                        } else if ($orderStatus['TrangThai'] == '0') {
                                            $isPendingOrder = true; // Chờ thanh toán
                                            $pendingOrderId = $orderStatus['HangDaDatId'];
                                        }
                                    }
                                } catch (PDOException $ex) {
                                    $progressPercent = 0;
                                }
                            }

                            // Đồng bộ cách build đường dẫn ảnh chính xác tuyệt đối từ đoạn Catalog phía dưới của bạn
                            $image_path = !empty($course['Anh']) ? '/DevMaster/' . ltrim($course['Anh'], '/') : '/DevMaster/assets/Images-Videos/default.png';
                    ?>
                        <div class="mini-card-premium <?php echo $hasOwned ? 'course-owned' : ''; ?>">
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
                                    <div class="rating-stars">
                                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
                                    </div>
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
                                        <i class="fa-solid fa-pen-to-square"></i> Chỉnh sửa
                                    </button>
                                <?php elseif ($hasOwned): ?>
                                    <button type="button" class="btn-add-to-cart btn-goto-study-now" onclick="window.location.href='/DevMaster/Pages/VaoHocNgay.php?id=<?php echo $course['KhoaHocId']; ?>';">
                                        <i class="fa-solid fa-circle-play"></i> Vào học ngay
                                    </button>
                                <?php elseif ($isPendingOrder): ?>
                                    <button type="button" class="btn-add-to-cart btn-pay-pending" style="background: linear-gradient(135deg, #f59e0b, #ea580c); color: white;" onclick="window.location.href='/DevMaster/Pages/DonHang.php?auto_open=<?php echo $pendingOrderId; ?>';">
                                        <i class="fa-solid fa-credit-card"></i> Thanh toán ngay
                                    </button>
                                <?php else: ?>
                                    <?php $is_in_cart = isset($_SESSION['cart']) && in_array($course['KhoaHocId'], $_SESSION['cart']); ?>
                                    <?php if ($is_in_cart): ?>
                                        <button type="button" class="btn-add-to-cart btn-view-cart-active" data-id="<?php echo $course['KhoaHocId']; ?>" onclick="window.location.href='/DevMaster/Pages/GioHang.php';">
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
                    <?php 
                        endforeach;
                    endif; 
                    ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="catalog-section">
    <?php if (!empty($catalog_data)): ?>
        <?php foreach ($catalog_data as $index => $catalog): ?>
            <?php 
                // Random chọn Gradient class từ mảng chuẩn bị trước
                $chosen_gradient = $gradient_classes[$index % count($gradient_classes)]; 
                $course_count = count($catalog['courses']);
            ?>
            <div class="catalog-container" data-aos="fade-up">
                
                <div class="catalog-header">
                    <div class="catalog-title-area">
                        <h2 class="<?php echo $chosen_gradient; ?>"><?php echo htmlspecialchars($catalog['name']); ?></h2>
                        <p>Khám phá các khóa học chất lượng trong danh mục này</p>
                    </div>
                    <a href="Pages/TatCaKhoaHoc.php?danhmuc_id=<?php echo $catalog['id']; ?>" class="btn-view-more-cat">
                        Xem thêm <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <div class="slider-outer-wrapper">
                    
                    <?php if ($course_count > 4): ?>
                        <button class="slider-nav-btn prev-btn" onclick="slideCarousel('track-<?php echo $catalog['id']; ?>', 'prev')">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                    <?php endif; ?>

                    <div class="slider-inner-track" id="track-<?php echo $catalog['id']; ?>">
                        <?php foreach ($catalog['courses'] as $course): ?>
                            <?php
                            $hasOwned = false;        // Đã kích hoạt học
                            $isPendingOrder = false;  // Đang chờ thanh toán (TrangThai = 0)
                            $pendingOrderId = 0;

                            if ($currentUserId > 0 && !$isAdminAccount) {
                                try {
                                    // 1. Kiểm tra đơn hàng xem đã mua thành công hay chưa
                                    $checkCourseStatusQuery = "SELECT hdd.HangDaDatId, hdd.TrangThai 
                                                            FROM chitiethangdadat cthdd
                                                            JOIN hangdadat hdd ON cthdd.HangDaDatId = hdd.HangDaDatId
                                                            WHERE hdd.STT = :userId AND cthdd.KhoaHocId = :courseId 
                                                            ORDER BY hdd.NgayDat DESC LIMIT 1";
                                    $stmtCheck = $db->prepare($checkCourseStatusQuery);
                                    $stmtCheck->execute([':userId' => $currentUserId, ':courseId' => $course['KhoaHocId']]);
                                    $orderStatus = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                                    $progressPercent = 0; // Khởi tạo tiến độ mặc định

                                    if ($orderStatus) {
                                        if ($orderStatus['TrangThai'] == '1') {
                                            $hasOwned = true; // Đã mua thành công

                                            // 2. TÍNH TOÁN TIẾN ĐỘ % (Đồng bộ logic từ KhoaHocCuaToi / VaoKhoaHoc)
                                            // ĐỒNG BỘ CHUẨN: Tính toán tiến độ từ bảng tiendohocvien theo cột STT
                                            $progressQuery = "SELECT 
                                                (SELECT COUNT(*) FROM tiendohocvien td 
                                                INNER JOIN baihoc bh ON td.BaiHocId = bh.BaiHocId 
                                                WHERE bh.KhoaHocId = :courseId AND td.STT = :userId AND td.TrangThai = 1) as CompletedLessons,
                                                (SELECT COUNT(*) FROM baihoc WHERE KhoaHocId = :courseId) as TotalLessons";
                                            
                                            $stmtProgress = $db->prepare($progressQuery);
                                            $stmtProgress->execute([':userId' => $currentUserId, ':courseId' => $course['KhoaHocId']]);
                                            $progressData = $stmtProgress->fetch(PDO::FETCH_ASSOC);

                                            if ($progressData && $progressData['TotalLessons'] > 0) {
                                                $progressPercent = round(($progressData['CompletedLessons'] / $progressData['TotalLessons']) * 100);
                                            }
                                        } else if ($orderStatus['TrangThai'] == '0') {
                                            $isPendingOrder = true; // Chờ thanh toán
                                            $pendingOrderId = $orderStatus['HangDaDatId'];
                                        }
                                    }
                                } catch (PDOException $ex) {
                                    $progressPercent = 0;
                                }
                            }

                            // Build đường dẫn ảnh chính xác tuyệt đối
                            $image_path = !empty($course['Anh']) ? '/DevMaster/' . ltrim($course['Anh'], '/') : '/DevMaster/assets/Images-Videos/default-course.png';
                            ?>
                            
                            <div class="mini-card-premium <?php echo $hasOwned ? 'course-owned' : ''; ?>">
                
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
                                        <div class="rating-stars">
                                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
                                        </div>
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
                                        <button type="button" class="btn-add-to-cart btn-pay-pending" style="background: linear-gradient(135deg, #f59e0b, #ea580c); color: white;" onclick="window.location.href='/DevMaster/Pages/DonHang.php?auto_open=<?php echo $pendingOrderId; ?>';">
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
                        <?php endforeach; ?>
                    </div>

                    <?php if ($course_count > 4): ?>
                        <button class="slider-nav-btn next-btn" onclick="slideCarousel('track-<?php echo $catalog['id']; ?>', 'next')">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    <?php endif; ?>

                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="text-center w-100 py-5">
            <p style="color: #94a3b8; font-size: 16px;">Hiện tại hệ thống đang cập nhật dữ liệu các danh mục khóa học.</p>
        </div>
    <?php endif; ?>
</section>

<section class="why-choose-us-section">
    <div class="container">
        <div class="wcu-title-block" data-aos="fade-up">
            <h2>Tại sao chọn chúng tôi?</h2>
            <p>Chúng tôi cam kết mang đến trải nghiệm học tập tốt nhất cho bạn</p>
        </div>
        
        <div class="wcu-grid">
            <div class="wcu-card" data-aos="fade-up" data-aos-delay="100">
                <div class="wcu-icon-box" style="background: #eff6ff; color: #2563eb;">
                    <i class="fas fa-award"></i>
                </div>
                <h4>Chất lượng cao</h4>
                <p>Nội dung được biên soạn bởi các chuyên gia hàng đầu trong ngành</p>
            </div>

            <div class="wcu-card" data-aos="fade-up" data-aos-delay="200">
                <div class="wcu-icon-box" style="background: #f0fdf4; color: #16a34a;">
                    <i class="far fa-clock"></i>
                </div>
                <h4>Học mọi lúc</h4>
                <p>Truy cập không giới hạn 24/7, học theo tốc độ của riêng bạn</p>
            </div>

            <div class="wcu-card" data-aos="fade-up" data-aos-delay="300">
                <div class="wcu-icon-box" style="background: #faf5ff; color: #9333ea;">
                    <i class="fas fa-users"></i>
                </div>
                <h4>Cộng đồng lớn</h4>
                <p>Kết nối với hàng ngàn học viên khác để cùng học hỏi</p>
            </div>

            <div class="wcu-card" data-aos="fade-up" data-aos-delay="400">
                <div class="wcu-icon-box" style="background: #fff7ed; color: #ea580c;">
                    <i class="fas fa-headphones-alt"></i>
                </div>
                <h4>Hỗ trợ tận tình</h4>
                <p>Đội ngũ hỗ trợ sẵn sàng giải đáp mọi thắc mắc của bạn</p>
            </div>
        </div>
    </div>
</section>

<script>
// Hàm click mũi tên danh mục phía dưới
function slideCarousel(trackId, direction) {
    const track = document.getElementById(trackId);
    if (!track) return;
    
    const card = track.querySelector('.mini-card-premium');
    if (!card) return;
    
    const cardWidth = card.offsetWidth + 20; // 20 là gap trong CSS
    const scrollAmount = direction === 'next' ? cardWidth * 4 : -cardWidth * 4;
    
    track.scrollBy({
        left: scrollAmount,
        behavior: 'smooth'
    });
}

// Giữ nguyên logic Drag-to-Scroll ban đầu của các danh mục Catalog bên dưới
document.addEventListener('DOMContentLoaded', () => {
    const tracks = document.querySelectorAll('.slider-inner-track');
    
    tracks.forEach(track => {
        let isDown = false;
        let startX;
        let scrollLeft;

        track.addEventListener('mousedown', (e) => {
            isDown = true;
            track.style.cursor = 'grabbing';
            startX = e.pageX - track.offsetLeft;
            scrollLeft = track.scrollLeft;
        });
        
        track.addEventListener('mouseleave', () => {
            isDown = false;
            track.style.cursor = 'grab';
        });
        
        track.addEventListener('mouseup', () => {
            isDown = false;
            track.style.cursor = 'grab';
        });
        
        track.addEventListener('mousemove', (e) => {
            if(!isDown) return;
            e.preventDefault();
            const x = e.pageX - track.offsetLeft;
            const walk = (x - startX) * 1.5;
            track.scrollLeft = scrollLeft - walk;
        });
    });


    const urlParams = new URLSearchParams(window.location.search);
    const danhmucId = urlParams.get('danhmuc_id');

    if (danhmucId) {
        // Find khối danh mục sidebar tương ứng với ID nhận được
        const targetBlock = document.querySelector(`.sidebar-danhmuc-block[data-danhmuc-id="${danhmucId}"]`);
        if (targetBlock) {
            // Tự động gỡ bỏ class collapsed để dropdown xổ thẳng các nhóm khóa học xuống dưới
            targetBlock.classList.remove('collapsed');
            targetBlock.classList.add('active-highlight');
            
            // Highlight làm nổi bật tiêu đề danh mục được chọn từ trang chủ
            const titleElement = targetBlock.querySelector('.danhmuc-title');
            if (titleElement) {
                titleElement.style.color = '#4f46e5';
                titleElement.style.fontWeight = '800';
                titleElement.style.background = '#f0f4ff';
                titleElement.style.borderRadius = '8px';
            }
            
            // Cuộn mượt mà thanh sidebar đến vị trí danh mục này nếu danh sách quá dài
            targetBlock.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }
});
</script>

<script src="/DevMaster/Assets/Javascript.js"></script>
<?php include 'includes/Footer.php'; ?>