<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $this->e($title); ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="<?php echo $this->theme_asset('css/admin.css'); ?>">
</head>
<body class="admin-login-body">
    <div class="admin-login-container">
        <div class="admin-login-box">
            <div class="admin-login-header">
                <h1>Shopologic Admin</h1>
                <p>Please sign in to continue</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo $this->e($error); ?>
                </div>
            <?php endif; ?>
            
            <form action="/admin/login" method="POST" class="admin-login-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="<?php echo $this->e($email); ?>"
                           placeholder="admin@example.com"
                           required 
                           autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           placeholder="Enter your password"
                           required>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" value="1">
                        <span>Remember me</span>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    Sign In to Admin Panel
                </button>
            </form>
            
            <div class="admin-login-footer">
                <p><a href="/admin/forgot-password">Forgot your password?</a></p>
                <p><a href="/">← Back to Store</a></p>
            </div>
            
            <div class="admin-login-info">
                <p class="demo-info">
                    <strong>Demo Admin Credentials:</strong><br>
                    Email: admin@shopologic.com<br>
                    Password: admin123
                </p>
            </div>
        </div>
    </div>
    
    <style>
        .admin-login-body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
        }
        
        .admin-login-container {
            width: 100%;
            max-width: 400px;
        }
        
        .admin-login-box {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            padding: 40px;
        }
        
        .admin-login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .admin-login-header h1 {
            margin: 0 0 10px;
            color: #333;
            font-size: 28px;
        }
        
        .admin-login-header p {
            margin: 0;
            color: #666;
            font-size: 16px;
        }
        
        .admin-login-form {
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input[type="email"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .checkbox-label input[type="checkbox"] {
            margin-right: 8px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-block {
            width: 100%;
            display: block;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .admin-login-footer {
            text-align: center;
            border-top: 1px solid #eee;
            padding-top: 20px;
            margin-top: 20px;
        }
        
        .admin-login-footer p {
            margin: 8px 0;
        }
        
        .admin-login-footer a {
            color: #667eea;
            text-decoration: none;
        }
        
        .admin-login-footer a:hover {
            text-decoration: underline;
        }
        
        .admin-login-info {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 6px;
            text-align: center;
        }
        
        .demo-info {
            margin: 0;
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .demo-info strong {
            color: #333;
        }
    </style>
</body>
</html>