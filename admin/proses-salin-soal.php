<?php

session_start();

require_once "../config/database.php";


/* =========================
   CEK LOGIN
========================= */

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}


$admin_id = (int)($_SESSION["admin_id"] ?? 0);
$admin_role = $_SESSION["admin_role"] ?? "admin";


/* =========================
   AMBIL DATA FORM
========================= */

$soal_id = isset($_POST["soal_id"])
    ? (int)$_POST["soal_id"]
    : 0;

$ujian_tujuan_id = isset($_POST["ujian_tujuan_id"])
    ? (int)$_POST["ujian_tujuan_id"]
    : 0;

$nomor = isset($_POST["nomor"])
    ? (int)$_POST["nomor"]
    : 0;


/* =========================
   VALIDASI
========================= */

if (
    $soal_id <= 0 ||
    $ujian_tujuan_id <= 0 ||
    $nomor <= 0
) {
    die("Data penyalinan tidak lengkap.");
}


/* =========================
   AMBIL SOAL ASAL
   + CEK AKSES
========================= */

if ($admin_role === "superadmin") {

    $stmt = $conn->prepare("
        SELECT
            s.id,
            s.ujian_id,
            s.pertanyaan,
            s.tipe,
            s.bobot
        FROM soal s
        INNER JOIN ujian u
            ON u.id = s.ujian_id
        WHERE s.id = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "i",
        $soal_id
    );

} else {

    $stmt = $conn->prepare("
        SELECT
            s.id,
            s.ujian_id,
            s.pertanyaan,
            s.tipe,
            s.bobot
        FROM soal s
        INNER JOIN ujian u
            ON u.id = s.ujian_id
        INNER JOIN admin_mapel am
            ON am.mapel_id = u.mapel_id
           AND am.admin_id = ?
        WHERE s.id = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "ii",
        $admin_id,
        $soal_id
    );
}

$stmt->execute();

$result = $stmt->get_result();

$soal = $result->fetch_assoc();

$stmt->close();


if (!$soal) {
    die("Soal asal tidak ditemukan atau Anda tidak memiliki akses.");
}


/* =========================
   CEGAH SALIN KE UJIAN SAMA
========================= */

if ((int)$soal["ujian_id"] === $ujian_tujuan_id) {
    die("Soal tidak dapat disalin ke ujian yang sama.");
}


/* =========================
   CEK UJIAN TUJUAN
   + CEK AKSES GURU
========================= */

if ($admin_role === "superadmin") {

    $stmt = $conn->prepare("
        SELECT
            id,
            nama_ujian,
            kelas,
            mapel_id
        FROM ujian
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "i",
        $ujian_tujuan_id
    );

} else {

    $stmt = $conn->prepare("
        SELECT
            u.id,
            u.nama_ujian,
            u.kelas,
            u.mapel_id
        FROM ujian u
        INNER JOIN admin_mapel am
            ON am.mapel_id = u.mapel_id
           AND am.admin_id = ?
        WHERE u.id = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "ii",
        $admin_id,
        $ujian_tujuan_id
    );
}

$stmt->execute();

$result = $stmt->get_result();

$ujianTujuan = $result->fetch_assoc();

$stmt->close();


if (!$ujianTujuan) {
    die("Ujian tujuan tidak ditemukan atau Anda tidak memiliki akses.");
}


/* =========================
   CEK SOAL DUPLIKAT
========================= */

$stmt = $conn->prepare("
    SELECT
        id,
        nomor
    FROM soal
    WHERE ujian_id = ?
      AND pertanyaan = ?
    LIMIT 1
");

$stmt->bind_param(
    "is",
    $ujian_tujuan_id,
    $soal["pertanyaan"]
);

$stmt->execute();

$result = $stmt->get_result();

$soalDuplikat = $result->fetch_assoc();

$stmt->close();


if ($soalDuplikat) {

    $nomorDuplikat = (int)$soalDuplikat["nomor"];

    die("
        <div style='
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 25px;
            border: 1px solid #ddd;
            border-radius: 10px;
            background: #fff;
        '>

            <h2 style='color: #dc2626;'>
                Soal Sudah Ada
            </h2>

            <p>
                Soal dengan pertanyaan yang sama sudah terdapat
                pada ujian tujuan.
            </p>

            <p>
                <strong>Nomor soal:</strong>
                $nomorDuplikat
            </p>

            <br>

            <a
                href='salin-soal.php?id=$soal_id'
                style='
                    display: inline-block;
                    padding: 10px 16px;
                    background: #2563eb;
                    color: white;
                    text-decoration: none;
                    border-radius: 6px;
                '
            >
                ← Kembali
            </a>

        </div>
    ");
}


/* =========================
   CEK NOMOR SUDAH DIPAKAI
========================= */

$stmt = $conn->prepare("
    SELECT id
    FROM soal
    WHERE ujian_id = ?
      AND nomor = ?
    LIMIT 1
");

$stmt->bind_param(
    "ii",
    $ujian_tujuan_id,
    $nomor
);

$stmt->execute();

$result = $stmt->get_result();

$nomorSudahAda = $result->num_rows > 0;

$stmt->close();


if ($nomorSudahAda) {

    die("
        <div style='
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 25px;
            border: 1px solid #ddd;
            border-radius: 10px;
            background: #fff;
        '>

            <h2 style='color: #dc2626;'>
                Nomor Soal Sudah Digunakan
            </h2>

            <p>
                Nomor soal <strong>$nomor</strong>
                sudah digunakan pada ujian tujuan.
            </p>

            <p>
                Silakan kembali dan gunakan nomor lain.
            </p>

            <br>

            <a
                href='salin-soal.php?id=$soal_id'
                style='
                    display: inline-block;
                    padding: 10px 16px;
                    background: #2563eb;
                    color: white;
                    text-decoration: none;
                    border-radius: 6px;
                '
            >
                ← Kembali
            </a>

        </div>
    ");
}


/* =========================
   AMBIL PILIHAN JAWABAN
========================= */

$stmt = $conn->prepare("
    SELECT
        kode,
        teks,
        benar
    FROM opsi_soal
    WHERE soal_id = ?
    ORDER BY kode ASC
");

$stmt->bind_param(
    "i",
    $soal_id
);

$stmt->execute();

$resultOpsi = $stmt->get_result();

$opsi = [];

while ($row = $resultOpsi->fetch_assoc()) {
    $opsi[] = $row;
}

$stmt->close();


if (count($opsi) === 0) {
    die("Pilihan jawaban soal tidak ditemukan.");
}


/* =========================
   MULAI TRANSAKSI
========================= */

$conn->begin_transaction();


try {

    /* =========================
       INSERT SOAL BARU
    ========================= */

    $stmtSoal = $conn->prepare("
        INSERT INTO soal
        (
            ujian_id,
            nomor,
            pertanyaan,
            tipe,
            bobot
        )
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmtSoal->bind_param(
        "iissi",
        $ujian_tujuan_id,
        $nomor,
        $soal["pertanyaan"],
        $soal["tipe"],
        $soal["bobot"]
    );

    $stmtSoal->execute();

    $soalBaruId = $conn->insert_id;

    $stmtSoal->close();


    /* =========================
       INSERT PILIHAN JAWABAN
    ========================= */

    $stmtOpsi = $conn->prepare("
        INSERT INTO opsi_soal
        (
            soal_id,
            kode,
            teks,
            benar
        )
        VALUES (?, ?, ?, ?)
    ");

    foreach ($opsi as $op) {

        $kode = $op["kode"];
        $teks = $op["teks"];
        $benar = (int)$op["benar"];

        $stmtOpsi->bind_param(
            "issi",
            $soalBaruId,
            $kode,
            $teks,
            $benar
        );

        $stmtOpsi->execute();
    }

    $stmtOpsi->close();


    /* =========================
       SIMPAN TRANSAKSI
    ========================= */

    $conn->commit();


    /* =========================
       BERHASIL
    ========================= */

    ?>

    <!DOCTYPE html>
    <html lang="id">

    <head>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

        <title>Salin Soal Berhasil</title>

        <style>

            body {
                margin: 0;
                font-family: Arial, sans-serif;
                background: #f3f4f6;
                color: #111827;
            }

            .box {
                max-width: 600px;
                margin: 60px auto;
                background: white;
                padding: 30px;
                border-radius: 12px;
                box-shadow: 0 2px 10px rgba(0,0,0,.08);
                text-align: center;
            }

            .icon {
                font-size: 55px;
                margin-bottom: 10px;
            }

            h2 {
                color: #16a34a;
                margin-bottom: 15px;
            }

            p {
                line-height: 1.7;
            }

            .tombol {
                display: inline-block;
                padding: 11px 18px;
                margin: 8px 4px 0;
                border-radius: 8px;
                text-decoration: none;
                color: white;
                background: #2563eb;
            }

            .kembali {
                background: #6b7280;
            }

        </style>

    </head>

    <body>

        <div class="box">

            <div class="icon">
                ✅
            </div>

            <h2>
                Soal Berhasil Disalin
            </h2>

            <p>
                Soal berhasil disalin ke:
            </p>

            <p>
                <strong>
                    <?= htmlspecialchars(
                        $ujianTujuan["nama_ujian"]
                    ) ?>
                </strong>
            </p>

            <p>
                Nomor soal:
                <strong>
                    <?= $nomor ?>
                </strong>
            </p>

            <a
                href="soal.php?ujian_id=<?= $ujian_tujuan_id ?>"
                class="tombol"
            >
                📝 Lihat Soal
            </a>

            <a
                href="bank-soal.php"
                class="tombol kembali"
            >
                ← Bank Soal
            </a>

        </div>

    </body>

    </html>

    <?php


} catch (Throwable $e) {

    /* =========================
       BATALKAN TRANSAKSI
    ========================= */

    $conn->rollback();

    die("
        <div style='
            font-family: Arial, sans-serif;
            max-width: 700px;
            margin: 50px auto;
            padding: 25px;
            border: 1px solid #fecaca;
            border-radius: 10px;
            background: #fff;
        '>

            <h2 style='color: #dc2626;'>
                Gagal Menyalin Soal
            </h2>

            <p>
                Terjadi kesalahan saat menyalin soal.
            </p>

            <p>
                <strong>Detail:</strong>
                " . htmlspecialchars($e->getMessage()) . "
            </p>

            <br>

            <a
                href='salin-soal.php?id=$soal_id'
                style='
                    display: inline-block;
                    padding: 10px 16px;
                    background: #2563eb;
                    color: white;
                    text-decoration: none;
                    border-radius: 6px;
                '
            >
                ← Kembali
            </a>

        </div>
    ");
}
?>