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


$error = "";


/*
|--------------------------------------------------------------------------
| PROSES TAMBAH SISWA
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
        $password === ""
    ) {

        $error = "Semua data wajib diisi.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | CEK USERNAME
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT id
            FROM siswa
            WHERE username = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "s",
            $username
        );

        $stmt->execute();

        $cek = $stmt->get_result();


        if ($cek->num_rows > 0) {

            $error = "Username sudah digunakan.";

        } else {


            /*
            |--------------------------------------------------------------------------
            | SIMPAN SISWA
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare("
                INSERT INTO siswa
                (
                    username,
                    nama,
                    kelas,
                    password,
                    status,
                    created_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    NOW()
                )
            ");


            $stmt->bind_param(
                "sssss",
                $username,
                $nama,
                $kelas,
                $password,
                $status
            );


            if ($stmt->execute()) {

                header(
                    "Location: siswa.php"
                );

                exit;

            } else {

                $error =
                    "Gagal menambahkan siswa: "
                    . $stmt->error;

            }

        }

    }

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
Tambah Siswa - PIRI CBT
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


.tombol {

    margin-top: 20px;

}


.simpan {

    padding: 11px 18px;

    border: none;

    border-radius: 8px;

    background: #198754;

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
Tambah Siswa
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
        $_POST["username"] ?? ""
    ) ?>"
    placeholder="Contoh: 24002"
    required
>


<label>
Nama Siswa
</label>

<input
    type="text"
    name="nama"
    value="<?= htmlspecialchars(
        $_POST["nama"] ?? ""
    ) ?>"
    placeholder="Nama lengkap siswa"
    required
>


<label>
Kelas
</label>

<input
    type="text"
    name="kelas"
    value="<?= htmlspecialchars(
        $_POST["kelas"] ?? ""
    ) ?>"
    placeholder="Contoh: VIII A"
    required
>


<label>
Password
</label>

<input
    type="text"
    name="password"
    placeholder="Password siswa"
    required
>


<label>
Status
</label>

<select name="status">

<option
    value="aktif"
>
Aktif
</option>

<option
    value="nonaktif"
>
Nonaktif
</option>

</select>


<div class="tombol">

<button
    type="submit"
    class="simpan"
>
Simpan Siswa
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