<?php
// filename: student/logout.php
// location: student/logout.php

session_start();
session_destroy();

// Redirect to home page with logout message
header("Location: ../index.php?msg=loggedout");
exit();
?>