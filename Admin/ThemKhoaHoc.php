<?php
// Admin/ThemKhoaHoc.php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
include '../Database.php';

$db = isset($connect) ? $connect : $conn;

// Khóa bảo mật: Phải là Admin tối cao mới được quyền can thiệp hệ thống
if (!isset($_SESSION['IsAdmin']) || $_SESSION['IsAdmin'] !== true) {
    header("Location: /DevMaster/Pages/Login.php");
    exit;
}

$systemMessage = "";

// 1. TẢI DỮ LIỆU DANH MỤC VÀ NHÓM KHÓA HỌC SẴN CÓ ĐỂ ĐỔ VÀO DROPDOWN LIST
$danhmucList = [];
$nhomList = [];
try {
    // Lấy danh mục
    $dmQuery = "SELECT DanhMucId, TenDanhMuc FROM danhmuc ORDER BY DanhMucId ASC";
    if ($db instanceof PDO) {
        $danhmucList = $db->query($dmQuery)->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $res = $db->query($dmQuery);
        while($r = $res->fetch_assoc()) { $danhmucList[] = $r; }
    }

    // Lấy nhóm khóa học kèm DanhMucId phục vụ việc lọc động bằng JS
    $nhomQuery = "SELECT NhomKhoaHocId, DanhMucId, TenNhom FROM nhomkhoahoc ORDER BY NhomKhoaHocId ASC";
    if ($db instanceof PDO) {
        $nhomList = $db->query($nhomQuery)->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $res = $db->query($nhomQuery);
        while($r = $res->fetch_assoc()) { $nhomList[] = $r; }
    }
} catch (Exception $e) {
    $systemMessage = "<div class='alert-box error'><i class='fa-solid fa-triangle-exclamation'></i> Lỗi tải dữ liệu cấu trúc: " . $e->getMessage() . "</div>";
}

// 2. XỬ LÝ SUBMIT FORM - Thiết lập luồng lưu trữ phân tách thông minh
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenKhoaHoc = trim($_POST['ten_khoahoc'] ?? '');
    $tenGiangVien = trim($_POST['ten_giangvien'] ?? '');
    $gia = floatval($_POST['gia'] ?? 0);
    $isFeatured = intval($_POST['is_featured'] ?? 0); // 1 hoặc 0
    
    $danhmucSelect = $_POST['danhmuc_select'] ?? 'NEW';
    $nhomSelect = $_POST['nhom_select'] ?? 'NEW';
    
    $newDanhmucName = trim($_POST['new_danhmuc_name'] ?? '');
    $newNhomName = trim($_POST['new_nhom_name'] ?? '');

    // Khởi tạo các cờ ID liên kết dữ liệu
    $finalDanhMucId = 0;
    $finalNhomKhoaHocId = 0;

    // Biến kiểm soát trạng thái lưu thành công nội dung gì để phản hồi UI khách quan nhất
    $savedStructureOnly = false;

    try {
        // Kiểm tra điều kiện tối thiểu: Phải có Tên khóa học HOẶC có hành động tạo mới cấu trúc
        $hasNewDanhMuc = ($danhmucSelect === 'NEW' && !empty($newDanhmucName));
        $hasNewNhom = ($nhomSelect === 'NEW' && !empty($newNhomName));

        if (empty($tenKhoaHoc) && !$hasNewDanhMuc && !$hasNewNhom) {
            throw new Exception("Vui lòng nhập Tên khóa học hoặc nhập dữ liệu để tạo mới Danh mục / Nhóm khóa học!");
        }

        // --- XỬ LÝ DANH MỤC (Cũ hoặc Tạo mới hoàn toàn) ---
        if ($danhmucSelect === 'NEW') {
            if (!empty($newDanhmucName)) {
                // Thêm mới vào bảng danhmuc
                $insDM = "INSERT INTO danhmuc (TenDanhMuc) VALUES (?)";
                if ($db instanceof PDO) {
                    $stmt = $db->prepare($insDM);
                    $stmt->execute([$newDanhmucName]);
                    $finalDanhMucId = $db->lastInsertId();
                } else {
                    $stmt = $db->prepare($insDM);
                    $stmt->bind_param("s", $newDanhmucName);
                    $stmt->execute();
                    $finalDanhMucId = $db->insert_id;
                }
            } else if (!empty($tenKhoaHoc)) {
                // Nếu định tạo khóa học mà bỏ trống tên danh mục mới
                throw new Exception("Bạn đã chọn tạo Danh mục mới, vui lòng không để trống tên Danh mục!");
            }
        } else {
            $finalDanhMucId = intval($danhmucSelect);
        }

        // --- XỬ LÝ NHÓM KHÓA HỌC (Cũ hoặc Tạo mới gắn kết Danh mục) ---
        if ($nhomSelect === 'NEW') {
            if (!empty($newNhomName)) {
                // Thêm mới vào bảng nhomkhoahoc tương ứng với DanhMucId cấp trên (Lưu ý: Nếu Danh mục cũng tạo mới thì lấy finalDanhMucId vừa sinh ra)
                $insNhom = "INSERT INTO nhomkhoahoc (DanhMucId, TenNhom) VALUES (?, ?)";
                if ($db instanceof PDO) {
                    $stmt = $db->prepare($insNhom);
                    $stmt->execute([$finalDanhMucId, $newNhomName]);
                    $finalNhomKhoaHocId = $db->lastInsertId();
                } else {
                    $stmt = $db->prepare($insNhom);
                    $stmt->bind_param("is", $finalDanhMucId, $newNhomName);
                    $stmt->execute();
                    $finalNhomKhoaHocId = $db->insert_id;
                }
            } else if (!empty($tenKhoaHoc)) {
                // Nếu định tạo khóa học mà bỏ trống tên nhóm mới
                throw new Exception("Bạn đã chọn tạo Nhóm khóa học mới, vui lòng không để trống tên Nhóm!");
            }
        } else {
            $finalNhomKhoaHocId = intval($nhomSelect);
        }

        // --- XỬ LÝ NHÁNH LƯU TRỮ KHÓA HỌC (Chỉ thực hiện khi TÊN KHÓA HỌC KHÔNG TRỐNG) ---
        if (!empty($tenKhoaHoc)) {
            // Rà soát tính hợp lệ của các dữ liệu đi kèm khóa học
            if (empty($tenGiangVien)) {
                throw new Exception("Vui lòng điền đầy đủ Tên giảng viên cho khóa học!");
            }
            if ($finalNhomKhoaHocId <= 0) {
                throw new Exception("Vui lòng chọn hoặc thiết lập Nhóm khóa học hợp lệ để gắn kết khóa học!");
            }

            // --- 🌟 NÂNG CẤP V2 CHUYÊN NGHIỆP: XỬ LÝ ẢNH BÌA KHÓA HỌC BIẾN TẠM ĐỒNG BỘ CHÂN THỰC 🌟 ---
            $dbImagePath = "Images-Videos/default-course.png"; 
            $fallbackImage = $_POST['devmaster_hidden_image_fallback'] ?? '';

            if (isset($_FILES['anh_khoahoc']) && $_FILES['anh_khoahoc']['error'] === UPLOAD_ERR_OK) {
                // Trường hợp 1: Có file upload mới từ trình duyệt
                $fileTmpPath = $_FILES['anh_khoahoc']['tmp_name'];
                $fileName = time() . '_' . $_FILES['anh_khoahoc']['name'];
                
                $uploadFileDir = '../Images-Videos/';
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0775, true);
                }
                
                $dest_path = $uploadFileDir . $fileName;
                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $dbImagePath = "Images-Videos/" . $fileName;
                }
            } else if (!empty($fallbackImage)) {
                // Trường hợp 2: Khôi phục lại đường dẫn ảnh cũ được lưu tạm từ Cache khi Admin quay về từ ThemBaiHoc
                $dbImagePath = $fallbackImage;
            } else {
                // Trường hợp 3: Hoàn toàn không có file và không có biến tạm
                throw new Exception("Vui lòng tải lên Ảnh bìa khóa học hợp lệ!");
            }

            // --- TIẾN HÀNH THÊM MỚI BẢN GHI KHÓA HỌC HOÀN CHỈNH VÀO PHPMYADMIN ---
            $insertCourseQuery = "INSERT INTO khoahoc (NhomKhoaHocId, Ten, Anh, Gia, TenGiangVien, IsFeatured, TrangThai)
                                VALUES (?, ?, ?, ?, ?, ?, b'1')";

            $newCourseId = 0;
            if ($db instanceof PDO) {
                $stmt = $db->prepare($insertCourseQuery);
                $stmt->execute([$finalNhomKhoaHocId, $tenKhoaHoc, $dbImagePath, $gia, $tenGiangVien, $isFeatured]);
                $newCourseId = $db->lastInsertId();
            } else {
                $stmt = $db->prepare($insertCourseQuery);
                $stmt->bind_param("issdsi", $finalNhomKhoaHocId, $tenKhoaHoc, $dbImagePath, $gia, $tenGiangVien, $isFeatured);
                $stmt->execute();
                $newCourseId = $db->insert_id;
            }

            // Kiểm tra xem có dữ liệu bài học đính kèm được gửi từ localStorage lên không
            $incomingLessonsJson = $_POST['embedded_lessons_json'] ?? '[]';
            $incomingLessons = json_decode($incomingLessonsJson, true);

            if (!empty($incomingLessons) && $newCourseId > 0) {
                $insertLessonQuery = "INSERT INTO baihoc (KhoaHocId, Ten, LinkVideo) VALUES (?, ?, ?)";
                $stmtLesson = $db->prepare($insertLessonQuery);
                foreach ($incomingLessons as $lesson) {
                    $tenBaiHoc = trim($lesson['ten'] ?? '');
                    $linkVideo = trim($lesson['video'] ?? 'default-video.mp4');
                    if (empty($tenBaiHoc)) continue;
                    
                    if ($db instanceof PDO) {
                        $stmtLesson->execute([$newCourseId, $tenBaiHoc, $linkVideo]);
                    } else {
                        $stmtLesson->bind_param("iss", $newCourseId, $tenBaiHoc, $linkVideo);
                        $stmtLesson->execute();
                    }
                }
            } else {
                $systemMessage = "<div class='alert-box success'><i class='fa-solid fa-circle-check'></i> Thêm mới Khóa học và Cấu trúc thành công!</div>";
            }
        } else {
            // Tên khóa học trống -> Chỉ có cấu trúc (Danh mục / Nhóm) được lưu thành công, bỏ qua việc chèn bảng khoahoc
            $savedStructureOnly = true;
            $systemMessage = "<div class='alert-box success'><i class='fa-solid fa-circle-check'></i> Đồng bộ cấu trúc (Danh mục/Nhóm) thành công! Thông tin khóa học đã được bỏ qua một cách an toàn.</div>";
        }
        
        // Làm mới mảng dữ liệu để dropdown cập nhật ngay lập tức các danh mục/nhóm vừa thêm mới
        header("Refresh: 2; url=QuanLyKhoaHoc.php");
    } catch (Exception $e) {
        $systemMessage = "<div class='alert-box error'><i class='fa-solid fa-circle-exclamation'></i> Thất bại: " . $e->getMessage() . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm Khóa Học Mới - DevMaster</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ==========================================================================
           KẾ THỪA 100% STYLE NHẬN DIỆN THƯƠNG HIỆU TỪ QUANLYKHOAHOC VÀ QUANLYHOCVIEN
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
        
        /* Sidebar Cố định */
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
        .dash-header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .dash-header h1 { font-size: 28px; font-weight: 800; margin: 0; }
        
        /* Cấu trúc Form Khung chứa Cao Cấp chuẩn Enterprise */
        .form-master-card {
            background: var(--admin-card);
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            padding: 35px;
            max-width: 850px;
        }

        .form-grid-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        .grid-full-width {
            grid-column: span 2;
        }

        .form-group-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .form-group-item label {
            font-size: 14px;
            font-weight: 700;
            color: #334155;
        }

        /* Input / Select Box đồng điệu tối đa với bộ nhận diện hệ thống */
        .form-control-input, .form-control-select {
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 14px;
            outline: none;
            background: #ffffff;
            color: #1e293b;
            transition: all 0.3s;
            font-family: inherit;
        }
        .form-control-input:focus, .form-control-select:focus {
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
        }

        /* Hộp văn bản nhập mới ẩn/hiện động dạng Slide Smooth */
        .dynamic-insert-input-box {
            margin-top: 8px;
            display: none; /* Ban đầu ẩn đi, kích hoạt bằng JS */
            border-left: 3px solid #10b981;
            padding-left: 12px;
        }

        /* Vùng nút Thao tác hành động cấp cao */
        .form-actions-footer-bar {
            margin-top: 35px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 12px;
        }
        .btn-action-master {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.2s;
        }
        .btn-submit-save { background: var(--admin-primary); color: white; box-shadow: 0 4px 6px rgba(79, 70, 229, 0.2); }
        .btn-submit-save:hover { background: #4338ca; }
        
        .btn-cancel-return { background: #f1f5f9; color: #475569; }
        .btn-cancel-return:hover { background: #e2e8f0; }

        /* NÚT THÊM BÀI HỌC */
        .btn-add-lesson-bridge {
            background: #0284c7; 
            color: #ffffff;
            box-shadow: 0 4px 6px rgba(2, 132, 199, 0.2);
        }
        .btn-add-lesson-bridge:hover {
            background: #0369a1;
        }

        /* Hệ thống thông điệp cảnh báo */
        .alert-box { padding: 14px 20px; border-radius: 8px; margin-bottom: 25px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .alert-box.success { background: #ecfdf5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-box.error { background: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; }
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
                <a href="/DevMaster/Admin/QuanLyKhoaHoc.php" class="menu-item active"><i class="fa-solid fa-book"></i> Quản lý khóa học</a>
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
                <h1>Thêm Khóa Học Mới</h1>
            </div>
        </div>

        <?php if(!empty($systemMessage)) { echo $systemMessage; } ?>

        <form id="master-course-form" class="form-master-card" method="POST" enctype="multipart/form-data">
            <input type="hidden" id="embedded_lessons_json" name="embedded_lessons_json" value="[]">
            <div class="form-grid-layout">
                
                <div class="form-group-item grid-full-width">
                    <label for="ten_khoahoc"><i class="fa-solid fa-heading"></i> Tên khóa học<span style="color:red">*</span></label>
                    <input type="text" id="ten_khoahoc" name="ten_khoahoc" class="form-control-input" oninput="evaluateRequiredEngine()">
                </div>

                <div class="form-group-item">
                    <label for="ten_giangvien"><i class="fa-solid fa-user-tie"></i> Giảng viên<span style="color:red">*</span></label>
                    <input type="text" id="ten_giangvien" name="ten_giangvien" class="form-control-input" required>
                </div>

                <div class="form-group-item">
                    <label for="gia"><i class="fa-solid fa-money-bill-wave"></i> Học phí khóa học (VND)<span style="color:red">*</span></label>
                    <input type="number" id="gia" name="gia" min="0" step="1000" class="form-control-input" required>
                </div>

                <div class="form-group-item">
                    <label for="danhmuc_select"><i class="fa-solid fa-layer-group"></i> Danh mục khóa học</label>
                    <select id="danhmuc_select" name="danhmuc_select" class="form-control-select" onchange="handleDanhMucToggleEngine()">
                        <option value="NEW" selected>[ + ] Tạo danh mục mới</option>
                        <?php foreach($danhmucList as $dm): ?>
                            <option value="<?php echo $dm['DanhMucId']; ?>"><?php echo htmlspecialchars($dm['TenDanhMuc']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="new_danhmuc_wrapper" class="dynamic-insert-input-box" style="display: block;">
                        <input type="text" id="new_danhmuc_name" name="new_danhmuc_name" class="form-control-input">
                    </div>
                </div>

                <div class="form-group-item">
                    <label for="nhom_select"><i class="fa-solid fa-folder-tree"></i> Nhóm khóa học</label>
                    <select id="nhom_select" name="nhom_select" class="form-control-select" onchange="handleNhomToggleEngine()">
                        <option value="NEW" selected>[ + ] Tạo nhóm khóa học mới</option>
                        <?php foreach($nhomList as $nhom): ?>
                            <option value="<?php echo $nhom['NhomKhoaHocId']; ?>" data-parent="<?php echo $nhom['DanhMucId']; ?>"><?php echo htmlspecialchars($nhom['TenNhom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="new_nhom_wrapper" class="dynamic-insert-input-box" style="display: block;">
                        <input type="text" id="new_nhom_name" name="new_nhom_name" class="form-control-input">
                    </div>
                </div>

                <div class="form-group-item">
                    <label for="is_featured"><i class="fa-solid fa-star"></i> Chế độ hiển thị nổi bật</label>
                    <select id="is_featured" name="is_featured" class="form-control-select">
                        <option value="0" selected>Disable</option>
                        <option value="1">Enable</option>
                    </select>
                </div>

                <div class="form-group-item">
                    <label for="anh_khoahoc"><i class="fa-solid fa-image"></i> Ảnh bìa khóa học<span id="star_anh" style="color:red; display:none;">*</span></label>
                    <input type="file" id="anh_khoahoc" name="anh_khoahoc" class="form-control-input" accept="image/*">
                </div>

            </div>

            <div class="form-actions-footer-bar">
                <button type="button" class="btn-action-master btn-add-lesson-bridge" onclick="navigateToThemBaiHocEngine()">
                    <i class="fa-solid fa-circle-plus"></i> Thêm bài học
                </button>
                <button type="submit" class="btn-action-master btn-submit-save">
                    <i class="fa-solid fa-floppy-disk"></i> Lưu
                </button>
                <a href="QuanLyKhoaHoc.php" class="btn-action-master btn-cancel-return">Hủy bỏ</a>
            </div>
        </form>
    </main>

    <script>
        // Mảng lưu dữ liệu nhóm gốc để thực hiện lọc tự động theo danh mục cha
        const fullNhomOptionsCollection = Array.from(document.getElementById('nhom_select').options).map(opt => ({
            value: opt.value,
            text: opt.text,
            parent: opt.getAttribute('data-parent')
        }));

        /**
         * Bộ điều khiển Engine logic: Kiểm tra tính rỗng của Tên khóa học để gán trạng thái bắt buộc cho Ảnh bìa
         */
        function evaluateRequiredEngine() {
            const tenKhoaHocVal = document.getElementById('ten_khoahoc').value.trim();
            const inputAnh = document.getElementById('anh_khoahoc');
            const starAnh = document.getElementById('star_anh');
            
            if (tenKhoaHocVal !== "") {
                // Nếu điền tên khóa học, ảnh bìa trở thành bắt buộc
                inputAnh.required = true;
                starAnh.style.display = "inline";
            } else {
                // Nếu bỏ trống tên khóa học, ảnh bìa không bắt buộc nữa
                inputAnh.required = false;
                starAnh.style.display = "none";
            }
        }

        /**
         * Xử lý ẩn/hiện ô văn bản khi người dùng chuyển đổi danh mục
         */
        function handleDanhMucToggleEngine() {
            const selectDM = document.getElementById('danhmuc_select');
            const wrapperInputDM = document.getElementById('new_danhmuc_wrapper');
            const inputDM = document.getElementById('new_danhmuc_name');
            const selectNhom = document.getElementById('nhom_select');

            if (selectDM.value === 'NEW') {
                wrapperInputDM.style.display = "block";
                inputDM.required = true;
                // Nếu danh mục tạo mới hoàn toàn, bắt buộc nhóm cũng phải tạo mới để liên kết chéo
                selectNhom.value = 'NEW';
                handleNhomToggleEngine();
            } else {
                wrapperInputDM.style.display = "none";
                inputDM.required = false;
                // Lọc động: Chỉ hiển thị các nhóm thuộc về Danh mục vừa chọn
                filterNhomOptionsByParent(parseInt(selectDM.value));
            }
        }

        /**
         * Xử lý ẩn/hiện ô văn bản khi người dùng chuyển đổi nhóm khóa học
         */
        function handleNhomToggleEngine() {
            const selectNhom = document.getElementById('nhom_select');
            const wrapperInputNhom = document.getElementById('new_nhom_wrapper');
            const inputNhom = document.getElementById('new_nhom_name');

            if (selectNhom.value === 'NEW') {
                wrapperInputNhom.style.display = "block";
                inputNhom.required = true;
            } else {
                wrapperInputNhom.style.display = "none";
                inputNhom.required = false;
            }
        }

        /**
         * Thuật toán lọc động các Nhóm khóa học tương ứng dựa theo Danh mục cha đã chọn
         */
        function filterNhomOptionsByParent(parentDMId) {
            const selectNhom = document.getElementById('nhom_select');
            selectNhom.innerHTML = ""; // Làm sạch

            // Luôn giữ tùy chọn thêm mới ở đầu
            const defaultNewOpt = document.createElement('option');
            defaultNewOpt.value = "NEW";
            defaultNewOpt.text = "[ + ] Tạo nhóm khóa học mới";
            selectNhom.appendChild(defaultNewOpt);

            // Duyệt lọc qua mảng đệm
            fullNhomOptionsCollection.forEach(item => {
                if(item.value !== 'NEW' && parseInt(item.parent) === parentDMId) {
                    const opt = document.createElement('option');
                    opt.value = item.value;
                    opt.text = item.text;
                    opt.setAttribute('data-parent', item.parent);
                    selectNhom.appendChild(opt);
                }
            });

            selectNhom.value = "NEW";
            handleNhomToggleEngine();
        }

        // Chạy rà soát cấu hình ban đầu ngay khi tải trang xong
        evaluateRequiredEngine();
        handleDanhMucToggleEngine();
        handleNhomToggleEngine();

        /**
         * ==========================================================================
         * BỘ NÃO ĐỒNG BỘ TRẠNG THÁI FORM HAI CHIỀU & RÀN BUỘC ĐIỀU KIỆN CHUYỂN TRANG
         * NÂNG CẤP V2: QUẢN LÝ FILE ẢNH & BIẾN TẠM KHÔNG BỊ MẤT DỮ LIỆU
         * ==========================================================================
         */

        document.addEventListener("DOMContentLoaded", () => {
            // Khởi tạo một biến tham số URL dùng chung cho toàn bộ sự kiện tải trang
            const urlParams = new URLSearchParams(window.location.search);
            
            // 1. TỰ ĐỘNG KHÔI PHỤC DỮ LIỆU KHI QUAY LẠI TỪ TRANG THÊM BÀI HỌC (Xử lý đồng bộ cả status và action)
            if (urlParams.get('status') === 'retrieved' || urlParams.get('action') === 'restore') {
                const cachedForm = localStorage.getItem('devmaster_temp_course_form');
                if (cachedForm) {
                    const formData = JSON.parse(cachedForm);
                    
                    // Khôi phục các trường dữ liệu text, select, textarea
                    Object.keys(formData).forEach(key => {
                        const element = document.getElementById(key) || document.querySelector(`[name="${key}"]`);
                        if (element && element.type !== 'file') { // Bỏ qua input file để xử lý riêng biệt bên dưới
                            if (element.type === 'checkbox' || element.type === 'radio') {
                                element.checked = formData[key];
                            } else {
                                element.value = formData[key];
                            }
                            // Kích hoạt sự kiện change thủ công để đồng bộ giao diện logic danh mục/nhóm
                            element.dispatchEvent(new Event('change'));
                        }
                    });

                    // 🌟 BIẾN ĐỔI PHẦN TỬ FILE ẢNH: Đưa thẳng tên ảnh cũ vào thanh chọn file của hệ thống
                    if (formData.saved_image_path || formData.saved_image_name) {
                        const targetImgPath = formData.saved_image_path || formData.saved_image_name;
                        const imageInput = document.querySelector('input[type="file"][name="anh_khoahoc"]') || document.querySelector('input[type="file"]');
                        if (imageInput) {
                            // Trích xuất lấy tên file gốc nguyên bản
                            const displayFileName = targetImgPath.split('/').pop().replace(/^\d+_/, '');
                            
                            // Thay đổi thuộc tính text hiển thị mặc định của chính thanh input file
                            imageInput.title = displayFileName; 
                            
                            const siblingText = imageInput.parentNode.querySelector('.file-custom-text, span, p, label');
                            if (siblingText && siblingText !== imageInput) {
                                siblingText.innerText = displayFileName;
                            }

                            // Tạo hoặc cập nhật input hidden truyền ngược lại PHP để không dính lỗi trống file
                            let hiddenImgInput = document.getElementById('devmaster_hidden_image_fallback');
                            if (!hiddenImgInput) {
                                hiddenImgInput = document.createElement('input');
                                hiddenImgInput.type = 'hidden';
                                hiddenImgInput.id = 'devmaster_hidden_image_fallback';
                                hiddenImgInput.name = 'devmaster_hidden_image_fallback';
                                imageInput.parentNode.appendChild(hiddenImgInput);
                            }
                            hiddenImgInput.value = targetImgPath;
                            
                            // Gỡ bỏ cờ required tạm thời vì hệ thống đã được backup dữ liệu ảnh cũ
                            imageInput.removeAttribute('required');
                        }
                    }

                    // Xử lý hiển thị lại các khung nhập liệu động nếu trước đó chọn tạo mới "NEW"
                    const dmSelect = document.querySelector('[name="danhmuc_select"]');
                    if (dmSelect && dmSelect.value === 'NEW') {
                        const dmBox = document.getElementById('new_danhmuc_wrapper') || document.getElementById('dynamic_danhmuc_input_wrapper') || document.querySelector('.dynamic-insert-input-box');
                        if (dmBox) dmBox.style.display = 'block';
                    }
                    const nhomSelect = document.querySelector('[name="nhom_select"]');
                    if (nhomSelect && nhomSelect.value === 'NEW') {
                        const nhomBox = document.getElementById('new_nhom_wrapper') || document.getElementById('dynamic_nhom_input_wrapper') || document.querySelectorAll('.dynamic-insert-input-box')[1];
                        if (nhomBox) nhomBox.style.display = 'block';
                    }

                    // Kích hoạt lại các trigger lọc động của hệ thống
                    if (typeof handleDanhMucToggleEngine === "function") handleDanhMucToggleEngine();
                    if (typeof handleNhomToggleEngine === "function") handleNhomToggleEngine();
                    if (typeof evaluateRequiredEngine === "function") evaluateRequiredEngine();
                }
            }

            // Lắng nghe hành vi nếu người dùng chủ động chọn lại file mới
            const mainImageInput = document.querySelector('input[type="file"][name="anh_khoahoc"]') || document.querySelector('input[type="file"]');
            if (mainImageInput) {
                mainImageInput.addEventListener('change', function() {
                    // Xóa bỏ biến ẩn fallback ngay khi admin chọn ảnh mới hoàn toàn
                    const fallback = document.getElementById('devmaster_hidden_image_fallback');
                    if (fallback) fallback.remove();
                });
            }
        });

        // 2. HÀM VALIDATE KIỂM TRA ĐIỀU KIỆN & ĐÓNG GÓI CHUYỂN TRANG
        function navigateToThemBaiHocEngine() {
            const formInputs = document.querySelectorAll('.form-master-card input[required], .form-master-card select[required], .form-master-card textarea[required]');
            let isFormValid = true;
            let firstInvalidElement = null;

            formInputs.forEach(input => {
                if (input.offsetParent !== null) { 
                    if (!input.value.trim() || input.value === 'NEW') {
                        isFormValid = false;
                        if (!firstInvalidElement) firstInvalidElement = input;
                        input.style.borderColor = '#ef4444'; 
                    } else {
                        input.style.borderColor = '#cbd5e1'; 
                    }
                }
            });

            if (!isFormValid) {
                alert('👉 Vui lòng điền đầy đủ tất cả thông tin bắt buộc của Khóa Học trước khi tiến hành Thêm Bài Học!');
                if (firstInvalidElement) firstInvalidElement.focus();
                return; 
            }

            // NẾU HỢP LỆ: Tiến hành lưu Form State vào LocalStorage
            const courseFormState = {};
            const allInputs = document.querySelectorAll('.form-master-card input, .form-master-card select, .form-master-card textarea');
            
            allInputs.forEach(input => {
                if (input.id || input.name) {
                    const key = input.id || input.name;
                    if (input.type === 'file') {
                        // 🌟 CHIẾN THUẬT: Nếu là ô chọn file, ta trích xuất lấy tên file đã chọn để lưu tên đệm
                        if (input.files && input.files[0]) {
                            courseFormState['saved_image_name'] = input.files[0].name;
                        } else {
                            // Nếu chưa chọn file mới nhưng có file đệm cũ từ trước, giữ lại nó
                            const fallback = document.getElementById('devmaster_hidden_image_fallback');
                            if (fallback) {
                                courseFormState['saved_image_name'] = fallback.value;
                            }
                        }
                    } else {
                        courseFormState[key] = (input.type === 'checkbox' || input.type === 'radio') ? input.checked : input.value;
                    }
                }
            });

            localStorage.setItem('devmaster_temp_course_form', JSON.stringify(courseFormState));
            window.location.href = 'ThemBaiHoc.php';
        }

        // ĐỒNG BỘ DỮ LIỆU BÀI HỌC SANG FORM TRƯỚC KHI SUBMIT LƯU KHÓA HỌC
        const courseFormElement = document.getElementById('master-course-form');
        if (courseFormElement) {
            courseFormElement.addEventListener('submit', function() {
                const localLessons = localStorage.getItem('devmaster_temp_lessons');
                if (localLessons) {
                    document.getElementById('embedded_lessons_json').value = localLessons;
                }
            });
        }

        const masterSaveBtn = document.querySelector('.btn-submit-save');
        if (masterSaveBtn) {
            masterSaveBtn.addEventListener('click', () => {
                // Sau khi form submit, dữ liệu trong form đã được lấy, xóa cache an toàn
                setTimeout(() => {
                    localStorage.removeItem('devmaster_temp_course_form');
                }, 100);
            });
        }
    </script>
</body>
</html>