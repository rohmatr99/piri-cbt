<?php

require __DIR__ . "/includes/auth.php";
require_once "../config/database.php";

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


/*
|--------------------------------------------------------------------------
| PAGE LAYOUT
|--------------------------------------------------------------------------
*/

$page_title = "Tambah Siswa";
$page_description = "Menambahkan data siswa baru";

require __DIR__ . "/includes/header.php";
require __DIR__ . "/includes/sidebar.php";

?>

<style>

/*
|--------------------------------------------------------------------------
| Tambah Siswa
|--------------------------------------------------------------------------
*/

.tambah-siswa-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 22px;
}

.tambah-siswa-heading h2 {
    margin: 0;
    font-size: 22px;
    color: #0f172a;
}

.tambah-siswa-heading p {
    margin: 6px 0 0;
    color: #64748b;
    font-size: 13px;
}

.tambah-siswa-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 10px 14px;
    border-radius: 9px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    color: #334155;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    transition: .18s ease;
}

.tambah-siswa-back:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}


/*
|--------------------------------------------------------------------------
| Card
|--------------------------------------------------------------------------
*/

.tambah-siswa-card {
    width: 100%;
    max-width: 760px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow:
        0 3px 12px rgba(15, 23, 42, .04);
    overflow: hidden;
}

.tambah-siswa-card-header {
    padding: 18px 22px;
    border-bottom: 1px solid #e2e8f0;
}

.tambah-siswa-card-header h3 {
    margin: 0;
    font-size: 15px;
    color: #0f172a;
}

.tambah-siswa-card-header p {
    margin: 5px 0 0;
    color: #64748b;
    font-size: 12px;
}

.tambah-siswa-form {
    padding: 22px;
}


/*
|--------------------------------------------------------------------------
| Error
|--------------------------------------------------------------------------
*/

.tambah-siswa-error {
    display: flex;
    align-items: flex-start;
    gap: 9px;

    padding: 12px 14px;
    margin-bottom: 20px;

    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 9px;

    color: #b91c1c;
    font-size: 13px;
    line-height: 1.5;
}


/*
|--------------------------------------------------------------------------
| Form
|--------------------------------------------------------------------------
*/

.tambah-siswa-field {
    margin-bottom: 18px;
}

.tambah-siswa-field label {
    display: block;
    margin-bottom: 7px;

    color: #334155;

    font-size: 12px;
    font-weight: 700;
}

.tambah-siswa-field input,
.tambah-siswa-field select {
    width: 100%;
    min-height: 43px;

    padding: 9px 12px;

    border: 1px solid #cbd5e1;
    border-radius: 9px;

    background: #ffffff;

    color: #0f172a;

    font-family: inherit;
    font-size: 13px;

    outline: none;

    transition: .18s ease;
}

.tambah-siswa-field input::placeholder {
    color: #94a3b8;
}

.tambah-siswa-field input:focus,
.tambah-siswa-field select:focus {
    border-color: #2563eb;

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.10);
}


/*
|--------------------------------------------------------------------------
| Form Grid
|--------------------------------------------------------------------------
*/

.tambah-siswa-grid {
    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 0 16px;
}

.tambah-siswa-grid .tambah-siswa-field {
    min-width: 0;
}


/*
|--------------------------------------------------------------------------
| Tombol
|--------------------------------------------------------------------------
*/

.tambah-siswa-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;

    gap: 9px;

    padding-top: 5px;
}

.tambah-siswa-btn {
    min-height: 42px;

    padding: 9px 16px;

    border-radius: 9px;

    font-family: inherit;

    font-size: 13px;
    font-weight: 700;

    text-decoration: none;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    cursor: pointer;

    transition: .18s ease;
}

.tambah-siswa-save {
    border: 0;

    background: #16a34a;

    color: #ffffff;
}

.tambah-siswa-save:hover {
    background: #15803d;
}

.tambah-siswa-cancel {
    border: 1px solid #e2e8f0;

    background: #f8fafc;

    color: #475569;
}

.tambah-siswa-cancel:hover {
    background: #e2e8f0;
}


/*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

@media (max-width: 760px) {

    .tambah-siswa-header {
        flex-direction: column;
        gap: 14px;
    }

    .tambah-siswa-back {
        width: 100%;
    }

    .tambah-siswa-card {
        max-width: none;
    }

    .tambah-siswa-grid {
        grid-template-columns: 1fr;
        gap: 0;
    }

}

@media (max-width: 480px) {

    .tambah-siswa-form {
        padding: 18px 16px;
    }

    .tambah-siswa-card-header {
        padding: 16px;
    }

    .tambah-siswa-actions {
        flex-direction: column-reverse;
        align-items: stretch;
    }

    .tambah-siswa-btn {
        width: 100%;
    }

}

</style>


<main class="main">

    <!-- Desktop topbar -->
    <div class="topbar">

        <div class="page-title">

            <h1>
                Tambah Siswa
            </h1>

            <p>
                Menambahkan data siswa baru
            </p>

        </div>

        <div class="top-user">

            <div class="top-user-icon">
                👤
            </div>

            <span>
                <?= htmlspecialchars(
                    $_SESSION["admin_nama"] ?? "Administrator"
                ) ?>
            </span>

        </div>

    </div>


    <!-- Mobile header -->
    <div class="mobile-header">

        <button
            type="button"
            class="menu-button"
            onclick="bukaSidebar()"
        >
            ☰
        </button>

        <div class="mobile-title">
            Tambah Siswa
        </div>

        <div style="width:40px;"></div>

    </div>


    <section class="content">


        <!-- Page heading -->
        <div class="tambah-siswa-header">

            <div class="tambah-siswa-heading">

                <h2>
                    Tambah Siswa
                </h2>

                <p>
                    Isi data siswa yang akan ditambahkan
                    ke sistem PIRI CBT.
                </p>

            </div>

        

        </div>


        <!-- Form Card -->
        <div class="tambah-siswa-card">

            <div class="tambah-siswa-card-header">

                <h3>
                    Data Siswa
                </h3>

                <p>
                    Semua data yang bertanda wajib harus diisi.
                </p>

            </div>


            <form
                method="POST"
                action=""
                class="tambah-siswa-form"
            >


                <?php if ($error !== ""): ?>

                    <div class="tambah-siswa-error">

                        <span>⚠️</span>

                        <span>
                            <?= htmlspecialchars($error) ?>
                        </span>

                    </div>

                <?php endif; ?>


                <div class="tambah-siswa-grid">


                    <!-- Username -->
                    <div class="tambah-siswa-field">

                        <label for="username">
                            Username
                        </label>

                        <input
                            type="text"
                            name="username"
                            id="username"
                            value="<?= htmlspecialchars(
                                $_POST["username"] ?? ""
                            ) ?>"
                            placeholder="Contoh: 24002"
                            autocomplete="off"
                            required
                        >

                    </div>


                    <!-- Nama -->
                    <div class="tambah-siswa-field">

                        <label for="nama">
                            Nama Siswa
                        </label>

                        <input
                            type="text"
                            name="nama"
                            id="nama"
                            value="<?= htmlspecialchars(
                                $_POST["nama"] ?? ""
                            ) ?>"
                            placeholder="Nama lengkap siswa"
                            required
                        >

                    </div>


                    <!-- Kelas -->
                    <div class="tambah-siswa-field">

                        <label for="kelas">
                            Kelas
                        </label>

                        <input
                            type="text"
                            name="kelas"
                            id="kelas"
                            value="<?= htmlspecialchars(
                                $_POST["kelas"] ?? ""
                            ) ?>"
                            placeholder="Contoh: VIII A"
                            required
                        >

                    </div>


                    <!-- Password -->
                    <div class="tambah-siswa-field">

                        <label for="password">
                            Password
                        </label>

                        <input
                            type="text"
                            name="password"
                            id="password"
                            placeholder="Password siswa"
                            required
                        >

                    </div>


                    <!-- Status -->
                    <div class="tambah-siswa-field">

                        <label for="status">
                            Status
                        </label>

                        <select
                            name="status"
                            id="status"
                        >

                            <option
                                value="aktif"
                                <?= (
                                    ($_POST["status"] ?? "aktif")
                                    === "aktif"
                                )
                                    ? "selected"
                                    : ""
                                ?>
                            >
                                Aktif
                            </option>

                            <option
                                value="nonaktif"
                                <?= (
                                    ($_POST["status"] ?? "")
                                    === "nonaktif"
                                )
                                    ? "selected"
                                    : ""
                                ?>
                            >
                                Nonaktif
                            </option>

                        </select>

                    </div>


                </div>


                <!-- Actions -->
                <div class="tambah-siswa-actions">

                    <a
                        href="siswa.php"
                        class="tambah-siswa-btn tambah-siswa-cancel"
                    >
                        Batal
                    </a>

                    <button
                        type="submit"
                        class="tambah-siswa-btn tambah-siswa-save"
                    >
                        ✓ Simpan Siswa
                    </button>

                </div>


            </form>

        </div>


    </section>

</main>


<?php

require __DIR__ . "/includes/footer.php";