-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 10, 2026 at 07:44 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `devmaster`
--

-- --------------------------------------------------------

--
-- Table structure for table `baihoc`
--

CREATE TABLE `baihoc` (
  `BaiHocId` int(11) NOT NULL,
  `KhoaHocId` int(11) NOT NULL,
  `Ten` varchar(255) NOT NULL,
  `LinkVideo` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `baihoc`
--

INSERT INTO `baihoc` (`BaiHocId`, `KhoaHocId`, `Ten`, `LinkVideo`) VALUES
(57, 1, 'What You\'ll Get in This Course', 'What You_ll Get in This Course.mp4'),
(58, 1, 'How Do Websites Actually Work', 'How Do Websites Actually Work.mp4'),
(59, 1, 'How Does the Internet Actually Work', 'How Does the Internet Actually Work.mp4'),
(60, 1, 'How to Get the Most Out of the Course', 'How to Get the Most Out of the Course.mp4'),
(61, 1, 'How to Get Help When You\'re Stuck', 'How to Get Help When You_re Stuck.mp4');

-- --------------------------------------------------------

--
-- Table structure for table `chitiethangdadat`
--

CREATE TABLE `chitiethangdadat` (
  `ChiTietId` int(11) NOT NULL,
  `HangDaDatId` int(11) NOT NULL,
  `KhoaHocId` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dangky`
--

CREATE TABLE `dangky` (
  `STT` int(11) NOT NULL,
  `HoTen` varchar(150) NOT NULL,
  `TenDangNhap` varchar(100) NOT NULL,
  `Gmail` varchar(150) NOT NULL,
  `SDT` varchar(20) DEFAULT NULL,
  `MatKhau` varchar(255) NOT NULL,
  `NgayDangKy` datetime DEFAULT current_timestamp(),
  `TrangThai` varchar(50) DEFAULT 'Đang học'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `danhmuc`
--

CREATE TABLE `danhmuc` (
  `DanhMucId` int(11) NOT NULL,
  `TenDanhMuc` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `danhmuc`
--

INSERT INTO `danhmuc` (`DanhMucId`, `TenDanhMuc`) VALUES
(1, 'Development'),
(2, 'IT & Software'),
(3, 'Design');

-- --------------------------------------------------------

--
-- Table structure for table `hangdadat`
--

CREATE TABLE `hangdadat` (
  `HangDaDatId` int(11) NOT NULL,
  `STT` int(11) NOT NULL,
  `NgayDat` datetime DEFAULT current_timestamp(),
  `TongTien` decimal(11,2) NOT NULL,
  `TrangThai` tinyint(1) NOT NULL DEFAULT 0,
  `MaDonHang` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `khoahoc`
--

CREATE TABLE `khoahoc` (
  `KhoaHocId` int(11) NOT NULL,
  `NhomKhoaHocId` int(11) NOT NULL,
  `Ten` varchar(255) NOT NULL,
  `Anh` varchar(500) DEFAULT NULL,
  `Gia` decimal(11,2) DEFAULT NULL,
  `TenGiangVien` varchar(255) DEFAULT NULL,
  `IsFeatured` bit(1) DEFAULT b'0',
  `TrangThai` bit(1) DEFAULT b'0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `khoahoc`
--

INSERT INTO `khoahoc` (`KhoaHocId`, `NhomKhoaHocId`, `Ten`, `Anh`, `Gia`, `TenGiangVien`, `IsFeatured`, `TrangThai`) VALUES
(1, 2, 'Khóa Học Toàn Diện Về Phát Triển Web Full-Stack', 'Images-Videos/Phát triển web.png', 50000.00, 'Dr. Angela Yu, Developer and Lead Instructor', b'1', b'0'),
(2, 2, 'Khóa Học Javascript Toàn Diện 2025', 'Images-Videos/Javascript.png', 50000.00, 'Jonas Schmedtmann', b'0', b'0'),
(3, 2, 'Khóa Học Web Developer Bootcamp 2026', 'Images-Videos/Bootcamp.png', 50000.00, 'Colt Steele', b'1', b'0'),
(4, 1, 'Khóa Học Python Toàn Diện', 'Images-Videos/Python.png', 50000.00, 'Jose Portilla', b'0', b'0'),
(5, 1, 'Hướng Dẫn Java Cho Người Mới Bắt Đầu', 'Images-Videos/Java.png', 50000.00, 'John Purcell', b'0', b'0'),
(6, 1, 'Lập Trình C++ Từ Đầu', 'Images-Videos/C.png', 50000.00, 'Tim Buchalka\'s Learn Programming Academy', b'0', b'0'),
(7, 3, 'Khóa Học Toàn Diện AWS Certified Cloud Practitioner CLF-C02 2026', 'Images-Videos/AWS.png', 50000.00, 'Stephane Maarek', b'0', b'0'),
(8, 3, 'Chứng Chỉ Kubernetes Administration (CKA)', 'Images-Videos/CKA.png', 50000.00, 'Mumshad Mannambeth', b'1', b'0'),
(9, 3, 'Cisco CCNA 200-301', 'Images-Videos/Cisco.png', 50000.00, 'Neil Anderson', b'0', b'0'),
(10, 4, 'Học Hacker Mũ Trắng Từ Con Số 0', 'Images-Videos/Hacker.png', 50000.00, 'Zaid Sabih', b'0', b'0'),
(11, 4, 'Wireshark: Phân Tích Gói Tin và Hacking Mũ Trắng', 'Images-Videos/Wireshark.png', 50000.00, 'David Bombal', b'1', b'0'),
(12, 4, 'Khóa Học An Ninh Mạng Toàn Diện', 'Images-Videos/Hacker2.png', 50000.00, 'Nathan House', b'0', b'0'),
(13, 5, 'Complete Blender Creator: Tạo Hình 3D', 'Images-Videos/Blender.png', 50000.00, 'GameDev.tv Team', b'0', b'0'),
(14, 5, 'Khóa Học Adobe Effects CC Toàn Diện', 'Images-Videos/Adobe.png', 50000.00, 'Louay Zambarakji', b'0', b'0'),
(15, 5, 'Maya Cho Người Mới Bắt Đầu', 'Images-Videos/Maya.png', 50000.00, 'Video School', b'1', b'0'),
(16, 6, 'Khóa Học Pixel Art Chuyên Sâu', 'Images-Videos/Pixel.png', 50000.00, 'Mislav Majdandžić', b'0', b'0'),
(17, 6, 'Hiệu Ứng Hình Ảnh Cho Game Trong Unity', 'Images-Videos/Effect.png', 50000.00, 'Gabriel Aguiar', b'1', b'0'),
(18, 6, 'Unreal Engine: Tạo Cảnh Quan Thế Giới Mở', 'Images-Videos/UE.png', 50000.00, 'Greg Wondra', b'0', b'0');

-- --------------------------------------------------------

--
-- Table structure for table `nhomkhoahoc`
--

CREATE TABLE `nhomkhoahoc` (
  `NhomKhoaHocId` int(11) NOT NULL,
  `DanhMucId` int(11) NOT NULL,
  `TenNhom` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `nhomkhoahoc`
--

INSERT INTO `nhomkhoahoc` (`NhomKhoaHocId`, `DanhMucId`, `TenNhom`) VALUES
(1, 1, 'Programming Languages'),
(2, 1, 'Web Development'),
(3, 2, 'IT Certifications'),
(4, 2, 'Network & Security'),
(5, 3, '3D & Animation'),
(6, 3, 'Game Design');

-- --------------------------------------------------------

--
-- Table structure for table `quantriadmin`
--

CREATE TABLE `quantriadmin` (
  `AdminId` int(11) NOT NULL,
  `TenAdmin` varchar(150) NOT NULL,
  `Gmail` varchar(150) NOT NULL,
  `MatKhau` varchar(255) NOT NULL,
  `TrangThai` varchar(10) DEFAULT 'On',
  `ViTri` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quantriadmin`
--

INSERT INTO `quantriadmin` (`AdminId`, `TenAdmin`, `Gmail`, `MatKhau`, `TrangThai`, `ViTri`) VALUES
(1, 'Phạm Quang Vinh', 'pvinh0440@gmail.com', 'Shisha', 'On', 1),
(2, 'Đặng Quốc Trung', 'dangquoct35@gmail.com', 'Gundam', 'On', 1),
(5, 'Nguyễn Thị Ngoan', 'nn9499008@gmail.com', 'Ngu', 'off', 0);

-- --------------------------------------------------------

--
-- Table structure for table `tiendohocvien`
--

CREATE TABLE `tiendohocvien` (
  `TienDoId` int(11) NOT NULL,
  `STT` int(11) NOT NULL,
  `BaiHocId` int(11) NOT NULL,
  `TrangThai` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `baihoc`
--
ALTER TABLE `baihoc`
  ADD PRIMARY KEY (`BaiHocId`),
  ADD KEY `FK_BaiHoc_KhoaHoc` (`KhoaHocId`);

--
-- Indexes for table `chitiethangdadat`
--
ALTER TABLE `chitiethangdadat`
  ADD PRIMARY KEY (`ChiTietId`),
  ADD KEY `FK_ChiTiet_HangDaDat` (`HangDaDatId`),
  ADD KEY `FK_ChiTiet_KhoaHoc` (`KhoaHocId`);

--
-- Indexes for table `dangky`
--
ALTER TABLE `dangky`
  ADD PRIMARY KEY (`STT`),
  ADD UNIQUE KEY `TenDangNhap` (`TenDangNhap`),
  ADD UNIQUE KEY `Gmail` (`Gmail`);

--
-- Indexes for table `danhmuc`
--
ALTER TABLE `danhmuc`
  ADD PRIMARY KEY (`DanhMucId`);

--
-- Indexes for table `hangdadat`
--
ALTER TABLE `hangdadat`
  ADD PRIMARY KEY (`HangDaDatId`),
  ADD KEY `FK_HangDaDat_DangKy` (`STT`);

--
-- Indexes for table `khoahoc`
--
ALTER TABLE `khoahoc`
  ADD PRIMARY KEY (`KhoaHocId`),
  ADD KEY `FK_KhoaHoc_NhomKhoaHoc` (`NhomKhoaHocId`);

--
-- Indexes for table `nhomkhoahoc`
--
ALTER TABLE `nhomkhoahoc`
  ADD PRIMARY KEY (`NhomKhoaHocId`),
  ADD KEY `FK_NhomKhoaHoc_DanhMuc` (`DanhMucId`);

--
-- Indexes for table `quantriadmin`
--
ALTER TABLE `quantriadmin`
  ADD PRIMARY KEY (`AdminId`),
  ADD UNIQUE KEY `Gmail` (`Gmail`);

--
-- Indexes for table `tiendohocvien`
--
ALTER TABLE `tiendohocvien`
  ADD PRIMARY KEY (`TienDoId`),
  ADD UNIQUE KEY `unique_user_lesson` (`STT`,`BaiHocId`),
  ADD KEY `FK_TDHV_BaiHoc` (`BaiHocId`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `baihoc`
--
ALTER TABLE `baihoc`
  MODIFY `BaiHocId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `chitiethangdadat`
--
ALTER TABLE `chitiethangdadat`
  MODIFY `ChiTietId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `dangky`
--
ALTER TABLE `dangky`
  MODIFY `STT` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `danhmuc`
--
ALTER TABLE `danhmuc`
  MODIFY `DanhMucId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `hangdadat`
--
ALTER TABLE `hangdadat`
  MODIFY `HangDaDatId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `khoahoc`
--
ALTER TABLE `khoahoc`
  MODIFY `KhoaHocId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `nhomkhoahoc`
--
ALTER TABLE `nhomkhoahoc`
  MODIFY `NhomKhoaHocId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `quantriadmin`
--
ALTER TABLE `quantriadmin`
  MODIFY `AdminId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tiendohocvien`
--
ALTER TABLE `tiendohocvien`
  MODIFY `TienDoId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `baihoc`
--
ALTER TABLE `baihoc`
  ADD CONSTRAINT `FK_BaiHoc_KhoaHoc` FOREIGN KEY (`KhoaHocId`) REFERENCES `khoahoc` (`KhoaHocId`) ON DELETE CASCADE;

--
-- Constraints for table `chitiethangdadat`
--
ALTER TABLE `chitiethangdadat`
  ADD CONSTRAINT `FK_ChiTiet_HangDaDat` FOREIGN KEY (`HangDaDatId`) REFERENCES `hangdadat` (`HangDaDatId`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_ChiTiet_KhoaHoc` FOREIGN KEY (`KhoaHocId`) REFERENCES `khoahoc` (`KhoaHocId`) ON DELETE CASCADE;

--
-- Constraints for table `hangdadat`
--
ALTER TABLE `hangdadat`
  ADD CONSTRAINT `FK_HangDaDat_DangKy` FOREIGN KEY (`STT`) REFERENCES `dangky` (`STT`) ON DELETE CASCADE;

--
-- Constraints for table `khoahoc`
--
ALTER TABLE `khoahoc`
  ADD CONSTRAINT `FK_KhoaHoc_NhomKhoaHoc` FOREIGN KEY (`NhomKhoaHocId`) REFERENCES `nhomkhoahoc` (`NhomKhoaHocId`) ON DELETE CASCADE;

--
-- Constraints for table `nhomkhoahoc`
--
ALTER TABLE `nhomkhoahoc`
  ADD CONSTRAINT `FK_NhomKhoaHoc_DanhMuc` FOREIGN KEY (`DanhMucId`) REFERENCES `danhmuc` (`DanhMucId`) ON DELETE CASCADE;

--
-- Constraints for table `tiendohocvien`
--
ALTER TABLE `tiendohocvien`
  ADD CONSTRAINT `FK_TDHV_BaiHoc` FOREIGN KEY (`BaiHocId`) REFERENCES `baihoc` (`BaiHocId`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_TDHV_HocVien` FOREIGN KEY (`STT`) REFERENCES `dangky` (`STT`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
