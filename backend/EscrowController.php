<?php
// backend/EscrowController.php
require_once 'db.php';
require_once 'SessionGuard.php';

class EscrowController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Helper: Generate Secure UUID
    private function generateUUID() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); 
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); 
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    // Helper: Generate a cryptographically secure 6-digit OTP
    private function generateOTP() {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    // 1. INITIATE ESCROW (Buyer locks the item)
    public function initiateEscrow($listing_id) {
        $buyer_id = $_SESSION['user_id'];
        
        try {
            $this->conn->beginTransaction();

            // Step A: Verify the item is actually available and get the seller's ID
            $check_query = "SELECT seller_id, price FROM listings WHERE listing_id = :listing_id AND status = 'available' FOR UPDATE";
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->bindParam(":listing_id", $listing_id);
            $check_stmt->execute();
            
            $listing = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$listing) {
                $this->conn->rollBack();
                return ["status" => "error", "message" => "Item is no longer available or does not exist."];
            }

            // Prevent buyers from buying their own items
            if ($listing['seller_id'] === $buyer_id) {
                $this->conn->rollBack();
                return ["status" => "error", "message" => "You cannot buy your own item."];
            }

            // Step B: Lock the Listing (Take it off the market)
            $update_query = "UPDATE listings SET status = 'locked' WHERE listing_id = :listing_id";
            $update_stmt = $this->conn->prepare($update_query);
            $update_stmt->bindParam(":listing_id", $listing_id);
            $update_stmt->execute();

            // Step C: Generate OTP and create the Escrow Record
            $escrow_id = $this->generateUUID();
            $otp_code = $this->generateOTP();
            
            $escrow_query = "INSERT INTO escrow_transactions 
                            (escrow_id, listing_id, buyer_id, seller_id, amount, otp_code, status) 
                            VALUES (:escrow_id, :listing_id, :buyer_id, :seller_id, :amount, :otp_code, 'locked')";
            
            $escrow_stmt = $this->conn->prepare($escrow_query);
            $escrow_stmt->bindParam(":escrow_id", $escrow_id);
            $escrow_stmt->bindParam(":listing_id", $listing_id);
            $escrow_stmt->bindParam(":buyer_id", $buyer_id);
            $escrow_stmt->bindParam(":seller_id", $listing['seller_id']);
            $escrow_stmt->bindParam(":amount", $listing['price']);
            $escrow_stmt->bindParam(":otp_code", $otp_code);
            $escrow_stmt->execute();

            $this->conn->commit();

            return [
                "status" => "success",
                "message" => "Item locked successfully. Meet the seller and provide this OTP to finalize.",
                "otp_code" => $otp_code,
                "escrow_id" => $escrow_id
            ];

        } catch (Exception $e) {
            $this->conn->rollBack();
            return ["status" => "error", "message" => "Transaction failed. " . $e->getMessage()];
        }
    }

    // 2. VERIFY HANDSHAKE (Seller enters the OTP to get paid)
    public function verifyHandshake($escrow_id, $provided_otp) {
        $seller_id = $_SESSION['user_id'];

        try {
            $this->conn->beginTransaction();

            // Check if this escrow exists, is still locked, and belongs to this seller
            $query = "SELECT otp_code, listing_id FROM escrow_transactions 
                      WHERE escrow_id = :escrow_id AND seller_id = :seller_id AND status = 'locked' FOR UPDATE";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":escrow_id", $escrow_id);
            $stmt->bindParam(":seller_id", $seller_id);
            $stmt->execute();

            $escrow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$escrow) {
                $this->conn->rollBack();
                return ["status" => "error", "message" => "Invalid transaction or you are not authorized."];
            }

            // Validate the OTP
            if ($escrow['otp_code'] !== $provided_otp) {
                $this->conn->rollBack();
                return ["status" => "error", "message" => "Incorrect OTP. Handshake failed."];
            }

            // OTP is correct! Release the funds and mark item as sold
            $update_escrow = "UPDATE escrow_transactions SET status = 'released' WHERE escrow_id = :escrow_id";
            $stmt_update_escrow = $this->conn->prepare($update_escrow);
            $stmt_update_escrow->bindParam(":escrow_id", $escrow_id);
            $stmt_update_escrow->execute();

            $update_listing = "UPDATE listings SET status = 'sold' WHERE listing_id = :listing_id";
            $stmt_update_listing = $this->conn->prepare($update_listing);
            $stmt_update_listing->bindParam(":listing_id", $escrow['listing_id']);
            $stmt_update_listing->execute();

            $this->conn->commit();

            // In Phase 5, this is where we will trigger the M-Pesa B2C API to send the money!
            return [
                "status" => "success", 
                "message" => "Handshake verified! Funds are being released to your M-Pesa account."
            ];

        } catch (Exception $e) {
            $this->conn->rollBack();
            return ["status" => "error", "message" => "Verification failed. " . $e->getMessage()];
        }
    }
}
?>