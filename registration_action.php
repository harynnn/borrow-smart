<?php
define('SECURE_ACCESS', true);
require_once 'includes/init.php';

// Redirect if already logged in
if (isset($_SESSION['uid'])) {
    header('Location: ' . $_SESSION['role'] . '_dashboard.php');
    exit();
}

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit();
}

// Verify CSRF token
if (!$securityHelper->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = "Invalid request. Please try again.";
    header('Location: register.php');
    exit();
}

// Validate required fields
$required_fields = ['name', 'email', 'matric', 'department', 'password', 'confirm_password', 'terms'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        $_SESSION['error'] = "Please fill in all required fields.";
        header('Location: register.php');
        exit();
    }
}

// Clean input data
$name = cleanInput($_POST['name']);
$email = strtolower(cleanInput($_POST['email']));
$matric = strtoupper(cleanInput($_POST['matric']));
$department = cleanInput($_POST['department']);
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Validate name format
    if (!preg_match('/^[A-Za-z\s]{2,100}$/', $name)) {
        throw new Exception("Please enter a valid name (letters and spaces only, 2-100 characters).");
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Please enter a valid email address.");
    }

    // Validate matric number format
    if (!preg_match('/^[A-Z][A-Z][0-9]{2}[A-Z]{2}[0-9]{4}$/', $matric)) {
        throw new Exception("Please enter a valid matric number (e.g., AI20EC0123).");
    }

    // Validate department
    if (!array_key_exists($department, DEPARTMENTS)) {
        throw new Exception("Please select a valid department.");
    }

    // Validate password match
    if ($password !== $confirm_password) {
        throw new Exception("Passwords do not match.");
    }

    // Validate password strength
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
        throw new Exception("Password must be at least 8 characters long and include uppercase, lowercase, number, and special character.");
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("This email address is already registered.");
    }

    // Check if matric number already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE matric = ?");
    $stmt->execute([$matric]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("This matric number is already registered.");
    }

    // Get student role ID
    $stmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = 'student'");
    $stmt->execute();
    $roleId = $stmt->fetchColumn();

    if (!$roleId) {
        throw new Exception("System configuration error. Please contact support.");
    }

    // Generate verification token
    $verificationToken = bin2hex(random_bytes(32));

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $stmt = $pdo->prepare("
        INSERT INTO users (
            name, email, matric, department, password, role_id, 
            status, email_verified, verification_token, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, 
            'pending', 0, ?, NOW()
        )
    ");
    $stmt->execute([
        $name,
        $email,
        $matric,
        $department,
        $hashedPassword,
        $roleId,
        $verificationToken
    ]);

    $userId = $pdo->lastInsertId();

    // Send verification email
    require_once 'PHPMailer-master/src/PHPMailer.php';
    require_once 'PHPMailer-master/src/SMTP.php';
    require_once 'PHPMailer-master/src/Exception.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    // Server settings
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;

    // Recipients
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress($email, $name);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Verify Your Email - BorrowSmart';
    
    $verificationUrl = APP_URL . '/verify_email.php?token=' . $verificationToken;
    
    $emailContent = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                line-height: 1.6; 
                color: #333;
            }
            .container { 
                max-width: 600px; 
                margin: 0 auto; 
                padding: 20px;
                background-color: #f8f9fa;
            }
            .header { 
                text-align: center; 
                margin-bottom: 30px;
                padding: 20px;
                background-color: #fff;
                border-radius: 8px;
            }
            .button {
                display: inline-block;
                padding: 12px 24px;
                background-color: #1a1a1a;
                color: #ffffff;
                text-decoration: none;
                border-radius: 4px;
                margin: 20px 0;
            }
            .warning {
                background-color: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 12px;
                margin: 20px 0;
            }
            .footer { 
                text-align: center; 
                margin-top: 30px;
                font-size: 12px;
                color: #666;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>Welcome to BorrowSmart!</h2>
            </div>
            <p>Hello ' . htmlspecialchars($name) . ',</p>
            <p>Thank you for registering with BorrowSmart. To complete your registration and activate your account, please click the button below:</p>
            <div style="text-align: center;">
                <a href="' . $verificationUrl . '" class="button">Verify Email Address</a>
            </div>
            <p>Or copy and paste this URL into your browser:</p>
            <p>' . $verificationUrl . '</p>
            <div class="warning">
                <p><strong>Important:</strong></p>
                <ul>
                    <li>This verification link will expire in 24 hours</li>
                    <li>If you did not create this account, please ignore this email</li>
                </ul>
            </div>
            <div class="footer">
                <p>This is an automated message from BorrowSmart System. Please do not reply.</p>
                <p>&copy; ' . date('Y') . ' BorrowSmart - UTHM. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    $mail->Body = $emailContent;
    $mail->AltBody = "Welcome to BorrowSmart!\n\n"
                   . "Please verify your email address by clicking this link:\n"
                   . $verificationUrl . "\n\n"
                   . "This link will expire in 24 hours.";

    $mail->send();

    // Log registration event
    $securityHelper->logSecurityEvent(
        $userId,
        'REGISTRATION',
        'New user registration'
    );

    // Commit transaction
    $pdo->commit();

    // Set success message
    $_SESSION['success'] = "Registration successful! Please check your email to verify your account.";

    // Redirect to login page
    header('Location: login.php');
    exit();

} catch (Exception $e) {
    // Rollback transaction
    $pdo->rollBack();

    error_log("Registration Error: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
    header('Location: register.php');
    exit();
}
?>
