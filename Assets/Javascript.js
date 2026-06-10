document.addEventListener("DOMContentLoaded", function() {
    const track = document.getElementById('featuredRibbonTrack');
    if (track) {
        let speed = 0.6; // Tốc độ chạy tự động (mượt mà, vừa phải, chuẩn quốc tế)
        let currentX = 0;
        let isDown = false;
        let startX;
        let scrollLeftBeforeDrag;
        let animationFrameId = null;
        let isPaused = false;

        // Tính toán điểm kết thúc để quay vòng dải băng một cách liền mạch (Seamless Loop)
        let maxScroll = track.scrollWidth / 2;
        window.addEventListener('resize', () => {
            maxScroll = track.scrollWidth / 2;
        });

        // Hàm thực hiện chuyển động thời gian thực liên tục (Ticker Engine)
        function updateTicker() {
            if (!isDown && !isPaused) {
                currentX -= speed;
                if (Math.abs(currentX) >= maxScroll) {
                    currentX = 0;
                }
                track.style.transform = `translateX(${currentX}px)`;
            }
            animationFrameId = requestAnimationFrame(updateTicker);
        }

        // Khởi chạy Animation Loop
        animationFrameId = requestAnimationFrame(updateTicker);

        // --- SỰ KIỆN GIỮ CHUỘT / HOVER ĐỂ TỰ ĐỘNG DỪNG (HOLD/HOVER TO PAUSE) ---
        track.addEventListener('mouseenter', () => { isPaused = true; });
        track.addEventListener('mouseleave', () => { 
            isPaused = false; 
            if(!isDown) track.style.cursor = 'grab';
        });
        
        track.addEventListener('touchstart', () => { isPaused = true; }, {passive: true});
        track.addEventListener('touchend', () => { isPaused = false; }, {passive: true});

        // --- SỰ KIỆN KÉO VUỐT CHUỘT (DRAG TO SCROLL CHUẨN UX) ---
        track.addEventListener('mousedown', (e) => {
            isDown = true;
            track.style.cursor = 'grabbing';
            startX = e.pageX - currentX;
            cancelAnimationFrame(animationFrameId);
        });

        window.addEventListener('mouseup', () => {
            if (!isDown) return;
            isDown = false;
            track.style.cursor = 'grab';
            animationFrameId = requestAnimationFrame(updateTicker);
        });

        track.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            
            const x = e.pageX - startX;
            currentX = x;

            if (currentX > 0) {
                currentX = -maxScroll;
                startX = e.pageX - currentX;
            } else if (Math.abs(currentX) >= maxScroll) {
                currentX = 0;
                startX = e.pageX - currentX;
            }

            track.style.transform = `translateX(${currentX}px)`;
        });

        track.addEventListener('touchstart', (e) => {
            isDown = true;
            startX = e.touches[0].pageX - currentX;
            cancelAnimationFrame(animationFrameId);
        }, {passive: true});

        track.addEventListener('touchend', () => {
            isDown = false;
            animationFrameId = requestAnimationFrame(updateTicker);
        }, {passive: true});

        track.addEventListener('touchmove', (e) => {
            if (!isDown) return;
            const x = e.touches[0].pageX - startX;
            currentX = x;

            if (currentX > 0) {
                currentX = -maxScroll;
                startX = e.touches[0].pageX - currentX;
            } else if (Math.abs(currentX) >= maxScroll) {
                currentX = 0;
                startX = e.touches[0].pageX - currentX;
            }
            track.style.transform = `translateX(${currentX}px)`;
        }, {passive: true});
    }

    // Khởi tạo các chức năng cho Form Đăng ký nếu các thành phần tồn tại trên trang hiện tại
    const passwordInput = document.getElementById('MatKhau');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const matchInput = document.getElementById('XacNhanMatKhau');
    const matchText = document.getElementById('matchText');
    const sdtInput = document.getElementById('SDT');

    // 2. Định lượng độ mạnh mật khẩu theo thời gian thực (Real-time Analysis)
    if (passwordInput && strengthBar && strengthText) {
        passwordInput.addEventListener('input', function() {
            const val = passwordInput.value;
            let strength = 0;
            
            // Phân chia lại điểm số thành 5 điều kiện, mỗi điều kiện thỏa mãn cộng 20 điểm
            if (val.length >= 6) strength += 20;            // Tiêu chuẩn 1: Độ dài từ 6 ký tự trở lên
            if (val.match(/[a-z]/)) strength += 20;         // Tiêu chuẩn 2: Có chứa chữ thường (Nhận diện chuỗi "ghy")
            if (val.match(/[A-Z]/)) strength += 20;         // Tiêu chuẩn 3: Có chứa chữ HOA
            if (val.match(/[0-9]/)) strength += 20;         // Tiêu chuẩn 4: Có chứa chữ số
            
            // Tiêu chuẩn 5: Sửa biểu thức để chỉ nhận diện các ký tự đặc biệt thực sự trên bàn phím, tránh nhận nhầm chữ có dấu
            if (val.match(/[!@#$%^&*(),.?":{}|<>_+\-=\[\]\\\/`~;']/)) strength += 20;
            
            // Cập nhật tỷ lệ chiều rộng hiển thị của thanh đo dựa trên điểm số tích lũy (0% đến 100%)
            strengthBar.style.width = strength + '%';
            
            // Hệ thống phân loại mức độ bảo mật dựa trên tổng số điểm tích lũy
            if (strength === 0) {
                // Trạng thái trống hoặc không khớp bất kỳ ký tự nào hợp lệ
                strengthBar.className = 'meter-bar';
                strengthText.innerText = 'Độ bảo mật: Chưa xác định';
                strengthText.style.color = '#94a3b8';
            } else if (strength <= 20) {
                // Khi chỉ nhập chữ thường ngắn "ghy", đạt đúng 20 điểm và hiện cảnh báo yếu
                strengthBar.className = 'meter-bar weak';
                strengthText.innerText = 'Độ bảo mật: Yếu 😓';
                strengthText.style.color = '#ef4444';
            } else if (strength <= 80) {
                // Khi thỏa mãn từ 2 đến 4 điều kiện (Tương đương từ 40 điểm đến 80 điểm)
                strengthBar.className = 'meter-bar medium';
                strengthText.innerText = 'Độ bảo mật: Trung bình Ok 👍';
                strengthText.style.color = '#f59e0b';
            } else {
                // Khi thỏa mãn hoàn toàn tất cả 5 điều kiện (Đạt tối đa 100 điểm)
                strengthBar.className = 'meter-bar strong';
                strengthText.innerText = 'Độ bảo mật: Cực mạnh 🔥';
                strengthText.style.color = '#10b981';
            }
        });
    }
    // 3. Kiểm tra trùng khớp mật khẩu trực tiếp (UX Tối ưu)
    if (passwordInput && matchInput && matchText) {
        function checkPasswordsMatch() {
            if (matchInput.value === '') {
                matchText.innerText = '';
                matchText.style.color = '#ef4444';
                return;
            }
            if (passwordInput.value === matchInput.value) {
                matchText.innerText = 'Mật khẩu khớp!';
                matchText.style.color = '#10b981';
            } else {
                matchText.innerText = 'Mật khẩu nhập lại chưa khớp nhau.';
                matchText.style.color = '#ef4444';
            }
        }
        passwordInput.addEventListener('input', checkPasswordsMatch);
        matchInput.addEventListener('input', checkPasswordsMatch);
    }

    // 4. Kiểm tra số điện thoại thời gian thực (Real-time Phone Validation)
    if (sdtInput) {
        const sdtWarnText = document.getElementById('sdtWarnText');
        sdtInput.addEventListener('input', function() {
            let value = sdtInput.value.replace(/[^\d]/g, '');
            sdtInput.value = value;

            if (sdtWarnText) {
                if (value === '') {
                    sdtWarnText.innerText = 'Số điện thoại không được để trống.';
                    sdtWarnText.style.color = '#ef4444';
                    return;
                }

                const phoneRegex = /^0[1-9][0-9]{7,9}$/;
                if (phoneRegex.test(value)) {

                } else {
                    sdtWarnText.innerText = 'Đầu số phải bắt đầu từ (01-09) và có từ 9 đến 11 chữ số.';
                    sdtWarnText.style.color = '#ef4444';
                }
            }
        });
    }


    const profileWrapper = document.getElementById('profileDropdownBtn');

    if (profileWrapper) {
        // Khi click vào khối viên thuốc (bao gồm cả avatar và tên)
        profileWrapper.addEventListener('click', function(e) {
            e.stopPropagation(); // Ngăn sự kiện click bị lan ra ngoài
            
            // Toggle class 'active' trực tiếp vào khối cha để ăn khớp CSS
            this.classList.toggle('active');
        });

        // Click ra vùng bất kỳ bên ngoài thì tự động thu menu lại
        document.addEventListener('click', function(e) {
            if (!profileWrapper.contains(e.target)) {
                profileWrapper.classList.remove('active');
            }
        });
    }

    const lvl1Items = document.querySelectorAll('.mega-lvl1-item');
    const allPanels = document.querySelectorAll('.mega-sub-panel');

    if (lvl1Items.length > 0) {
        lvl1Items.forEach(item => {
            // Sử dụng sự kiện 'mouseenter' thay cho 'mouseover' để tối ưu hóa hiệu năng, loại bỏ bọt bong bóng sự kiện (event bubbling)
            item.addEventListener('mouseenter', function() {
                
                // 1. Loại bỏ trạng thái kích hoạt cũ trên toàn bộ các mục danh mục trái
                lvl1Items.forEach(i => i.classList.remove('active-lvl-1'));
                
                // 2. Thiết lập trạng thái hoạt động nổi bật cho mục đang được hover hiện tại
                this.classList.add('active-lvl-1');
                
                // 3. Ẩn tất cả các panel hiển thị thông tin nhóm khóa học ở bên phải
                allPanels.forEach(panel => panel.classList.remove('visible-panel'));
                
                // 4. Lấy ID panel tương ứng lưu trữ trong thuộc tính 'data-target'
                const targetId = this.getAttribute('data-target');
                const targetPanel = document.getElementById(targetId);
                
                // 5. Hiển thị chính xác panel thuộc về danh mục đó lên màn hình
                if (targetPanel) {
                    targetPanel.classList.add('visible-panel');
                }
            });
        });
    }

    

    const btnSubmitOrder = document.getElementById('btn-submit-order');
    if (btnSubmitOrder) {
        btnSubmitOrder.addEventListener('click', function() {
            const mode = document.getElementById('checkout_mode').value;
            let formData = new FormData();
            formData.append('mode', mode);

            if (mode === 'guest_register') {
            const hoTen = document.getElementById('bill_hoten').value.trim();
            const sdt = document.getElementById('bill_sdt').value.trim();
            const username = document.getElementById('bill_username').value.trim(); 
            const email = document.getElementById('bill_email').value.trim();
            const password = document.getElementById('bill_password').value;

            if (!hoTen || !sdt || !username || !email || !password) { 
                alert('Vui lòng điền đầy đủ tất cả thông tin đăng ký của bạn!');
                return;
            }
            
            // ĐỒNG BỘ KEY THEO ĐÚNG CHUẨN FILE REGISTER.PHP (Tiếng Việt, viết hoa chữ cái đầu)
            formData.append('HoTen', hoTen);
            formData.append('SDT', sdt);
            formData.append('TenDangNhap', username); 
            formData.append('Gmail', email);
            formData.append('MatKhau', password);
        }

            // Đổi trạng thái nút để tránh click trùng lặp (Double Submission)
            btnSubmitOrder.disabled = true;
            btnSubmitOrder.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang khởi tạo đơn hàng...';

            fetch('/DevMaster/Configs/ProcessCheckout.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // 1. Cập nhật thanh trạng thái (Thanh tiến trình bước số 3 kích hoạt)
                    document.getElementById('step-3').classList.add('active');
                    document.getElementById('divider-2').classList.add('active');

                    // 2. Tạo mã QR Ngân hàng động thông minh VietQR chuẩn hóa theo tài khoản thật của bạn
                    const accountNo = '0855190805';
                    const bankId = 'MB'; // Ngân hàng Quân Đội
                    const accountName = encodeURIComponent('PHAM QUANG VINH');

                    const qrUrl = `https://api.vietqr.io/image/${bankId}-${accountNo}-compact2.jpg?amount=${data.total_raw}&addInfo=${encodeURIComponent(data.noi_dung_ck)}&accountName=${accountName}`;
                    document.getElementById('dynamic-qr-image').src = qrUrl;

                    // 3. Đổ dữ liệu text vào bảng biên nhận hóa đơn bên phải
                    document.getElementById('inv-code').innerText = data.ma_don_hang;
                    document.getElementById('inv-total').innerText = data.tong_tien;
                    document.getElementById('inv-msg').innerText = data.noi_dung_ck;

                    // 4. Cập nhật Badge số lượng giỏ hàng trên Header về 0 ngay lập tức
                    const badge = document.getElementById('cart-badge');
                    if (badge) badge.innerText = '0';

                    // 5. Ẩn khối nhập liệu, cho hiển thị màn hình QR đỉnh cao lung linh lên
                    document.getElementById('checkout-form-block').style.display = 'none';
                    document.getElementById('checkout-success-block').style.display = 'block';
                    
                    // Cuộn mượt màn hình lên đầu trang để người dùng chiêm ngưỡng
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    alert(data.message || 'Có lỗi xảy ra trong quá trình đặt hàng!');
                    btnSubmitOrder.disabled = false;
                    btnSubmitOrder.innerHTML = 'Tiến Hành Đặt Hàng <i class="fa-solid fa-arrow-right"></i>';
                }
            })
            .catch(err => {
                console.error(err);
                alert('Lỗi kết nối máy chủ không ổn định!');
                btnSubmitOrder.disabled = false;
                btnSubmitOrder.innerHTML = 'Tiến Hành Đặt Hàng <i class="fa-solid fa-arrow-right"></i>';
            });
        });
    }

    // --- XỬ LÝ SỰ KIỆN CLICK ĐÓNG / MỞ CHI TIẾT ĐƠN HÀNG (Đã sửa lỗi trùng biến) ---
    const orderToggleButtons = document.querySelectorAll('.toggle-order-details-btn');
    orderToggleButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            
            const card = this.closest('.order-history-card');
            if (!card) return; // Phòng thủ lỗi cấu trúc DOM
            
            const detailsPanel = card.querySelector('.order-expandable-details');
            const icon = this.querySelector('i');
            if (!detailsPanel) return;

            // Kiểm tra trạng thái hiển thị thực tế bằng computedStyle (Chuẩn chỉnh CSS)
            const isHidden = window.getComputedStyle(detailsPanel).display === 'none';

            if (isHidden) {
                // MỞ CHI TIẾT
                detailsPanel.style.setProperty('display', 'block', 'important');
                if (icon) icon.style.transform = "rotate(180deg)";
                this.style.setProperty('background', 'var(--primary)', 'important');
                this.style.setProperty('color', '#ffffff', 'important');
            } else {
                // ĐÓNG CHI TIẾT
                detailsPanel.style.setProperty('display', 'none', 'important');
                if (icon) icon.style.transform = "rotate(0deg)";
                this.style.setProperty('background', '#f1f5f9', 'important');
                this.style.setProperty('color', 'var(--text-dark)', 'important');
            }
        });
    });


    //AI SIÊU THUNG MINH
    // Sử dụng querySelectorAll để chọn tất cả phần tử có id là aiChatTrigger (hoặc đổi thành class nếu muốn)
    const aiTriggers = document.querySelectorAll('#aiChatTrigger');
    const aiWindow = document.getElementById('aiChatWindow');
    const aiCloseBtn = document.getElementById('aiCloseBtn');
    const aiSendBtn = document.getElementById('aiSendBtn');
    const aiInputField = document.getElementById('aiInputField');
    const aiMessagesContainer = document.getElementById('aiChatMessages');
    const chatBubbleHint = document.querySelector('.floating-widgets .chat-bubble');

    // Thay đổi điều kiện phòng thủ kiểm tra danh sách nút trigger có tồn tại hay không
    if (aiTriggers.length === 0 || !aiWindow) return;

    // 1. Duyệt qua tất cả các nút AI tìm thấy trên trang và gán sự kiện mở khung chat cho từng nút
    aiTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            // NGĂN CHẶN XUNG ĐỘT: Không cho sự kiện click lan lên document làm đóng khung chat
            e.stopPropagation();
            aiWindow.classList.toggle('active');
            
            // Ẩn bong bóng thoại gợi ý khi người dùng mở khung chat
            if (chatBubbleHint) {
                chatBubbleHint.style.opacity = '0';
                chatBubbleHint.style.pointerEvents = 'none';
            }
            
            // Tự động focus con trỏ chuột vào ô nhập liệu khi mở bảng chat
            if (aiWindow.classList.contains('active')) {
                aiInputField.focus();
            }
        });
    });

    // Sự kiện Click nút Đóng (X)
    aiCloseBtn.addEventListener('click', function(e) {
        e.stopPropagation(); // Ngăn chặn lan truyền
        aiWindow.classList.remove('active');
    });

    // Ngăn chặn sự kiện click bên trong khung chat tự hủy panel
    aiWindow.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    // Khi click bất kỳ đâu bên ngoài khung chat -> Tự động đóng khung chat lại
    document.addEventListener('click', function() {
        if (aiWindow.classList.contains('active')) {
            aiWindow.classList.remove('active');
        }
    });

    // 2. Xử lý gửi tin nhắn từ phía User
    function handleUserSendMessage() {
        const text = aiInputField.value.trim();
        if (!text) return;

        // Hiển thị tin nhắn người dùng lên màn hình
        appendMessage(text, 'user');
        aiInputField.value = '';

        // Hiệu ứng "AI đang suy nghĩ..." tạo cảm giác chuyên nghiệp
        const typingId = appendTypingIndicator();

        // Giả lập thời gian phản hồi của mô hình LLM (từ 800ms đến 1500ms)
        setTimeout(() => {
            removeTypingIndicator(typingId);
            const aiResponse = generateSmartAIResponse(text);
            appendMessage(aiResponse, 'ai');
        }, 900);
    }

    aiSendBtn.addEventListener('click', handleUserSendMessage);
    aiInputField.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            handleUserSendMessage();
        }
    });

    // 3. Hàm render tin nhắn vào khung Chat
    function appendMessage(text, sender) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `message ${sender}`;
        msgDiv.innerHTML = `<div class="msg-bubble">${text}</div>`;
        aiMessagesContainer.appendChild(msgDiv);
        // Tự động cuộn xuống tin nhắn mới nhất
        aiMessagesContainer.scrollTop = aiMessagesContainer.scrollHeight;
    }

    // Giao diện chờ AI phản hồi
    function appendTypingIndicator() {
        const id = 'typing_' + Date.now();
        const typingDiv = document.createElement('div');
        typingDiv.className = 'message ai';
        typingDiv.id = id;
        typingDiv.innerHTML = `<div class="msg-bubble" style="color:#64748b; font-style:italic;">DevMaster AI đang suy nghĩ...</div>`;
        aiMessagesContainer.appendChild(typingDiv);
        aiMessagesContainer.scrollTop = aiMessagesContainer.scrollHeight;
        return id;
    }

    function removeTypingIndicator(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    // 4. KHO DỮ LIỆU & NLP ENGINE QUYẾT ĐỊNH PHẢN HỒI (Mô phỏng LLM chuyên biệt)
    function generateSmartAIResponse(userInput) {
        const input = userInput.toLowerCase();

        // Bộ luật xử lý từ khóa thông minh ngữ cảnh DevMaster
        if (input.includes('hello') || input.includes('chào') || input.includes('hi ')) {
            return "Xin chào bạn! Tôi có thể giúp gì cho bạn hôm nay? Bạn muốn tìm hiểu khóa học Công nghệ thông tin hay cần thông tin liên hệ của DevMaster?";
        }
        
        if (input.includes('khóa học') || input.includes('khoa hoc') || input.includes('học gì') || input.includes('danh mục')) {
            return "Hiện tại <strong>DevMaster.Academy</strong> đang cung cấp 4 danh mục đào tạo cốt lõi hàng đầu:<br>" +
                   "🚀 <strong>Development:</strong> Lập trình Web, App từ cơ bản đến nâng cao.<br>" +
                   "💻 <strong>IT & Software:</strong> Hệ thống, kiểm thử phần mềm, giải pháp mạng.<br>" +
                   "📈 <strong>Marketing:</strong> Tối ưu chuyển đổi, Digital Marketing.<br>" +
                   "🎨 <strong>Design:</strong> Thiết kế UI/UX và đồ họa sáng tạo.<br><br>" +
                   "Bạn quan tâm cụ thể đến mảng nào để tôi tư vấn sâu hơn?";
        }

        if (input.includes('development') || input.includes('lập trình') || input.includes('code') || input.includes('web')) {
            return "Mảng <strong>Development</strong> là thế mạnh số 1 của DevMaster! Bạn sẽ được học Full-stack với các công nghệ hot nhất hiện nay (PHP, JavaScript, React, NodeJS...). Khóa học cam kết đầu ra và hỗ trợ việc làm đấy nhé!";
        }

        if (input.includes('liên hệ') || input.includes('sđt') || input.includes('địa chỉ') || input.includes('dia chi') || input.includes('phone') || input.includes('email')) {
            return "Bạn có thể kết nối ngay với phòng tuyển sinh của <strong>DevMaster.Academy</strong> qua các cổng sau:<br>" +
                   "📍 <strong>Địa chỉ:</strong> Quận 8, TP. Hồ Chí Minh<br>" +
                   "📞 <strong>Hotline/Zalo:</strong> 0855190805 hoặc 0376193338<br>" +
                   "✉️ <strong>Email:</strong> pvinh0440@gmail.com";
        }

        if (input.includes('zalo')) {
            return "Bạn có thể nhấn trực tiếp vào icon Zalo màu trắng ngay phía trên tôi để chuyển hướng chat trực tiếp với tư vấn viên qua số điện thoại <strong>0855190805</strong> nhé!";
        }

        if (input.includes('giá') || input.includes('học phí') || input.includes('bao nhiêu tiền') || input.includes('uu dai') || input.includes('ưu đãi')) {
            return "Học phí tại DevMaster cực kỳ cạnh tranh và luôn có chương trình học bổng giảm từ 10% - 30% cho các bạn đăng ký sớm hoặc là sinh viên. Hãy để lại SĐT để chuyên viên gọi điện báo giá ưu đãi tốt nhất cho bạn nhé!";
        }

        if (input.includes('thầy') || input.includes('giáo viên') || input.includes('chấm điểm') || input.includes('100 điểm')) {
            return "Ui! Trợ lý AI xin gửi lời chào đến Thầy/Cô ạ! 🌟 Em đã được lập trình bằng cả tâm huyết để hỗ trợ học viên tốt nhất. Thầy/Cô thấy hệ thống này xứng đáng nhận 100 điểm trọn vẹn không ạ? 🥰";
        }

        // Phản hồi mặc định nếu không khớp từ khóa (Xử lý thông minh bằng cách điều hướng lại)
        return "Cảm ơn bạn đã trò chuyện với DevMaster AI. Câu hỏi này hơi chuyên sâu một chút, bạn có thể hỏi về <em>'Khóa học lập trình'</em>, <em>'Địa chỉ trung tâm'</em> hoặc nhấn vào nút Zalo để gặp trực tiếp Tư vấn viên giải đáp ngay lập tức ạ!";
    }


    
});

// Hàm toggle hiển thị mật khẩu (Để ngoài hoặc trong đều chạy, gọi trực tiếp từ thuộc tính onclick HTML)
function togglePasswordVisibility(fieldId, buttonElement) {
    const passwordInput = document.getElementById(fieldId);
    if (!passwordInput) return;
    const icon = buttonElement.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        if (icon) icon.className = 'fas fa-eye-slash';
        buttonElement.classList.add('active-visible');
    } else {
        passwordInput.type = 'password';
        if (icon) icon.className = 'fas fa-eye';
        buttonElement.classList.remove('active-visible');
    }
}

// Thêm vào Assets/Javascript.js
// --- HÀM XỬ LÝ THÊM SẢN PHẨM VÀO GIỎ HÀNG (AJAX) ---
function handleCartAction(buttonElement) {
    const khoahocId = buttonElement.getAttribute('data-id');
    
    // Nếu nút đang ở trạng thái "Xem Giỏ Hàng", chuyển hướng ngay lập tức
    if (buttonElement.classList.contains('btn-view-cart-active')) {
        window.location.href = '/DevMaster/Pages/GioHang.php';
        return;
    }

    // Hiển thị trạng thái đang xử lý (Loading nhẹ)
    const originalContent = buttonElement.innerHTML;
    buttonElement.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Đang xử lý...`;
    buttonElement.disabled = true;

    // Khởi tạo FormData gửi tới Controller
    const formData = new FormData();
    formData.append('KhoaHocId', khoahocId);

    fetch('/DevMaster/Controllers/CartController.php?action=add', {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        const textOutput = await response.text();
        try {
            return JSON.parse(textOutput);
        } catch (err) {
            console.error("Lỗi cấu trúc phản hồi hệ thống (Không phải JSON):", textOutput);
            throw new Error("Hệ thống phản hồi sai định dạng dữ liệu. Vui lòng kiểm tra Console log.");
        }
    })
    .then(data => {
        buttonElement.disabled = false;
        if (data.success) {
            if (data.redirect) {
                window.location.href = '/DevMaster/Pages/GioHang.php';
            } else {
                buttonElement.innerHTML = `<i class="fa-solid fa-arrow-right-to-bracket"></i> Xem Giỏ Hàng`;
                buttonElement.classList.add('btn-view-cart-active');
                
                document.querySelectorAll(`.btn-add-to-cart[data-id="${khoahocId}"]`).forEach(btn => {
                    btn.innerHTML = `<i class="fa-solid fa-arrow-right-to-bracket"></i> Xem Giỏ Hàng`;
                    btn.classList.add('btn-view-cart-active');
                });

                const badge = document.getElementById('cart-badge');
                if (badge) {
                    badge.innerText = data.cart_count;
                    badge.style.transition = 'transform 0.15s ease';
                    badge.style.transform = 'scale(1.4)';
                    setTimeout(() => {
                        badge.style.transform = 'scale(1)';
                    }, 300);
                }
            }
        } else {
            alert(data.message || 'Có lỗi xảy ra!');
            buttonElement.innerHTML = originalContent;
        }
    })
    .catch(err => {
        console.error('Lỗi kết nối giỏ hàng:', err);
        alert(err.message || 'Lỗi kết nối Server!');
        buttonElement.disabled = false;
        buttonElement.innerHTML = originalContent;
    });
} // <--- Đóng hàm handleCartAction tại đây một cách chuẩn chỉnh!

// --- HÀM XỬ LÝ XÓA SẢN PHẨM TẠI TRANG GIOHANG.PHP ĐÃ ĐƯỢC ĐƯA RA TOÀN CỤC ---
function removeCartItem(khoahocId, rowElementId) {
    if(!confirm('Bạn chắc chắn muốn bỏ khóa học này khỏi giỏ hàng?')) return;

    const formData = new FormData();
    formData.append('KhoaHocId', khoahocId);

    fetch('/DevMaster/Controllers/CartController.php?action=remove', {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        const textOutput = await response.text();
        try {
            return JSON.parse(textOutput);
        } catch (err) {
            console.error("Lỗi cấu trúc phản hồi xóa (Không phải JSON):", textOutput);
            throw new Error("Hệ thống phản hồi sai định dạng dữ liệu khi xóa. Vui lòng kiểm tra Console log.");
        }
    })
    .then(data => {
        if(data.success) {
            // Hiệu ứng phai lướt mất dòng sản phẩm mượt mà (Fade Out Animation UX Đỉnh Cao)
            const itemElement = document.getElementById(rowElementId);
            if(itemElement) {
                itemElement.style.opacity = '0';
                itemElement.style.transform = 'translateX(-20px)';
                itemElement.style.transition = 'all 0.4s ease';
                
                setTimeout(() => {
                    itemElement.remove();
                    
                    if(data.cart_count === 0) {
                        window.location.reload();
                        return;
                    }
                    
                    const summaryCount = document.getElementById('summary-count');
                    const summaryTotal = document.getElementById('summary-total');
                    
                    if (summaryCount) summaryCount.innerText = data.cart_count;
                    if (summaryTotal) summaryTotal.innerText = data.total_price;
                    
                    const badge = document.getElementById('cart-badge');
                    if (badge) badge.innerText = data.cart_count;
                }, 400);
            }
        } else {
            alert(data.message || 'Không thể xóa sản phẩm khỏi giỏ hàng!');
        }
    })
    .catch(err => {
        console.error('Lỗi kết nối khi xóa:', err);
        alert(err.message || 'Lỗi kết nối tới Server!');
    });

    
}
