<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth;

use Shopologic\Core\Plugin\HookSystem;

class AuthService
{
    private array $users = [];
    private ?array $currentUser = null;
    
    public function __construct()
    {
        $this->initializeUsers();
        $this->loadCurrentUser();
    }
    
    private function initializeUsers(): void
    {
        // Sample users for demo
        $this->users = [
            'john@example.com' => [
                'id' => 1,
                'email' => 'john@example.com',
                'password' => password_hash('password123', PASSWORD_DEFAULT),
                'first_name' => 'John',
                'last_name' => 'Doe',
                'phone' => '+1-555-0123',
                'created_at' => '2024-01-15 10:30:00',
                'is_active' => true,
                'role' => 'customer',
                'addresses' => [
                    [
                        'id' => 1,
                        'type' => 'billing',
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                        'company' => '',
                        'address_1' => '123 Main St',
                        'address_2' => 'Apt 4B',
                        'city' => 'New York',
                        'state' => 'NY',
                        'postcode' => '10001',
                        'country' => 'US',
                        'is_default' => true
                    ],
                    [
                        'id' => 2,
                        'type' => 'shipping',
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                        'company' => '',
                        'address_1' => '456 Oak Avenue',
                        'address_2' => '',
                        'city' => 'Brooklyn',
                        'state' => 'NY',
                        'postcode' => '11201',
                        'country' => 'US',
                        'is_default' => false
                    ]
                ],
                'preferences' => [
                    'newsletter' => true,
                    'marketing_emails' => true,
                    'order_updates' => true
                ]
            ],
            'jane@example.com' => [
                'id' => 2,
                'email' => 'jane@example.com',
                'password' => password_hash('secure456', PASSWORD_DEFAULT),
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'phone' => '+1-555-0456',
                'created_at' => '2024-02-20 14:15:00',
                'is_active' => true,
                'role' => 'customer',
                'addresses' => [
                    [
                        'id' => 3,
                        'type' => 'billing',
                        'first_name' => 'Jane',
                        'last_name' => 'Smith',
                        'company' => 'Tech Solutions Inc',
                        'address_1' => '789 Business Blvd',
                        'address_2' => 'Suite 200',
                        'city' => 'San Francisco',
                        'state' => 'CA',
                        'postcode' => '94105',
                        'country' => 'US',
                        'is_default' => true
                    ]
                ],
                'preferences' => [
                    'newsletter' => false,
                    'marketing_emails' => false,
                    'order_updates' => true
                ]
            ]
        ];
    }
    
    private function loadCurrentUser(): void
    {
        session_start();
        if (isset($_SESSION['user_id']) && isset($_SESSION['user_email'])) {
            $user = $this->getUserByEmail($_SESSION['user_email']);
            if ($user && $user['id'] === $_SESSION['user_id']) {
                $this->currentUser = $user;
            }
        }
    }
    
    public function login(string $email, string $password): array
    {
        $email = filter_var($email, FILTER_VALIDATE_EMAIL);
        if (!$email) {
            return ['success' => false, 'message' => 'Invalid email address'];
        }
        
        $user = $this->getUserByEmail($email);
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
        
        if (!$user['is_active']) {
            return ['success' => false, 'message' => 'Account is deactivated'];
        }
        
        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
        
        // Login successful
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        
        $this->currentUser = $user;
        
        HookSystem::doAction('user.login_success', $user);
        
        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => $this->sanitizeUser($user)
        ];
    }
    
    public function register(array $userData): array
    {
        $errors = $this->validateRegistration($userData);
        if (!empty($errors)) {
            return ['success' => false, 'message' => 'Validation failed', 'errors' => $errors];
        }
        
        $email = $userData['email'];
        if ($this->getUserByEmail($email)) {
            return ['success' => false, 'message' => 'Email address already exists'];
        }
        
        // Create new user
        $newUser = [
            'id' => count($this->users) + 1,
            'email' => $email,
            'password' => password_hash($userData['password'], PASSWORD_DEFAULT),
            'first_name' => $userData['first_name'],
            'last_name' => $userData['last_name'],
            'phone' => $userData['phone'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'is_active' => true,
            'role' => 'customer',
            'addresses' => [],
            'preferences' => [
                'newsletter' => $userData['newsletter'] ?? false,
                'marketing_emails' => $userData['marketing_emails'] ?? false,
                'order_updates' => true
            ]
        ];
        
        $this->users[$email] = $newUser;
        
        HookSystem::doAction('user.registration_success', $newUser);
        
        return [
            'success' => true,
            'message' => 'Registration successful',
            'user' => $this->sanitizeUser($newUser)
        ];
    }
    
    public function logout(): void
    {
        if ($this->currentUser) {
            HookSystem::doAction('user.logout', $this->currentUser);
        }
        
        session_start();
        session_destroy();
        $this->currentUser = null;
    }
    
    public function isLoggedIn(): bool
    {
        return $this->currentUser !== null;
    }
    
    public function getCurrentUser(): ?array
    {
        return $this->currentUser ? $this->sanitizeUser($this->currentUser) : null;
    }
    
    public function updateProfile(array $profileData): array
    {
        if (!$this->isLoggedIn()) {
            return ['success' => false, 'message' => 'Not authenticated'];
        }
        
        $errors = $this->validateProfileUpdate($profileData);
        if (!empty($errors)) {
            return ['success' => false, 'message' => 'Validation failed', 'errors' => $errors];
        }
        
        // Update user data
        $email = $this->currentUser['email'];
        $allowedFields = ['first_name', 'last_name', 'phone'];
        
        foreach ($allowedFields as $field) {
            if (isset($profileData[$field])) {
                $this->users[$email][$field] = $profileData[$field];
                $this->currentUser[$field] = $profileData[$field];
            }
        }
        
        // Update preferences if provided
        if (isset($profileData['preferences'])) {
            $this->users[$email]['preferences'] = array_merge(
                $this->users[$email]['preferences'],
                $profileData['preferences']
            );
            $this->currentUser['preferences'] = $this->users[$email]['preferences'];
        }
        
        HookSystem::doAction('user.profile_updated', $this->currentUser);
        
        return [
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => $this->sanitizeUser($this->currentUser)
        ];
    }
    
    public function changePassword(string $currentPassword, string $newPassword): array
    {
        if (!$this->isLoggedIn()) {
            return ['success' => false, 'message' => 'Not authenticated'];
        }
        
        if (!password_verify($currentPassword, $this->currentUser['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'New password must be at least 8 characters long'];
        }
        
        $email = $this->currentUser['email'];
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $this->users[$email]['password'] = $hashedPassword;
        $this->currentUser['password'] = $hashedPassword;
        
        HookSystem::doAction('user.password_changed', $this->currentUser);
        
        return ['success' => true, 'message' => 'Password changed successfully'];
    }
    
    public function addAddress(array $addressData): array
    {
        if (!$this->isLoggedIn()) {
            return ['success' => false, 'message' => 'Not authenticated'];
        }
        
        $errors = $this->validateAddress($addressData);
        if (!empty($errors)) {
            return ['success' => false, 'message' => 'Validation failed', 'errors' => $errors];
        }
        
        $email = $this->currentUser['email'];
        $newAddress = [
            'id' => count($this->users[$email]['addresses']) + 1,
            'type' => $addressData['type'] ?? 'both',
            'first_name' => $addressData['first_name'],
            'last_name' => $addressData['last_name'],
            'company' => $addressData['company'] ?? '',
            'address_1' => $addressData['address_1'],
            'address_2' => $addressData['address_2'] ?? '',
            'city' => $addressData['city'],
            'state' => $addressData['state'],
            'postcode' => $addressData['postcode'],
            'country' => $addressData['country'],
            'is_default' => $addressData['is_default'] ?? false
        ];
        
        // If this is set as default, unset other defaults
        if ($newAddress['is_default']) {
            foreach ($this->users[$email]['addresses'] as &$address) {
                if ($address['type'] === $newAddress['type'] || $newAddress['type'] === 'both') {
                    $address['is_default'] = false;
                }
            }
        }
        
        $this->users[$email]['addresses'][] = $newAddress;
        $this->currentUser['addresses'] = $this->users[$email]['addresses'];
        
        HookSystem::doAction('user.address_added', $newAddress, $this->currentUser);
        
        return [
            'success' => true,
            'message' => 'Address added successfully',
            'address' => $newAddress
        ];
    }
    
    public function getDefaultAddress(string $type = 'billing'): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        foreach ($this->currentUser['addresses'] as $address) {
            if (($address['type'] === $type || $address['type'] === 'both') && $address['is_default']) {
                return $address;
            }
        }
        
        // If no default found, return first address of type
        foreach ($this->currentUser['addresses'] as $address) {
            if ($address['type'] === $type || $address['type'] === 'both') {
                return $address;
            }
        }
        
        return null;
    }
    
    private function getUserByEmail(string $email): ?array
    {
        return $this->users[$email] ?? null;
    }
    
    private function sanitizeUser(array $user): array
    {
        unset($user['password']);
        return $user;
    }
    
    private function validateRegistration(array $data): array
    {
        $errors = [];
        
        if (empty($data['first_name'])) {
            $errors['first_name'] = 'First name is required';
        }
        
        if (empty($data['last_name'])) {
            $errors['last_name'] = 'Last name is required';
        }
        
        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }
        
        if (empty($data['password'])) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters long';
        }
        
        if (empty($data['password_confirm'])) {
            $errors['password_confirm'] = 'Password confirmation is required';
        } elseif ($data['password'] !== $data['password_confirm']) {
            $errors['password_confirm'] = 'Passwords do not match';
        }
        
        return HookSystem::applyFilters('user.registration_validation_errors', $errors, $data);
    }
    
    private function validateProfileUpdate(array $data): array
    {
        $errors = [];
        
        if (isset($data['first_name']) && empty($data['first_name'])) {
            $errors['first_name'] = 'First name cannot be empty';
        }
        
        if (isset($data['last_name']) && empty($data['last_name'])) {
            $errors['last_name'] = 'Last name cannot be empty';
        }
        
        if (isset($data['phone']) && !empty($data['phone'])) {
            if (!preg_match('/^[\+]?[0-9\-\s\(\)]{10,}$/', $data['phone'])) {
                $errors['phone'] = 'Invalid phone number format';
            }
        }
        
        return HookSystem::applyFilters('user.profile_validation_errors', $errors, $data);
    }
    
    private function validateAddress(array $data): array
    {
        $errors = [];
        
        $required = ['first_name', 'last_name', 'address_1', 'city', 'postcode', 'country'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        
        if (isset($data['type']) && !in_array($data['type'], ['billing', 'shipping', 'both'])) {
            $errors['type'] = 'Invalid address type';
        }
        
        return HookSystem::applyFilters('user.address_validation_errors', $errors, $data);
    }
}