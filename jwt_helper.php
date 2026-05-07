<?php
// JWT Helper for creating and validating JWT tokens

// The secret for JWT is set in config.php or the JWT_SECRET environment variable (see config.php).
if (!defined('JWT_SECRET')) {
    define(
        'JWT_SECRET',
        '__DEFINE_JWT_SECRET_IN_CONFIG_OR_ENV__' . 'dev-only-min-32-chars-pad-xxxxxxxxxxxxxx'
    );
}
if (strlen((string) JWT_SECRET) < 32) {
    error_log('BTB security: JWT_SECRET must be at least 32 characters. Set JWT_SECRET in the server environment or config.php.');
}

if (!defined('JWT_ALGORITHM')) {
    define('JWT_ALGORITHM', 'HS256');
}

if (!defined('JWT_EXPIRATION')) {
    define('JWT_EXPIRATION', 86400 * 30); // 30 days
}

/**
 * Creating a JWT token
 * @param array $payload Data for token (user_id, email, etc.)
 * @param int $expiration Token lifetime in seconds
 * @return string JWT token
 */
function createJWT($payload, $expiration = null) {
    if ($expiration === null) {
        $expiration = JWT_EXPIRATION;
    }
    
    $header = [
        'typ' => 'JWT',
        'alg' => JWT_ALGORITHM
    ];
    
    $payload['iat'] = time(); // Issued at
    $payload['exp'] = time() + $expiration; // Expiration
    
    $base64UrlHeader = base64UrlEncode(json_encode($header));
    $base64UrlPayload = base64UrlEncode(json_encode($payload));
    
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
    $base64UrlSignature = base64UrlEncode($signature);
    
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

/**
 * JWT token verification and decoding
 * @param string $token JWT token
 * @return array|false Decoded data or false on error
 */
function verifyJWT($token) {
    $parts = explode('.', $token);
    
    if (count($parts) !== 3) {
        return false;
    }
    
    list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;
    
    // Checking the signature
    $signature = base64UrlDecode($base64UrlSignature);
    $expectedSignature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
    
    if (!hash_equals($expectedSignature, $signature)) {
        return false;
    }
    
    // Decoding payload
    $payload = json_decode(base64UrlDecode($base64UrlPayload), true);
    
    if (!$payload) {
        return false;
    }
    
    // Checking the expiration date
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return false;
    }
    
    return $payload;
}

/**
 * True if Bearer or btb_auth_token cookie is a valid JWT with is_admin set (CMS login).
 * Used so staff can update guest bookings from the same browser session without matching guest email.
 */
function btbJwtIsAdmin() {
    $token = getBearerToken();
    if (!$token && !empty($_COOKIE['btb_auth_token'])) {
        $token = (string) $_COOKIE['btb_auth_token'];
    }
    if (!$token) {
        return false;
    }
    $payload = verifyJWT($token);
    return is_array($payload) && !empty($payload['is_admin']);
}

/**
 * Getting a token from the Authorization header
 * @return string|false Token or false
 */
function getBearerToken() {
    $authHeader = '';
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $key => $value) {
                if (strcasecmp((string) $key, 'Authorization') === 0) {
                    $authHeader = (string) $value;
                    break;
                }
            }
        }
    }
    if ($authHeader === '' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = (string) $_SERVER['HTTP_AUTHORIZATION'];
    }
    if ($authHeader === '' && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if ($authHeader === '') {
        return false;
    }

    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return $matches[1];
    }

    return false;
}

/**
 * User Authentication Verification
 * @param mysqli $conn Database connection
 * @return array|false User data or false
 */
function authenticateUser($conn) {
    $token = getBearerToken();
    
    if (!$token) {
        // Trying to get from cookie
        $token = $_COOKIE['btb_auth_token'] ?? null;
    }
    
    if (!$token) {
        return false;
    }
    
    $payload = verifyJWT($token);
    
    if (!$payload || !isset($payload['user_id'])) {
        return false;
    }
    
    // Getting the user from the database
    $user = fetchOne($conn, "SELECT id, email, name, phone, phone2, is_verified, created_at, last_session FROM users WHERE id = ?", [$payload['user_id']]);
    
    if (!$user) {
        return false;
    }
    
    return $user;
}

/**
 * Base64 URL encoding
 */
function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64 URL decoding
 */
function base64UrlDecode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

