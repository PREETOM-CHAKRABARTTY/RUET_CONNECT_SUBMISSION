<?php

session_start();

require_once "config/database.php";

/*
|--------------------------------------------------------------------------
| Login Check
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Teacher-Only Access
|--------------------------------------------------------------------------
*/

if ($_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Teacher Information
|--------------------------------------------------------------------------
*/

$teacher_id = (int)$_SESSION["user_id"];

$first_name = explode(
    " ",
    trim($_SESSION["full_name"])
)[0];


/*
|--------------------------------------------------------------------------
| Get Courses Assigned to This Teacher
|--------------------------------------------------------------------------
*/

$teacher_courses = [];

$sql = "
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

$stmt = $conn->prepare($sql);

if ($stmt) {

    $stmt->bind_param(
        "i",
        $teacher_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $teacher_courses[] = $row;
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Year Text
|--------------------------------------------------------------------------
*/

$year_text = [
    1 => "1st Year",
    2 => "2nd Year",
    3 => "3rd Year",
    4 => "4th Year"
];

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

    <link
        rel="stylesheet"
        href="css/style.css?v=3"
    >

    <style>

        .teacher-course-page {
            width: 100%;
        }


        /* =========================================================
           Course Groups
        ========================================================== */

        .course-group {
            margin-bottom: 35px;
        }

        .course-group-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 16px;
        }

        .course-group-title {
            color: #3D1C24;
            font-size: 20px;
            font-weight: 600;
        }

        .course-group-semester {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            background-color: #f8f3e4;
            color: #80610d;
            border: 1px solid #dfcf9d;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }


        /* =========================================================
           Course Cards
        ========================================================== */

        .teacher-course-grid {
            display: grid;
            grid-template-columns: repeat(
                3,
                minmax(0, 1fr)
            );
            gap: 20px;
        }

        .teacher-course-card {
            position: relative;
            min-height: 210px;
            padding: 25px;
            background-color: #ffffff;
            border: 1px solid #e5dfdc;
            border-radius: 12px;
            box-shadow:
                0 3px 12px rgba(61, 28, 36, 0.05);

            overflow: hidden;

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease,
                border-color 0.2s ease;
        }

        .teacher-course-card::before {
            content: "";
            position: absolute;

            top: 0;
            left: 0;

            width: 100%;
            height: 3px;

            background-color: #DAA520;
        }

        .teacher-course-card:hover {
            transform: translateY(-4px);

            border-color: #d8c9a5;

            box-shadow:
                0 10px 24px rgba(61, 28, 36, 0.09);
        }


        /* =========================================================
           Course Information
        ========================================================== */

        .teacher-course-code {
            margin-bottom: 8px;

            color: #80610d;

            font-size: 13px;
            font-weight: 700;

            letter-spacing: 0.3px;
        }

        .teacher-course-name {
            margin-bottom: 14px;

            color: #3D1C24;

            font-size: 19px;
            font-weight: 600;

            line-height: 1.35;
        }

        .teacher-course-description {
            min-height: 44px;
            margin-bottom: 20px;

            color: #706865;

            font-size: 13px;
            line-height: 1.55;
        }


        /* =========================================================
           Course Meta
        ========================================================== */

        .teacher-course-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .course-meta-item {
            display: inline-flex;
            align-items: center;

            padding: 6px 9px;

            background-color: #f8f7f6;

            color: #4d4745;

            border: 1px solid #ded8d5;
            border-radius: 6px;

            font-size: 12px;
            font-weight: 600;
        }


        /* =========================================================
           Empty State
        ========================================================== */

        .no-teacher-courses {
            padding: 55px 30px;

            background-color: #ffffff;

            border: 1px solid #e5dfdc;
            border-radius: 12px;

            text-align: center;

            box-shadow:
                0 3px 12px rgba(61, 28, 36, 0.05);
        }

        .no-teacher-courses h3 {
            margin-bottom: 10px;

            color: #3D1C24;

            font-size: 19px;
            font-weight: 600;
        }

        .no-teacher-courses p {
            color: #766d6a;

            font-size: 14px;
            line-height: 1.6;
        }


        /* =========================================================
           Responsive
        ========================================================== */

        @media (max-width: 1050px) {

            .teacher-course-grid {
                grid-template-columns: repeat(
                    2,
                    minmax(0, 1fr)
                );
            }

        }


        @media (max-width: 700px) {

            .teacher-course-grid {
                grid-template-columns: 1fr;
            }

            .course-group-header {
                align-items: flex-start;
                flex-direction: column;
            }

        }

    </style>

</head>


<body class="dashboard-page">


    <!-- =========================================================
         Header
    ========================================================== -->

    <header class="dashboard-header">

        <div>

            <h1>RUET Connect</h1>

            <p>My Courses</p>

        </div>


        <div class="dashboard-user">

            <div class="user-name">

                <?php
                echo htmlspecialchars($first_name);
                ?>

            </div>


            <a
                href="logout.php"
                class="dashboard-logout"
            >
                Logout
            </a>

        </div>

    </header>


    <!-- =========================================================
         Navigation
    ========================================================== -->

    <nav>

        <a href="teacher.php">
            Dashboard
        </a>

        <a href="teacher_courses.php">
            My Courses
        </a>

    </nav>


    <!-- =========================================================
         Main
    ========================================================== -->

    <main
        class="dashboard-main teacher-course-page"
    >


        <section class="welcome-section">

            <h2>
                My Courses
            </h2>

            <p>
                Courses currently assigned to you.
            </p>

        </section>


        <?php if (count($teacher_courses) > 0): ?>


            <?php

            /*
             * Group courses by year and semester
             */

            $grouped_courses = [];

            foreach ($teacher_courses as $course) {

                $year = (int)$course["year"];

                $semester = (int)$course["semester"];

                $group_key =
                    $year . "_" . $semester;

                if (
                    !isset(
                        $grouped_courses[$group_key]
                    )
                ) {

                    $grouped_courses[$group_key] = [
                        "year" => $year,
                        "semester" => $semester,
                        "courses" => []
                    ];

                }

                $grouped_courses[$group_key]["courses"][] =
                    $course;
            }

            ?>


            <?php foreach (
                $grouped_courses
                as $group
            ): ?>


                <section class="course-group">


                    <div class="course-group-header">


                        <h3 class="course-group-title">

                            <?php
                            echo $year_text[
                                $group["year"]
                            ];
                            ?>

                        </h3>


                        <span
                            class="course-group-semester"
                        >

                            <?php
                            echo $group["semester"] === 1
                                ? "1st Semester"
                                : "2nd Semester";
                            ?>

                        </span>


                    </div>


                    <div class="teacher-course-grid">


                        <?php foreach (
                            $group["courses"]
                            as $course
                        ): ?>


                            <article
                                class="teacher-course-card"
                            >


                                <div
                                    class="teacher-course-code"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $course["course_code"]
                                    );
                                    ?>

                                </div>


                                <h3
                                    class="teacher-course-name"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $course["course_name"]
                                    );
                                    ?>

                                </h3>


                                <p
                                    class="teacher-course-description"
                                >

                                    <?php

                                    if (
                                        !empty(
                                            $course[
                                                "course_description"
                                            ]
                                        )
                                    ) {

                                        echo htmlspecialchars(
                                            $course[
                                                "course_description"
                                            ]
                                        );

                                    } else {

                                        echo "No course description available.";

                                    }

                                    ?>

                                </p>


                                <div
                                    class="teacher-course-meta"
                                >


                                    <span
                                        class="course-meta-item"
                                    >

                                        <?php
                                        echo htmlspecialchars(
                                            $course["department"]
                                        );
                                        ?>

                                    </span>


                                    <span
                                        class="course-meta-item"
                                    >

                                        <?php
                                        echo htmlspecialchars(
                                            $course["credit"]
                                        );
                                        ?>

                                        Credits

                                    </span>


                                    <span
                                        class="course-meta-item"
                                    >

                                        Session:

                                        <?php
                                        echo htmlspecialchars(
                                            $course[
                                                "academic_session"
                                            ]
                                        );
                                        ?>

                                    </span>


                                </div>


                            </article>


                        <?php endforeach; ?>


                    </div>


                </section>


            <?php endforeach; ?>


        <?php else: ?>


            <section
                class="no-teacher-courses"
            >

                <h3>
                    No Courses Assigned
                </h3>

                <p>
                    You currently have no courses assigned to you.
                    Please contact the administrator if you believe
                    this is incorrect.
                </p>

            </section>


        <?php endif; ?>


    </main>

</body>

</html>
