$path='\\\\app1boggca\\\\xampp\\\\htdocs\\\\Proyecto_GCA_Mejorado\\\\cargue_horas.php'
$lines = Get-Content -Encoding UTF8 $path
for ($i=0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -like '*Deshabilitar botones de comentario*') {
        if ($i+1 -lt $lines.Count -and $lines[$i+1] -like "*document.querySelectorAll('.comment-btn')*") {
            $lines[$i] = '                    // NOTA: mantener los botones de comentario habilitados para permitir'
            $lines[$i+1] = '                    // la consulta/visualización de comentarios aunque el mes esté inactivo.'
            break
        }
    }
}
Set-Content -Encoding UTF8 -Path $path -Value $lines
Write-Host 'PATCH_APPLIED'
