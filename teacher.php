
<?php

session_start();

// Check whether the user is logged in

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Check whether the logged-in user is a teacher

if ($_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit;
}

// Get the first name of the logged-in teacher

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

    <title>Teacher Dashboard</title>

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
                Teacher Dashboard
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

        <a href="teacher.php">
            Dashboard
        </a>

        <a href="teacher_courses.php">
            Courses
        </a>

        <a href="teacher_materials.php">
            Materials
        </a>

        <a href="teacher_marks.php">
            Marks
        </a>

        <a href="teacher_announcements.php">
            Announcements
        </a>

        <a href="teacher_attendance.php">
            Attendance
        </a>

        <a href="#">
            Routine
        </a>

        <a href="teacher_appointments.php">
            Appointments
        </a>

    </nav>

    <main class="dashboard-main">

        <div class="welcome-section">

            <h2>

                Welcome back,
                <?php echo htmlspecialchars($first_name); ?>!

            </h2>

            <p>

                Here's an overview of your teaching activities.

            </p>

        </div>

        <section class="dashboard-grid">

            <!-- Today's Classes -->

            <div class="dashboard-card">

                <h3>
                    Today's Classes
                </h3>

                <p>
                    View your classes scheduled for today.
                </p>

                <a href="teacher_routine.php">
                    View Classes →
                </a>

            </div>

            <!-- My Courses -->

            <div class="dashboard-card">

                <h3>
                    My Courses
                </h3>

                <p>
                    View the courses you are currently teaching.
                </p>

                <a href="teacher_courses.php">
                    View Courses →
                </a>

            </div>

            <!-- Course Materials -->

            <div class="dashboard-card">

                <h3>
                    Course Materials
                </h3>

                <p>
                    Upload and manage lecture materials.
                </p>

                <a href="teacher_materials.php">
                    Manage Materials →
                </a>

            </div>

            <!-- Marks -->

            <div class="dashboard-card">

                <h3>
                    Marks
                </h3>

                <p>
                    Enter and manage student CT and assignment marks.
                </p>

                <a href="teacher_marks.php">
                    Manage Marks →
                </a>

            </div>

            <!-- Attendance -->

            <div class="dashboard-card">

                <h3>
                    Attendance
                </h3>

                <p>
                    Record and manage student attendance.
                </p>

                <a href="teacher_attendance.php">
                    Manage Attendance →
                </a>

            </div>

            <!-- Appointments -->

            <div class="dashboard-card">

                <h3>
                    Appointments
                </h3>

                <p>
                    View and manage student appointment requests.
                </p>

                <a href="teacher_appointments.php">
                    View Appointments →
                </a>

            </div>

            <!-- Announcements -->

            <div class="dashboard-card">

                <h3>
                    Announcements
                </h3>

                <p>
                    View the latest announcements from the administration.
                </p>

                <a href="teacher_announcements.php">
                    View Announcements →
                </a>

            </div>

        </section>

    </main>

</body>

</html>
