
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit;
}

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

    <title>Student Dashboard</title>

    <link
        rel="stylesheet"
        href="css/style.css?v=2"
    >

</head>

<body class="dashboard-page">

    <header class="dashboard-header">

        <div>

            <h1>RUET Connect</h1>

            <p>Student Dashboard</p>

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

        <a href="student_courses.php">
            Courses
        </a>

        <a href="student_materials.php">
            Materials
        </a>

        <a href="student_marks.php">
            Marks
        </a>

        <a href="student_announcements.php">
            Announcements
        </a>

        <a href="student_attendance.php">
            Attendance
        </a>

        <a href="student_routine.php">
            Routine
        </a>

        <a href="appointments.php">
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
                Here's an overview of your academic activities.
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

                <a href="student_routine.php">
                    View Classes →
                </a>

            </div>

            <!-- Upcoming Exams -->

            <div class="dashboard-card">

                <h3>
                    Upcoming Exams
                </h3>

                <p>
                    Check your upcoming examinations and schedules.
                </p>

                <a href="#">
                    View Exams →
                </a>

            </div>

            <!-- Announcements -->

            <div class="dashboard-card">

                <h3>
                    Announcements
                </h3>

                <p>
                    Stay updated with the latest announcements from the administration.
                </p>

                <a href="student_announcements.php">
                    View Announcements →
                </a>

            </div>

            <!-- Course Materials -->

            <div class="dashboard-card">

                <h3>
                    Course Materials
                </h3>

                <p>
                    Access lecture notes, slides and other materials.
                </p>

                <a href="student_materials.php">
                    View Materials →
                </a>

            </div>

            <!-- Marks -->

            <div class="dashboard-card">

                <h3>
                    Marks
                </h3>

                <p>
                    View your CT, assignment and academic marks.
                </p>

                <a href="student_marks.php">
                    View Marks →
                </a>

            </div>

            <!-- Attendance -->

            <div class="dashboard-card">

                <h3>
                    Attendance
                </h3>

                <p>
                    Check your attendance records for each course.
                </p>

                <a href="student_attendance.php">
                    View Attendance →
                </a>

            </div>

        </section>

    </main>

</body>

</html>
