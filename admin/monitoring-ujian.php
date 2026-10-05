<?php

require __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../config/database.php";

$admin_id = (int)($_SESSION["admin_id"] ?? 0);
$admin_nama = $_SESSION["admin_nama"] ?? "Administrator";
$admin_role = $_SESSION["admin_role"] ?? "admin";

$page_title = "Monitoring Ujian";
$page_description = "Pantau proses siswa mengerjakan ujian";

require __DIR__ . "/includes/header.php";
require __DIR__ . "/includes/sidebar.php";


/* =========================================================
   FILTER
========================================================= */

$filter_mapel = isset($_GET["mapel_id"])
    ? (array)$_GET["mapel_id"]
    : [];

$filter_mapel = array_values(
    array_unique(
        array_filter(
            array_map("intval", $filter_mapel),
            function ($id) {
                return $id > 0;
            }
        )
    )
);

$filter_kelas = isset($_GET["kelas"])
    ? (array)$_GET["kelas"]
    : [];

$filter_kelas = array_values(
    array_unique(
        array_filter(
            array_map(
                function ($kelas) {
                    return trim((string)$kelas);
                },
                $filter_kelas
            ),
            function ($kelas) {
                return $kelas !== "";
            }
        )
    )
);

$filter_ujian = isset($_GET["ujian_id"])
    ? (int)$_GET["ujian_id"]
    : 0;

$filter_status = $_GET["status"] ?? "";


/* =========================================================
   DAFTAR MAPEL
========================================================= */

if ($admin_role === "superadmin") {

    $stmtMapel = $conn->prepare("
        SELECT id, nama
        FROM mata_pelajaran
        WHERE status = 'aktif'
        ORDER BY nama ASC
    ");

} else {

    $stmtMapel = $conn->prepare("
        SELECT DISTINCT
            m.id,
            m.nama
        FROM mata_pelajaran m
        INNER JOIN admin_mapel am
            ON am.mapel_id = m.id
           AND am.admin_id = ?
        WHERE m.status = 'aktif'
        ORDER BY m.nama ASC
    ");

    $stmtMapel->bind_param(
        "i",
        $admin_id
    );
}

$stmtMapel->execute();

$resultMapel = $stmtMapel->get_result();


/* =========================================================
   DAFTAR UJIAN
========================================================= */

if ($admin_role === "superadmin") {

    $stmtUjian = $conn->prepare("
        SELECT
            u.id,
            u.nama_ujian,
            u.kelas,
            m.nama AS nama_mapel
        FROM ujian u
        LEFT JOIN mata_pelajaran m
            ON m.id = u.mapel_id
        ORDER BY u.id DESC
    ");

} else {

    $stmtUjian = $conn->prepare("
        SELECT
            DISTINCT u.id,
            u.nama_ujian,
            u.kelas,
            m.nama AS nama_mapel
        FROM ujian u
        INNER JOIN admin_mapel am
            ON am.mapel_id = u.mapel_id
           AND am.admin_id = ?
        LEFT JOIN mata_pelajaran m
            ON m.id = u.mapel_id
        ORDER BY u.id DESC
    ");

    $stmtUjian->bind_param(
        "i",
        $admin_id
    );
}

$stmtUjian->execute();

$resultUjian = $stmtUjian->get_result();


/* =========================================================
   DAFTAR KELAS
========================================================= */

$stmtKelas = $conn->prepare("
    SELECT DISTINCT kelas
    FROM siswa
    WHERE kelas IS NOT NULL
      AND TRIM(kelas) <> ''
    ORDER BY kelas ASC
");

$stmtKelas->execute();

$resultKelas = $stmtKelas->get_result();


/* =========================================================
   DATA MONITORING
========================================================= */

$params = [];
$types = "";

$sql = "
    SELECT
        sesi.id AS sesi_id,
        sesi.siswa_id,
        sesi.ujian_id,
        sesi.mulai,
        sesi.batas_waktu,
        sesi.selesai,
        sesi.status AS status_sesi,

        s.nama AS nama_siswa,
        s.kelas,

        u.nama_ujian,
        u.kelas AS kelas_ujian,
        u.mapel_id,

        m.nama AS nama_mapel,

        COUNT(DISTINCT j.id) AS jumlah_dijawab,

        (
            SELECT COUNT(*)
            FROM soal so
            WHERE so.ujian_id = u.id
        ) AS jumlah_soal,

        (
            SELECT COUNT(*)
            FROM pelanggaran_ujian p
            WHERE p.sesi_id = sesi.id
        ) AS jumlah_pelanggaran

    FROM sesi_ujian sesi

    INNER JOIN siswa s
        ON s.id = sesi.siswa_id

    INNER JOIN ujian u
        ON u.id = sesi.ujian_id

    LEFT JOIN mata_pelajaran m
        ON m.id = u.mapel_id

    LEFT JOIN jawaban j
        ON j.sesi_id = sesi.id
";


/* =========================================================
   BATAS AKSES ADMIN
========================================================= */

$where = [];


if ($admin_role !== "superadmin") {

    $where[] = "
        EXISTS (
            SELECT 1
            FROM admin_mapel am_access
            WHERE am_access.admin_id = ?
              AND am_access.mapel_id = u.mapel_id
        )
    ";

    $params[] = $admin_id;
    $types .= "i";
}


/* =========================================================
   FILTER MAPEL
========================================================= */

if (count($filter_mapel) > 0) {

    $placeholders = implode(
        ",",
        array_fill(0, count($filter_mapel), "?")
    );

    $where[] = "u.mapel_id IN (" . $placeholders . ")";

    foreach ($filter_mapel as $mapel_id) {
        $params[] = $mapel_id;
        $types .= "i";
    }
}


/* =========================================================
   FILTER KELAS
========================================================= */

if (count($filter_kelas) > 0) {

    $placeholders = implode(
        ",",
        array_fill(0, count($filter_kelas), "?")
    );

    $where[] = "s.kelas IN (" . $placeholders . ")";

    foreach ($filter_kelas as $kelas) {
        $params[] = $kelas;
        $types .= "s";
    }
}


/* =========================================================
   FILTER UJIAN
========================================================= */


if ($filter_ujian > 0) {

    $where[] = "sesi.ujian_id = ?";

    $params[] = $filter_ujian;
    $types .= "i";
}


/* =========================================================
   FILTER STATUS
========================================================= */

if ($filter_status === "aktif") {

    $where[] = "sesi.status = 'mengerjakan'";

}

if ($filter_status === "selesai") {

    /*
    | Status selesai mencakup sesi yang sudah ditandai selesai/habis
    | maupun sesi yang secara waktu sudah berakhir tetapi belum sempat
    | diproses oleh browser siswa. Monitoring tetap READ-ONLY.
    */
    $where[] = "
        (
            sesi.status IN ('selesai', 'habis')
            OR (
                sesi.status = 'mengerjakan'
                AND sesi.batas_waktu IS NOT NULL
                AND sesi.batas_waktu <= NOW()
            )
        )
    ";

}


if (count($where) > 0) {

    $sql .= "
        WHERE
        " . implode(" AND ", $where);
}


$sql .= "
    GROUP BY
        sesi.id,
        sesi.siswa_id,
        sesi.ujian_id,
        sesi.mulai,
        sesi.batas_waktu,
        sesi.selesai,
        sesi.status,
        s.nama,
        s.kelas,
        u.nama_ujian,
        u.kelas,
        u.mapel_id,
        m.nama

    ORDER BY
        sesi.status ASC,
        sesi.batas_waktu ASC
";


$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Gagal menyiapkan monitoring: " . $conn->error);
}


if (count($params) > 0) {
    $stmt->bind_param(
        $types,
        ...$params
    );
}


$stmt->execute();

$resultMonitoring = $stmt->get_result();


/* =========================================================
   STATISTIK
========================================================= */

$totalAktif = 0;
$totalSelesai = 0;
$totalPelanggaran = 0;

$rows = [];

while ($row = $resultMonitoring->fetch_assoc()) {

    $rows[] = $row;

    if ($row["status_sesi"] === "mengerjakan") {
        $totalAktif++;
    }

    $waktuSudahHabis =
        !empty($row["batas_waktu"]) &&
        strtotime($row["batas_waktu"]) <= time();

    if (
        $row["status_sesi"] === "selesai" ||
        $row["status_sesi"] === "habis" ||
        (
            $row["status_sesi"] === "mengerjakan" &&
            $waktuSudahHabis
        )
    ) {
        $totalSelesai++;
    }

    $totalPelanggaran +=
        (int)$row["jumlah_pelanggaran"];
}

?>

<style>

.monitor-toolbar {
    display: grid;
    grid-template-columns:
        minmax(0, 1fr)
        minmax(0, 1fr)
        minmax(0, 1fr)
        minmax(0, 1fr)
        auto;
    gap: 12px;
    margin-bottom: 20px;
    align-items: start;
}

.check-filter {
    position: relative;
    width: 100%;
}

.check-filter summary {
    list-style: none;
    min-height: 42px;
    box-sizing: border-box;
    padding: 11px 13px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: white;
    font-size: 13px;
    cursor: pointer;
    user-select: none;
}

.check-filter summary::-webkit-details-marker {
    display: none;
}

.check-filter summary::after {
    content: "▾";
    float: right;
    color: var(--muted);
}

.check-filter[open] summary {
    border-color: var(--primary);
}

.check-filter-panel {
    position: absolute;
    z-index: 50;
    top: calc(100% + 5px);
    left: 0;
    right: 0;
    min-width: 230px;
    max-height: 280px;
    overflow-y: auto;
    padding: 8px;
    background: white;
    border: 1px solid var(--border);
    border-radius: 10px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, .12);
}

.check-option {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 8px 9px;
    border-radius: 7px;
    cursor: pointer;
    font-size: 13px;
}

.check-option:hover {
    background: #f3f4f6;
}

.check-option input {
    width: 16px;
    height: 16px;
    margin: 0;
    cursor: pointer;
    accent-color: var(--primary);
}

.simple-filter {
    width: 100%;
    min-height: 42px;
    box-sizing: border-box;
    padding: 9px 12px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: white;
    font-size: 13px;
}

.monitor-toolbar button {
    min-height: 42px;
    padding: 9px 14px;
    border-radius: 9px;
    background: var(--primary);
    color: white;
    border: none;
    cursor: pointer;
    font-weight: bold;
    white-space: nowrap;
}

.stats-grid {
    display: grid;
    grid-template-columns:
        repeat(3, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 18px;
}

.stat-label {
    color: var(--muted);
    font-size: 12px;
    margin-bottom: 7px;
}

.stat-value {
    font-size: 27px;
    font-weight: bold;
}

.monitor-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
}

.monitor-header {
    padding: 18px 20px;
    border-bottom: 1px solid var(--border);
}

.monitor-header h3 {
    margin: 0 0 4px;
    font-size: 16px;
}

.monitor-header p {
    margin: 0;
    color: var(--muted);
    font-size: 12px;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1050px;
}

th,
td {
    padding: 13px 14px;
    border-bottom: 1px solid var(--border);
    text-align: left;
    font-size: 12px;
    vertical-align: middle;
}

th {
    background: #f8fafc;
    color: #475569;
    font-size: 11px;
    text-transform: uppercase;
}

.student-name {
    font-weight: bold;
}

.sub-text {
    display: block;
    color: var(--muted);
    margin-top: 3px;
    font-size: 11px;
}

.badge {
    display: inline-flex;
    align-items: center;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: bold;
}

.badge-active {
    background: #dcfce7;
    color: #166534;
}

.badge-finished {
    background: #e2e8f0;
    color: #475569;
}

.progress-box {
    min-width: 100px;
}

.progress-text {
    font-size: 11px;
    margin-bottom: 5px;
}

.progress {
    height: 6px;
    background: #e5e7eb;
    border-radius: 99px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    background: var(--primary);
}

.violation {
    font-weight: bold;
}

.violation.warning {
    color: #dc2626;
}

.detail-btn {
    display: inline-block;
    padding: 7px 10px;
    border-radius: 7px;
    background: #fef2f2;
    color: #b91c1c;
    text-decoration: none;
    font-size: 11px;
    font-weight: bold;
}

.empty {
    padding: 45px 20px;
    text-align: center;
    color: var(--muted);
}

.live-indicator {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #16a34a;
    font-size: 11px;
    font-weight: bold;
}

.live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #16a34a;
}

@media (max-width: 1100px) {

    .monitor-toolbar {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

    .monitor-toolbar button {
        width: 100%;
    }
}

@media (max-width: 650px) {

    .monitor-toolbar {
        grid-template-columns: 1fr;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .check-filter-panel {
        position: static;
        margin-top: 5px;
        box-shadow: none;
    }
}

</style>


<main class="main">

<header class="topbar">

    <div class="page-title">

        <h1>
            <?= htmlspecialchars($page_title) ?>
        </h1>

        <p>
            <?= htmlspecialchars($page_description) ?>
        </p>

    </div>

    <div class="top-user">

        <div class="top-user-icon">
            👤
        </div>

        <span>
            <?= htmlspecialchars($admin_nama) ?>
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
        PIRI CBT — Admin
    </div>

    <div style="width:40px;"></div>

</header>


<section class="content">


    <div class="section-heading">

        <h3>
            📡 Monitoring Ujian
        </h3>

        <span class="live-indicator">
            <span class="live-dot"></span>
            Live
        </span>

    </div>


    <!-- FILTER -->

    <div class="filter-help">
        💡 Pilih beberapa Mata Pelajaran atau Kelas dengan mencentang kotaknya.
        Anda dapat memilih lebih dari satu sekaligus.
    </div>

    <form
        method="GET"
        class="monitor-toolbar"
    >

        <!-- MULTI FILTER MAPEL -->
        <details class="check-filter">

            <summary>
                <span class="filter-summary" data-filter-label="mapel">
                    Mata Pelajaran
                </span>
            </summary>

            <div class="check-filter-panel">

                <?php while ($mapel = $resultMapel->fetch_assoc()): ?>

                    <label class="check-option">

                        <input
                            type="checkbox"
                            name="mapel_id[]"
                            value="<?= (int)$mapel["id"] ?>"
                            <?= in_array(
                                (int)$mapel["id"],
                                $filter_mapel,
                                true
                            ) ? "checked" : "" ?>
                        >

                        <span>
                            <?= htmlspecialchars($mapel["nama"]) ?>
                        </span>

                    </label>

                <?php endwhile; ?>

            </div>

        </details>


        <!-- MULTI FILTER KELAS -->
        <details class="check-filter">

            <summary>
                <span class="filter-summary" data-filter-label="kelas">
                    Kelas
                </span>
            </summary>

            <div class="check-filter-panel">

                <?php while ($kelas = $resultKelas->fetch_assoc()): ?>

                    <label class="check-option">

                        <input
                            type="checkbox"
                            name="kelas[]"
                            value="<?= htmlspecialchars($kelas["kelas"]) ?>"
                            <?= in_array(
                                $kelas["kelas"],
                                $filter_kelas,
                                true
                            ) ? "checked" : "" ?>
                        >

                        <span>
                            <?= htmlspecialchars($kelas["kelas"]) ?>
                        </span>

                    </label>

                <?php endwhile; ?>

            </div>

        </details>


        <!-- FILTER UJIAN -->
        <select name="ujian_id" class="simple-filter">

            <option value="">
                Semua Ujian
            </option>

            <?php while ($ujian = $resultUjian->fetch_assoc()): ?>

                <option
                    value="<?= (int)$ujian["id"] ?>"
                    <?= $filter_ujian === (int)$ujian["id"] ? "selected" : "" ?>
                >
                    <?= htmlspecialchars($ujian["nama_ujian"]) ?>
                    -
                    <?= htmlspecialchars($ujian["kelas"]) ?>
                </option>

            <?php endwhile; ?>

        </select>


        <!-- FILTER STATUS -->
        <select name="status" class="simple-filter">

            <option value="">
                Semua Status
            </option>

            <option
                value="aktif"
                <?= $filter_status === "aktif" ? "selected" : "" ?>
            >
                Sedang Mengerjakan
            </option>

            <option
                value="selesai"
                <?= $filter_status === "selesai" ? "selected" : "" ?>
            >
                Selesai
            </option>

        </select>


        <button type="submit">
            🔎 Tampilkan
        </button>

    </form>


    <!-- STATISTIK -->

    <div class="stats-grid">

        <div class="stat-card">

            <div class="stat-label">
                SEDANG MENGERJAKAN
            </div>

            <div class="stat-value">
                <?= $totalAktif ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                SELESAI
            </div>

            <div class="stat-value">
                <?= $totalSelesai ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                TOTAL PELANGGARAN
            </div>

            <div class="stat-value">
                <?= $totalPelanggaran ?>
            </div>

        </div>

    </div>


    <!-- MONITORING -->

    <div class="monitor-card">

        <div class="monitor-header">

            <h3>
                Aktivitas Siswa
            </h3>

            <p>
                Data diperbarui otomatis setiap 5 detik.
            </p>

        </div>


        <div class="table-wrap">

            <?php if (count($rows) === 0): ?>

                <div class="empty">
                    Belum ada aktivitas ujian.
                </div>

            <?php else: ?>

                <table>

                    <thead>

                        <tr>

                            <th>
                                Siswa
                            </th>

                            <th>
                                Ujian
                            </th>

                            <th>
                                Mapel
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Progress
                            </th>

                            <th>
                                Waktu
                            </th>

                            <th>
                                Pelanggaran
                            </th>

                            <th>
                                Detail
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($rows as $row): ?>

                        <?php

                        $jumlah_soal =
                            (int)$row["jumlah_soal"];

                        $jumlah_dijawab =
                            (int)$row["jumlah_dijawab"];

                        $persen = 0;

                        if ($jumlah_soal > 0) {
                            $persen = round(
                                ($jumlah_dijawab / $jumlah_soal) * 100
                            );
                        }

                        if ($persen > 100) {
                            $persen = 100;
                        }

                        $waktuSudahHabis =
                            !empty($row["batas_waktu"]) &&
                            strtotime($row["batas_waktu"]) <= time();

                        $habis =
                            $row["status_sesi"] === "habis" ||
                            (
                                $row["status_sesi"] === "mengerjakan" &&
                                $waktuSudahHabis
                            );

                        $aktif =
                            $row["status_sesi"] === "mengerjakan" &&
                            !$waktuSudahHabis;

                        $selesai =
                            $row["status_sesi"] === "selesai" &&
                            !$habis;

                        ?>

                        <tr>

                            <td>

                                <span class="student-name">
                                    <?= htmlspecialchars(
                                        $row["nama_siswa"]
                                    ) ?>
                                </span>

                                <span class="sub-text">
                                    Kelas:
                                    <?= htmlspecialchars(
                                        $row["kelas"]
                                    ) ?>
                                </span>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $row["nama_ujian"]
                                ) ?>

                                <span class="sub-text">
                                    Kelas:
                                    <?= htmlspecialchars(
                                        $row["kelas_ujian"]
                                    ) ?>
                                </span>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $row["nama_mapel"] ?? "-"
                                ) ?>

                            </td>


                            <td>

                                <?php if ($aktif): ?>

                                    <span class="badge badge-active">
                                        🟢 AKTIF
                                    </span>

                                <?php elseif ($selesai): ?>

                                    <span class="badge badge-finished">
                                        🔵 SELESAI
                                    </span>

                                <?php elseif ($habis): ?>

                                    <span
                                        class="badge badge-finished"
                                        title="Waktu pengerjaan telah habis"
                                    >
                                        ⏱️ WAKTU HABIS
                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-finished">
                                        ⚪ <?= htmlspecialchars(
                                            $row["status_sesi"]
                                        ) ?>
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <div class="progress-box">

                                    <div class="progress-text">

                                        <?= $jumlah_dijawab ?>
                                        /
                                        <?= $jumlah_soal ?>

                                        (<?= $persen ?>%)

                                    </div>

                                    <div class="progress">

                                        <div
                                            class="progress-bar"
                                            style="
                                                width: <?= $persen ?>%;
                                            "
                                        ></div>

                                    </div>

                                </div>

                            </td>


                            <td>

                                <?php if ($aktif): ?>

                                    <span
                                        class="countdown"
                                        data-batas="<?= htmlspecialchars(
                                            $row["batas_waktu"]
                                        ) ?>"
                                    >
                                        menghitung...
                                    </span>

                                <?php else: ?>

                                    <span>
                                        Selesai
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <span
                                    class="
                                        violation
                                        <?= (int)$row["jumlah_pelanggaran"] > 0
                                            ? "warning"
                                            : ""
                                        ?>
                                    "
                                >

                                    ⚠️
                                    <?= (int)$row["jumlah_pelanggaran"] ?>

                                </span>

                            </td>


                            <td>

                                <?php if ((int)$row["jumlah_pelanggaran"] > 0): ?>

                                    <a
                                        href="pelanggaran.php?sesi_id=<?= (int)$row["sesi_id"] ?>"
                                        class="detail-btn"
                                    >
                                        Detail
                                    </a>

                                <?php else: ?>

                                    <span class="sub-text">
                                        Tidak ada
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            <?php endif; ?>

        </div>

    </div>


</section>

</main>


<script>

/* =========================================================
   RINGKASAN JUMLAH CHECKBOX TERPILIH
========================================================= */

function updateFilterSummary() {

    document
        .querySelectorAll(".filter-summary")
        .forEach(function(summary) {

            const label =
                summary.dataset.filterLabel;

            const details =
                summary.closest("details");

            if (!details) {
                return;
            }

            const checked =
                details.querySelectorAll(
                    'input[type="checkbox"]:checked'
                ).length;

            if (label === "mapel") {

                summary.textContent =
                    checked > 0
                        ? "Mata Pelajaran (" + checked + " dipilih)"
                        : "Mata Pelajaran";

            }

            if (label === "kelas") {

                summary.textContent =
                    checked > 0
                        ? "Kelas (" + checked + " dipilih)"
                        : "Kelas";

            }

        });
}


document
    .querySelectorAll(
        '.check-filter input[type="checkbox"]'
    )
    .forEach(function(checkbox) {

        checkbox.addEventListener(
            "change",
            updateFilterSummary
        );

    });


updateFilterSummary();


/* =========================================================
   COUNTDOWN
========================================================= */

function updateCountdown() {

    document
        .querySelectorAll(".countdown")
        .forEach(function(element) {

            const batas =
                element.dataset.batas;

            if (!batas) {
                return;
            }

            const target =
                new Date(
                    batas.replace(" ", "T")
                ).getTime();

            const sekarang =
                new Date().getTime();

            let selisih =
                target - sekarang;

            if (selisih <= 0) {

                element.textContent =
                    "Waktu habis";

                return;
            }

            const totalDetik =
                Math.floor(
                    selisih / 1000
                );

            const jam =
                Math.floor(
                    totalDetik / 3600
                );

            const menit =
                Math.floor(
                    (totalDetik % 3600) / 60
                );

            const detik =
                totalDetik % 60;

            element.textContent =
                String(jam).padStart(2, "0") +
                ":" +
                String(menit).padStart(2, "0") +
                ":" +
                String(detik).padStart(2, "0");

        });
}


updateCountdown();

setInterval(
    updateCountdown,
    1000
);


/* =========================================================
   AUTO REFRESH
========================================================= */

setTimeout(
    function() {

        window.location.reload();

    },
    5000
);

</script>


<?php

require __DIR__ . "/includes/footer.php";

?>