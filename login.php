<?php

session_start();

require_once "config/database.php";

$error = "";


/* =========================================================
   Handle Login
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get submitted values
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";


    /* =====================================================
       1. Validate input
    ===================================================== */

    if (empty($email) || empty($password)) {

        $error = "Please enter your email and password.";

    } else {


        /* =================================================
           2. Find user by email
        ================================================= */

        $sql = "
            SELECT
                id,
                full_name,
                email,
                password,
                role,
                student_id,
                department
            FROM users
            WHERE email = ?
        ";


        $stmt = $conn->prepare($sql);


        if (!$stmt) {

            $error = "Something went wrong. Please try again.";

        } else {

            $stmt->bind_param("s", $email);

            $stmt->execute();

            $result = $stmt->get_result();


            /* =============================================
               3. Check whether account exists
            ============================================= */

            if ($result->num_rows === 1) {

                $user = $result->fetch_assoc();


                /* =========================================
                   4. Verify password
                ========================================= */

                if (password_verify($password, $user["password"])) {


                    /* =====================================
                       5. Regenerate session ID

                       This helps protect against
                       session fixation attacks.
                    ===================================== */

                    session_regenerate_id(true);


                    /* =====================================
                       6. Store user information in session
                    ===================================== */

                    $_SESSION["user_id"] = $user["id"];

                    $_SESSION["full_name"] = $user["full_name"];

                    $_SESSION["email"] = $user["email"];

                    $_SESSION["role"] = $user["role"];

                    $_SESSION["student_id"] = $user["student_id"];

                    $_SESSION["department"] = $user["department"];


                    /* =====================================
                       7. Redirect according to role
                    ===================================== */

                    if ($user["role"] === "student") {

                        header("Location: student.php");
                        exit;
                    }


                    if ($user["role"] === "teacher") {

                        header("Location: teacher.php");
                        exit;
                    }


                    if ($user["role"] === "admin") {

                        header("Location: admin.php");
                        exit;
                    }


                    /* =====================================
                       Unexpected role
                    ===================================== */

                    $error = "Invalid account role.";

                    // Destroy the session if role is invalid
                    session_unset();
                    session_destroy();

                } else {

                    $error = "Invalid email or password.";
                }

            } else {

                $error = "Invalid email or password.";
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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Login - RUET Connect</title>

    <link rel="stylesheet" href="css/style.css">

</head>


<body>

    <header>

        <h1>RUET Connect</h1>

    </header>


    <main class="login-container">

        <section>

            <h2>Sign in to RUET Connect</h2>


            <!-- Login error -->

            <?php if (!empty($error)): ?>

                <p class="login-error">

                    <?php echo htmlspecialchars($error); ?>

                </p>

            <?php endif; ?>


            <form
                action="login.php"
                method="post"
            >

                <div class="login_data">


                    <!-- Email -->

                    <label for="email">
                        Email
                    </label>


                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter your university email"
                        required
                    >


                    <!-- Password -->

                    <label for="pass">
                        Password
                    </label>


                    <input
                        type="password"
                        id="pass"
                        name="password"
                        placeholder="Enter your password"
                        required
                    >


                    <!-- Login button -->

                    <button type="submit">
                        Login
                    </button>


                </div>

            </form>

        </section>

    </main>

</body>

</html>
