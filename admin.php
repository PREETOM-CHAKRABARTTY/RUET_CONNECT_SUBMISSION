
<?php

session_start();

// Check whether the user is logged in

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Check whether the logged-in user is an administrator

if ($_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}

// Get the first name of the logged-in administrator

$first_name = explode(" ", trim($_SESSION["full_name"]))[0];

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Admin Dashboard</title>

    <link
        rel="stylesheet"
        href="css/style.css?v=3"
    >

</head>

<body class="dashboard-page">

    <header class="dashboard-header">

        <div>

            <h1>
                RUET Connect
            </h1>

            <p>
                Admin Dashboard
            </p>

        </div>

        <div class="dashboard-user">

            <div class="user-name">

                <?php echo htmlspecialchars($first_name); ?>

            </div>

            <a
                href="logout.php"
                class="dashboard-logout"
            >
                Logout
            </a>

        </div>

    </header>

    <nav>

        <a href="#">
            Dashboard
        </a>

        <a href="#">
            Users
        </a>

        <a href="courses.php">
            Courses
        </a>

        <a href="admin_announcements.php">
            Announcements
        </a>

        <a href="#">
            Routine
        </a>

        <a href="#">
            Exams
        </a>

    </nav>

    <main class="dashboard-main">

        <div class="welcome-section">

            <h2>

                Welcome back,
                <?php echo htmlspecialchars($first_name); ?>!

            </h2>

            <p>

                Here's an overview of the academic system.

            </p>

        </div>

        <section class="dashboard-grid">

            <!-- User Management -->

            <div class="dashboard-card">

                <h3>
                    User Management
                </h3>

                <p>
                    Manage student and teacher accounts.
                </p>

                <a href="#">
                    Manage Users →
                </a>

            </div>

            <!-- Course Management -->

            <div class="dashboard-card">

                <h3>
                    Course Management
                </h3>

                <p>
                    Add and manage courses and course information.
                </p>

                <a href="courses.php">
                    Manage Courses →
                </a>

            </div>

            <!-- Announcements -->

            <div class="dashboard-card">

                <h3>
                    Announcements
                </h3>

                <p>
                    Create and manage announcements for students and teachers.
                </p>

                <a href="admin_announcements.php">
                    Manage Announcements →
                </a>

            </div>

            <!-- Routine Management -->

            <div class="dashboard-card">

                <h3>
                    Routine Management
                </h3>

                <p>
                    Manage class routines and schedules.
                </p>

                <a href="admin_routine.php">
                    Manage Routine →
                </a>

            </div>

            <!-- Exam Management -->

            <div class="dashboard-card">

                <h3>
                    Exam Management
                </h3>

                <p>
                    Add and manage examination schedules.
                </p>

                <a href="#">
                    Manage Exams →
                </a>

            </div>

            <!-- System Overview -->

            <div class="dashboard-card">

                <h3>
                    System Overview
                </h3>

                <p>
                    View an overview of students, teachers and courses.
                </p>

                <a href="#">
                    View Overview →
                </a>

            </div>

        </section>

    </main>

</body>

</html>
