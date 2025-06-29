<?php

class AuthMiddleware {
    /**
     * Authenticates the user. If not logged in, redirects for browser requests
     * or sends a JSON error for AJAX/Fetch requests.
     *
     * @param User $user The User object to check authentication status.
     */
    public static function authenticate(User $user) {
        if (!$user->isLoggedIn()) {
            // Check if the request is an AJAX/Fetch request
            // HTTP_X_REQUESTED_WITH is common for jQuery AJAX
            // HTTP_ACCEPT containing application/json is standard for modern fetch API
            if ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
                
                // This is an AJAX/Fetch request, send JSON error response
                header('Content-Type: application/json', true, 401); // 401 Unauthorized
                echo json_encode(["status" => "error", "message" => "Unauthorized. Please log in."]);
                exit();
            } else {
                // Regular browser request, redirect to login page
                header("Location: /view/login.php");
                exit();
            }
        }
    }

    /**
     * Requires the authenticated user to have one of the specified roles.
     * If not, redirects for browser requests or sends a JSON error for AJAX/Fetch requests.
     *
     * @param User $user The User object whose role is to be checked.
     * @param array $allowedRoles An array of roles that are permitted.
     */
    public static function requireRole(User $user, array $allowedRoles) {
        // First, ensure the user is logged in (though authenticate() should handle this prior)
        if (!$user->isLoggedIn()) {
            // This case should ideally be caught by authenticate() first,
            // but as a fallback, handle it as unauthorized for JSON requests.
            if ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
                header('Content-Type: application/json', true, 401); // 401 Unauthorized
                echo json_encode(["status" => "error", "message" => "Unauthorized. Please log in."]);
                exit();
            } else {
                header("Location: /view/login.php");
                exit();
            }
        }

        // Check if user has any of the required roles
        $hasRequiredRole = false;
        foreach ($allowedRoles as $role) {
            if ($user->hasRole($role)) {
                $hasRequiredRole = true;
                break; // Found a matching role, no need to check further
            }
        }

        // If no matching role is found, deny access
        if (!$hasRequiredRole) {
            if ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
                
                // This is an AJAX/Fetch request, send JSON error response
                header('Content-Type: application/json', true, 403); // 403 Forbidden
                echo json_encode(["status" => "error", "message" => "Forbidden. You do not have the required role."]);
                exit();
            } else {
                // Regular browser request, redirect to an unauthorized page or dashboard with error
                // You might have an 'unauthorized.php' page, or redirect to dashboard with a message
                header("Location: /view/dashboard.php?error=unauthorized_role");
                exit();
            }
        }
    }
}
