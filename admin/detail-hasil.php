<?php

session_start();

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| CEK LOGIN ADMIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["admin_id"])) {

    header("Location: login.php");
    exit;

}

$admin_id = (int)($_SESSION["admin_id"] ?? 0);
$admin_role = $_SESSION["admin_role"] ?? "admin";


/*
|--------------------------------------------------------------------------
| CEK ID HASIL
|--------------------------------------------------------------------------
*/

if (!isset($_GET["id"])) {

    header("Location: hasil-ujian.php");
    exit;

}


$hasil_id = (int) $_GET["id"];


if ($hasil_id <= 0) {

    header("Location: hasil-ujian.php");
    exit;

}


/*
|--------------------------------------------------------------------------
| AMBIL DATA HASIL
|--------------------------------------------------------------------------
*/

if ($admin_role === "superadmin") {

    $stmt = $conn->prepare("
        SELECT
            h.id,
            h.siswa_id,
            h.ujian_id,
            h.jumlah_soal,
            h.jumlah_dijawab,
            h.jumlah_benar,
            h.jumlah_salah,
            h.nilai,

            s.nama,
            s.username,
            s.kelas,

            u.nama_ujian,
            m.nama AS mapel

        FROM hasil_ujian h

        LEFT JOIN siswa s
            ON h.siswa_id = s.id

        LEFT JOIN ujian u
            ON h.ujian_id = u.id

        LEFT JOIN mata_pelajaran m
            ON u.mapel_id = m.id

        WHERE h.id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $hasil_id);

} else {

    $stmt = $conn->prepare("
        SELECT
            h.id,
            h.siswa_id,
            h.ujian_id,
            h.jumlah_soal,
            h.jumlah_dijawab,
            h.jumlah_benar,
            h.jumlah_salah,
            h.nilai,

            s.nama,
            s.username,
            s.kelas,

            u.nama_ujian,
            m.nama AS mapel

        FROM hasil_ujian h

        LEFT JOIN siswa s
            ON h.siswa_id = s.id

        LEFT JOIN ujian u
            ON h.ujian_id = u.id

        LEFT JOIN mata_pelajaran m
            ON u.mapel_id = m.id

        INNER JOIN admin_mapel am
            ON am.mapel_id = u.mapel_id
           AND am.admin_id = ?

        WHERE h.id = ?
        LIMIT 1
    ");

    $stmt->bind_param("ii", $admin_id, $hasil_id);
}

$stmt->execute();

$hasil = $stmt
    ->get_result()
    ->fetch_assoc();

if (!$hasil) {
    die("Data hasil ujian tidak ditemukan.");
}


$stmt->execute();


$hasil =
    $stmt
        ->get_result()
        ->fetch_assoc();


if (!$hasil) {

    die(
        "Data hasil ujian tidak ditemukan."
    );

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


<title>
Detail Hasil Ujian
</title>


<style>

body {

    margin: 0;

    font-family:
        Arial,
        sans-serif;

    background: #f2f5f9;

}


.header {

    background: white;

    padding: 18px;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,.08);

}


.container {

    max-width: 700px;

    margin: auto;

    padding: 20px;

}


.card {

    background: white;

    padding: 25px;

    border-radius: 12px;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.06);

}


h2 {

    margin-top: 0;

}


.info {

    margin-top: 20px;

}


.info div {

    padding: 12px 0;

    border-bottom:
        1px solid #eee;

}


.nilai {

    font-size: 32px;

    font-weight: bold;

    margin:
        20px 0;

}


.tombol {

    margin-top: 25px;

}


.tombol a {

    display: inline-block;

    padding:
        11px 16px;

    background: #6c757d;

    color: white;

    text-decoration: none;

    border-radius: 8px;

}


</style>

</head>


<body>


<div class="header">

<strong>
PIRI CBT — ADMIN
</strong>

</div>


<div class="container">


<div class="card">


<h2>
Detail Hasil Ujian
</h2>


<div class="info">


<div>

<strong>
Nama Siswa:
</strong>

<?= htmlspecialchars(
    $hasil["nama"] ?? "-"
) ?>

</div>


<div>

<strong>
Username:
</strong>

<?= htmlspecialchars(
    $hasil["username"] ?? "-"
) ?>

</div>


<div>

<strong>
Kelas:
</strong>

<?= htmlspecialchars(
    $hasil["kelas"] ?? "-"
) ?>

</div>


<div>

<strong>
Ujian:
</strong>

<?= htmlspecialchars(
    $hasil["nama_ujian"] ?? "-"
) ?>

</div>


<div>

<strong>
Mata Pelajaran:
</strong>

<?= htmlspecialchars(
    $hasil["mapel"] ?? "-"
) ?>

</div>


<div>

<strong>
Jumlah Soal:
</strong>

<?= (int)
    $hasil["jumlah_soal"]
?>

</div>


<div>

<strong>
Dijawab:
</strong>

<?= (int)
    $hasil["jumlah_dijawab"]
?>

</div>


<div>

<strong>
Benar:
</strong>

<?= (int)
    $hasil["jumlah_benar"]
?>

</div>


<div>

<strong>
Salah:
</strong>

<?= (int)
    $hasil["jumlah_salah"]
?>

</div>


<div>

<strong>
Nilai:
</strong>

<span class="nilai">

<?= number_format(
    (float)
    $hasil["nilai"],
    0
) ?>

</span>

</div>


</div>


<div class="tombol">


<a
    href="hasil-ujian.php"
>

← Kembali ke Hasil Ujian

</a>


</div>


</div>


</div>


</body>

</html>