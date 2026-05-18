<!-- Gasto_general_detalle_imputacion.php -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de Imputación - Gasto General</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .modal-dialog {
            max-width: 90vw;
        }
        .modal-content {
            border-radius: 1rem;
        }
        .modal-body {
            min-height: 200px;
        }
    </style>
</head>
<body>
<div class="modal fade" id="modalGastoGeneralDetalle" tabindex="-1" aria-labelledby="modalGastoGeneralDetalleLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow rounded-4 border-0">
            <div class="modal-header bg-primary bg-gradient text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="modalGastoGeneralDetalleLabel">
                    <i class="bi bi-info-circle-fill me-2"></i>Detalle de Imputación - Gasto General
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4 bg-light rounded-bottom-4" id="modalGastoGeneralDetalleBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-3 text-muted">Cargando detalles de gasto general...</p>
                </div>
            </div>
            <div class="modal-footer bg-light rounded-bottom-4 border-0 d-flex justify-content-end">
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal" style="min-width: 120px;">
                    &larr; Regresar
                </button>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Puedes cargar aquí el detalle real por AJAX si lo necesitas
</script>
</body>
</html>
