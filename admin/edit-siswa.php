<?php

require __DIR__ . "/includes/auth.php";
require_once "../config/database.php";


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

        $error =
            "Username, nama, kelas, dan status wajib diisi.";

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

            $error =
                "Username sudah digunakan oleh siswa lain.";

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


$page_title = "Edit Siswa";
$page_description = "Mengubah data siswa";


require __DIR__ . "/includes/header.php";
require __DIR__ . "/includes/sidebar.php";

?>

<main class="main">

    <header class="topbar">

        <div class="page-title">

            <h1>
                Edit Siswa
            </h1>

            <p>
                Mengubah data siswa
            </p>

        </div>


        <div class="top-user">

            <div class="top-user-icon">
                👤
            </div>

            <span>
                <?= htmlspecialchars($_SESSION["admin_nama"]) ?>
            </span>

        </div>

    </header>


    <header class="mobile-header">

        <button
            type="button"
            class="menu-button"
            onclick="bukaSidebar()"
        >
            ☰
        </button>

        <div class="mobile-title">
            Edit Siswa
        </div>

        <div style="width:40px;"></div>

    </header>


    <section class="content">

        <div class="form-card">

            <div class="form-header">

                <div>

                    <h2>
                        ✏️ Edit Siswa
                    </h2>

                    <p>
                        Silakan ubah data siswa di bawah ini.
                    </p>

                </div>

            </div>


            <?php if ($error !== ""): ?>

                <div class="error">

                    <?= htmlspecialchars($error) ?>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                action=""
            >


                <div class="form-group">

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

                </div>


                <div class="form-group">

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

                </div>


                <div class="form-group">

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

                </div>


                <div class="form-group">

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

                </div>


                <div class="form-group">

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

                </div>


                <div class="tombol-area">

                    <button
                        type="submit"
                        class="simpan"
                    >
                        💾 Simpan Perubahan
                    </button>


                    <a
                        href="siswa.php"
                        class="kembali"
                    >
                        ← Kembali
                    </a>

                </div>


            </form>

        </div>

    </section>

</main>


<style>

.form-card {
    width: 100%;
    max-width: 760px;
    margin: 0 auto;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, .06);
}

.form-header {
    padding-bottom: 20px;
    margin-bottom: 20px;
    border-bottom: 1px solid var(--border);
}

.form-header h2 {
    margin: 0;
    font-size: 21px;
    color: var(--text);
}

.form-header p {
    margin: 6px 0 0;
    color: var(--muted);
    font-size: 13px;
}

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    margin-bottom: 7px;
    font-size: 13px;
    font-weight: 700;
    color: #334155;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px 13px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: white;
    color: var(--text);
    font-size: 14px;
    outline: none;
    transition: .18s ease;
}

.form-group input:focus,
.form-group select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .10);
}

.info {
    margin-top: 7px;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.5;
}

.error {
    margin-bottom: 20px;
    padding: 12px 14px;
    border-radius: 9px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
    font-size: 13px;
}

.tombol-area {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
}

.simpan,
.kembali {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 10px 16px;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
}

.simpan {
    border: none;
    background: var(--primary);
    color: white;
}

.simpan:hover {
    background: var(--primary-dark);
}

.kembali {
    background: #64748b;
    color: white;
}

.kembali:hover {
    background: #475569;
}


@media (max-width: 600px) {

    .form-card {
        padding: 20px 16px;
        border-radius: 12px;
    }

    .form-header h2 {
        font-size: 18px;
    }

    .tombol-area {
        flex-direction: column;
        align-items: stretch;
    }

    .simpan,
    .kembali {
        width: 100%;
    }

}

</style>


<?php

require __DIR__ . "/includes/footer.php";

?>