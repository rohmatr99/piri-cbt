<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}


/* =========================
   PESAN
========================= */

$pesan = "";


/* =========================
   TAMBAH MATA PELAJARAN
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nama = trim($_POST["nama"] ?? "");
    $kode = strtoupper(trim($_POST["kode"] ?? ""));
    $status = trim($_POST["status"] ?? "aktif");


    /* =========================
       VALIDASI
    ========================= */

    if ($nama === "") {

        $pesan = "Nama mata pelajaran wajib diisi.";

    } elseif ($kode === "") {

        $pesan = "Kode mata pelajaran wajib diisi.";

    } elseif (!in_array($status, ["aktif", "nonaktif"], true)) {

        $pesan = "Status mata pelajaran tidak valid.";

    } else {


        /* =========================
           CEK NAMA
        ========================= */

        $stmt = $conn->prepare("
            SELECT id
            FROM mata_pelajaran
            WHERE nama = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $nama);
        $stmt->execute();

        $resultNama = $stmt->get_result();


        if ($resultNama->num_rows > 0) {

            $pesan = "Mata pelajaran tersebut sudah ada.";

        } else {


            /* =========================
               CEK KODE
            ========================= */

            $stmt = $conn->prepare("
                SELECT id
                FROM mata_pelajaran
                WHERE kode = ?
                LIMIT 1
            ");

            $stmt->bind_param("s", $kode);
            $stmt->execute();

            $resultKode = $stmt->get_result();


            if ($resultKode->num_rows > 0) {

                $pesan = "Kode mata pelajaran tersebut sudah digunakan.";

            } else {


                /* =========================
                   SIMPAN
                ========================= */

                $stmt = $conn->prepare("
                    INSERT INTO mata_pelajaran
                    (
                        nama,
                        kode,
                        status
                    )
                    VALUES (?, ?, ?)
                ");

                $stmt->bind_param(
                    "sss",
                    $nama,
                    $kode,
                    $status
                );


                if ($stmt->execute()) {

                    header("Location: mata-pelajaran.php");
                    exit;

                } else {

                    $pesan =
                        "Gagal menyimpan mata pelajaran: " .
                        $stmt->error;
                }
            }
        }
    }
}


/* =========================
   AMBIL DATA
========================= */

$result = $conn->query("
    SELECT
        id,
        nama,
        kode,
        status
    FROM mata_pelajaran
    ORDER BY nama ASC
");

if (!$result) {
    die(
        "Gagal mengambil data: " .
        $conn->error
    );
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Mata Pelajaran - PIRI CBT</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f3f4f6;
    color: #111827;
}

.header {
    background: #2563eb;
    color: white;
    padding: 18px;
    font-size: 20px;
    font-weight: bold;
}

.container {
    max-width: 900px;
    margin: 25px auto;
    padding: 0 15px;
}

.card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,.08);
    margin-bottom: 20px;
}

h2 {
    margin-top: 0;
}

label {
    display: block;
    margin-top: 15px;
    margin-bottom: 7px;
    font-weight: bold;
}

input,
select {
    width: 100%;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 16px;
}

.info {
    margin-top: 6px;
    color: #6b7280;
    font-size: 13px;
}

button,
.tombol {
    display: inline-block;
    padding: 11px 16px;
    margin-top: 20px;
    border: none;
    border-radius: 8px;
    text-decoration: none;
    cursor: pointer;
    font-size: 15px;
}

.simpan {
    background: #16a34a;
    color: white;
}

.kembali {
    background: #6b7280;
    color: white;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 12px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
}

th {
    background: #f9fafb;
}

.pesan {
    padding: 12px;
    background: #fef3c7;
    color: #92400e;
    border-radius: 8px;
    margin-bottom: 15px;
}

.status-aktif {
    color: #15803d;
    font-weight: bold;
}

.status-nonaktif {
    color: #dc2626;
    font-weight: bold;
}

@media (max-width: 600px) {

    .card {
        padding: 15px;
    }

    table {
        font-size: 14px;
    }

    th,
    td {
        padding: 9px 6px;
    }

}

</style>

</head>

<body>

<div class="header">
    PIRI CBT — ADMIN
</div>


<div class="container">


<!-- =========================
     FORM TAMBAH
========================= -->

<div class="card">

    <h2>📚 Tambah Mata Pelajaran</h2>


    <?php if ($pesan !== ""): ?>

        <div class="pesan">
            <?= htmlspecialchars($pesan) ?>
        </div>

    <?php endif; ?>


    <form method="POST">


        <label>
            Nama Mata Pelajaran
        </label>

        <input
            type="text"
            name="nama"
            placeholder="Contoh: Matematika"
            required
        >


        <label>
            Kode Mata Pelajaran
        </label>

        <input
            type="text"
            name="kode"
            placeholder="Contoh: MTK"
            maxlength="20"
            required
        >

        <div class="info">
            Kode harus unik. Contoh: MTK, PAI, BIND, IPS.
        </div>


        <label>
            Status
        </label>

        <select
            name="status"
            required
        >

            <option value="aktif">
                Aktif
            </option>

            <option value="nonaktif">
                Nonaktif
            </option>

        </select>


        <button
            type="submit"
            class="simpan"
        >
            💾 Simpan
        </button>

    </form>

</div>


<!-- =========================
     DAFTAR
========================= -->

<div class="card">

    <h2>📋 Daftar Mata Pelajaran</h2>


    <table>

        <thead>

            <tr>

                <th>
                    No
                </th>

                <th>
                    Mata Pelajaran
                </th>

                <th>
                    Kode
                </th>

                <th>
                    Status
                </th>

            </tr>

        </thead>


        <tbody>

        <?php

        $nomor = 1;

        while ($row = $result->fetch_assoc()):

        ?>

            <tr>

                <td>
                    <?= $nomor++ ?>
                </td>

                <td>
                    <?= htmlspecialchars($row["nama"]) ?>
                </td>

                <td>
                    <?= htmlspecialchars($row["kode"]) ?>
                </td>

                <td>

                    <?php if ($row["status"] === "aktif"): ?>

                        <span class="status-aktif">
                            Aktif
                        </span>

                    <?php else: ?>

                        <span class="status-nonaktif">
                            Nonaktif
                        </span>

                    <?php endif; ?>

                </td>

            </tr>

        <?php endwhile; ?>


        <?php if ($nomor === 1): ?>

            <tr>

                <td colspan="4">
                    Belum ada mata pelajaran.
                </td>

            </tr>

        <?php endif; ?>

        </tbody>

    </table>

</div>


<a
    href="dashboard.php"
    class="tombol kembali"
>
    ← Kembali ke Dashboard
</a>


</div>

</body>

</html>