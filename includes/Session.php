<?php
class Session {
    const FLASH_KEY = 'flash_messages';
    const LOGIN_REDIRECT_URL = '../HTML/login.html'; // Default redirect URL

    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0, // Expires when browser closes
                'path' => '/',
                'domain' => '', // Current domain
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // Use secure cookies if HTTPS
                'httponly' => true, // Prevent JS access
                'samesite' => 'Lax' // Protect against CSRF
            ]);
            session_start();
        }

        // Generate CSRF token if not exists
        if (!isset($_SESSION['csrf_token'])) {
            try {
                 $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                // Handle error if random_bytes fails
                error_log("Failed to generate CSRF token: " . $e->getMessage());
                // You might want to terminate or use a fallback
            }
        }

        // Process flash messages
        self::processFlashMessages();
    }

    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }

    public static function has($key) {
        return isset($_SESSION[$key]);
    }

    public static function remove($key) {
        unset($_SESSION[$key]);
    }

    public static function setFlash($key, $message, $type = 'info') { // Added type for styling
        $_SESSION[self::FLASH_KEY][$key] = [
            'message' => $message,
            'type' => $type, // e.g., 'success', 'danger', 'warning'
            'remove' => false
        ];
    }

    public static function getFlash($key) {
        if (isset($_SESSION[self::FLASH_KEY][$key])) {
            $flash = $_SESSION[self::FLASH_KEY][$key];
            $_SESSION[self::FLASH_KEY][$key]['remove'] = true; // Mark for removal
            return $flash; // Return the whole array (message + type)
        }
        return null;
    }

    // Helper to display all flash messages (e.g., in a layout)
    public static function displayFlashMessages() {
        if (!isset($_SESSION[self::FLASH_KEY])) {
            return '';
        }
        $output = '';
        foreach (array_keys($_SESSION[self::FLASH_KEY]) as $key) {
            $flash = self::getFlash($key); // This also marks it for removal
            if ($flash) {
                $output .= '<div class="alert alert-' . htmlspecialchars($flash['type']) . '">' . htmlspecialchars($flash['message']) . '</div>';
            }
        }
        return $output;
    }


    private static function processFlashMessages() {
        if (isset($_SESSION[self::FLASH_KEY])) {
            foreach ($_SESSION[self::FLASH_KEY] as $key => $flash) {
                // Check if the flash message was marked for removal during the previous request
                if ($flash['remove']) {
                    unset($_SESSION[self::FLASH_KEY][$key]);
                }
            }
             // Clean up empty flash key array
            if (empty($_SESSION[self::FLASH_KEY])) {
                unset($_SESSION[self::FLASH_KEY]);
            }
        }
    }

    public static function regenerate($destroyOld = true) { // Added option
        // Regenerate session ID to prevent session fixation
        session_regenerate_id($destroyOld);
    }

    public static function destroy() {
        // Unset all session variables
        $_SESSION = array();

        // If it's desired to kill the session, also delete the session cookie.
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Finally, destroy the session.
        session_destroy();
    }

    public static function checkCSRF($token, $key = 'csrf_token') {
        if (!isset($_SESSION[$key])) {
             error_log("CSRF check failed: No token found in session.");
            return false;
        }
        $result = hash_equals($_SESSION[$key], $token);
         if (!$result) {
             error_log("CSRF check failed: Submitted token does not match session token.");
         }
         return $result;
    }

    // Get CSRF token to include in forms
    public static function getCsrfToken($key = 'csrf_token') {
        return $_SESSION[$key] ?? null;
    }

    // Check if user is logged in
    public static function isAuthenticated() {
        return isset($_SESSION['user_id']);
    }

    // **Require Login Function**
    public static function requireLogin($redirectUrl = self::LOGIN_REDIRECT_URL) {
        if (!self::isAuthenticated()) {
             error_log("Authentication required. Redirecting to login page.");
             // Store the intended destination to redirect back after login (optional)
             $_SESSION['return_to'] = $_SERVER['REQUEST_URI']; 
             // Set a flash message (optional)
             self::setFlash('auth_error', 'You need to log in to access this page.', 'warning');
            header("Location: " . $redirectUrl);
            exit();
        }
    }
    
    // **Require Profile Completion Function**
    public static function requireProfileComplete($profilePageUrl = '../HTML/profile.html?mode=complete') {
         // Ensure user is logged in first
        self::requireLogin(); 

        // Check if profile is marked as complete in the session
        if (!isset($_SESSION['profile_completed']) || !$_SESSION['profile_completed']) {
             // Avoid redirect loop if already on the profile page
            if (strpos($_SERVER['REQUEST_URI'], basename($profilePageUrl)) === false) {
                 error_log("Profile completion required for user_id: " . self::get('user_id') . ". Redirecting to profile completion.");
                 self::setFlash('profile_notice', 'Please complete your profile to continue.', 'info');
                header("Location: " . $profilePageUrl);
                exit();
             }
        }
    }

    // Function to prevent form resubmission on refresh
    public static function preventResubmit($formIdentifier) {
        $tokenKey = 'form_token_' . $formIdentifier;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_SESSION[$tokenKey]) && isset($_POST[$tokenKey]) && $_SESSION[$tokenKey] === $_POST[$tokenKey]) {
                // Already submitted or invalid token
                self::setFlash('resubmit_error', 'Form already submitted or invalid request.', 'warning');
                // Redirect back to the form page or a relevant page
                header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? self::LOGIN_REDIRECT_URL));
                exit();
            }
             // Valid submission, store token to prevent resubmit
            if(isset($_POST[$tokenKey])){
                $_SESSION[$tokenKey] = $_POST[$tokenKey];
            }
        } 
        // Generate a new token for the form to use
        $newToken = bin2hex(random_bytes(16));
        $_SESSION[$tokenKey . '_next'] = $newToken; // Store next token for the form to use
        return $newToken;
    }

    // Helper to get the next form token for use in the form
    public static function getFormToken($formIdentifier) {
         return $_SESSION['form_token_' . $formIdentifier . '_next'] ?? '';
    }

}

// Initialize session on script inclusion (if not already done elsewhere)
Session::init();

?>