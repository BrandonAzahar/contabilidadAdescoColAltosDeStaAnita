<?php
require_once 'config.php';

// Handle form submissions for members
if ($_POST) {
    $conn = getConnection();
    
    if (isset($_POST['add_member'])) {
        // Add new member
        $name = $_POST['name'];
        $address = $_POST['address'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];
        $registration_date = $_POST['registration_date'];
        
        $sql = "INSERT INTO members (name, address, phone, email, registration_date) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$name, $address, $phone, $email, $registration_date]);
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (isset($_POST['update_member'])) {
        // Update existing member
        $id = $_POST['id'];
        $name = $_POST['name'];
        $address = $_POST['address'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];
        $status = $_POST['status'];
        
        $sql = "UPDATE members SET name=?, address=?, phone=?, email=?, status=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$name, $address, $phone, $email, $status, $id]);
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (isset($_POST['delete_member'])) {
        // Get the member's water payments to remove associated accounting entries
        $id = $_POST['id'];

        // Get all water payment IDs for this member to delete associated accounting entries
        $sql = "SELECT wp.id, wp.payment_month, wp.payment_year FROM water_payments wp WHERE wp.member_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Delete associated accounting entries
        foreach ($payments as $payment) {
            $payment_month_name = date('F', mktime(0, 0, 0, $payment['payment_month'], 1));
            $sql = "DELETE FROM accounting_entries WHERE description LIKE ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute(["%{$payment_month_name} {$payment['payment_year']} (Socio: {$id})%"]);
        }

        // Delete member (water_payments will be deleted automatically due to foreign key constraint)
        $sql = "DELETE FROM members WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Get all members
$conn = getConnection();
$sql = "SELECT * FROM members ORDER BY name";
$stmt = $conn->query($sql);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For editing, get the member to edit if ID is provided in URL
$edit_member = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $sql = "SELECT * FROM members WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $edit_member = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ADESCO - Gestión de Socios</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-bottom: 20px;
        }
        .header {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            color: white;
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
            margin-bottom: 1rem;
        }
        .table th {
            background-color: #e9ecef;
        }
        .action-btn {
            margin: 0 2px;
            padding: 2px 6px;
        }
        .status-active {
            color: #198754;
            font-weight: bold;
        }
        .status-inactive {
            color: #6c757d;
            font-weight: bold;
        }
        .form-row-mobile {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        @media (max-width: 576px) {
            .form-row-mobile {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h1 class="h3"><i class="bi bi-people"></i> ADESCO - Gestión de Socios</h1>
                    <p class="lead small">Administración de socios y control de pagos de agua</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Form to add or update members -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h3 class="h5 mb-0">
                    <?php echo $edit_member ? '<i class="bi bi-pencil"></i> Editar Socio' : '<i class="bi bi-plus-circle"></i> Agregar Nuevo Socio'; ?>
                </h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="id" value="<?php echo $edit_member ? $edit_member['id'] : ''; ?>">

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="name" class="form-label">Nombre Completo</label>
                            <input type="text" class="form-control" id="name" name="name"
                                   placeholder="Nombre del socio"
                                   value="<?php echo $edit_member ? htmlspecialchars($edit_member['name']) : ''; ?>" required>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   placeholder="Email del socio"
                                   value="<?php echo $edit_member ? htmlspecialchars($edit_member['email']) : ''; ?>">
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-12 col-md-6">
                            <label for="address" class="form-label">Dirección</label>
                            <input type="text" class="form-control" id="address" name="address"
                                   placeholder="Dirección del socio"
                                   value="<?php echo $edit_member ? htmlspecialchars($edit_member['address']) : ''; ?>">
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label for="phone" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="phone" name="phone"
                                   placeholder="Teléfono del socio"
                                   value="<?php echo $edit_member ? htmlspecialchars($edit_member['phone']) : ''; ?>">
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label for="registration_date" class="form-label">Fecha de Registro</label>
                            <input type="date" class="form-control" id="registration_date" name="registration_date"
                                   value="<?php echo $edit_member ? $edit_member['registration_date'] : date('Y-m-d'); ?>"
                                   <?php echo $edit_member ? '' : 'required'; ?>>
                        </div>
                    </div>

                    <?php if ($edit_member): ?>
                    <div class="row g-3 mt-2">
                        <div class="col-12 col-md-3">
                            <label for="status" class="form-label">Estado</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?php echo ($edit_member['status'] == 'active') ? 'selected' : ''; ?>>Activo</option>
                                <option value="inactive" <?php echo ($edit_member['status'] == 'inactive') ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="mt-3 d-grid d-md-block">
                        <?php if ($edit_member): ?>
                            <button type="submit" name="update_member" class="btn btn-warning me-2 mb-1">
                                <i class="bi bi-save"></i> Actualizar
                            </button>
                            <a href="members.php" class="btn btn-secondary mb-1">
                                <i class="bi bi-x-circle"></i> Cancelar
                            </a>
                        <?php else: ?>
                            <button type="submit" name="add_member" class="btn btn-primary me-2 mb-1">
                                <i class="bi bi-plus-lg"></i> Agregar
                            </button>
                        <?php endif; ?>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Volver al Inicio
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Members Table -->
        <div class="card">
            <div class="card-header bg-dark text-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h3 class="h5 mb-0"><i class="bi bi-people"></i> Lista de Socios</h3>
                <span class="badge bg-secondary"><?php echo count($members); ?> socios</span>
            </div>
            <div class="card-body">
                <?php if (count($members) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Dirección</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $member): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($member['id']); ?></td>
                                        <td><?php echo htmlspecialchars($member['name']); ?></td>
                                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                                        <td><?php echo htmlspecialchars($member['phone']); ?></td>
                                        <td><?php echo htmlspecialchars($member['address']); ?></td>
                                        <td>
                                            <span class="<?php echo $member['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $member['status'] == 'active' ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td class="d-flex flex-wrap gap-1">
                                            <a href="?edit=<?php echo $member['id']; ?>" class="btn btn-sm btn-outline-primary action-btn">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="water_payments.php?member_id=<?php echo $member['id']; ?>" class="btn btn-sm btn-outline-info action-btn">
                                                <i class="bi bi-water"></i>
                                            </a>
                                            <form method="POST" style="display: inline;"
                                                  onsubmit="return confirm('¿Estás seguro de que deseas eliminar este socio?');">
                                                <input type="hidden" name="id" value="<?php echo $member['id']; ?>">
                                                <button type="submit" name="delete_member" class="btn btn-sm btn-outline-danger action-btn">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-people" style="font-size: 3rem; color: #6c757d;"></i>
                        <h4 class="text-muted mt-3">No hay socios registrados</h4>
                        <p class="text-muted">Agregue su primer socio para comenzar a gestionar los pagos de agua</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <footer class="mt-5 mb-4 text-center text-muted">
            <p>Sistema de Gestión de Socios para ADESCO - <?php echo date('Y'); ?></p>
        </footer>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>