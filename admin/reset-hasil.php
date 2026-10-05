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

$admin_id = (int)($_SESSION["admin_id"] ?? 0);
$admin_role = $_SESSION["admin_role"] ?? "admin";


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

if ($admin_role === "superadmin") {
    $stmt = $conn->prepare("
        SELECT id, siswa_id, ujian_id, sesi_id
        FROM hasil_ujian
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $hasil_id);
} else {
    $stmt = $conn->prepare("
        SELECT h.id, h.siswa_id, h.ujian_id, h.sesi_id
        FROM hasil_ujian h
        INNER JOIN ujian u ON u.id = h.ujian_id
        INNER JOIN admin_mapel am
            ON am.mapel_id = u.mapel_id
           AND am.admin_id = ?
        WHERE h.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $admin_id, $hasil_id);
}

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