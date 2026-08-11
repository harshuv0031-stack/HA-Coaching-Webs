<?php

session_start();

if (!isset($_SESSION["student_id"])) {

    header("Location: login.html");

    exit;

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Student Dashboard</title>

    <link rel="stylesheet"
          href="style.css">

</head>

<body>

    <div style="
        padding:40px;
        font-family:Arial;
    ">

        <h1>
            Welcome,
            <?php echo htmlspecialchars(
                $_SESSION["student_name"]
            ); ?>
        </h1>

        <br>

        <p>
            Email:
            <?php echo htmlspecialchars(
                $_SESSION["student_email"]
            ); ?>
        </p>

        <p>
            Course:
            <?php echo htmlspecialchars(
                $_SESSION["student_course"]
            ); ?>
        </p>

        <br>

        <a href="logout.php">
            Logout
        </a>

    </div>

</body>

</html>