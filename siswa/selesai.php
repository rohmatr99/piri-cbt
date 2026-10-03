<?php

session_start();

if (!isset($_SESSION["siswa_id"])) {
    header("Location: login.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Ujian Selesai</title>

<style>

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f2f5f9;
}

.box {
    max-width: 500px;
    margin: 80px auto;
    background: white;
    padding: 30px;
    border-radius: 15px;
    text-align: center;
}

.icon {
    font-size: 60px;
}

h1 {
    margin-bottom: 10px;
}

p {
    color: #555;
}

button {
    margin-top: 20px;
    padding: 14px 25px;
    border: none;
    border-radius: 8px;
    background: #198754;
    color: white;
    font-size: 16px;
}

</style>

</head>

<body>

<div class="box">

<div class="icon">
✓
</div>

<h1>Ujian Selesai</h1>

<p>
Jawaban Anda telah berhasil dikirim.
</p>

<p>
Terima kasih telah mengikuti ujian.
</p>

<button onclick="window.location.href='dashboard.php'">
Kembali ke Dashboard
</button>

</div>

</body>

</html>