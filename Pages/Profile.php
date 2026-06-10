<?php
// Pages/Profile.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../Database.php';
include '../includes/Header.php';

// Đồng bộ biến kết nối (Ưu tiên PDO, fallback sang MySQLi)
$db = isset($connect) ? $connect : $conn;

// Bảo mật: Bắt buộc học viên phải đăng nhập
if (!isset($_SESSION['UserLoggedIn']) || $_SESSION['UserLoggedIn'] !== true) {
    echo "<script>alert('Vui lòng đăng nhập để xem thông tin tài khoản!'); window.location.href='/DevMaster/Pages/Login.php';</script>";
    exit;
}

$stt_user = isset($_SESSION['UserId']) ? intval($_SESSION['UserId']) : 0;
$success_msg = "";
$error_msg = "";

// ==========================================
// 1. XỬ LÝ SUBMIT FORM (CẬP NHẬT THÔNG TIN & ĐỔI MẬT KHẨU)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A. Xử lý cập nhật thông tin cá nhân (HoTen, SDT)
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $hoTen = trim($_POST['HoTen'] ?? '');
        $sdt = trim($_POST['SDT'] ?? '');

        if (empty($hoTen)) {
            $error_msg = "Họ và tên không được để trống!";
        } else {
            if ($db instanceof PDO) {
                $stmt = $db->prepare("UPDATE dangky SET HoTen = ?, SDT = ? WHERE STT = ?");
                $status = $stmt->execute([$hoTen, $sdt, $stt_user]);
            } else {
                $stmt = $db->prepare("UPDATE dangky SET HoTen = ?, SDT = ? WHERE STT = ?");
                $stmt->bind_param("ssi", $hoTen, $sdt, $stt_user);
                $status = $stmt->execute();
            }
            if ($status) {
                $success_msg = "Cập nhật thông tin cá nhân thành công!";
            } else {
                $error_msg = "Có lỗi xảy ra trong quá trình cập nhật.";
            }
        }
    }

    // B. Xử lý đổi mật khẩu bảo mật
    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $currentPass = $_POST['CurrentPassword'] ?? '';
        $newPass = $_POST['NewPassword'] ?? '';

        if (empty($currentPass) || empty($newPass)) {
            $error_msg = "Vui lòng nhập đầy đủ mật khẩu cũ và mới!";
        } else {
            // Lấy mật khẩu hiện tại trong DB ra đối chiếu
            $pwdQuery = "SELECT MatKhau FROM dangky WHERE STT = ?";
            $dbPass = "";
            if ($db instanceof PDO) {
                $stmt = $db->prepare($pwdQuery);
                $stmt->execute([$stt_user]);
                $dbPass = $stmt->fetchColumn();
            } else {
                $stmt = $db->prepare($pwdQuery);
                $stmt->bind_param("i", $stt_user);
                $stmt->execute();
                $stmt->bind_result($dbPass);
                $stmt->fetch();
                $stmt->close();
            }

            // Kiểm tra mật khẩu (Hỗ trợ cả text thuần hoặc hash tùy cấu trúc cũ hệ thống của bạn)
            if ($currentPass !== $dbPass && md5($currentPass) !== $dbPass && !password_verify($currentPass, $dbPass)) {
                $error_msg = "Mật khẩu hiện tại không chính xác!";
            } else {
                // Tiến hành cập nhật mật khẩu mới (giữ đồng bộ kiểu lưu trữ cũ của bạn, ở đây dùng text/MD5 an toàn)
                $finalNewPass = (strlen($dbPass) == 32) ? md5($newPass) : $newPass; 
                if ($db instanceof PDO) {
                    $stmt = $db->prepare("UPDATE dangky SET MatKhau = ? WHERE STT = ?");
                    $status = $stmt->execute([$finalNewPass, $stt_user]);
                } else {
                    $stmt = $db->prepare("UPDATE dangky SET MatKhau = ? WHERE STT = ?");
                    $stmt->bind_param("si", $finalNewPass, $stt_user);
                    $status = $stmt->execute();
                }
                if ($status) {
                    $success_msg = "Thay đổi mật khẩu thành công!";
                } else {
                    $error_msg = "Không thể cập nhật mật khẩu mới.";
                }
            }
        }
    }
}

// ==========================================
// 2. TRUY VẤN DỮ LIỆU ĐỒNG BỘ THÔNG TIN & THỐNG KÊ
// ==========================================
try {
    // A. Lấy thông tin tài khoản cơ bản từ bảng dangky
    $userQuery = "SELECT HoTen, TenDangNhap, Gmail, SDT, NgayDangKy FROM dangky WHERE STT = ?";
    $userData = [];
    if ($db instanceof PDO) {
        $stmtUser = $db->prepare($userQuery);
        $stmtUser->execute([$stt_user]);
        $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmtUser = $db->prepare($userQuery);
        $stmtUser->bind_param("i", $stt_user);
        $stmtUser->execute();
        $res = $stmtUser->get_result();
        $userData = $res->fetch_assoc();
    }

    // B. Thống kê: Khóa học đã mua hoàn thành (TrangThai = 1 ở hangdadat)
    // Đếm số lượng khóa học khác nhau được kích hoạt thành công
    $countPaidCoursesQuery = "SELECT COUNT(DISTINCT cthdd.KhoaHocId) FROM chitiethangdadat cthdd 
                              INNER JOIN hangdadat hdd ON cthdd.HangDaDatId = hdd.HangDaDatId 
                              WHERE hdd.STT = ? AND hdd.TrangThai = 1";
    $totalPaidCourses = 0;

    // C. Thống kê: Tổng đơn hàng (tất cả trạng thái 0 và 1)
    $countTotalOrdersQuery = "SELECT COUNT(HangDaDatId) FROM hangdadat WHERE STT = ?";
    $totalOrders = 0;

    // D. Thống kê: Tổng chi tiêu (Tổng tiền của toàn bộ hóa đơn đã TrangThai = 1)
    $sumSpentQuery = "SELECT SUM(TongTien) FROM hangdadat WHERE STT = ? AND TrangThai = 1";
    $totalSpent = 0;

    if ($db instanceof PDO) {
        $stmt = $db->prepare($countPaidCoursesQuery); $stmt->execute([$stt_user]); $totalPaidCourses = $stmt->fetchColumn();
        $stmt = $db->prepare($countTotalOrdersQuery); $stmt->execute([$stt_user]); $totalOrders = $stmt->fetchColumn();
        $stmt = $db->prepare($sumSpentQuery); $stmt->execute([$stt_user]); $totalSpent = $stmt->fetchColumn();
    } else {
        $stmt = $db->prepare($countPaidCoursesQuery); $stmt->bind_param("i", $stt_user); $stmt->execute(); $stmt->bind_result($totalPaidCourses); $stmt->fetch(); $stmt->close();
        $stmt = $db->prepare($countTotalOrdersQuery); $stmt->bind_param("i", $stt_user); $stmt->execute(); $stmt->bind_result($totalOrders); $stmt->fetch(); $stmt->close();
        $stmt = $db->prepare($sumSpentQuery); $stmt->bind_param("i", $stt_user); $stmt->execute(); $stmt->bind_result($totalSpent); $stmt->fetch(); $stmt->close();
    }
    $totalSpent = $totalSpent ?? 0;

} catch (Exception $e) {
    die("Lỗi đồng bộ dữ liệu hệ thống: " . $e->getMessage());
}
?>

<link rel="stylesheet" href="/DevMaster/assets/Style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<main class="profile-premium-wrapper">
    <div class="profile-container-fluid">
        
        <div class="toast-fixed-container">
            <?php if(!empty($success_msg)): ?>
                <div class="profile-alert alert-success-toast animate-toast">
                    <i class="fa-solid fa-circle-check"></i> <?php echo $success_msg; ?>
                </div>
            <?php endif; ?>
            <?php if(!empty($error_msg)): ?>
                <div class="profile-alert alert-danger-toast animate-toast">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="profile-split-layout">
            
            <aside class="profile-left-sidebar">
                <div class="sidebar-user-card">
                    <div class="avatar-wrapper-circle">
                        <i class="fa-solid fa-user-astronaut"></i>
                    </div>
                    <h3 class="user-display-name-2"><?php echo htmlspecialchars($userData['HoTen'] ?? 'Học viên DevMaster'); ?></h3>
                    <p class="user-display-email"><?php echo htmlspecialchars($userData['Gmail'] ?? '---'); ?></p>
                </div>
                
                <div class="divider-glow-line"></div>
                
                <div class="sidebar-statistics-list">
                    <div class="stat-item-row">
                        <span class="stat-label"><i class="fa-solid fa-graduation-cap"></i> Khóa học đã mua</span>
                        <span class="stat-counter-val"><?php echo $totalPaidCourses; ?></span>
                    </div>
                    <div class="stat-item-row">
                        <span class="stat-label"><i class="fa-solid fa-file-invoice-dollar"></i> Tổng đơn hàng</span>
                        <span class="stat-counter-val"><?php echo $totalOrders; ?></span>
                    </div>
                    <div class="stat-item-row">
                        <span class="stat-label"><i class="fa-solid fa-wallet"></i> Tổng chi tiêu</span>
                        <span class="stat-counter-val text-gradient-money"><?php echo number_format($totalSpent, 0, ',', '.'); ?>đ</span>
                    </div>
                    <div class="stat-item-row">
                        <span class="stat-label"><i class="fa-solid fa-calendar-days"></i> Ngày tham gia</span>
                        <span class="stat-counter-val font-date">
                            <?php 
                                $dateRaw = $userData['NgayDangKy'] ?? '2026-01-01';
                                echo date('d/m/Y', strtotime($dateRaw)); 
                            ?>
                        </span>
                    </div>
                </div>
            </aside>

            <section class="profile-right-content">
                
                <div class="management-card-panel">
                    <div class="card-panel-header">
                        <h2><i class="fa-solid fa-id-card"></i> Thông tin Cá nhân</h2>
                    </div>
                    <form action="" method="POST" class="panel-body-form">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="form-grid-half">
                            <div class="form-group-item">
                                <label>Họ và Tên</label>
                                <div class="input-with-icon-box">
                                    <i class="fa-solid fa-signature"></i>
                                    <input type="text" name="HoTen" value="<?php echo htmlspecialchars($userData['HoTen'] ?? ''); ?>" required placeholder="Nhập họ tên...">
                                </div>
                            </div>
                            <div class="form-group-item">
                                <label>Số điện thoại</label>
                                <div class="input-with-icon-box">
                                    <i class="fa-solid fa-phone"></i>
                                    <input type="text" name="SDT" value="<?php echo htmlspecialchars($userData['SDT'] ?? ''); ?>" placeholder="Chưa cập nhật số điện thoại...">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group-item width-full-span">
                            <label>Email đăng ký tài khoản</label>
                            <div class="input-with-icon-box input-disabled-style">
                                <i class="fa-solid fa-envelope"></i>
                                <input type="email" value="<?php echo htmlspecialchars($userData['Gmail'] ?? ''); ?>" disabled readonly>
                            </div>
                            <span class="small-tip-muted-text"><i class="fa-solid fa-lock"></i> Email không thể thay đổi.</span>
                        </div>

                        <div class="form-actions-right">
                            <button type="submit" class="btn-premium-submit">
                                <i class="fa-solid fa-floppy-disk"></i> Cập nhật thông tin
                            </button>
                        </div>
                    </form>
                </div>

                <div class="management-card-panel">
                    <div class="card-panel-header">
                        <h2><i class="fa-solid fa-key"></i> Đổi mật khẩu</h2>
                    </div>
                    <form action="" method="POST" class="panel-body-form">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group-item width-full-span">
                            <label>Mật khẩu hiện tại</label>
                            <div class="input-with-icon-box pass-container">
                                <i class="fa-solid fa-shield-halved"></i>
                                <input type="password" name="CurrentPassword" id="current_pwd_input" required>
                                <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility2('current_pwd_input')">
                                    <i class="fa-solid fa-eye" id="current_pwd_input_icon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group-item width-full-span" style="margin-top: 20px;">
                            <label>Mật khẩu mới</label>
                            <div class="input-with-icon-box pass-container">
                                <i class="fa-solid fa-lock-open"></i>
                                <input type="password" name="NewPassword" id="new_pwd_input" required placeholder="Tối thiểu 6 ký tự bảo mật...">
                                <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility2('new_pwd_input')">
                                    <i class="fa-solid fa-eye" id="new_pwd_input_icon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-actions-right" style="margin-top: 25px;">
                            <button type="submit" class="btn-premium-accent">
                                <i class="fa-solid fa-rotate"></i> Đổi mật khẩu
                            </button>
                        </div>
                    </form>
                </div>

                <div class="management-card-panel">
                    <div class="card-panel-header">
                        <h2><i class="fa-solid fa-bolt"></i> Truy cập nhanh</h2>
                    </div>
                    <div class="quick-access-flex-row">
                        <a href="/DevMaster/Pages/KhoaHocCuaToi.php" class="quick-link-box-item item-blue">
                            <div class="quick-icon-circle"><i class="fa-solid fa-graduation-cap"></i></div>
                            <span>Khóa học</span>
                        </a>
                        <a href="/DevMaster/Pages/DonHang.php" class="quick-link-box-item item-orange">
                            <div class="quick-icon-circle"><i class="fa-solid fa-basket-shopping"></i></div>
                            <span>Đơn hàng</span>
                        </a>
                        <a href="/DevMaster/Pages/GioHang.php" class="quick-link-box-item item-purple">
                            <div class="quick-icon-circle"><i class="fa-solid fa-cart-shopping"></i></div>
                            <span>Giỏ Hàng</span>
                        </a>
                        <a href="/DevMaster/Pages/TatCaKhoaHoc.php" class="quick-link-box-item item-green">
                            <div class="quick-icon-circle"><i class="fa-solid fa-compass"></i></div>
                            <span>Khám phá</span>
                        </a>
                    </div>
                </div>

            </section>
        </div>
    </div>
</main>

<script>
    // Tự động thêm hiệu ứng biến mất và xóa bỏ Toast sau 2 giây
    document.addEventListener("DOMContentLoaded", function() {
        const toasts = document.querySelectorAll('.animate-toast');
        toasts.forEach(toast => {
            setTimeout(() => {
                // Thêm class ẩn để kích hoạt CSS Transition mượt mà
                toast.classList.add('toast-fade-out');
                // Chờ hiệu ứng mờ dần (0.5s) chạy xong rồi xóa hẳn khỏi giao diệnDOM
                setTimeout(() => { toast.remove(); }, 500);
            }, 2000); // 2000ms = 2 giây hiển thị
        });
    });
    // Logic xử lý Toggle ẩn hiện mật khẩu chuyên nghiệp bằng Javascript
    function togglePasswordVisibility2(inputId) {
        const inputEl = document.getElementById(inputId);
        const iconEl = document.getElementById(inputId + '_icon');
        
        if (inputEl.type === 'password') {
            inputEl.type = 'text';
            iconEl.classList.remove('fa-eye');
            iconEl.classList.add('fa-eye-slash');
        } else {
            inputEl.type = 'password';
            iconEl.classList.remove('fa-eye-slash');
            iconEl.classList.add('fa-eye');
        }
    }
</script>
<script src="/DevMaster/assets/Javascript.js"></script> 
<?php 
include '../includes/Footer.php'; 
?>