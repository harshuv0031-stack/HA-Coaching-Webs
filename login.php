<?php

session_start();

require_once "db.php";

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";


    if (empty($email) || empty($password)) {

        $message = "Please enter email and password.";
        $message_type = "error";

    } else {

        $stmt = $conn->prepare(
            "SELECT id, fullname, email, course, password
             FROM students
             WHERE email = ?"
        );

        $stmt->bind_param("s", $email);

        $stmt->execute();

        $result = $stmt->get_result();


        if ($result->num_rows === 1) {

            $student = $result->fetch_assoc();


            if (password_verify(
                $password,
                $student["password"]
            )) {

                $_SESSION["student_id"] = $student["id"];

                $_SESSION["student_name"] =
                    $student["fullname"];

                $_SESSION["student_email"] =
                    $student["email"];

                $_SESSION["student_course"] =
                    $student["course"];


                header("Location: dashboard.php");

                exit;

            } else {

                $message = "Incorrect password.";
                $message_type = "error";

            }

        } else {

            $message = "Account not found.";
            $message_type = "error";

        }

        $stmt->close();

    }
}

?>