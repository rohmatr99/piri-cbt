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
| CEK ID HASIL
|--------------------------------------------------------------------------
*/

if (
    !isset($_POST["id"]) ||
    !is_numeric($_POST["id"])
) {

    header("Location: hasil-ujian.php");
    exit;

}


$hasil_id = (int) $_POST["id"];


if ($hasil_id <= 0) {

    header("Location: hasil-ujian.php");
    exit;

}


/*
|--------------------------------------------------------------------------
| AMBIL DATA HASIL
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        siswa_id,
        ujian_id,
        sesi_id
    FROM hasil_ujian
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $hasil_id
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows === 0) {

    die("Data hasil ujian tidak ditemukan.");

}


$hasil = $result->fetch_assoc();


$siswa_id = (int) $hasil["siswa_id"];
$ujian_id = (int) $hasil["ujian_id"];
$sesi_id  = (int) $hasil["sesi_id"];


/*
|--------------------------------------------------------------------------
| MULAI TRANSAKSI
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();


try {


    /*
    |--------------------------------------------------------------------------
    | HAPUS PELANGGARAN UJIAN
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        DELETE FROM pelanggaran_ujian
        WHERE sesi_id = ?
    ");

    $stmt->bind_param(
        "i",
        $sesi_id
    );

    $stmt->execute();


    /*
    |--------------------------------------------------------------------------
    | HAPUS JAWABAN
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        DELETE FROM jawaban
        WHERE sesi_id = ?
    ");

    $stmt->bind_param(
        "i",
        $sesi_id
    );

    $stmt->execute();


    /*
    |--------------------------------------------------------------------------
    | HAPUS HASIL UJIAN
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        DELETE FROM hasil_ujian
        WHERE id = ?
    ");

    $stmt->bind_param(
        "i",
        $hasil_id
    );

    $stmt->execute();


    /*
    |--------------------------------------------------------------------------
    | HAPUS SESI UJIAN
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        DELETE FROM sesi_ujian
        WHERE id = ?
    ");

    $stmt->bind_param(
        "i",
        $sesi_id
    );

    $stmt->execute();


    /*
    |--------------------------------------------------------------------------
    | SIMPAN PERUBAHAN
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | KEMBALI KE HASIL UJIAN
    |--------------------------------------------------------------------------
    */

    header("Location: hasil-ujian.php");
    exit;


} catch (Exception $e) {


    /*
    |--------------------------------------------------------------------------
    | BATALKAN JIKA GAGAL
    |--------------------------------------------------------------------------
    */

    $conn->rollback();


    die(
        "Reset ujian gagal: "
        . htmlspecialchars($e->getMessage())
    );

}