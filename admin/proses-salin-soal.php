<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}


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

if ($soal_id <= 0 || $ujian_tujuan_id <= 0 || $nomor <= 0) {
    die("Data penyalinan tidak lengkap.");
}


/* =========================
   AMBIL SOAL ASAL
========================= */

$stmt = $conn->prepare("
    SELECT
        id,
        ujian_id,
        pertanyaan,
        tipe,
        bobot
    FROM soal
    WHERE id = ?
");

$stmt->bind_param("i", $soal_id);
$stmt->execute();

$result = $stmt->get_result();

$soal = $result->fetch_assoc();

if (!$soal) {
    die("Soal asal tidak ditemukan.");
}


/* =========================
   CEGAH SALIN KE UJIAN YANG SAMA
========================= */

if ((int)$soal["ujian_id"] === $ujian_tujuan_id) {
    die("Soal tidak dapat disalin ke ujian yang sama.");
}


/* =========================
   CEK UJIAN TUJUAN
========================= */

$stmt = $conn->prepare("
    SELECT id, nama_ujian, kelas
    FROM ujian
    WHERE id = ?
");

$stmt->bind_param("i", $ujian_tujuan_id);
$stmt->execute();

$result = $stmt->get_result();

$ujianTujuan = $result->fetch_assoc();

if (!$ujianTujuan) {
    die("Ujian tujuan tidak ditemukan.");
}


/* =========================
   CEK SOAL DUPLIKAT
========================= */

$stmt = $conn->prepare("
    SELECT id, nomor
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

if ($result->num_rows > 0) {
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

$stmt->bind_param("i", $soal_id);
$stmt->execute();

$resultOpsi = $stmt->get_result();

$opsi = [];

while ($row = $resultOpsi->fetch_assoc()) {
    $opsi[] = $row;
}

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


    /* =========================
       INSERT PILIHAN JAWABAN
    ========================= */

    $stmtOpsi = $conn->prepare("
        INSERT INTO opsi_soal
        (
            soal_id,
            kode,
            teks,