<?php
// 1. Khởi tạo session để quản lý trạng thái đăng nhập hệ thống
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nếu người dùng đã đăng nhập trước đó, điều hướng thẳng về trang chủ Index
if (isset($_SESSION['UserLoggedIn']) && $_SESSION['UserLoggedIn'] === true) {
    header("Location: ../index.php");
    exit();
}

// 2. Tự khởi tạo kết nối database bằng PDO riêng cho file này để tránh xung đột hệ thống
try {
    // Cấu hình kết nối thẳng đến Database "devmaster" bằng driver PDO
    $conn = new PDO("mysql:host=localhost;dbname=devmaster;charset=utf8mb4", "root", "");
    // Bật chế độ báo lỗi ngoại lệ để dễ kiểm soát cấu trúc dữ liệu
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Kết nối cơ sở dữ liệu thất bại: " . $e->getMessage());
}

$field_errors = [
    'TenDangNhap' => '',
    'MatKhau' => ''
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tenDangNhap = trim($_POST['TenDangNhap']);
    $matKhau = $_POST['MatKhau'];

    // Kiểm tra tính hợp lệ thô của dữ liệu đầu vào
    if (empty($tenDangNhap)) {
        $field_errors['TenDangNhap'] = "Tên đăng nhập không được để trống.";
    }
    if (empty($matKhau)) {
        $field_errors['MatKhau'] = "Mật khẩu không được để trống.";
    }

    // Tiến hành xác thực sâu nếu không có trường nào trống
    if (empty($field_errors['TenDangNhap']) && empty($field_errors['MatKhau'])) {
        try {
            /**
             * BƯỚC 1: KIỂM TRA ĐĂNG NHẬP TRONG BẢNG QUẢN TRỊ ADMIN TRƯỚC
             * Quét cả TenAdmin và Gmail trong bảng quantriadmin để tránh sót tài khoản như Nuclear hay dangquoct35@gmail.com
             */
            $adminSql = "SELECT * FROM quantriadmin WHERE TenAdmin = :input OR Gmail = :input LIMIT 1";
            $stmtAdmin = $conn->prepare($adminSql);
            $stmtAdmin->execute([':input' => $tenDangNhap]);
            $adminUser = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

            if ($adminUser) {
                // Xác thực mật khẩu của Admin (Hỗ trợ đối chiếu chuỗi thô từ ảnh DB: "Gundam")
                if (password_verify($matKhau, $adminUser['MatKhau']) || $matKhau === $adminUser['MatKhau']) {
                    
                    // Thiết lập các thông số Session cấp cao dành cho Quản trị viên
                    $_SESSION['UserLoggedIn'] = true;
                    $_SESSION['UserId'] = $adminUser['AdminId']; 
                    $_SESSION['Username'] = $adminUser['TenAdmin'];
                    $_SESSION['FullName'] = $adminUser['TenAdmin'];
                    $_SESSION['Email'] = $adminUser['Gmail'];
                    $_SESSION['IsAdmin'] = true; // KÍCH HOẠT QUYỀN ADMIN TUYỆT ĐỐI
                    
                    // --- ĐOẠN CẬP NHẬT TRẠNG THÁI ONLINE CHO ADMIN THÀNH CÔNG ---
                    $updateAdminStatusSql = "UPDATE quantriadmin SET TrangThai = 'on' WHERE AdminId = ?";
                    $stmtUpdateAdminStatus = $conn->prepare($updateAdminStatusSql);
                    $stmtUpdateAdminStatus->execute([$adminUser['AdminId']]);
                    // --- KẾT THÚC ĐOẠN CẬP NHẬT ---
                    
                    // Điều hướng thẳng về trang chủ Index hoặc trang Dashboard quản trị của bạn
                    header("Location: ../Admin/Dashboard.php");
                    exit();
                } else {
                    $errors[] = "Mật khẩu Quản trị viên không chính xác.";
                }
            } else {
                /**
                 * BƯỚC 2: NẾU KHÔNG PHẢI ADMIN -> QUÉT SANG BẢNG THÀNH VIÊN ĐĂNG KÝ THƯỜNG
                 */
                $userSql = "SELECT * FROM dangky WHERE TenDangNhap = ? OR Gmail = ? LIMIT 1";
                $stmtUser = $conn->prepare($userSql);
                $stmtUser->execute([$tenDangNhap, $tenDangNhap]);
                $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    if (password_verify($matKhau, $user['MatKhau']) || $matKhau === $user['MatKhau']) {
    
                    // Thiết lập thông số Session thông thường
                    $_SESSION['UserLoggedIn'] = true;
                    $_SESSION['UserId'] = $user['STT']; 
                    $_SESSION['Username'] = $user['TenDangNhap'];
                    $_SESSION['FullName'] = $user['HoTen'];
                    $_SESSION['Email'] = $user['Gmail'];
                    $_SESSION['UserActiveName'] = $user['TenDangNhap'];
                    $_SESSION['IsAdmin'] = false; // Người dùng thường, không có quyền admin
                    
                    // ĐỒNG BỘ TRẠNG THÁI: Cập nhật trạng thái hoạt động vào Database để Admin nhìn thấy
                    // ĐỒNG BỘ TRẠNG THÁI: Cập nhật thời gian hoạt động mới nhất vào Database để Admin nhìn thấy
                    $updateStatusSql = "UPDATE dangky SET TrangThai = NOW() WHERE STT = ?";
                    $stmtUpdateStatus = $conn->prepare($updateStatusSql);
                    $stmtUpdateStatus->execute([$user['STT']]);
                    
                    header("Location: ../index.php");
                    exit();
                } else {
                        $errors[] = "Mật khẩu không chính xác. Vui lòng kiểm tra lại.";
                    }
                } else {
                    $errors[] = "Tài khoản không tồn tại trên hệ thống.";
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Lỗi kết nối hệ thống: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập Hệ Thống | DEV MASTER</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/DevMaster/assets/style.css"> 
</head>
<body>

<main class="register-master-container">
    <div class="split-screen-wrapper" style="display: flex; width: 100%; min-height: 100vh;">
        
        <div class="form-panel" style="flex: 1; max-width: 50%; width: 50%;">
            <div class="form-scrollable-content">
                
                <div class="form-header-heading animate-fade-in">
                    <h1>Chào mừng trở lại</h1>
                    <p>Vui lòng điền thông tin để đăng nhập</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert-box error-alert animate-fade-in">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <?php foreach ($errors as $error) { echo "<p style='margin:0;'>$error</p>"; } ?>
                        </div>
                    </div>
                <?php endif; ?>

                <form action="Login.php" method="POST" class="ergonomic-form" autocomplete="off" novalidate>
                    
                    <div class="input-field-group">
                        <label for="TenDangNhap">Tên đăng nhập hoặc Gmail <span class="req">*</span></label>
                        <div class="input-wrapper-iconic">
                            <i class="fas fa-user field-icon"></i>
                            <input type="text" id="TenDangNhap" name="TenDangNhap" 
                                   value="<?php echo isset($_POST['TenDangNhap']) ? htmlspecialchars($_POST['TenDangNhap']) : ''; ?>" 
                                   placeholder="Nhập tài khoản hoặc email của bạn..." required>
                        </div>
                        <span class="inline-error-msg"><?php echo $field_errors['TenDangNhap']; ?></span>
                    </div>

                    <div class="input-field-group">
                        <label for="MatKhau">Mật khẩu <span class="req">*</span></label>
                        <div class="input-wrapper-iconic">
                            <i class="fas fa-lock field-icon"></i>
                            <input type="password" id="MatKhau" name="MatKhau" required>
                            <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('MatKhau', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <span class="inline-error-msg"><?php echo $field_errors['MatKhau']; ?></span>
                    </div>

                    <button type="submit" class="btn-submit-premium">
                        <span>Đăng nhập</span>
                        <div class="btn-glow-effect"></div>
                    </button>

                    <div class="form-footer-routing">
                        <p>Chưa có tài khoản? <a href="Register.php" class="link-highlight">Đăng ký thành viên ngay</a></p>
                    </div>
                </form>

            </div>
        </div>

        <div class="visual-panel" style="flex: 1; max-width: 50%; width: 50%;">
            <div class="overlay-glow"></div>
            <div class="animated-background-shapes">
                <div class="v-shape vs1"></div>
                <div class="v-shape vs2"></div>
            </div>
            
            <div class="visual-content">
                <div class="branding-showcase">
                    <div class="brand-icon-hexa">
                        <i class="fa-solid fa-code"></i>
                    </div>
                    <h2 class="brand-name-visual">DEV<span>MASTER</span></h2>
                </div>
                
                <div class="center-illustration-wrapper">
                    <div class="mockup-code-window">
                        <div class="window-header">
                            <span class="dot red"></span>
                            <span class="dot yellow"></span>
                            <span class="dot green"></span>
                        </div>
                        <div class="code-lines">
                            <p><span class="c-purple">import</span> { <span class="c-yellow">AuthService</span> } <span class="c-purple">from</span> <span class="c-orange">"devmaster-secure"</span>;</p>
                            <p><span class="c-purple">const</span> session = <span class="c-blue">await</span> <span class="c-yellow">AuthService</span>.<span class="c-green">login</span>(user);</p>
                            <p><span class="c-blue">if</span> (session.<span class="c-green">isValid</span>) <span class="c-yellow">console</span>.<span class="c-green">log</span>(<span class="c-orange">"Welcome Back! 🚀"</span>);</p>
                        </div>
                    </div>
                </div>

                <div class="motivational-text">
                    <h3>Khởi động đam mê của bạn</h3>
                    <p>Đăng nhập để tiếp tục lộ trình học tập thực chiến, tương tác cùng các chuyên gia hàng đầu và làm chủ công nghệ.</p>
                </div>
            </div>
        </div>

    </div>
</main>

<script src="../Assets/Javascript.js"></script>
</body>
</html>
