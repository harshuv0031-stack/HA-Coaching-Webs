<?php
/*
|--------------------------------------------------------------------------
| Gmail SMTP Configuration
|--------------------------------------------------------------------------
| IMPORTANT:
| 1. Put YOUR Gmail address below.
| 2. Enable 2-Step Verification on that Gmail account.
| 3. Create a Google App Password.
| 4. Put the 16-character App Password below.
|
| The student's email is NOT entered here. It is taken automatically
| from the registration form and used as the OTP recipient.
|--------------------------------------------------------------------------
*/

define("SMTP_HOST", "smtp.gmail.com");
define("SMTP_PORT", 465);

define("SMTP_USERNAME", "YOUR_GMAIL@gmail.com");
define("SMTP_PASSWORD", "YOUR_16_CHARACTER_APP_PASSWORD");

define("SMTP_FROM_EMAIL", "YOUR_GMAIL@gmail.com");
define("SMTP_FROM_NAME", "H.A Coaching Center");
?>
