<?php
// MILELE - Secure Session Destroyer
session_start();
session_unset();    // Remove all session variables
session_destroy();  // Destroy the session itself

// Send them back to the login page
header("Location: login.php");
exit();
?>