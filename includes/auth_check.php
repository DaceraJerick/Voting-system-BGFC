<?php
// filename: includes/auth_check.php

if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: ../index.php");
    exit();
}
?>