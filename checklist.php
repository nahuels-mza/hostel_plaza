<?php
session_start();
// Ensure someone is logged in (staff or admin)
if (!isset($_SESSION['staff_logged_in']) && !isset($_SESSION['admin_logged_in'])) {
    // header('Location: login.php');
    // exit;
}

$tasksFile = 'tasks.json';
if (!file_exists($tasksFile)) {
    file_put_contents($tasksFile, json_encode([], JSON_PRETTY_PRINT));
}
$staffTasks = json_decode(file_get_contents($tasksFile), true);

// Handle ticking/unticking a task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_task'])) {
    $listId = $_POST['list_id'];
    $taskId = $_POST['task_id'];
    
    foreach ($staffTasks as &$list) {
        if ($list['id'] === $listId) {
            foreach ($list['tasks'] as &$task) {
                if ($task['id'] === $taskId) {
                    $task['done'] = !$task['done']; // Toggle boolean
                    break 2;
                }
            }
        }
    }
    file_put_contents($tasksFile, json_encode($staffTasks, JSON_PRETTY_PRINT));
    header("Location: checklist.php"); 
    exit;
}

// Helper to get today's date formatted nicely
$todayDate = date('l, jS \of F Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Daily Rota & Checklist | Hostel Plaza</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,700;1,400&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = {
            theme: { 
                extend: { 
                    colors: { teal: { DEFAULT: '#1c5457', hover: '#144042', light: '#e8f0f0' } },
                    fontFamily: { serif: ['"Playfair Display"', 'serif'], sans: ['"Inter"', 'sans-serif'] } 
                } 
            }
        }
    </script>
    <style>
        input[type="checkbox"] { accent-color: #10b981; }
        /* Hide scrollbar for a cleaner look on mobile */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="bg-[#F8FAFC] font-sans text-slate-900 min-h-screen flex flex-col">

    <header class="bg-slate-900 text-white sticky top-0 z-50 shadow-md">
        <div class="max-w-7xl mx-auto px-6 py-5 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-white/10 rounded-xl flex items-center justify-center">
                    <i data-lucide="clipboard-check" class="w-6 h-6 text-teal-300"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-serif font-bold tracking-wide leading-tight">Daily Staff Rota</h1>
                    <p class="text-xs text-slate-400 font-medium uppercase tracking-widest mt-1"><?php echo $todayDate; ?></p>
                </div>
            </div>
            <button onclick="window.location.reload()" class="flex items-center gap-2 bg-white/10 hover:bg-white/20 transition-colors px-4 py-2 rounded-lg text-sm font-bold border border-white/5">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i> <span class="hidden sm:inline">Refresh Sync</span>
            </button>
        </div>
    </header>

    <main class="flex-1 max-w-7xl mx-auto w-full px-4 sm:px-6 py-8">
        
        <?php if(empty($staffTasks)): ?>
            <div class="text-center text-slate-400 py-20 bg-white rounded-3xl border border-slate-200 shadow-sm">
                <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="check-circle-2" class="w-10 h-10 text-slate-300"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-700">No tasks assigned for today.</h3>
                <p class="mt-2 text-sm">Waiting for admin to populate the checklist.</p>
            </div>
        <?php else: ?>
            
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                
                <div class="hidden md:grid grid-cols-12 bg-slate-50 border-b border-slate-200 px-6 py-4">
                    <div class="col-span-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Staff Member & Role</div>
                    <div class="col-span-9 text-xs font-bold text-slate-500 uppercase tracking-wider">Checklist Items</div>
                </div>

                <div class="divide-y divide-slate-100">
                    <?php foreach($staffTasks as $list): 
                        
                        // Calculate progress
                        $totalTasks = count($list['tasks']);
                        $completedTasks = 0;
                        foreach($list['tasks'] as $t) { if($t['done']) $completedTasks++; }
                        $progressPercent = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
                        
                        // Get initials for avatar
                        $nameParts = explode(" ", $list['name']);
                        $initials = count($nameParts) > 1 ? substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1) : substr($nameParts[0], 0, 1);
                    ?>
                        <div class="grid grid-cols-1 md:grid-cols-12 transition-colors hover:bg-slate-50/50 group">
                            
                            <div class="md:col-span-3 p-6 md:border-r border-slate-100 flex flex-col justify-center bg-slate-50/30">
                                <div class="flex items-center gap-4 mb-4">
                                    <div class="w-12 h-12 rounded-full flex items-center justify-center text-white font-bold text-lg shadow-sm <?php echo $list['color']; ?>">
                                        <?php echo strtoupper($initials); ?>
                                    </div>
                                    <div>
                                        <h2 class="font-bold text-slate-900 text-base leading-tight"><?php echo htmlspecialchars($list['name']); ?></h2>
                                    </div>
                                </div>

                                <div class="w-full">
                                    <div class="flex justify-between text-[10px] font-bold uppercase tracking-wider mb-1.5 text-slate-500">
                                        <span>Progress</span>
                                        <span class="<?php echo $progressPercent === 100 ? 'text-emerald-600' : 'text-slate-500'; ?>"><?php echo $completedTasks; ?> / <?php echo $totalTasks; ?></span>
                                    </div>
                                    <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden">
                                        <div class="bg-teal h-1.5 rounded-full transition-all duration-500 <?php echo $progressPercent === 100 ? 'bg-emerald-500' : ''; ?>" style="width: <?php echo $progressPercent; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="md:col-span-9 p-6">
                                <?php if(empty($list['tasks'])): ?>
                                    <div class="h-full flex items-center justify-center">
                                        <span class="text-sm text-slate-400 italic">No tasks assigned.</span>
                                    </div>
                                <?php else: ?>
                                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-3">
                                        <?php foreach($list['tasks'] as $task): ?>
                                            <label class="flex items-start gap-4 p-4 rounded-xl border transition-all cursor-pointer shadow-sm
                                                <?php echo $task['done'] ? 'border-emerald-200 bg-emerald-50/30' : 'border-slate-200 bg-white hover:border-teal/30 hover:shadow-md'; ?>">
                                                
                                                <form method="POST" action="checklist.php" class="m-0 p-0 flex-shrink-0 mt-0.5">
                                                    <input type="hidden" name="toggle_task" value="1">
                                                    <input type="hidden" name="list_id" value="<?php echo $list['id']; ?>">
                                                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                    <input type="checkbox" onChange="this.form.submit()" class="w-6 h-6 rounded-md border-slate-300 cursor-pointer shadow-sm focus:ring-emerald-500 transition-colors" <?php echo $task['done'] ? 'checked' : ''; ?>>
                                                </form>
                                                
                                                <div class="flex-1 flex flex-col justify-center">
                                                    <span class="text-[15px] <?php echo $task['done'] ? 'text-slate-400 line-through' : 'text-slate-800 font-semibold'; ?> leading-snug">
                                                        <?php echo htmlspecialchars($task['text']); ?>
                                                    </span>
                                                </div>
                                                
                                                <?php if($task['done']): ?>
                                                    <i data-lucide="check" class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5"></i>
                                                <?php endif; ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php endif; ?>
    </main>

    <script>
        lucide.createIcons();
        // Auto refresh every 2 minutes so staff always see updates pushed by the Admin
        setTimeout(() => { window.location.reload(); }, 120000);
    </script>
</body>
</html>