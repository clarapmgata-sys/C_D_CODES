<?php
session_start();

// Clear all session data
$_SESSION = [];

// Destroy the current session
session_destroy();

// Display confirmation without redirecting
echo "<h2>Session cleared successfully!</h2>";
echo "<p>Now open the login page again.</p>";
?>