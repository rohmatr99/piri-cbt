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
| CEK ID SISWA
|--------------------------------------------------------------------------
*/

if (
    !isset($_GET["id"]) ||
    !is_numeric($_GET["id"])
) {

    header("Location: siswa.php");
    exit;

}


$siswa_id = (int) $_GET["id"];


if ($siswa_id <= 0) {

    header("Location: siswa.php");
    exit;

}


/*
|--------------------------------------------------------------------------
| CEK DATA SISWA
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT id
    FROM siswa
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $siswa_id
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows === 0) {

    header("Location: siswa.php");
    exit;

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
    | AMBIL SEMUA SESI SISWA
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT id
        FROM sesi_ujian
        WHERE siswa_id = ?
    ");

    $stmt->bind_param(
        "i",
        $siswa_id
    );

    $stmt->execute();

    $result_sesi = $stmt->get_result();


    $sesi_ids = [];


    while (
        $sesi = $result_sesi->fetch_assoc()
    ) {

        $sesi_ids[] =
            (int) $sesi["id"];

    }


    /*
    |--------------------------------------------------------------------------
    | HAPUS HASIL UJIAN
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        DELETE FROM hasil_ujian
        WHERE siswa_id = ?
    ");

    $stmt->bind_param(
        "i",
        $siswa_id
    );

    $stmt->execute();


    /*
    |--------------------------------------------------------------------------
    | HAPUS JAWABAN
    |--------------------------------------------------------------------------
    */

    if (count($sesi_ids) > 0) {

        $stmt = $conn->prepare("
            DELETE FROM jawaban
            WHERE siswa_id = ?
        ");

        $stmt->bind_param(
            "i",
            $siswa_id
        );

        $stmt->execute();

    }


    /*
    |--------------------------------------------------------------------------
    | HAPUS SESI UJIAN
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        DELETE FROM sesi_ujian
        WHERE siswa_id = ?
    ");

    $stmt->bind_param(
        "i",
        $siswa_id
    );

    $stmt->execute();


    /*
    |--------------------------------------------------------------------------
    | HAPUS DATA SISWA
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        DELETE FROM siswa
        WHERE id = ?
    ");

    $stmt->bind_param(
        "i",
        $siswa_id
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
    | KEMBALI KE DATA SISWA
    |--------------------------------------------------------------------------
    */

    header("Location: siswa.php");
    exit;


} catch (Exception $e) {


    /*
    |--------------------------------------------------------------------------
    | BATALKAN SEMUA PERUBAHAN
    |--------------------------------------------------------------------------
    */

    $conn->rollback();


    die(
        "Gagal menghapus siswa: "
        . htmlspecialchars(
            $e->getMessage()
        )
    );

}