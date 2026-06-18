<?php
// backend/api/MpesaGateway.php

class MpesaGateway {
    private $consumerKey;
    private $consumerSecret;
    private $env;
    private $baseUrl;

    // Sandbox STK Push Standard credentials
    private $stkShortcode = "174379";
    private $stkPasskey   = "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919";

    public function __construct() {
        // App V2 Credentials
        $this->consumerKey    = "cCE5mHUdgIVpmcym94b21OEG7x7jqqK3gzjCiwQQglUBbOUZ";
        $this->consumerSecret = "ElUGFeLTYC4WYWkwYnIZ19KmHOzXLgeAqtMFufyCdcoH3jiHnVAnI8AtuslaG6Em";
        $this->env            = "sandbox"; 
        
        $this->baseUrl = ($this->env === "production") 
            ? "https://api.safaricom.co.ke" 
            : "https://sandbox.safaricom.co.ke";
    }

    private function getAccessToken() {
        $url = $this->baseUrl . "/oauth/v1/generate?grant_type=client_credentials";
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ["Authorization: Basic " . base64_encode($this->consumerKey . ":" . $this->consumerSecret)]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); 
        $response = curl_exec($curl);
        curl_close($curl);
        
        $result = json_decode($response, true);
        return $result['access_token'] ?? null;
    }

    /**
     * Trigger STK Push (Escrow Deposit from Buyer)
     */
    public function triggerStkPush($phoneNumber, $amount, $escrowId) {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) return ["status" => "error", "message" => "OAuth Access Token generation failed."];

            $url = $this->baseUrl . "/mpesa/stkpush/v1/processrequest";
            
            $phoneNumber = preg_replace('/^0/', '254', $phoneNumber);
            $phoneNumber = preg_replace('/^\+/', '', $phoneNumber);

            $timestamp = date('YmdHis');
            $password = base64_encode($this->stkShortcode . $this->stkPasskey . $timestamp);

            $curlPayload = [
                "BusinessShortCode" => $this->stkShortcode,
                "Password"          => $password,
                "Timestamp"         => $timestamp,
                "TransactionType"   => "CustomerPayBillOnline", 
                "Amount"            => round($amount),
                "PartyA"            => $phoneNumber, 
                "PartyB"            => $this->stkShortcode, 
                "PhoneNumber"       => $phoneNumber,
                "CallBackURL"       => "https://gonad-running-shifter.ngrok-free.dev/MILELE/backend/api/stk_callback.php",
                "AccountReference"  => substr($escrowId, 0, 12), 
                "TransactionDesc"   => "Escrow Deposit"
            ];

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $accessToken, "Content-Type: application/json"]);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($curlPayload));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($curl);
            curl_close($curl);

            return ["status" => "success", "raw" => json_decode($response, true)];

        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    /**
     * Trigger B2C Payout (Escrow Disbursement to Seller)
     */
    public function sendPayout($phoneNumber, $amount, $escrowId) {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) return ["status" => "error", "message" => "OAuth Access Token generation failed."];

            // Stabilized v1 Endpoint
            $url = $this->baseUrl . "/mpesa/b2c/v1/paymentrequest";

            // Your Brand New Credential
            $securityCredential = "dQULEr9e5VjC0qMX2EQ8tEoBeE3cuBBVmyAFz/Cbe9niC/IPoToHZMrODSZQlmTXDr3QdThx/gaQHdKPa+SBDA+uN3R1SPFkvrvdeP0zVwZgk7gmqbmxHFtPfAgPmRn7bxKim+e0Ym3huZdgzzOBnQ773DKClUIRmsQxVAXqtBCGnOUzLYQ5GsYeOhEv7t+xr63WCrkZtmw40W8JyOWIwwAd0GvTT94Gbt6OfCSAu1wVXMRY2P4LsStSj39EFXO+rJFABhv0ku4UmsimRQs6P2WNCYu8pS0h67ObfUawbd54oEwo0VPUO5vo9NTxlMB+LEgJXEXsR8FVV4vuZv/7RQ==";

            // Strict Payload formatting to match the simulator exactly and avoid Sandbox crashes
            $curlPayload = [
                "InitiatorName"      => "testapi",
                "SecurityCredential" => $securityCredential,
                "CommandID"          => "BusinessPayment",
                "Amount"             => (string) round($amount), // Forced to String
                "PartyA"             => "600983",
                "PartyB"             => (int) $phoneNumber,      // Forced to Integer
                "Remarks"            => "Test",                  // Simplified to prevent Sandbox space glitches
                "QueueTimeOutURL"    => "https://webhook.site/6d56136c-e295-49b5-bf93-6e820bd2f2bd",
                "ResultURL"          => "https://webhook.site/6d56136c-e295-49b5-bf93-6e820bd2f2bd",
                "Occasion"           => "Test"                   // Simplified
            ];

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $accessToken, "Content-Type: application/json"]);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($curlPayload));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            $result = json_decode($response, true);

            if ($httpCode == 200 && isset($result['ConversationID'])) {
                return ["status" => "success", "raw" => $result];
            } else {
                return ["status" => "error", "message" => $result['errorMessage'] ?? 'B2C payload rejected.'];
            }

        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }
}
?>