<?php // admin/includes/admin_footer.php ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 3500 };
<?php if (isset($_SESSION['flash_success'])): ?>
toastr.success('<?= addslashes($_SESSION['flash_success']) ?>');
<?php unset($_SESSION['flash_success']); endif; ?>
<?php if (isset($_SESSION['flash_error'])): ?>
toastr.error('<?= addslashes($_SESSION['flash_error']) ?>');
<?php unset($_SESSION['flash_error']); endif; ?>

// Confirm delete helper
function confirmDelete(formId) {
    Swal.fire({ title:'Are you sure?', text:'This cannot be undone!', icon:'warning', showCancelButton:true, confirmButtonColor:'#d33', cancelButtonColor:'#6c757d', confirmButtonText:'Yes, delete!' })
    .then(r => { if (r.isConfirmed) document.getElementById(formId).submit(); });
}
</script>
</body>
</html>
