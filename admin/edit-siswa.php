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


$id = (int) $_GET["id"];


if ($id <= 0) {

    header("Location: siswa.php");
    exit;

}


$error = "";


/*
|--------------------------------------------------------------------------
| AMBIL DATA SISWA
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        username,
        nama,
        kelas,
        password,
        status
    FROM siswa
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $id
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows === 0) {

    die("Data siswa tidak ditemukan.");

}


$siswa = $result->fetch_assoc();


/*
|--------------------------------------------------------------------------
| PROSES UPDATE
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $nama     = trim($_POST["nama"] ?? "");
    $kelas    = trim($_POST["kelas"] ?? "");
    $password = trim($_POST["password"] ?? "");
    $status   = trim($_POST["status"] ?? "aktif");


    /*
    |--------------------------------------------------------------------------
    | VALIDASI
    |--------------------------------------------------------------------------
    */

    if (
        $username === "" ||
        $nama === "" ||
        $kelas === "" ||
        $status === ""
    ) {

        $error = "Username, nama, kelas, dan status wajib diisi.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | CEK USERNAME MILIK SISWA LAIN
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT id
            FROM siswa
            WHERE username = ?
              AND id != ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "si",
            $username,
            $id
        );

        $stmt->execute();

        $cek = $stmt->get_result();


        if ($cek->num_rows > 0) {

            $error = "Username sudah digunakan oleh siswa lain.";

        } else {


            /*
            |--------------------------------------------------------------------------
            | UPDATE DENGAN PASSWORD BARU
            |--------------------------------------------------------------------------
            */

            if ($password !== "") {

                $stmt = $conn->prepare("
                    UPDATE siswa
                    SET
                        username = ?,
                        nama = ?,
                        kelas = ?,
                        password = ?,
                        status = ?
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    "sssssi",
                    $username,
                    $nama,
                    $kelas,
                    $password,
                    $status,
                    $id
                );


            } else {


                /*
                |--------------------------------------------------------------------------
                | UPDATE TANPA MENGUBAH PASSWORD
                |--------------------------------------------------------------------------
                */

                $stmt = $conn->prepare("
                    UPDATE siswa
                    SET
                        username = ?,
                        nama = ?,
                        kelas = ?,
                        status = ?
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    "ssssi",
                    $username,
                    $nama,
                    $kelas,
                    $status,
                    $id
                );

            }


            /*
            |--------------------------------------------------------------------------
            | SIMPAN UPDATE
            |--------------------------------------------------------------------------
            */

            if ($stmt->execute()) {

                header(
                    "Location: siswa.php"
                );

                exit;

            } else {

                $error =
                    "Gagal mengubah data siswa: "
                    . $stmt->error;

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | JIKA ADA ERROR, TAMPILKAN DATA YANG DIINPUT
    |--------------------------------------------------------------------------
    */

    $siswa["username"] = $username;
    $siswa["nama"]     = $nama;
    $siswa["kelas"]    = $kelas;
    $siswa["status"]   = $status;

}

?>


<!DOCTYPE html>

<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
Edit Siswa - PIRI CBT
</title>


<style>

body {

    margin: 0;

    font-family: Arial, sans-serif;

    background: #f2f5f9;

}


.header {

    background: white;

    padding: 18px;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,.08);

}


.container {

    max-width: 700px;

    margin: auto;

    padding: 20px;

}


.card {

    background: white;

    padding: 25px;

    border-radius: 12px;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.06);

}


label {

    display: block;

    margin-top: 15px;

    margin-bottom: 6px;

    font-weight: bold;

}


input,
select {

    width: 100%;

    box-sizing: border-box;

    padding: 11px;

    border:
        1px solid #ccc;

    border-radius: 8px;

    font-size: 15px;

}


.error {

    padding: 12px;

    margin-bottom: 15px;

    background: #f8d7da;

    color: #842029;

    border-radius: 8px;

}


.info {

    margin-top: 6px;

    font-size: 13px;

    color: #777;

}


.tombol {

    margin-top: 20px;

}


.simpan {

    padding: 11px 18px;

    border: none;

    border-radius: 8px;

    background: #0d6efd;

    color: white;

    cursor: pointer;

    font-size: 15px;

}


.kembali {

    display: inline-block;

    margin-left: 8px;

    padding: 10px 16px;

    background: #6c757d;

    color: white;

    text-decoration: none;

    border-radius: 8px;

}

</style>

</head>


<body>


<div class="header">

<strong>
PIRI CBT — ADMIN
</strong>

</div>


<div class="container">


<div class="card">


<h2>
Edit Siswa
</h2>


<?php if ($error !== ""): ?>

<div class="error">

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>


<form
    method="POST"
    action=""
>


<label>
Username
</label>

<input
    type="text"
    name="username"
    value="<?= htmlspecialchars(
        $siswa["username"]
    ) ?>"
    required
>


<label>
Nama Siswa
</label>

<input
    type="text"
    name="nama"
    value="<?= htmlspecialchars(
        $siswa["nama"]
    ) ?>"
    required
>


<label>
Kelas
</label>

<input
    type="text"
    name="kelas"
    value="<?= htmlspecialchars(
        $siswa["kelas"]
    ) ?>"
    required
>


<label>
Password
</label>

<input
    type="text"
    name="password"
    value=""
    placeholder="Kosongkan jika tidak ingin mengubah password"
>

<div class="info">

Kosongkan password jika password siswa tidak ingin diubah.

</div>


<label>
Status
</label>

<select name="status">

<option
    value="aktif"
    <?= $siswa["status"] === "aktif"
        ? "selected"
        : ""
    ?>
>
Aktif
</option>


<option
    value="nonaktif"
    <?= $siswa["status"] === "nonaktif"
        ? "selected"
        : ""
    ?>
>
Nonaktif
</option>

</select>


<div class="tombol">

<button
    type="submit"
    class="simpan"
>
Simpan Perubahan
</button>


<a
    href="siswa.php"
    class="kembali"
>
Kembali
</a>

</div>


</form>


</div>

</div>


</body>

</html>