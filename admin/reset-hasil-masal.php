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
        h.id,
        h.sesi_id
    FROM hasil_ujian h
    INNER JOIN ujian u
        ON u.id = h.ujian_id
";

if ($admin_role !== "superadmin") {
    $sql .= "
        INNER JOIN admin_mapel am
            ON am.mapel_id = u.mapel_id
           AND am.admin_id = ?
    ";
}

$sql .= "
    WHERE h.id IN ($placeholder)
";

if ($admin_role !== "superadmin") {

    $types = "i" . str_repeat("i", count($ids));

    $params = array_merge(
        [$admin_id],
        $ids
    );

} else {

    $types = str_repeat("i", count($ids));

    $params = $ids;
}

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    $types,
    ...$params
);

$stmt->execute();

$result = $stmt->get_result();

$sesi_ids = [];

$authorized_ids = [];

while ($row = $result->fetch_assoc()) {

    $authorized_ids[] = (int) $row["id"];

    $sesi_id = (int) $row["sesi_id"];

    if ($sesi_id > 0) {
        $sesi_ids[] = $sesi_id;
    }
}

if ($admin_role !== "superadmin") {

    $ids = $authorized_ids;

    if (empty($ids)) {
        throw new Exception(
            "Tidak ada hasil ujian yang dapat direset."
        );
    }

    $placeholder = implode(
        ",",
        array_fill(0, count($ids), "?")
    );

    $types = str_repeat(
        "i",
        count($ids)
    );
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