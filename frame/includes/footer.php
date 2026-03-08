<?php
// includes/footer.php
?>
</main>

<script src="/assets/js/theme.js"></script>
<script>
function openSidebar() {
    document.getElementById('sidebar')?.classList.add('open');
    document.getElementById('overlay')?.style.display = 'block';
}

function closeSidebar() {
    document.getElementById('sidebar')?.classList.remove('open');
    document.getElementById('overlay')?.style.display = 'none';
}

// Close on ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeSidebar();
    }
});
</script>
</body>
</html>