<?php

session_start();

require_once "config/database.php";

/* =========================================================
   LOGIN & ROLE CHECK
========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit;
}


/* =========================================================
   GET STUDENT INFORMATION
========================================================= */

$student_id = (int) $_SESSION["user_id"];

$student_sql = "
    SELECT
        full_name,
        department,
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


/* =========================================================
   STUDENT INFORMATION
========================================================= */

$full_name = $student["full_name"];
$department = $student["department"];
$academic_year = $student["academic_year"];
$semester = $student["semester"];

$first_name = explode(" ", trim($full_name))[0];


/* =========================================================
   YEAR / SEMESTER LABELS
========================================================= */

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


/* =========================================================
   GET STUDENT COURSES
========================================================= */

$courses = [];

if (
    $department !== null &&
    $academic_year !== null &&
    $semester !== null
) {

    $course_sql = "
        SELECT DISTINCT
            courses.id AS course_id,
            courses.course_code,
            courses.course_name,
            courses.department,
            courses.credit

        FROM course_offerings

        INNER JOIN courses
            ON course_offerings.course_id = courses.id

        WHERE course_offerings.year = ?
        AND course_offerings.semester = ?
        AND courses.department = ?

        ORDER BY courses.course_code ASC
    ";

    $course_stmt = $conn->prepare($course_sql);

    if ($course_stmt) {

        $course_stmt->bind_param(
            "iis",
            $academic_year,
            $semester,
            $department
        );

        $course_stmt->execute();

        $course_result = $course_stmt->get_result();

        while ($row = $course_result->fetch_assoc()) {
            $courses[] = $row;
        }

        $course_stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Materials - RUET Connect</title>

    <link rel="stylesheet" href="css/style.css">

    <style>

        /* =====================================================
           PAGE BASE
        ===================================================== */

        html,
        body {
            margin: 0;
            padding: 0;
            width: 100%;
            min-height: 100%;
        }

        body {
            background: #f7f5f3;
            color: #252525;
            font-family: Arial, Helvetica, sans-serif;
        }


        /* =====================================================
           HEADER
        ===================================================== */

        .dashboard-header {
            width: calc(100% - 136px);

            min-height: 78px;

            margin: 8px auto 0;

            padding: 0 44px;

            background: #ffffff;

            display: flex;

            align-items: center;

            justify-content: space-between;

            border: 1px solid #eee7e9;

            box-shadow:
                0 4px 16px rgba(61, 28, 36, 0.05);

            border-radius: 0;

            box-sizing: border-box;
        }


        .dashboard-brand h1 {
            margin: 0;

            color: #000000;

            font-size: 28px;

            font-weight: 500;

            letter-spacing: 0.2px;
        }


        /* =====================================================
           USER AREA
        ===================================================== */

        .dashboard-user {
            display: flex;

            align-items: center;

            gap: 10px;

            background: transparent;

            margin: 0;

            padding: 0;
        }


        .dashboard-user .user-name {
            display: inline-flex;

            align-items: center;

            min-width: auto;

            height: auto;

            padding: 0;

            background: transparent;

            border: none;

            border-radius: 0;

            color: #3D1C24;

            font-size: 16px;

            font-weight: 400;

            white-space: nowrap;
        }


        /* =====================================================
           LOGOUT
        ===================================================== */

        .dashboard-user .dashboard-logout {
            display: inline-flex;

            align-items: center;

            justify-content: center;

            height: 38px;

            padding: 0 18px;

            background: #3D1C24;

            color: #ffffff;

            border: 1px solid #3D1C24;

            border-radius: 6px;

            font-size: 13px;

            font-weight: 500;

            text-decoration: none;

            transition:
                background-color 0.2s ease,
                border-color 0.2s ease,
                transform 0.2s ease;
        }


        .dashboard-user .dashboard-logout:hover {
            background: #DAA520;

            border-color: #DAA520;

            color: #ffffff;

            transform: translateY(-1px);
        }


        /* =====================================================
           NAVIGATION
        ===================================================== */

        .dashboard-nav {
            width: fit-content;

            min-height: 53px;

            margin: 23px auto 0;

            padding: 0;

            background: #3D1C24;

            display: flex;

            align-items: stretch;

            border-radius: 0;

            box-shadow:
                0 3px 10px rgba(61, 28, 36, 0.06);
        }


        .dashboard-nav a {
            position: relative;

            display: flex;

            align-items: center;

            justify-content: center;

            min-height: 53px;

            padding: 0 17px;

            color: #ffffff;

            font-size: 14px;

            font-weight: 500;

            text-decoration: none;

            transition:
                background-color 0.2s ease,
                color 0.2s ease;
        }


        .dashboard-nav a:hover {
            background: #512632;

            color: #DAA520;
        }


        .dashboard-nav a.active {
            background: transparent;

            color: #ffffff;
        }


        .dashboard-nav a.active::after {
            content: "";

            position: absolute;

            left: 14px;

            right: 14px;

            bottom: 0;

            height: 3px;

            background: #DAA520;
        }


        /* =====================================================
           MAIN PAGE
        ===================================================== */

        .materials-page {
            width: 100%;

            max-width: 1345px;

            margin: 0 auto;

            padding: 68px 0 60px;

            box-sizing: border-box;
        }


        /* =====================================================
           PAGE HEADING
        ===================================================== */

        .materials-heading {
            margin-bottom: 31px;
        }


        .materials-heading h2 {
            margin: 0 0 8px;

            color: #3D1C24;

            font-size: 30px;

            font-weight: 600;

            line-height: 1.25;
        }


        .materials-heading p {
            margin: 0;

            color: #666;

            font-size: 15px;

            line-height: 1.5;
        }


        /* =====================================================
           ACADEMIC INFORMATION
        ===================================================== */

        .academic-info {
            display: grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 22px;

            margin-bottom: 38px;
        }


        .academic-box {
            min-height: 94px;

            padding: 20px 24px;

            background: #ffffff;

            border: 1px solid #e9e3e5;

            border-top: 4px solid #DAA520;

            border-radius: 10px;

            box-shadow:
                0 5px 18px rgba(61, 28, 36, 0.06);

            display: flex;

            flex-direction: column;

            justify-content: center;
        }


        .academic-box span {
            display: block;

            margin-bottom: 7px;

            color: #777;

            font-size: 12px;

            font-weight: 500;

            letter-spacing: 0.6px;

            text-transform: uppercase;
        }


        .academic-box strong {
            color: #3D1C24;

            font-size: 18px;

            font-weight: 600;
        }


        /* =====================================================
           COURSE LIST
        ===================================================== */

        .course-list {
            display: flex;

            flex-direction: column;

            gap: 16px;
        }


        /* =====================================================
           COURSE ROW
        ===================================================== */

        .course-row {
            width: 100%;

            min-height: 112px;

            padding: 20px 24px;

            background: #ffffff;

            border: 1px solid #e9e3e5;

            border-left: 5px solid #DAA520;

            border-radius: 10px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 25px;

            box-shadow:
                0 5px 18px rgba(61, 28, 36, 0.06);

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease,
                border-color 0.2s ease;

            box-sizing: border-box;
        }


        .course-row:hover {
            transform: translateY(-2px);

            border-color: #d8c5c9;

            box-shadow:
                0 9px 25px rgba(61, 28, 36, 0.10);
        }


        /* =====================================================
           COURSE INFORMATION
        ===================================================== */

        .course-info {
            min-width: 0;
        }


        .course-code {
            margin-bottom: 5px;

            color: #DAA520;

            font-size: 14px;

            font-weight: 700;

            letter-spacing: 0.6px;
        }


        .course-name {
            margin: 0;

            color: #3D1C24;

            font-size: 19px;

            font-weight: 600;

            line-height: 1.3;
        }


        .course-credit {
            margin-top: 6px;

            color: #888;

            font-size: 13px;
        }


        /* =====================================================
           VIEW MATERIALS BUTTON
        ===================================================== */

        .view-materials-btn {
            display: inline-flex;

            align-items: center;

            justify-content: center;

            min-width: 145px;

            height: 42px;

            padding: 0 18px;

            background: #3D1C24;

            color: #ffffff;

            border: 1px solid #3D1C24;

            border-radius: 6px;

            font-size: 13px;

            font-weight: 500;

            text-decoration: none;

            transition:
                background-color 0.2s ease,
                border-color 0.2s ease,
                transform 0.2s ease;
        }


        .view-materials-btn:hover {
            background: #DAA520;

            border-color: #DAA520;

            color: #ffffff;

            transform: translateY(-1px);
        }


        /* =====================================================
           EMPTY MESSAGE
        ===================================================== */

        .empty-message {
            padding: 50px 30px;

            background: #ffffff;

            border: 1px solid #e9e3e5;

            border-top: 4px solid #DAA520;

            border-radius: 10px;

            text-align: center;

            box-shadow:
                0 6px 22px rgba(61, 28, 36, 0.06);
        }


        .empty-message h3 {
            margin: 0 0 10px;

            color: #3D1C24;

            font-size: 21px;

            font-weight: 600;
        }


        .empty-message p {
            margin: 0;

            color: #777;

            line-height: 1.6;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1450px) {

            .dashboard-header {
                width: calc(100% - 80px);
            }

            .materials-page {
                max-width: none;

                margin-left: 50px;

                margin-right: 50px;
            }

        }


        @media (max-width: 900px) {

            .dashboard-header {
                width: calc(100% - 40px);

                padding: 0 25px;
            }


            .materials-page {
                margin-left: 30px;

                margin-right: 30px;

                padding-top: 55px;
            }


            .academic-info {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

        }


        @media (max-width: 700px) {

            .dashboard-header {
                width: calc(100% - 30px);

                min-height: 72px;

                padding: 0 18px;

                flex-direction: column;

                align-items: flex-start;

                justify-content: center;

                gap: 10px;
            }


            .dashboard-user {
                width: 100%;

                justify-content: flex-end;
            }


            .dashboard-nav {
                width: 100%;

                margin-top: 15px;

                overflow-x: auto;

                justify-content: flex-start;
            }


            .dashboard-nav a {
                flex-shrink: 0;

                padding: 0 15px;
            }


            .materials-page {
                margin-left: 18px;

                margin-right: 18px;

                padding-top: 45px;
            }


            .academic-info {
                grid-template-columns: 1fr;
            }


            .course-row {
                align-items: flex-start;

                flex-direction: column;
            }


            .view-materials-btn {
                width: 100%;
            }

        }


        @media (max-width: 480px) {

            .materials-heading h2 {
                font-size: 25px;
            }


            .course-row {
                padding: 19px;
            }

        }

    </style>

</head>


<body>


    <!-- =====================================================
         HEADER
    ===================================================== -->

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


    <!-- =====================================================
         NAVIGATION
    ===================================================== -->

    <nav class="dashboard-nav">

        <a href="student.php">
            Dashboard
        </a>


        <a href="student_courses.php">
            My Courses
        </a>


        <a
            href="student_materials.php"
            class="active"
        >
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


    <!-- =====================================================
         MAIN CONTENT
    ===================================================== -->

    <main class="materials-page">


        <!-- PAGE HEADING -->

        <div class="materials-heading">

            <h2>
                Course Materials
            </h2>

            <p>
                Access learning materials for your current semester courses.
            </p>

        </div>


        <?php if (
            $department === null ||
            $academic_year === null ||
            $semester === null
        ): ?>


            <div class="empty-message">

                <h3>
                    Academic Information Not Available
                </h3>

                <p>
                    Your department, academic year or semester has not been
                    assigned yet. Please contact the administrator.
                </p>

            </div>


        <?php else: ?>


            <!-- =================================================
                 ACADEMIC INFORMATION
            ================================================== -->

            <div class="academic-info">


                <div class="academic-box">

                    <span>
                        Department
                    </span>

                    <strong>
                        <?php echo htmlspecialchars($department); ?>
                    </strong>

                </div>


                <div class="academic-box">

                    <span>
                        Academic Year
                    </span>

                    <strong>
                        <?php echo htmlspecialchars(
                            yearLabel($academic_year)
                        ); ?>
                    </strong>

                </div>


                <div class="academic-box">

                    <span>
                        Semester
                    </span>

                    <strong>
                        <?php echo htmlspecialchars(
                            semesterLabel($semester)
                        ); ?>
                    </strong>

                </div>


            </div>


            <!-- =================================================
                 COURSE LIST
            ================================================== -->

            <?php if (empty($courses)): ?>


                <div class="empty-message">

                    <h3>
                        No Courses Available
                    </h3>

                    <p>
                        No courses have been assigned to your current
                        academic year and semester yet.
                    </p>

                </div>


            <?php else: ?>


                <div class="course-list">


                    <?php foreach ($courses as $course): ?>


                        <div class="course-row">


                            <div class="course-info">


                                <div class="course-code">

                                    <?php echo htmlspecialchars(
                                        $course["course_code"]
                                    ); ?>

                                </div>


                                <h3 class="course-name">

                                    <?php echo htmlspecialchars(
                                        $course["course_name"]
                                    ); ?>

                                </h3>


                                <div class="course-credit">

                                    <?php echo htmlspecialchars(
                                        $course["credit"]
                                    ); ?>

                                    Credit

                                </div>


                            </div>


                            <a
                                href="student_course_materials.php?course_id=<?php echo (int) $course["course_id"]; ?>"
                                class="view-materials-btn"
                            >
                                View Materials
                            </a>


                        </div>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        <?php endif; ?>


    </main>


</body>

</html>
