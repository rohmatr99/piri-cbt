<?php

session_start();

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| Jika sudah login admin
|--------------------------------------------------------------------------
*/

if (isset($_SESSION["admin_id"])) {

    header("Location: dashboard.php");
    exit;

}


$error = "";


/*
|--------------------------------------------------------------------------
| Proses login
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";


    if ($username === "" || $password === "") {

        $error = "Username dan password wajib diisi.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Ambil akun admin berdasarkan username
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT
                id,
                nama,
                username,
                password,
                role,
                status
            FROM admin_users
            WHERE username = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $username);

        $stmt->execute();

        $result = $stmt->get_result();

        $admin = $result->fetch_assoc();

        $stmt->close();


        /*
        |--------------------------------------------------------------------------
        | Periksa akun dan password
        |--------------------------------------------------------------------------
        */

        if (!$admin) {

            $error = "Username atau password salah.";

        } elseif ($admin["status"] !== "aktif") {

            $error = "Akun administrator tidak aktif.";

        } elseif (!password_verify($password, $admin["password"])) {

            $error = "Username atau password salah.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Login berhasil
            |--------------------------------------------------------------------------
            */

            session_regenerate_id(true);

            $_SESSION["admin_id"] = (int) $admin["id"];

            $_SESSION["admin_nama"] = $admin["nama"];

            $_SESSION["admin_username"] = $admin["username"];

            $_SESSION["admin_role"] = $admin["role"];

            header("Location: dashboard.php");
            exit;

        }

    }

}

?>

<!DOCTYPE html>

<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Login Admin - PIRI CBT</title>


<style>

body {

    margin: 0;

    font-family: Arial, sans-serif;

    background: #f2f5f9;

}


.container {

    max-width: 400px;

    margin: 80px auto;

    padding: 20px;

}


.card {

    background: white;

    padding: 25px;

    border-radius: 15px;

    box-shadow:
        0 4px 15px
        rgba(0,0,0,.08);

}


h1 {

    margin-top: 0;

    text-align: center;

}


input {

    width: 100%;

    padding: 12px;

    margin:
        8px 0 15px;

    border:
        1px solid #ddd;

    border-radius: 8px;

    box-sizing: border-box;

}


button {

    width: 100%;

    padding: 13px;

    border: none;

    border-radius: 8px;

    background: #198754;

    color: white;

    font-size: 16px;

    cursor: pointer;

}


button:hover {

    background: #157347;

}


.error {

    background: #f8d7da;

    color: #842029;

    padding: 10px;

    border-radius: 8px;

    margin-bottom: 15px;

}

</style>

</head>


<body>


<div class="container">


<div class="card">


<h1>
PIRI CBT
</h1>


<p style="text-align:center;">
Login Administrator
</p>


<?php if ($error): ?>

<div class="error">

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>


<form method="POST">


<label>
Username
</label>


<input
    type="text"
    name="username"
    autocomplete="username"
    autofocus
>


<label>
Password
</label>


<input
    type="password"
    name="password"
    autocomplete="current-password"
>


<button type="submit">

MASUK

</button>


</form>


</div>

</div>


</body>

</html>