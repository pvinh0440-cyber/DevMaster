<script src="../Assets/Javascript.js"></script>
<?php
// --- BỔ SUNG ĐOẠN NÀY VÀO ĐẦU FILE REGISTER.PHP ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../Database.php';

$field_errors = [
    'TenDangNhap' => '',
    'SDT' => '',
    'HoTen' => '',
    'Gmail' => '',
    'MatKhau' => '',
    'XacNhanMatKhau' => ''
];
$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $hoTen = trim($_POST['HoTen']);
    $tenDangNhap = trim($_POST['TenDangNhap']);
    $gmail = trim($_POST['Gmail']);
    $sdt = trim($_POST['SDT']);
    $matKhau = $_POST['MatKhau'];
    $xacNhanMatKhau = $_POST['XacNhanMatKhau'];

    // Kiểm tra rỗng cho từng trường bắt buộc
    if (empty($tenDangNhap)) $field_errors['TenDangNhap'] = "Tên đăng nhập không được để trống.";
    if (empty($sdt)) $field_errors['SDT'] = "Số điện thoại không được để trống.";
    if (empty($hoTen)) $field_errors['HoTen'] = "Vui lòng nhập họ và tên của bạn.";
    if (empty($gmail)) $field_errors['Gmail'] = "Gmail bắt buộc không được bỏ trống.";
    if (empty($matKhau)) $field_errors['MatKhau'] = "Mật khẩu không được để trống.";

    // Validate định dạng số điện thoại (Đầu 01-09, độ dài 9-11 số)
    $sdt_clean = preg_replace('/[^\d]/', '', $sdt);
    if (!empty($sdt) && !preg_match('/^0[1-9][0-9]{7,9}$/', $sdt_clean)) {
        $field_errors['SDT'] = "Số điện thoại không hợp lệ.";
    }

    // Validate Gmail
    if (!empty($gmail) && !filter_var($gmail, FILTER_VALIDATE_EMAIL)) {
        $field_errors['Gmail'] = "Định dạng email không hợp lệ.";
    }

    // Validate mật khẩu
    if (!empty($matKhau) && strlen($matKhau) < 6) {
        $field_errors['MatKhau'] = "Mật khẩu phải chứa ít nhất 6 ký tự.";
    }
    if (!empty($matKhau) && !empty($xacNhanMatKhau) && $matKhau !== $xacNhanMatKhau) {
        $field_errors['XacNhanMatKhau'] = "Mật khẩu xác nhận không trùng khớp.";
    }

    // Kiểm tra xem có bất kỳ lỗi nhập liệu nào không
    $has_error = false;
    foreach ($field_errors as $err) {
        if (!empty($err)) { $has_error = true; break; }
    }

    // NẾU KHÔNG CÓ LỖI (has_error == false) -> Tiến hành kiểm tra DB và lưu
    if (!$has_error) {
        try {
            // Kiểm tra xem TenDangNhap hoặc Gmail đã tồn tại chưa
            $checkQuery = "SELECT TenDangNhap, Gmail FROM dangky WHERE TenDangNhap = :tenDangNhap OR Gmail = :gmail LIMIT 1";
            $checkStmt = $connect->prepare($checkQuery);
            $checkStmt->execute([
                ':tenDangNhap' => $tenDangNhap,
                ':gmail' => $gmail
            ]);
            $existingUser = $checkStmt->fetch();

            if ($existingUser) {
                if ($existingUser['TenDangNhap'] === $tenDangNhap) {
                    $field_errors['TenDangNhap'] = "Tên đăng nhập này đã được sử dụng.";
                }
                if ($existingUser['Gmail'] === $gmail) {
                    $field_errors['Gmail'] = "Địa chỉ Email này đã được đăng ký.";
                }
            } else {
                // Mã hóa mật khẩu bảo mật chuẩn Bcrypt quốc tế trước khi lưu vào SQL
                $hashedPassword = password_hash($matKhau, PASSWORD_BCRYPT);
                
                // Chuẩn bị câu lệnh Insert tương thích 100% với cấu trúc bảng `dangky`
                $insertQuery = "INSERT INTO dangky (HoTen, TenDangNhap, Gmail, SDT, MatKhau, NgayDangKy, TrangThai) 
                VALUES (:hoTen, :tenDangNhap, :gmail, :sdt, :matKhau, NOW(), 'Online')";
                
                $insertStmt = $connect->prepare($insertQuery);
                $result = $insertStmt->execute([
                    ':hoTen' => $hoTen,
                    ':tenDangNhap' => $tenDangNhap,
                    ':gmail' => $gmail,
                    ':sdt' => !empty($sdt_clean) ? $sdt_clean : null,
                    ':matKhau' => $hashedPassword
                ]);

                if ($result) {
                    // 1. Lấy STT (ID) của học viên vừa được chèn vào database
                    $newUserId = $connect->lastInsertId();

                    // 2. Thiết lập Session đăng nhập tự động đồng bộ với hệ thống (Login.php)
                    $_SESSION['UserLoggedIn'] = true;
                    $_SESSION['UserId'] = $newUserId;
                    $_SESSION['Username'] = $tenDangNhap;
                    $_SESSION['FullName'] = $hoTen;
                    $_SESSION['Email'] = $gmail;
                    $_SESSION['UserActiveName'] = $tenDangNhap;
                    $_SESSION['IsAdmin'] = false;

                    $success = "Đăng ký tài khoản thành công! Hệ thống đang tự động đăng nhập...";
                    echo "<script>
                        window.location.href = '/DevMaster/Index.php';
                    </script>";
                } else {
                    $errors[] = "Đã xảy ra lỗi trong quá trình ghi nhận hệ thống. Vui lòng thử lại.";
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage();
        }
    }
}
?>

<link rel="stylesheet" href="/DevMaster/assets/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<main class="register-master-container">
    <div class="split-screen-wrapper">
        
        <div class="visual-panel">
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
                            <p><span class="c-purple">const</span> student = <span class="c-blue">new</span> <span class="c-yellow">MasterDeveloper</span>();</p>
                            <p>student.<span class="c-green">learn</span>(<span class="c-orange">"Full-Stack 2026"</span>);</p>
                            <p>student.<span class="c-green">status</span> = <span class="c-orange">"Chinh phục Thế giới Code"</span>;</p>
                        </div>
                    </div>
                </div>

                <div class="motivational-text">
                    <h3>Bắt đầu hành trình học tập</h3>
                    <p>Tạo tài khoản miễn phí để truy cập hàng ngàn khóa học chất lượng cao chuyên sâu về lập trình thực chiến.</p>
                </div>
            </div>
        </div>

        <div class="form-panel">
            <div class="form-scrollable-content">
                
                <div class="form-header-heading">
                    <h1>Tạo tài khoản</h1>
                    <p>Đăng ký để bắt đầu học tập ngay hôm nay</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert-box error-alert animate-fade-in">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <?php foreach ($errors as $error) echo "<p>$error</p>"; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert-box success-alert animate-fade-in">
                        <i class="fas fa-check-circle"></i>
                        <p><?php echo $success; ?></p>
                    </div>
                <?php endif; ?>

                <form id="registerForm" action="" method="POST" class="ergonomic-form" novalidate>
                    
                    <div class="input-grid-row">
                        <div class="input-field-group">
                            <label for="TenDangNhap">Tên đăng nhập <span class="req">*</span></label>
                            <div class="input-wrapper-iconic">
                                <i class="fas fa-user field-icon"></i>
                                <input type="text" id="TenDangNhap" name="TenDangNhap" value="<?php echo isset($_POST['TenDangNhap']) ? htmlspecialchars($_POST['TenDangNhap']) : ''; ?>" required>
                            </div>
                            <span class="inline-error-msg"><?php echo $field_errors['TenDangNhap']; ?></span>
                        </div>

                        <div class="input-field-group">
                            <label for="SDT">Số điện thoại <span class="req">*</span></label>
                            <div class="input-wrapper-iconic">
                                <i class="fas fa-phone field-icon"></i>
                                <input type="tel" id="SDT" name="SDT" value="<?php echo isset($_POST['SDT']) ? htmlspecialchars($_POST['SDT']) : ''; ?>" required>
                            </div>
                            <span id="sdtWarnText" class="inline-error-msg"><?php echo $field_errors['SDT']; ?></span>
                        </div>
                    </div>

                    <div class="input-field-group">
                        <label for="HoTen">Tên của bạn <span class="req">*</span></label>
                        <div class="input-wrapper-iconic">
                            <i class="fas fa-id-card field-icon"></i>
                            <input type="text" id="HoTen" name="HoTen" value="<?php echo isset($_POST['HoTen']) ? htmlspecialchars($_POST['HoTen']) : ''; ?>" required>
                        </div>
                        <span class="inline-error-msg"><?php echo $field_errors['HoTen']; ?></span>
                    </div>

                    <div class="input-field-group">
                        <label for="Gmail">Gmail <span class="req">*</span></label>
                        <div class="input-wrapper-iconic">
                            <i class="fas fa-envelope field-icon"></i>
                            <input type="email" id="Gmail" name="Gmail" value="<?php echo isset($_POST['Gmail']) ? htmlspecialchars($_POST['Gmail']) : ''; ?>" required>
                        </div>
                        <span class="inline-error-msg"><?php echo $field_errors['Gmail']; ?></span>
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
                        <div class="password-strength-meter">
                            <div id="strengthBar" class="meter-bar"></div>
                        </div>
                        <span id="strengthText" class="strength-label-text">Độ bảo mật: Chưa xác định</span>
                        <span class="inline-error-msg"><?php echo $field_errors['MatKhau']; ?></span>
                    </div>

                    <div class="input-field-group">
                        <label for="XacNhanMatKhau">Xác nhận mật khẩu <span class="req">*</span></label>
                        <div class="input-wrapper-iconic">
                            <i class="fas fa-shield-alt field-icon"></i>
                            <input type="password" id="XacNhanMatKhau" name="XacNhanMatKhau" required>
                            <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('XacNhanMatKhau', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <span id="matchText" class="inline-error-msg"><?php echo $field_errors['XacNhanMatKhau']; ?></span>
                    </div>

                    <button type="submit" class="btn-submit-premium">
                        <span>Đăng ký</span>
                        <div class="btn-glow-effect"></div>
                    </button>

                    <div class="form-footer-routing">
                        <p>Đã có tài khoản? <a href="Login.php" class="link-highlight">Đăng nhập ngay</a></p>
                    </div>
                </form>

            </div>
        </div>

    </div>
</main>