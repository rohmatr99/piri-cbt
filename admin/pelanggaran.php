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

$admin_id = (int) ($_SESSION["admin_id"] ?? 0);
$admin_role = $_SESSION["admin_role"] ?? "admin";

$result_mapel = $conn->query("
    SELECT id, nama
    FROM mata_pelajaran
    " . ($admin_role === "superadmin" ? "" : " WHERE id IN (SELECT mapel_id FROM admin_mapel WHERE admin_id = {$admin_id})") . "
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
    SELECT id, nama_ujian
    FROM ujian
    " . ($admin_role === "superadmin" ? "" : " WHERE mapel_id IN (SELECT mapel_id FROM admin_mapel WHERE admin_id = {$admin_id})") . "
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

if ($admin_role !== "superadmin") {
    $sql .= " AND u.mapel_id IN (SELECT mapel_id FROM admin_mapel WHERE admin_id = {$admin_id})";
}


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

<?php
$page_title = "Pelanggaran Ujian";
$page_description = "Periksa aktivitas pelanggaran siswa selama ujian";

require __DIR__ . "/includes/header.php";
require __DIR__ . "/includes/sidebar.php";
?>
<style>

/* =========================================================
   HALAMAN PELANGGARAN - PROFESSIONAL ADMIN STYLE
========================================================= */

/* Area halaman menggunakan lebar penuh */
.main .content {
    max-width: none;
    width: 100%;
}

.pelanggaran-page {
    width: 100%;
    max-width: none;
    margin: 0;
}

.pelanggaran-container {
    width: 100%;
    max-width: none;
    margin: 0;
}


/* =========================================================
   JUDUL HALAMAN
========================================================= */

.pelanggaran-page .header {
    margin: 0 0 22px;
}

.pelanggaran-page .header h1 {
    margin: 0 0 7px;
    font-size: 28px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.25;
}

.pelanggaran-page .header p {
    margin: 0;
    color: #64748b;
    font-size: 14px;
}


/* =========================================================
   RINGKASAN STATISTIK
========================================================= */

.pelanggaran-page .summary {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin: 0 0 20px;
}

.pelanggaran-page .summary .box {
    position: relative;
    overflow: hidden;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 20px 22px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.pelanggaran-page .summary .box::after {
    content: "";
    position: absolute;
    right: -25px;
    top: -25px;
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: rgba(37, 99, 235, 0.06);
}

.pelanggaran-page .summary .label {
    margin-bottom: 8px;
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
}

.pelanggaran-page .summary .number {
    color: #0f172a;
    font-size: 30px;
    font-weight: 800;
    line-height: 1;
}


/* =========================================================
   FILTER
========================================================= */

.pelanggaran-page .filter {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.pelanggaran-filter-grid {
    display: grid;
    grid-template-columns:
        minmax(180px, 1.2fr)
        minmax(150px, 1fr)
        minmax(170px, 1fr)
        minmax(190px, 1.1fr);
    gap: 16px;
}

.pelanggaran-field label {
    display: block;
    margin-bottom: 7px;
    color: #334155;
    font-size: 13px;
    font-weight: 700;
}

.pelanggaran-field input,
.pelanggaran-field select {
    width: 100%;
    height: 42px;
    padding: 0 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #ffffff;
    color: #0f172a;
    font-size: 14px;
    outline: none;
    transition: border-color .15s, box-shadow .15s;
    box-sizing: border-box;
}

.pelanggaran-field input:focus,
.pelanggaran-field select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .10);
}

.filter-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 16px;
}

.pelanggaran-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    padding: 0 15px;
    border: 0;
    border-radius: 8px;
    color: #ffffff;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: transform .15s, opacity .15s;
}

.pelanggaran-btn:hover {
    opacity: .92;
    transform: translateY(-1px);
}

.pelanggaran-filter {
    background: #2563eb;
}

.pelanggaran-reset {
    background: #64748b;
}


/* =========================================================
   TABEL UTAMA
========================================================= */

.table-box {
    overflow: hidden;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.pelanggaran-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.pelanggaran-table {
    width: 100%;
    min-width: 950px;
    border-collapse: collapse;
}

.pelanggaran-table th {
    padding: 14px 13px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    color: #334155;
    font-size: 12px;
    font-weight: 800;
    text-align: left;
    white-space: nowrap;
}

.pelanggaran-table td {
    padding: 14px 13px;
    border-bottom: 1px solid #eef2f7;
    color: #334155;
    font-size: 13px;
    vertical-align: middle;
}

.pelanggaran-table tbody tr:hover {
    background: #f8fafc;
}

.pelanggaran-table td strong {
    color: #0f172a;
    font-weight: 700;
}

.pelanggaran-table td small {
    color: #94a3b8;
    font-size: 11px;
}


/* =========================================================
   BADGE JUMLAH PELANGGARAN
========================================================= */

.pelanggaran-table .badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px !important;
    height: 28px;
    padding: 0 9px;
    border-radius: 999px !important;
    background: #fee2e2 !important;
    color: #b91c1c !important;
    font-size: 12px !important;
    font-weight: 800;
}


/* =========================================================
   TOMBOL DETAIL
========================================================= */

.btn-detail {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 0 11px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    background: #ffffff;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.btn-detail:hover {
    background: #f1f5f9;
}


/* =========================================================
   BARIS DETAIL
========================================================= */

.detail-row {
    display: none;
}

.detail-row td {
    padding: 0 !important;
    background: #f8fafc;
}

.detail-box {
    padding: 18px 22px 20px;
    border-top: 1px solid #e2e8f0;
}

.detail-title {
    margin-bottom: 12px;
    color: #0f172a;
    font-size: 13px;
    font-weight: 800;
}

.detail-table {
    width: 100%;
    max-width: 760px;
    border-collapse: collapse;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
}

.detail-table th {
    padding: 10px 12px;
    background: #f1f5f9;
    border-bottom: 1px solid #e2e8f0;
    color: #475569;
    font-size: 11px;
    font-weight: 800;
    text-align: left;
}

.detail-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #eef2f7;
    color: #475569;
    font-size: 12px;
}

.detail-table tr:last-child td {
    border-bottom: 0;
}


/* =========================================================
   DATA KOSONG
========================================================= */

.empty,
.pelanggaran-empty {
    padding: 45px 20px !important;
    color: #94a3b8 !important;
    text-align: center !important;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1100px) {

    .pelanggaran-filter-grid {
        grid-template-columns: 1fr 1fr;
    }

}

@media (max-width: 700px) {

    .pelanggaran-page .summary {
        grid-template-columns: 1fr;
    }

    .pelanggaran-filter-grid {
        grid-template-columns: 1fr;
    }

    .pelanggaran-page .header h1 {
        font-size: 23px;
    }

    .pelanggaran-page .filter {
        padding: 15px;
    }

    .pelanggaran-page .summary .box {
        padding: 16px;
    }

}

</style>
<main class="main">
<header class="topbar"><div class="page-title"><h1><?= htmlspecialchars($page_title) ?></h1><p><?= htmlspecialchars($page_description) ?></p></div><div class="top-user"><div class="top-user-icon">👤</div><span><?= htmlspecialchars($_SESSION["admin_nama"] ?? "Administrator") ?></span></div></header>
<header class="mobile-header"><button type="button" class="menu-button" onclick="bukaSidebar()">☰</button><div class="mobile-title">PIRI CBT — Admin</div><div style="width:40px;"></div></header>
<section class="content">
<div class="pelanggaran-page">



<div class="pelanggaran-container">


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


<div class="pelanggaran-filter-grid">


<div class="pelanggaran-field">

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


<div class="pelanggaran-field">

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


<div class="pelanggaran-field">

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


<div class="pelanggaran-field">

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
    class="pelanggaran-btn pelanggaran-filter"
>
    🔍 Terapkan Filter
</button>


<a
    href="pelanggaran.php"
    class="pelanggaran-btn pelanggaran-reset"
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

<div class="pelanggaran-table-wrap">

<table class="pelanggaran-table">

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


</div>
</section>
</main>
<?php require __DIR__ . "/includes/footer.php"; ?>
