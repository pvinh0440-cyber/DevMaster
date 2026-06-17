<?php
// Admin/ThemBaiHoc.php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
include '../Database.php';

$db = isset($connect) ? $connect : $conn;

// Khóa bảo mật: Phải là Admin tối cao
if (!isset($_SESSION['IsAdmin']) || $_SESSION['IsAdmin'] !== true) {
    header("Location: /DevMaster/Pages/Login.php");
    exit;
}

// --- ĐOẠN THÊM MỚI: Lấy danh sách tất cả khóa học phục vụ Dropdown nếu đi từ QLKH ---
$allCourses = [];
if (isset($_GET['from']) && $_GET['from'] === 'QLKH') {
    try {
        $courseQuery = "SELECT KhoaHocId, Ten FROM khoahoc ORDER BY Ten ASC";
        if ($db instanceof PDO) {
            $allCourses = $db->query($courseQuery)->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $res = $db->query($courseQuery);
            while($r = $res->fetch_assoc()) { $allCourses[] = $r; }
        }
    } catch (Exception $e) { /**/ }
}

$systemMessage = "";

// Xử lý lưu trữ chính thức vào Database khi nhấn nút "Lưu" cuối cùng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'FINAL_SAVE') {
    try {
        $khoaHocId = intval($_POST['target_khoahoc_id'] ?? 0);
        $lessonsJson = $_POST['lessons_data_json'] ?? '[]';
        $lessonsArray = json_decode($lessonsJson, true);

        if (empty($lessonsArray)) {
            throw new Exception("Danh sách bài học trống, vui lòng thêm ít nhất một bài học trước khi lưu!");
        }

        // Tự động kiểm tra xem có thông tin cấu trúc khóa học mới đi kèm không
        $courseDataJson = $_POST['course_meta_data_json'] ?? '';
        $courseData = json_decode($courseDataJson, true);

        if ($khoaHocId <= 0 && !empty($courseData)) {
            // LUỒNG TỰ ĐỘNG KHỞI TẠO CẤU TRÚC KHI LƯU TỪ THEMBAIHOC
            $tenKhoaHoc = trim($courseData['ten_khoahoc'] ?? '');
            $tenGiangVien = trim($courseData['ten_giangvien'] ?? '');
            $gia = floatval($courseData['gia'] ?? 0);
            $isFeatured = isset($courseData['is_featured']) ? intval($courseData['is_featured']) : 0;
            
            $danhmucSelect = $courseData['danhmuc_select'] ?? 'NEW';
            $nhomSelect = $courseData['nhom_select'] ?? 'NEW';
            $newDanhmucName = trim($courseData['new_danhmuc_name'] ?? '');
            $newNhomName = trim($courseData['new_nhom_name'] ?? '');

            $finalDanhMucId = 0;
            $finalNhomKhoaHocId = 0;

            if (empty($tenKhoaHoc)) {
                throw new Exception("Thông tin khóa học từ bước trước bị trống. Không thể tạo khóa học mới!");
            }

            // 1. Tạo Danh mục
            if ($danhmucSelect === 'NEW') {
                if (!empty($newDanhmucName)) {
                    $insDM = "INSERT INTO danhmuc (TenDanhMuc) VALUES (?)";
                    $stmt = $db->prepare($insDM);
                    if ($db instanceof PDO) {
                        $stmt->execute([$newDanhmucName]);
                        $finalDanhMucId = $db->lastInsertId();
                    } else {
                        $stmt->bind_param("s", $newDanhmucName);
                        $stmt->execute();
                        $finalDanhMucId = $db->insert_id;
                    }
                } else {
                    throw new Exception("Vui lòng cung cấp tên Danh mục mới!");
                }
            } else {
                $finalDanhMucId = intval($danhmucSelect);
            }

            // 2. Tạo Nhóm Khóa Học
            if ($nhomSelect === 'NEW') {
                if (!empty($newNhomName)) {
                    $insNhom = "INSERT INTO nhomkhoahoc (DanhMucId, TenNhom) VALUES (?, ?)";
                    $stmt = $db->prepare($insNhom);
                    if ($db instanceof PDO) {
                        $stmt->execute([$finalDanhMucId, $newNhomName]);
                        $finalNhomKhoaHocId = $db->lastInsertId();
                    } else {
                        $stmt->bind_param("is", $finalDanhMucId, $newNhomName);
                        $stmt->execute();
                        $finalNhomKhoaHocId = $db->insert_id;
                    }
                } else {
                    throw new Exception("Vui lòng cung cấp tên Nhóm khóa học mới!");
                }
            } else {
                $finalNhomKhoaHocId = intval($nhomSelect);
            }

            // 3. Tạo Khóa Học Mới
            $dbImagePath = $courseData['saved_image_name'] ?? 'Images/default-course.png';
            if (strpos($dbImagePath, 'Images/') === false) {
                $dbImagePath = "Images/" . $dbImagePath;
            }

            $insertCourseQuery = "INSERT INTO khoahoc (NhomKhoaHocId, Ten, Anh, Gia, TenGiangVien, IsFeatured, TrangThai)
                                  VALUES (?, ?, ?, ?, ?, ?, b'1')";
            $stmt = $db->prepare($insertCourseQuery);
            if ($db instanceof PDO) {
                $stmt->execute([$finalNhomKhoaHocId, $tenKhoaHoc, $dbImagePath, $gia, $tenGiangVien, $isFeatured]);
                $khoaHocId = $db->lastInsertId();
            } else {
                $stmt->bind_param("issdsi", $finalNhomKhoaHocId, $tenKhoaHoc, $dbImagePath, $gia, $tenGiangVien, $isFeatured);
                $stmt->execute();
                $khoaHocId = $db->insert_id;
            }
        }

        if ($khoaHocId <= 0) {
            throw new Exception("Không xác định được Khóa học mục tiêu để gắn bài học!");
        }

        // Tiến hành chèn danh sách bài học vào Database dựa trên ID khóa học hợp lệ vừa tìm được hoặc vừa tạo
        $insertQuery = "INSERT INTO baihoc (KhoaHocId, Ten, LinkVideo) VALUES (?, ?, ?)";
        $stmt = $db->prepare($insertQuery);

        // --- ĐOẠN ĐÃ SỬA: Tiến hành kiểm tra trùng lặp dưới DB trước khi chèn danh sách bài học ---
        foreach ($lessonsArray as $lesson) {
            $tenBaiHoc = trim($lesson['ten'] ?? '');
            $linkVideo = trim($lesson['video'] ?? 'default-video.mp4');
            if (empty($tenBaiHoc)) continue;

            // 1. Kiểm tra trùng lặp Tên bài học hoặc Link Video trong khóa học hiện tại
            $checkQuery = "SELECT COUNT(*) as co FROM baihoc WHERE KhoaHocId = ? AND (LOWER(Ten) = LOWER(?) OR LOWER(LinkVideo) = LOWER(?))";
            $isDuplicate = false;

            if ($db instanceof PDO) {
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->execute([$khoaHocId, $tenBaiHoc, $linkVideo]);
                $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
                if (($row['co'] ?? 0) > 0) {
                    $isDuplicate = true;
                }
            } else {
                $checkStmt = $db->prepare($checkQuery);
                $lowerTen = strtolower($tenBaiHoc);
                $lowerVideo = strtolower($linkVideo);
                $checkStmt->bind_param("iss", $khoaHocId, $lowerTen, $lowerVideo);
                $checkStmt->execute();
                $resCheck = $checkStmt->get_result();
                $row = $resCheck->fetch_assoc();
                if (($row['co'] ?? 0) > 0) {
                    $isDuplicate = true;
                }
            }

            // 2. Nếu phát hiện trùng lặp, chặn tiến trình và thông báo lỗi trực tiếp
            if ($isDuplicate) {
                throw new Exception("Bài học với tên \"{$tenBaiHoc}\" hoặc video \"{$linkVideo}\" đã tồn tại trong hệ thống quản lý của khóa học này!");
            }

            // 3. Nếu không trùng, tiến hành chèn dữ liệu như bình thường
            $insertQuery = "INSERT INTO baihoc (KhoaHocId, Ten, LinkVideo) VALUES (?, ?, ?)";
            $stmt = $db->prepare($insertQuery);

            if ($db instanceof PDO) {
                $stmt->execute([$khoaHocId, $tenBaiHoc, $linkVideo]);
            } else {
                $stmt->bind_param("iss", $khoaHocId, $tenBaiHoc, $linkVideo);
                $stmt->execute();
            }
        }

        $systemMessage = "<div class='alert-box success'><i class='fa-solid fa-circle-check'></i> Tạo thành công!</div>";
        echo "<script>
            // Xóa bộ nhớ tạm thời
            localStorage.removeItem('devmaster_temp_lessons'); 
            localStorage.removeItem('devmaster_temp_course_form');
            
            // Kích hoạt trạng thái luôn mở bung cho khóa học này
            let expandKh = JSON.parse(localStorage.getItem('remember_expandKh') || '{}');
            expandKh[" . $khoaHocId . "] = true;
            localStorage.setItem('remember_expandKh', JSON.stringify(expandKh));

            // 2. ÉP ĐỒNG BỘ TRẠNG THÁI MỞ RỘNG CỦA CẢ DANH MỤC VÀ NHÓM CHA
            const targetKhId = " . $khoaHocId . ";
            let expandNhom = JSON.parse(localStorage.getItem('remember_expandNhom') || '{}');
            let expandDM = JSON.parse(localStorage.getItem('remember_expandDM') || '{}');

            // Tìm option tương ứng trong datalist để bóc tách ID Nhóm và Danh mục cha
            const courseInput = document.getElementById('qlkh_course_search_input') || document.getElementById('courseSearchInput');
            const courseDatalist = document.getElementById('qlkh_courses_list') || document.getElementById('qlkh_courses_datalist') || document.getElementById('allCoursesDatalist');

            if (courseInput && courseDatalist) {
                const selectedOpt = Array.from(courseDatalist.options).find(opt => opt.value === courseInput.value.trim() || opt.getAttribute('data-id') == targetKhId);
                if (selectedOpt) {
                    const nhomId = selectedOpt.getAttribute('data-parent-nhom') || selectedOpt.getAttribute('data-nhom-id');
                    const dmId = selectedOpt.getAttribute('data-parent-dm') || selectedOpt.getAttribute('data-dm-id');
                    if (nhomId) expandNhom[nhomId] = true;
                    if (dmId) expandDM[dmId] = true;
                }
            }

            // Luồng kiểm tra dự phòng từ bộ nhớ tạm thời của hệ thống
            const tempCourseData = localStorage.getItem('devmaster_temp_course_form');
            if (tempCourseData) {
                const parsedMeta = JSON.parse(tempCourseData);
                if (parsedMeta.danhmuc_select && parsedMeta.danhmuc_select !== 'NEW') expandDM[parsedMeta.danhmuc_select] = true;
                if (parsedMeta.nhom_select && parsedMeta.nhom_select !== 'NEW') expandNhom[parsedMeta.nhom_select] = true;
            }

            // Thực hiện lưu đè đồng bộ để không làm mất các tầng đang mở khác của người dùng
            localStorage.setItem('remember_expandNhom', JSON.stringify(expandNhom));
            localStorage.setItem('remember_expandDM', JSON.stringify(expandDM));

            // Đẩy phân trang của tầng bài học thuộc khóa học này sang trang cuối (hoặc trang 1 tùy cấu hình)
            let subBhPage = JSON.parse(localStorage.getItem('remember_subBhPage') || '{}');
            subBhPage[" . $khoaHocId . "] = 1; // Có thể đổi thành trang cụ thể nếu hệ thống có biến tính tổng trang
            localStorage.setItem('remember_subBhPage', JSON.stringify(subBhPage));
            
            // Chuyển hướng sau 1.5 giây để hiệu ứng mượt mà
            setTimeout(function() {
                window.location.href = 'QuanLyKhoaHoc.php?status=success';
            }, 1500);
        </script>";
        // Bỏ header refresh cũ của PHP để tránh xung đột điều hướng với script
    } catch (Exception $e) {
        $systemMessage = "<div class='alert-box error'><i class='fa-solid fa-circle-exclamation'></i> Thất bại: " . $e->getMessage() . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản Lý Cấu Trúc Bài Học - DevMaster</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ==========================================================================
           KẾ THỪA 100% STYLE NHẬN DIỆN THƯƠNG HIỆU TỪ THEMKHOAHOC & VAOHOCNGAY
           ========================================================================== */
        :root {
            --sidebar-width: 260px;
            --admin-primary: #4f46e5;
            --admin-bg: #f8fafc;
            --admin-card: #ffffff;
            --admin-text: #1e293b;
            --admin-muted: #64748b;
            --primary-glow: #00E6F6;
            --progress-fill: linear-gradient(90deg, #3B82F6, #00E6F6);
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
        .btn-back:hover { background: #475569; }
        .btn-logout { color: #f87171; background: rgba(239, 68, 68, 0.1); }
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
        
        /* Form Khung chứa Cao Cấp kích thước bằng form-master-card */
        .form-master-card {
            background: var(--admin-card);
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            padding: 35px;
            max-width: 850px;
            box-sizing: border-box;
            position: relative;
        }

        /* Vùng chứa các bài học có khả năng Scroll mượt mà khi tràn dữ liệu quá 12 bài */
        .lessons-scroll-wrapper {
            max-height: 640px;
            overflow-y: auto;
            padding-right: 8px;
            margin-bottom: 20px;
        }
        .lessons-scroll-wrapper::-webkit-scrollbar {
            width: 6px;
        }
        .lessons-scroll-wrapper::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }
        .lessons-scroll-wrapper::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        /* Grid hiển thị các ô bài học */
        .lessons-internal-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr); /* Tối ưu hóa không gian hiển thị trong khung card */
            gap: 20px;
        }

        /* Cấu trúc thiết kế ô bài học thừa hưởng từ VaoHocNgay */
        .lesson-workspace-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            overflow: hidden;
            display: flex;
            position: relative;
            box-sizing: border-box;
            transition: all 0.3s ease;
        }
        .lesson-workspace-card:hover {
            border-color: var(--admin-primary);
            box-shadow: 0 8px 16px rgba(79, 70, 229, 0.08);
        }

        /* Khối hiển thị Media Video bên trái */
        .video-preview-block {
            width: 45%;
            aspect-ratio: 16/9;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .video-preview-block video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Thông tin nội dung bài học */
        .lesson-details-block {
            padding: 12px;
            width: 43%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-sizing: border-box;
        }
        .lesson-meta-title {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .lesson-meta-filename {
            font-size: 11px;
            color: var(--admin-muted);
            word-break: break-all;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Hệ thống nút thao tác bên phải của thẻ bài học */
        .lesson-actions-sidebar {
            width: 12%;
            border-left: 1px solid #f1f5f9;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 8px 0;
            background: #fafafa;
        }
        .btn-mini-control {
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-mini-delete {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.08);
        }
        .btn-mini-delete:hover {
            background: #ef4444;
            color: white;
        }
        .btn-mini-edit {
            color: #0284c7;
            background: rgba(2, 132, 199, 0.08);
        }
        .btn-mini-edit:hover {
            background: #0284c7;
            color: white;
        }

        /* Thanh trạng thái trống khi chưa có bài học */
        .empty-state-container {
            grid-column: span 2;
            text-align: center;
            padding: 60px 20px;
            color: var(--admin-muted);
        }
        .empty-state-container i {
            font-size: 48px;
            color: #cbd5e1;
            margin-bottom: 15px;
        }

        /* Vùng nút Thao tác hành động chân trang */
        .form-actions-footer-bar {
            margin-top: 25px;
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

        .btn-add-lesson-bridge {
            background: #10b981; 
            color: #ffffff;
            box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2);
        }
        .btn-add-lesson-bridge:hover { background: #059669; }
        
        .btn-navigation-back { background: #64748b; color: white; }
        .btn-navigation-back:hover { background: #475569; }

        /* ==========================================================================
           HỆ THỐNG MODAL DIỄN HỌA BLUR BACKGROUND CHUẨN ENTERPRISE
           ========================================================================== */
        .modal-overlay-blur {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0; pointer-events: none;
            transition: all 0.3s ease;
        }
        .modal-overlay-blur.active {
            opacity: 1;
            pointer-events: auto;
        }
        .modal-content-box {
            background: #ffffff;
            border-radius: 16px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
            padding: 30px;
            box-sizing: border-box;
            transform: scale(0.9);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .modal-overlay-blur.active .modal-content-box {
            transform: scale(1);
        }
        .modal-header-title {
            font-size: 18px;
            font-weight: 800;
            margin-top: 0;
            margin-bottom: 20px;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 18px;
        }
        .modal-form-group label {
            font-size: 13px;
            font-weight: 700;
            color: #334155;
        }
        .modal-input-control {
            padding: 11px 14px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 14px;
            outline: none;
        }
        .modal-input-control:focus {
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
        }
        .modal-footer-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 25px;
        }

        /* Phân trang nội bộ tương tự VaoHocNgay */
        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 15px 0 0 0;
            border-top: 1px solid #f1f5f9;
        }
        .pagination-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 35px;
            height: 35px;
            padding: 0 10px;
            border-radius: 8px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #475569;
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }
        .pagination-btn:hover:not(.disabled) {
            border-color: var(--admin-primary);
            background: var(--admin-primary);
            color: white;
        }
        .pagination-btn.active {
            background: var(--admin-primary);
            color: white;
            border-color: var(--admin-primary);
        }
        .pagination-btn.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* --- ĐOẠN ĐÃ SỬA: Cấu hình Toast thông báo xuất hiện từ hư không, không làm giãn trang --- */
        .alert-box { 
            position: fixed;
            top: 30px;
            right: 30px;
            z-index: 9999;
            padding: 16px 24px; 
            border-radius: 10px; 
            font-size: 14px; 
            font-weight: 600; 
            display: flex; 
            align-items: center; 
            gap: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            
            /* Giới hạn độ dài bảng thông báo - Tự động xuống dòng khi thông báo dài */
            max-width: 380px;
            word-break: break-word;
            white-space: normal;
            
            /* Hiệu ứng xuất hiện từ hư không (Slide từ phải qua và Fade in) */
            animation: toastSlideIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
            transition: opacity 0.5s ease, transform 0.5s ease;
        }
        .alert-box.success { background: #ffffff; color: #10b981; border-left: 5px solid #10b981; }
        .alert-box.error { background: #ffffff; color: #ef4444; border-left: 5px solid #ef4444; }
        
        /* Trạng thái ẩn đi (Fade out) khi Javascript kích hoạt */
        .alert-box.fade-out {
            opacity: 0;
            transform: translateY(-20px);
            pointer-events: none;
        }

        @keyframes toastSlideIn {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
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
                <h1>Bài Học</h1>
            </div>
        </div>
        <?php if (isset($_GET['from']) && $_GET['from'] === 'QLKH'): ?>
            <style>
                .qlkh-search-dropdown-box { margin-bottom: 25px; max-width: 850px; position: relative; }
                .qlkh-search-dropdown-box label { display: block; font-size: 13px; font-weight: 700; color: #475569; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;}
                .qlkh-wrapper-input { position: relative; display: flex; align-items: center; }
                .qlkh-wrapper-input i { position: absolute; left: 16px; color: #94a3b8; font-size: 16px; }
                .qlkh-combo-input { width: 100%; padding: 14px 16px 14px 45px; border-radius: 10px; border: 1px solid #e2e8f0; font-size: 15px; font-weight: 600; outline: none; background: #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: all 0.3s; }
                .qlkh-combo-input:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15); }
            </style>

            <div class="qlkh-search-dropdown-box">
                <label><i class="fa-solid fa-magnifying-glass-arrow-right"></i> Chọn hoặc tìm nhanh khóa học mục tiêu</label>
                <div class="qlkh-wrapper-input">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <input 
                        type="text" 
                        id="qlkh_course_search_input" 
                        class="qlkh-combo-input" 
                        list="qlkh_courses_datalist" 
                        placeholder="Nhập chữ để tìm kiếm hoặc chọn từ danh sách thả xuống..."
                        autocomplete="off"
                    >
                </div>
                <datalist id="qlkh_courses_datalist">
                    <?php foreach ($allCourses as $course): ?>
                        <option value="<?php echo htmlspecialchars($course['Ten']); ?>" data-id="<?php echo $course['KhoaHocId']; ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
        <?php endif; ?>
        <?php if(!empty($systemMessage)): ?>
            <div id="system-toast-container">
                <?php echo $systemMessage; ?>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const toast = document.querySelector('#system-toast-container .alert-box');
                    if (toast) {
                        // Chờ 4 giây (4000ms) sau đó thêm class 'fade-out' để kích hoạt hiệu ứng biến mất mượt mà
                        setTimeout(function() {
                            toast.classList.add('fade-out');
                            // Sau khi hiệu ứng mờ kết thúc (500ms), tiến hành xóa hẳn phần tử khỏi DOM
                            setTimeout(function() {
                                toast.remove();
                            }, 500);
                        }, 4000);
                    }
                });
            </script>
        <?php endif; ?>

        <div class="form-master-card">
            <div class="lessons-scroll-wrapper">
                <div class="lessons-internal-grid" id="lessonsWorkspaceGrid">
                    </div>
            </div>

            <div class="pagination-container" id="internalPaginationBar">
                </div>

            <div class="form-actions-footer-bar">
                <button type="button" class="btn-action-master btn-add-lesson-bridge" onclick="openLessonModal('ADD')">
                    <i class="fa-solid fa-plus"></i> Thêm bài học mới
                </button>
                
                <button type="button" class="btn-action-master btn-submit-save" onclick="triggerFinalDatabaseSubmit()">
                    <i class="fa-solid fa-floppy-disk"></i> Lưu dữ liệu
                </button>
                <?php if (!isset($_GET['from']) || $_GET['from'] !== 'QLKH'): ?>
                    <button type="button" class="btn-action-master btn-navigation-back" onclick="navigateBackToCourse()">
                        <i class="fa-solid fa-rotate-left"></i> Quay lại
                    </button>
                <?php endif; ?>
                <?php if (isset($_GET['from']) && $_GET['from'] === 'QLKH'): ?>
                    <a href="QuanLyKhoaHoc.php" class="btn-action-master btn-cancel-return">Hủy bỏ</a>
                <?php else: ?>
                    <button type="button" class="btn-action-master btn-cancel-return" onclick="terminateAllProcesses();">Hủy bỏ</button>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <form method="POST" style="display:none;" id="final-master-submit-form">
        <input type="hidden" name="action_type" value="FINAL_SAVE">
        <input type="hidden" name="target_khoahoc_id" id="final_target_khoahoc_id" value="0">
        <input type="hidden" name="lessons_data_json" id="final_lessons_data_json" value="[]">
        <input type="hidden" name="course_meta_data_json" id="final_course_meta_data_json" value="[]">
    </form>

    <div class="modal-overlay-blur" id="lessonModalComponent">
        <div class="modal-content-box">
            <h3 class="modal-header-title" id="modalTitleText"><i class="fa-solid fa-circle-plus"></i> Thêm bài học mới</h3>
            
            <div class="modal-form-group">
                <label for="modal_lesson_name">Tên bài học</label>
                <input type="text" id="modal_lesson_name" class="modal-input-control" placeholder="Nhập tên tiêu đề bài học...">
            </div>

            <div class="modal-form-group">
                <label for="modal_lesson_video">Video bài học (File nguồn MP4)</label>
                <input type="file" id="modal_lesson_video" class="modal-input-control" accept="video/mp4" onchange="handleModalVideoSelect(this)">
                <span id="modal_video_current_label" style="font-size: 11px; color:#6366f1; margin-top:4px;"></span>
            </div>

            <div class="modal-footer-actions">
                <button type="button" class="btn-action-master btn-cancel-return" style="padding: 8px 16px;" onclick="closeLessonModal()">Hủy bỏ</button>
                <button type="button" class="btn-action-master btn-submit-save" style="padding: 8px 16px;" id="modalSubmitButton" onclick="saveModalStateData()">Thêm</button>
            </div>
        </div>
    </div>

    <script>
        // Khởi tạo State toàn cục cho danh sách bài học
        let LESSON_DATA_STATE = [];
        let CURRENT_PAGE = 1;
        const ITEMS_PER_PAGE = 12; // Giới hạn tối đa 12 bài học trên 1 trang theo yêu cầu

        // Biến kiểm soát chế độ Modal: 'ADD' hoặc 'EDIT'
        let MODAL_MODE = 'ADD';
        let SELECTED_EDIT_INDEX = null;
        let TEMPORARY_SELECTED_VIDEO_NAME = '';

        document.addEventListener('DOMContentLoaded', () => {
            // Đọc dữ liệu tạm từ localStorage để đảm bảo tính năng "Quay lại" không làm mất dữ liệu
            const cachedData = localStorage.getItem('devmaster_temp_lessons');
            if (cachedData) {
                LESSON_DATA_STATE = JSON.parse(cachedData);
            }
            renderLessonsStateEngine();

            const searchInput = document.getElementById('qlkh_course_search_input');
            const datalist = document.getElementById('qlkh_courses_datalist');
            const targetHiddenInput = document.getElementById('target_khoahoc_id'); // Ô hidden ID gốc của trang của bạn

            if (searchInput && datalist) {
                // Biến lưu trữ giá trị hợp lệ gần nhất (Dùng làm mặc định khi xóa trống bấm ra ngoài)
                let lastValidValue = searchInput.value;

                // Sự kiện khi người dùng tương tác xong và nhấn chuột ra ngoài (Blur)
                searchInput.addEventListener('blur', function() {
                    const currentVal = this.value.trim();
                    
                    // 1. Trường hợp để trống và bấm ra ngoài -> Tự động khôi phục về tên khóa học trước đó
                    if (currentVal === '') {
                        this.value = lastValidValue;
                        return;
                    }

                    // 2. Tìm kiếm xem tên vừa nhập có khớp với Option nào trong Datalist không
                    const options = datalist.querySelectorAll('option');
                    let isMatchFound = false;

                    options.forEach(opt => {
                        if (opt.value === currentVal) {
                            isMatchFound = true;
                            lastValidValue = currentVal; // Cập nhật lại giá trị hợp lệ mới nhất
                            
                            // Đồng bộ hóa ID khóa học sang ô input hidden xử lý dữ liệu gốc của hệ thống của bạn
                            if (targetHiddenInput) {
                                targetHiddenInput.value = opt.getAttribute('data-id');
                            }
                        }
                    });

                    // 3. Nếu gõ linh tinh không trùng khớp -> Cũng tự động rollback về giá trị chuẩn gần nhất
                    if (!isMatchFound) {
                        this.value = lastValidValue;
                    }
                });

                // Hỗ trợ lắng nghe thay đổi trực tiếp khi chọn từ Dropdown bằng chuột để cập nhật ID ngay lập tức
                searchInput.addEventListener('input', function() {
                    const currentVal = this.value.trim();
                    const options = datalist.querySelectorAll('option');
                    options.forEach(opt => {
                        if (opt.value === currentVal) {
                            lastValidValue = currentVal;
                            if (targetHiddenInput) {
                                targetHiddenInput.value = opt.getAttribute('data-id');
                            }
                        }
                    });
                });
            }
        });

        // Hàm mở Modal
        function openLessonModal(mode, index = null) {
            MODAL_MODE = mode;
            const modal = document.getElementById('lessonModalComponent');
            const title = document.getElementById('modalTitleText');
            const submitBtn = document.getElementById('modalSubmitButton');
            const nameInput = document.getElementById('modal_lesson_name');
            const videoInput = document.getElementById('modal_lesson_video');
            const videoLabel = document.getElementById('modal_video_current_label');

            videoInput.value = ''; // Reset file input
            videoLabel.innerText = '';

            if (mode === 'ADD') {
                title.innerHTML = `<i class="fa-solid fa-circle-plus"></i> Thêm bài học mới`;
                submitBtn.innerText = 'Thêm';
                nameInput.value = '';
                TEMPORARY_SELECTED_VIDEO_NAME = '';
            } else {
                title.innerHTML = `<i class="fa-solid fa-pen-to-square"></i> Hiệu chỉnh bài học`;
                submitBtn.innerText = 'Lưu';
                SELECTED_EDIT_INDEX = index;
                
                const dataItem = LESSON_DATA_STATE[index];
                nameInput.value = dataItem.ten;
                TEMPORARY_SELECTED_VIDEO_NAME = dataItem.video;
                videoLabel.innerText = `File đang chọn: ${dataItem.video}`;
            }

            modal.classList.add('active');
        }

        function closeLessonModal() {
            document.getElementById('lessonModalComponent').classList.remove('active');
        }

        function handleModalVideoSelect(input) {
            if (input.files && input.files[0]) {
                TEMPORARY_SELECTED_VIDEO_NAME = input.files[0].name;
                window.currentPreviewVideo = URL.createObjectURL(input.files[0]);
                document.getElementById('modal_video_current_label').innerText = `File vừa chọn: ${TEMPORARY_SELECTED_VIDEO_NAME}`;
            }
        }

        // Lưu hoặc cập nhật mảng State từ Modal (Đã bổ sung tính năng kiểm tra trùng lặp)
        function saveModalStateData() {
            const nameVal = document.getElementById('modal_lesson_name').value.trim();
            
            if (!nameVal) {
                alert('Vui lòng nhập tên bài học!');
                return;
            }
            if (!TEMPORARY_SELECTED_VIDEO_NAME) {
                alert('Vui lòng chọn hoặc tải lên một file video bài học!');
                return;
            }

            // --- ĐOẠN KIỂM TRA TRÙNG LẶP DỮ LIỆU TRONG DANH SÁCH TẠM THỜI ---
            for (let i = 0; i < LESSON_DATA_STATE.length; i++) {
                // Nếu đang ở chế độ chỉnh sửa (EDIT), bỏ qua không kiểm tra phần tử hiện tại của chính nó
                if (MODAL_MODE === 'EDIT' && i === SELECTED_EDIT_INDEX) {
                    continue;
                }

                // Kiểm tra trùng tên bài học (Không phân biệt chữ hoa, chữ thường)
                if (LESSON_DATA_STATE[i].ten.toLowerCase() === nameVal.toLowerCase()) {
                    alert(`Tên bài học "${nameVal}" đã tồn tại!`);
                    return;
                }

                // Kiểm tra trùng File video bài học
                if (LESSON_DATA_STATE[i].video.toLowerCase() === TEMPORARY_SELECTED_VIDEO_NAME.toLowerCase()) {
                    alert(`Video "${TEMPORARY_SELECTED_VIDEO_NAME}" đã được gán cho một bài học khác!`);
                    return;
                }
            }
            // ----------------------------------------------------------------

            if (MODAL_MODE === 'ADD') {
                LESSON_DATA_STATE.push({
                    ten: nameVal,
                    video: TEMPORARY_SELECTED_VIDEO_NAME,
                    preview: window.currentPreviewVideo
                });
                // Nhảy đến trang cuối cùng nếu phần tử mới vượt trang hiện tại
                CURRENT_PAGE = Math.ceil(LESSON_DATA_STATE.length / ITEMS_PER_PAGE) || 1;
            } else {
                LESSON_DATA_STATE[SELECTED_EDIT_INDEX] = {
                    ten: nameVal,
                    video: TEMPORARY_SELECTED_VIDEO_NAME
                };
            }

            saveStateToCache();
            closeLessonModal();
            renderLessonsStateEngine();
        }

        // Xóa phần tử khỏi danh sách soạn thảo tạm thời
        function deleteLessonItem(index) {
            if (confirm('Bạn chắc chắn muốn xóa bài học này khỏi danh sách đang soạn thảo?')) {
                LESSON_DATA_STATE.splice(index, 1);
                
                // Điều tiết lại chỉ số trang hiện tại nếu xóa phần tử duy nhất của trang cuối
                const maxPage = Math.ceil(LESSON_DATA_STATE.length / ITEMS_PER_PAGE) || 1;
                if (CURRENT_PAGE > maxPage) {
                    CURRENT_PAGE = maxPage;
                }
                
                saveStateToCache();
                renderLessonsStateEngine();
            }
        }

        function saveStateToCache() {
            localStorage.setItem('devmaster_temp_lessons', JSON.stringify(LESSON_DATA_STATE));
        }

        // Hàm quay lại trang cấu hình Khóa Học nhưng vẫn giữ nguyên dữ liệu
        function navigateBackToCourse() {
            window.location.href = 'ThemKhoaHoc.php?status=retrieved';
        }

        // Đẩy dữ liệu JSON qua Form ẩn để PHP thực thi đồng bộ lưu DB
        // Hàm đồng bộ tối ưu hóa dữ liệu khóa học và bài học để gửi lên PHP xử lý
        function triggerFinalDatabaseSubmit() {
            // Kiểm tra trạng thái dữ liệu bài học tạm thời
            if (!LESSON_DATA_STATE || LESSON_DATA_STATE.length === 0) {
                alert("Danh sách bài học trống! Vui lòng thêm ít nhất một bài học trước khi tiến hành lưu dữ liệu.");
                return;
            }
            
            const urlParams = new URLSearchParams(window.location.search);
            const targetHiddenInput = document.getElementById('target_khoahoc_id');
            
            if (urlParams.get('from') === 'QLKH') {
                // Trường hợp đi từ Quản lý khóa học: ưu tiên lấy ID từ input hidden hoặc datalist
                let currentCourseId = 0;
                if (targetHiddenInput && parseInt(targetHiddenInput.value) > 0) {
                    currentCourseId = parseInt(targetHiddenInput.value);
                } else {
                    // Dự phòng quét nhanh thẻ datalist để bóc tách ID
                    const searchInput = document.getElementById('qlkh_course_search_input');
                    const datalist = document.getElementById('qlkh_courses_datalist');
                    if (searchInput && datalist) {
                        const options = datalist.querySelectorAll('option');
                        options.forEach(opt => {
                            if (opt.value === searchInput.value.trim()) {
                                currentCourseId = parseInt(opt.getAttribute('data-id'));
                            }
                        });
                    }
                }

                if (currentCourseId > 0) {
                    document.getElementById('final_target_khoahoc_id').value = currentCourseId;
                } else {
                    alert("Hệ thống không tìm thấy khóa học mục tiêu tương ứng. Vui lòng chọn lại khóa học từ danh sách thả xuống!");
                    return;
                }
            } else {
                // Luồng tạo mới: Đặt ID bằng 0 để PHP tự động bóc tách chuỗi JSON tạo cấu trúc Danh mục/Nhóm/Khóa học mới
                document.getElementById('final_target_khoahoc_id').value = 0;
                const tempCourseForm = localStorage.getItem('devmaster_temp_course_form');
                if (tempCourseForm) {
                    document.getElementById('final_course_meta_data_json').value = tempCourseForm;
                }
            }
            
            // Đóng gói mảng dữ liệu bài học chuyển thành chuỗi JSON gán vào form ẩn
            document.getElementById('final_lessons_data_json').value = JSON.stringify(LESSON_DATA_STATE);
            
            // Kích hoạt submit form gửi toàn bộ thông tin lên máy chủ PHP
            document.getElementById('final-master-submit-form').submit();
        }
        // ENGINE RENDER GIAO DIỆN CHÍNH XÁC THEO YÊU CẦU ĐỀ RA
        function renderLessonsStateEngine() {
            const grid = document.getElementById('lessonsWorkspaceGrid');
            grid.innerHTML = '';

            if (LESSON_DATA_STATE.length === 0) {
                grid.innerHTML = `
                    <div class="empty-state-container">
                        <i class="fa-solid fa-photo-film"></i>
                        <p>Chưa có bài học nào được khởi tạo trong cấu trúc này. Hãy bấm <b>"Thêm bài học mới"</b> để bắt đầu!</p>
                    </div>`;
                document.getElementById('internalPaginationBar').innerHTML = '';
                return;
            }

            // Tính toán chỉ số phân trang
            const startIndex = (CURRENT_PAGE - 1) * ITEMS_PER_PAGE;
            const endIndex = Math.min(startIndex + ITEMS_PER_PAGE, LESSON_DATA_STATE.length);
            const paginatedItems = LESSON_DATA_STATE.slice(startIndex, endIndex);

            // Duyệt mảng render cấu trúc UI
            paginatedItems.forEach((item, relativeIndex) => {
                const absoluteIndex = startIndex + relativeIndex;
                
                const cardNode = document.createElement('div');
                cardNode.className = 'lesson-workspace-card';
                cardNode.innerHTML = `
                    <div class="video-preview-block">
                        <video
                            src="${item.preview || ''}#t=0.5"
                            preload="metadata"
                            muted
                            playsinline>
                        </video>
                    </div>
                    <div class="lesson-details-block">
                        <h3 class="lesson-meta-title">${absoluteIndex + 1}. ${item.ten}</h3>
                        <div class="lesson-meta-filename">
                            <i class="fa-solid fa-video" style="color:#6366f1;"></i> ${item.video}
                        </div>
                    </div>
                    <div class="lesson-actions-sidebar">
                        <button type="button" class="btn-mini-control btn-mini-delete" title="Xóa bài học" onclick="deleteLessonItem(${absoluteIndex})">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                        <button type="button" class="btn-mini-control btn-mini-edit" title="Chỉnh sửa thông tin" onclick="openLessonModal('EDIT', ${absoluteIndex})">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                    </div>
                `;
                grid.appendChild(cardNode);
            });

            renderPaginationControls();
        }

        // Render thanh phân trang nội bộ tương tự VaoHocNgay
        function renderPaginationControls() {
            const paginationBar = document.getElementById('internalPaginationBar');
            paginationBar.innerHTML = '';

            const totalPages = Math.ceil(LESSON_DATA_STATE.length / ITEMS_PER_PAGE);
            if (totalPages <= 1) return;

            // Nút Previous
            const prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.className = `pagination-btn ${CURRENT_PAGE === 1 ? 'disabled' : ''}`;
            prevBtn.innerHTML = `<i class="fa-solid fa-chevron-left"></i>`;
            prevBtn.onclick = () => { if(CURRENT_PAGE > 1) { CURRENT_PAGE--; renderLessonsStateEngine(); } };
            paginationBar.appendChild(prevBtn);

            // Các nút số trang
            for (let i = 1; i <= totalPages; i++) {
                const numBtn = document.createElement('button');
                numBtn.type = 'button';
                numBtn.className = `pagination-btn ${CURRENT_PAGE === i ? 'active' : ''}`;
                numBtn.innerText = i;
                numBtn.onclick = () => { CURRENT_PAGE = i; renderLessonsStateEngine(); };
                paginationBar.appendChild(numBtn);
            }

            // Nút Next
            const nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = `pagination-btn ${CURRENT_PAGE === totalPages ? 'disabled' : ''}`;
            nextBtn.innerHTML = `<i class="fa-solid fa-chevron-right"></i>`;
            nextBtn.onclick = () => { if(CURRENT_PAGE < totalPages) { CURRENT_PAGE++; renderLessonsStateEngine(); } };
            paginationBar.appendChild(nextBtn);
        }

        // Hàm hủy bỏ, giải phóng bộ nhớ (terminate hoàn toàn) theo yêu cầu hệ thống
        function terminateAllProcesses() {
            // Lấy ID khóa học cha hiện tại đang được thêm bài học
            const currentCourseId = document.getElementById('SelectKhoaHocChaId') ? document.getElementById('SelectKhoaHocChaId').value : null; 
            
            if (currentCourseId) {
                // Lưu trạng thái mở cho Khóa học
                let expandKh = {};
                expandKh[currentCourseId] = true;
                localStorage.setItem("remember_expandKh", JSON.stringify(expandKh));

                // Lấy thông tin Nhóm cha và Danh mục cha từ thẻ Option được chọn (nếu có cấu hình data-attributes)
                const selectBox = document.getElementById('SelectKhoaHocChaId');
                const selectedOption = selectBox ? selectBox.options[selectBox.selectedIndex] : null;
                
                if (selectedOption) {
                    const nhomId = selectedOption.getAttribute('data-parent-nhom');
                    const dmId = selectedOption.getAttribute('data-parent-dm');
                    
                    // --- ĐOẠN CODE MỚI ---
                    if (dmId) {
                        let expandDM = JSON.parse(localStorage.getItem("remember_expandDM") || "{}");
                        expandDM[dmId] = true;
                        localStorage.setItem("remember_expandDM", JSON.stringify(expandDM));
                    }
                    if (nhomId) {
                        let expandNhom = JSON.parse(localStorage.getItem("remember_expandNhom") || "{}");
                        expandNhom[nhomId] = true;
                        localStorage.setItem("remember_expandNhom", JSON.stringify(expandNhom));
                    }
                    // Bổ sung ghi nhớ khóa học cha để tự động mở bung tầng khóa học khi quay lại
                    if (currentCourseId) {
                        let expandKh = JSON.parse(localStorage.getItem("remember_expandKh") || "{}");
                        expandKh[currentCourseId] = true;
                        localStorage.setItem("remember_expandKh", JSON.stringify(expandKh));
                    }
                }

                // Đẩy phân trang bài học của khóa học này sang trang đầu tiên (do ORDER BY BaiHocId DESC)
                let subBhPage = JSON.parse(localStorage.getItem("remember_subBhPage") || "{}");
                subBhPage[currentCourseId] = 1; 
                localStorage.setItem("remember_subBhPage", JSON.stringify(subBhPage));
            }

            localStorage.removeItem('devmaster_temp_lessons');
            window.location.href = 'QuanLyKhoaHoc.php?status=success'; 
        }
    </script>
</body>
</html>
