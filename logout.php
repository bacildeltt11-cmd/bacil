<?php
session_start();
session_destroy(); // Menghapus semua tanda login
header("Location: login.php"); // Balik ke pintu depan
exit;
?>