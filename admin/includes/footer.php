</main>


<script>

function bukaSidebar() {

    const sidebar =
        document.getElementById("sidebar");

    const overlay =
        document.getElementById("overlay");

    if (sidebar) {
        sidebar.classList.add("open");
    }

    if (overlay) {
        overlay.classList.add("show");
    }

}


function tutupSidebar() {

    const sidebar =
        document.getElementById("sidebar");

    const overlay =
        document.getElementById("overlay");

    if (sidebar) {
        sidebar.classList.remove("open");
    }

    if (overlay) {
        overlay.classList.remove("show");
    }

}

</script>


</body>

</html>