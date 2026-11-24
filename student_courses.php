
<?php

session_start();

require_once "config/database.php";

/* =========================
   LOGIN & ROLE CHECK
========================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit;
}

/* =========================
   STUDENT INFORMATION
========================= */

$student_id = (int) $_SESSION["user_id"];

$student_sql = "
    SELECT
        full_name,
        academic_year,
        semester
    FROM users
    WHERE id = ?
    AND role = 'student'
";

$student_stmt = $conn->prepare($student_sql);

if (!$student_stmt) {
    die("Something went wrong. Please try again.");
}

$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();

$student_result = $student_stmt->get_result();

if ($student_result->num_rows !== 1) {
    $student_stmt->close();
    session_unset();
    session_destroy();

    header("Location: login.php");
    exit;
}

$student = $student_result->fetch_assoc();

$student_stmt->close();

/* =========================
   STUDENT ACADEMIC INFO
========================= */

$academic_year = $student["academic_year"];
$semester = $student["semester"];

$first_name = explode(" ", trim($student["full_name"]))[0];

/* =========================
   GET STUDENT COURSES
========================= */

$courses = [];

if ($academic_year !== null && $semester !== null) {

    $course_sql = "
        SELECT
            courses.id AS course_id,
            courses.course_code,
            courses.course_name,
            courses.course_description,
            courses.department,
            courses.credit,
            course_offerings.year,
            course_offerings.semester,
            course_offerings.academic_session,

            GROUP_CONCAT(
                DISTINCT users.full_name
                ORDER BY users.full_name
                SEPARATOR ', '
            ) AS teacher_names

        FROM course_offerings

        INNER JOIN courses
            ON course_offerings.course_id = courses.id

        INNER JOIN users
            ON course_offerings.teacher_id = users.id

        WHERE course_offerings.year = ?
        AND course_offerings.semester = ?

        GROUP BY
            courses.id,
            courses.course_code,
            courses.course_name,
            courses.course_description,
            courses.department,
            courses.credit,
            course_offerings.year,
            course_offerings.semester,
            course_offerings.academic_session

        ORDER BY courses.course_code ASC
    ";

    $course_stmt = $conn->prepare($course_sql);

    if ($course_stmt) {

        $course_stmt->bind_param(
            "ii",
            $academic_year,
            $semester
        );

        $course_stmt->execute();

        $course_result = $course_stmt->get_result();

        while ($row = $course_result->fetch_assoc()) {
            $courses[] = $row;
        }

        $course_stmt->close();
    }
}

/* =========================
   YEAR / SEMESTER DISPLAY
========================= */

function yearLabel($year)
{
    $labels = [
        1 => "1st Year",
        2 => "2nd Year",
        3 => "3rd Year",
        4 => "4th Year"
    ];

    return $labels[$year] ?? $year . "th Year";
}

function semesterLabel($semester)
{
    $labels = [
        1 => "1st Semester",
        2 => "2nd Semester"
    ];

    return $labels[$semester] ?? $semester . "th Semester";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Courses - RUET Connect</title>

    <link rel="stylesheet" href="css/style.css">

    <style>

        /* =========================
           PAGE LAYOUT
        ========================= */

        .courses-page {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 45px 50px 60px;
        }

        /* =========================
           PAGE HEADING
        ========================= */

        .courses-heading {
            margin-bottom: 30px;
        }

        .courses-heading h2 {
            margin: 0 0 8px;
            color: #3D1C24;
            font-size: 30px;
            font-weight: 600;
        }

        .courses-heading p {
            margin: 0;
            color: #666;
            font-size: 15px;
        }

        /* =========================
           ACADEMIC INFORMATION
        ========================= */

        .academic-info {
            display: grid;
            grid-template-columns: repeat(3, minmax(200px, 1fr));
            gap: 22px;
            margin-bottom: 35px;
        }

        .academic-box {
            background: #ffffff;
            border: 1px solid #e9e3e5;
            border-top: 4px solid #DAA520;
            padding: 20px 24px;
            border-radius: 10px;
            box-shadow: 0 5px 18px rgba(61, 28, 36, 0.06);
        }

        .academic-box span {
            display: block;
            font-size: 12px;
            color: #777;
            margin-bottom: 7px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 500;
        }

        .academic-box strong {
            color: #3D1C24;
            font-size: 19px;
            font-weight: 600;
        }

        /* =========================
           COURSE GRID
        ========================= */

        .course-grid {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(320px, 1fr)
            );
            gap: 25px;
        }

        /* =========================
           COURSE CARD
        ========================= */

        .course-card {
            background: #ffffff;
            border: 1px solid #e9e3e5;
            border-radius: 12px;
            padding: 28px;
            position: relative;
            box-shadow: 0 6px 22px rgba(61, 28, 36, 0.07);
            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease,
                border-color 0.2s ease;
        }

        .course-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: #DAA520;
            border-radius: 12px 12px 0 0;
        }

        .course-card:hover {
            transform: translateY(-4px);
            border-color: #d8c5c9;
            box-shadow: 0 12px 30px rgba(61, 28, 36, 0.11);
        }

        /* =========================
           COURSE CONTENT
        ========================= */

        .course-code {
            color: #DAA520;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.7px;
            margin-bottom: 8px;
        }

        .course-card h3 {
            margin: 0 0 12px;
            color: #3D1C24;
            font-size: 21px;
            font-weight: 600;
        }

        .course-description {
            color: #666;
            font-size: 14px;
            line-height: 1.65;
            margin-bottom: 22px;
        }

        /* =========================
           COURSE DETAILS
        ========================= */

        .course-details {
            border-top: 1px solid #eee7e9;
            padding-top: 14px;
        }

        .course-detail {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            padding: 7px 0;
            font-size: 13px;
        }

        .course-detail span:first-child {
            color: #888;
        }

        .course-detail span:last-child {
            color: #3D1C24;
            font-weight: 600;
            text-align: right;
        }

        /* =========================
           EMPTY MESSAGE
        ========================= */

        .empty-message {
            background: #ffffff;
            border: 1px solid #e9e3e5;
            border-top: 4px solid #DAA520;
            border-radius: 10px;
            padding: 50px 30px;
            text-align: center;
            box-shadow: 0 6px 22px rgba(61, 28, 36, 0.06);
        }

        .empty-message h3 {
            color: #3D1C24;
            margin: 0 0 10px;
            font-size: 21px;
            font-weight: 600;
        }

        .empty-message p {
            color: #777;
            margin: 0;
            line-height: 1.6;
        }

        /* =========================
           HEADER
        ========================= */

        .dashboard-header {
            background: #ffffff !important;
            border-bottom: 1px solid #eee7e9;
            box-shadow: 0 3px 15px rgba(61, 28, 36, 0.05);
        }

        .dashboard-brand h1 {
            color: #000000 !important;
            font-weight: 500 !important;
        }

        .dashboard-user {
            color: #3D1C24;
        }

        .user-name {
            color: #3D1C24 !important;
        }

        .dashboard-logout {
            background: #3D1C24 !important;
            color: #ffffff !important;
            border: 1px solid #3D1C24;
            padding: 9px 18px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .dashboard-logout:hover {
            background: #DAA520 !important;
            border-color: #DAA520;
            color: #3D1C24 !important;
        }

        /* =========================
           IMPROVED NAVIGATION
        ========================= */

        .dashboard-nav {
            width: fit-content;
            max-width: calc(100% - 40px);
            margin: 18px auto 0;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 4px;
            background: #3D1C24 !important;
            border-radius: 10px;
            box-shadow: 0 5px 18px rgba(61, 28, 36, 0.12);
        }

        .dashboard-nav a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 11px 18px;
            border-radius: 7px;
            color: #ffffff !important;
            text-decoration: none;
            white-space: nowrap;
            font-size: 14px;
            font-weight: 600;
            transition:
                background 0.2s ease,
                color 0.2s ease,
                transform 0.2s ease;
        }

        .dashboard-nav a:hover {
            background: #DAA520 !important;
            color: #3D1C24 !important;
            transform: translateY(-1px);
        }

        .dashboard-nav a.active {
            background: #DAA520 !important;
            color: #3D1C24 !important;
        }

        /* =========================
           BODY BACKGROUND
        ========================= */

        body {
            background: #f7f5f5 !important;
        }

        /* =========================
           RESPONSIVE DESIGN
        ========================= */

        @media (max-width: 900px) {

            .courses-page {
                padding: 35px 30px 50px;
            }

            .academic-info {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 750px) {

            .dashboard-nav {
                width: calc(100% - 30px);
                max-width: none;
                margin-top: 15px;
                gap: 3px;
            }

            .dashboard-nav a {
                flex: 1 1 auto;
                padding: 10px 12px;
                font-size: 13px;
            }

        }

        @media (max-width: 650px) {

            .courses-page {
                padding: 28px 18px 40px;
            }

            .academic-info {
                grid-template-columns: 1fr;
            }

            .course-grid {
                grid-template-columns: 1fr;
            }

            .course-card {
                padding: 23px;
            }

            .course-detail {
                flex-direction: column;
                gap: 3px;
            }

            .course-detail span:last-child {
                text-align: left;
            }

        }

        @media (max-width: 500px) {

            .dashboard-nav {
                width: calc(100% - 20px);
                padding: 4px;
            }

            .dashboard-nav a {
                flex: 1 1 45%;
                min-height: 40px;
                padding: 9px 8px;
                font-size: 12px;
            }

        }

    </style>

</head>

<body>

    <!-- =========================
         HEADER
    ========================= -->

    <header class="dashboard-header">

        <div class="dashboard-brand">
            <h1>RUET Connect</h1>
        </div>

        <div class="dashboard-user">

            <span class="user-name">
                Welcome, <?php echo htmlspecialchars($first_name); ?>
            </span>

            <a
                href="logout.php"
                class="dashboard-logout"
            >
                Logout
            </a>

        </div>

    </header>

    <!-- =========================
         NAVIGATION
    ========================= -->

    <nav class="dashboard-nav">

        <a href="student.php">
            Dashboard
        </a>

        <a
            href="student_courses.php"
            class="active"
        >
            My Courses
        </a>

        <a href="student_materials.php">
            Materials
        </a>

        <a href="marks.html">
            Marks
        </a>

        <a href="attendance.html">
            Attendance
        </a>

        <a href="routine.html">
            Routine
        </a>

    </nav>

    <!-- =========================
         MAIN CONTENT
    ========================= -->

    <main class="courses-page">

        <div class="courses-heading">

            <h2>My Courses</h2>

            <p>
                Courses assigned to your current academic year and semester.
            </p>

        </div>

        <?php if ($academic_year === null || $semester === null): ?>

            <div class="empty-message">

                <h3>Academic Information Not Available</h3>

                <p>
                    Your academic year and semester have not been assigned yet.
                    Please contact the administrator.
                </p>

            </div>

        <?php else: ?>

            <!-- =========================
                 ACADEMIC INFORMATION
            ========================= -->

            <div class="academic-info">

                <div class="academic-box">

                    <span>Academic Year</span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            yearLabel($academic_year)
                        );
                        ?>
                    </strong>

                </div>

                <div class="academic-box">

                    <span>Semester</span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            semesterLabel($semester)
                        );
                        ?>
                    </strong>

                </div>

                <div class="academic-box">

                    <span>Total Courses</span>

                    <strong>
                        <?php echo count($courses); ?>
                    </strong>

                </div>

            </div>

            <!-- =========================
                 COURSE LIST
            ========================= -->

            <?php if (empty($courses)): ?>

                <div class="empty-message">

                    <h3>No Courses Assigned</h3>

                    <p>
                        No courses have been assigned to
                        <?php
                        echo htmlspecialchars(
                            yearLabel($academic_year)
                        );
                        ?>,
                        <?php
                        echo htmlspecialchars(
                            semesterLabel($semester)
                        );
                        ?>
                        yet.
                    </p>

                </div>

            <?php else: ?>

                <div class="course-grid">

                    <?php foreach ($courses as $course): ?>

                        <article class="course-card">

                            <div class="course-code">
                                <?php
                                echo htmlspecialchars(
                                    $course["course_code"]
                                );
                                ?>
                            </div>

                            <h3>
                                <?php
                                echo htmlspecialchars(
                                    $course["course_name"]
                                );
                                ?>
                            </h3>

                            <?php if (
                                !empty($course["course_description"])
                            ): ?>

                                <div class="course-description">

                                    <?php
                                    echo htmlspecialchars(
                                        $course["course_description"]
                                    );
                                    ?>

                                </div>

                            <?php endif; ?>

                            <div class="course-details">

                                <div class="course-detail">

                                    <span>Department</span>

                                    <span>
                                        <?php
                                        echo htmlspecialchars(
                                            $course["department"]
                                        );
                                        ?>
                                    </span>

                                </div>

                                <div class="course-detail">

                                    <span>Credit</span>

                                    <span>
                                        <?php
                                        echo htmlspecialchars(
                                            $course["credit"]
                                        );
                                        ?>
                                    </span>

                                </div>

                                <div class="course-detail">

                                    <span>Instructor(s)</span>

                                    <span>
                                        <?php
                                        echo htmlspecialchars(
                                            $course["teacher_names"]
                                            ?? "Not Assigned"
                                        );
                                        ?>
                                    </span>

                                </div>

                                <div class="course-detail">

                                    <span>Academic Session</span>

                                    <span>
                                        <?php
                                        echo htmlspecialchars(
                                            $course["academic_session"]
                                        );
                                        ?>
                                    </span>

                                </div>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        <?php endif; ?>

    </main>

</body>

</html>
