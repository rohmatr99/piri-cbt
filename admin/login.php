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

    $username =
        trim($_POST["username"] ?? "");

    $password =
        $_POST["password"] ?? "";


    if (
        $username === ""
        ||
        $password === ""
    ) {

        $error =
            "Username dan password wajib diisi.";

    } else {

        /*
        |----------------------------------------------------------------------
        | Untuk tahap testing kita gunakan admin sederhana.
        |----------------------------------------------------------------------
        */

        if (
            $username === "admin"
            &&
            $password === "admin123"
        ) {

            $_SESSION["admin_id"] = 1;

            $_SESSION["admin_nama"] =
                "Administrator";

            header(
                "Location: dashboard.php"
            );

            exit;

        } else {

            $error =
                "Username atau password salah.";

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


<form
    method="POST"
>


<label>
Username
</label>


<input
    type="text"
    name="username"
    autocomplete="username"
>


<label>
Password
</label>


<input
    type="password"
    name="password"
    autocomplete="current-password"
>


<button
    type="submit"
>

MASUK

</button>


</form>


</div>

</div>


</body>

</html>