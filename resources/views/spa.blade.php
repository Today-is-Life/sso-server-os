<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>TIL SSO Admin</title>
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- HTMX -->
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <script src="https://unpkg.com/htmx.org/dist/ext/json-enc.js"></script>
    
    <!-- SortableJS für Drag & Drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --secondary: #48bb78;
            --danger: #f56565;
            --warning: #ed8936;
            --dark: #2d3748;
            --light: #f7fafc;
            --border: #e2e8f0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f7fafc;
            color: var(--dark);
        }
        
        /* Layout */
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 250px;
            background: white;
            border-right: 1px solid var(--border);
            padding: 1rem;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }
        
        /* Navigation */
        .nav-item {
            display: block;
            padding: 0.75rem 1rem;
            margin: 0.25rem 0;
            color: var(--dark);
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .nav-item:hover {
            background: var(--light);
            color: var(--primary);
        }
        
        .nav-item.active {
            background: var(--primary);
            color: white;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.625rem;
            border: 1px solid var(--border);
            border-radius: 0.375rem;
            font-size: 1rem;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.625rem 1.25rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }
        
        .btn-secondary {
            background: var(--dark);
        }
        
        .btn-success {
            background: var(--secondary);
        }
        
        .btn-danger {
            background: var(--danger);
        }
        
        .btn-group {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Tables */
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background: var(--light);
            padding: 0.75rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            border-bottom: 2px solid var(--border);
        }
        
        .table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border);
        }
        
        .table tr:hover {
            background: #fafafa;
        }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.625rem;
            background: var(--light);
            color: var(--dark);
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-primary {
            background: var(--primary);
            color: white;
        }
        
        .badge-success {
            background: var(--secondary);
            color: white;
        }
        
        .badge-danger {
            background: var(--danger);
            color: white;
        }
        
        /* Tree View für Gruppen */
        .tree-item {
            margin-left: 1.5rem;
            padding: 0.5rem;
            border-left: 2px solid var(--border);
        }
        
        .tree-node {
            display: flex;
            align-items: center;
            padding: 0.5rem;
            margin: 0.25rem 0;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .tree-node:hover {
            background: var(--light);
        }
        
        .tree-node.dragging {
            opacity: 0.5;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: #718096;
            margin-top: 0.25rem;
        }
        
        /* Loading States */
        .htmx-indicator {
            display: none;
        }
        
        .htmx-request .htmx-indicator {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        
        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #fc8181;
        }
    </style>
</head>
<body>
    <div class="app-container" x-data="ssoApp()">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div style="padding: 1rem 0; margin-bottom: 1rem; border-bottom: 1px solid var(--border);">
                <h2 style="font-size: 1.5rem; color: var(--primary);">SSO Admin</h2>
            </div>
            
            <nav>
                <a class="nav-item" 
                   :class="{ active: currentView === 'dashboard' }"
                   @click="navigate('dashboard')"
                   hx-get="/admin/dashboard"
                   hx-target="#main-content"
                   hx-push-url="true">
                    📊 Dashboard
                </a>
                
                <a class="nav-item"
                   :class="{ active: currentView === 'users' }"
                   @click="navigate('users')"
                   hx-get="/admin/users"
                   hx-target="#main-content"
                   hx-push-url="true">
                    👥 Benutzer
                </a>
                
                <a class="nav-item"
                   :class="{ active: currentView === 'groups' }"
                   @click="navigate('groups')"
                   hx-get="/admin/groups"
                   hx-target="#main-content"
                   hx-push-url="true">
                    🏢 Gruppen
                </a>
                
                <a class="nav-item"
                   :class="{ active: currentView === 'domains' }"
                   @click="navigate('domains')"
                   hx-get="/admin/domains"
                   hx-target="#main-content"
                   hx-push-url="true">
                    🌐 Domains
                </a>
                
                <a class="nav-item"
                   :class="{ active: currentView === 'permissions' }"
                   @click="navigate('permissions')"
                   hx-get="/admin/permissions"
                   hx-target="#main-content"
                   hx-push-url="true">
                    🔐 Berechtigungen
                </a>
            </nav>
            
            <div style="margin-top: auto; padding-top: 1rem;">
                <div style="padding: 1rem; background: var(--light); border-radius: 0.5rem;">
                    <div style="font-weight: 600;">{{ Auth::user()->name ?? 'Nicht angemeldet' }}</div>
                    <div style="font-size: 0.75rem; color: #718096;">{{ Auth::user()->email ?? '' }}</div>
                    <form action="/auth/logout" method="POST" style="margin-top: 0.5rem;">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-secondary" style="width: 100%;">Logout</button>
                    </form>
                </div>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content" id="main-content">
            <!-- Content will be loaded here via HTMX -->
            <div hx-get="/admin/dashboard" hx-trigger="load"></div>
        </main>
    </div>
    
    <script>
        // Alpine.js App
        function ssoApp() {
            return {
                currentView: 'dashboard',
                
                navigate(view) {
                    this.currentView = view;
                },
                
                confirmDelete(message = 'Wirklich löschen?') {
                    return confirm(message);
                }
            }
        }
        
        // HTMX Configuration
        document.body.addEventListener('htmx:configRequest', (event) => {
            event.detail.headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;
        });
        
        // Initialize drag & drop for groups
        document.addEventListener('htmx:afterSwap', (event) => {
            const groupTrees = document.querySelectorAll('.group-tree');
            groupTrees.forEach(tree => {
                new Sortable(tree, {
                    group: 'groups',
                    animation: 150,
                    ghostClass: 'dragging',
                    onEnd: function(evt) {
                        // Send update to server
                        fetch(`/api/admin/groups/${evt.item.dataset.groupId}/move`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                parent_id: evt.to.dataset.parentId,
                                sort_order: evt.newIndex
                            })
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>