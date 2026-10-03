<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($id <= 0) {
    die("ID ujian tidak valid.");
}

/* Pastikan ujian ada */
$stmt = $conn->prepare("
    SELECT id, nama_ujian
    FROM ujian
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$ujian = $stmt->get_result()->fetch_assoc();

if (!$ujian) {
    die("Ujian tidak ditemukan.");
}

/*
|--------------------------------------------------------------------------
| CEK HASIL UJIAN
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS jumlah
    FROM hasil_ujian
    WHERE ujian_id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$hasil = $stmt->get_result()->fetch_assoc();

$jumlahHasil = (int)$hasil["jumlah"];

if ($jumlahHasil > 0) {
    die(
        "<h3>Ujian tidak dapat dihapus.</h3>" .
        "<p>Ujian <strong>" .
        htmlspecialchars($ujian["nama_ujian"]) .
        "</strong> sudah memiliki " .
        $jumlahHasil .
        " hasil ujian.</p>" .
        "<p>Riwayat nilai siswa harus tetap disimpan.</p>" .
        "<p>Jika ujian tidak ingin digunakan lagi, ubah statusnya menjadi <strong>Nonaktif</strong>.</p>" .
        '<p><a href="ujian.php">← Kembali ke daftar ujian</a></p>'
    );
}

/*
|--------------------------------------------------------------------------
| MULAI TRANSAKSI
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    /*
    |--------------------------------------------------------------------------
    | 1. Hapus pelanggaran
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        DELETE p
        FROM pelanggaran_ujian p
        INNER JOIN sesi_ujian su
            ON su.id = p.sesi_id
        WHERE su.ujian_id = ?
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();


    /*
    |--------------------------------------------------------------------------
    | 2. Hapus jawaban
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        DELETE j
        FROM jawaban j
        INNER JOIN sesi_ujian su
            ON su.id = j.sesi_id
        WHERE su.ujian_id = ?
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();


    /*
    |--------------------------------------------------------------------------
    | 3. Hapus sesi ujian
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        DELETE FROM sesi_ujian
        WHERE ujian_id = ?
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();


    /*
    |--------------------------------------------------------------------------
    | 4. Hapus opsi jawaban
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        DELETE os
        FROM opsi_soal os
        INNER JOIN soal s
            ON s.id = os.soal_id
        WHERE s.ujian_id = ?
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();


    /*
    |--------------------------------------------------------------------------
    | 5. Hapus soal
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        DELETE FROM soal
        WHERE ujian_id = ?
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();


    /*
    |--------------------------------------------------------------------------
    | 6. Hapus ujian
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        DELETE FROM ujian
        WHERE id = ?
    ");

    $stmt->bind_param("i", $id);

    if (!$stmt->execute()) {
        throw new Exception(
            "Gagal menghapus ujian: " . $stmt->error
        );
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception(
            "Ujian tidak ditemukan saat proses penghapusan."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SEMUA BERHASIL
    |--------------------------------------------------------------------------
    */

    $conn->commit();

    header("Location: ujian.php");
    exit;

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | JIKA ERROR, BATALKAN SEMUA
    |--------------------------------------------------------------------------
    */

    $conn->rollback();

    die(
        "Ujian gagal dihapus.<br><br>" .
        "Penyebab: " .
        htmlspecialchars($e->getMessage())
    );
}