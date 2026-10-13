<?php

session_start();

require_once "config/db.php";

$message = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get form data safely
    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";

    // Validate input
    if ($email === "" || $password === "") {

        $message = "Please enter both email and password.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";

    } else {

        // Find user by email
        $stmt = $conn->prepare(
            "SELECT user_id, role_id, name, email, password
             FROM `user`
             WHERE email = ?
             LIMIT 1"
        );

        if (!$stmt) {

            $message = "Something went wrong. Please try again.";

        } else {

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 1) {

                $user = $result->fetch_assoc();

                // Verify password
                if (password_verify($password, $user["password"])) {

                    // Prevent session fixation
                    session_regenerate_id(true);

                    // Store user information in session
                    $_SESSION["user_id"] = $user["user_id"];
                    $_SESSION["role_id"] = $user["role_id"];
                    $_SESSION["name"] = $user["name"];
                    $_SESSION["email"] = $user["email"];

                    $stmt->close();

                    // Redirect to dashboard
                    header("Location: dashboard.php");
                    exit();

                } else {

                    $message = "Incorrect email or password.";

                }

            } else {

                $message = "Incorrect email or password.";

            }

            $stmt->close();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - SkillSync</title>

    <link rel="stylesheet" href="assets/style.css">

</head>

<body>

<nav>

    <h2>SkillSync</h2>

    <div>
        <a href="index.php">Home</a>
        <a href="register.php">Register</a>
    </div>

</nav>

<div class="container">

    <div class="form-box">

        <h1>Login</h1>

        <p>Login to your SkillSync account.</p>

        <?php if ($message !== ""): ?>

            <div class="message">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="login.php">

            <label for="email">Email</label>

            <input
                type="email"
                id="email"
                name="email"
                placeholder="Enter your email"
                value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="username"
                required
            >

            <label for="password">Password</label>

            <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter your password"
                autocomplete="current-password"
                required
            >

            <button type="submit">
                Login
            </button>

        </form>

        <p>
            Don't have an account?
            <a href="register.php">Register here</a>
        </p>

    </div>

</div>

</body>

</html>