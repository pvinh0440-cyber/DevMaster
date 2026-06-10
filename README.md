# 🎓 Hệ thống Website Khóa Học Lập Trình Online DevMaster

Chào mừng đến với dự án **DevMaster** - Hệ thống website bán khóa học lập trình online tích hợp giỏ hàng, thanh toán, quản lý tiến độ học tập và cổng quản trị nội bộ dành cho nền tảng đào tạo kỹ năng số. Dự án được xây dựng theo mô hình **PHP**, sử dụng **MySQL**, giao diện responsive hiện đại và hỗ trợ xử lý thanh toán trực tuyến.

---

## 🌟 Các tính năng chính

### 1. Website Học viên (Frontend)

* **Trang chủ & Giới thiệu khóa học:** Hiển thị giao diện đẹp mắt, trình bày nổi bật các khóa học, giảng viên và nội dung đào tạo.
* **Danh mục khóa học động:** Sắp xếp khóa học theo danh mục, nhóm chuyên đề và hiển thị danh sách khóa học từ cơ sở dữ liệu.
* **Tìm kiếm khóa học:** Hỗ trợ tìm kiếm nhanh theo tên khóa học, giúp học viên dễ dàng lọc đúng nội dung cần học.
* **Giỏ hàng khóa học:** Cho phép học viên thêm nhiều khóa học vào giỏ trước khi thanh toán.
* **Thanh toán trực tuyến:** Xử lý đặt mua khóa học, tạo mã đơn hàng và đồng bộ trạng thái thanh toán.
* **Khu vực học tập cá nhân:** Học viên có thể xem khóa học đã mua, theo dõi tiến độ học và truy cập bài học.
* **Xem bài học video:** Mỗi khóa học có thể gắn nhiều bài học kèm video để học viên theo dõi theo từng chương.
* **Tài khoản người dùng:** Hỗ trợ đăng ký, đăng nhập, đăng xuất và quản lý thông tin cá nhân.

### 2. Trang Quản trị & Nhân viên (Backend)

* **Bảng điều khiển (Dashboard):** Thống kê tổng số học viên, số lượng khóa học, đơn hàng và doanh thu hệ thống.
* **Quản lý Khóa học:**

  * Thêm, sửa, xóa khóa học.
  * Cập nhật ảnh đại diện, giá bán, giảng viên và trạng thái hiển thị.
  * Đánh dấu khóa học nổi bật để hiển thị ở vị trí ưu tiên.
* **Quản lý Danh mục & Nhóm khóa học:** Tổ chức nội dung đào tạo theo từng ngành học và chuyên đề.
* **Quản lý Bài học:** Thêm các bài học video vào từng khóa học.
* **Quản lý Học viên:** Theo dõi danh sách học viên, trạng thái học tập và dữ liệu đăng ký.
* **Quản trị viên hệ thống:** Tạo và quản lý tài khoản admin, phân quyền sử dụng hệ thống.
* **Quản lý Đơn hàng:** Theo dõi danh sách đơn hàng, trạng thái thanh toán và chi tiết khóa học đã mua.
* **Quản lý Tiến độ học viên:** Ghi nhận trạng thái hoàn thành bài học của từng học viên theo từng khóa học.

---

## 📂 Cấu trúc thư mục dự án

```text
DevMaster/
│
├── Admin/               # Khu vực quản trị hệ thống
│   ├── Dashboard.php
│   ├── QuanLyHocVien.php
│   ├── QuanLyKhoaHoc.php
│   ├── QuanTriVien.php
│   ├── ThemBaiHoc.php
│   ├── ThemKhoaHoc.php
│   └── ...
├── Assets/              # CSS, JavaScript, tài nguyên giao diện
│   ├── Javascript.js
│   └── Style.css
├── Auth/                # Đăng nhập, đăng ký, đăng xuất
│   ├── Login.php
│   ├── Register.php
│   └── Logout.php
├── Configs/             # Xử lý tìm kiếm, thanh toán, webhook, cập nhật tiến độ
│   ├── AjaxUpdateProgress.php
│   ├── ProcessCheckout.php
│   ├── WebhookPayment.php
│   └── XuLyTimKiem.php
├── Controllers/        # Controller xử lý logic giỏ hàng và điều hướng
│   └── CartController.php
├── Includes/           # Thành phần dùng chung như header, footer
│   ├── Header.php
│   └── Footer.php
├── Pages/              # Các trang chức năng cho học viên
│   ├── TatCaKhoaHoc.php
│   ├── GioHang.php
│   ├── ThanhToan.php
│   ├── KhoaHocCuaToi.php
│   ├── VaoHocNgay.php
│   ├── DonHang.php
│   └── Profile.php
├── Images-Videos/      # Hình ảnh khóa học và video bài học
├── Database.php        # File kết nối cơ sở dữ liệu
├── Index.php           # File định tuyến chính của ứng dụng
├── devmaster.sql       # File lược đồ cơ sở dữ liệu MySQL
└── README.md           # Tài liệu hướng dẫn cài đặt và sử dụng
```

---

## 🛠 Hướng dẫn Cài đặt & Cấu hình

### 1. Yêu cầu hệ thống

* Máy chủ web: **Apache** (khuyên dùng XAMPP, Laragon hoặc tương đương).
* Phiên bản PHP: **7.4 trở lên**.
* Cơ sở dữ liệu: **MySQL**.
* Hỗ trợ: **PDO** hoặc **MySQLi**, session, file upload và xử lý video.

### 2. Các bước triển khai

1. **Tải mã nguồn:** Sao chép thư mục dự án `DevMaster` vào thư mục chạy web của bạn, ví dụ `C:\xampp\htdocs\` trên Windows.
2. **Khởi tạo cơ sở dữ liệu:**

   * Mở `phpMyAdmin` tại địa chỉ `http://localhost/phpmyadmin/`.
   * Tạo một cơ sở dữ liệu mới tên là `devmaster` với mã hóa `utf8mb4_unicode_ci`.
   * Import tệp `devmaster.sql` có sẵn trong thư mục dự án vào cơ sở dữ liệu vừa tạo.
3. **Cấu hình kết nối database:**

   * Mở tệp `Database.php` và cập nhật các thông số kết nối phù hợp với máy chủ của bạn như host, username, password và tên database.
4. **Kiểm tra thư mục tài nguyên:**

   * Đảm bảo thư mục `Images-Videos/` có đủ ảnh khóa học và video bài học đi kèm.
   * Kiểm tra quyền ghi nếu hệ thống cần tải lên hoặc cập nhật tệp mới.
5. **Truy cập ứng dụng:**

   * Mở trình duyệt và truy cập `http://localhost/DevMaster/` để sử dụng website học viên.
   * Để vào trang quản trị, truy cập đường dẫn quản trị trong hệ thống theo cấu hình dự án.

---

## 🔐 Tài khoản Đăng nhập Hệ thống

Để truy cập vào hệ thống quản trị nội bộ, sử dụng tài khoản admin đã có trong cơ sở dữ liệu hoặc tạo mới từ trang quản trị:

* **Tài khoản:** `admin` hoặc email quản trị được lưu trong bảng `quantriadmin`
* **Mật khẩu:** mật khẩu tương ứng trong cơ sở dữ liệu

---

## 🧱 Cấu trúc cơ sở dữ liệu chính

Dự án sử dụng các bảng chính sau:

* `danhmuc`: Danh mục lớn của khóa học.
* `nhomkhoahoc`: Nhóm khóa học thuộc từng danh mục.
* `khoahoc`: Thông tin khóa học, giá bán, giảng viên, trạng thái hiển thị.
* `baihoc`: Danh sách bài học video thuộc từng khóa học.
* `dangky`: Thông tin tài khoản học viên.
* `hangdadat`: Đơn hàng khóa học đã thanh toán.
* `chitiethangdadat`: Chi tiết các khóa học trong từng đơn hàng.
* `quantriadmin`: Tài khoản quản trị viên.
* `tiendohocvien`: Theo dõi tiến độ học tập của học viên.

---

## ✨ Ghi chú

* Dự án phù hợp cho mô hình bán khóa học trực tuyến, quản lý nội dung học tập và theo dõi tiến độ học viên.
* Các chức năng thanh toán, tìm kiếm và cập nhật tiến độ được tổ chức riêng trong thư mục `Configs/` để dễ bảo trì.
* File `devmaster.sql` đã bao gồm cấu trúc bảng và dữ liệu mẫu để bạn có thể chạy thử ngay sau khi import.

---

*Dự án được xây dựng với mục tiêu tạo ra một nền tảng học lập trình online trực quan, dễ quản lý và dễ mở rộng cho tương lai.*
