<?php
// Admin/QuanLyKhoaHoc.php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
include '../Database.php';

$db = isset($connect) ? $connect : $conn;

// Khóa bảo mật tối cao
if (!isset($_SESSION['IsAdmin']) || $_SESSION['IsAdmin'] !== true) {
    header("Location: /DevMaster/Pages/Login.php");
    exit;
}

// ==========================================================================
// BỘ NÃO AJAX XỬ LÝ DỮ LIỆU EDIT (FETCH & UPDATE) CHO MODAL
// ==========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];
    
    // Hàm Helper để query an toàn (Hỗ trợ cả PDO và MySQLi)
    function executeQuery($db, $sql, $params = []) {
        if ($db instanceof PDO) {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $db->prepare($sql);
            if (!empty($params)) {
                $types = str_repeat('s', count($params)); 
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $data = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) { $data[] = $row; }
            }
            return $data;
        }
    }

    // 1. FETCH DATA: Lấy thông tin để đổ vào Modal
    if ($action === 'fetch_edit') {
        $level = $_POST['level'];
        $id = intval($_POST['id']);
        $response = ['status' => 'success', 'data' => []];

        try {
            if ($level === 'danhmuc') {
                $response['data'] = executeQuery($db, "SELECT DanhMucId as id, TenDanhMuc as name FROM danhmuc WHERE DanhMucId = ?", [$id])[0];
            } 
            elseif ($level === 'nhom') {
                $response['data'] = executeQuery($db, "SELECT NhomKhoaHocId as id, TenNhom as name, DanhMucId as parent_id FROM nhomkhoahoc WHERE NhomKhoaHocId = ?", [$id])[0];
                // Lấy danh sách Category cho Dropdown
                $response['categories'] = executeQuery($db, "SELECT DanhMucId as id, TenDanhMuc as name FROM danhmuc ORDER BY TenDanhMuc ASC");
            } 
            elseif ($level === 'khoahoc') {
                $response['data'] = executeQuery($db, "SELECT KhoaHocId as id, Ten as name, TenGiangVien, Gia, NhomKhoaHocId as parent_id, Anh FROM khoahoc WHERE KhoaHocId = ?", [$id])[0];
                // Lấy danh sách Nhóm (Kèm theo Danh mục cha để làm Optical Grouping)
                $groupsRaw = executeQuery($db, "SELECT n.NhomKhoaHocId, n.TenNhom, d.DanhMucId, d.TenDanhMuc FROM nhomkhoahoc n JOIN danhmuc d ON n.DanhMucId = d.DanhMucId ORDER BY d.TenDanhMuc ASC, n.TenNhom ASC");
                $grouped = [];
                foreach ($groupsRaw as $g) {
                    if (!isset($grouped[$g['DanhMucId']])) {
                        $grouped[$g['DanhMucId']] = ['cat_name' => $g['TenDanhMuc'], 'groups' => []];
                    }
                    $grouped[$g['DanhMucId']]['groups'][] = ['id' => $g['NhomKhoaHocId'], 'name' => $g['TenNhom']];
                }
                $response['grouped_options'] = array_values($grouped);
            }
            // Thêm vào trong khối: if ($action === 'fetch_edit') { ... }
            elseif ($level === 'baihoc') {
                $response['data'] = executeQuery($db, "SELECT BaiHocId as id, Ten as name, LinkVideo, KhoaHocId as parent_id FROM baihoc WHERE BaiHocId = ?", [$id])[0];
                
                // Lấy toàn bộ danh sách Khóa học phục vụ việc đổi Khóa học cha cho bài học (Phân cụm theo Nhóm để tăng độ trải nghiệm người dùng)
                $coursesRaw = executeQuery($db, "SELECT k.KhoaHocId, k.Ten as TenKhoaHoc, n.TenNhom FROM khoahoc k JOIN nhomkhoahoc n ON k.NhomKhoaHocId = n.NhomKhoaHocId ORDER BY n.TenNhom ASC, k.Ten ASC");
                $grouped = [];
                foreach ($coursesRaw as $c) {
                    if (!isset($grouped[$c['TenNhom']])) {
                        $grouped[$c['TenNhom']] = ['group_name' => $c['TenNhom'], 'courses' => []];
                    }
                    $grouped[$c['TenNhom']]['courses'][] = ['id' => $c['KhoaHocId'], 'name' => $c['TenKhoaHoc']];
                }
                $response['grouped_courses'] = array_values($grouped);
            }
            echo json_encode($response);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Lỗi truy xuất dữ liệu: ' . $e->getMessage()]);
        }
        exit;
    }

    // 2. SAVE DATA: Kiểm tra trùng lặp và Cập nhật
    if ($action === 'save_edit') {
        $level = $_POST['level'];
        $id = intval($_POST['id']);
        $newName = trim($_POST['name']);
        
        try {
            // Kiểm tra trùng lặp Tên (Tuyệt đối không được trùng với ID khác)
            $isDuplicate = false;
            if ($level === 'danhmuc') {
                $check = executeQuery($db, "SELECT COUNT(*) as count FROM danhmuc WHERE TenDanhMuc = ? AND DanhMucId != ?", [$newName, $id]);
                if ($check[0]['count'] > 0) $isDuplicate = true;
            } elseif ($level === 'nhom') {
                $check = executeQuery($db, "SELECT COUNT(*) as count FROM nhomkhoahoc WHERE TenNhom = ? AND NhomKhoaHocId != ?", [$newName, $id]);
                if ($check[0]['count'] > 0) $isDuplicate = true;
            } elseif ($level === 'khoahoc') {
                $check = executeQuery($db, "SELECT COUNT(*) as count FROM khoahoc WHERE Ten = ? AND KhoaHocId != ?", [$newName, $id]);
                if ($check[0]['count'] > 0) $isDuplicate = true;
            }
            // Thêm vào trong khối check trùng của: if ($action === 'save_edit') { ... }
            elseif ($level === 'baihoc') {
                $check = executeQuery($db, "SELECT COUNT(*) as count FROM baihoc WHERE Ten = ? AND BaiHocId != ?", [$newName, $id]);
                if ($check[0]['count'] > 0) $isDuplicate = true;
            }

            if ($isDuplicate) {
                echo json_encode(['status' => 'error', 'message' => 'Tên này đã tồn tại trong hệ thống. Vui lòng chọn tên khác!']);
                exit;
            }

            // Thực thi Update
            if ($level === 'danhmuc') {
                executeQuery($db, "UPDATE danhmuc SET TenDanhMuc = ? WHERE DanhMucId = ?", [$newName, $id]);
            } elseif ($level === 'nhom') {
                $parentId = intval($_POST['parent_id']);
                executeQuery($db, "UPDATE nhomkhoahoc SET TenNhom = ?, DanhMucId = ? WHERE NhomKhoaHocId = ?", [$newName, $parentId, $id]);
            } elseif ($level === 'khoahoc') {
                $parentId = intval($_POST['parent_id']);
                $gv = trim($_POST['giangvien']);
                $gia = floatval($_POST['gia']);
                // Ghi chú: Ảnh làm phức tạp form AJAX nên mặc định giữ nguyên hoặc làm upload riêng. Ở đây cập nhật text trước.
                executeQuery($db, "UPDATE khoahoc SET Ten = ?, TenGiangVien = ?, Gia = ?, NhomKhoaHocId = ? WHERE KhoaHocId = ?", [$newName, $gv, $gia, $parentId, $id]);
            } else if ($level === 'baihoc') {
                $parentId = intval($_POST['parent_id']);

                // Mặc định giữ lại link cũ nếu người dùng không chọn file mới.
                $video = '';
                if (isset($_POST['video_link'])) {
                    $video = trim($_POST['video_link']);
                } elseif (isset($_POST['LinkVideo'])) {
                    $video = trim($_POST['LinkVideo']);
                } elseif (isset($_POST['video'])) {
                    $video = trim($_POST['video']);
                }

                // Nếu có file video mới thì upload và ghi đè link video cũ.
                if (isset($_FILES['video_file']) && is_array($_FILES['video_file']) && $_FILES['video_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception('Không thể tải video lên. Mã lỗi: ' . $_FILES['video_file']['error']);
                    }

                    $allowedExt = ['mp4', 'webm', 'ogg', 'mov', 'm4v'];
                    $allowedMime = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-m4v', 'application/octet-stream'];

                    $originalName = $_FILES['video_file']['name'] ?? '';
                    $tmpName = $_FILES['video_file']['tmp_name'] ?? '';
                    $size = intval($_FILES['video_file']['size'] ?? 0);
                    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    $mime = $_FILES['video_file']['type'] ?? '';

                    if (!in_array($ext, $allowedExt, true)) {
                        throw new Exception('Định dạng video không hợp lệ. Chỉ chấp nhận: mp4, webm, ogg, mov, m4v.');
                    }
                    if (!in_array($mime, $allowedMime, true) && strpos($mime, 'video/') !== 0) {
                        throw new Exception('File tải lên không phải video hợp lệ.');
                    }

                    if ($size > 500 * 1024 * 1024) {
                        throw new Exception('Video quá lớn. Giới hạn tối đa là 500MB.');
                    }

                    $uploadDir = dirname(__DIR__) . '/Uploads/LessonVideos/';
                    if (!is_dir($uploadDir)) {
                        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                            throw new Exception('Không thể tạo thư mục lưu video.');
                        }
                    }

                    $safeFileName = 'lesson_' . $id . '_' . time() . '.' . $ext;
                    $destPath = $uploadDir . $safeFileName;

                    if (!move_uploaded_file($tmpName, $destPath)) {
                        throw new Exception('Không thể lưu file video lên máy chủ.');
                    }

                    $video = '/DevMaster/Uploads/LessonVideos/' . $safeFileName;
                }

                if ($video === '') {
                    throw new Exception('Link video không được để trống.');
                }

                executeQuery($db, "UPDATE baihoc SET Ten = ?, LinkVideo = ?, KhoaHocId = ? WHERE BaiHocId = ?", [$newName, $video, $parentId, $id]);
            }

            if ($level === 'khoahoc' || $level === 'nhom' || $level === 'baihoc') {
                $customMsg = 'Đã di chuyển và cập nhật cấu trúc thành công!';
            }
            echo json_encode(['status' => 'success', 'message' => $customMsg]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
        }
        exit;
    }
}

// ==========================================================================
// HỆ THỐNG THÔNG BÁO THÔNG MINH (CHỐNG LẶP LẠI THÔNG BÁO XÓA)
// ==========================================================================
$systemMessage = "";

// ƯU TIÊN 1: Nếu có tham số báo CẬP NHẬT THÀNH CÔNG, hiển thị cập nhật trước!
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        $systemMessage =
            "<div class='alert-box success'>
                <i class='fa-solid fa-circle-check'></i>
                Cập nhật dữ liệu thành công!
            </div>";
    } elseif ($_GET['status'] === 'delete_success') {
        $systemMessage =
            "<div class='alert-box success'>
                <i class='fa-solid fa-circle-check'></i>
                Đã xóa dữ liệu thành công vĩnh viễn!
            </div>";
    }
}
// ƯU TIÊN 2: CHỈ xử lý XÓA khi thực sự có lệnh delete VÀ không có cờ ẩn đi
elseif (
    isset($_GET['action']) &&
    $_GET['action'] === 'delete' &&
    isset($_GET['level']) &&
    isset($_GET['id']) &&
    !isset($_GET['status']) // Thêm điều kiện này để chặn bộ lọc cũ
) {
    $level = $_GET['level'];
    $idToDelete = intval($_GET['id']);

    try {
        $msg = "Đã xóa dữ liệu thành công!";
        $sqlDelete = "";
        
        if ($level === 'danhmuc') { 
            $msg = "Đã xóa vĩnh viễn Danh mục!"; 
            $sqlDelete = "DELETE FROM danhmuc WHERE DanhMucId = ?";
        }
        elseif ($level === 'nhom') { 
            $msg = "Đã xóa vĩnh viễn Nhóm khóa học!"; 
            $sqlDelete = "DELETE FROM nhomkhoahoc WHERE NhomKhoaHocId = ?";
        }
        elseif ($level === 'khoahoc') { 
            $msg = "Đã xóa vĩnh viễn Khóa học!"; 
            $sqlDelete = "DELETE FROM khoahoc WHERE KhoaHocId = ?";
        }
        elseif ($level === 'baihoc') { 
            $msg = "Đã xóa vĩnh viễn Bài học!"; 
            $sqlDelete = "DELETE FROM baihoc WHERE BaiHocId = ?";
        }

        if (!empty($sqlDelete)) {
            $parentKhId = 0;
            // Nếu xóa bài học, hãy tìm Khóa học cha của nó trước khi nó bị xóa mất khỏi DB
            if ($level === 'baihoc') {
                $findParentSql = "SELECT KhoaHocId FROM baihoc WHERE BaiHocId = ?";
                if ($db instanceof PDO) {
                    $stKh = $db->prepare($findParentSql); $stKh->execute([$idToDelete]);
                    $rKh = $stKh->fetch(PDO::FETCH_ASSOC);
                    if ($rKh) $parentKhId = $rKh['KhoaHocId'];
                } else {
                    $stKh = $db->prepare($findParentSql); $stKh->bind_param("i", $idToDelete); $stKh->execute();
                    $resKh = $stKh->get_result();
                    if ($rKh = $resKh->fetch_assoc()) $parentKhId = $rKh['KhoaHocId'];
                }
            }

            // Thực thi truy vấn xóa an toàn
            if ($db instanceof PDO) {
                $stmt = $db->prepare($sqlDelete);
                $stmt->execute([$idToDelete]);
            } else {
                $stmt = $db->prepare($sqlDelete);
                $stmt->bind_param("i", $idToDelete);
                $stmt->execute();
            }
            
            // Điều hướng kèm theo thông tin khóa học cha để JS bên dưới bắt được trạng thái mở rộng
            if ($level === 'baihoc' && $parentKhId > 0) {
                header("Location: QuanLyKhoaHoc.php?status=delete_success&retain_kh=" . $parentKhId);
            } else {
                header("Location: QuanLyKhoaHoc.php?status=delete_success");
            }
            exit;
        }
    } catch (Exception $e) {
        $systemMessage = "<div class='alert-box error'><i class='fa-solid fa-circle-exclamation'></i> Lỗi hệ thống: " . $e->getMessage() . "</div>";
    }
}


// ==========================================================================
// TRUY VẤN TẢI TOÀN BỘ CẤU TRÚC ĐA TẦNG (Cập nhật Tầng 4: Bài học)
// ==========================================================================
$treeData = [];
$totalCoursesCount = 0;
$totalLessonsCount = 0; // Thêm biến đếm tổng số bài học ngoài Dashboard

try {
    $dmQuery = "SELECT DanhMucId, TenDanhMuc FROM danhmuc ORDER BY DanhMucId DESC";
    $dmRows = [];
    if ($db instanceof PDO) { $dmRows = $db->query($dmQuery)->fetchAll(PDO::FETCH_ASSOC); } 
    else { $res = $db->query($dmQuery); while($r = $res->fetch_assoc()) { $dmRows[] = $r; } }
    
    $totalDanhMucCount = count($dmRows); 
    $totalNhomCount = 0;
    
    foreach ($dmRows as $dm) {
        $dmId = $dm['DanhMucId'];
        $nhomQuery = "SELECT NhomKhoaHocId, TenNhom FROM nhomkhoahoc WHERE DanhMucId = ? ORDER BY NhomKhoaHocId DESC";
        $nhomRows = [];
        if ($db instanceof PDO) { $st = $db->prepare($nhomQuery); $st->execute([$dmId]); $nhomRows = $st->fetchAll(PDO::FETCH_ASSOC); } 
        else { $st = $db->prepare($nhomQuery); $st->bind_param("i", $dmId); $st->execute(); $res = $st->get_result(); while($r = $res->fetch_assoc()) { $nhomRows[] = $r; } }

        $processedNhoms = [];
        foreach ($nhomRows as $nhom) {
            $nhomId = $nhom['NhomKhoaHocId'];
            $khQuery = "SELECT KhoaHocId, Ten, Anh, Gia, TenGiangVien FROM khoahoc WHERE NhomKhoaHocId = ? ORDER BY KhoaHocId DESC";
            $khRows = [];
            if ($db instanceof PDO) { $st = $db->prepare($khQuery); $st->execute([$nhomId]); $khRows = $st->fetchAll(PDO::FETCH_ASSOC); } 
            else { $st = $db->prepare($khQuery); $st->bind_param("i", $nhomId); $st->execute(); $res = $st->get_result(); while($r = $res->fetch_assoc()) { $khRows[] = $r; } }
            
            $totalNhomCount += 1; 
            $totalCoursesCount += count($khRows);
            
            $processedKhoas = [];
            foreach ($khRows as $kh) {
                $khId = $kh['KhoaHocId'];
                // Truy vấn lấy danh sách Bài học thuộc Khóa học
                $bhQuery = "SELECT BaiHocId, Ten, LinkVideo FROM baihoc WHERE KhoaHocId = ? ORDER BY BaiHocId DESC";
                $bhRows = [];
                if ($db instanceof PDO) { $st = $db->prepare($bhQuery); $st->execute([$khId]); $bhRows = $st->fetchAll(PDO::FETCH_ASSOC); } 
                else { $st = $db->prepare($bhQuery); $st->bind_param("i", $khId); $st->execute(); $res = $st->get_result(); while($r = $res->fetch_assoc()) { $bhRows[] = $r; } }
                
                $totalLessonsCount += count($bhRows);
                $kh['BaiHocs'] = $bhRows; // Đóng gói mảng bài học vào khóa học cha
                $processedKhoas[] = $kh;
            }
            
            $processedNhoms[] = ['NhomKhoaHocId' => $nhomId, 'TenNhom' => $nhom['TenNhom'], 'KhoaHocs' => $processedKhoas];
        }
        $treeData[] = ['DanhMucId' => $dmId, 'TenDanhMuc' => $dm['TenDanhMuc'], 'Nhoms' => $processedNhoms];
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản Lý Khóa Học Đa Tầng - DevMaster</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* GIỮ NGUYÊN CSS GỐC CỦA BẠN */
        :root { --sidebar-width: 260px; --admin-primary: #4f46e5; --admin-bg: #f8fafc; --admin-card: #ffffff; --admin-text: #1e293b; --admin-muted: #64748b; }
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--admin-bg); color: var(--admin-text); display: flex; }
        .admin-sidebar { width: var(--sidebar-width); height: 100vh; background: #0f172a; color: #ffffff; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; justify-content: space-between; padding: 24px 16px; box-sizing: border-box; z-index: 100; }
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
        .btn-back:hover { background: #475569; }
        .admin-main-content { margin-left: var(--sidebar-width); flex-grow: 1; padding: 40px; box-sizing: border-box; }
        .dash-header { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start; }
        .dash-header h1 { font-size: 28px; font-weight: 800; margin: 0 0 10px 0; }
        .header-meta-box { display: flex; align-items: center; gap: 12px; color: #1e293b; font-size: 16px; font-weight: 600; margin: 0; }
        .total-badge-count { background: #e0e7ff; color: #4338ca; padding: 6px 16px; border-radius: 9999px; font-weight: 800; font-size: 16px; box-shadow: 0 2px 4px rgba(67, 56, 202, 0.1); }
        .badge-danhmuc { background: #ffe4e6 !important; color: #9f1239 !important; }
        .badge-nhom { background: #e0f2fe !important; color: #0369a1 !important; }
        .badge-khoahoc { background: #dcfce7 !important; color: #15803d !important; }
        /* Thêm vào trong thẻ <style> cùng cụm với .badge-khoahoc */
        .badge-baihoc { 
            background: #e0f2fe !important; 
            color: #0369a1 !important; 
            border: 1px solid #bde4ff;
        }
        .right-control-panel { display: flex; flex-direction: column; align-items: flex-end; gap: 12px; }
        .search-container { position: relative; }
        .search-container input { padding: 11px 16px 11px 40px; border-radius: 8px; border: 1px solid #e2e8f0; outline: none; font-size: 14px; width: 280px; transition: all 0.3s; background: #ffffff; }
        .search-container input:focus { border-color: var(--admin-primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        .search-container i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--admin-muted); }
        .btn-add-course-master { display: inline-flex; align-items: center; gap: 8px; background: #10b981; color: #ffffff; text-decoration: none; padding: 10px 18px; border-radius: 8px; font-size: 14px; font-weight: 700; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2); transition: all 0.2s; }
        .btn-add-course-master:hover { background: #059669; transform: translateY(-1px); }
        .table-wrapper { background: var(--admin-card); border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow: hidden; margin-top: 10px; }
        .student-enterprise-table { width: 100%; border-collapse: collapse; }
        .student-enterprise-table th { background: #f8fafc; color: #475569; font-weight: 700; font-size: 14px; padding: 16px 20px; border-bottom: 2px solid #e2e8f0; text-align: center; }
        .student-enterprise-table td { padding: 14px 24px; border-bottom: 1px solid #f1f5f9; color: #1e293b; font-size: 15px; vertical-align: middle; }
        .cell-center { text-align: center; } .cell-left { text-align: left; }
        .tree-row-danhmuc { background: #f1f5f9; font-weight: 700; cursor: pointer; }
        .tree-row-danhmuc:hover { background: #e2e8f0; }
        .tree-node-click-area { display: flex; align-items: center; gap: 12px; font-size: 16px; color: #0f172a; }
        .tree-toggle-icon { width: 16px; color: var(--admin-primary); transition: transform 0.2s; }
        .tree-row-nhom { background: #ffffff; font-weight: 600; cursor: pointer; display: none; }
        .tree-row-nhom:hover { background: #f8fafc; }
        .tree-node-nhom-area { display: flex; align-items: center; gap: 12px; padding-left: 25px; font-size: 15px; color: #334155; }
        .tree-toggle-sub-icon { width: 14px; color: #0284c7; transition: transform 0.2s; }
        .tree-row-khoahoc { background: #ffffff; display: none; }
        .tree-row-khoahoc:hover td { background: #fafafa; }
        .tree-row-baihoc { background: #fafbfc; }
        .tree-row-baihoc:hover td { background: #f1f5f9; }
        .btn-sub-page.active-bh { background: #10b981; color: white; border-color: #10b981; }
        .course-master-profile { display: flex; align-items: center; gap: 20px; padding-left: 55px; }
        .course-preview-image { width: 130px; height: 75px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0; flex-shrink: 0; }
        .course-info-text-block { display: flex; flex-direction: column; gap: 6px; }
        .course-title-main { font-size: 16px; font-weight: 700; color: #0f172a; line-height: 1.4; }
        .course-author-sub { font-size: 14px; color: var(--admin-muted); font-weight: 500; }
        .course-price-tag { font-size: 17px; font-weight: 800; color: #0056b3; }
        .node-expanded { transform: rotate(90deg); }
        .action-container { display: flex; gap: 8px; justify-content: center; align-items: center; }
        .action-icon-btn { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; text-decoration: none; border: none; cursor: pointer; transition: 0.2s; outline: none;}
        .btn-info-style { background: rgba(79, 70, 229, 0.1); color: var(--admin-primary); }
        .btn-info-style:hover { background: var(--admin-primary); color: white; }
        .btn-trash-style { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .btn-trash-style:hover { background: #ef4444; color: white; }
        .pagination-container-wrapper { display: flex; justify-content: center; align-items: center; margin-top: 24px; gap: 6px; user-select: none; }
        .pagination-nav-btn { min-width: 36px; height: 36px; padding: 0 6px; border-radius: 8px; border: 1px solid #e2e8f0; background: #ffffff; color: #475569; font-size: 13px; font-weight: 600; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease; }
        .pagination-nav-btn:hover, .pagination-nav-btn.active-page-node { background: var(--admin-primary); color: #ffffff; border-color: var(--admin-primary); }
        .pagination-dots-ellipsis { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; color: var(--admin-muted); font-size: 13px; }
        .sub-pagination-row { display: none; background: #fdfdfd; }
        .sub-pagination-container { display: flex; align-items: center; justify-content: flex-start; gap: 4px; padding: 8px 24px 8px 55px !important; }
        .sub-pagination-kh-container { padding: 8px 24px 8px 85px !important; }
        .btn-sub-page { min-width: 28px; height: 28px; font-size: 12px; border-radius: 6px; border: 1px solid #e2e8f0; background: white; cursor: pointer; }
        .btn-sub-page.active { background: #0284c7; color: white; border-color: #0284c7; }
        .btn-sub-page.active-kh { background: #4f46e5; color: white; border-color: #4f46e5; }
        .alert-box { padding: 14px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .alert-box.success { background: #ecfdf5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-box.error { background: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; }

        /* ==========================================================================
           CSS MỚI: BẢNG EDIT (MODAL) CÔNG THÁI HỌC VÀ COMBOBOX SEARCH
           ========================================================================== */
        .edit-modal-overlay {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px);
            z-index: 1000; display: flex; justify-content: center; align-items: center;
            opacity: 0; visibility: hidden; transition: all 0.3s ease;
        }
        .edit-modal-overlay.active { opacity: 1; visibility: visible; }
        
        .edit-modal-box {
            background: #ffffff; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            width: 100%; max-width: 400px; /* Size linh hoạt đổi bằng JS */
            transform: scale(0.95) translateY(20px); transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex; flex-direction: column; overflow: hidden;
        }
        .edit-modal-overlay.active .edit-modal-box { transform: scale(1) translateY(0); }

        .modal-header { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
        .modal-header h2 { margin: 0; font-size: 18px; font-weight: 700; color: #0f172a; }
        .modal-close-btn { background: none; border: none; color: #64748b; font-size: 20px; cursor: pointer; transition: 0.2s; }
        .modal-close-btn:hover { color: #ef4444; transform: rotate(90deg); }

        .modal-body { padding: 24px; display: flex; flex-direction: column; gap: 16px; max-height: 70vh; overflow-y: auto; }
        
        .form-group { display: flex; flex-direction: column; gap: 8px; position: relative; }
        .form-group label { font-size: 14px; font-weight: 600; color: #334155; }
        .form-group input.std-input { 
            padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 15px; 
            outline: none; transition: 0.2s; font-family: inherit; color: #1e293b;
        }
        .form-group input.std-input:focus { border-color: var(--admin-primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15); }
        .form-group input.std-input.error { border-color: #ef4444; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15); }

        .error-msg-box { color: #ef4444; font-size: 13px; font-weight: 500; display: none; margin-top: -4px;}

        /* Custom Combobox (Gõ & Dropdown) */
        .combo-box-wrapper { position: relative; width: 100%; }
        .combo-box-input {
            width: 100%; padding: 12px 40px 12px 16px; border: 1px solid #cbd5e1; border-radius: 8px;
            font-size: 15px; outline: none; transition: 0.2s; font-family: inherit; box-sizing: border-box; cursor: text;
        }
        .combo-box-input:focus { border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15); }
        .combo-box-toggle {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            color: #94a3b8; cursor: pointer; padding: 5px;
        }
        .combo-box-list {
            position: absolute; top: calc(100% + 4px); left: 0; width: 100%; max-height: 250px;
            overflow-y: auto; background: white; border: 1px solid #e2e8f0; border-radius: 8px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); z-index: 10; display: none; margin: 0; padding: 8px 0; list-style: none;
        }
        .combo-box-list.show { display: block; }
        .combo-item { padding: 10px 16px; font-size: 14px; color: #334155; cursor: pointer; transition: 0.2s; }
        .combo-item:hover { background: #f1f5f9; color: var(--admin-primary); font-weight: 600; }
        .combo-optgroup { padding: 8px 16px; font-size: 12px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; background: #f8fafc; cursor: default; }
        
        .modal-footer { padding: 20px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; background: #f8fafc; }
        .btn-modal { padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; transition: 0.2s; }
        .btn-cancel { background: #e2e8f0; color: #475569; }
        .btn-cancel:hover { background: #cbd5e1; }
        .btn-save { background: var(--admin-primary); color: white; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3); }
        .btn-save:hover { background: #4338ca; transform: translateY(-1px); }
        
        /* Loading Overlay inside modal */
        .modal-loader { position: absolute; inset: 0; background: rgba(255,255,255,0.8); z-index: 20; display: flex; justify-content: center; align-items: center; border-radius: 16px; opacity: 0; visibility: hidden; transition: 0.2s; }
        .modal-loader.active { opacity: 1; visibility: visible; }
        .spinner { border: 3px solid #e2e8f0; border-top: 3px solid var(--admin-primary); border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        #system-alert-message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            animation: slideInFromRight 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes slideInFromRight {
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
            <a href="/DevMaster/Auth/Logout.php" class="btn-footer btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</a>
        </div>
    </aside>

    <main class="admin-main-content">
        <div class="dash-header">
            <div>
                <h1>Quản Lý Cấu Trúc Khóa Học</h1>
                <div class="header-meta-box" style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 8px;"><span>Danh mục:</span><span class="total-badge-count badge-danhmuc"><?php echo $totalDanhMucCount; ?></span></div>
                    <div style="display: flex; align-items: center; gap: 8px;"><span>Nhóm:</span><span class="total-badge-count badge-nhom"><?php echo $totalNhomCount; ?></span></div>
                    <div style="display: flex; align-items: center; gap: 8px;"><span>Khóa học:</span><span class="total-badge-count badge-khoahoc"><?php echo $totalCoursesCount; ?></span></div>
                    <div style="display: flex; align-items: center; gap: 8px;"><span>Bài học:</span><span class="total-badge-count badge-baihoc"><?php echo $totalLessonsCount; ?></span></div>
                </div>
            </div>
            <div class="right-control-panel">
                <div class="search-container">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="courseFilterInput" placeholder="Tìm danh mục, nhóm, khóa học..." onkeyup="liveSearchTreeEngine()">
                </div>
                <a href="ThemKhoaHoc.php" class="btn-add-course-master"><i class="fa-solid fa-circle-plus"></i> Thêm khóa học mới</a>
                <a href="ThemBaiHoc.php?from=QLKH" class="btn-add-course-master">
                    <i class="fa-solid fa-book-open"></i> Thêm Bài Học
                </a>
            </div>
        </div>

        <?php if(!empty($systemMessage)): ?>
            <div id="system-alert-message"> 
                <?php echo $systemMessage; ?> 
            </div> 
            <script>
                // Xóa sạch toàn bộ tham số (cả action, status, edit_id...) trên URL ngay lập tức 
                // Cách này giúp thanh địa chỉ trở nên sạch sẽ vĩnh viễn, khi reload (F5) không bao giờ bị hiện lại
                if (window.history.replaceState) {
                    window.history.replaceState(null, null, window.location.pathname);
                }
            </script>
        <?php endif; ?>

        <div class="table-wrapper">
            <table class="student-enterprise-table" id="treeGridMasterTable">
                <thead><tr><th class="cell-left" style="width: 75%;">Danh mục, Nhóm và Khóa học</th><th style="width: 25%;">Thao tác</th></tr></thead>
                <tbody>
                    <?php if (empty($treeData)): ?>
                        <tr><td colspan="2" class="cell-center" style="color: var(--admin-muted); padding: 40px;">Hệ thống chưa có dữ liệu.</td></tr>
                    <?php else: ?>
                        <?php foreach ($treeData as $dmIndex => $dm): $dmId = $dm['DanhMucId']; ?>
                            <tr class="tree-row-danhmuc" data-dm-id="<?php echo $dmId; ?>" onclick="toggleDanhMucNode(this, '<?php echo $dmId; ?>')">
                                <td class="cell-left">
                                    <div class="tree-node-click-area">
                                        <i class="fa-solid fa-chevron-right tree-toggle-icon"></i><i class="fa-solid fa-layer-group" style="color: #4f46e5;"></i>
                                        <span class="node-text-search-target" id="text-dm-<?php echo $dmId; ?>"><?php echo htmlspecialchars($dm['TenDanhMuc']); ?></span>
                                    </div>
                                </td>
                                <td class="cell-center" onclick="event.stopPropagation();">
                                    <div class="action-container">
                                        <button class="action-icon-btn btn-info-style" onclick="openEditModal('danhmuc', <?php echo $dmId; ?>)" title="Sửa danh mục"><i class="fa-solid fa-pen-to-square"></i></button>
                                        <button type="button" onclick="executeCascadeDelete('danhmuc', <?php echo $dmId; ?>, '<?php echo htmlspecialchars($dm['TenDanhMuc'], ENT_QUOTES); ?>')" class="action-icon-btn btn-trash-style" title="Xóa danh mục"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>

                            <?php foreach ($dm['Nhoms'] as $nhomIndex => $nhom): $nhomId = $nhom['NhomKhoaHocId']; ?>
                                <tr class="tree-row-nhom dm-child-of-<?php echo $dmId; ?>" data-nhom-id="<?php echo $nhomId; ?>" data-parent-dm="<?php echo $dmId; ?>" onclick="toggleNhomNode(this, '<?php echo $nhomId; ?>')">
                                    <td class="cell-left">
                                        <div class="tree-node-nhom-area">
                                            <i class="fa-solid fa-chevron-right tree-toggle-sub-icon"></i><i class="fa-solid fa-folder-tree" style="color: #0284c7;"></i>
                                            <span class="node-text-search-target" id="text-nhom-<?php echo $nhomId; ?>"><?php echo htmlspecialchars($nhom['TenNhom']); ?></span>
                                        </div>
                                    </td>
                                    <td class="cell-center" onclick="event.stopPropagation();">
                                        <div class="action-container">
                                            <button class="action-icon-btn btn-info-style" onclick="openEditModal('nhom', <?php echo $nhomId; ?>)" title="Sửa nhóm"><i class="fa-solid fa-pen-to-square"></i></button>
                                            <button type="button" onclick="executeCascadeDelete('nhom', <?php echo $nhomId; ?>, '<?php echo htmlspecialchars($nhom['TenNhom'], ENT_QUOTES); ?>')" class="action-icon-btn btn-trash-style" title="Xóa nhóm"><i class="fa-solid fa-trash-can"></i></button>
                                        </div>
                                    </td>
                                </tr>

                                <?php foreach ($nhom['KhoaHocs'] as $khIndex => $course): 
                                    $khId = $course['KhoaHocId'];
                                    $imgUrl = !empty($course['Anh']) ? '/DevMaster/' . $course['Anh'] : '/DevMaster/Images-Videos/default-course.png';
                                    $priceDisplay = number_format($course['Gia'], 0, ',', '.') . ' đ';
                                    $hasLessons = count($course['BaiHocs']) > 0;
                                ?>
                                    <tr class="tree-row-khoahoc nhom-child-of-<?php echo $nhomId; ?> dm-grandchild-of-<?php echo $dmId; ?>" 
                                        data-kh-id="<?php echo $khId; ?>" 
                                        data-parent-nhom="<?php echo $nhomId; ?>"
                                        style="display: none; cursor: <?php echo $hasLessons ? 'pointer' : 'default'; ?>;"
                                        <?php if($hasLessons): ?> onclick="toggleKhoaHocNode(this, '<?php echo $khId; ?>')" <?php endif; ?>>
                                        <td class="cell-left">
                                            <div class="course-master-profile">
                                                <?php if($hasLessons): ?>
                                                    <i class="fa-solid fa-chevron-right tree-toggle-kh-icon" style="margin-right: -10px; color: #10b981; transition: transform 0.2s;"></i>
                                                <?php else: ?>
                                                    <div style="width: 14px; margin-right: -10px;"></div>
                                                <?php endif; ?>
                                                
                                                <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="Thumbnail" class="course-preview-image">
                                                <div class="course-info-text-block">
                                                    <div class="course-title-main node-text-search-target" id="text-kh-<?php echo $khId; ?>"><?php echo htmlspecialchars($course['Ten']); ?></div>
                                                    <div class="course-author-sub node-text-search-target"><?php echo htmlspecialchars($course['TenGiangVien'] ?? 'Chưa rõ GV'); ?></div>
                                                    <div class="course-price-tag"><?php echo $priceDisplay; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="cell-center" onclick="event.stopPropagation();">
                                            <div class="action-container">
                                                <button class="action-icon-btn btn-info-style" onclick="openEditModal('khoahoc', <?php echo $khId; ?>)" title="Sửa khóa học"><i class="fa-solid fa-pen-to-square"></i></button>
                                                <button type="button" onclick="executeCascadeDelete('khoahoc', <?php echo $khId; ?>, '<?php echo htmlspecialchars($course['Ten'], ENT_QUOTES); ?>')" class="action-icon-btn btn-trash-style" title="Xóa khóa học"><i class="fa-solid fa-trash-can"></i></button>
                                            </div>
                                        </td>
                                    </tr>

                                    <?php foreach ($course['BaiHocs'] as $bhIndex => $lesson): $bhId = $lesson['BaiHocId'];
                                    // Đường dẫn video gốc từ database
                                    $videoUrl = htmlspecialchars($lesson['LinkVideo']); 
                                    ?>
                                    <tr class="tree-row-baihoc kh-child-of-<?php echo $khId; ?> nhom-grandchild-of-<?php echo $nhomId; ?>" data-bh-id="<?php echo $bhId; ?>" data-parent-kh="<?php echo $khId; ?>" style="display: none;">
                                        <td class="cell-left">
                                            <div class="lesson-master-profile" style="padding-left: 95px; display: flex; align-items: center; gap: 15px;">
                                                <div class="video-preview-wrapper" style="position: relative; width: 100px; height: 58px; border-radius: 6px; overflow: hidden; border: 1px solid #e2e8f0; background: #000;">
                                                    <video src="<?php echo $videoUrl; ?>#t=0.5" preload="metadata" muted style="width: 100%; height: 100%; object-fit: cover; position: absolute; top:0; left:0; pointer-events: none;"></video>
                                                        <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.2); display: flex; align-items: center; justify-content: center; color: white;">
                                                            <i class="fa-solid fa-play" style="font-size: 12px;"></i>
                                                        </div>
                                                    </div>
                                                    <div class="course-info-text-block">
                                                        <div class="course-title-main node-text-search-target" id="text-bh-<?php echo $bhId; ?>" style="font-size: 14px; font-weight: 600; color: #334155;"><?php echo htmlspecialchars($lesson['Ten']); ?></div>
                                                        <div class="course-author-sub node-text-search-target" style="font-size: 12px; font-family: monospace; color: #0284c7;">
                                                            <i class="fa-solid fa-link"></i> <?php echo !empty($lesson['LinkVideo']) ? htmlspecialchars($lesson['LinkVideo']) : 'Chưa có Link Video'; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="cell-center">
                                                <div class="action-container">
                                                    <button class="action-icon-btn btn-info-style" onclick="openEditModal('baihoc', <?php echo $bhId; ?>)" title="Sửa bài học"><i class="fa-solid fa-pen-to-square" style="font-size:12px;"></i></button>
                                                    <button type="button" onclick="executeCascadeDelete('baihoc', <?php echo $bhId; ?>, '<?php echo htmlspecialchars($lesson['Ten'], ENT_QUOTES); ?>')" class="action-icon-btn btn-trash-style" title="Xóa bài học" style="width:30px; height:30px;"><i class="fa-solid fa-trash-can" style="font-size:12px;"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    
                                    <tr class="sub-pagination-row pag-bh-for-kh-<?php echo $khId; ?> nhom-grandchild-of-<?php echo $nhomId; ?>" data-parent-kh="<?php echo $khId; ?>" style="display: none;">
                                        <td colspan="2" class="sub-pagination-container" id="pagBHNavContainer-<?php echo $khId; ?>" style="padding: 8px 24px 8px 95px !important;"></td>
                                    </tr>

                                <?php endforeach; ?>
                                <tr class="sub-pagination-row pag-kh-for-nhom-<?php echo $nhomId; ?> dm-grandchild-of-<?php echo $dmId; ?>" data-parent-nhom="<?php echo $nhomId; ?>"><td colspan="2" class="sub-pagination-container sub-pagination-kh-container" id="pagKHNavContainer-<?php echo $nhomId; ?>"></td></tr>
                            <?php endforeach; ?>
                            <tr class="sub-pagination-row pag-nhom-for-dm-<?php echo $dmId; ?>" data-parent-dm="<?php echo $dmId; ?>"><td colspan="2" class="sub-pagination-container" id="pagNhomNavContainer-<?php echo $dmId; ?>"></td></tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="pagination-container-wrapper" id="mainCategoryPaginationWrapper"></div>
    </main>

    <div class="edit-modal-overlay" id="masterEditModal" onclick="closeEditModal(event)">
        <div class="edit-modal-box" onclick="event.stopPropagation()">
            <div class="modal-loader" id="modalLoader"><div class="spinner"></div></div>
            
            <div class="modal-header">
                <h2 id="modalTitle">Chỉnh sửa</h2>
                <button class="modal-close-btn" onclick="closeEditModal(null, true)"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <div class="modal-body" id="modalBody">
                </div>
            
            <div class="modal-footer">
                <button class="btn-modal btn-cancel" onclick="closeEditModal(null, true)">Hủy</button>
                <button class="btn-modal btn-save" onclick="saveEditData()"><i class="fa-solid fa-floppy-disk"></i> Lưu</button>
            </div>
        </div>
    </div>

    <script>
        // 1. Trước khi trang bị tải lại (Xóa hoặc Sửa), lưu lại các ID đang mở vào localStorage
        window.addEventListener("beforeunload", function() {
            localStorage.setItem("remember_expandDM", JSON.stringify(expandDMRegistry || {}));
            localStorage.setItem("remember_expandNhom", JSON.stringify(expandNhomRegistry || {}));
            localStorage.setItem("remember_expandKh", JSON.stringify(expandKhoaHocRegistry || {}));
            localStorage.setItem("remember_mainPage", currentMainPage || 1);
        });
        const LIMIT_DANHMUC_MAIN = 10; const LIMIT_NHOM_SUB = 10; const LIMIT_KHOAHOC_SUB = 20; const LIMIT_BAIHOC_SUB = 12;
        let currentMainPage = 1; let subNhomPageRegistry = {}; let subKhPageRegistry = {}; let subBhPageRegistry = {}; let expandDMRegistry = {}; let expandNhomRegistry = {}; let expandKhoaHocRegistry = {};
        let expandKhRegistry = expandKhoaHocRegistry;
        const dmRowsCollection = Array.from(document.querySelectorAll('.tree-row-danhmuc'));
        const totalDMCount = dmRowsCollection.length; const totalDMPages = Math.ceil(totalDMCount / LIMIT_DANHMUC_MAIN);

        document.addEventListener("DOMContentLoaded", function() {
            const savedMainPage = parseInt(localStorage.getItem("remember_mainPage") || "1", 10);
            if (totalDMCount > 0) switchMainCategoryPage(isNaN(savedMainPage) ? 1 : savedMainPage);
        });

        function switchMainCategoryPage(page) {
            currentMainPage = page; const startIndex = (page - 1) * LIMIT_DANHMUC_MAIN; const endIndex = startIndex + LIMIT_DANHMUC_MAIN;
            hideAllRowsCompletely();
            dmRowsCollection.forEach((row, index) => {
                if (index >= startIndex && index < endIndex) {
                    row.style.display = ""; const dmId = row.getAttribute('data-dm-id');
                    if (expandDMRegistry[dmId]) { row.querySelector('.tree-toggle-icon').classList.add('node-expanded'); applySubNhomPaginationEngine(dmId); }
                }
            });
            renderMainPaginationUI();
        }

        function renderMainPaginationUI() {
            const container = document.getElementById('mainCategoryPaginationWrapper'); container.innerHTML = "";
            if (totalDMPages <= 1) { const btn = document.createElement('button'); btn.className = "pagination-nav-btn active-page-node"; btn.innerText = "1"; container.appendChild(btn); return; }
            const prev = document.createElement('button'); prev.className = "pagination-nav-btn"; prev.innerHTML = '<i class="fa-solid fa-angle-left"></i>';
            if (currentMainPage === 1) { prev.style.opacity = "0.4"; prev.style.cursor = "not-allowed"; } else { prev.onclick = () => switchMainCategoryPage(currentMainPage - 1); } container.appendChild(prev);
            let start = Math.max(1, currentMainPage - 2); let end = Math.min(totalDMPages, currentMainPage + 2);
            if (start > 1) { const fBtn = document.createElement('button'); fBtn.className = "pagination-nav-btn"; fBtn.innerText = "1"; fBtn.onclick = () => switchMainCategoryPage(1); container.appendChild(fBtn); if (start > 2) { const dots = document.createElement('div'); dots.className = "pagination-dots-ellipsis"; dots.innerText = "..."; container.appendChild(dots); } }
            for (let i = start; i <= end; i++) { const btn = document.createElement('button'); btn.className = "pagination-nav-btn" + (i === currentMainPage ? " active-page-node" : ""); btn.innerText = i; btn.onclick = () => switchMainCategoryPage(i); container.appendChild(btn); }
            if (end < totalDMPages) { if (end < totalDMPages - 1) { const dots = document.createElement('div'); dots.className = "pagination-dots-ellipsis"; dots.innerText = "..."; container.appendChild(dots); } const lBtn = document.createElement('button'); lBtn.className = "pagination-nav-btn"; lBtn.innerText = totalDMPages; lBtn.onclick = () => switchMainCategoryPage(totalDMPages); container.appendChild(lBtn); }
            const next = document.createElement('button'); next.className = "pagination-nav-btn"; next.innerHTML = '<i class="fa-solid fa-angle-right"></i>';
            if (currentMainPage === totalDMPages) { next.style.opacity = "0.4"; next.style.cursor = "not-allowed"; } else { next.onclick = () => switchMainCategoryPage(currentMainPage + 1); } container.appendChild(next);
        }

        function toggleDanhMucNode(rowElement, dmId) {
            const icon = rowElement.querySelector('.tree-toggle-icon');
            const keyword = document.getElementById('courseFilterInput').value.trim().toUpperCase();
            
            // Nếu đang tìm kiếm, sử dụng logic lọc chỉ hiển thị những gì liên quan
            if (keyword !== "") {
                if (!expandDMRegistry[dmId]) {
                    expandDMRegistry[dmId] = true;
                    if (icon) icon.classList.add('node-expanded');
                    document.querySelectorAll(`.tree-row-nhom.dm-child-of-${dmId}`).forEach(row => {
                        if (row.classList.contains('open') || row.classList.contains('is-expanded') || row.style.display !== "none") {
                            row.style.display = "table-row";
                        }
                    });
                } else {
                    expandDMRegistry[dmId] = false;
                    if (icon) icon.classList.remove('node-expanded');

                    // 1. Ẩn tất cả Nhóm con thuộc danh mục này và tắt trạng thái mở của Nhóm
                    document.querySelectorAll(`.tree-row-nhom.dm-child-of-${dmId}`).forEach(row => {
                        row.style.display = "none";
                        const nhomId = row.getAttribute('data-nhom-id');
                        if (nhomId && typeof expandNhomRegistry !== 'undefined') {
                            expandNhomRegistry[nhomId] = false;
                        }
                        const subIcon = row.querySelector('.tree-toggle-sub-icon');
                        if (subIcon) subIcon.classList.remove('node-expanded');
                    });

                    // 2. Duyệt qua toàn bộ Khóa học thuộc Danh mục để thực hiện tắt sâu (Deep Hide)
                    document.querySelectorAll(`.tree-row-khoahoc.dm-grandchild-of-${dmId}`).forEach(row => {
                        row.style.display = "none";
                        const khId = row.getAttribute('data-kh-id');
                        
                        if (khId) {
                            // Đưa trạng thái mở của Khóa học về false để chặn các hàm phân trang tự động kích hoạt lại
                            if (typeof expandKhoaHocRegistry !== 'undefined') {
                                expandKhoaHocRegistry[khId] = false;
                            }
                            
                            // Áp dụng ép ẩn triệt để toàn bộ Bài học trực thuộc Khóa học này
                            document.querySelectorAll(`.tree-row-baihoc.kh-child-of-${khId}`).forEach(bhRow => {
                                bhRow.style.display = "none";
                            });
                            
                            // Ẩn luôn thanh phân trang bài học của khóa học đó nếu có
                            const pagBhRow = document.querySelector(`.sub-pagination-row.pag-bh-for-kh-${khId}`);
                            if (pagBhRow) pagBhRow.style.display = "none";
                        }
                        
                        const khIcon = row.querySelector('.tree-toggle-kh-icon');
                        if (khIcon) khIcon.classList.remove('node-expanded');
                    });

                    // 3. Quét dọn dự phòng toàn bộ các dòng Bài học và dòng phân trang mang class danh mục ông cố
                    document.querySelectorAll(`.tree-row-baihoc.dm-grandchild-of-${dmId}`).forEach(row => {
                        row.style.display = "none";
                    });
                    document.querySelectorAll(`.sub-pagination-row.dm-grandchild-of-${dmId}`).forEach(row => {
                        row.style.display = "none";
                    });

                    // Đồng bộ và lưu khóa trạng thái mới vào LocalStorage để không bị bung ra khi reload trang
                    localStorage.setItem("remember_expandDM", JSON.stringify(expandDMRegistry));
                    if (typeof expandNhomRegistry !== 'undefined') localStorage.setItem("remember_expandNhom", JSON.stringify(expandNhomRegistry));
                    if (typeof expandKhoaHocRegistry !== 'undefined') localStorage.setItem("remember_expandKh", JSON.stringify(expandKhoaHocRegistry));
                }
                return; // Thoát hàm, không chạy logic phân trang mặc định
            }

            // --- LOGIC KHI KHÔNG TÌM KIẾM ---
            if (!expandDMRegistry[dmId]) {
                expandDMRegistry[dmId] = true;
                if (icon) icon.classList.add('node-expanded');
                if (!subNhomPageRegistry[dmId]) subNhomPageRegistry[dmId] = 1;
                applySubNhomPaginationEngine(dmId);
            // --- LOGIC KHI KHÔNG TÌM KIẾM ---
            } else {
                if (!expandDMRegistry[dmId]) {
                    expandDMRegistry[dmId] = true;
                    if (icon) icon.classList.add('node-expanded');
                    applySubNhomPaginationEngine(dmId);
                } else {
                    expandDMRegistry[dmId] = false;
                    if (icon) icon.classList.remove('node-expanded');

                    // 1. Ép ẩn toàn bộ tầng Nhóm trực thuộc danh mục
                    document.querySelectorAll(`.tree-row-nhom.dm-child-of-${dmId}`).forEach(row => {
                        row.style.display = "none";
                        const nhomId = row.getAttribute('data-nhom-id');
                        if (nhomId && typeof expandNhomRegistry !== 'undefined') {
                            expandNhomRegistry[nhomId] = false;
                        }
                        const subIcon = row.querySelector('.tree-toggle-sub-icon');
                        if (subIcon) subIcon.classList.remove('node-expanded');
                    });

                    // 2. Ép ẩn toàn bộ tầng Khóa học có class định danh cha/ông là danh mục này
                    document.querySelectorAll(`.tree-row-khoahoc.dm-grandchild-of-${dmId}`).forEach(row => {
                        row.style.display = "none";
                        const khId = row.getAttribute('data-kh-id');
                        if (khId && typeof expandKhoaHocRegistry !== 'undefined') {
                            expandKhoaHocRegistry[khId] = false; // GIỮ NGUYÊN CODE CŨ CỦA ANH
                        }
                        const khIcon = row.querySelector('.tree-toggle-kh-icon');
                        if (khIcon) khIcon.classList.remove('node-expanded');
                    });

                    // 3. Xử lý triệt để quét dọn toàn bộ Bài học liên đới thuộc các khóa học nằm trong danh mục này
                    document.querySelectorAll(`.tree-row-baihoc`).forEach(bhRow => {
                        const parentKhId = bhRow.getAttribute('data-parent-kh');
                        if (parentKhId) {
                            const khRow = document.querySelector(`.tree-row-khoahoc[data-kh-id="${parentKhId}"]`);
                            if (khRow && khRow.classList.contains(`dm-grandchild-of-${dmId}`)) {
                                bhRow.style.display = "none";
                                // BỔ SUNG AN TOÀN: Khi bài học bị ép ẩn, bộ nhớ mở của Khóa học cha PHẢI về false 
                                // để chặn hàm applySubKhPaginationEngine tự ý vẽ lại bài học khi tương tác nhóm khác
                                if (typeof expandKhoaHocRegistry !== 'undefined') {
                                    expandKhoaHocRegistry[parentKhId] = false;
                                }
                            }
                        }
                        // Quét dọn bổ sung nếu bạn có đặt class kế thừa trực tiếp trên hàng bài học
                        if (bhRow.classList.contains(`dm-grandchild-of-${dmId}`) || bhRow.className.includes(`dm-grandchild-of-${dmId}`)) {
                            bhRow.style.display = "none";
                            // BỔ SUNG AN TOÀN: Truy vết thêm ID khóa học từ class để ép đóng bộ nhớ Registry
                            const classMatch = bhRow.className.match(/kh-child-of-(\d+)/);
                            const cKhId = classMatch ? classMatch[1] : null;
                            if (cKhId && typeof expandKhoaHocRegistry !== 'undefined') {
                                expandKhoaHocRegistry[cKhId] = false;
                            }
                        }
                    });

                    // 4. Ẩn toàn bộ tất cả các thanh phân trang phụ (Cả phân trang nhóm và phân trang khóa học)
                    document.querySelectorAll(`.sub-pagination-row`).forEach(pagRow => {
                        if (pagRow.classList.contains(`dm-grandchild-of-${dmId}`) || 
                            pagRow.getAttribute('data-parent-dm') == dmId) {
                            pagRow.style.display = "none";
                        }
                        const pNhomId = pagRow.getAttribute('data-parent-nhom');
                        if (pNhomId) {
                            const nhomRow = document.querySelector(`.tree-row-nhom[data-nhom-id="${pNhomId}"]`);
                            if (nhomRow && nhomRow.getAttribute('data-parent-dm') == dmId) {
                                pagRow.style.display = "none";
                            }
                        }
                        const pKhId = pagRow.getAttribute('data-parent-kh');
                        if (pKhId) {
                            const khRow = document.querySelector(`.tree-row-khoahoc[data-kh-id="${pKhId}"]`);
                            if (khRow && khRow.classList.contains(`dm-grandchild-of-${dmId}`)) {
                                pagRow.style.display = "none";
                                // BỔ SUNG AN TOÀN: Ép bộ nhớ đóng khóa học tại thanh phân trang bài học liên quan
                                if (typeof expandKhoaHocRegistry !== 'undefined') {
                                    expandKhoaHocRegistry[pKhId] = false;
                                }
                            }
                        }
                    });

                    // Lưu trạng thái đóng vào bộ nhớ trình duyệt
                    localStorage.setItem("remember_expandDM", JSON.stringify(expandDMRegistry));
                    if (typeof expandNhomRegistry !== 'undefined') localStorage.setItem("remember_expandNhom", JSON.stringify(expandNhomRegistry));
                    if (typeof expandKhoaHocRegistry !== 'undefined') localStorage.setItem("remember_expandKh", JSON.stringify(expandKhoaHocRegistry));
                }
                return;
            }
        }

        function toggleNhomNode(rowElement, nhomId) {
            const icon = rowElement.querySelector('.tree-toggle-sub-icon');
            const keyword = document.getElementById('courseFilterInput').value.trim().toUpperCase();
            
            if (keyword !== "") {
                if (!expandNhomRegistry[nhomId]) {
                    expandNhomRegistry[nhomId] = true;
                    if (icon) icon.classList.add('node-expanded');
                    // Chỉ mở các Khóa học liên quan đến từ khóa tìm kiếm
                    document.querySelectorAll(`.tree-row-khoahoc.nhom-child-of-${nhomId}`).forEach(row => {
                        if (row.classList.contains('open') || row.classList.contains('expanded') || row.classList.contains('is-expanded')) {
                            row.style.display = "table-row";
                        }
                    });
                } else {
                    expandNhomRegistry[nhomId] = false;
                    if (icon) icon.classList.remove('node-expanded');
                    hideNhomChildrenDeep(nhomId);

                    // 1. Ẩn toàn bộ Khóa học con trực thuộc Nhóm và reset trạng thái Registry, mũi tên
                    document.querySelectorAll(`.tree-row-khoahoc.nhom-child-of-${nhomId}`).forEach(row => {
                        row.style.display = "none";
                        const khId = row.getAttribute('data-kh-id');
                        if (khId && typeof expandKhoaHocRegistry !== 'undefined') expandKhoaHocRegistry[khId] = false;
                        const khIcon = row.querySelector('.tree-toggle-kh-icon');
                        if (khIcon) khIcon.classList.remove('node-expanded');
                    });

                    // 2. Ẩn toàn bộ Bài học cháu trực thuộc Nhóm này
                    document.querySelectorAll(`.tree-row-baihoc.nhom-grandchild-of-${nhomId}`).forEach(row => {
                        row.style.display = "none";
                    });

                    // 3. Ẩn các hàng phân trang phụ của Nhóm và Khóa học con
                    document.querySelectorAll(`.sub-pagination-row.nhom-grandchild-of-${nhomId}`).forEach(row => {
                        row.style.display = "none";
                    });
                    const pagKhRow = document.querySelector(`.sub-pagination-row.pag-kh-for-nhom-${nhomId}`);
                    if (pagKhRow) pagKhRow.style.display = "none";
                }
                return;
            }

            // --- LOGIC KHI KHÔNG TÌM KIẾM ---
            if (!subKhPageRegistry[nhomId]) subKhPageRegistry[nhomId] = 1;

            if (!expandNhomRegistry[nhomId]) {
                expandNhomRegistry[nhomId] = true;
                if (icon) icon.classList.add('node-expanded');
                applySubKhPaginationEngine(nhomId);
            } else {
                expandNhomRegistry[nhomId] = false;
                if (icon) icon.classList.remove('node-expanded');

                // 1. ÉP ẨN TOÀN BỘ TẦNG KHÓA HỌC CON VÀ TẮT TRẠNG THÁI TRONG REGISTRY
                document.querySelectorAll(`.tree-row-khoahoc.nhom-child-of-${nhomId}`).forEach(row => {
                    row.style.display = "none";
                    const khId = row.getAttribute('data-kh-id');
                    if (khId && typeof expandKhoaHocRegistry !== 'undefined') {
                        expandKhoaHocRegistry[khId] = false; 
                    }
                    const khIcon = row.querySelector('.tree-toggle-kh-icon');
                    if (khIcon) khIcon.classList.remove('node-expanded');
                });

                // 2. ÉP ẨN TOÀN BỘ TẦNG BÀI HỌC THUỘC CÁC KHÓA HỌC NẰM TRONG NHÓM NÀY
                document.querySelectorAll(`.tree-row-baihoc`).forEach(bhRow => {
                    const parentKhId = bhRow.getAttribute('data-parent-kh');
                    if (parentKhId) {
                        const khRow = document.querySelector(`.tree-row-khoahoc[data-kh-id="${parentKhId}"]`);
                        if (khRow && khRow.classList.contains(`nhom-child-of-${nhomId}`)) {
                            bhRow.style.display = "none";
                        }
                    }
                });

                // 3. ẨN TOÀN BỘ THANH PHÂN TRANG BÀI HỌC CỦA CÁC KHÓA HỌC CON
                document.querySelectorAll(`.sub-pagination-row`).forEach(pagRow => {
                    const pKhId = pagRow.getAttribute('data-parent-kh');
                    if (pKhId) {
                        const khRow = document.querySelector(`.tree-row-khoahoc[data-kh-id="${pKhId}"]`);
                        if (khRow && khRow.classList.contains(`nhom-child-of-${nhomId}`)) {
                            pagRow.style.display = "none";
                        }
                    }
                });

                // 4. ẨN THANH PHÂN TRANG KHÓA HỌC CỦA CHÍNH NHÓM NÀY
                const myPagRow = document.querySelector(`.sub-pagination-row.pag-kh-for-nhom-${nhomId}`);
                if (myPagRow) myPagRow.style.display = "none";

                // Đồng bộ lưu trạng thái đóng an toàn vào bộ nhớ trình duyệt
                localStorage.setItem("remember_expandNhom", JSON.stringify(expandNhomRegistry));
                if (typeof expandKhoaHocRegistry !== 'undefined') {
                    localStorage.setItem("remember_expandKh", JSON.stringify(expandKhoaHocRegistry));
                }
            }
        }

        function toggleKhoaHocNode(rowElement, khId) {
            const icon = rowElement.querySelector('.tree-toggle-kh-icon');
            const keyword = document.getElementById('courseFilterInput').value.trim().toUpperCase();
            if (typeof expandKhoaHocRegistry === 'undefined') {
                window.expandKhoaHocRegistry = {};
            }
            if (keyword !== "") {
                // Kiểm tra trạng thái đóng mở dựa vào registry lưu trữ để đảm bảo tính đồng bộ chính xác
                const isExpanded = !!expandKhoaHocRegistry[khId];

                if (isExpanded) {
                    // Nếu đang mở thì thực hiện đóng lại toàn bộ bài học thuộc khóa học này
                    expandKhoaHocRegistry[khId] = false;
                    if (icon) icon.classList.remove('node-expanded');
                    document.querySelectorAll(`.tree-row-baihoc.kh-child-of-${khId}`).forEach(bhRow => {
                        bhRow.style.display = "none";
                    });
                } else {
                    // Nếu đang đóng thì thực hiện mở ra, nhưng lọc kỹ để CHỈ hiển thị bài học trùng khớp từ khóa
                    expandKhoaHocRegistry[khId] = true;
                    if (icon) icon.classList.add('node-expanded');
                    document.querySelectorAll(`.tree-row-baihoc.kh-child-of-${khId}`).forEach(bhRow => {
                        const searchTargets = bhRow.querySelectorAll('.node-text-search-target');
                        let isMatch = false;
                        searchTargets.forEach(t => {
                            if ((t.textContent || t.innerText).toUpperCase().indexOf(keyword) > -1) {
                                isMatch = true;
                            }
                        });
                        // Nếu chứa từ khóa tìm kiếm thì hiển thị, không liên quan thì tiếp tục ẩn
                        if (isMatch) {
                            bhRow.style.display = "table-row";
                        } else {
                            bhRow.style.display = "none";
                        }
                    });
                }
                return;
            }

            // --- LOGIC GỐC KHI KHÔNG TÌM KIẾM ---
            // MỚI
            if (!subBhPageRegistry[khId]) subBhPageRegistry[khId] = 1;
            if (!expandKhoaHocRegistry[khId]) {
                expandKhoaHocRegistry[khId] = true;
                if (icon) icon.classList.add('node-expanded');
                applySubBhPaginationEngine(khId);
            } else {
                expandKhoaHocRegistry[khId] = false;
                if (icon) icon.classList.remove('node-expanded');
                hideKhChildrenDeep(khId);
            }
        }

        function applySubBhPaginationEngine(khId) {
            const bhRows = Array.from(document.querySelectorAll(`.tree-row-baihoc.kh-child-of-${khId}`));
            const pagRow = document.querySelector(`.sub-pagination-row.pag-bh-for-kh-${khId}`);
            const navContainer = document.getElementById(`pagBHNavContainer-${khId}`);
            
            bhRows.forEach(r => r.style.display = "none"); 
            if(pagRow) pagRow.style.display = "none";
            if (bhRows.length === 0) return;
            
            const totalSubPages = Math.ceil(bhRows.length / LIMIT_BAIHOC_SUB); 
            let activeSubPage = subBhPageRegistry[khId] || 1; 
            if (activeSubPage > totalSubPages) activeSubPage = totalSubPages;

            if (activeSubPage < 1) activeSubPage = 1; // Đảm bảo không bị âm trang
            // Cập nhật ngược lại vào bộ nhớ để đồng bộ cấu trúc trang mới nhất sau khi xóa
            subBhPageRegistry[khId] = activeSubPage;
            let subBhPageData = JSON.parse(localStorage.getItem("remember_subBhPage") || "{}");
            subBhPageData[khId] = activeSubPage;
            localStorage.setItem("remember_subBhPage", JSON.stringify(subBhPageData));

            const start = (activeSubPage - 1) * LIMIT_BAIHOC_SUB; 
            const end = start + LIMIT_BAIHOC_SUB;
            
            bhRows.forEach((row, index) => { if (index >= start && index < end) { row.style.display = "table-row"; } });
            
            if (bhRows.length > LIMIT_BAIHOC_SUB) {
                if(pagRow && navContainer) {
                    pagRow.style.display = "table-row"; 
                    navContainer.innerHTML = `<span style="font-size:12px; font-weight:700; color:var(--admin-muted); margin-right:8px;">Trang Bài Học:</span>`;
                    for(let p=1; p<=totalSubPages; p++) {
                        const btn = document.createElement('button'); 
                        btn.className = `btn-sub-page ${p === activeSubPage ? 'active-bh' : ''}`; 
                        btn.innerText = p; 
                        btn.type = "button";
                        btn.onclick = (e) => {
                            e.stopPropagation();
                            subBhPageRegistry[khId] = p;
                            
                            // Đồng bộ tức thời vào Storage để chống mất dấu khi sửa/xóa
                            let subBhPage = JSON.parse(localStorage.getItem("remember_subBhPage") || "{}");
                            subBhPage[khId] = p;
                            localStorage.setItem("remember_subBhPage", JSON.stringify(subBhPage));
                            
                            applySubBhPaginationEngine(khId);
                        };
                        navContainer.appendChild(btn);
                    }
                }
            }
        }

        function hideKhChildrenDeep(khId) {
            // 1. Ép trạng thái trong Registry về false ngay lập tức để chặn hàm phân trang tự động kích hoạt lại
            if (typeof expandKhoaHocRegistry !== 'undefined') {
                expandKhoaHocRegistry[khId] = false;
            }
            
            // Đồng bộ xóa luôn trong localStorage để tránh lệch pha khi reload trang
            let expandKhData = JSON.parse(localStorage.getItem('remember_expandKh') || '{}');
            if (expandKhData[khId]) {
                expandKhData[khId] = false;
                localStorage.setItem('remember_expandKh', JSON.stringify(expandKhData));
            }

            // 2. Ẩn toàn bộ Bài học trực thuộc
            document.querySelectorAll(`.tree-row-baihoc.kh-child-of-${khId}`).forEach(r => {
                r.style.display = "none";
            });

            // 3. Ẩn luôn thanh phân trang bài học của khóa học này (nếu có)
            const pagBhRow = document.querySelector(`.sub-pagination-row.pag-bh-for-kh-${khId}`);
            if (pagBhRow) {
                pagBhRow.style.display = "none";
            }
        }

        function applySubNhomPaginationEngine(dmId) {
            const nhomRows = Array.from(document.querySelectorAll(`.tree-row-nhom.dm-child-of-${dmId}`));
            const pagRow = document.querySelector(`.sub-pagination-row.pag-nhom-for-dm-${dmId}`);
            const navContainer = document.getElementById(`pagNhomNavContainer-${dmId}`);
            nhomRows.forEach(r => r.style.display = "none"); if(pagRow) pagRow.style.display = "none";
            if (nhomRows.length === 0) return;
            const totalSubPages = Math.ceil(nhomRows.length / LIMIT_NHOM_SUB); let activeSubPage = subNhomPageRegistry[dmId] || 1; if (activeSubPage > totalSubPages) activeSubPage = totalSubPages;
            const start = (activeSubPage - 1) * LIMIT_NHOM_SUB; const end = start + LIMIT_NHOM_SUB;
            nhomRows.forEach((row, index) => {
                if (index >= start && index < end) {
                    row.style.display = "table-row"; const nhomId = row.getAttribute('data-nhom-id');
                    if (expandNhomRegistry[nhomId]) { row.querySelector('.tree-toggle-sub-icon').classList.add('node-expanded'); applySubKhPaginationEngine(nhomId); }
                }
            });
            if (nhomRows.length > LIMIT_NHOM_SUB) {
                if(pagRow && navContainer) {
                    pagRow.style.display = "table-row"; navContainer.innerHTML = `<span style="font-size:12px; font-weight:700; color:var(--admin-muted); margin-right:8px;">Trang Nhóm:</span>`;
                    for(let p=1; p<=totalSubPages; p++) {
                        const btn = document.createElement('button'); btn.className = `btn-sub-page ${p === activeSubPage ? 'active' : ''}`; btn.innerText = p; btn.type = "button";
                        btn.onclick = (e) => { e.stopPropagation(); subNhomPageRegistry[dmId] = p; applySubNhomPaginationEngine(dmId); };
                        navContainer.appendChild(btn);
                    }
                }
            }
        }

        function applySubKhPaginationEngine(nhomId) {
            const khRows = Array.from(document.querySelectorAll(`.tree-row-khoahoc.nhom-child-of-${nhomId}`));
            const pagRow = document.querySelector(`.sub-pagination-row.pag-kh-for-nhom-${nhomId}`);
            const navContainer = document.getElementById(`pagKhNavContainer-${nhomId}`);
            
            // Ẩn toàn bộ khóa học thuộc nhóm này để chuẩn bị vẽ lại theo trang
            khRows.forEach(r => {
                r.style.display = "none";
                // Đồng thời ẩn luôn bài học của các khóa học đó nếu khóa học đó đang ở trạng thái đóng
                const khId = r.getAttribute('data-kh-id');
                if (!window.expandKhoaHocRegistry || !window.expandKhoaHocRegistry[khId]) {
                    document.querySelectorAll(`.tree-row-baihoc.kh-child-of-${khId}`).forEach(bhRow => bhRow.style.display = "none");
                }
            });
            
            if(pagRow) pagRow.style.display = "none";
            if (khRows.length === 0) return;

            const totalSubPages = Math.ceil(khRows.length / LIMIT_KHOAHOC_SUB);
            let activeSubPage = subKhPageRegistry[nhomId] || 1;
            if (activeSubPage > totalSubPages) activeSubPage = totalSubPages;

            const start = (activeSubPage - 1) * LIMIT_KHOAHOC_SUB;
            const end = start + LIMIT_KHOAHOC_SUB;

            khRows.forEach((row, index) => {
                if (index >= start && index < end) {
                    row.style.display = "table-row"; // Hiển thị dòng khóa học thuộc trang hiện tại
                    
                    // ĐẶC BIỆT: Nếu khóa học này trước đó ĐANG MỞ, phải giữ nguyên hiển thị bài học của nó
                    // MỚI
                    const khId = row.getAttribute('data-kh-id');
                    if (window.expandKhoaHocRegistry && window.expandKhoaHocRegistry[khId]) {
                        document.querySelectorAll(`.tree-row-baihoc.kh-child-of-${khId}`).forEach(bhRow => {
                            bhRow.style.display = "table-row";
                        });
                    }
                }
            });

            // Vẽ giao diện thanh phân trang mini cho danh sách Khóa học
            if (khRows.length > LIMIT_KHOAHOC_SUB) {
                if(pagRow && navContainer) {
                    pagRow.style.display = "table-row";
                    navContainer.innerHTML = `<span style="font-size:12px; font-weight:700; color:var(--admin-muted); margin-right:8px;">Trang Khóa Học:</span>`;
                    for(let p=1; p<=totalSubPages; p++) {
                        const btn = document.createElement('button');
                        btn.className = `btn-sub-page ${p === activeSubPage ? 'active-kh' : ''}`;
                        btn.innerText = p;
                        btn.type = "button";
                        btn.onclick = (e) => {
                            e.stopPropagation();
                            subKhPageRegistry[nhomId] = p;
                            applySubKhPaginationEngine(nhomId);
                        };
                        navContainer.appendChild(btn);
                    }
                }
            }
        }

        function hideDanhMucChildrenDeep(dmId) {
            document.querySelectorAll(`.tree-row-nhom.dm-child-of-${dmId}`).forEach(r => { r.style.display = "none"; r.querySelector('.tree-toggle-sub-icon').classList.remove('node-expanded'); const nhomId = r.getAttribute('data-nhom-id'); expandNhomRegistry[nhomId] = false; });
            document.querySelectorAll(`.tree-row-khoahoc.dm-grandchild-of-${dmId}`).forEach(r => r.style.display = "none");
            document.querySelectorAll(`.sub-pagination-row[data-parent-dm="${dmId}"]`).forEach(r => r.style.display = "none");
            document.querySelectorAll(`.sub-pagination-row[data-parent-nhom]`).forEach(r => { const nhomId = r.getAttribute('data-parent-nhom'); const nhomRow = document.querySelector(`.tree-row-nhom[data-nhom-id="${nhomId}"]`); if(nhomRow && nhomRow.getAttribute('data-parent-dm') == dmId) { r.style.display = "none"; } });
        }
        function hideNhomChildrenDeep(nhomId) {
            // 1. Ẩn toàn bộ Khóa học thuộc Nhóm
            document.querySelectorAll(`.tree-row-khoahoc.nhom-child-of-${nhomId}`).forEach(r => {
                r.style.display = "none";
                
                // Reset xoay icon chevron của Khóa học về mặc định (đóng)
                const icon = r.querySelector('.tree-toggle-kh-icon');
                if(icon) icon.classList.remove('node-expanded');
                
                // Lấy ID khóa học để tắt trạng thái mở của nó trong bộ nhớ
                const khId = r.getAttribute('data-kh-id');
                expandKhRegistry[khId] = false;
                
                // Gọi hàm ẩn bài học của khóa học đó
                hideKhChildrenDeep(khId);
            });
            
            // 2. Ẩn luôn toàn bộ Bài học và thanh phân trang Bài học thuộc Nhóm này (Phòng ngừa sót dòng)
            document.querySelectorAll(`.tree-row-baihoc.nhom-grandchild-of-${nhomId}`).forEach(r => r.style.display = "none");
            document.querySelectorAll(`.sub-pagination-row.nhom-grandchild-of-${nhomId}`).forEach(r => r.style.display = "none");
            
            // 3. Ẩn thanh phân trang của Khóa học
            const pagRow = document.querySelector(`.sub-pagination-row.pag-kh-for-nhom-${nhomId}`);
            if(pagRow) pagRow.style.display = "none";
        }
        function hideAllRowsCompletely() { document.querySelectorAll('#treeGridMasterTable tbody tr').forEach(r => r.style.display = "none"); }

        function liveSearchTreeEngine() {
            const keyword = document.getElementById('courseFilterInput').value.trim().toUpperCase();
            const mainPaginationContainer = document.getElementById('mainCategoryPaginationWrapper');
            
            // =========================================================================
            // TRƯỜNG HỢP 1: XÓA HẾT CHỮ TRONG Ô TÌM KIẾM
            // =========================================================================
            if (keyword === "") {
                document.querySelectorAll('#treeGridMasterTable tbody tr').forEach(row => {
                    if(row.classList.contains('sub-pagination-row')) return;

                    // Thu gọn hoàn toàn trạng thái đóng/mở và đồng bộ lại Registry toàn cục về trạng thái ĐÓNG
                    row.classList.remove('open', 'expanded', 'is-expanded');
                    
                    const dmId = row.getAttribute('data-dm-id');
                    const nhomId = row.getAttribute('data-nhom-id');
                    const khId = row.getAttribute('data-kh-id');
                    
                    if (dmId && typeof expandDMRegistry !== 'undefined') expandDMRegistry[dmId] = false;
                    if (nhomId && typeof expandNhomRegistry !== 'undefined') expandNhomRegistry[nhomId] = false;
                    if (khId && typeof window.expandKhoaHocRegistry !== 'undefined') window.expandKhoaHocRegistry[khId] = false;

                    // Ép hướng mũi tên quay về phía bên PHẢI tuyệt đối bằng cách gỡ bỏ class xoay góc của hệ thống
                    const icon = row.querySelector('.tree-toggle-icon, .tree-toggle-sub-icon, .tree-toggle-kh-icon, .toggle-icon');
                    if (icon) {
                        icon.classList.remove('node-expanded');
                    }
                });
                
                // Trả lại thanh phân trang chính và vẽ lại trang hiện tại
                mainPaginationContainer.style.display = "flex";
                switchMainCategoryPage(currentMainPage);
                return;
            }
            
            // =========================================================================
            // TRƯỜNG HỢP 2: ĐANG GÕ TỪ KHÓA TÌM KIẾM
            // =========================================================================
            mainPaginationContainer.style.display = "none";
            document.querySelectorAll('.sub-pagination-row').forEach(r => r.style.display = "none");
            
            const visibleRowsMap = new Set();
            const allRows = document.querySelectorAll('#treeGridMasterTable tbody tr');
            
            // Bước 1: Quét tìm các dòng trùng khớp để đưa vào bản đồ hiển thị cấu trúc cây
            allRows.forEach(row => {
                if(row.classList.contains('sub-pagination-row')) return;
                
                const searchTargets = row.querySelectorAll('.node-text-search-target');
                let isMatch = false;
                searchTargets.forEach(t => {
                    if((t.textContent || t.innerText).toUpperCase().indexOf(keyword) > -1) {
                        isMatch = true;
                    }
                });
                
                if (isMatch) {
                    visibleRowsMap.add(row);
                    
                    // Truy vết ngược lên trên để hiển thị đầy đủ các nút cha
                    if (row.classList.contains('tree-row-khoahoc')) {
                        const parentNhomId = row.getAttribute('data-parent-nhom');
                        const nhomRow = document.querySelector(`.tree-row-nhom[data-nhom-id="${parentNhomId}"]`);
                        if (nhomRow) {
                            visibleRowsMap.add(nhomRow);
                            const parentDmId = nhomRow.getAttribute('data-parent-dm');
                            const dmRow = document.querySelector(`.tree-row-danhmuc[data-dm-id="${parentDmId}"]`);
                            if (dmRow) visibleRowsMap.add(dmRow);
                        }
                    } else if (row.classList.contains('tree-row-nhom')) {
                        const parentDmId = row.getAttribute('data-parent-dm');
                        const dmRow = document.querySelector(`.tree-row-danhmuc[data-dm-id="${parentDmId}"]`);
                        if (dmRow) visibleRowsMap.add(dmRow);
                    } else if (row.classList.contains('tree-row-baihoc')) {
                        const parentKhId = row.className.match(/kh-child-of-(\d+)/)?.[1];
                        const khRow = document.querySelector(`.tree-row-khoahoc[data-kh-id="${parentKhId}"]`);
                        if (khRow) {
                            visibleRowsMap.add(khRow);
                            const parentNhomId = khRow.getAttribute('data-parent-nhom');
                            const nhomRow = document.querySelector(`.tree-row-nhom[data-nhom-id="${parentNhomId}"]`);
                            if (nhomRow) {
                                visibleRowsMap.add(nhomRow);
                                const parentDmId = nhomRow.getAttribute('data-parent-dm');
                                const dmRow = document.querySelector(`.tree-row-danhmuc[data-dm-id="${parentDmId}"]`);
                                if (dmRow) visibleRowsMap.add(dmRow);
                            }
                        }
                    }
                }
            });

            // Bước 2: Duyệt lại toàn bộ bảng để thực thi hiển thị và ép hướng mũi tên TUYỆT ĐỐI
            allRows.forEach(row => {
                if(row.classList.contains('sub-pagination-row')) return;
                
                const icon = row.querySelector('.tree-toggle-icon, .tree-toggle-sub-icon, .tree-toggle-kh-icon, .toggle-icon');
                
                const dmId = row.getAttribute('data-dm-id');
                const nhomId = row.getAttribute('data-nhom-id');
                const khId = row.getAttribute('data-kh-id');

                if (visibleRowsMap.has(row)) {
                    row.style.display = "table-row";
                    row.classList.add('open', 'expanded', 'is-expanded');
                    
                    // Kiểm tra riêng cho hàng Khóa học
                    if (row.classList.contains('tree-row-khoahoc') && khId) {
                        // Tìm xem có bài học con nào của khóa học này cũng nằm trong diện hiển thị hay không
                        let hasVisibleChild = false;
                        document.querySelectorAll(`.tree-row-baihoc.kh-child-of-${khId}`).forEach(bhRow => {
                            if (visibleRowsMap.has(bhRow)) {
                                hasVisibleChild = true;
                            }
                        });

                        if (hasVisibleChild) {
                            // Nếu có bài học khớp từ khóa bên trong thì mới cho xoáy mũi tên xuống và đặt registry thành true
                            if (typeof window.expandKhoaHocRegistry !== 'undefined') window.expandKhoaHocRegistry[khId] = true;
                            if (icon) icon.classList.add('node-expanded');
                        } else {
                            // Nếu không có bài học nào khớp, giữ nguyên trạng thái đóng (mũi tên quay sang phải) để người dùng tự click mở
                            if (typeof window.expandKhoaHocRegistry !== 'undefined') window.expandKhoaHocRegistry[khId] = false;
                            if (icon) icon.classList.remove('node-expanded');
                        }
                    } else {
                        // Đối với Danh mục và Nhóm khóa học thì giữ nguyên logic mở rộng mặc định
                        if (dmId && typeof expandDMRegistry !== 'undefined') expandDMRegistry[dmId] = true;
                        if (nhomId && typeof expandNhomRegistry !== 'undefined') expandNhomRegistry[nhomId] = true;
                        if (icon) icon.classList.add('node-expanded');
                    }
                } else {
                    // Biến các hàng không liên quan thành không tồn tại
                    row.style.display = "none";
                    row.classList.remove('open', 'expanded', 'is-expanded');
                    // Đưa trạng thái Registry về FALSE đồng bộ trên toàn cục hệ thống
                    if (dmId && typeof expandDMRegistry !== 'undefined') expandDMRegistry[dmId] = false;
                    if (nhomId && typeof expandNhomRegistry !== 'undefined') expandNhomRegistry[nhomId] = false;
                    if (khId && typeof expandKhoaHocRegistry !== 'undefined') {
                        expandKhoaHocRegistry[khId] = false;
                        // BỔ SUNG: Ẩn toàn bộ bài học thuộc khóa học này khi khóa học bị ẩn/đóng trong chế độ tìm kiếm
                        document.querySelectorAll(`.tree-row-baihoc.kh-child-of-${khId}`).forEach(bhRow => {
                            bhRow.style.display = "none";
                        });
                    }
                    // Tìm chính xác tất cả các loại icon mũi tên tầng DM, Nhóm, Khóa học để hủy xoay
                    const anyIcon = row.querySelector('.tree-toggle-icon, .tree-toggle-sub-icon, .tree-toggle-kh-icon');
                    if (anyIcon) {
                        anyIcon.classList.remove('node-expanded');
                    }
                }
            });
        }

        function executeCascadeDelete(level, id, name) {
            let warnMsg = "";
            if (level === 'danhmuc') { warnMsg = `CẢNH BÁO NGUY HIỂM CẤP ĐỘ 3:\nHành động này sẽ XÓA SẠCH Danh mục [ ${name} ] cùng toàn bộ Nhóm, Khóa học và Bài học!\n\nBạn chắc chứ?`; } 
            else if (level === 'nhom') { warnMsg = `CẢNH BÁO NGUY HIỂM CẤP ĐỘ 2:\nHành động này sẽ XÓA SẠCH Nhóm [ ${name} ] cùng toàn bộ Khóa học và Bài học!\n\nBạn chắc chứ?`; } 
            else if (level === 'khoahoc') { warnMsg = `XÁC THỰC XÓA KHÓA HỌC:\nBạn có chắc chắn muốn xóa Khóa học [ ${name} ] cùng toàn bộ Bài học!\n\nBạn chắc chứ?`; }
            else if (level === 'baihoc') { warnMsg = `XÁC THỰC XÓA BÀI HỌC:\nBạn có chắc chắn muốn xóa Bài học [ ${name} ]?`; }
            if (confirm(warnMsg)) {
                if (level === 'khoahoc') {
                    const khId = id;
                    const courseRow = document.querySelector(`.tree-row-khoahoc[data-kh-id="${khId}"]`);
                    if (courseRow) {
                        let expandKh = JSON.parse(localStorage.getItem("remember_expandKh") || "{}");
                        expandKh[khId] = true;
                        localStorage.setItem("remember_expandKh", JSON.stringify(expandKh));
                        
                        if (typeof subBhPageRegistry !== 'undefined' && subBhPageRegistry[khId]) {
                            let subBhPage = JSON.parse(localStorage.getItem("remember_subBhPage") || "{}");
                            subBhPage[khId] = subBhPageRegistry[khId];
                            localStorage.setItem("remember_subBhPage", JSON.stringify(subBhPage));
                        }
                    }
                }
                // Bổ sung xử lý lưu vết toàn bộ cấu trúc cây cha (Danh mục, Nhóm, Khóa học) khi xóa Bài học
                else if (level === 'baihoc') {
                    const bhId = id;
                    const bhRow = document.querySelector(`button[onclick*="openEditModal('baihoc', ${bhId})"]`)?.closest('tr');
                    if (bhRow) {
                        const classMatch = bhRow.className.match(/kh-child-of-(\d+)/);
                        const khId = classMatch ? classMatch[1] : null;
                        if (khId) {
                            // 1. Bung mở khóa học hiện tại
                            let expandKh = JSON.parse(localStorage.getItem("remember_expandKh") || "{}");
                            expandKh[khId] = true;
                            localStorage.setItem("remember_expandKh", JSON.stringify(expandKh));

                            // 2. Truy vết ngược mở Nhóm cha và Danh mục cha
                            const khRow = document.querySelector(`.tree-row-khoahoc[data-kh-id="${khId}"]`);
                            if (khRow) {
                                const nhomId = khRow.getAttribute('data-parent-nhom');
                                if (nhomId) {
                                    let expandNhom = JSON.parse(localStorage.getItem("remember_expandNhom") || "{}");
                                    expandNhom[nhomId] = true;
                                    localStorage.setItem("remember_expandNhom", JSON.stringify(expandNhom));
                                    
                                    const nhomRow = document.querySelector(`.tree-row-nhom[data-nhom-id="${nhomId}"]`);
                                    if (nhomRow) {
                                        const dmId = nhomRow.getAttribute('data-parent-dm');
                                        if (dmId) {
                                            let expandDM = JSON.parse(localStorage.getItem("remember_expandDM") || "{}");
                                            expandDM[dmId] = true;
                                            localStorage.setItem("remember_expandDM", JSON.stringify(expandDM));
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                window.location.href = `QuanLyKhoaHoc.php?action=delete&level=${level}&id=${id}`;
            }
        }

        /* === BỘ CODE JAVASCRIPT MỚI: XỬ LÝ MODAL, AJAX, COMBOBOX === */
        let currentEditConfig = { level: null, id: null };
        const modal = document.getElementById('masterEditModal');
        const modalBox = modal.querySelector('.edit-modal-box');
        const modalBody = document.getElementById('modalBody');
        const loader = document.getElementById('modalLoader');

        function openEditModal(level, id) {
            currentEditConfig = { level, id };
            modal.classList.add('active');
            loader.classList.add('active');
            
            // Chỉnh kích thước khung theo Content
            if(level === 'danhmuc') { modalBox.style.maxWidth = '400px'; document.getElementById('modalTitle').innerText = 'Chỉnh sửa Danh mục'; }
            if(level === 'nhom') { modalBox.style.maxWidth = '500px'; document.getElementById('modalTitle').innerText = 'Chỉnh sửa Nhóm Khóa Học'; }
            if(level === 'khoahoc') { modalBox.style.maxWidth = '600px'; document.getElementById('modalTitle').innerText = 'Chỉnh sửa Khóa Học'; }
            if(level === 'baihoc') { modalBox.style.maxWidth = '760px'; document.getElementById('modalTitle').innerText = 'Chỉnh sửa Bài Học'; }

            // Gọi AJAX lấy data
            const formData = new FormData();
            formData.append('ajax_action', 'fetch_edit');
            formData.append('level', level);
            formData.append('id', id);

            fetch('', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if(res.status === 'success') {
                    buildFormUI(level, res);
                } else {
                    alert(res.message); closeEditModal(null, true);
                }
            })
            .catch(err => { alert('Lỗi kết nối tới Server!'); closeEditModal(null, true); })
            .finally(() => loader.classList.remove('active'));
        }

        function closeEditModal(event, force = false) {
            if (force || event.target === modal) {
                modal.classList.remove('active');
                setTimeout(() => modalBody.innerHTML = '', 300); // clear after animation
            }
        }

        // Tạo giao diện form tùy biến
        function buildFormUI(level, res) {
            const data = res.data;
            let html = `
                <div class="form-group">
                    <label>Tên ${level === 'danhmuc' ? 'Danh mục' : (level === 'nhom' ? 'Nhóm khóa học' : (level === 'khoahoc' ? 'Khóa học' : 'Bài học'))}</label>
                    <input type="text" id="editName" class="std-input" value="${data.name}" placeholder="Nhập tên mới...">
                    <div id="errorMsg" class="error-msg-box"></div>
                </div>
            `;

            if (level === 'nhom') {
                // Dropdown Danh mục
                html += `
                <div class="form-group">
                    <label>Thuộc Danh mục</label>
                    <div class="combo-box-wrapper" id="catCombo">
                        <input type="hidden" id="editParentId" value="${data.parent_id}">
                        <input type="text" class="combo-box-input" id="catComboInput" placeholder="Gõ để tìm danh mục..." onfocus="showComboList('catComboList')" onkeyup="filterComboList(this, 'catComboList')">
                        <i class="fa-solid fa-chevron-down combo-box-toggle" onclick="toggleComboList('catComboInput', 'catComboList')"></i>
                        <ul class="combo-box-list" id="catComboList">
                `;
                res.categories.forEach(c => { html += `<li class="combo-item" data-val="${c.id}" onclick="selectComboItem('${c.name}', ${c.id}, 'catComboInput', 'editParentId', 'catComboList')">${c.name}</li>`; });
                html += `</ul></div></div>`;
            }

            if (level === 'khoahoc') {
                // Form Giá + Giảng Viên
                html += `
                <div class="form-group">
                    <label>Giảng viên</label>
                    <input type="text" id="editGv" class="std-input" value="${data.TenGiangVien || ''}" placeholder="Tên giảng viên...">
                </div>
                <div class="form-group">
                    <label>Giá (VNĐ)</label>
                    <input type="number" id="editGia" class="std-input" value="${data.Gia || 0}">
                </div>
                `;

                // Dropdown Nhóm (Phân cấp theo Danh mục)
                html += `
                <div class="form-group">
                    <label>Thuộc Nhóm khóa học</label>
                    <div class="combo-box-wrapper" id="groupCombo">
                        <input type="hidden" id="editParentId" value="${data.parent_id}">
                        <input type="text" class="combo-box-input" id="groupComboInput" placeholder="Gõ để tìm nhóm..." onfocus="showComboList('groupComboList')" onkeyup="filterComboList(this, 'groupComboList')">
                        <i class="fa-solid fa-chevron-down combo-box-toggle" onclick="toggleComboList('groupComboInput', 'groupComboList')"></i>
                        <ul class="combo-box-list" id="groupComboList">
                `;
                res.grouped_options.forEach(cat => {
                    html += `<li class="combo-optgroup">${cat.cat_name}</li>`;
                    cat.groups.forEach(g => {
                        html += `<li class="combo-item" data-val="${g.id}" onclick="selectComboItem('${g.name}', ${g.id}, 'groupComboInput', 'editParentId', 'groupComboList')">${g.name}</li>`;
                    });
                });
                html += `</ul></div></div>`;
            }
            // Bổ sung vào cuối hàm buildFormUI(level, res)
            if (level === 'baihoc') {
                // Tách lấy tên file từ đường dẫn video gốc để hiển thị cho người dùng dễ nhìn
                let currentFileName = "Chưa có video";
                if (data.LinkVideo) {
                    currentFileName = data.LinkVideo.split('/').pop();
                }
                html += `
                <div class="form-group">
                    <label>Video bài học</label>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <button type="button" class="btn-modal" style="background: #e0f2fe; color: #0369a1; border: 1px dashed #0284c7; display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 12px; font-weight: 600;" onclick="document.getElementById('editVideoFile').click()">
                            <i class="fa-solid fa-upload"></i> Chọn video thay thế...
                        </button>
                        
                        <input type="file" id="editVideoFile" accept="video/*" style="display: none;" onchange="handleVideoFileChange(this)">
                        
                        <input type="hidden" id="editVideoLink" value="${data.LinkVideo || ''}">
                        
                        <div style="font-size: 13px; color: #475569; background: #f8fafc; padding: 8px 12px; border-radius: 6px; border: 1px solid #e2e8f0; word-break: break-all; display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-video" style="color: #64748b;"></i>
                            <span id="videoFileStatus"><strong>Gốc:</strong> ${currentFileName}</span>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Thuộc Khóa Học</label>
                    <div class="combo-box-wrapper" id="courseCombo">
                        <input type="hidden" id="editParentId" value="${data.parent_id}">
                        <input type="text" class="combo-box-input" id="courseComboInput" placeholder="Gõ để tìm khóa học..." onfocus="showComboList('courseComboList')" onkeyup="filterComboList(this, 'courseComboList')">
                        <i class="fa-solid fa-chevron-down combo-box-toggle" onclick="toggleComboList('courseComboInput', 'courseComboList')"></i>
                        <ul class="combo-box-list" id="courseComboList">
                `;
                res.grouped_courses.forEach(group => {
                    html += `<li class="combo-optgroup">${group.group_name}</li>`;
                    group.courses.forEach(c => {
                        html += `<li class="combo-item" data-val="${c.id}" onclick="selectComboItem('${c.name.replace(/'/g, "\\'")}', ${c.id}, 'courseComboInput', 'editParentId', 'courseComboList')">${c.name}</li>`;
                    });
                });
                html += `</ul></div></div>`;
            }

            modalBody.innerHTML = html;

            // Focus và set Default Text cho input
            setTimeout(() => document.getElementById('editName').focus(), 100);

            if (level === 'nhom') {
                const curCat = res.categories.find(c => c.id == data.parent_id);
                if(curCat) document.getElementById('catComboInput').value = curCat.name;
            }
            if (level === 'khoahoc') {
                let foundGroup = null;
                res.grouped_options.forEach(c => { const match = c.groups.find(g => g.id == data.parent_id); if(match) foundGroup = match; });
                if(foundGroup) document.getElementById('groupComboInput').value = foundGroup.name;
            }
            // Chèn logic đổ dữ liệu mặc định vào cuối hàm buildFormUI:
            if (level === 'baihoc') {
                let foundCourse = null;
                res.grouped_courses.forEach(g => { const match = g.courses.find(c => c.id == data.parent_id); if(match) foundCourse = match; });
                if(foundCourse) document.getElementById('courseComboInput').value = foundCourse.name;
            }
        }

        /* Các hàm xử lý Dropdown (Combobox) */
        function toggleComboList(inputId, listId) {
            const list = document.getElementById(listId);
            if(list.classList.contains('show')) list.classList.remove('show');
            else { showComboList(listId); document.getElementById(inputId).focus(); }
        }
        function showComboList(listId) {
            document.querySelectorAll('.combo-box-list').forEach(l => l.classList.remove('show'));
            document.getElementById(listId).classList.add('show');
        }
        function filterComboList(inputEle, listId) {
            const filter = inputEle.value.toUpperCase();
            const list = document.getElementById(listId);
            const items = list.querySelectorAll('li');
            items.forEach(item => {
                if (item.classList.contains('combo-optgroup')) return; // Giữ header nhưng logic ẩn sẽ rắc rối, ta check theo item con
                const txtValue = item.textContent || item.innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) item.style.display = ""; else item.style.display = "none";
            });
        }
        function selectComboItem(name, id, inputId, hiddenId, listId) {
            document.getElementById(inputId).value = name;
            document.getElementById(hiddenId).value = id;
            document.getElementById(listId).classList.remove('show');
        }
        // Đóng dropdown khi click ngoài
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.combo-box-wrapper')) { document.querySelectorAll('.combo-box-list').forEach(l => l.classList.remove('show')); }
        });

        /* Lưu dữ liệu */
        function saveEditData() {
            const nameInput = document.getElementById('editName');
            const newName = nameInput.value.trim();
            const errorBox = document.getElementById('errorMsg');
            
            if (newName === "") {
                nameInput.classList.add('error'); errorBox.innerText = "Tên không được để trống!"; errorBox.style.display = "block"; return;
            } else { nameInput.classList.remove('error'); errorBox.style.display = "none"; }

            const formData = new FormData();
            formData.append('ajax_action', 'save_edit');
            formData.append('level', currentEditConfig.level);
            formData.append('id', currentEditConfig.id);
            formData.append('name', newName);

            if (currentEditConfig.level === 'nhom' || currentEditConfig.level === 'khoahoc') {
                const pId = document.getElementById('editParentId').value;
                if(!pId) { alert("Vui lòng chọn mục cha hợp lệ từ danh sách!"); return; }
                formData.append('parent_id', pId);
            }
            if (currentEditConfig.level === 'khoahoc') {
                formData.append('giangvien', document.getElementById('editGv').value);
                formData.append('gia', document.getElementById('editGia').value);
            }
            // Thêm đoạn này vào trong hàm saveEditData() trước khi gọi fetch:
            if (currentEditConfig.level === 'baihoc') {
                const pId = document.getElementById('editParentId').value;
                if(!pId) { alert("Vui lòng chọn khóa học hợp lệ từ danh sách!"); return; }
                formData.append('parent_id', pId);
                
                // Lấy link video gốc hiện tại
                formData.append('video_link', document.getElementById('editVideoLink').value);
                
                // Kiểm tra xem người dùng có chọn file video mới nào từ máy tính không
                const videoFileInput = document.getElementById('editVideoFile');
                if (videoFileInput && videoFileInput.files && videoFileInput.files[0]) {
                    formData.append('video_file', videoFileInput.files[0]);
                }
            }
            loader.classList.add('active');

            fetch('', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    // Cập nhật Database thành công -> Refresh page để reset lại Cây thư mục (chuẩn nhất cho UI động)
                    window.location.href = window.location.pathname + '?status=success';
                } else {
                    nameInput.classList.add('error'); errorBox.innerText = res.message; errorBox.style.display = "block";
                }
            })
            .catch(err => alert('Có lỗi xảy ra khi lưu dữ liệu!'))
            .finally(() => loader.classList.remove('active'));
        }

        // Đóng modal bằng phím ESC
        document.addEventListener('keydown', (e) => { if(e.key === 'Escape' && modal.classList.contains('active')) closeEditModal(null, true); });

        function handleVideoFileChange(input) {
            const statusSpan = document.getElementById('videoFileStatus');
            if (input.files && input.files[0]) {
                statusSpan.innerHTML = `<strong>Mới chọn:</strong> ${input.files[0].name}`;
                statusSpan.style.color = "#16a34a"; // Đổi chữ sang màu xanh lá báo hiệu file mới chuẩn bị up
            }
        }

        // ==========================================================================
        // BỘ ĐỊNH TUYẾN ĐƯỜNG DẪN SÂU (DEEP LINKING & AUTO-TRIGGER MODAL)
        // ==========================================================================
        document.addEventListener("DOMContentLoaded", function() {
            try {
                // 1. Tự động định vị và kích hoạt chuỗi cha-con từ tầng Khóa học lên Nhóm và Danh mục
                const expandKhData = JSON.parse(localStorage.getItem('remember_expandKh') || '{}');
                
                for (let khId in expandKhData) {
                    if (expandKhData[khId]) {
                        const khRow = document.querySelector(`.tree-row-khoahoc[data-kh-id="${khId}"]`);
                        if (khRow) {
                            // Lấy ID nhóm cha và danh mục cha dựa trên các thuộc tính data- có sẵn trên hàng
                            const parentNhomId = khRow.getAttribute('data-parent-nhom');
                            if (parentNhomId) {
                                let expandNhom = JSON.parse(localStorage.getItem('remember_expandNhom') || '{}');
                                expandNhom[parentNhomId] = true;
                                localStorage.setItem('remember_expandNhom', JSON.stringify(expandNhom));
                                
                                const nhomRow = document.querySelector(`.tree-row-nhom[data-nhom-id="${parentNhomId}"]`);
                                if (nhomRow) {
                                    const parentDmId = nhomRow.getAttribute('data-parent-dm');
                                    if (parentDmId) {
                                        let expandDM = JSON.parse(localStorage.getItem('remember_expandDM') || '{}');
                                        expandDM[parentDmId] = true;
                                        localStorage.setItem('remember_expandDM', JSON.stringify(expandDM));
                                    }
                                }
                            }
                        }
                    }
                }

                // 2. Thực thi bung Tầng 1: Danh mục
                const expandDMData = JSON.parse(localStorage.getItem('remember_expandDM') || '{}');
                for (let id in expandDMData) {
                    if (expandDMData[id]) {
                        const row = document.querySelector(`.tree-row-danhmuc[data-dm-id="${id}"]`);
                        if (row) {
                            if (typeof expandDMRegistry !== 'undefined') expandDMRegistry[id] = false;
                            // Gọi hàm gốc để đồng bộ mũi tên và trạng thái mở rộng an toàn
                            if (typeof toggleDanhMucNode === 'function') {
                                toggleDanhMucNode(row, id);
                            } else {
                                row.click();
                            }
                        }
                    }
                }

                // 3. Thực thi bung Tầng 2: Nhóm khóa học
                const expandNhomData = JSON.parse(localStorage.getItem('remember_expandNhom') || '{}');
                for (let id in expandNhomData) {
                    if (expandNhomData[id]) {
                        const row = document.querySelector(`.tree-row-nhom[data-nhom-id="${id}"]`);
                        if (row) {
                            row.style.display = "table-row";
                            if (typeof expandNhomRegistry !== 'undefined') expandNhomRegistry[id] = false;
                            if (typeof toggleNhomNode === 'function') {
                                toggleNhomNode(row, id);
                            } else {
                                row.click();
                            }
                        }
                    }
                }

                // 4. Thực thi bung Tầng 3: Khóa học & hiển thị Bài học
                for (let id in expandKhData) {
                    if (expandKhData[id]) {
                        if (typeof window.expandKhoaHocRegistry !== 'undefined') window.expandKhoaHocRegistry[id] = false;
                        const row = document.querySelector(`.tree-row-khoahoc[data-kh-id="${id}"]`);
                        if (row) {
                            row.style.display = "table-row";
                            if (typeof toggleKhoaHocNode === 'function') {
                                toggleKhoaHocNode(row, id);
                            } else {
                                const btn = row.querySelector('.tree-toggle-kh-icon');
                                if (btn) btn.click();
                            }
                            if (typeof applySubBhPaginationEngine === "function") {
                                applySubBhPaginationEngine(id);
                            }
                        }
                    }
                }
            } catch (e) {
                console.error("Lỗi khôi phục trạng thái cây thư mục:", e);
            }
            
            // Xóa bộ nhớ đệm sau khi đã khôi phục xong để tránh ảnh hưởng lần truy cập mới hoàn toàn
            localStorage.removeItem("remember_expandDM");
            localStorage.removeItem("remember_expandNhom");
            localStorage.removeItem("remember_expandKh");
            localStorage.removeItem("remember_mainPage");
            // 1. Phân tích URL để tìm tham số edit_id
            const urlParams = new URLSearchParams(window.location.search);
            const editId = urlParams.get('edit_id');
            if (localStorage.getItem("remember_expandDM")) return;
            
            if (editId) {
                // 2. Tìm hàng chứa khóa học tương ứng dựa trên thuộc tính data-kh-id có sẵn trong bảng
                const courseRow = document.querySelector(`.tree-row-khoahoc[data-kh-id="${editId}"]`);
                
                if (courseRow) {
                    // Lấy ID của Nhóm cha và Danh mục ông nội từ data attribute có sẵn trên hàng khóa học
                    const parentNhomId = courseRow.getAttribute('data-parent-nhom');
                    const nhomRow = document.querySelector(`.tree-row-nhom[data-nhom-id="${parentNhomId}"]`);
                    
                    if (nhomRow) {
                        const parentDmId = nhomRow.getAttribute('data-parent-dm');
                        const dmRow = document.querySelector(`.tree-row-danhmuc[data-dm-id="${parentDmId}"]`);
                        
                        if (dmRow) {
                            // 3. XỬ LÝ PHÂN TRANG TỰ ĐỘNG: Tìm xem Danh mục này đang nằm ở trang mấy của bảng QLKH
                            const dmIndex = dmRowsCollection.indexOf(dmRow);
                            if (dmIndex !== -1) {
                                // Tính toán trang chính xác chứa danh mục này
                                const targetMainPage = Math.floor(dmIndex / LIMIT_DANHMUC_MAIN) + 1;
                                if (targetMainPage !== currentMainPage) {
                                    switchMainCategoryPage(targetMainPage);
                                }
                            }
                            
                            // 4. KÍCH HOẠT BUNG MỞ CẤU TRÚC (Nếu cấu trúc đó chưa được mở sẵn)
                            // Mở Danh mục (Tầng 1)
                            if (!expandDMRegistry[parentDmId]) {
                                toggleDanhMucNode(dmRow, parentDmId);
                            }
                            // Mở Nhóm khóa học (Tầng 2)
                            if (!expandNhomRegistry[parentNhomId]) {
                                toggleNhomNode(nhomRow, parentNhomId);
                            }
                            
                            // 5. CUỘN MÀN HÌNH ĐẾN KHÓA HỌC & BẬT MODAL CHỈNH SỬA LÊN NGAY LẬP TỨC
                            courseRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            
                            // Gọi hàm mở modal có sẵn của hệ thống, truyền đúng cấp độ và ID khóa học vào
                            setTimeout(() => {
                                openEditModal('khoahoc', parseInt(editId));
                            }, 400); // Trì hoãn nhẹ 400ms để hiệu ứng cuộn và bung cấu trúc mượt mà hơn
                        }
                    }
                } else {
                    console.warn(`[DevMaster Hệ Thống]: Không tìm thấy Khóa học có ID [${editId}] trong bảng dữ liệu hiện tại.`);
                }
            }

            const alertBox = document.getElementById("system-alert-message");       
            if (alertBox) {
                // Thiết lập hiệu ứng chuyển động CSS mượt mà cho khối thông báo
                alertBox.style.transition = "opacity 0.6s ease, transform 0.6s ease, max-height 0.6s ease, margin 0.6s ease";
                alertBox.style.opacity = "1";
                alertBox.style.transform = "translateY(0)";
                
                // Đợi 3.5 giây (3500ms) trước khi kích hoạt hiệu ứng biến mất (Fade Out)
                setTimeout(function () {
                    alertBox.style.opacity = "0";
                    alertBox.style.transform = "translateY(-10px)";
                    alertBox.style.maxHeight = "0px";
                    alertBox.style.padding = "0px";
                    alertBox.style.margin = "0px";
                    
                    // Sau khi hiệu ứng mờ kết thúc (600ms), xóa hoàn toàn thẻ HTML khỏi cây DOM
                    setTimeout(function () {
                        alertBox.remove();
                    }, 600);
                }, 1000);
            }

            // MỚI
            const expandDMData = JSON.parse(localStorage.getItem('remember_expandDM') || '{}');
            for (let id in expandDMData) {
                if (expandDMData[id]) {
                    const row = document.querySelector(`.tree-row-danhmuc[data-dm-id="${id}"]`);
                    const btn = row ? row.querySelector('.tree-toggle-icon') : null;
                    if (row && btn && !btn.classList.contains('node-expanded')) {
                        row.click();
                    }
                }
            }

            const expandNhomData = JSON.parse(localStorage.getItem('remember_expandNhom') || '{}');
            for (let id in expandNhomData) {
                if (expandNhomData[id]) {
                    const row = document.querySelector(`.tree-row-nhom[data-nhom-id="${id}"]`);
                    const btn = row ? row.querySelector('.tree-toggle-sub-icon') : null;
                    if (row && btn && !btn.classList.contains('node-expanded')) {
                        row.click();
                    }
                }
            }

            // MỚI
            const expandKhData = JSON.parse(localStorage.getItem('remember_expandKh') || '{}');
            for (let id in expandKhData) {
                if (expandKhData[id]) {
                    const row = document.querySelector(`.tree-row-khoahoc[data-kh-id="${id}"]`);
                    if (row) {
                        row.style.display = "table-row";
                        const btn = row.querySelector('.tree-toggle-kh-icon');
                        if (btn && !btn.classList.contains('node-expanded')) {
                            if (typeof row.onclick === 'function' || row.getAttribute('onclick')) {
                                row.click();
                            } else {
                                btn.click();
                            }
                        }
                        if (typeof applySubBhPaginationEngine === "function") {
                            applySubBhPaginationEngine(id);
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
