<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

$ujian_id = isset($_GET["ujian_id"])
    ? (int) $_GET["ujian_id"]
    : 0;

if ($ujian_id <= 0) {
    die("Ujian tidak valid.");
}


/*
|--------------------------------------------------------------------------
| DATA UJIAN
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        u.id,
        u.nama_ujian,
        u.kelas,
        u.durasi,
        m.nama AS nama_mapel
    FROM ujian u
    LEFT JOIN mata_pelajaran m
        ON u.mapel_id = m.id
    WHERE u.id = ?
    LIMIT 1
");

$stmt->bind_param("i", $ujian_id);
$stmt->execute();

$ujian = $stmt->get_result()->fetch_assoc();

if (!$ujian) {
    die("Data ujian tidak ditemukan.");
}


/*
|--------------------------------------------------------------------------
| DATA SOAL
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        nomor,
        pertanyaan,
        tipe,
        bobot
    FROM soal
    WHERE ujian_id = ?
    ORDER BY nomor ASC
");

$stmt->bind_param("i", $ujian_id);
$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Kelola Soal - PIRI CBT</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f2f5f9;
        }

        .header {
            background: white;
            padding: 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }

        .header-inner {
            max-width: 1100px;
            margin: auto;
        }

        .container {
            max-width: 1100px;
            margin: auto;
            padding: 20px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,.06);
            margin-bottom: 20px;
        }

        .info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .info-item {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
        }

        .info-item strong {
            display: block;
            margin-bottom: 5px;
        }

        .tombol {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 8px;
            color: white;
            text-decoration: none;
            font-weight: bold;
            border: 0;
            cursor: pointer;
        }

        .tambah {
            background: #198754;
        }

        .edit {
            background: #0d6efd;
        }

        .hapus {
            background: #dc3545;
        }

        .kembali {
            background: #6c757d;
        }

        .tabel-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f1f3f5;
        }

        .pertanyaan {
            max-width: 400px;
            line-height: 1.5;
        }

        .opsi {
            line-height: 1.8;
        }

        .kunci {
            font-weight: bold;
            color: #198754;
        }

        .kosong {
            text-align: center;
            padding: 30px;
            color: #777;
        }

        .aksi {
            white-space: nowrap;
        }

        @media (max-width: 700px) {

            .container {
                padding: 10px;
            }

            .info {
                grid-template-columns: 1fr;
            }

        }
		.salin {
    background: #16a34a;
    color: white;
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


    <!-- INFORMASI UJIAN -->

    <div class="card">

        <h2>
            Kelola Soal
        </h2>


        <div class="info">

            <div class="info-item">

                <strong>Ujian</strong>

                <?= htmlspecialchars(
                    $ujian["nama_ujian"]
                ) ?>

            </div>


            <div class="info-item">

                <strong>Mata Pelajaran</strong>

                <?= htmlspecialchars(
                    $ujian["nama_mapel"] ?? "-"
                ) ?>

            </div>


            <div class="info-item">

                <strong>Kelas</strong>

                <?= htmlspecialchars(
                    $ujian["kelas"]
                ) ?>

            </div>

        </div>


        <br>


        <a
            href="tambah-soal.php?ujian_id=<?= $ujian_id ?>"
            class="tombol tambah"
        >
            + Tambah Soal
        </a>


        <a
            href="ujian.php"
            class="tombol kembali"
        >
            ← Daftar Ujian
        </a>

    </div>



    <!-- DAFTAR SOAL -->

    <div class="card">

        <h3>
            Daftar Soal
        </h3>
		<?php if (isset($_GET["salin_berhasil"]) || isset($_GET["salin_duplikat"])): ?>

    <?php
    $jumlahBerhasil = isset($_GET["salin_berhasil"])
        ? (int)$_GET["salin_berhasil"]
        : 0;

    $jumlahDuplikat = isset($_GET["salin_duplikat"])
        ? (int)$_GET["salin_duplikat"]
        : 0;
    ?>

    <?php if ($jumlahBerhasil > 0): ?>

        <div style="
            background: #d1e7dd;
            color: #0f5132;
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #badbcc;
        ">
            ✅ <strong>Berhasil!</strong>
            <?= $jumlahBerhasil ?> soal berhasil disalin.
        </div>

    <?php endif; ?>


    <?php if ($jumlahDuplikat > 0): ?>

        <div style="
            background: #fff3cd;
            color: #664d03;
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #ffecb5;
        ">
            ⚠️ <strong>Soal duplikat dilewati.</strong>
            <?= $jumlahDuplikat ?> soal sudah ada di ujian ini
            dan tidak disalin ulang.
        </div>

    <?php endif; ?>

<?php endif; ?>


        <div class="tabel-container">

            <table>

                <thead>

                    <tr>

                        <th>No</th>

                        <th>Pertanyaan</th>

                        <th>Pilihan</th>

                        <th>Kunci</th>

                        <th>Bobot</th>

                        <th>Aksi</th>

                    </tr>

                </thead>


                <tbody>


                <?php if ($result->num_rows === 0): ?>

                    <tr>

                        <td
                            colspan="6"
                            class="kosong"
                        >

                            Belum ada soal.

                        </td>

                    </tr>


                <?php else: ?>


                    <?php while (
                        $row = $result->fetch_assoc()
                    ): ?>


                        <?php

                        /*
                        |--------------------------------------------------------------------------
                        | AMBIL OPSI JAWABAN
                        |--------------------------------------------------------------------------
                        */

                        $stmtOpsi = $conn->prepare("
                            SELECT
                                id,
                                kode,
                                teks,
                                benar
                            FROM opsi_soal
                            WHERE soal_id = ?
                            ORDER BY kode ASC
                        ");

                        $stmtOpsi->bind_param(
                            "i",
                            $row["id"]
                        );

                        $stmtOpsi->execute();

                        $opsiResult =
                            $stmtOpsi->get_result();


                        /*
                        |--------------------------------------------------------------------------
                        | AMBIL KUNCI JAWABAN
                        |--------------------------------------------------------------------------
                        */

                        $stmtKunci = $conn->prepare("
                            SELECT kode
                            FROM opsi_soal
                            WHERE soal_id = ?
                            AND benar = 1
                            LIMIT 1
                        ");

                        $stmtKunci->bind_param(
                            "i",
                            $row["id"]
                        );

                        $stmtKunci->execute();

                        $kunci =
                            $stmtKunci
                            ->get_result()
                            ->fetch_assoc();

                        ?>


                        <tr>


                            <!-- NOMOR -->

                            <td>

                                <?= (int)$row["nomor"] ?>

                            </td>


                            <!-- PERTANYAAN -->

                            <td class="pertanyaan">

                                <?= nl2br(
                                    htmlspecialchars(
                                        $row["pertanyaan"]
                                    )
                                ) ?>

                            </td>


                            <!-- PILIHAN -->

                            <td class="opsi">

                                <?php while (
                                    $opsi =
                                    $opsiResult->fetch_assoc()
                                ): ?>


                                    <?php if (
                                        (int)$opsi["benar"] === 1
                                    ): ?>

                                        <span class="kunci">

                                            <?= htmlspecialchars(
                                                $opsi["kode"]
                                            ) ?>.

                                            <?= htmlspecialchars(
                                                $opsi["teks"]
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        <?= htmlspecialchars(
                                            $opsi["kode"]
                                        ) ?>.

                                        <?= htmlspecialchars(
                                            $opsi["teks"]
                                        ) ?>

                                    <?php endif; ?>


                                    <br>


                                <?php endwhile; ?>

                            </td>


                            <!-- KUNCI -->

                            <td class="kunci">

                                <?= htmlspecialchars(
                                    $kunci["kode"] ?? "-"
                                ) ?>

                            </td>


                            <!-- BOBOT -->

                            <td>

                                <?= htmlspecialchars(
                                    $row["bobot"]
                                ) ?>

                            </td>


                            <!-- AKSI -->

                            <td class="aksi">


                                <!-- EDIT -->

                                <a href="edit-soal.php?id=<?= (int)$row["id"] ?>&ujian_id=<?= (int)$ujian_id ?>" class="tombol edit">
    ✏️ Edit
</a>

<a href="salin-soal.php?id=<?= (int)$row["id"] ?>" class="tombol salin">
    📋 Salin
</a>

<a href="hapus-soal.php?id=<?= (int)$row["id"] ?>&ujian_id=<?= (int)$ujian_id ?>" class="tombol hapus" onclick="return confirm('Hapus soal ini?')">
    🗑️ Hapus
</a>


                            </td>


                        </tr>


                    <?php endwhile; ?>


                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </div>


</div>


</body>

</html>