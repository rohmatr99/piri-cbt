<?php

session_start();

require_once "../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {

        $error = "Username dan password wajib diisi.";

    } else {

        $stmt = $conn->prepare(
            "SELECT id, username, nama, kelas, password, status
             FROM siswa
             WHERE username = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $username);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $siswa = $result->fetch_assoc();

            if ($siswa["status"] !== "aktif") {

                $error = "Akun siswa tidak aktif.";

            } elseif ($password === $siswa["password"]) {

                $_SESSION["siswa_id"] = $siswa["id"];
                $_SESSION["username"] = $siswa["username"];
                $_SESSION["nama"] = $siswa["nama"];
                $_SESSION["kelas"] = $siswa["kelas"];

                header("Location: dashboard.php");
                exit;

            } else {

                $error = "Username atau password salah.";
            }

        } else {

            $error = "Username atau password salah.";
        }

        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Login Siswa - PIRI CBT</title>

<style>

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f2f5f9;
}

.container {
    max-width: 420px;
    margin: 50px auto;
    padding: 25px;
}

.card {
    background: white;
    padding: 30px;
    border-radius: 16px;
    box-shadow: 0 4px 15px rgba(0,0,0,.08);
}

h1 {
    text-align: center;
    margin-bottom: 5px;
}

.subtitle {
    text-align: center;
    color: #666;
    margin-bottom: 25px;
}

label {
    display: block;
    margin-top: 15px;
    margin-bottom: 5px;
}

input {
    width: 100%;
    padding: 13px;
    box-sizing: border-box;
    border: 1px solid #ccc;
    border-radius: 8px;
    font-size: 16px;
}

button {
    width: 100%;
    padding: 13px;
    margin-top: 20px;
    border: none;
    border-radius: 8px;
    background: #1769aa;
    color: white;
    font-size: 16px;
}

.error {
    background: #ffe5e5;
    color: #a00;
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 15px;
}

</style>

</head>

<body>

<div class="container">

<div class="card">

<h1>PIRI CBT</h1>

<div class="subtitle">
Login Siswa
</div>

<?php if ($error): ?>

<div class="error">
<?= htmlspecialchars($error) ?>
</div>

<?php endif; ?>

<form method="POST">

<label>Username</label>

<input
    type="text"
    name="username"
    placeholder="Masukkan username"
    required
>

<label>Password</label>

<input
    type="password"
    name="password"
    placeholder="Masukkan password"
    required
>

<button type="submit">
LOGIN
</button>

</form>

</div>

</div>

</body>
</html>