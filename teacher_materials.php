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

if ($_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit;
}


/* =========================================================
   TEACHER INFORMATION
========================================================= */

$teacher_id = (int) $_SESSION["user_id"];

$teacher_sql = "
    SELECT
        full_name,
        department
    FROM users
    WHERE id = ?
    AND role = 'teacher'
";

$teacher_stmt = $conn->prepare($teacher_sql);

if (!$teacher_stmt) {
    die("Something went wrong. Please try again.");
}

$teacher_stmt->bind_param("i", $teacher_id);

$teacher_stmt->execute();

$teacher_result = $teacher_stmt->get_result();

if ($teacher_result->num_rows !== 1) {

    $teacher_stmt->close();

    session_unset();
    session_destroy();

    header("Location: login.php");
    exit;
}

$teacher = $teacher_result->fetch_assoc();

$teacher_stmt->close();


$full_name = $teacher["full_name"];

$first_name = explode(
    " ",
    trim($full_name)
)[0];


/* =========================================================
   GET COURSES ASSIGNED TO THIS TEACHER
========================================================= */

$courses = [];

$course_sql = "
    SELECT
        course_offerings.id AS offering_id,
        course_offerings.year,
        course_offerings.semester,
        course_offerings.academic_session,

        courses.id AS course_id,
        courses.course_code,
        courses.course_name,
        courses.course_description,
        courses.department,
        courses.credit

    FROM course_offerings

    INNER JOIN courses
        ON course_offerings.course_id = courses.id

    WHERE course_offerings.teacher_id = ?

    ORDER BY
        course_offerings.year ASC,
        course_offerings.semester ASC,
        courses.course_code ASC
";

$course_stmt = $conn->prepare($course_sql);

if ($course_stmt) {

    $course_stmt->bind_param(
        "i",
        $teacher_id
    );

    $course_stmt->execute();

    $course_result = $course_stmt->get_result();

    while ($row = $course_result->fetch_assoc()) {
        $courses[] = $row;
    }

    $course_stmt->close();
}


/* =========================================================
   COURSE COUNT
========================================================= */

$course_count = count($courses);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Course Materials - RUET Connect
    </title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >


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

            font-family:
                Arial,
                Helvetica,
                sans-serif;

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
                0 4px 16px
                rgba(61, 28, 36, 0.05);

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

            margin: 0;

            padding: 0;

            background: transparent;

        }


        .dashboard-user .user-name {

            display: inline-flex;

            align-items: center;

            min-width: auto;

            height: auto;

            padding: 0;

            background: transparent;

            border: none;

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

            box-shadow:
                0 3px 10px
                rgba(61, 28, 36, 0.06);

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
           MAIN CONTENT
        ===================================================== */

        .teacher-materials-page {

            width: 100%;

            max-width: 1345px;

            margin: 0 auto;

            padding: 68px 0 60px;

            box-sizing: border-box;

        }


        /* =====================================================
           PAGE HEADING
        ===================================================== */

        .page-heading {

            margin-bottom: 32px;

        }


        .page-heading h2 {

            margin: 0 0 8px;

            color: #3D1C24;

            font-size: 30px;

            font-weight: 600;

            line-height: 1.25;

        }


        .page-heading p {

            margin: 0;

            color: #666;

            font-size: 15px;

            line-height: 1.5;

        }


        /* =====================================================
           SUMMARY BOXES
        ===================================================== */

        .summary-grid {

            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 22px;

            margin-bottom: 38px;

        }


        .summary-box {

            min-height: 94px;

            padding: 20px 24px;

            background: #ffffff;

            border: 1px solid #e9e3e5;

            border-top: 4px solid #DAA520;

            border-radius: 10px;

            box-shadow:
                0 5px 18px
                rgba(61, 28, 36, 0.06);

            display: flex;

            flex-direction: column;

            justify-content: center;

        }


        .summary-box span {

            margin-bottom: 7px;

            color: #777;

            font-size: 12px;

            font-weight: 500;

            letter-spacing: 0.6px;

            text-transform: uppercase;

        }


        .summary-box strong {

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
           COURSE CARD
        ===================================================== */

        .course-card {

            width: 100%;

            min-height: 118px;

            padding: 21px 24px;

            background: #ffffff;

            border: 1px solid #e9e3e5;

            border-left: 5px solid #DAA520;

            border-radius: 10px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 25px;

            box-shadow:
                0 5px 18px
                rgba(61, 28, 36, 0.06);

            box-sizing: border-box;

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease,
                border-color 0.2s ease;

        }


        .course-card:hover {

            transform: translateY(-2px);

            border-color: #d8c5c9;

            box-shadow:
                0 9px 25px
                rgba(61, 28, 36, 0.10);

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

            margin: 0 0 6px;

            color: #3D1C24;

            font-size: 19px;

            font-weight: 600;

            line-height: 1.3;

        }


        .course-details {

            color: #777;

            font-size: 13px;

        }


        /* =====================================================
           MANAGE BUTTON
        ===================================================== */

        .manage-btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            min-width: 150px;

            height: 42px;

            padding: 0 18px;

            background: #3D1C24;

            color: #ffffff;

            border: 1px solid #3D1C24;

            border-radius: 6px;

            font-size: 13px;

            font-weight: 500;

            text-decoration: none;

            white-space: nowrap;

            transition:
                background-color 0.2s ease,
                border-color 0.2s ease,
                transform 0.2s ease;

        }


        .manage-btn:hover {

            background: #DAA520;

            border-color: #DAA520;

            color: #ffffff;

            transform: translateY(-1px);

        }


        /* =====================================================
           EMPTY STATE
        ===================================================== */

        .empty-message {

            padding: 55px 30px;

            background: #ffffff;

            border: 1px solid #e9e3e5;

            border-top: 4px solid #DAA520;

            border-radius: 10px;

            text-align: center;

            box-shadow:
                0 6px 22px
                rgba(61, 28, 36, 0.06);

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


            .teacher-materials-page {

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


            .teacher-materials-page {

                margin-left: 30px;

                margin-right: 30px;

                padding-top: 55px;

            }

        }


        @media (max-width: 700px) {

            .dashboard-header {

                width: calc(100% - 30px);

                min-height: 72px;

                padding: 10px 18px;

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


            .teacher-materials-page {

                margin-left: 18px;

                margin-right: 18px;

                padding-top: 45px;

            }


            .summary-grid {

                grid-template-columns: 1fr;

            }


            .course-card {

                align-items: flex-start;

                flex-direction: column;

            }


            .manage-btn {

                width: 100%;

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

            <h1>
                RUET Connect
            </h1>

        </div>


        <div class="dashboard-user">

            <span class="user-name">

                Welcome,
                <?php echo htmlspecialchars($first_name); ?>

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


        <a href="teacher.php">
            Dashboard
        </a>


        <a href="teacher_courses.php">
            My Courses
        </a>


        <a
            href="teacher_materials.php"
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


        <a href="appointments.html">
            Appointments
        </a>


    </nav>


    <!-- =====================================================
         MAIN CONTENT
    ===================================================== -->

    <main class="teacher-materials-page">


        <div class="page-heading">

            <h2>
                Course Materials
            </h2>

            <p>
                Manage learning materials for your assigned courses.
            </p>

        </div>


        <!-- =================================================
             SUMMARY
        ================================================== -->

        <div class="summary-grid">


            <div class="summary-box">

                <span>
                    Teacher
                </span>

                <strong>
                    <?php echo htmlspecialchars($full_name); ?>
                </strong>

            </div>


            <div class="summary-box">

                <span>
                    Total Courses
                </span>

                <strong>
                    <?php echo $course_count; ?>
                </strong>

            </div>


        </div>


        <!-- =================================================
             COURSE LIST
        ================================================== -->

        <?php if (empty($courses)): ?>


            <div class="empty-message">

                <h3>
                    No Courses Assigned
                </h3>

                <p>
                    No courses have been assigned to you yet.
                </p>

            </div>


        <?php else: ?>


            <div class="course-list">


                <?php foreach ($courses as $course): ?>


                    <article class="course-card">


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


                            <div class="course-details">

                                <?php echo htmlspecialchars(
                                    $course["credit"]
                                ); ?>

                                Credit

                                &nbsp; · &nbsp;

                                <?php echo htmlspecialchars(
                                    $course["year"]
                                ); ?>

                                Year

                                &nbsp; · &nbsp;

                                Semester
                                <?php echo htmlspecialchars(
                                    $course["semester"]
                                ); ?>

                                &nbsp; · &nbsp;

                                Session
                                <?php echo htmlspecialchars(
                                    $course["academic_session"]
                                ); ?>

                            </div>


                        </div>


                        <a
                            href="teacher_course_materials.php?course_id=<?php echo (int) $course["course_id"]; ?>"
                            class="manage-btn"
                        >
                            Manage Materials
                        </a>


                    </article>


                <?php endforeach; ?>


            </div>


        <?php endif; ?>


    </main>


</body>

</html>
