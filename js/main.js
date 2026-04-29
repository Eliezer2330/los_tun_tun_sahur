// ============================================
// JS PRINCIPAL — CONCURSO ISC 2026
// ============================================

document.addEventListener('DOMContentLoaded', function () {

    // Auto-cerrar alertas después de 4 segundos
    const alertas = document.querySelectorAll('.alert.alert-dismissible');
    alertas.forEach(function (alerta) {
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alerta);
            bsAlert.close();
        }, 4000);
    });

    // Confirmación antes de eliminar (respaldo extra además del onclick)
    document.querySelectorAll('a[href*="eliminar.php"]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            if (!confirm('¿Estás seguro de que deseas eliminar este registro? Esta acción no se puede deshacer.')) {
                e.preventDefault();
            }
        });
    });

    // Marcar enlace activo en navbar
    const currentPath = window.location.pathname.split('/').pop();
    document.querySelectorAll('.nav-link').forEach(function (link) {
        const href = link.getAttribute('href');
        if (href && href.includes(currentPath) && currentPath !== '') {
            link.classList.add('active');
        }
    });

});
