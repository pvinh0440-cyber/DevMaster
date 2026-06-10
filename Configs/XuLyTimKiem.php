<?php
header('Content-Type: application/json; charset=utf-8');

// Khởi tạo kết nối Database (Giống cấu hình trong Header của bạn)
$conn = new mysqli("localhost", "root", "", "devmaster");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    echo json_encode([]);
    exit;
}

// Lấy từ khóa tìm kiếm từ client gửi lên
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

if ($keyword === '') {
    echo json_encode([]);
    exit;
}

// Query tìm kiếm theo tên khóa học, giới hạn đúng 8 cái như bạn yêu cầu
// Sử dụng Prepared Statement để chống tấn công SQL Injection (Chuẩn bảo mật quốc tế)
$sql = "SELECT KhoaHocId, Ten, Anh, Gia, TenGiangVien 
        FROM khoahoc 
        WHERE Ten LIKE ? 
        LIMIT 8";

$stmt = $conn->prepare($sql);
$searchParam = "%" . $keyword . "%";
$stmt->bind_param("s", $searchParam);
$stmt->execute();
$result = $stmt->get_result();

$courses = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
}

// Trả kết quả về cho JavaScript dưới dạng JSON mượt mà
echo json_encode($courses);

$stmt->close();
$conn->close();
?>