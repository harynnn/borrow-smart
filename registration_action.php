<?php
define('SECURE_ACCESS', true);
require_once 'includes/init.php';

// Redirect if already logged in
if (isset($_SESSION['uid'])) {
    header('Location: ' . $_SESSION['role'] . '_dashboard.php');
    exit();
}

try {
    // Verify CSRF token
    if (!$securityHelper->verifyCsrfToken($_POST['csrf_token'])) {
        throw new Exception("Invalid request. Please try again.");
    }

    // Validate required fields
    $required_fields = ['name', 'email', 'matric', 'department', 'password', 'confirm_password', 'terms'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("All fields are required.");
        }
    }

    // Clean and validate input
    $name = trim($_POST['name']);
    $email = strtolower(trim($_POST['email']));
    $matric = strtoupper(trim($_POST['matric']));
    $department = trim($_POST['department']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate name (letters and spaces only)
    if (!preg_match('/^[A-Za-z\s]+$/', $name)) {
        throw new Exception("Name can only contain letters and spaces.");
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Please enter a valid email address.");
    }

    // Check if email is from UTHM domain
    if (!preg_match('/@(uthm\.edu\.my|student\.uthm\.edu\.my)$/', $email)) {
        throw new Exception("Please use your UTHM email address.");
    }

    // Validate matric number format
    if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z]{2}[0-9]{4}$/', $matric)) {
        throw new Exception("Please enter a valid matric number (e.g., AB12CD3456).");
    }

    // Validate department
    if (!array_key_exists($department, DEPARTMENTS)) {
        throw new Exception("Please select a valid department.");
    }

    // Validate password
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        throw new Exception("Password must be at least " . PASSWORD_MIN_LENGTH . " characters long.");
    }
    if (!preg_match('/[A-Z]/', $password)) {
        throw new Exception("Password must contain at least one uppercase letter.");
    }
    if (!preg_match('/[a-z]/', $password)) {
        throw new Exception("Password must contain at least one lowercase letter.");
    }
    if (!preg_match('/[0-9]/', $password)) {
        throw new Exception("Password must contain at least one number.");
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        throw new Exception("Password must contain at least one special character.");
    }

    // Check if passwords match
    if ($password !== $confirm_password) {
        throw new Exception("Passwords do not match.");
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Check if email already exists
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("This email address is already registered.");
        }

        // Check if matric number already exists
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM users 
            WHERE matric = ?
        ");
        $stmt->execute([$matric]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("This matric number is already registered.");
        }

        // Get student role ID
        $stmt = $pdo->prepare("
            SELECT role_id 
            FROM roles 
            WHERE role_name = 'student'
        ");
        $stmt->execute();
        $role_id = $stmt->fetchColumn();

        if (!$role_id) {
            throw new Exception("System configuration error. Please contact support.");
        }

        // Generate verification token
        $verification_token = $securityHelper->generateRandomString(32);

        // Create user
        $stmt = $pdo->prepare("
            INSERT INTO users (
                name, email, matric, department, password, role_id, 
                verification_token, status, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, 'pending', NOW()
            )
        ");
        $stmt->execute([
            $name,
            $email,
            $matric,
            $department,
            password_hash($password, PASSWORD_DEFAULT),
            $role_id,
            $verification_token
        ]);

        $user_id = $pdo->lastInsertId();

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
        $mail->Subject = 'Verify Your Email - ' . APP_NAME;
        
        $verificationLink = APP_URL . '/verify_email.php?token=' . $verification_token;
        
        $mail->Body = '
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
                }
                .button {
                    display: inline-block;
                    padding: 10px 20px;
                    background-color: #000;
                    color: #fff;
                    text-decoration: none;
                    border-radius: 5px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h2>Welcome to ' . APP_NAME . '!</h2>
                <p>Hello ' . htmlspecialchars($name) . ',</p>
                <p>Thank you for registering. Please verify your email address by clicking the button below:</p>
                <p>
                    <a href="' . $verificationLink . '" class="button">Verify Email</a>
                </p>
                <p>Or copy and paste this link in your browser:</p>
                <p>' . $verificationLink . '</p>
                <p>This link will expire in 24 hours.</p>
                <p>If you did not create an account, please ignore this email.</p>
                <p>Best regards,<br>' . APP_NAME . ' Team</p>
            </div>
        </body>
        </html>';
        
        $mail->AltBody = "Hello " . $name . ",\n\n"
                       . "Thank you for registering. Please verify your email address by clicking this link:\n\n"
                       . $verificationLink . "\n\n"
                       . "This link will expire in 24 hours.\n\n"
                       . "If you did not create an account, please ignore this email.\n\n"
                       . "Best regards,\n"
                       . APP_NAME . " Team";

        $mail->send();

        // Log registration
        $securityHelper->logSecurityEvent(
            $user_id,
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
        throw $e;
    }

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: register.php');
    exit();
}
?>
