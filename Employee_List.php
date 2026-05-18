
<?php
require_once 'include.php';
require_once 'config.php';
require_once 'includes/header.php';
?>

<div class="page-header"><h3>Listado de Empleados</h3></div>
<div style="margin-bottom:12px;">
    <a href="export_empleados_xls.php" class="btn btn-success" target="_blank">Exportar a XLS</a>
</div>
<div class="table-wrapper">
    <table class="data-table" id="employee-table">
        <thead>
            <tr>
                <th style="width: 5%;">Matrícula</th><th>Nombre</th><th>Apellido</th><th>F. Ingreso</th><th>Área</th><th>Cargo</th><th>Salario</th>
                <th colspan="2">Acción</th>
            </tr>
            <tr id="search-inputs">
                <td><input type="text" placeholder="Buscar..."></td>
                <td><input type="text" placeholder="Buscar..."></td>
                <td><input type="text" placeholder="Buscar..."></td>
                <td><input type="text" placeholder="Buscar..."></td>
                <td><input type="text" placeholder="Buscar..."></td>
                <td><input type="text" placeholder="Buscar..."></td>
                <td><input type="text" placeholder="Buscar..."></td>
                <td colspan="2"></td>
            </tr>
        </thead>
        <tbody>
        <?php 
        $results = $conn->query("SELECT * FROM empleados");
        while ($row = $results->fetch_assoc()) { ?>
            <tr>
                <td><?php echo $row['matricula']; ?></td>
                <td><?php echo $row['nom']; ?></td>
                <td><?php echo $row['prenom']; ?></td>
                <td><?php echo $row['fecha_ingreso']; ?></td>
                <td><?php echo $row['area']; ?></td>
                <td><?php echo $row['cargo_ingresa']; ?></td>
                <td><?php echo number_format($row['salario'], 2); ?></td>
                <td><a href="empleados.php?edit=<?php echo $row['id']; ?>" class="btn btn-info">Editar</a></td>
                <td><a href="empleados.php?delete=<?php echo $row['id']; ?>" class="btn btn-danger" onclick="return confirm('¿Seguro?');">Eliminar</a></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div>

<script>
function filterTable() {
    const table = document.getElementById("employee-table");
    const rows = table.getElementsByTagName("tbody")[0].getElementsByTagName("tr");
    const searchInputs = document.getElementById("search-inputs").getElementsByTagName("input");

    for (let i = 0; i < rows.length; i++) {
        let display = true;
        for (let j = 0; j < searchInputs.length; j++) {
            const cell = rows[i].getElementsByTagName("td")[j];
            const filter = searchInputs[j].value.toUpperCase();
            if (cell) {
                const textValue = cell.textContent || cell.innerText;
                if (textValue.toUpperCase().indexOf(filter) === -1) {
                    display = false;
                    break;
                }
            }
        }
        rows[i].style.display = display ? "" : "none";
    }
}

// Add event listeners to all search inputs
const searchInputs = document.getElementById("search-inputs").getElementsByTagName("input");
for (let i = 0; i < searchInputs.length; i++) {
    searchInputs[i].addEventListener("keyup", filterTable);
}
</script>

<?php
require_once 'includes/footer.php';
?>

<script>
// Move the list page header into the global header placeholder so it sits inline with the site image and centered
document.addEventListener('DOMContentLoaded', function(){
    try {
        var slot = document.querySelector('.page-title-placeholder');
        var ph = document.querySelector('.page-header');
        if (slot && ph) {
            ph.style.margin = '0';
            ph.style.display = 'flex';
            ph.style.alignItems = 'center';
            slot.style.justifyContent = 'center';
            const h3 = ph.querySelector('h3');
            if (h3) { h3.style.margin = '0'; h3.style.textAlign = 'center'; }
            slot.appendChild(ph);
        }
    } catch(e){ console && console.warn && console.warn('Move header failed', e); }
});
</script>
