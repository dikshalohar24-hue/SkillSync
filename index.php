<?php
session_start();

$isLoggedIn = isset($_SESSION["user_id"]);
$name = $_SESSION["name"] ?? "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SkillSync - Learn Together, Grow Together</title>

    <link rel="stylesheet" href="assets/style.css">

    <style>
        .home-hero {
            text-align: center;
            padding: 65px 25px;
        }

        .home-hero h1 {
            font-size: 42px;
        }

        .home-hero p {
            max-width: 700px;
            margin: 20px auto;
            font-size: 18px;
        }

        .home-buttons {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 25px;
        }

        .home-buttons .btn {
            margin-top: 0;
        }

        .btn-light {
            background: white;
            color: #1e40af;
            border: 1px solid #dbeafe;
        }

        .section-title {
            text-align: center;
            margin: 40px 0 20px;
        }

        .feature-card {
            text-align: center;
        }

        .feature-icon {
            font-size: 35px;
        }

        .home-footer {
            text-align: center;
            padding: 25px;
            margin-top: 40px;
            background: #1e3a8a;
            color: white;
        }

        @media (max-width: 600px) {
            .home-hero {
                padding: 40px 15px;
            }

            .home-hero h1 {
                font-size: 30px;
            }
        }
    </style>
</head>

<body>

<nav>
    <h2>SkillSync</h2>

    <div>
        <a href="index.php">Home</a>

        <?php if ($isLoggedIn) { ?>
            <a href="dashboard.php">Dashboard</a>
            <a href="logout.php">Logout</a>
        <?php } else { ?>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        <?php } ?>
    </div>
</nav>

<div class="container">

    <section class="hero home-hero">
        <h1>Learn Together, Grow Together!</h1>

        <p>
            Welcome to SkillSync — a student skill exchange
            and peer learning platform where students can
            teach what they know and learn what they need.
        </p>

        <?php if ($isLoggedIn) { ?>

            <p>
                Welcome back,
                <strong><?php echo htmlspecialchars($name); ?></strong>!
            </p>

            <div class="home-buttons">
                <a class="btn" href="dashboard.php">Go to Dashboard</a>
            </div>

        <?php } else { ?>

            <div class="home-buttons">
                <a class="btn" href="register.php">Join SkillSync</a>
                <a class="btn btn-light" href="login.php">Login</a>
            </div>

        <?php } ?>
    </section>

    <h2 class="section-title">How SkillSync Works</h2>

    <div class="cards">

        <div class="card feature-card">
            <div class="feature-icon">🎓</div>
            <h3>Share Your Skills</h3>
            <p>
                Add the skills you can teach and help
                other students improve their knowledge.
            </p>
        </div>

        <div class="card feature-card">
            <div class="feature-icon">📚</div>
            <h3>Set Learning Goals</h3>
            <p>
                Choose the skills you want to learn
                and set your learning priorities.
            </p>
        </div>

        <div class="card feature-card">
            <div class="feature-icon">🤝</div>
            <h3>Find Student Matches</h3>
            <p>
                Discover students with matching skills
                and send session requests.
            </p>
        </div>

        <div class="card feature-card">
            <div class="feature-icon">⭐</div>
            <h3>Review and Improve</h3>
            <p>
                Complete learning sessions, share feedback
                and help build a supportive community.
            </p>
        </div>

    </div>

    <section class="card">
        <h2>Why Choose SkillSync?</h2>

        <p>✓ Learn from fellow students.</p>
        <p>✓ Share your knowledge and experience.</p>
        <p>✓ Schedule learning sessions.</p>
        <p>✓ Track your learning journey.</p>
        <p>✓ Build a collaborative learning community.</p>
    </section>

</div>

<footer class="home-footer">
    <p>SkillSync — Student Skill Exchange & Peer Learning Platform</p>
    <p>Learn a skill. Share a skill. Grow together.</p>
</footer>

</body>
</html>