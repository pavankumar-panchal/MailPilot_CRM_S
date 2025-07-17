<?php
require_once __DIR__ . '/../config/db.php';


// Initialize message variables
$message = '';
$message_type = ''; // 'success' or 'error'

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['add_smtp'])) {
    // Add new SMTP server
    $name = $conn->real_escape_string($_POST['name']);
    $host = $conn->real_escape_string($_POST['host']);
    $port = intval($_POST['port']);
    $encryption = $conn->real_escape_string($_POST['encryption']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $conn->real_escape_string($_POST['password']);
    $hourly_limit = intval($_POST['hourly_limit']);

    $daily_limit = intval($_POST['daily_limit']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $sql = "INSERT INTO smtp_servers (name, host, port, encryption, email, password, daily_limit,hourly_limit, is_active) 
                VALUES ('$name', '$host', $port, '$encryption', '$email', '$password', $daily_limit,$hourly_limit, $is_active)";

    if ($conn->query($sql)) {
      $message = 'SMTP server added successfully!';
      $message_type = 'success';
      // Clear POST data to prevent resubmission
      header("Location: smtp_records.php?message=" . urlencode($message) . "&message_type=$message_type");
      exit();
    } else {
      $message = 'Error adding SMTP server: ' . $conn->error;
      $message_type = 'error';
    }
  } elseif (isset($_POST['update_smtp'])) {
    // Update existing SMTP server
    $id = intval($_POST['id']);
    $name = $conn->real_escape_string($_POST['name']);
    $host = $conn->real_escape_string($_POST['host']);
    $port = intval($_POST['port']);
    $encryption = $conn->real_escape_string($_POST['encryption']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $conn->real_escape_string($_POST['password']);
    $daily_limit = intval($_POST['daily_limit']);
    $hourly_limit = intval($_POST['hourly_limit']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $sql = "UPDATE smtp_servers SET 
                name = '$name',
                host = '$host',
                port = $port,
                encryption = '$encryption',
                email = '$email',
                password = '$password',
                daily_limit = $daily_limit,
                hourly_limit = $hourly_limit,
                is_active = $is_active
                WHERE id = $id";

    if ($conn->query($sql)) {
      $message = 'SMTP server updated successfully!';
      $message_type = 'success';
      header("Location: smtp_records.php?message=" . urlencode($message) . "&message_type=$message_type");
      exit();
    } else {
      $message = 'Error updating SMTP server: ' . $conn->error;
      $message_type = 'error';
    }
  }
} elseif (isset($_GET['delete'])) {
  // Delete SMTP server
  $id = intval($_GET['delete']);
  $sql = "DELETE FROM smtp_servers WHERE id = $id";

  if ($conn->query($sql)) {
    $message = 'SMTP server deleted successfully!';
    $message_type = 'success';
  } else {
    $message = 'Error deleting SMTP server: ' . $conn->error;
    $message_type = 'error';
  }
  header("Location: smtp_records.php?message=" . urlencode($message) . "&message_type=$message_type");
  exit();
}

// Check for messages in URL parameters
if (isset($_GET['message']) && isset($_GET['message_type'])) {
  $message = urldecode($_GET['message']);
  $message_type = $_GET['message_type'];
}

// Get all SMTP servers
$result = $conn->query("SELECT * FROM smtp_servers ORDER BY id DESC");
$smtpServers = [];
while ($row = $result->fetch_assoc()) {
  $smtpServers[] = $row;
}

// Get server for editing if edit parameter is set
$editServer = null;
if (isset($_GET['edit'])) {
  $id = intval($_GET['edit']);
  $result = $conn->query("SELECT * FROM smtp_servers WHERE id = $id");
  $editServer = $result->fetch_assoc();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage SMTP Servers</title>
  <!-- <script src="https://cdn.tailwindcss.com"></script> -->
  <link rel="stylesheet" href="assets/style_tailwind.css">
  <link rel="stylesheet" href="assets/main.css">


  <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> -->
  <style>
    :root {
      --primary: #4f46e5;
      --primary-light: #6366f1;
      --primary-dark: #4338ca;
      --secondary: #f9fafb;
      --text: #111827;
      --text-light: #6b7280;
      --border: #e5e7eb;
      --success: #10b981;
      --warning: #f59e0b;
      --danger: #ef4444;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      /* background-color: #f3f4f6; */
      color: var(--text);
    }

    .navbar {
      background-color: white;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 50;
    }

    .card {
      background: white;
      border-radius: 0.75rem;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
      transition: all 0.2s ease;
    }

    .card:hover {
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    .status-active {
      background-color: #dcfce7;
      color: #166534;
    }

    .status-inactive {
      background-color: #fee2e2;
      color: #991b1b;
    }

    .alert-success {
      background-color: #d1fae5;
      color: #065f46;
      border-left: 4px solid #10b981;
    }

    .alert-error {
      background-color: #fee2e2;
      color: #b91c1c;
      border-left: 4px solid #ef4444;
    }

    .navbar {
      background-color: #ffffff;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      padding: 1rem 2rem;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 100;
    }

    .navbar-container {
      display: flex;
      justify-content: space-between;
      align-items: center;
      max-width: 1200px;
      margin: 0 auto;
    }

    .navbar-brand {
      font-size: 1.25rem;
      font-weight: 600;
      color: #3b82f6;
      text-decoration: none;
    }

    .navbar-links {
      display: flex;
      gap: 1rem;
    }

    .nav-link {
      padding: 0.5rem 1rem;
      border-radius: 0.375rem;
      font-weight: 500;
      transition: all 0.2s;
    }

    .nav-link:hover {
      background-color: #f3f4f6;
    }

    .nav-link.active {
      background-color: #3b82f6;
      color: white;
    }
  </style>
</head>

<body class="antialiased bg-gray-100 ">
  <!-- Navbar -->
  <?php include 'navbar.php'; ?>


  <!-- Main Content -->
  <main class="max-w-7xl mx-auto px-4 sm:px-6 py-6 ">
    <!-- Status Message -->
    <?php if ($message): ?>
      <div class="alert-<?= $message_type ?> p-4 mb-6 rounded-md shadow-sm flex items-start">
        <div class="flex-shrink-0">
          <?php if ($message_type === 'success'): ?>
            <i class="fas fa-check-circle text-green-500"></i>
          <?php else: ?>
            <i class="fas fa-exclamation-circle text-red-500"></i>
          <?php endif; ?>
        </div>
        <div class="ml-3">
          <p class="text-sm font-medium">
            <?= htmlspecialchars($message) ?>
          </p>
        </div>
        <div class="ml-auto pl-3">
          <button onclick="this.parentElement.parentElement.remove()" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
    <?php endif; ?>

    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-900 flex items-center">
        <i class="fas fa-server mr-3 text-indigo-600"></i>
        SMTP Records
      </h1>
      <button onclick="document.getElementById('addServerModal').classList.remove('hidden')"
        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
        <i class="fas fa-plus mr-2"></i> Add SMTP Server
      </button>
    </div>

    <!-- SMTP Servers Table -->
    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Name
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Host
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Email
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Status
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Hourly Limit
              </th>

              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Daily Limit
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($smtpServers as $server): ?>
              <tr>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="text-sm font-medium text-gray-900">
                      <?= htmlspecialchars($server['name']) ?>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900"><?= htmlspecialchars($server['host']) ?></div>
                  <div class="text-sm text-gray-500">
                    Port: <?= $server['port'] ?> (<?= strtoupper($server['encryption']) ?>)
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  <?= htmlspecialchars($server['email']) ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                  <?= $server['is_active'] ? 'status-active' : 'status-inactive' ?>">
                    <?= $server['is_active'] ? 'Active' : 'Inactive' ?>
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  <?= $server['hourly_limit'] ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  <?= $server['daily_limit'] ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <a href="?edit=<?= $server['id'] ?>#editServerModal" class="text-indigo-600 hover:text-indigo-900 mr-3">
                    <i class="fas fa-edit mr-1"></i> Edit
                  </a>
                  <a href="#" onclick="confirmDelete(<?= $server['id'] ?>)" class="text-red-600 hover:text-red-900">
                    <i class="fas fa-trash mr-1"></i> Delete
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($smtpServers)): ?>
              <tr>
                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                  No SMTP servers found. Add one to get started.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add Server Modal -->
    <div id="addServerModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
      <div class="relative top-20 mx-auto p-5 border w-1/2 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-medium text-gray-900">
            <i class="fas fa-plus-circle mr-2 text-indigo-600"></i>
            Add New SMTP Server
          </h3>
          <button onclick="document.getElementById('addServerModal').classList.add('hidden')"
            class="text-gray-400 hover:text-gray-500">
            <i class="fas fa-times"></i>
          </button>
        </div>

        <form method="POST" class="space-y-4">
          <!-- 1st Row: Name + Host -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
              <input type="text" name="name" required
                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                placeholder="SMTP1">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Host</label>
              <input type="text" name="host" required
                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                placeholder="smtp.example.com">
            </div>
          </div>

          <!-- 2nd Row: Port + Encryption + Hourly Limit -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
              <input type="number" name="port" required
                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                placeholder="465" value="465">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Encryption</label>
              <select name="encryption" required
                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                <option value="ssl">SSL</option>
                <option value="tls">TLS</option>
                <option value="">None</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Hourly Limit</label>
              <input type="number" name="hourly_limit" required
                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                placeholder="100" value="100">
            </div>
          </div>

          <!-- 3rd Row: Daily Limit + Email + Password -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Daily Limit</label>
              <input type="number" name="daily_limit" required
                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                placeholder="500" value="500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
              <input type="email" name="email" required
                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                placeholder="user@example.com">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
              <input type="password" name="password" required
                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                placeholder="SMTP password">
            </div>
          </div>

          <div class="flex items-center">
            <input type="checkbox" name="is_active" id="is_active" checked
              class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
            <label for="is_active" class="ml-2 block text-sm text-gray-700">
              Active
            </label>
          </div>

          <div class="flex justify-end pt-4 space-x-3">
            <button type="button" onclick="document.getElementById('addServerModal').classList.add('hidden')"
              class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
              Cancel
            </button>
            <button type="submit" name="add_smtp"
              class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
              <i class="fas fa-save mr-2"></i> Save Server
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit Server Modal -->
    <?php if ($editServer): ?>
      <div id="editServerModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-1/2 shadow-lg rounded-md bg-white">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">
              <i class="fas fa-edit mr-2 text-indigo-600"></i>
              Edit SMTP Server
            </h3>
            <a href="smtp_records.php" class="text-gray-400 hover:text-gray-500">
              <i class="fas fa-times"></i>
            </a>
          </div>

          <form method="POST" class="space-y-4">
            <input type="hidden" name="id" value="<?= $editServer['id'] ?>">

            <!-- 1st Row: Name + Host -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" name="name" required
                  class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  value="<?= htmlspecialchars($editServer['name']) ?>">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Host</label>
                <input type="text" name="host" required
                  class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  value="<?= htmlspecialchars($editServer['host']) ?>">
              </div>
            </div>

            <!-- 2nd Row: Port + Encryption + Hourly Limit -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
                <input type="number" name="port" required
                  class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  value="<?= $editServer['port'] ?>">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Encryption</label>
                <select name="encryption" required
                  class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                  <option value="ssl" <?= $editServer['encryption'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                  <option value="tls" <?= $editServer['encryption'] === 'tls' ? 'selected' : '' ?>>TLS</option>
                  <option value="" <?= empty($editServer['encryption']) ? 'selected' : '' ?>>None</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Hourly Limit</label>
                <input type="number" name="hourly_limit" required
                  class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  value="<?= $editServer['hourly_limit'] ?>">
              </div>
            </div>

            <!-- 3rd Row: Daily Limit + Email + Password -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Daily Limit</label>
                <input type="number" name="daily_limit" required
                  class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  value="<?= $editServer['daily_limit'] ?>">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" required
                  class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  value="<?= htmlspecialchars($editServer['email']) ?>">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" name="password" required
                  class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  value="<?= htmlspecialchars($editServer['password']) ?>">
              </div>
            </div>

            <div class="flex items-center">
              <input type="checkbox" name="is_active" id="edit_is_active"
                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded" <?= $editServer['is_active'] ? 'checked' : '' ?>>
              <label for="edit_is_active" class="ml-2 block text-sm text-gray-700">
                Active
              </label>
            </div>

            <div class="flex justify-end pt-4 space-x-3">
              <a href="smtp_records.php"
                class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Cancel
              </a>
              <button type="submit" name="update_smtp"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <i class="fas fa-save mr-2"></i> Update Server
              </button>
            </div>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </main>

  <script>
    // Close modals when clicking outside
    window.addEventListener('click', function (event) {
      const addModal = document.getElementById('addServerModal');
      if (event.target === addModal) {
        addModal.classList.add('hidden');
      }

      <?php if ($editServer): ?>
        const editModal = document.getElementById('editServerModal');
        if (event.target === editModal) {
          window.location.href = 'smtp_records.php';
        }
      <?php endif; ?>
    });

    // Confirm delete function
    function confirmDelete(id) {
      if (confirm('Are you sure you want to delete this SMTP server?')) {
        window.location.href = 'smtp_records.php?delete=' + id;
      }
    }

    // Scroll to edit modal if it exists
    <?php if ($editServer): ?>
      document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('editServerModal').scrollIntoView({ behavior: 'smooth' });
      });
    <?php endif; ?>

    // Auto-hide success message after 5 seconds
    <?php if ($message_type === 'success'): ?>
      setTimeout(() => {
        const alert = document.querySelector('.alert-success');
        if (alert) alert.remove();
      }, 5000);
    <?php endif; ?>
  </script>
</body>

</html>