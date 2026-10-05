<?php

session_start();

require_once "../config/database.php";


if (!isset($_SESSION["admin_id"])) {

    header("Location: login.php");

    exit;

}

$admin_id = (int)($_SESSION["admin_id"] ?? 0);
$admin_role = $_SESSION["admin_role"] ?? "admin";


/* =========================
   AMBIL DATA FORM
========================= */

$nama_ujian =
    trim($_POST["nama_ujian"] ?? "");

$mapel_id =
    (int)($_POST["mapel_id"] ?? 0);

$kelas =
    trim($_POST["kelas"] ?? "");

$durasi =
    (int)($_POST["durasi"] ?? 0);

$minimal_menit =
    (int)($_POST["minimal_menit"] ?? 0);

$maks_pelanggaran =
    (int)($_POST["maks_pelanggaran"] ?? 0);

$token =
    trim($_POST["token"] ?? "");

$tanggal_mulai =
    $_POST["tanggal_mulai"] ?? "";

$tanggal_selesai =
    $_POST["tanggal_selesai"] ?? "";

$status =
    trim($_POST["status"] ?? "");


/* =========================
   VALIDASI
========================= */

if (
    $nama_ujian === "" ||
    $mapel_id <= 0 ||
    $kelas === "" ||
    $durasi <= 0 ||
    $minimal_menit < 0 ||
    $minimal_menit > $durasi ||
    $maks_pelanggaran < 0 ||
    $token === "" ||
    $tanggal_mulai === "" ||
    $tanggal_selesai === "" ||
    $status === ""
) {

    die(
        "Data ujian belum lengkap " .
        "atau minimal waktu tidak valid."
    );

}


/* =========================
   CEK MATA PELAJARAN
========================= */

$stmt = $conn->prepare("
    SELECT id
    FROM mata_pelajaran
    WHERE id = ?
    LIMIT 1
");


if (!$stmt) {

    die(
        "Query cek mata pelajaran gagal: " .
        htmlspecialchars($conn->error)
    );

}


$stmt->bind_param(
    "i",
    $mapel_id
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows === 0) {

    die(
        "Mata pelajaran tidak ditemukan."
    );

}


$stmt->close();


/*
|--------------------------------------------------------------------------
| CEK HAK AKSES MAPEL
|--------------------------------------------------------------------------
*/

if ($admin_role !== "superadmin") {

    $stmt = $conn->prepare("
        SELECT id
        FROM admin_mapel
        WHERE admin_id = ?
          AND mapel_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        die(
            "Query cek hak akses mapel gagal: " .
            htmlspecialchars($conn->error)
        );
    }

    $stmt->bind_param(
        "ii",
        $admin_id,
        $mapel_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();

        die("
            <h3>Akses ditolak</h3>
            <p>Anda tidak memiliki akses untuk membuat ujian pada mata pelajaran tersebut.</p>
            <br>
            <a href='tambah-ujian.php'>← Kembali</a>
        ");
    }

    $stmt->close();
}


/* =========================
   CEK TOKEN
========================= */

$stmt = $conn->prepare("
    SELECT id
    FROM ujian
    WHERE token = ?
    LIMIT 1
");


if (!$stmt) {

    die(
        "Query cek token gagal: " .
        htmlspecialchars($conn->error)
    );

}


$stmt->bind_param(
    "s",
    $token
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows > 0) {

    $stmt->close();

    die("
        Token ujian sudah digunakan.
        <br><br>
        Silakan gunakan token lain.
        <br><br>
        <a href='tambah-ujian.php'>
            ← Kembali
        </a>
    ");

}


$stmt->close();


/* =========================
   SIMPAN UJIAN
========================= */

$stmt = $conn->prepare("
    INSERT INTO ujian
    (
        nama_ujian,
        mapel_id,
        kelas,
        durasi,
        minimal_menit,
        maks_pelanggaran,
        token,
        tanggal_mulai,
        tanggal_selesai,
        status
    )
    VALUES
    (
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?
    )
");


/*
   Pastikan prepare berhasil
*/

if (!$stmt) {

    die(
        "Query simpan ujian gagal: " .
        htmlspecialchars($conn->error)
    );

}


/* =========================
   BIND DATA
========================= */

$stmt->bind_param(
    "sisiiissss",
    $nama_ujian,
    $mapel_id,
    $kelas,
    $durasi,
    $minimal_menit,
    $maks_pelanggaran,
    $token,
    $tanggal_mulai,
    $tanggal_selesai,
    $status
);


/* =========================
   EKSEKUSI
========================= */

if (!$stmt->execute()) {

    die(
        "Gagal menyimpan ujian: " .
        htmlspecialchars($stmt->error)
    );

}


$stmt->close();


/* =========================
   BERHASIL
========================= */

header(
    "Location: ujian.php"
);

exit;