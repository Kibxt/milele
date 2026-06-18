<?php
// backend/ListingController.php
require_once 'db.php';
require_once 'SessionGuard.php'; // Ensures only logged-in users can access this

class ListingController {
    private $conn;
    private $table_name = "listings";

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

    // 1. CREATE A LISTING
    public function createListing($title, $description, $price, $hostel_zone, $image_paths = '[]') {
        // Enforce Zero-Trust: We don't trust user input for who they are. 
        // We pull their ID and Campus directly from the secure server session.
        $seller_id = $_SESSION['user_id'];
        $campus_id = $_SESSION['campus_id'];
        $listing_id = $this->generateUUID();

        $query = "INSERT INTO " . $this->table_name . " 
                  (listing_id, seller_id, campus_id, title, description, price, hostel_zone, image_paths) 
                  VALUES (:listing_id, :seller_id, :campus_id, :title, :description, :price, :hostel_zone, :image_paths)";

        $stmt = $this->conn->prepare($query);

        // Sanitize input to prevent XSS attacks when displayed later
        $title = htmlspecialchars(strip_tags($title));
        $description = htmlspecialchars(strip_tags($description));
        $hostel_zone = htmlspecialchars(strip_tags($hostel_zone));

        $stmt->bindParam(":listing_id", $listing_id);
        $stmt->bindParam(":seller_id", $seller_id);
        $stmt->bindParam(":campus_id", $campus_id);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":price", $price);
        $stmt->bindParam(":hostel_zone", $hostel_zone);
        $stmt->bindParam(":image_paths", $image_paths);

        if ($stmt->execute()) {
            return [
                "status" => "success", 
                "message" => "Item listed securely in your campus feed.",
                "listing_id" => $listing_id
            ];
        }
        return ["status" => "error", "message" => "Database error while creating listing."];
    }

    // 2. FETCH THE HYPER-LOCAL FEED (Geofenced by Campus)
    public function getCampusFeed() {
        // The user only sees items from their specific university
        $campus_id = $_SESSION['campus_id'];

        $query = "SELECT l.listing_id, l.title, l.price, l.hostel_zone, l.image_paths, l.created_at, u.full_name as seller_name, u.trust_score 
                  FROM " . $this->table_name . " l
                  JOIN users u ON l.seller_id = u.user_id
                  WHERE l.campus_id = :campus_id AND l.status = 'available'
                  ORDER BY l.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":campus_id", $campus_id);
        $stmt->execute();

        $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode the JSON image paths for the frontend
        foreach ($listings as &$listing) {
            $listing['image_paths'] = json_decode($listing['image_paths']);
        }

        return [
            "status" => "success",
            "count" => count($listings),
            "data" => $listings
        ];
    }
}
?>