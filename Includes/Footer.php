<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<footer class="main-footer">
    <div class="footer-container">
        <div class="footer-column brand-info">
            <h3>DevMaster.Academy</h3>
            <p>Nền tảng e-learning hàng đầu Việt Nam. Tổng hợp khóa học chất lượng cao từ các giảng viên hàng đầu trong lĩnh vực.</p>
            <div class="social-icons">
                <a href="#"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#"><i class="fa-brands fa-youtube"></i></a>
            </div>
        </div>

        <div class="footer-column">
            <h4>Khám phá</h4>
            <ul>
                <li><a href="/DevMaster/Pages/TatCaKhoaHoc.php">Tất cả khóa học</a></li>
            </ul>
        </div>

        <div class="footer-column">
            <h4>Danh mục</h4>
            <ul>
                <?php
                // Khởi tạo kết nối database cục bộ nếu chưa có biến $conn toàn cục
                if (!isset($conn) || $conn->connect_error) {
                    $conn = new mysqli("localhost", "root", "", "devmaster");
                    $conn->set_charset("utf8mb4");
                }

                if (!$conn->connect_error) {
                    // Lấy giới hạn 4 danh mục đầu tiên theo thứ tự ID tăng dần
                    $sqlFooterDm = "SELECT DanhMucId, TenDanhMuc FROM danhmuc ORDER BY DanhMucId ASC LIMIT 4";
                    $resFooterDm = $conn->query($sqlFooterDm);
                    
                    if ($resFooterDm && $resFooterDm->num_rows > 0) {
                        while ($rowDm = $resFooterDm->fetch_assoc()) {
                            $dmId = intval($rowDm['DanhMucId']);
                            $dmName = htmlspecialchars($rowDm['TenDanhMuc']);
                            // Đường dẫn redirect truyền tham số id danh mục sang trang tất cả khóa học
                            echo "<li><a href='/DevMaster/Pages/TatCaKhoaHoc.php?danhmuc_id={$dmId}'>{$dmName}</a></li>";
                        }
                    } else {
                        echo "<li><a href='#'>Chưa có danh mục</a></li>";
                    }
                } else {
                    echo "<li><a href='#'>Lỗi kết nối</a></li>";
                }
                ?>
            </ul>
        </div>

        <div class="footer-column contact-info">
            <h4>Liên hệ</h4>
            <p><i class="fa-solid fa-location-dot"></i> Quận 8, TP. Hồ Chí Minh</p>
            <p><i class="fa-solid fa-phone"></i> 0855190805</p>
            <p><i class="fa-solid fa-envelope"></i> devmaster@gmail.com</p>
        </div>
    </div>

    <div class="footer-bottom">
        <p>© 2026 DevMaster.Academy. Tất cả quyền được bảo lưu.</p>
        <div class="footer-policy">
            <a href="#">Điều khoản sử dụng</a>
            <a href="#">Chính sách bảo mật</a>
        </div>
    </div>
</footer>

<div class="floating-widgets">
    <div class="chat-bubble">
        <span>Tôi có thể tư vấn khóa học cho bạn 😊</span>
    </div>

    <a href="https://zalo.me/0855190805" class="widget-zalo" target="_blank" title="Chat qua Zalo">
        <img src="https://upload.wikimedia.org/wikipedia/commons/9/91/Icon_of_Zalo.svg" alt="Zalo">
    </a>

    <div class="widget-chatbot" id="aiChatTrigger" title="Trợ lý ảo DevMaster AI">
        <i class="fa-solid fa-brain"></i>
        <span class="pulse-wave"></span>
    </div>

    <div class="ai-chat-window" id="aiChatWindow">
        <div class="ai-chat-header">
            <div class="ai-avatar">
                <i class="fa-solid fa-brain"></i>
                <span class="online-dot"></span>
            </div>
            <div class="ai-status-info">
                <h4>DevMaster AI Assistant</h4>
                <p>Trợ lý ảo thông minh • Trực tuyến</p>
            </div>
            <button class="ai-close-btn" id="aiCloseBtn">&times;</button>
        </div>
        
        <div class="ai-chat-messages" id="aiChatMessages">
            <div class="message ai">
                <div class="msg-bubble">
                    Xin chào! Tôi là Trợ lý AI của <strong>DevMaster Academy</strong>. Bạn cần tôi tư vấn về khóa học lập trình (Development, IT & Software, Design...) hay chính sách ưu đãi nào không? Thử hỏi tôi "Có khóa học gì?" nhé!
                </div>
            </div>
        </div>
        
        <div class="ai-chat-input-area">
            <input type="text" id="aiInputField" placeholder="Nhập câu hỏi của bạn..." autocomplete="off">
            <button id="aiSendBtn">
                <i class="fa-solid fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>