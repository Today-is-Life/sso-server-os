<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>TIL SSO Admin</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
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
    <!-- Vue.js SSO Admin App -->
    <div id="vue-app" class="app-container">
        <sso-admin-app></sso-admin-app>
    </div>
    
    <script>
        // Vue.js will handle all the functionality now
        window.csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        window.currentUser = {
            name: '{{ Auth::user()->name ?? "" }}',
            email: '{{ Auth::user()->email ?? "" }}'
        };
    </script>
</body>
</html>