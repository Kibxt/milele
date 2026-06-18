<?php
// backend/AuthController.php
require_once 'db.php';

class AuthController {
    private $conn;
    private $table_name = "users";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Helper: Generate a secure UUID v4
    private function generateUUID() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); 
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); 
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    // 1. REGISTRATION METHOD (From previous step)
    public function register($campus_id, $full_name, $email, $password, $phone_number) {
        $domain = substr(strrchr($email, "@"), 1);
        
        if (strpos($domain, '.ac.ke') === false && strpos($domain, '.edu') === false) {
            return ["status" => "error", "message" => "Unauthorized domain. You must use an official university email."];
        }

        $check_query = "SELECT email FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($check_query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return ["status" => "error", "message" => "This university email is already registered."];
        }

        $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $user_id = $this->generateUUID();
        $is_verified = 0; 

        $query = "INSERT INTO " . $this->table_name . " 
                  (user_id, campus_id, email, password_hash, full_name, phone_number, is_verified) 
                  VALUES (:user_id, :campus_id, :email, :password_hash, :full_name, :phone_number, :is_verified)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":campus_id", $campus_id);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":password_hash", $password_hash);
        $stmt->bindParam(":full_name", $full_name);
        $stmt->bindParam(":phone_number", $phone_number);
        $stmt->bindParam(":is_verified", $is_verified);

        if ($stmt->execute()) {
            return [
                "status" => "success", 
                "message" => "Registration successful. Please check your university email to verify your node.",
                "user_id" => $user_id
            ];
        }
        return ["status" => "error", "message" => "System failure during registration."];
    }

    // 2. NEW: SECURE LOGIN METHOD
    public function login($email, $password, $device_fingerprint = null) {
        $query = "SELECT user_id, campus_id, password_hash, is_verified, strike_count, subscription_tier 
                  FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            // We use a generic error message so hackers don't know if the email exists or not
            return ["status" => "error", "message" => "Invalid credentials."];
        }

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify Cryptographic Hash
        if (!password_verify($password, $user['password_hash'])) {
            return ["status" => "error", "message" => "Invalid credentials."];
        }

        // Zero-Trust Checks
        if ($user['is_verified'] == 0) {
            return ["status" => "error", "message" => "Account not verified. Check your student email."];
        }

        if ($user['strike_count'] >= 3) {
            return ["status" => "error", "message" => "Account suspended due to excessive ghosting violations."];
        }

        // Initialize Secure Session
        $this->startSecureSession();
        
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['campus_id'] = $user['campus_id'];
        $_SESSION['tier'] = $user['subscription_tier'];
        
        // Optional: Bind session to a hardware fingerprint
        if ($device_fingerprint) {
            $_SESSION['device_fingerprint'] = $device_fingerprint;
        }

        return [
            "status" => "success", 
            "message" => "Authentication successful.",
            "data" => [
                "user_id" => $user['user_id'],
                "tier" => $user['subscription_tier']
            ]
        ];
    }

    // 3. NEW: SESSION CONFIGURATION
    private function startSecureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Configure PHP session cookies to be highly secure before starting
            session_set_cookie_params([
                'lifetime' => 86400, // 24 hours
                'path' => '/',
                'domain' => '', // Set your production domain here later
                'secure' => true, // Only send over HTTPS
                'httponly' => true, // JavaScript CANNOT read this cookie (Prevents XSS theft)
                'samesite' => 'Strict' // Prevents CSRF attacks
            ]);
            session_start();
        }
        // Regenerate ID to prevent Session Fixation attacks
        session_regenerate_id(true); 
    }
}
?>