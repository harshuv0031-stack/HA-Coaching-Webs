<?php
session_start();

require_once "db.php";
require_once "smtp_mailer.php";

$message = "";
$message_type = "";
$showOtpForm = false;

function clean($value): string
{
    return trim((string)$value);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "send_otp";

    /*
     * STEP 1: Validate registration details, generate OTP,
     * store details temporarily in session, and email OTP
     * to the student's entered email address.
     */
    if ($action === "send_otp") {

        $fullname = clean($_POST["fullname"] ?? "");
        $fathername = clean($_POST["fathername"] ?? "");
        $email = strtolower(clean($_POST["email"] ?? ""));
        $mobile = clean($_POST["mobile"] ?? "");
        $course = clean($_POST["course"] ?? "");
        $password = $_POST["password"] ?? "";
        $confirm_password = $_POST["confirm_password"] ?? "";

        if ($fullname === "" || $fathername === "" || $email === "" ||
            $mobile === "" || $course === "" || $password === "" || $confirm_password === "") {

            $message = "Please fill all registration fields.";
            $message_type = "error";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $message = "Please enter a valid email address.";
            $message_type = "error";

        } elseif (!preg_match("/^[0-9]{10}$/", $mobile)) {

            $message = "Please enter a valid 10-digit mobile number.";
            $message_type = "error";

        } elseif ($password !== $confirm_password) {

            $message = "Passwords do not match.";
            $message_type = "error";

        } elseif (strlen($password) < 6) {

            $message = "Password must contain at least 6 characters.";
            $message_type = "error";

        } else {

            // Do not send OTP if this email is already registered.
            $check = $conn->prepare("SELECT id FROM students WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows > 0) {

                $message = "This email is already registered.";
                $message_type = "error";

            } else {

                $otp = (string) random_int(100000, 999999);

                $_SESSION["registration_otp_hash"] = password_hash($otp, PASSWORD_DEFAULT);
                $_SESSION["registration_otp_expires"] = time() + 600; // 10 minutes
                $_SESSION["registration_data"] = [
                    "fullname" => $fullname,
                    "fathername" => $fathername,
                    "email" => $email,
                    "mobile" => $mobile,
                    "course" => $course,
                    "password" => $password
                ];

                if (sendOtpEmail($email, $otp)) {

                    $showOtpForm = true;
                    $message = "OTP has been sent to " . htmlspecialchars($email) . ". Please check your inbox/spam folder.";
                    $message_type = "success";

                } else {

                    unset(
                        $_SESSION["registration_otp_hash"],
                        $_SESSION["registration_otp_expires"],
                        $_SESSION["registration_data"]
                    );

                    $message = "OTP could not be sent. Please check your Gmail SMTP settings in smtp_config.php.";
                    $message_type = "error";
                }
            }

            $check->close();
        }

    /*
     * STEP 2: Verify OTP. Only after successful verification
     * is the student inserted into the database.
     */
    } elseif ($action === "verify_otp") {

        $enteredOtp = preg_replace("/\D/", "", $_POST["otp"] ?? "");

        if (!isset($_SESSION["registration_otp_hash"], $_SESSION["registration_otp_expires"], $_SESSION["registration_data"])) {

            $message = "Your OTP session has expired. Please register again.";
            $message_type = "error";

        } elseif (time() > (int) $_SESSION["registration_otp_expires"]) {

            unset(
                $_SESSION["registration_otp_hash"],
                $_SESSION["registration_otp_expires"],
                $_SESSION["registration_data"]
            );

            $message = "OTP has expired. Please register again.";
            $message_type = "error";

        } elseif (!preg_match("/^[0-9]{6}$/", $enteredOtp) ||
                  !password_verify($enteredOtp, $_SESSION["registration_otp_hash"])) {

            $showOtpForm = true;
            $message = "Invalid OTP. Please enter the correct 6-digit OTP.";
            $message_type = "error";

        } else {

            $data = $_SESSION["registration_data"];

            // Check once again before inserting.
            $check = $conn->prepare("SELECT id FROM students WHERE email = ?");
            $check->bind_param("s", $data["email"]);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows > 0) {

                unset(
                    $_SESSION["registration_otp_hash"],
                    $_SESSION["registration_otp_expires"],
                    $_SESSION["registration_data"]
                );

                $message = "This email is already registered.";
                $message_type = "error";

            } else {

                $hashed_password = password_hash(
                    $data["password"],
                    PASSWORD_DEFAULT
                );

                $stmt = $conn->prepare(
                    "INSERT INTO students
                    (fullname, fathername, email, mobile, course, password)
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "ssssss",
                    $data["fullname"],
                    $data["fathername"],
                    $data["email"],
                    $data["mobile"],
                    $data["course"],
                    $hashed_password
                );

                if ($stmt->execute()) {

                    unset(
                        $_SESSION["registration_otp_hash"],
                        $_SESSION["registration_otp_expires"],
                        $_SESSION["registration_data"]
                    );

                    $stmt->close();
                    $check->close();

                    header("Location: login.html?registered=1");
                    exit;

                } else {

                    $message = "Registration failed. Please try again.";
                    $message_type = "error";
                }

                $stmt->close();
            }

            $check->close();
        }
    }
}

$sessionEmail = $_SESSION["registration_data"]["email"] ?? "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - ABC Coaching</title>
    <link rel="stylesheet" href="auth.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <style>
        .otp-box {
            text-align: center;
            margin-top: 10px;
        }
        .otp-input {
            width: 100%;
            padding: 14px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            text-align: center;
            font-size: 24px;
            letter-spacing: 8px;
            outline: none;
        }
        .otp-info {
            margin: 10px 0 18px;
            color: #475569;
            font-size: 14px;
            line-height: 1.6;
        }
        .success-message,
        .error-message {
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 14px;
        }
        .success-message {
            background: #dcfce7;
            color: #166534;
        }
        .error-message {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>

<body>
<div class="auth-page">
    <div class="auth-container">

        <div class="auth-left">
            <div class="brand">
                <img src="logo.png" alt="H.A Coaching Logo">
                <h1>ABC Coaching</h1>
            </div>

            <h2>Start Your Learning Journey</h2>

            <p>
                Join our coaching institute and start learning
                with experienced teachers and quality study material.
            </p>

            <div class="features">
                <div>
                    <i class="fa-solid fa-user-graduate"></i>
                    <span>Expert Teachers</span>
                </div>
                <div>
                    <i class="fa-solid fa-book-open"></i>
                    <span>Quality Study Material</span>
                </div>
                <div>
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Track Your Progress</span>
                </div>
            </div>
        </div>

        <div class="auth-right">

            <div class="form-header">
                <h2><?php echo $showOtpForm ? "Verify Email" : "Create Account"; ?></h2>
                <p>
                    <?php echo $showOtpForm
                        ? "Enter the OTP sent to your email"
                        : "Register as a student"; ?>
                </p>
            </div>

            <?php if ($message !== ""): ?>
                <div class="<?php echo $message_type === "success" ? "success-message" : "error-message"; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($showOtpForm): ?>

                <form action="register.php" method="post">

                    <input type="hidden" name="action" value="verify_otp">

                    <div class="otp-box">
                        <i class="fa-solid fa-envelope"
                           style="font-size:45px; margin-bottom:12px;"></i>

                        <div class="otp-info">
                            A 6-digit OTP has been sent to
                            <strong><?php echo htmlspecialchars($sessionEmail); ?></strong>.
                            It is valid for 10 minutes.
                        </div>

                        <div class="input-group">
                            <label>Enter OTP</label>

                            <div class="input-box">
                                <i class="fa-solid fa-key"></i>

                                <input
                                    class="otp-input"
                                    type="text"
                                    name="otp"
                                    inputmode="numeric"
                                    autocomplete="one-time-code"
                                    maxlength="6"
                                    pattern="[0-9]{6}"
                                    placeholder="000000"
                                    required>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="auth-btn">
                        <i class="fa-solid fa-circle-check"></i>
                        Verify OTP & Create Account
                    </button>

                </form>

                <div class="bottom-text">
                    OTP नहीं मिला? Check your Spam/Junk folder.
                </div>

            <?php else: ?>

                <form action="register.php" method="post">

                    <input type="hidden" name="action" value="send_otp">

                    <div class="input-group">
                        <label>Full Name</label>
                        <div class="input-box">
                            <i class="fa-solid fa-user"></i>
                            <input type="text" name="fullname"
                                   placeholder="Enter your full name" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Father's Name</label>
                        <div class="input-box">
                            <i class="fa-solid fa-user-tie"></i>
                            <input type="text" name="fathername"
                                   placeholder="Enter father's name" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Email Address</label>
                        <div class="input-box">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="email" name="email"
                                   placeholder="Enter your email" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Mobile Number</label>
                        <div class="input-box">
                            <i class="fa-solid fa-phone"></i>
                            <input type="tel" name="mobile"
                                   placeholder="Enter mobile number"
                                   maxlength="10" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Select Course</label>
                        <div class="input-box">
                            <i class="fa-solid fa-graduation-cap"></i>
                            <select name="course" required>
                                <option value="">Select your course</option>
                                <option value="web-development">Web Development</option>
                                <option value="python">Python Programming</option>
                                <option value="java">Java Programming</option>
                                <option value="c-programming">C Programming</option>
                                <option value="spoken-english">Spoken English</option>
                                <option value="competitive-exam">Competitive Exams</option>
                            </select>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Password</label>
                        <div class="input-box">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password"
                                   id="registerPassword"
                                   name="password"
                                   placeholder="Create password" required>
                            <i class="fa-solid fa-eye password-eye"
                               onclick="togglePassword('registerPassword', this)"></i>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Confirm Password</label>
                        <div class="input-box">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password"
                                   id="confirmPassword"
                                   name="confirm_password"
                                   placeholder="Confirm password" required>
                            <i class="fa-solid fa-eye password-eye"
                               onclick="togglePassword('confirmPassword', this)"></i>
                        </div>
                    </div>

                    <div class="terms">
                        <input type="checkbox" required>
                        <span>
                            I agree to the
                            <a href="#">Terms & Conditions</a>
                        </span>
                    </div>

                    <button type="submit" class="auth-btn">
                        <i class="fa-solid fa-envelope"></i>
                        Send OTP & Continue
                    </button>
                </form>

            <?php endif; ?>

            <div class="bottom-text">
                Already have an account?
                <a href="login.html">Login Now</a>
            </div>

        </div>
    </div>
</div>

<script>
function togglePassword(inputId, icon) {
    const input = document.getElementById(inputId);

    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}
</script>
</body>
</html>
