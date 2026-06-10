<?php
session_start();
// Kết nối Database 
$conn = new mysqli("localhost", "root", "", "devmaster");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Lấy ID khóa học từ URL, mặc định là 1 nếu không có
$khoaHocId = isset($_GET['id']) ? intval($_GET['id']) : 1; 

// 🔥 ĐỒNG BỘ: Sử dụng đúng $_SESSION['UserId'] theo chuẩn của ProcessCheckout.php
// Nếu chưa đăng nhập, mặc định lấy user mẫu có STT = 10 trong DB của bạn để test thử
$hocVienId = isset($_SESSION['UserId']) ? intval($_SESSION['UserId']) : 10; 

// Tính toán số bài và tiến độ dựa hoàn toàn vào bảng tiendohocvien theo đúng ID Học viên
$sqlKhoaHoc = "SELECT kh.Ten,
               (SELECT COUNT(bh.BaiHocId) FROM baihoc bh WHERE bh.KhoaHocId = kh.KhoaHocId) AS TongSoBai, 
               (SELECT COUNT(td.BaiHocId) FROM tiendohocvien td 
                JOIN baihoc bh ON td.BaiHocId = bh.BaiHocId 
                WHERE bh.KhoaHocId = kh.KhoaHocId AND td.STT = ? AND td.TrangThai = 1) AS BaiDaXong
               FROM khoahoc kh WHERE kh.KhoaHocId = ?";
$stmt = $conn->prepare($sqlKhoaHoc);
$stmt->bind_param("ii", $hocVienId, $khoaHocId);
$stmt->execute();
$khoaHoc = $stmt->get_result()->fetch_assoc();

$tienDoTong = ($khoaHoc['TongSoBai'] > 0)
    ? round(($khoaHoc['BaiDaXong'] / $khoaHoc['TongSoBai']) * 100)
    : 0;
$tongSoBai = $khoaHoc['TongSoBai'] > 0 ? $khoaHoc['TongSoBai'] : 1;

// Lấy danh sách bài học phân trang
$limit = 25; 
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$sqlCount = "SELECT COUNT(BaiHocId) AS TongSoBai FROM baihoc WHERE KhoaHocId = ?";
$stmtCount = $conn->prepare($sqlCount);
$stmtCount->bind_param("i", $khoaHocId);
$stmtCount->execute();
$totalLessonsCount = $stmtCount->get_result()->fetch_assoc()['TongSoBai'];
$totalPages = ceil($totalLessonsCount / $limit); 

// 🔥 ĐỒNG BỘ: Sử dụng LEFT JOIN để nếu học viên chưa học bài nào (chưa có dòng trong tiendohocvien), 
// Hệ thống sẽ tự động gán TrangThai = 0 thông qua hàm IFNULL, không lo bị lỗi hay thiếu dữ liệu.
$sqlBaiHoc = "SELECT bh.BaiHocId, bh.Ten, bh.LinkVideo, IFNULL(td.TrangThai, 0) AS TrangThai 
              FROM baihoc bh
              LEFT JOIN tiendohocvien td ON bh.BaiHocId = td.BaiHocId AND td.STT = ?
              WHERE bh.KhoaHocId = ? 
              ORDER BY bh.BaiHocId ASC 
              LIMIT ? OFFSET ?";
$stmt2 = $conn->prepare($sqlBaiHoc);
$stmt2->bind_param("iiii", $hocVienId, $khoaHocId, $limit, $offset);
$stmt2->execute();
$danhSachBaiHoc = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vào Học Ngay - <?php echo htmlspecialchars($khoaHoc['Ten']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Mọi Class/Style giữ nguyên 100% để tránh vỡ giao diện */
        :root {
            --bg-main: #0B0F19; 
            --card-bg: #1A2235;
            --primary-glow: #00E6F6; 
            --text-light: #E2E8F0;
            --progress-bg: #334155;
            --progress-fill: linear-gradient(90deg, #3B82F6, #00E6F6);
        }

        body {
            margin: 0; padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #FFFFFF;
            color: var(--text-light);
            overflow-x: hidden;
        }

        .hero-banner {
            position: relative;
            width: 100%;
            padding: 50px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at center, #1e293b 0%, #0B0F19 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            overflow: hidden;
        }
        
        .hero-banner::before {
            content: '';
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 60%; height: 60%;
            background: var(--primary-glow);
            filter: blur(150px);
            opacity: 0.15;
            z-index: 0;
        }

        .course-title {
            position: relative;
            z-index: 1;
            font-size: 32px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-align: center;
            background: linear-gradient(180deg, #FFFFFF 0%, #A5B4FC 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 40px rgba(0, 230, 246, 0.3);
        }

        .top-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 5%;
            background: rgba(236, 232, 232, 0.8);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .search-box {
            position: relative;
            width: 300px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: #FFFFFF;
            color: black;
            font-size: 14px;
            outline: none;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            border-color: var(--primary-glow);
            box-shadow: 0 0 15px rgba(0, 230, 246, 0.2);
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
        }

        .course-progress-container {
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 600;
        }

        .course-progress-bar {
            width: 200px;
            height: 10px;
            background: var(--progress-bg);
            border-radius: 10px;
            overflow: hidden;
        }

        .course-progress-fill {
            height: 100%;
            background: var(--progress-fill);
            width: <?php echo $tienDoTong; ?>%;
            transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .lessons-container {
            padding: 40px 5%;
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 25px;
        }

        .lesson-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            cursor: pointer;
        }

        .lesson-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.4);
            border-color: rgba(0, 230, 246, 0.3);
        }

        .video-wrapper {
            position: relative;
            width: 100%;
            aspect-ratio: 16/9;
            background: #000;
        }

        .video-wrapper video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .lesson-info {
            padding: 15px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .lesson-title {
            font-size: 15px;
            font-weight: 600;
            margin: 0 0 15px 0;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .lesson-progress-bar {
            width: 100%;
            height: 6px;
            background: var(--progress-bg);
            border-radius: 4px;
            margin-bottom: 5px;
            overflow: hidden;
        }

        .lesson-progress-fill {
            height: 100%;
            background: var(--progress-fill);
            width: 0%;
            transition: width 0.3s linear;
        }

        .lesson-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #94A3B8;
        }

        .status-badge {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.1);
        }
        .status-badge.completed {
            background: rgba(16, 185, 129, 0.2);
            color: #10B981;
        }
        .status-badge.watching {
            background: rgba(245, 158, 11, 0.2);
            color: #F59E0B;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        /* Style bảng thông báo trống dạng Light Mode: Nền xám nhạt, viền gạch, chữ tối */
        .empty-course-alert {
            grid-column: 1 / -1;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 60px 20px;
            background: #F1F5F9; /* Nền xám sáng mịn (Slate 100) thay cho nền tối cũ */
            border: 2px dashed #CBD5E1; /* Viền nét đứt màu xám trung tính giúp nổi bật trên nền trắng */
            border-radius: 16px;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.02); /* Đổ bóng nhẹ phía trong tạo độ chìm */
            text-align: center;
            box-sizing: border-box;
        }

        .alert-content i {
            font-size: 50px;
            color: #64748B; /* Icon màu xám đậm vừa phải */
            margin-bottom: 15px;
        }

        .alert-content h2 {
            font-size: 24px;
            margin: 10px 0;
            color: #1E293B; /* Chữ tiêu đề đảo ngược sang màu xám đen rất đậm */
            font-weight: 700;
        }

        .alert-content p {
            color: #475569; /* Chữ nội dung màu xám tối, dễ đọc trên nền xám nhạt */
            font-size: 15px;
            max-width: 500px;
            margin: 0 auto 20px auto;
            line-height: 1.6;
        }

        .coming-soon-badge {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 6px 16px;
            border-radius: 20px;
            background: #E2E8F0; /* Nền badge màu xám đậm hơn nền bảng một chút */
            color: #334155; /* Màu chữ badge xám tối tối giản */
            border: 1px solid #CBD5E1;
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 40px 0;
            width: 100%;
        }

        .pagination-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 45px;
            height: 45px;
            padding: 0 15px;
            border-radius: 12px;
            background: #1A2235;
            border: 1px solid rgba(255, 255, 255, 0.05);
            color: #94A3B8;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }

        .pagination-btn:hover:not(.disabled) {
            border-color: #00E6F6;
            color: #FFFFFF;
            box-shadow: 0 0 15px rgba(0, 230, 246, 0.3);
            transform: translateY(-2px);
        }

        .pagination-btn.active {
            background: linear-gradient(90deg, #3B82F6, #00E6F6);
            color: #FFFFFF;
            border: none;
            box-shadow: 0 0 20px rgba(0, 230, 246, 0.4);
        }

        .pagination-btn.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }

        @media (max-width: 1400px) { .lessons-container { grid-template-columns: repeat(4, 1fr); } }
        @media (max-width: 1100px) { .lessons-container { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 800px) { .lessons-container { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 500px) { .lessons-container { grid-template-columns: repeat(1, 1fr); } }
    </style>
</head>
<body>
<?php include '../Includes/Header.php'; ?>
    <div class="hero-banner">
        <h1 class="course-title"><?php echo htmlspecialchars($khoaHoc['Ten']); ?></h1>
    </div>

    <div class="top-controls">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Tìm kiếm bài học...">
        </div>
        
        <div class="course-progress-container">
            <span>Tiến độ khóa học: <span id="overall-percent-text"><?php echo $tienDoTong; ?></span>%</span>
            <div class="course-progress-bar">
                <div class="course-progress-fill" id="overall-progress-bar" style="width: <?php echo $tienDoTong; ?>%"></div>
            </div>
        </div>
    </div>

    <div class="lessons-container" id="lessonsGrid">
        <?php if (!empty($danhSachBaiHoc)): ?>
            <?php foreach ($danhSachBaiHoc as $index => $baihoc): 
                $isCompleted = $baihoc['TrangThai'] == 1;
                $progressWidth = $isCompleted ? 100 : 0; 
            ?>
            <div class="lesson-card" data-title="<?php echo mb_strtolower($baihoc['Ten'], 'UTF-8'); ?>">
                <div class="video-wrapper">
                    <video id="video-<?php echo $baihoc['BaiHocId']; ?>" 
                           controls 
                           controlslist="nodownload"
                           data-lesson-id="<?php echo $baihoc['BaiHocId']; ?>"
                           data-completed="<?php echo $isCompleted ? 'true' : 'false'; ?>">
                        <source src="/DevMaster/Images-Videos/<?php echo htmlspecialchars($baihoc['LinkVideo']); ?>" type="video/mp4">
                        Trình duyệt không hỗ trợ thẻ video.
                    </video>
                </div>
                
                <div class="lesson-info">
                    <h3 class="lesson-title"><?php echo ($index + 1) . ". " . htmlspecialchars($baihoc['Ten']); ?></h3>
                    
                    <div>
                        <div class="lesson-progress-bar">
                            <div class="lesson-progress-fill" id="prog-fill-<?php echo $baihoc['BaiHocId']; ?>" style="width: <?php echo $progressWidth; ?>%"></div>
                        </div>
                        <div class="lesson-meta">
                            <span class="prog-text" id="prog-text-<?php echo $baihoc['BaiHocId']; ?>"><?php echo $progressWidth; ?>%</span>
                            <span class="status-badge <?php if ($baihoc['TrangThai'] == 1) echo 'completed'; else echo ''; ?>" id="badge-<?php echo $baihoc['BaiHocId']; ?>">
                                <?php 
                                    if ($baihoc['TrangThai'] == 1) {
                                        echo 'Hoàn thành <i class="fas fa-check"></i>';
                                    } else {
                                        echo 'Chưa học';
                                    }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-course-alert">
                <div class="alert-content">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <h2>Nội dung đang được cập nhật</h2>
                    <p>Khóa học này hiện chưa có bài học nào trực tuyến. Vui lòng quay lại sau hoặc liên hệ ban quản trị để biết thêm chi tiết.</p>
                    <span class="coming-soon-badge">Coming Soon</span>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($totalPages > 1): ?>
    <div class="pagination-container">
        <a href="?id=<?php echo $khoaHocId; ?>&page=<?php echo $page - 1; ?>" 
           class="pagination-btn <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
            <i class="fa-solid fa-chevron-left"></i>
        </a>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?id=<?php echo $khoaHocId; ?>&page=<?php echo $i; ?>" 
               class="pagination-btn <?php echo ($page == $i) ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <a href="?id=<?php echo $khoaHocId; ?>&page=<?php echo $page + 1; ?>" 
           class="pagination-btn <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
            <i class="fa-solid fa-chevron-right"></i>
        </a>
    </div>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('searchInput');
            const lessonCards = document.querySelectorAll('.lesson-card');

            searchInput.addEventListener('input', function(e) {
                const keyword = e.target.value.toLowerCase().trim();
                lessonCards.forEach(card => {
                    const title = card.getAttribute('data-title');
                    if(title.includes(keyword)) {
                        card.style.display = 'flex'; 
                    } else {
                        card.style.display = 'none';
                    }
                });
            });

            const totalLessons = <?php echo $tongSoBai; ?>;
            let completedLessons = <?php echo $khoaHoc['BaiDaXong']; ?>;
            const videos = document.querySelectorAll('video');

            // Đọc tiến độ tổng thể hiện tại của khóa học từ server/biến PHP ban đầu
            let currentOverallPercent = <?php echo $tienDoTong; ?>;
            videos.forEach(video => {
                const lessonId = video.getAttribute('data-lesson-id');
                const isAlreadyCompleted = video.getAttribute('data-completed') === 'true';
                
                let state = {
                    maxTimeWatched: 0,
                    isCompleted: isAlreadyCompleted,
                    isSeeking: false
                };

                const progFill = document.getElementById(`prog-fill-${lessonId}`);
                const progText = document.getElementById(`prog-text-${lessonId}`);
                const badge = document.getElementById(`badge-${lessonId}`);

                // Nếu bài học này đã hoàn thành từ trước, cho phép tua thoải mái ngay từ đầu
                if (state.isCompleted) {
                    state.maxTimeWatched = 999999; 
                }

                video.addEventListener('timeupdate', () => {
                    // ĐIỀU KIỆN 1: Nếu KHÓA HỌC ĐÃ HOÀN THÀNH 100%, bỏ qua toàn bộ cơ chế chặn tua
                    if (currentOverallPercent >= 100) return;

                    // Nếu bài học này đã xong thì cũng không cần tính toán lại nữa
                    if (state.isCompleted) return; 
                    if (state.isSeeking) return;   

                    const currentTime = video.currentTime;
                    const duration = video.duration;
                    if (!duration) return;

                    // Chỉ cập nhật mốc thời gian lớn nhất xem được nếu người dùng đang xem tuyến tính (không tua vượt)
                    if (currentTime > state.maxTimeWatched && currentTime <= state.maxTimeWatched + 2) {
                        state.maxTimeWatched = currentTime;
                    }

                    let percent = (state.maxTimeWatched / duration) * 100;
                    if (percent > 100) percent = 100;
                    
                    progFill.style.width = `${percent}%`;
                    progText.innerText = `${Math.floor(percent)}%`;

                    if (!state.isCompleted) {
                        if (percent >= 98) {
                            markLessonAsCompleted(lessonId, state, badge, progFill, progText);
                            // Cập nhật lại biến local để các video khác biết khóa học đã tăng tiến độ
                            currentOverallPercent = Math.round((completedLessons / totalLessons) * 100);
                        } else if (percent > 0) {
                            badge.className = 'status-badge watching'; 
                            badge.innerHTML = 'Đang học <i class="fas fa-spinner fa-spin"></i>';
                        }
                    }
                });

                video.addEventListener('seeking', () => {
                    state.isSeeking = true;
                });

                video.addEventListener('seeked', () => {
                    state.isSeeking = false;

                    // ĐIỀU KIỆN 2: Không giật ngược video (reset) lại nữa!
                    // Nếu khóa học đã 100% hoặc bài học đã xong, cho tua thoải mái
                    if (currentOverallPercent >= 100 || state.isCompleted) return;

                    // Nếu học viên tua vượt quá phân đoạn đã xem thực tế:
                    if (video.currentTime > state.maxTimeWatched + 1) { 
                        // Không ép tua ngược lại nữa, giữ nguyên vị trí cho họ xem thử đoạn đó.
                        // Nhưng hệ thống sẽ KHÔNG cập nhật `state.maxTimeWatched` ở sự kiện `timeupdate` 
                        // cho đến khi họ quay lại xem tiếp từ mốc đã tích lũy cũ.
                        console.log("Chế độ xem thử: Tiến độ bài học tạm dừng tăng cho đến khi bạn xem hết các đoạn trước.");
                    }
                });
            });

            function markLessonAsCompleted(lessonId, state, badge, progFill, progText) {
                state.isCompleted = true;
                progFill.style.width = '100%';
                progText.innerText = '100%';
                badge.className = 'status-badge completed';
                badge.innerHTML = 'Hoàn thành <i class="fas fa-check"></i>';

                completedLessons++;
                let overallPercent = Math.round((completedLessons / totalLessons) * 100);
                if (overallPercent > 100) overallPercent = 100;
                
                localStorage.setItem('course_progress_<?php echo $khoaHocId; ?>', overallPercent);

                document.getElementById('overall-percent-text').innerText = overallPercent;
                document.getElementById('overall-progress-bar').style.width = overallPercent + '%';

                fetch('/DevMaster/Configs/AjaxUpdateProgress.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=complete_lesson&baihoc_id=${lessonId}&khoahoc_id=<?php echo $khoaHocId; ?>&tiendo_tong=${overallPercent}`
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Đã đồng bộ tiến độ lên máy chủ: ', data.message);
                })
                .catch(error => console.error('Lỗi khi đồng bộ tiến độ:', error));
            }
        });
    </script>
    <?php include '../Includes/Footer.php'; ?>
    <script src="../Assets/Javascript.js"></script>
</body>
</html>