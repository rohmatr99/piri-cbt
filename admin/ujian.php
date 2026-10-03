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
| AMBIL DATA UJIAN
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        u.id,
        u.nama_ujian,
        u.kelas,
        u.durasi,
        u.minimal_menit,
        u.token,
        u.tanggal_mulai,
        u.tanggal_selesai,
        u.status,

        m.nama AS nama_mapel

    FROM ujian u

    LEFT JOIN mata_pelajaran m
        ON u.mapel_id = m.id

    ORDER BY u.id DESC
";


$result = $conn->query($sql);


if (!$result) {

    die(
        "Query gagal: " .
        $conn->error
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
Manajemen Ujian - PIRI CBT
</title>


<style>

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    font-family: Arial, sans-serif;

    background: #f2f5f9;

    color: #222;

}


.header {

    background: white;

    padding: 18px;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,.08);

}


.header-inner {

    max-width: 1200px;

    margin: auto;

}


.header strong {

    font-size: 18px;

}


.container {

    max-width: 1200px;

    margin: auto;

    padding: 20px;

}


.card {

    background: white;

    padding: 20px;

    border-radius: 12px;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.06);

}


.atas {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    margin-bottom: 15px;

}


.atas h2 {

    margin: 0;

}


.tombol {

    display: inline-block;

    padding: 9px 12px;

    color: white;

    text-decoration: none;

    border-radius: 7px;

    font-size: 13px;

    font-weight: bold;

    border: none;

    cursor: pointer;

}


.tombol-tambah {

    display: inline-block;

    padding: 11px 16px;

    background: #198754;

    color: white;

    text-decoration: none;

    border-radius: 8px;

    font-weight: bold;

}


.tombol-bank {

    background: #6f42c1;

}


.tombol-edit {

    background: #0d6efd;

}


.tombol-hapus {

    background: #dc3545;

}


.tombol-soal {

    background: #198754;

}


.tabel-container {

    overflow-x: auto;

}


table {

    width: 100%;

    border-collapse: collapse;

    min-width: 1100px;

}


th,
td {

    padding: 12px;

    border-bottom:
        1px solid #ddd;

    text-align: left;

}


th {

    background: #f1f3f5;

}


.status {

    display: inline-block;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;

}


.status-aktif {

    background: #d1e7dd;

    color: #0f5132;

}


.status-nonaktif {

    background: #f8d7da;

    color: #842029;

}


.status-draft {

    background: #fff3cd;

    color: #664d03;

}


.status-selesai {

    background: #e2e3e5;

    color: #41464b;

}


.aksi {

    display: flex;

    flex-wrap: wrap;

    gap: 5px;

}


.kembali {

    display: inline-block;

    margin-top: 15px;

    padding: 10px 15px;

    background: #6c757d;

    color: white;

    text-decoration: none;

    border-radius: 8px;

}


.kosong {

    text-align: center;

    padding: 30px;

    color: #777;

}


@media (max-width: 700px) {

    .container {

        padding: 10px;

    }


    .card {

        padding: 15px;

    }


    .atas {

        flex-direction: column;

        align-items: stretch;

    }


    .tombol-tambah {

        text-align: center;

    }

}

</style>

</head>


<body>


<div class="header">

    <div class="header-inner">

        <strong>
            PIRI CBT — ADMIN
        </strong>

    </div>

</div>


<div class="container">


<div class="card">


<div class="atas">

    <div>

        <h2>
            Manajemen Ujian
        </h2>

        <p>
            Kelola daftar ujian CBT.
        </p>

    </div>


    <div>

        <a
            href="bank-soal.php"
            class="tombol tombol-bank"
        >
            📚 Bank Soal
        </a>


        <a
            href="tambah-ujian.php"
            class="tombol-tambah"
        >
            + Tambah Ujian
        </a>

    </div>

</div>


<div class="tabel-container">

<table>

<thead>

<tr>

<th>No</th>

<th>Nama Ujian</th>

<th>Mata Pelajaran</th>

<th>Kelas</th>

<th>Durasi</th>

<th>Minimal</th>

<th>Token</th>

<th>Status</th>

<th>Aksi</th>

</tr>

</thead>


<tbody>


<?php if ($result->num_rows === 0): ?>

<tr>

<td
    colspan="9"
    class="kosong"
>

Belum ada data ujian.

</td>

</tr>


<?php else: ?>


<?php

$no = 1;

while (
    $row =
    $result->fetch_assoc()
):

?>


<tr>


<td>

<?= $no++ ?>

</td>


<td>

<?= htmlspecialchars(
    $row["nama_ujian"]
) ?>

</td>


<td>

<?= htmlspecialchars(
    $row["nama_mapel"]
    ?? "-"
) ?>

</td>


<td>

<?= htmlspecialchars(
    $row["kelas"]
) ?>

</td>


<td>

<?= (int)$row["durasi"] ?>
menit

</td>


<td>

<?= (int)$row["minimal_menit"] ?>
menit

</td>


<td>

<?= htmlspecialchars(
    $row["token"]
) ?>

</td>


<td>

<span
    class="status status-<?= htmlspecialchars(
        $row["status"]
    ) ?>"
>

<?= strtoupper(
    htmlspecialchars(
        $row["status"]
    )
) ?>

</span>

</td>


<td>

<div class="aksi">


<a
    href="soal.php?ujian_id=<?= (int)$row["id"] ?>"
    class="tombol tombol-soal"
>
    📝 Soal
</a>


<a
    href="edit-ujian.php?id=<?= (int)$row["id"] ?>"
    class="tombol tombol-edit"
>
    ✏️ Edit
</a>


<a
    href="hapus-ujian.php?id=<?= (int)$row["id"] ?>"
    class="tombol tombol-hapus"
    onclick="
        return confirm(
            'Yakin ingin menghapus ujian ini?'
        );
    "
>
    🗑️ Hapus
</a>


</div>

</td>


</tr>


<?php endwhile; ?>


<?php endif; ?>


</tbody>

</table>

</div>


<a
    href="dashboard.php"
    class="kembali"
>

← Kembali ke Dashboard

</a>


</div>

</div>


</body>

</html>