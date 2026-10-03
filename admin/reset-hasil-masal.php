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
| CEK DATA YANG DIPILIH
|--------------------------------------------------------------------------
*/

if (
    !isset($_POST["hasil_id"]) ||
    !is_array($_POST["hasil_id"])
) {

    header("Location: hasil-ujian.php");
    exit;

}


$hasil_ids = $_POST["hasil_id"];


/*
|--------------------------------------------------------------------------
| BERSIHKAN ID
|--------------------------------------------------------------------------
*/

$ids = [];

foreach ($hasil_ids as $id) {

    if (is_numeric($id)) {

        $id = (int) $id;

        if ($id > 0) {

            $ids[] = $id;

        }

    }

}


/*
|--------------------------------------------------------------------------
| JIKA TIDAK ADA ID VALID
|--------------------------------------------------------------------------
*/

if (empty($ids)) {

    header("Location: hasil-ujian.php");
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
    | AMBIL DATA HASIL
    |--------------------------------------------------------------------------
    */

    $placeholder = implode(
        ",",
        array_fill(0, count($ids), "?")
    );

    $types = str_repeat(
        "i",
        count($ids)
    );


    $sql = "
        SELECT
            id,
            sesi_id
        FROM hasil_ujian
        WHERE id IN ($placeholder)
    ";


    $stmt = $conn->prepare($sql);


    $stmt->bind_param(
        $types,
        ...$ids
    );


    $stmt->execute();


    $result = $stmt->get_result();


    $sesi_ids = [];


    while ($row = $result->fetch_assoc()) {

        $sesi_id = (int) $row["sesi_id"];

        if ($sesi_id > 0) {

            $sesi_ids[] = $sesi_id;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | HAPUS PELANGGARAN
    |--------------------------------------------------------------------------
    */

    if (!empty($sesi_ids)) {

        $placeholder_sesi = implode(
            ",",
            array_fill(
                0,
                count($sesi_ids),
                "?"
            )
        );

        $types_sesi = str_repeat(
            "i",
            count($sesi_ids)
        );


        $stmt = $conn->prepare("
            DELETE FROM pelanggaran_ujian
            WHERE sesi_id IN ($placeholder_sesi)
        ");


        $stmt->bind_param(
            $types_sesi,
            ...$sesi_ids
        );


        $stmt->execute();

    }


    /*
    |--------------------------------------------------------------------------
    | HAPUS JAWABAN
    |--------------------------------------------------------------------------
    */

    if (!empty($sesi_ids)) {

        $stmt = $conn->prepare("
            DELETE FROM jawaban
            WHERE sesi_id IN ($placeholder_sesi)
        ");


        $stmt->bind_param(
            $types_sesi,
            ...$sesi_ids
        );


        $stmt->execute();

    }


    /*
    |--------------------------------------------------------------------------
    | HAPUS HASIL
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        DELETE FROM hasil_ujian
        WHERE id IN ($placeholder)
    ");


    $stmt->bind_param(
        $types,
        ...$ids
    );


    $stmt->execute();


    /*
    |--------------------------------------------------------------------------
    | HAPUS SESI UJIAN
    |--------------------------------------------------------------------------
    */

    if (!empty($sesi_ids)) {

        $stmt = $conn->prepare("
            DELETE FROM sesi_ujian
            WHERE id IN ($placeholder_sesi)
        ");


        $stmt->bind_param(
            $types_sesi,
            ...$sesi_ids
        );


        $stmt->execute();

    }


    /*
    |--------------------------------------------------------------------------
    | SIMPAN
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | KEMBALI
    |--------------------------------------------------------------------------
    */

    header("Location: hasil-ujian.php");
    exit;


} catch (Exception $e) {


    /*
    |--------------------------------------------------------------------------
    | BATALKAN
    |--------------------------------------------------------------------------
    */

    $conn->rollback();


    die(
        "Reset hasil massal gagal: "
        . htmlspecialchars(
            $e->getMessage()
        )
    );

}