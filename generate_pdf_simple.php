<?php
require_once 'config.php';

// Simple HTML-to-PDF solution using browser print functionality
// This approach works without additional libraries

// Get filter parameters
$month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;

// Build query based on filters
$where_clause = "";
$params = [];
if ($month > 0 && $year > 0) {
    $where_clause = "WHERE YEAR(date) = ? AND MONTH(date) = ?";
    $params = [$year, $month];
} elseif ($year > 0) {
    $where_clause = "WHERE YEAR(date) = ?";
    $params = [$year];
}

// Get all entries with filtering
$conn = getConnection();
$sql = "SELECT * FROM accounting_entries " . $where_clause . " ORDER BY date DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals with filtering
$total_entradas = 0;
$total_salidas = 0;
foreach ($entries as $entry) {
    if ($entry['entry_type'] == 'entrada') {
        $total_entradas += $entry['amount'];
    } else {
        $total_salidas += $entry['amount'];
    }
}
$saldo = $total_entradas - $total_salidas;

// Get period name for report title
$period_name = "Todos los registros";
if ($month > 0 && $year > 0) {
    $period_name = date('F Y', mktime(0, 0, 0, $month, 1, $year));
} elseif ($year > 0) {
    $period_name = "Año " . $year;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Contabilidad - ADESCO</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .summary {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .summary-item {
            text-align: center;
            padding: 10px;
        }
        .summary-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 0.9em;
        }
        .table th, .table td {
            border: 1px solid #333;
            padding: 8px;
            text-align: left;
        }
        .table th {
            background-color: #f2f2f2;
        }
        .entrada {
            color: #28a745;
            font-weight: bold;
        }
        .salida {
            color: #dc3545;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 0.8em;
            color: #666;
        }
        @media (max-width: 600px) {
            body {
                margin: 10px;
            }
            .table {
                font-size: 0.8em;
            }
            .table th, .table td {
                padding: 4px;
            }
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de Contabilidad - ADESCO</h1>
        <h2>Período: <?php echo $period_name; ?></h2>
        <p>Generado el: <?php echo date('d/m/Y H:i:s'); ?></p>
    </div>

    <div class="summary">
        <div class="summary-item">
            <div class="summary-title">Entradas (Ingresos)</div>
            <div class="entrada">+<?php echo number_format($total_entradas, 2); ?> $</div>
        </div>
        <div class="summary-item">
            <div class="summary-title">Salidas (Gastos)</div>
            <div class="salida">-<?php echo number_format($total_salidas, 2); ?> $</div>
        </div>
        <div class="summary-item">
            <div class="summary-title">Saldo Total</div>
            <div><?php echo number_format($saldo, 2); ?> $</div>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Descripción</th>
                <th>Tipo</th>
                <th>Monto</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($entries as $entry): ?>
            <tr>
                <td><?php echo htmlspecialchars($entry['id']); ?></td>
                <td><?php echo htmlspecialchars($entry['date']); ?></td>
                <td><?php echo htmlspecialchars($entry['description']); ?></td>
                <td>
                    <?php if ($entry['entry_type'] == 'entrada'): ?>
                        <span class="entrada">Entrada</span>
                    <?php else: ?>
                        <span class="salida">Salida</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($entry['entry_type'] == 'entrada'): ?>
                        <span class="entrada">+<?php echo number_format($entry['amount'], 2); ?> $</span>
                    <?php else: ?>
                        <span class="salida">-<?php echo number_format($entry['amount'], 2); ?> $</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>

            <?php if (count($entries) == 0): ?>
            <tr>
                <td colspan="5" style="text-align: center;">No hay registros para el período seleccionado</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        <p>Sistema de Contabilidad para ADESCO - <?php echo date('Y'); ?></p>
        <p>Página <span class="page-number"></span></p>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" class="btn btn-primary">Imprimir o Guardar como PDF</button>
        <button onclick="window.close()" class="btn btn-secondary">Cerrar</button>
    </div>

    <script>
        // Add page numbers
        document.addEventListener('DOMContentLoaded', function() {
            const pageNumbers = document.querySelectorAll('.page-number');
            pageNumbers.forEach(function(el) {
                el.textContent = document.querySelector('.page-number').closest('body').children.length;
            });
        });

        // Auto-print when page loads
        window.onload = function() {
            // Uncomment the next line if you want to automatically open the print dialog
            // window.print();
        };
    </script>
</body>
</html>