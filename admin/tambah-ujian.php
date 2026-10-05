<?php

require_once "includes/auth.php";
require_once "../config/database.php";

$page_title = "Tambah Ujian";
$page_description = "Membuat ujian baru";

/*
|--------------------------------------------------------------------------
| Ambil data mata pelajaran sesuai hak akses
|--------------------------------------------------------------------------
*/

$admin_id   = (int)($_SESSION["admin_id"] ?? 0);
$admin_role = $_SESSION["admin_role"] ?? "admin";

if ($admin_role === "superadmin") {

    // Superadmin boleh melihat semua mapel
    $resultMapel = $conn->query("
        SELECT id, nama
        FROM mata_pelajaran
        WHERE status = 'aktif'
        ORDER BY nama ASC
    ");

} else {

    // Admin/Guru hanya boleh melihat mapel yang ditugaskan
    $stmtMapel = $conn->prepare("
        SELECT mp.id, mp.nama
        FROM mata_pelajaran mp
        INNER JOIN admin_mapel am
            ON am.mapel_id = mp.id
        WHERE am.admin_id = ?
          AND mp.status = 'aktif'
        ORDER BY mp.nama ASC
    ");

    $stmtMapel->bind_param("i", $admin_id);
    $stmtMapel->execute();

    $resultMapel = $stmtMapel->get_result();
}

if (!$resultMapel) {
    die("Gagal mengambil mata pelajaran: " . $conn->error);
}

?>

<?php require_once "includes/header.php"; ?>
<?php require_once "includes/sidebar.php"; ?>

<style>

/* =========================
   TAMBAH UJIAN
========================= */

.form-wrapper {
    width: 100%;
    max-width: 850px;
    margin: 0 auto;
}

.form-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 8px 25px rgba(15, 23, 42, .06);
}

.form-header {
    margin-bottom: 24px;
}

.form-header h2 {
    margin: 0;
    font-size: 22px;
    color: var(--text);
}

.form-header p {
    margin: 7px 0 0;
    color: var(--muted);
    font-size: 13px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px 20px;
}

.form-group {
    min-width: 0;
}

.form-group.full {
    grid-column: 1 / -1;
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
    min-height: 44px;
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    background: white;
    color: #0f172a;
    font-family: inherit;
    font-size: 14px;
    outline: none;
    transition: .18s ease;
}

.form-group input:focus,
.form-group select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .10);
}

.form-group input::placeholder {
    color: #94a3b8;
}

.info {
    margin-top: 6px;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.5;
}

.form-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 26px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 42px;
    padding: 10px 16px;
    border: 0;
    border-radius: 9px;
    text-decoration: none;
    cursor: pointer;
    font-family: inherit;
    font-size: 14px;
    font-weight: 600;
    transition: .18s ease;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.btn-secondary {
    background: #64748b;
    color: white;
}

.btn-secondary:hover {
    background: #475569;
}

/* =========================
   RESPONSIVE
========================= */

@media (max-width: 760px) {

    .form-card {
        padding: 20px;
        border-radius: 14px;
    }

    .form-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .form-group.full {
        grid-column: auto;
    }

    .form-header h2 {
        font-size: 19px;
    }

    .form-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .btn {
        width: 100%;
    }

}

@media (max-width: 480px) {

    .form-card {
        padding: 16px;
    }

    .form-header {
        margin-bottom: 20px;
    }

    .form-header h2 {
        font-size: 18px;
    }

    .form-header p {
        font-size: 12px;
    }

    .form-group label {
        font-size: 12px;
    }

    .form-group input,
    .form-group select {
        min-height: 42px;
        font-size: 14px;
    }

}

</style>

<div class="form-wrapper">

    <div class="form-card">

        <div class="form-header">

            <h2>➕ Tambah Ujian</h2>

            <p>
                Isi data ujian dengan lengkap sebelum menyimpan.
            </p>

        </div>

        <form
            action="simpan-ujian.php"
            method="POST"
        >

            <div class="form-grid">

                <!-- Nama Ujian -->
                <div class="form-group full">

                    <label for="nama_ujian">
                        Nama Ujian
                    </label>

                    <input
                        type="text"
                        id="nama_ujian"
                        name="nama_ujian"
                        placeholder="Contoh: PAI VIII Semester 1"
                        required
                    >

                </div>

                <!-- Mata Pelajaran -->
                <div class="form-group">

                    <label for="mapel_id">
                        Mata Pelajaran
                    </label>

                    <select
                        id="mapel_id"
                        name="mapel_id"
                        required
                    >

                        <option value="">
                            -- Pilih Mata Pelajaran --
                        </option>

                        <?php while ($mapel = $resultMapel->fetch_assoc()): ?>

                            <option
                                value="<?= (int)$mapel["id"] ?>"
                            >
                                <?= htmlspecialchars($mapel["nama"]) ?>
                            </option>

                        <?php endwhile; ?>

                    </select>

                    <?php if ($admin_role !== "superadmin"): ?>

                        <div class="info">
                            Anda hanya dapat membuat ujian untuk mata pelajaran
                            yang ditugaskan kepada Anda.
                        </div>

                    <?php endif; ?>

                </div>

                <!-- Kelas -->
                <div class="form-group">

                    <label for="kelas">
                        Kelas
                    </label>

                    <input
                        type="text"
                        id="kelas"
                        name="kelas"
                        placeholder="Contoh: VIII A"
                        required
                    >

                </div>

                <!-- Durasi -->
                <div class="form-group">

                    <label for="durasi">
                        Durasi (menit)
                    </label>

                    <input
                        type="number"
                        id="durasi"
                        name="durasi"
                        min="1"
                        value="60"
                        required
                    >

                </div>

                <!-- Minimal Waktu -->
                <div class="form-group">

                    <label for="minimal_menit">
                        Minimal Waktu Mengerjakan (menit)
                    </label>

                    <input
                        type="number"
                        id="minimal_menit"
                        name="minimal_menit"
                        min="0"
                        value="0"
                        required
                    >

                    <div class="info">
                        Isi 0 jika siswa boleh mengirim ujian kapan saja.
                    </div>

                </div>

                <!-- Maksimal Pelanggaran -->
                <div class="form-group">

                    <label for="maks_pelanggaran">
                        Maksimal Pelanggaran
                    </label>

                    <input
                        type="number"
                        id="maks_pelanggaran"
                        name="maks_pelanggaran"
                        min="0"
                        value="0"
                        required
                    >

                    <div class="info">
                        Isi 0 jika tidak ada batas pelanggaran.
                        Jika mencapai batas, ujian akan otomatis dikirim.
                    </div>

                </div>

                <!-- Token -->
                <div class="form-group">

                    <label for="token">
                        Token
                    </label>

                    <input
                        type="text"
                        id="token"
                        name="token"
                        placeholder="Contoh: PAI123"
                        required
                    >

                </div>

                <!-- Tanggal Mulai -->
                <div class="form-group">

                    <label for="tanggal_mulai">
                        Tanggal Mulai
                    </label>

                    <input
                        type="datetime-local"
                        id="tanggal_mulai"
                        name="tanggal_mulai"
                        required
                    >

                </div>

                <!-- Tanggal Selesai -->
                <div class="form-group">

                    <label for="tanggal_selesai">
                        Tanggal Selesai
                    </label>

                    <input
                        type="datetime-local"
                        id="tanggal_selesai"
                        name="tanggal_selesai"
                        required
                    >

                </div>

                <!-- Status -->
                <div class="form-group">

                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                        required
                    >

                        <option value="aktif">
                            Aktif
                        </option>

                        <option value="nonaktif">
                            Nonaktif
                        </option>

                    </select>

                </div>

            </div>

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    💾 Simpan Ujian
                </button>

            </div>

        </form>

    </div>

</div>

<?php require_once "includes/footer.php"; ?>