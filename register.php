<?php
require_once "config/db.php";

$message = "";
$messageType = "";

$name = "";
$email = "";
$student_id = "";
$department = "";
$semester = "";
$bio = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Collect and clean form data
    $name = trim($_POST["name"] ?? "");
    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";
    $student_id = trim($_POST["student_id"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $semester = trim($_POST["semester"] ?? "");
    $bio = trim($_POST["bio"] ?? "");

    // Required field validation
    if (
        $name === "" ||
        $email === "" ||
        $password === "" ||
        $student_id === "" ||
        $department === "" ||
        $semester === ""
    ) {
        $message = "Please fill in all required fields.";
        $messageType = "error";

    } elseif (strlen($name) > 100) {
        $message = "Name must not exceed 100 characters.";
        $messageType = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "error";

    } elseif (strlen($email) > 255) {
        $message = "Email address is too long.";
        $messageType = "error";

    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters long.";
        $messageType = "error";

    } elseif (strlen($student_id) > 50) {
        $message = "Student ID must not exceed 50 characters.";
        $messageType = "error";

    } elseif (strlen($department) > 100) {
        $message = "Department must not exceed 100 characters.";
        $messageType = "error";

    } elseif (
        !ctype_digit($semester) ||
        (int)$semester < 1 ||
        (int)$semester > 8
    ) {
        $message = "Please select a semester between 1 and 8.";
        $messageType = "error";

    } elseif (strlen($bio) > 1000) {
        $message = "Bio must not exceed 1000 characters.";
        $messageType = "error";

    } else {

        // Check whether the email already exists
        $check = $conn->prepare(
            "SELECT user_id FROM `user` WHERE email = ? LIMIT 1"
        );

        if (!$check) {
            $message = "Something went wrong. Please try again.";
            $messageType = "error";

        } else {
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $message = "This email is already registered.";
                $messageType = "error";

            } else {
                // Securely hash the password
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // New users receive Student role_id = 1
                $role_id = 1;
                $semester = (int)$semester;

                $stmt = $conn->prepare(
                    "INSERT INTO `user`
                    (
                        role_id,
                        name,
                        email,
                        password,
                        student_id,
                        department,
                        semester,
                        bio
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );

                if (!$stmt) {
                    $message = "Registration could not be completed.";
                    $messageType = "error";

                } else {
                    $stmt->bind_param(
                        "isssssis",
                        $role_id,
                        $name,
                        $email,
                        $hashedPassword,
                        $student_id,
                        $department,
                        $semester,
                        $bio
                    );

                    if ($stmt->execute()) {
                        $message = "Registration successful! You can now log in.";
                        $messageType = "success";

                        // Clear form fields after successful registration
                        $name = "";
                        $email = "";
                        $student_id = "";
                        $department = "";
                        $semester = "";
                        $bio = "";

                    } else {
                        $message = "Registration failed. Please try again.";
                        $messageType = "error";
                    }

                    $stmt->close();
                }
            }

            $check->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Registration | SkillSync</title>

    <link rel="stylesheet" href="assets/style.css">

    <style>
        .register-container {
            max-width: 600px;
            margin: 35px auto;
            padding: 25px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .register-container h1 {
            text-align: center;
            margin-bottom: 10px;
        }

        .register-description {
            text-align: center;
            color: #666;
            margin-bottom: 25px;
        }

        .register-container label {
            display: block;
            margin-top: 14px;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .register-container input,
        .register-container select,
        .register-container textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
        }

        .register-container textarea {
            min-height: 90px;
            resize: vertical;
        }

        .register-container button {
            width: 100%;
            margin-top: 22px;
            padding: 12px;
            cursor: pointer;
        }

        .register-container .message {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 6px;
        }

        .register-container .success {
            background: #e5f8e9;
            color: #176b2c;
        }

        .register-container .error {
            background: #ffe8e8;
            color: #a32222;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>

<body>

<nav>
    <a href="index.php">Home</a>
    <a href="login.php">Login</a>
</nav>

<div class="register-container">

    <h1>Create Your Account</h1>

    <p class="register-description">
        Join SkillSync and start learning new skills with other students.
    </p>

    <?php if ($message !== ""): ?>
        <div class="message <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="register.php">

        <label for="name">Full Name *</label>
        <input
            type="text"
            id="name"
            name="name"
            maxlength="100"
            value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <label for="email">Email Address *</label>
        <input
            type="email"
            id="email"
            name="email"
            maxlength="255"
            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <label for="password">Password *</label>
        <input
            type="password"
            id="password"
            name="password"
            minlength="8"
            autocomplete="new-password"
            required
        >

        <label for="student_id">Student ID *</label>
        <input
            type="text"
            id="student_id"
            name="student_id"
            maxlength="50"
            value="<?= htmlspecialchars($student_id, ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <label for="department">Department *</label>
        <input
            type="text"
            id="department"
            name="department"
            maxlength="100"
            value="<?= htmlspecialchars($department, ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <label for="semester">Semester *</label>
        <select id="semester" name="semester" required>
            <option value="">Select Semester</option>

            <?php for ($i = 1; $i <= 8; $i++): ?>
                <option
                    value="<?= $i ?>"
                    <?= (string)$semester === (string)$i ? "selected" : "" ?>
                >
                    Semester <?= $i ?>
                </option>
            <?php endfor; ?>
        </select>

        <label for="bio">About Yourself (Optional)</label>
        <textarea
            id="bio"
            name="bio"
            maxlength="1000"
            placeholder="Write a few lines about yourself..."
        ><?= htmlspecialchars($bio, ENT_QUOTES, 'UTF-8') ?></textarea>

        <button type="submit">Register</button>

    </form>

    <div class="login-link">
        Already have an account?
        <a href="login.php">Login here</a>
    </div>

</div>

</body>
</html>