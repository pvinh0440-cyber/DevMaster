<?php
// THÊM ĐOẠN NÀY VÀO ĐẦU FILE HEADER: Bắt buộc phải có để nhận diện người dùng đã đăng nhập!
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// KHỞI TẠO BIẾN $CONN NGAY TẠI ĐÂY
$conn = new mysqli("localhost", "root", "", "devmaster");
$conn->set_charset("utf8mb4");

// Kiểm tra nếu kết nối lỗi thì báo ngay
if ($conn->connect_error) {
    die("Kết nối database thất bại: " . $conn->connect_error);
}

$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

// 1. Truy vấn lấy toàn bộ Danh Mục và Nhóm Khóa Học trực thuộc
$sql = "SELECT dm.DanhMucId, dm.TenDanhMuc, nkh.NhomKhoaHocId, nkh.TenNhom 
        FROM danhmuc dm
        LEFT JOIN nhomkhoahoc nkh ON dm.DanhMucId = nkh.DanhMucId
        ORDER BY dm.DanhMucId ASC, nkh.NhomKhoaHocId ASC";

$result = $conn->query($sql);

$menuData = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $dmId = $row['DanhMucId'];
        if (!isset($menuData[$dmId])) {
            $menuData[$dmId] = [
                'TenDanhMuc' => $row['TenDanhMuc'],
                'Nhom' => []
            ];
        }
        if ($row['TenNhom'] !== null) {
            $menuData[$dmId]['Nhom'][] = [
                'NhomKhoaHocId' => $row['NhomKhoaHocId'],
                'TenNhom' => $row['TenNhom']
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DEV MASTER</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/DevMaster/Assets/Style.css">
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const searchInput = document.getElementById("searchInput");
            const searchBtn = document.getElementById("searchBtn");
            const searchDropdown = document.getElementById("searchDropdown");
            const searchResults = document.getElementById("searchDropdownResults");
            const viewAllSearch = document.getElementById("viewAllSearch");

            // Hàm thực hiện tìm kiếm qua AJAX
            async function fetchSearchResults(keyword) {
                if (!keyword.trim()) {
                    searchDropdown.style.display = "none";
                    return;
                }

                try {
                    // Gọi đến file xử lý PHP (chúng ta sẽ tạo ở bước sau)
                    const response = await fetch(`/DevMaster/Configs/XuLyTimKiem.php?keyword=${encodeURIComponent(keyword)}`);
                    const data = await response.json();

                    // Xóa kết quả cũ
                    searchResults.innerHTML = "";

                    if (data.length === 0) {
                        searchResults.innerHTML = '<div style="padding: 15px; color: #888; text-align: center;">Không tìm thấy khóa học nào phù hợp.</div>';
                        searchDropdown.style.display = "block";
                        return;
                    }

                    // Render tối đa 8 kết quả chuẩn giao diện 5 sao
                    data.forEach(course => {
                        // Tạo số lượng học viên ngẫu nhiên (RNG) từ 1000 đến 5000 như bạn yêu cầu
                        const randomHocVien = Math.floor(Math.random() * (5000 - 1000 + 1)) + 1000;
                        
                        // Định dạng giá tiền (Ví dụ: 500.000đ hoặc Miễn phí)
                        const priceDisplay = parseFloat(course.Gia) === 0 ? "Miễn phí" : parseInt(course.Gia).toLocaleString('vi-VN') + 'đ';

                        const itemLink = document.createElement("a");
                        itemLink.href = `/DevMaster/Pages/TatCaKhoaHoc.php?click_id=${course.KhoaHocId}`;
                        itemLink.className = "live-search-item";
                        itemLink.style.cssText = `
                            display: flex;
                            gap: 15px;
                            padding: 12px;
                            border-bottom: 1px solid #f1f1f1;
                            text-decoration: none;
                            color: inherit;
                            transition: background 0.2s;
                        `;
                        
                        // Hiệu ứng hover cho item
                        itemLink.addEventListener('mouseenter', () => itemLink.style.backgroundColor = '#f9f9f9');
                        itemLink.addEventListener('mouseleave', () => itemLink.style.backgroundColor = 'transparent');

                        itemLink.innerHTML = `
                            <div class="search-img-wrapper" style="width: 85px; height: 55px; flex-shrink: 0; border-radius: 6px; overflow: hidden;">
                                <img src="/DevMaster/${course.Anh || 'default.jpg'}" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='/DevMaster/Assets/Images/default.jpg'">
                            </div>
                            
                            <div class="search-info-wrapper" style="flex-grow: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; justify-content: center;">
                                
                                <h4 style="margin: 0; font-size: 14px; font-weight: 600; color: #222; line-height: 1.4; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    ${course.Ten}
                                </h4>
                                
                                <div style="font-size: 11px; color: #666; display: flex; align-items: center; gap: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; width: 100%;">
                                    <span style="color: #ff9800; font-weight: bold; flex-shrink: 0;"><i class="fa-solid fa-star"></i> 4.6</span>
                                    <span style="flex-shrink: 0;">•</span>
                                    <span style="flex-shrink: 0;">${randomHocVien} học viên</span>
                                    <span style="flex-shrink: 0;">•</span>
                                    <span style="overflow: hidden; text-overflow: ellipsis;">${course.TenGiangVien || 'DevMaster Ed'}</span>
                                </div>
                                
                                <div class="search-price-wrapper" style="margin-top: 2px;">
                                    <span style="font-size: 14px; font-weight: 700; color: #503ce7;">${priceDisplay}</span>
                                </div>
                            </div>
                        `;
                        searchResults.appendChild(itemLink);
                    });

                    // Cập nhật link cho nút "Xem tất cả kết quả" ở cuối bảng
                    viewAllSearch.href = `/DevMaster/Pages/TatCaKhoaHoc.php?keyword=${encodeURIComponent(keyword)}`;
                    searchDropdown.style.display = "block";

                } catch (error) {
                    console.error("Lỗi Live Search:", error);
                }
            }

            // Bắt sự kiện gõ chữ trên ô input (Debounce nhẹ để tránh gửi request liên tục)
            let typingTimer;
            searchInput.addEventListener("input", function () {
                clearTimeout(typingTimer);
                const keyword = this.value;
                typingTimer = setTimeout(() => {
                    fetchSearchResults(keyword);
                }, 300); // Đợi 300ms sau khi dừng gõ mới gọi DB
            });

            // Thay đổi tham số truyền đi từ 'search' thành 'keyword' cho đồng bộ toàn hệ thống
            searchBtn.addEventListener("click", function () {
                const keyword = searchInput.value.trim();
                if (keyword) {
                    window.location.href = `/DevMaster/Pages/TatCaKhoaHoc.php?keyword=${encodeURIComponent(keyword)}`;
                }
            });

            // Bấm Enter trong ô input cũng search luôn
            searchInput.addEventListener("keypress", function (e) {
                if (e.key === "Enter") {
                    searchBtn.click();
                }
            });

            // THÊM MỚI: Khi click vào ô tìm kiếm, nếu đang có chữ thì hiện ngay lại bảng kết quả dropdown
            searchInput.addEventListener("focus", function () {
                const keyword = this.value.trim();
                const dropdown = document.getElementById("liveSearchDropdown"); // Hãy đảm bảo ID này trùng với khối dropdown của bạn
                if (keyword.length > 0 && dropdown) {
                    dropdown.style.display = "block";
                }
            });

            // Ẩn bảng khi bấm ra ngoài vùng tìm kiếm
            document.addEventListener("click", function (e) {
                if (!document.getElementById("liveSearchWrapper").contains(e.target)) {
                    searchDropdown.style.display = "none";
                }
            });
        });
    </script>
</head>
<body>

<header class="master-header">
    <div class="header-inner">
        <div class="left-box">
            <div class="logo-wrapper">
                <div class="logo-square">
                    <i class="fa-solid fa-code"></i>
                </div>
                <div class="logo-brand">
                    DEV<span>MASTER</span>
                </div>
            </div>
            
            <nav class="header-nav">
                <div class="nav-btn category-btn">
                    <i class="fa-solid fa-bars-staggered"></i>
                    <span>Danh mục</span>
                    <i class="fa-solid fa-chevron-down arrow-toggle"></i>
                    <div class="mega-dropdown-wrapper">
                        <div class="mega-dropdown-container">
                            
                            <ul class="mega-lvl1-list">
                                <?php 
                                $isFirst = true;
                                foreach ($menuData as $dmId => $data): 
                                ?>
                                    <li class="mega-lvl1-item <?php echo $isFirst ? 'active-lvl-1' : ''; ?>" data-target="panel-dm-<?php echo $dmId; ?>">
                                        <a href="/DevMaster/Pages/TatCaKhoaHoc.php?danhmuc_id=<?php echo $dmId; ?>" class="lvl1-link" style="text-decoration: none; color: inherit; display: block; width: 100%;">
                                            <?php echo htmlspecialchars($data['TenDanhMuc']); ?>
                                        </a>
                                        <i class="fa-solid fa-chevron-right lvl1-arrow"></i>
                                    </li>
                                <?php 
                                    $isFirst = false;
                                endforeach; 
                                ?>
                            </ul>

                            <div class="mega-panels-container">
                                <?php 
                                $isFirst = true;
                                foreach ($menuData as $dmId => $data): 
                                ?>
                                    <div class="mega-sub-panel <?php echo $isFirst ? 'visible-panel' : ''; ?>" id="panel-dm-<?php echo $dmId; ?>">
                                        <div class="panel-grid">
                                            <?php if (!empty($data['Nhom'])): ?>
                                                <?php foreach ($data['Nhom'] as $nhom): ?>
                                                    <a href="/DevMaster/Pages/TatCaKhoaHoc.php?nhom_id=<?php echo $nhom['NhomKhoaHocId']; ?>" class="panel-item-link">
                                                        <?php echo htmlspecialchars($nhom['TenNhom']); ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="no-data-msg">Đang cập nhật nhóm khóa học...</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php 
                                    $isFirst = false;
                                endforeach; 
                                ?>
                            </div>

                        </div>
                    </div>
                </div>
                <a href="/DevMaster/Index.php" class="nav-link">Trang chủ</a>
            </nav>
        </div>

        <div class="center-box">
            <div class="search-container" style="position: relative;" id="liveSearchWrapper">
                <i class="fa-solid fa-magnifying-glass s-icon"></i>
                <input type="text" id="searchInput" placeholder="Nhập từ khóa khóa học...">
                <button class="s-btn" id="searchBtn">Tìm kiếm</button>

                <div class="search-dropdown-panel" id="searchDropdown">
                    <div class="search-dropdown-scroll" id="searchDropdownResults">
                        </div>
                    <div class="search-dropdown-footer">
                        <a href="#" id="viewAllSearch" class="view-all-btn">Xem tất cả kết quả <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        </div>

        <div class="right-box">
            <?php if (!isset($_SESSION['IsAdmin']) || $_SESSION['IsAdmin'] !== true): ?>
                <a href="/DevMaster/Pages/GioHang.php" class="cart-pill-link">
                    <div class="cart-pill">
                        <i class="fa-solid fa-shopping-cart"></i>
                        <span id="cart-badge" class="badge-count"><?php echo $cartCount; ?></span>
                        <span class="cart-text">Giỏ hàng</span>
                    </div>
                </a>
            <?php endif; ?>

            <div class="auth-group">
                <?php if (isset($_SESSION['UserLoggedIn']) && $_SESSION['UserLoggedIn'] === true): ?>
                    <div class="user-profile-menu-wrapper" id="profileDropdownBtn">
                        <div class="profile-trigger-pill">
                            <div class="avatar-circle-icon">
                                <?php 
                                    $firstLetter = mb_substr($_SESSION['FullName'], 0, 1, 'UTF-8');
                                    echo strtoupper($firstLetter); 
                                ?>
                            </div>
                            <span class="user-display-name"><?php echo htmlspecialchars($_SESSION['FullName']); ?></span>
                            <i class="fa-solid fa-chevron-down arrow-indicator"></i>
                        </div>
                        
                        <div class="dropdown-premium-panel" id="dropdownPanel">
                            <div class="dropdown-header-info">
                                <p class="u-name"><?php echo htmlspecialchars($_SESSION['FullName']); ?></p>
                                <p class="u-email"><?php echo htmlspecialchars($_SESSION['Email'] ?? 'Chưa cập nhật'); ?></p>
                            </div>
                            <div class="dropdown-divider"></div>
                            <div class="user-dropdown-menu">
                                <?php if (isset($_SESSION['IsAdmin']) && $_SESSION['IsAdmin'] === true): ?>
                                    <a href="/DevMaster/Admin/Dashboard.php" class="dropdown-item-link">
                                        <i class="fa-solid fa-chart-pie"></i> Dashboard
                                    </a>
                                    <a href="/DevMaster/Admin/QuanLyHocVien.php" class="dropdown-item-link">
                                        <i class="fa-solid fa-graduation-cap"></i> Quản lý học viên
                                    </a>
                                    <a href="/DevMaster/Admin/QuanLyKhoaHoc.php" class="dropdown-item-link">
                                        <i class="fa-solid fa-book"></i> Quản lý khóa học
                                    </a>
                                    <a href="/DevMaster/Admin/QuanTriVien.php" class="dropdown-item-link">
                                        <i class="fa-solid fa-user-shield"></i> Quản trị viên
                                    </a>
                                <?php else: ?>
                                    <a href="/DevMaster/Pages/KhoaHocCuaToi.php" class="dropdown-item-link">
                                        <i class="fa-solid fa-graduation-cap"></i> Khóa học của tôi
                                    </a>
                                    <a href="/DevMaster/Pages/DonHang.php" class="dropdown-item-link">
                                        <i class="fa-solid fa-file-invoice-dollar"></i> Đơn hàng của bạn
                                    </a>
                                    <a href="/DevMaster/Pages/Profile.php" class="dropdown-item-link">
                                        <i class="fa-solid fa-user-gear"></i> Hồ sơ cá nhân
                                    </a>
                                <?php endif; ?>
                                <div class="dropdown-divider"></div>
                                <a href="/DevMaster/Auth/Logout.php" class="dropdown-item-link text-danger">
                                    <i class="fa-solid fa-right-from-bracket"></i> Đăng xuất
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="/DevMaster/Auth/Register.php" class="reg-link">Đăng ký</a>
                    <a href="/DevMaster/Auth/Login.php" class="login-pill">Đăng nhập</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>