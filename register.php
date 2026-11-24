<?php

session_start();

require_once "config/database.php";

$error = "";
$success = "";

// Form variables
$full_name = "";
$email = "";
$role = "";
$student_id = "";
$department = "";
$password = "";
$confirm_password = "";


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get submitted values
    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $role = $_POST["role"] ?? "";
    $student_id = trim($_POST["student_id"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";


    /* =========================================================
       1. Validate required information
    ========================================================= */

    if (
        empty($full_name) ||
        empty($email) ||
        empty($role) ||
        empty($department) ||
        empty($password) ||
        empty($confirm_password)
    ) {

        $error = "Please fill in all required fields.";
    }


    /* =========================================================
       2. Validate email format
    ========================================================= */

    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";
    }


    /* =========================================================
       3. Validate account type
    ========================================================= */

    elseif ($role !== "student" && $role !== "teacher") {

        $error = "Invalid account type.";
    }


    /* =========================================================
       4. Student ID validation
          Only students require a Student ID.
    ========================================================= */

    elseif ($role === "student" && empty($student_id)) {

        $error = "Student ID is required for student registration.";
    }


    /* =========================================================
       5. Password confirmation
    ========================================================= */

    elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";
    }


    /* =========================================================
       6. Password length
    ========================================================= */

    elseif (strlen($password) < 8) {

        $error = "Password must be at least 8 characters long.";
    }


    /* =========================================================
       7. Database operations
    ========================================================= */

    else {

        /* -----------------------------------------------------
           Check whether email already exists
        ----------------------------------------------------- */

        $stmt = $conn->prepare(
            "SELECT id FROM users WHERE email = ?"
        );

        $stmt->bind_param("s", $email);

        $stmt->execute();

        $result = $stmt->get_result();


        if ($result->num_rows > 0) {

            $error = "An account with this email already exists.";

            $stmt->close();

        } else {

            $stmt->close();


            /* -------------------------------------------------
               Check Student ID
               Only for students
            ------------------------------------------------- */

            if ($role === "student") {

                $stmt = $conn->prepare(
                    "SELECT id FROM users WHERE student_id = ?"
                );

                $stmt->bind_param("s", $student_id);

                $stmt->execute();

                $student_result = $stmt->get_result();


                if ($student_result->num_rows > 0) {

                    $error = "This Student ID is already registered.";
                }

                $stmt->close();
            }


            /* -------------------------------------------------
               Create account if there are no errors
            ------------------------------------------------- */

            if (empty($error)) {

                /*
                 * Never store the original password.
                 *
                 * password_hash() creates a secure password hash.
                 */

                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


                /*
                 * Teachers do not have Student IDs.
                 * Therefore, store NULL for teachers.
                 */

                if ($role === "teacher") {

                    $student_id_value = null;

                } else {

                    $student_id_value = $student_id;
                }


                /* -------------------------------------------------
                   Insert new user into the database
                ------------------------------------------------- */

                $stmt = $conn->prepare(
                    "INSERT INTO users
                    (
                        full_name,
                        email,
                        password,
                        role,
                        student_id,
                        department
                    )
                    VALUES (?, ?, ?, ?, ?, ?)"
                );


                $stmt->bind_param(
                    "ssssss",
                    $full_name,
                    $email,
                    $hashed_password,
                    $role,
                    $student_id_value,
                    $department
                );


                if ($stmt->execute()) {

                    $success =
                        "Registration successful! You can now log in.";


                    /*
                     * Clear form values after successful registration.
                     */

                    $full_name = "";
                    $email = "";
                    $role = "";
                    $student_id = "";
                    $department = "";
                    $password = "";
                    $confirm_password = "";

                } else {

                    $error =
                        "Registration failed. Please try again.";
                }


                $stmt->close();
            }
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

    <title>Register - RUET Connect</title>

    <link rel="stylesheet" href="css/style.css">

</head>


<body>

    <header>

        <h1>RUET Connect</h1>

    </header>


    <main class="login-container">

        <section>

            <h2>Create your RUET Connect account</h2>


            <!-- Error message -->

            <?php if (!empty($error)): ?>

                <p class="login-error">

                    <?php echo htmlspecialchars($error); ?>

                </p>

            <?php endif; ?>


            <!-- Success message -->

            <?php if (!empty($success)): ?>

                <p class="login-success">

                    <?php echo htmlspecialchars($success); ?>

                </p>

            <?php endif; ?>


            <form
                action="register.php"
                method="post"
            >

                <div class="login_data">


                    <!-- =================================================
                         Account Type
                    ================================================== -->

                    <label for="role">
                        Account Type
                    </label>


                    <select
                        id="role"
                        name="role"
                        required
                    >

                        <option
                            value=""
                            <?php echo ($role === "") ? "selected" : ""; ?>
                        >
                            Select account type
                        </option>


                        <option
                            value="student"
                            <?php echo ($role === "student") ? "selected" : ""; ?>
                        >
                            Student
                        </option>


                        <option
                            value="teacher"
                            <?php echo ($role === "teacher") ? "selected" : ""; ?>
                        >
                            Teacher
                        </option>

                    </select>


                    <!-- =================================================
                         Additional Fields

                         Hidden until Student or Teacher is selected.
                    ================================================== -->

                    <div
                        id="additional-fields"
                        style="display: <?php echo empty($role) ? "none" : "block"; ?>;"
                    >


                        <!-- Full Name -->

                        <label for="full_name">
                            Full Name
                        </label>


                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            placeholder="Enter your full name"
                            value="<?php echo htmlspecialchars($full_name); ?>"
                        >


                        <!-- Email -->

                        <label for="email">
                            Email
                        </label>


                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Enter your university email"
                            value="<?php echo htmlspecialchars($email); ?>"
                        >


                        <!-- =================================================
                             Student ID

                             Visible only for students.
                        ================================================== -->

                        <div
                            id="student-id-field"
                            style="display: <?php echo ($role === "student") ? "block" : "none"; ?>;"
                        >

                            <label for="student_id">
                                Student ID
                            </label>


                            <input
                                type="text"
                                id="student_id"
                                name="student_id"
                                placeholder="Enter your student ID"
                                value="<?php echo htmlspecialchars($student_id); ?>"
                            >

                        </div>


                        <!-- Department -->

                        <label for="department">
                            Department
                        </label>


                        <input
                            type="text"
                            id="department"
                            name="department"
                            placeholder="Enter your department"
                            value="<?php echo htmlspecialchars($department); ?>"
                        >


                        <!-- Password -->

                        <label for="password">
                            Password
                        </label>


                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                        >


                        <!-- Confirm Password -->

                        <label for="confirm_password">
                            Confirm Password
                        </label>


                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Confirm your password"
                        >


                        <!-- Create Account -->

                        <button type="submit">
                            Create Account
                        </button>

                    </div>

                </div>

            </form>

        </section>

    </main>


    <!-- =========================================================
         JavaScript
         Controls Student/Teacher-specific fields.
    ========================================================== -->

    <script>

        const roleSelect =
            document.getElementById("role");

        const additionalFields =
            document.getElementById("additional-fields");

        const studentIdField =
            document.getElementById("student-id-field");

        const studentIdInput =
            document.getElementById("student_id");


        roleSelect.addEventListener("change", function () {

            /* -------------------------------------------------
               Student selected
            ------------------------------------------------- */

            if (this.value === "student") {

                // Show all additional fields
                additionalFields.style.display = "block";

                // Show Student ID
                studentIdField.style.display = "block";

                // Student ID is required
                studentIdInput.required = true;
            }


            /* -------------------------------------------------
               Teacher selected
            ------------------------------------------------- */

            else if (this.value === "teacher") {

                // Show additional fields
                additionalFields.style.display = "block";

                // Hide Student ID
                studentIdField.style.display = "none";

                // Student ID is not required
                studentIdInput.required = false;

                // Clear Student ID
                studentIdInput.value = "";
            }


            /* -------------------------------------------------
               No account type selected
            ------------------------------------------------- */

            else {

                // Hide all additional fields
                additionalFields.style.display = "none";

                // Hide Student ID
                studentIdField.style.display = "none";

                // Student ID is not required
                studentIdInput.required = false;

                // Clear Student ID
                studentIdInput.value = "";
            }

        });


        /* =====================================================
           Set correct Student ID requirement when page loads.

           This is important if PHP reloads the page after
           a validation error.
        ====================================================== */

        if (roleSelect.value === "student") {

            studentIdInput.required = true;

        } else {

            studentIdInput.required = false;
        }

    </script>

</body>

</html>
