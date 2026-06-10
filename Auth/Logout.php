<?php
session_start();
// Xóa bỏ cờ hoạt động trước khi xóa toàn bộ session
if(isset($_SESSION['UserActiveName'])) {
    unset($_SESSION['UserActiveName']);
}
session_unset();
session_destroy();
header("Location: ../index.php");
exit();
?>