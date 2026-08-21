<?php
// ==========================================
// 1. CONFIGURATION & DATABASE CONNECTION
// ==========================================

// "host.docker.internal" maps to your actual computer's localhost 
// (provided you added the extra_hosts in docker-compose.yml)
$host = "192.168.55.120"; 

// UPDATE THESE WITH YOUR EXISTING POSTGRES CREDENTIALS
$port = "5432";
$dbname = "postgres";  // <--- CHANGE THIS
$user = "admin";   // <--- CHANGE THIS
$password = "P@ssw0rd123"; // <--- CHANGE THIS

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    // If connection fails, show a clean error message
    die("<div style='font-family:sans-serif; padding:20px; color:red; background:#ffeeee; border:1px solid red;'>
            <strong>Database Connection Failed:</strong><br>" . $e->getMessage() . 
            "<br><br><em>Tip: Ensure your host Postgres pg_hba.conf allows connections from Docker IPs.</em>
         </div>");
}

// ==========================================
// 2. API HANDLER (AJAX REQUESTS)
// ==========================================

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $nodeName = $_GET['node_name'] ?? '';

    // FETCH DESCRIPTION
    if ($_GET['action'] === 'get_description') {
        // Query matches your 'node_description' table structure
        $stmt = $pdo->prepare("SELECT node_name, location, node_owner, noc_number FROM node_description WHERE node_name = :name");
        $stmt->execute(['name' => $nodeName]);
        $result = $stmt->fetch();
        echo json_encode($result ?: null);
        exit;
    }

    // FETCH STATUS HISTORY
    if ($_GET['action'] === 'get_status') {
        // Query matches your 'node_status' table structure
        // Limits to last 5 records so the modal doesn't get too long
        $stmt = $pdo->prepare("SELECT operational_status, cpu_utilization, memory_utilization FROM node_status WHERE node_name = :name ORDER BY id DESC LIMIT 5");
        $stmt->execute(['name' => $nodeName]);
        echo json_encode($stmt->fetchAll());
        exit;
    }
}

// ==========================================
// 3. MAIN PAGE DATA FETCH
// ==========================================
// Query matches your 'nodes' table structure
$nodes = $pdo->query("SELECT id, node_name, ip_address, machine_type, polling_method FROM nodes ORDER BY id ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>NetNode Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .modal { transition: opacity 0.25s ease; }
        body.modal-open { overflow: hidden; }
        /* Custom scrollbar for table body if needed */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">

    <div class="max-w-6xl mx-auto">
        <div class="flex items-center space-x-3 mb-8">
            <div class="bg-indigo-600 p-3 rounded-lg shadow-lg">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
            </div>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">NetNode Manager</h1>
                <p class="text-sm text-gray-500">Live Host Database Connection</p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-gray-600 font-semibold uppercase tracking-wider border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4">Node Name</th>
                            <th class="px-6 py-4">IP Address</th>
                            <th class="px-6 py-4">Machine Type</th>
                            <th class="px-6 py-4">Polling Method</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (count($nodes) > 0): ?>
                            <?php foreach ($nodes as $node): ?>
                            <tr class="hover:bg-indigo-50 transition-colors duration-150">
                                <td class="px-6 py-4">
                                    <button onclick="openDescription('<?= htmlspecialchars($node['node_name']) ?>')" class="font-bold text-indigo-600 hover:text-indigo-800 hover:underline">
                                        <?= htmlspecialchars($node['node_name']) ?>
                                    </button>
                                </td>
                                <td class="px-6 py-4">
                                    <button onclick="openStatus('<?= htmlspecialchars($node['ip_address']) ?>', '<?= htmlspecialchars($node['node_name']) ?>')" class="font-mono text-emerald-600 hover:text-emerald-800 hover:underline bg-emerald-50 px-2 py-1 rounded border border-emerald-100">
                                        <?= htmlspecialchars($node['ip_address']) ?>
                                    </button>
                                </td>
                                <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($node['machine_type']) ?></td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        <?= htmlspecialchars($node['polling_method']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500 italic">No nodes found in the database.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="desc-modal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-600 bg-opacity-75 transition-opacity backdrop-blur-sm" onclick="closeModal('desc-modal')"></div>
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                <div class="bg-indigo-600 px-4 py-3 sm:px-6 flex justify-between items-center">
                    <h3 class="text-base font-semibold leading-6 text-white" id="modal-title">Node Details</h3>
                    <button onclick="closeModal('desc-modal')" class="text-indigo-200 hover:text-white">&times;</button>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <div id="desc-content" class="space-y-4">
                        </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 border-t border-gray-100">
                    <button type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto" onclick="closeModal('desc-modal')">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="status-modal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-600 bg-opacity-75 transition-opacity backdrop-blur-sm" onclick="closeModal('status-modal')"></div>
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-xl">
                <div class="bg-gray-800 px-4 py-3 sm:px-6 flex justify-between items-center">
                    <h3 class="text-base font-semibold leading-6 text-white" id="status-modal-title">Node Status</h3>
                    <button onclick="closeModal('status-modal')" class="text-gray-400 hover:text-white">&times;</button>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <div class="overflow-hidden rounded-lg border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="py-3 pl-4 pr-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Oper. Status</th>
                                    <th scope="col" class="px-3 py-3 text-right text-xs font-bold uppercase tracking-wide text-gray-500">CPU Load</th>
                                    <th scope="col" class="px-3 py-3 text-right text-xs font-bold uppercase tracking-wide text-gray-500">Mem Usage</th>
                                </tr>
                            </thead>
                            <tbody id="status-table-body" class="divide-y divide-gray-200 bg-white">
                                </tbody>
                        </table>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 border-t border-gray-100">
                    <button type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto" onclick="closeModal('status-modal')">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ==========================================
        // DYNAMIC JAVASCRIPT LOGIC
        // ==========================================

        async function openDescription(nodeName) {
            // 1. Show Modal with loading state
            const contentDiv = document.getElementById('desc-content');
            contentDiv.innerHTML = '<div class="flex justify-center p-4"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div></div>';
            document.getElementById('desc-modal').classList.remove('hidden');

            try {
                // 2. Fetch data (node_description table)
                const response = await fetch(`?action=get_description&node_name=${encodeURIComponent(nodeName)}`);
                const desc = await response.json();

                // 3. Render
                if (desc) {
                    contentDiv.innerHTML = `
                        <div class="grid grid-cols-1 gap-4">
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <p class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-1">Node Name</p>
                                <p class="font-bold text-xl text-gray-800">${desc.node_name}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-1">Location</p>
                                    <p class="font-medium text-gray-700">${desc.location}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-1">Owner</p>
                                    <p class="font-medium text-gray-700">${desc.node_owner}</p>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-1">NOC Number</p>
                                <p class="font-mono text-indigo-600 bg-indigo-50 inline-block px-2 py-1 rounded border border-indigo-100">${desc.noc_number}</p>
                            </div>
                        </div>
                    `;
                } else {
                    contentDiv.innerHTML = '<div class="text-center p-4 bg-yellow-50 text-yellow-700 rounded-lg">No description data found for this node.</div>';
                }
            } catch (error) {
                console.error(error);
                contentDiv.innerHTML = '<div class="text-center p-4 bg-red-50 text-red-700 rounded-lg">Error fetching data from server.</div>';
            }
        }

        async function openStatus(ipAddress, nodeName) {
            // 1. Setup Modal
            const tbody = document.getElementById('status-table-body');
            tbody.innerHTML = '<tr><td colspan="3" class="text-center py-6 text-gray-500"><div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-gray-600"></div> Loading...</td></tr>';
            document.getElementById('status-modal-title').innerText = `${nodeName} (${ipAddress})`;
            document.getElementById('status-modal').classList.remove('hidden');

            try {
                // 2. Fetch data (node_status table)
                const response = await fetch(`?action=get_status&node_name=${encodeURIComponent(nodeName)}`);
                const statusList = await response.json();

                // 3. Render
                tbody.innerHTML = '';
                if (statusList && statusList.length > 0) {
                    statusList.forEach(stat => {
                        let badgeClass = 'bg-gray-100 text-gray-800 ring-gray-500/10';
                        if (stat.operational_status === 'UP') badgeClass = 'bg-green-100 text-green-700 ring-green-600/20';
                        if (stat.operational_status === 'DOWN') badgeClass = 'bg-red-100 text-red-700 ring-red-600/10';
                        if (stat.operational_status === 'WARNING') badgeClass = 'bg-yellow-100 text-yellow-800 ring-yellow-600/20';

                        // Format numbers to look nice
                        const cpu = parseFloat(stat.cpu_utilization).toFixed(1);
                        const mem = parseFloat(stat.memory_utilization).toFixed(1);

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td class="whitespace-nowrap py-3 pl-4 pr-3 text-sm font-medium">
                                 <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-bold ring-1 ring-inset ${badgeClass}">
                                    ${stat.operational_status}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-600 text-right font-mono font-medium">${cpu}%</td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-600 text-right font-mono font-medium">${mem}%</td>
                        `;
                        tbody.appendChild(tr);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center py-6 text-gray-400 italic">No status history records found.</td></tr>';
                }
            } catch (error) {
                console.error(error);
                tbody.innerHTML = '<tr><td colspan="3" class="text-center py-6 text-red-500">Failed to load status history.</td></tr>';
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }
        
        // Close modal on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === "Escape") {
                closeModal('desc-modal');
                closeModal('status-modal');
            }
        });
    </script>
</body>
</html>
