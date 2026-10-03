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

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Ujian Selesai</title>

<style>

body {

    margin: 0;

    font-family:
        Arial,
        sans-serif;

    background: #f2f5f9;

}


.container {

    max-width: 500px;

    margin: 80px auto;

    padding: 15px;

}


.card {

    background: white;

    padding: 30px;

    border-radius: 15px;

    text-align: center;

    box-shadow:
        0 3px 10px
        rgba(0,0,0,.08);

}


.icon {

    font-size: 60px;

    margin-bottom: 15px;

}


h2 {

    margin:
        0 0 10px 0;

}


p {

    color: #666;

    line-height: 1.6;

}


button {

    margin-top: 20px;

    padding:
        14px 25px;

    border: none;

    border-radius: 8px;

    background: #198754;

    color: white;

    font-size: 16px;

    cursor: pointer;

}


button:hover {

    opacity: .9;

}

</style>

</head>


<body>


<div class="container">


<div class="card">


<div class="icon">

✓

</div>


<h2>

Ujian Berhasil Dikumpulkan

</h2>


<p>

Jawaban Anda telah berhasil dikirim dan disimpan.

</p>


<p>

Hasil ujian akan diumumkan oleh guru atau admin.

</p>


<button
    onclick="
        window.location.href='dashboard.php'
    "
>

Kembali ke Dashboard

</button>


</div>


</div>


</body>

</html>