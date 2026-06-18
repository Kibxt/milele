<?php
// backend/SessionGuard.php

class SessionGuard {
    
    public static function protect() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if the user ID exists in the session
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401); // 401 Unauthorized HTTP Status
            echo json_encode([
                "status" => "error", 
                "message" => "Unauthorized node. Session invalid or expired."
            ]);
            exit(); // Immediately kill the script. No further code executes.
        }

        // Optional Anti-Hijacking Check: Ensure the user's IP address hasn't suddenly changed
        if (!isset($_SESSION['ip_address'])) {
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
        } elseif ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
            // Potential session hijacking detected
            session_destroy();
            http_response_code(403);
            echo json_encode([
                "status" => "error", 
                "message" => "Security anomaly detected. Session terminated."
            ]);
            exit();
        }
    }

    public static function requireTier($required_tier) {
        self::protect(); // Run standard protection first

        $current_tier = $_SESSION['tier'] ?? 'standard';
        $tiers = ['standard' => 1, 'premium' => 2, 'merchant' => 3];

        if ($tiers[$current_tier] < $tiers[$required_tier]) {
            http_response_code(403);
            echo json_encode([
                "status" => "error", 
                "message" => "Insufficient clearance. This action requires " . strtoupper($required_tier) . " status."
            ]);
            exit();
        }
    }
}
?>