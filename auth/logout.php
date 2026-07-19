<?php
session_start();
session_destroy();
header("Location: /blood_bank/auth/login.php");
exit();
