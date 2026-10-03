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


/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

$nama_siswa = trim($_GET["nama_siswa"] ?? "");
$kelas      = trim($_GET["kelas"] ?? "");
$mapel_id   = (int) ($_GET["mapel_id"] ?? 0);
$ujian_id   = (int) ($_GET["ujian_id"] ?? 0);


/*
|--------------------------------------------------------------------------
| DATA FILTER KELAS
|--------------------------------------------------------------------------
*/

$daftar_kelas = [];

$result_kelas = $conn->query("
    SELECT DISTINCT kelas
    FROM siswa
    WHERE kelas IS NOT NULL
      AND kelas <> ''
    ORDER BY kelas ASC
");

while ($row = $result_kelas->fetch_assoc()) {

    $daftar_kelas[] = $row["kelas"];

}


/*
|--------------------------------------------------------------------------
| DATA MAPEL
|--------------------------------------------------------------------------
*/

$daftar_mapel = [];

$result_mapel = $conn->query("
    SELECT
        id,
        nama
    FROM mata_pelajaran
    ORDER BY nama ASC
");

while ($row = $result_mapel->fetch_assoc()) {

    $daftar_mapel[] = $row;

}


/*
|--------------------------------------------------------------------------
| DATA UJIAN
|--------------------------------------------------------------------------
*/

$daftar_ujian = [];

$result_ujian = $conn->query("
    SELECT
        id,
        nama_ujian
    FROM ujian
    ORDER BY nama_ujian ASC
");

while ($row = $result_ujian->fetch_assoc()) {

    $daftar_ujian[] = $row;

}


/*
|--------------------------------------------------------------------------
| QUERY PELANGGARAN
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        pel.id,
        pel.sesi_id,
        pel.siswa_id,
        pel.ujian_id,
        pel.jenis,
        pel.waktu,

        (
            SELECT COUNT(*)
            FROM pelanggaran_ujian pel2
            WHERE pel2.siswa_id = pel.siswa_id
              AND pel2.ujian_id = pel.ujian_id
        ) AS total_pelanggaran_siswa,

        s.nama AS nama_siswa,
        s.username,
        s.kelas,

        u.nama_ujian,

        mp.nama AS nama_mapel

    FROM pelanggaran_ujian pel

    INNER JOIN siswa s
        ON s.id = pel.siswa_id

    INNER JOIN ujian u
        ON u.id = pel.ujian_id

    LEFT JOIN mata_pelajaran mp
        ON mp.id = u.mapel_id

    WHERE 1 = 1
";


$params = [];
$types  = "";


/*
|--------------------------------------------------------------------------
| FILTER NAMA
|--------------------------------------------------------------------------
*/

if ($nama_siswa !== "") {

    $sql .= "
        AND (
            s.nama LIKE ?
            OR s.username LIKE ?
        )
    ";

    $kata_nama = "%" . $nama_siswa . "%";

    $params[] = $kata_nama;
    $params[] = $kata_nama;

    $types .= "ss";

}


/*
|--------------------------------------------------------------------------
| FILTER KELAS
|--------------------------------------------------------------------------
*/

if ($kelas !== "") {

    $sql .= "
        AND s.kelas = ?
    ";

    $params[] = $kelas;

    $types .= "s";

}


/*
|--------------------------------------------------------------------------
| FILTER MAPEL
|--------------------------------------------------------------------------
*/

if ($mapel_id > 0) {

    $sql .= "
        AND u.mapel_id = ?
    ";

    $params[] = $mapel_id;

    $types .= "i";

}


/*
|--------------------------------------------------------------------------
| FILTER UJIAN
|--------------------------------------------------------------------------
*/

if ($ujian_id > 0) {

    $sql .= "
        AND pel.ujian_id = ?
    ";

    $params[] = $ujian_id;

    $types .= "i";

}


/*
|--------------------------------------------------------------------------
| URUTKAN
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        pel.waktu DESC,
        pel.id DESC
";


$stmt = $conn->prepare($sql);


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );

}


$stmt->execute();

$result = $stmt->get_result();


/*
|--------------------------------------------------------------------------
| DATA HASIL
|--------------------------------------------------------------------------
*/

$data_pelanggaran = [];

$total_pelanggaran = 0;

while ($row = $result->fetch_assoc()) {

    $data_pelanggaran[] = $row;

    $total_pelanggaran++;

}


/*
|--------------------------------------------------------------------------
| KELOMPOK PELANGGARAN PER SISWA + UJIAN
|--------------------------------------------------------------------------
*/

$kelompok_pelanggaran = [];

foreach ($data_pelanggaran as $row) {

    $key = $row["siswa_id"] . "_" . $row["ujian_id"];

    if (!isset($kelompok_pelanggaran[$key])) {

        $kelompok_pelanggaran[$key] = [
            "siswa_id" => $row["siswa_id"],
            "ujian_id" => $row["ujian_id"],
            "nama_siswa" => $row["nama_siswa"],
            "username" => $row["username"],
            "kelas" => $row["kelas"],
            "nama_mapel" => $row["nama_mapel"] ?? "-",
            "nama_ujian" => $row["nama_ujian"],
            "total_pelanggaran" => 0,
            "detail" => []
        ];

    }

    $kelompok_pelanggaran[$key]["total_pelanggaran"]++;

    $kelompok_pelanggaran[$key]["detail"][] = [
        "waktu" => $row["waktu"],
        "jenis" => $row["jenis"]
    ];

}


/*
|--------------------------------------------------------------------------
| JUMLAH SISWA YANG MELANGGAR
|--------------------------------------------------------------------------
*/

$siswa_melanggar = [];

foreach ($data_pelanggaran as $row) {

    $siswa_melanggar[
        $row["siswa_id"]
    ] = true;

}

$total_siswa_melanggar =
    count($siswa_melanggar);


/*
|--------------------------------------------------------------------------
| ESCAPE
|--------------------------------------------------------------------------
*/

function e($text)
{
    return htmlspecialchars(
        (string) $text,
        ENT_QUOTES,
        "UTF-8"
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
    Data Pelanggaran
</title>


<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f4f6f9;

    color: #212529;

}


.container {

    max-width: 1400px;

    margin: 30px auto;

    padding: 0 20px;

}


.header {

    background: white;

    padding: 20px;

    border-radius: 10px;

    margin-bottom: 20px;

    box-shadow:
        0 2px 8px rgba(0,0,0,.08);

}


.header h1 {

    margin: 0 0 8px 0;

}


.header p {

    margin: 0;

    color: #6c757d;

}


/*
|--------------------------------------------------------------------------
| RINGKASAN
|--------------------------------------------------------------------------
*/

.summary {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 15px;

    margin-bottom: 20px;

}


.box {

    background: white;

    padding: 20px;

    border-radius: 10px;

    box-shadow:
        0 2px 8px rgba(0,0,0,.08);

}


.box .label {

    color: #6c757d;

    font-size: 14px;

}


.box .number {

    font-size: 30px;

    font-weight: bold;

    margin-top: 5px;

}


/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

.filter {

    background: white;

    padding: 20px;

    border-radius: 10px;

    margin-bottom: 20px;

    box-shadow:
        0 2px 8px rgba(0,0,0,.08);

}


.filter-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

}


.field label {

    display: block;

    font-weight: bold;

    margin-bottom: 6px;

}


.field input,
.field select {

    width: 100%;

    padding: 10px;

    border: 1px solid #ced4da;

    border-radius: 6px;

    background: white;

}


.filter-actions {

    margin-top: 15px;

    display: flex;

    gap: 10px;

    flex-wrap: wrap;

}


.btn {

    display: inline-block;

    padding: 10px 16px;

    border: none;

    border-radius: 6px;

    text-decoration: none;

    cursor: pointer;

    font-size: 14px;

}


.btn-filter {

    background: #0d6efd;

    color: white;

}


.btn-reset {

    background: #6c757d;

    color: white;

}


/*
|--------------------------------------------------------------------------
| TABEL
|--------------------------------------------------------------------------
*/

.table-box {

    background: white;

    border-radius: 10px;

    overflow: hidden;

    box-shadow:
        0 2px 8px rgba(0,0,0,.08);

}


.table-wrapper {

    overflow-x: auto;

}


table {

    width: 100%;

    border-collapse: collapse;

    min-width: 950px;

}


th,
td {

    padding: 12px;

    border-bottom: 1px solid #dee2e6;

    text-align: left;

}


th {

    background: #f8f9fa;

    white-space: nowrap;

}


.badge {

    display: inline-block;

    padding: 5px 8px;

    border-radius: 5px;

    background: #fff3cd;

    color: #856404;

    font-size: 13px;

    font-weight: bold;

}


.empty {

    padding: 40px;

    text-align: center;

    color: #6c757d;

}


.detail-row {

    display: none;

    background: #f8f9fa;

}


.detail-row td {

    padding: 0;

}


.detail-box {

    padding: 15px 20px 20px 55px;

    border-top: 1px solid #e9ecef;

}


.detail-title {

    font-weight: bold;

    margin-bottom: 10px;

    color: #495057;

}


.detail-table {

    width: 100%;

    min-width: 500px;

    border-collapse: collapse;

    background: white;

}


.detail-table th,
.detail-table td {

    padding: 8px 10px;

    border-bottom: 1px solid #dee2e6;

    font-size: 13px;

}


.detail-table th {

    background: #f1f3f5;

}


.btn-detail {

    background: #0d6efd;

    color: white;

    padding: 7px 12px;

    border: none;

    border-radius: 5px;

    cursor: pointer;

    font-size: 13px;

}


.btn-detail:hover {

    opacity: .9;

}


@media (max-width: 900px) {

    .filter-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


@media (max-width: 600px) {

    .filter-grid {

        grid-template-columns: 1fr;

    }

    .summary {

        grid-template-columns: 1fr;

    }

}

</style>

</head>


<body>


<div class="container">


<div class="header">

<h1>
    ⚠️ Data Pelanggaran Ujian
</h1>

<p>
    Melihat aktivitas pelanggaran siswa selama mengerjakan ujian.
</p>

</div>


<!-- =========================================================
     RINGKASAN
========================================================= -->

<div class="summary">


<div class="box">

<div class="label">
    Total Pelanggaran
</div>

<div class="number">
    <?= $total_pelanggaran ?>
</div>

</div>


<div class="box">

<div class="label">
    Siswa yang Melanggar
</div>

<div class="number">
    <?= $total_siswa_melanggar ?>
</div>

</div>


</div>


<!-- =========================================================
     FILTER
========================================================= -->

<div class="filter">

<form
    method="GET"
>


<div class="filter-grid">


<div class="field">

<label>
    Nama Siswa
</label>

<input
    type="text"
    name="nama_siswa"
    value="<?= e($nama_siswa) ?>"
    placeholder="Cari nama / username..."
>

</div>


<div class="field">

<label>
    Kelas
</label>

<select name="kelas">

<option value="">
    Semua Kelas
</option>

<?php foreach ($daftar_kelas as $item_kelas): ?>

<option
    value="<?= e($item_kelas) ?>"
    <?= $kelas === $item_kelas
        ? "selected"
        : ""
    ?>
>
    <?= e($item_kelas) ?>
</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
    Mapel
</label>

<select name="mapel_id">

<option value="0">
    Semua Mapel
</option>

<?php foreach ($daftar_mapel as $mapel): ?>

<option
    value="<?= (int) $mapel["id"] ?>"
    <?= $mapel_id === (int) $mapel["id"]
        ? "selected"
        : ""
    ?>
>
    <?= e($mapel["nama"]) ?>
</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
    Jenis / Nama Ujian
</label>

<select name="ujian_id">

<option value="0">
    Semua Ujian
</option>

<?php foreach ($daftar_ujian as $ujian): ?>

<option
    value="<?= (int) $ujian["id"] ?>"
    <?= $ujian_id === (int) $ujian["id"]
        ? "selected"
        : ""
    ?>
>
    <?= e($ujian["nama_ujian"]) ?>
</option>

<?php endforeach; ?>

</select>

</div>


</div>


<div class="filter-actions">

<button
    type="submit"
    class="btn btn-filter"
>
    🔍 Terapkan Filter
</button>


<a
    href="pelanggaran.php"
    class="btn btn-reset"
>
    Reset Filter
</a>

</div>


</form>

</div>


<!-- =========================================================
     TABEL
========================================================= -->

<div class="table-box">

<div class="table-wrapper">

<table>

<thead>

<tr>

<th>
    No
</th>

<th>
    Nama Siswa
</th>

<th>
    Kelas
</th>

<th>
    Mapel
</th>

<th>
    Ujian
</th>

<th>
    Total Pelanggaran
</th>

<th>
    Aksi
</th>

</tr>

</thead>


<tbody>


<?php if (empty($kelompok_pelanggaran)): ?>

<tr>

<td
    colspan="7"
    class="empty"
>
    Tidak ada data pelanggaran.
</td>

</tr>


<?php else: ?>


<?php
$nomor = 1;

foreach (
    $kelompok_pelanggaran
    as $group
):
?>

<tr>

<td>
    <?= $nomor++ ?>
</td>


<td>

<strong>
    <?= e($group["nama_siswa"]) ?>
</strong>

<br>

<small>
    <?= e($group["username"]) ?>
</small>

</td>


<td>
    <?= e($group["kelas"]) ?>
</td>


<td>
    <?= e($group["nama_mapel"]) ?>
</td>


<td>
    <?= e($group["nama_ujian"]) ?>
</td>


<td>

<span
    class="badge"
    style="
        background: #dc3545;
        color: white;
        font-size: 14px;
        min-width: 40px;
        text-align: center;
    "
>
    <?= (int) $group["total_pelanggaran"] ?>
</span>

</td>


<td>

<button
    type="button"
    class="btn-detail"
    onclick="toggleDetail('detail-<?= $nomor - 1 ?>', this)"
>
    👁 Lihat Detail
</button>

</td>

</tr>


<tr
    id="detail-<?= $nomor - 1 ?>"
    class="detail-row"
>

<td colspan="7">

<div class="detail-box">

<div class="detail-title">
    Detail Pelanggaran —
    <?= e($group["nama_siswa"]) ?>
    (<?= e($group["nama_ujian"]) ?>)
</div>


<table class="detail-table">

<thead>

<tr>

<th>
    No
</th>

<th>
    Waktu
</th>

<th>
    Jenis Pelanggaran
</th>

</tr>

</thead>


<tbody>

<?php foreach (
    $group["detail"]
    as $detail_index => $detail
): ?>

<tr>

<td>
    <?= $detail_index + 1 ?>
</td>

<td>
    <?= e($detail["waktu"]) ?>
</td>

<td>

<span class="badge">
    <?= e($detail["jenis"]) ?>
</span>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</td>

</tr>


<?php endforeach; ?>


<?php endif; ?>


</tbody>

</table>

</div>

</div>


</div>



<script>

function toggleDetail(id, button) {

    const row = document.getElementById(id);

    if (!row) {
        return;
    }

    if (row.style.display === "table-row") {

        row.style.display = "none";

        button.innerHTML = "👁 Lihat Detail";

    } else {

        row.style.display = "table-row";

        button.innerHTML = "▲ Tutup Detail";

    }

}

</script>

</body>

</html>