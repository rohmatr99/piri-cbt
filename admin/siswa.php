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
| AMBIL FILTER
|--------------------------------------------------------------------------
*/

$cari = trim($_GET["cari"] ?? "");

$kelas_filter = trim($_GET["kelas"] ?? "");


/*
|--------------------------------------------------------------------------
| AMBIL DAFTAR KELAS
|--------------------------------------------------------------------------
*/

$result_kelas = $conn->query("
    SELECT DISTINCT kelas
    FROM siswa
    WHERE kelas IS NOT NULL
      AND kelas != ''
    ORDER BY kelas ASC
");


if (!$result_kelas) {

    die(
        "Gagal mengambil daftar kelas: "
        . $conn->error
    );

}


/*
|--------------------------------------------------------------------------
| QUERY DATA SISWA
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        username,
        nama,
        kelas,
        status,
        created_at
    FROM siswa
    WHERE 1 = 1
";


$params = [];

$types = "";


/*
|--------------------------------------------------------------------------
| FILTER PENCARIAN
|--------------------------------------------------------------------------
*/

if ($cari !== "") {

    $sql .= "
        AND (
            nama LIKE ?
            OR username LIKE ?
        )
    ";

    $kata_cari = "%" . $cari . "%";

    $params[] = $kata_cari;
    $params[] = $kata_cari;

    $types .= "ss";

}


/*
|--------------------------------------------------------------------------
| FILTER KELAS
|--------------------------------------------------------------------------
*/

if ($kelas_filter !== "") {

    $sql .= "
        AND kelas = ?
    ";

    $params[] = $kelas_filter;

    $types .= "s";

}


/*
|--------------------------------------------------------------------------
| URUTKAN DATA
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY kelas ASC, nama ASC
";


/*
|--------------------------------------------------------------------------
| JALANKAN QUERY
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare($sql);


if (!$stmt) {

    die(
        "Gagal menyiapkan query: "
        . $conn->error
    );

}


if (count($params) > 0) {

    $stmt->bind_param(
        $types,
        ...$params
    );

}


$stmt->execute();


$result = $stmt->get_result();


if (!$result) {

    die(
        "Gagal mengambil data siswa: "
        . $stmt->error
    );

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
Data Siswa - PIRI CBT
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

    max-width: 1100px;

    margin: auto;

    padding: 20px;

}


.card {

    background: white;

    padding: 20px;

    border-radius: 12px;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.06);

}


/*
|--------------------------------------------------------------------------
| PENCARIAN DAN FILTER
|--------------------------------------------------------------------------
*/

.pencarian {

    margin-top: 20px;

    padding: 15px;

    background: #f8f9fa;

    border-radius: 10px;

}


.form-cari {

    display: flex;

    gap: 8px;

    flex-wrap: wrap;

}


.form-cari input,
.form-cari select {

    flex: 1;

    min-width: 220px;

    padding: 11px;

    border:
        1px solid #ccc;

    border-radius: 8px;

    font-size: 15px;

    box-sizing: border-box;

}


.btn-cari {

    padding: 11px 18px;

    border: none;

    border-radius: 8px;

    background: #0d6efd;

    color: white;

    cursor: pointer;

    font-size: 15px;

}


.btn-reset {

    display: inline-block;

    padding: 11px 18px;

    background: #6c757d;

    color: white;

    text-decoration: none;

    border-radius: 8px;

    box-sizing: border-box;

}


/*
|--------------------------------------------------------------------------
| TABEL
|--------------------------------------------------------------------------
*/

table {

    width: 100%;

    border-collapse: collapse;

    margin-top: 20px;

}


th,
td {

    padding: 12px;

    border-bottom:
        1px solid #ddd;

    text-align: left;

}


th {

    background: #f1f3f5;

}


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

.status {

    display: inline-block;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 13px;

}


.status.aktif {

    background: #d1e7dd;

    color: #0f5132;

}


.status.nonaktif {

    background: #f8d7da;

    color: #842029;

}


/*
|--------------------------------------------------------------------------
| LAIN-LAIN
|--------------------------------------------------------------------------
*/
.btn-hapus-masal {
    display: inline-block;
    margin-top: 15px;
    padding: 10px 16px;
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 15px;
}

.btn-hapus-masal:hover {
    background: #bb2d3b;
}

.checkbox-siswa {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.pilih-semua {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.area-masal {
    margin-top: 15px;
    padding: 12px;
    background: #fff3cd;
    border-radius: 8px;
}

.kosong {

    text-align: center;

    padding: 30px;

    color: #777;

}


.hasil-filter {

    margin-top: 15px;

    color: #555;

}


.kembali {

    display: inline-block;

    margin-top: 20px;

    padding: 10px 15px;

    background: #6c757d;

    color: white;

    text-decoration: none;

    border-radius: 8px;

}


.logout {

    display: inline-block;

    margin-top: 20px;

    margin-left: 8px;

    padding: 10px 15px;

    background: #dc3545;

    color: white;

    text-decoration: none;

    border-radius: 8px;

}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 700px) {

    .card {

        overflow-x: auto;

    }


    .pencarian {

        overflow: hidden;

    }


    .form-cari {

        display: block;

    }


    .form-cari input,
    .form-cari select {

        width: 100%;

        margin-bottom: 8px;

    }


    .btn-cari,
    .btn-reset {

        display: inline-block;

    }


    table {

        min-width: 800px;

    }

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
Data Siswa
</h2>


<a
    href="tambah-siswa.php"
    style="
        display: inline-block;
        margin-top: 10px;
        padding: 10px 16px;
        background: #198754;
        color: white;
        text-decoration: none;
        border-radius: 8px;
    "
>
    + Tambah Siswa
</a>
<a
    href="import-siswa.php"
    style="
        display: inline-block;
        margin-top: 10px;
        margin-left: 5px;
        padding: 10px 16px;
        background: #0d6efd;
        color: white;
        text-decoration: none;
        border-radius: 8px;
    "
>
    📥 Import Siswa
</a>
<?php if (
    isset($_SESSION["pesan_siswa"])
): ?>

<div
    style="
        margin-top: 15px;
        padding: 12px;
        border-radius: 8px;
        background:
        <?= ($_SESSION["tipe_pesan_siswa"] ?? "") === "error"
            ? "#f8d7da"
            : "#d1e7dd" ?>;
        color:
        <?= ($_SESSION["tipe_pesan_siswa"] ?? "") === "error"
            ? "#842029"
            : "#0f5132" ?>;
    "
>
    <?= htmlspecialchars(
        $_SESSION["pesan_siswa"]
    ) ?>
</div>

<?php

unset(
    $_SESSION["pesan_siswa"]
);

unset(
    $_SESSION["tipe_pesan_siswa"]
);

endif;

?>

<p>

Selamat datang,
<strong>
<?= htmlspecialchars(
    $_SESSION["admin_nama"]
) ?>
</strong>

</p>


<!-- =========================================================
     PENCARIAN DAN FILTER
========================================================= -->

<div class="pencarian">

<form
    method="GET"
    action="siswa.php"
    class="form-cari"
>


<input
    type="text"
    name="cari"
    value="<?= htmlspecialchars($cari) ?>"
    placeholder="Cari nama atau username siswa..."
>


<select name="kelas">

<option value="">
Semua Kelas
</option>


<?php while (
    $kelas =
    $result_kelas->fetch_assoc()
): ?>


<option
    value="<?= htmlspecialchars($kelas["kelas"]) ?>"
    <?= $kelas_filter === $kelas["kelas"]
        ? "selected"
        : ""
    ?>
>

<?= htmlspecialchars(
    $kelas["kelas"]
) ?>

</option>


<?php endwhile; ?>


</select>


<button
    type="submit"
    class="btn-cari"
>
Cari
</button>


<a
    href="siswa.php"
    class="btn-reset"
>
Reset
</a>


</form>


<?php if (
    $cari !== "" ||
    $kelas_filter !== ""
): ?>

<div class="hasil-filter">

Menampilkan hasil

<?php if ($cari !== ""): ?>

pencarian:
<strong>
<?= htmlspecialchars($cari) ?>
</strong>

<?php endif; ?>


<?php if ($kelas_filter !== ""): ?>

kelas:
<strong>
<?= htmlspecialchars($kelas_filter) ?>
</strong>

<?php endif; ?>

</div>

<?php endif; ?>


</div>


<!-- =========================================================
     TABEL DATA SISWA
========================================================= -->


<?php if ($result->num_rows === 0): ?>


<div class="kosong">

<?php if (
    $cari !== "" ||
    $kelas_filter !== ""
): ?>

Data siswa sesuai filter tidak ditemukan.

<?php else: ?>

Belum ada data siswa.

<?php endif; ?>

</div>


<?php else: ?>

<div class="area-masal">

    <label>
        <input
            type="checkbox"
            id="pilihSemua"
            class="pilih-semua"
        >

        <strong>
            Pilih Semua
        </strong>
    </label>

    <button
        type="submit"
        form="formHapusMasal"
        class="btn-hapus-masal"
        onclick="
            return konfirmasiHapusMasal();
        "
    >
        🗑️ Hapus Siswa Terpilih
    </button>

</div>
<form
    method="POST"
    action="hapus-siswa-masal.php"
    id="formHapusMasal"
>
<table>

<thead>

<tr>

<th>
    Pilih
</th>

<th>No</th>

<th>Username</th>

<th>Nama</th>

<th>Kelas</th>

<th>Status</th>

<th>Dibuat</th>

<th>Aksi</th>

</tr>

</thead>


<tbody>


<?php

$no = 1;

while (
    $row =
    $result->fetch_assoc()
):

?>


<tr>

<td>

<input
    type="checkbox"
    name="siswa_id[]"
    value="<?= (int) $row["id"] ?>"
    class="checkbox-siswa"
>

</td>

<td>
<?= $no++ ?>
</td>


<td>

<?= htmlspecialchars(
    $row["username"]
) ?>

</td>


<td>

<?= htmlspecialchars(
    $row["nama"]
) ?>

</td>


<td>

<?= htmlspecialchars(
    $row["kelas"]
) ?>

</td>


<td>

<?php

$status =
    strtolower(
        $row["status"]
    );

?>


<span
    class="status <?= htmlspecialchars($status) ?>"
>

<?= htmlspecialchars(
    $row["status"]
) ?>

</span>

</td>


<td>

<?= htmlspecialchars(
    $row["created_at"]
) ?>

</td>


<td>

<a
    href="edit-siswa.php?id=<?= (int) $row["id"] ?>"
    style="
        display: inline-block;
        padding: 8px 12px;
        background: #0d6efd;
        color: white;
        text-decoration: none;
        border-radius: 6px;
    "
>
    Edit
</a>


<a
    href="hapus-siswa.php?id=<?= (int) $row["id"] ?>"
    onclick="
        return confirm(
            'Yakin ingin menghapus siswa ini?'
        );
    "
    style="
        display: inline-block;
        padding: 8px 12px;
        background: #dc3545;
        color: white;
        text-decoration: none;
        border-radius: 6px;
        margin-left: 5px;
    "
>
    Hapus
</a>

</td>

</tr>


<?php endwhile; ?>


</tbody>

</table>
</form>

<?php endif; ?>


<br>


<a
    class="kembali"
    href="dashboard.php"
>

← Dashboard

</a>


<a
    class="logout"
    href="logout.php"
>

Keluar

</a>


</div>

</div>

<script>

const pilihSemua =
    document.getElementById("pilihSemua");

if (pilihSemua) {

    pilihSemua.addEventListener(
        "change",
        function () {

            const checkbox =
                document.querySelectorAll(
                    ".checkbox-siswa"
                );

            checkbox.forEach(
                function (item) {

                    item.checked =
                        pilihSemua.checked;

                }
            );

        }
    );

}


function konfirmasiHapusMasal() {

    const terpilih =
        document.querySelectorAll(
            ".checkbox-siswa:checked"
        );

    if (terpilih.length === 0) {

        alert(
            "Silakan pilih siswa yang ingin dihapus terlebih dahulu."
        );

        return false;

    }


    return confirm(
        "Yakin ingin menghapus " +
        terpilih.length +
        " siswa yang dipilih?\n\n" +
        "Data hasil ujian, jawaban, sesi ujian, " +
        "dan data siswa tersebut juga akan dihapus."
    );

}

</script>

</body>

</html>