<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todo List - 任務管理</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }



        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .header p {
            font-size: 1.1em;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .content {
            padding: 30px;
        }

        .add-task-form {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transform: translateY(0);
            transition: all 0.3s ease;
        }

        .add-task-form:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
            font-size: 0.9em;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
        }

        .tasks-container {
            display: grid;
            gap: 40px;
        }

        .task-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border-left: 5px solid #667eea;
            transform: translateX(0);
            transition: all 0.3s ease;
            animation: slideIn 0.5s ease;
            margin-bottom: 25px;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .task-card:hover {
            transform: translateX(5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .task-card.completed {
            border-left-color: #28a745;
            opacity: 0.8;
        }

        .task-card.completed .task-title {
            text-decoration: line-through;
            color: #6c757d;
        }

        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .task-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #212529;
            margin-bottom: 5px;
        }

        .task-description {
            color: #6c757d;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .task-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            background: #f8f9fa;
            border-radius: 20px;
            font-size: 0.85em;
            color: #495057;
        }

        .priority-high { background: #ffe6e6; color: #dc3545; }
        .priority-medium { background: #fff3cd; color: #856404; }
        .priority-low { background: #d1ecf1; color: #0c5460; }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-in_progress { background: #d1ecf1; color: #0c5460; }
        .status-completed { background: #d4edda; color: #155724; }

        .task-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 8px 15px;
            font-size: 0.85em;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .search-form {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transform: translateY(0);
            transition: all 0.3s ease;
        }

        .search-form:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }

        .search-container {
            display: flex;
            justify-content: center;
        }

        .search-input-group {
            display: flex;
            gap: 10px;
            align-items: center;
            max-width: 500px;
            width: 100%;
        }

        .search-input {
            flex: 1;
            padding: 12px 20px;
            border: 2px solid #e9ecef;
            border-radius: 25px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: white;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-btn, .clear-btn {
            padding: 12px 20px;
            border-radius: 25px;
            white-space: nowrap;
            min-width: 100px;
        }

        .search-btn:hover, .clear-btn:hover {
            transform: translateY(-2px);
        }

        .search-results-info {
            text-align: center;
            padding: 15px;
            background: #e3f2fd;
            border-radius: 10px;
            margin-bottom: 20px;
            color: #1976d2;
            font-weight: 500;
        }

        .filter-form {
            background: #f8f9fa;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            cursor: pointer;
        }

        .filter-header h3 {
            margin: 0;
            font-size: 1.2em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-content {
            padding: 25px;
            background: white;
            border-top: 1px solid #e9ecef;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-row:last-child {
            margin-bottom: 0;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
            font-size: 0.9em;
        }

        .filter-select,
        .filter-input {
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.95em;
            transition: all 0.3s ease;
            background: white;
        }

        .filter-select:focus,
        .filter-input:focus {
            outline: none;
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: end;
            flex-wrap: wrap;
        }

        .filter-actions .btn {
            padding: 10px 20px;
            font-size: 0.9em;
            border-radius: 8px;
        }

        .filter-active {
            background: #d4edda;
            border-color: #28a745;
        }

        .filter-summary {
            background: #e3f2fd;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: #1976d2;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .task-meta {
                flex-direction: column;
            }
            
            .task-actions {
                justify-content: center;
            }

            .search-input-group {
                flex-direction: column;
                gap: 15px;
            }

            .search-input {
                width: 100%;
            }

            .search-btn, .clear-btn {
                width: 100%;
            }

            .filter-row {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                justify-content: center;
            }

            .filter-actions .btn {
                flex: 1;
                min-width: 120px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-tasks"></i> Todo List</h1>
            <p>管理您的任務，提升工作效率</p>
        </div>

        <div class="content">
            <!-- 新增任務表單 -->
            <div class="add-task-form">
                <form id="addTaskForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="title">任務標題 *</label>
                            <input type="text" id="title" name="title" required placeholder="輸入任務標題">
                        </div>
                        <div class="form-group">
                            <label for="priority">優先級</label>
                            <select id="priority" name="priority">
                                <option value="1">低</option>
                                <option value="2" selected>中</option>
                                <option value="3">高</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status">狀態</label>
                            <select id="status" name="status">
                                <option value="pending" selected>待處理</option>
                                <option value="in_progress">進行中</option>
                                <option value="completed">已完成</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="due_date">截止日期</label>
                            <input type="date" id="due_date" name="due_date">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> 新增
                        </button>
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <label for="description">描述</label>
                        <textarea id="description" name="description" rows="3" placeholder="任務描述（可選）"></textarea>
                    </div>
                </form>
            </div>

            <!-- 搜尋功能 -->
            <div class="search-form">
                <div class="search-container">
                    <div class="search-input-group">
                        <input type="text" id="searchInput" placeholder="搜尋任務標題..." class="search-input">
                        <button type="button" id="searchBtn" class="btn btn-primary search-btn">
                            <i class="fas fa-search"></i> 搜尋
                        </button>
                        <button type="button" id="clearSearchBtn" class="btn btn-warning clear-btn" style="display: none;">
                            <i class="fas fa-times"></i> 清除
                        </button>
                    </div>
                </div>
            </div>

            <!-- 篩選和排序功能 -->
            <div class="filter-form">
                <div class="filter-header">
                    <h3><i class="fas fa-filter"></i> 篩選和排序</h3>
                    <button type="button" id="toggleFilterBtn" class="btn btn-primary btn-sm">
                        <i class="fas fa-chevron-down"></i> 展開篩選
                    </button>
                </div>
                
                <div class="filter-content" id="filterContent" style="display: none;">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="filterStatus">狀態</label>
                            <select id="filterStatus" class="filter-select">
                                <option value="all">全部狀態</option>
                                <option value="pending">待處理</option>
                                <option value="in_progress">進行中</option>
                                <option value="completed">已完成</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="filterPriority">優先級</label>
                            <select id="filterPriority" class="filter-select">
                                <option value="all">全部優先級</option>
                                <option value="1">低</option>
                                <option value="2">中</option>
                                <option value="3">高</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="filterDateFrom">建立日期（從）</label>
                            <input type="date" id="filterDateFrom" class="filter-input">
                        </div>
                        
                        <div class="filter-group">
                            <label for="filterDateTo">建立日期（到）</label>
                            <input type="date" id="filterDateTo" class="filter-input">
                        </div>
                    </div>
                    
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="sortBy">排序欄位</label>
                            <select id="sortBy" class="filter-select">
                                <option value="created_at">建立時間</option>
                                <option value="updated_at">更新時間</option>
                                <option value="title">標題</option>
                                <option value="priority">優先級</option>
                                <option value="status">狀態</option>
                                <option value="due_date">截止日期</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="sortOrder">排序方向</label>
                            <select id="sortOrder" class="filter-select">
                                <option value="desc">降序（新到舊）</option>
                                <option value="asc">升序（舊到新）</option>
                            </select>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="button" id="applyFilterBtn" class="btn btn-success">
                                <i class="fas fa-check"></i> 套用篩選
                            </button>
                            <button type="button" id="clearFilterBtn" class="btn btn-warning">
                                <i class="fas fa-times"></i> 清除篩選
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 提示訊息 -->
            <div id="alertContainer"></div>

            <!-- 任務列表 -->
            <div id="tasksContainer">
                <div class="loading">
                    <div class="spinner"></div>
                    <p>載入中...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        class TodoApp {
            constructor() {
                this.tasks = [];
                this.init();
            }

            init() {
                this.bindEvents();
                this.loadTasks();
            }

            bindEvents() {
                document.getElementById('addTaskForm').addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.addTask();
                });

                // 搜尋功能事件綁定
                document.getElementById('searchBtn').addEventListener('click', () => {
                    this.performSearch();
                });

                document.getElementById('clearSearchBtn').addEventListener('click', () => {
                    this.clearSearch();
                });

                // 按 Enter 鍵搜尋
                document.getElementById('searchInput').addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this.performSearch();
                    }
                });

                // 即時搜尋（可選）
                document.getElementById('searchInput').addEventListener('input', (e) => {
                    if (e.target.value.length > 2) {
                        // 可以實作即時搜尋，這裡先不實作
                    }
                });

                // 篩選功能事件綁定
                document.getElementById('toggleFilterBtn').addEventListener('click', () => {
                    this.toggleFilter();
                });

                document.getElementById('applyFilterBtn').addEventListener('click', () => {
                    this.applyFilter();
                });

                document.getElementById('clearFilterBtn').addEventListener('click', () => {
                    this.clearFilter();
                });
            }

            async loadTasks() {
                try {
                    const response = await fetch('/api/tasks');
                    const result = await response.json();
                    
                    if (result.success) {
                        this.tasks = result.data;
                        this.renderTasks();
                    }
                } catch (error) {
                    this.showAlert('載入任務失敗', 'error');
                }
            }

            async addTask() {
                const formData = new FormData(document.getElementById('addTaskForm'));
                const taskData = {
                    title: formData.get('title'),
                    description: formData.get('description'),
                    priority: parseInt(formData.get('priority')),
                    status: formData.get('status'),
                    due_date: formData.get('due_date') || null
                };

                try {
                    const response = await fetch('/api/tasks', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify(taskData)
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        this.showAlert('任務新增成功！', 'success');
                        document.getElementById('addTaskForm').reset();
                        this.loadTasks();
                    } else {
                        this.showAlert(result.message || '新增失敗', 'error');
                    }
                } catch (error) {
                    this.showAlert('新增任務失敗', 'error');
                }
            }

            async updateTask(id, data) {
                try {
                    const response = await fetch(`/api/tasks/${id}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        this.showAlert('任務更新成功！', 'success');
                        this.loadTasks();
                    } else {
                        this.showAlert(result.message || '更新失敗', 'error');
                    }
                } catch (error) {
                    this.showAlert('更新任務失敗', 'error');
                }
            }

            async deleteTask(id) {
                if (!confirm('確定要刪除這個任務嗎？')) return;

                try {
                    const response = await fetch(`/api/tasks/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        this.showAlert('任務刪除成功！', 'success');
                        this.loadTasks();
                    } else {
                        this.showAlert(result.message || '刪除失敗', 'error');
                    }
                } catch (error) {
                    this.showAlert('刪除任務失敗', 'error');
                }
            }

            renderTasks() {
                const container = document.getElementById('tasksContainer');
                
                if (this.tasks.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-clipboard-list"></i>
                            <h3>還沒有任務</h3>
                            <p>新增您的第一個任務開始工作吧！</p>
                        </div>
                    `;
                    return;
                }

                container.innerHTML = this.tasks.map(task => this.renderTask(task)).join('');
                
                // 綁定任務操作事件
                this.bindTaskEvents();
            }

            renderTask(task) {
                const priorityClass = `priority-${task.priority === 1 ? 'low' : task.priority === 2 ? 'medium' : 'high'}`;
                const statusClass = `status-${task.status}`;
                const completedClass = task.status === 'completed' ? 'completed' : '';
                
                // 手動處理優先級和狀態的文字顯示
                const priorityText = {
                    1: '低',
                    2: '中',
                    3: '高'
                }[task.priority] || '未知';
                
                const statusText = {
                    'pending': '待處理',
                    'in_progress': '進行中',
                    'completed': '已完成'
                }[task.status] || '未知';
                
                return `
                    <div class="task-card ${completedClass}" data-id="${task.id}">
                        <div class="task-header">
                            <div>
                                <div class="task-title">${task.title}</div>
                                ${task.description ? `<div class="task-description">${task.description}</div>` : ''}
                            </div>
                            <div class="task-actions">
                                <button class="btn btn-sm btn-warning edit-task" data-id="${task.id}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-task" data-id="${task.id}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="task-meta">
                            <span class="meta-item ${priorityClass}">
                                <i class="fas fa-flag"></i>
                                優先級: ${priorityText}
                            </span>
                            <span class="meta-item ${statusClass}">
                                <i class="fas fa-circle"></i>
                                狀態: ${statusText}
                            </span>
                            ${task.due_date ? `
                                <span class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    截止: ${new Date(task.due_date).toLocaleDateString('zh-TW')}
                                </span>
                            ` : ''}
                            <span class="meta-item">
                                <i class="fas fa-clock"></i>
                                建立: ${new Date(task.created_at).toLocaleDateString('zh-TW')}
                            </span>
                        </div>
                        
                        <div class="task-actions">
                            ${task.status !== 'completed' ? `
                                <button class="btn btn-sm btn-success complete-task" data-id="${task.id}">
                                    <i class="fas fa-check"></i> 完成
                                </button>
                            ` : `
                                <button class="btn btn-sm btn-warning reopen-task" data-id="${task.id}">
                                    <i class="fas fa-undo"></i> 重新開啟
                                </button>
                            `}
                        </div>
                    </div>
                `;
            }

            bindTaskEvents() {
                // 完成任務
                document.querySelectorAll('.complete-task').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const id = e.target.closest('.complete-task').dataset.id;
                        this.updateTask(id, { status: 'completed' });
                    });
                });

                // 重新開啟任務
                document.querySelectorAll('.reopen-task').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const id = e.target.closest('.reopen-task').dataset.id;
                        this.updateTask(id, { status: 'pending' });
                    });
                });

                // 編輯任務
                document.querySelectorAll('.edit-task').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const id = e.target.closest('.edit-task').dataset.id;
                        this.editTask(id);
                    });
                });

                // 刪除任務
                document.querySelectorAll('.delete-task').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const id = e.target.closest('.delete-task').dataset.id;
                        this.deleteTask(id);
                    });
                });
            }

            editTask(id) {
                const task = this.tasks.find(t => t.id == id);
                if (!task) return;

                // 簡單的編輯方式：填入表單
                document.getElementById('title').value = task.title;
                document.getElementById('description').value = task.description || '';
                document.getElementById('priority').value = task.priority;
                document.getElementById('status').value = task.status;
                document.getElementById('due_date').value = task.due_date || '';

                // 修改表單提交行為
                const form = document.getElementById('addTaskForm');
                form.dataset.editId = id;
                form.querySelector('button[type="submit"]').innerHTML = '<i class="fas fa-save"></i> 更新';
                
                // 滾動到表單
                form.scrollIntoView({ behavior: 'smooth' });
            }

            async performSearch() {
                const query = document.getElementById('searchInput').value.trim();
                
                if (!query) {
                    this.showAlert('請輸入搜尋關鍵字', 'error');
                    return;
                }

                try {
                    // 顯示載入狀態
                    const container = document.getElementById('tasksContainer');
                    container.innerHTML = `
                        <div class="loading">
                            <div class="spinner"></div>
                            <p>搜尋中...</p>
                        </div>
                    `;

                    const response = await fetch(`/api/tasks/search?query=${encodeURIComponent(query)}`);
                    const result = await response.json();
                    
                    if (result.success) {
                        this.tasks = result.data;
                        this.renderTasks();
                        
                        // 顯示搜尋結果資訊
                        this.showSearchResults(query, result.data.length);
                        
                        // 顯示清除按鈕
                        document.getElementById('clearSearchBtn').style.display = 'inline-flex';
                    } else {
                        this.showAlert('搜尋失敗', 'error');
                        this.loadTasks(); // 重新載入所有任務
                    }
                } catch (error) {
                    this.showAlert('搜尋失敗', 'error');
                    this.loadTasks(); // 重新載入所有任務
                }
            }

            clearSearch() {
                document.getElementById('searchInput').value = '';
                document.getElementById('clearSearchBtn').style.display = 'none';
                
                // 移除搜尋結果資訊
                const existingInfo = document.querySelector('.search-results-info');
                if (existingInfo) {
                    existingInfo.remove();
                }
                
                // 重新載入所有任務
                this.loadTasks();
            }

            showSearchResults(query, count) {
                // 移除現有的搜尋結果資訊
                const existingInfo = document.querySelector('.search-results-info');
                if (existingInfo) {
                    existingInfo.remove();
                }

                // 添加新的搜尋結果資訊
                const container = document.getElementById('tasksContainer');
                const searchInfo = document.createElement('div');
                searchInfo.className = 'search-results-info';
                searchInfo.innerHTML = `
                    <i class="fas fa-search"></i>
                    搜尋「${query}」找到 ${count} 個結果
                `;
                
                container.insertBefore(searchInfo, container.firstChild);
            }

            toggleFilter() {
                const content = document.getElementById('filterContent');
                const btn = document.getElementById('toggleFilterBtn');
                const icon = btn.querySelector('i');
                
                if (content.style.display === 'none') {
                    content.style.display = 'block';
                    icon.className = 'fas fa-chevron-up';
                    btn.innerHTML = '<i class="fas fa-chevron-up"></i> 收起篩選';
                } else {
                    content.style.display = 'none';
                    icon.className = 'fas fa-chevron-down';
                    btn.innerHTML = '<i class="fas fa-chevron-down"></i> 展開篩選';
                }
            }

            async applyFilter() {
                const filters = {
                    status: document.getElementById('filterStatus').value,
                    priority: document.getElementById('filterPriority').value,
                    date_from: document.getElementById('filterDateFrom').value,
                    date_to: document.getElementById('filterDateTo').value,
                    sort_by: document.getElementById('sortBy').value,
                    sort_order: document.getElementById('sortOrder').value
                };

                try {
                    // 顯示載入狀態
                    const container = document.getElementById('tasksContainer');
                    container.innerHTML = `
                        <div class="loading">
                            <div class="spinner"></div>
                            <p>篩選中...</p>
                        </div>
                    `;

                    // 構建查詢參數
                    const params = new URLSearchParams();
                    Object.keys(filters).forEach(key => {
                        if (filters[key] && filters[key] !== 'all') {
                            params.append(key, filters[key]);
                        }
                    });

                    const response = await fetch(`/api/tasks/filter?${params.toString()}`);
                    const result = await response.json();
                    
                    if (result.success) {
                        this.tasks = result.data;
                        this.renderTasks();
                        
                        // 顯示篩選結果資訊
                        this.showFilterResults(result.filters, result.data.length);
                        
                        // 標記篩選為活躍狀態
                        document.querySelector('.filter-form').classList.add('filter-active');
                    } else {
                        this.showAlert('篩選失敗', 'error');
                        this.loadTasks();
                    }
                } catch (error) {
                    this.showAlert('篩選失敗', 'error');
                    this.loadTasks();
                }
            }

            clearFilter() {
                // 重置所有篩選選項
                document.getElementById('filterStatus').value = 'all';
                document.getElementById('filterPriority').value = 'all';
                document.getElementById('filterDateFrom').value = '';
                document.getElementById('filterDateTo').value = '';
                document.getElementById('sortBy').value = 'created_at';
                document.getElementById('sortOrder').value = 'desc';

                // 移除篩選活躍狀態
                document.querySelector('.filter-form').classList.remove('filter-active');
                
                // 移除篩選結果資訊
                const existingInfo = document.querySelector('.filter-summary');
                if (existingInfo) {
                    existingInfo.remove();
                }
                
                // 重新載入所有任務
                this.loadTasks();
            }

            showFilterResults(filters, count) {
                // 移除現有的篩選結果資訊
                const existingInfo = document.querySelector('.filter-summary');
                if (existingInfo) {
                    existingInfo.remove();
                }

                // 構建篩選描述
                const filterDescriptions = [];
                
                if (filters.status && filters.status !== 'all') {
                    const statusText = {
                        'pending': '待處理',
                        'in_progress': '進行中',
                        'completed': '已完成'
                    }[filters.status] || filters.status;
                    filterDescriptions.push(`狀態: ${statusText}`);
                }
                
                if (filters.priority && filters.priority !== 'all') {
                    const priorityText = {
                        '1': '低',
                        '2': '中',
                        '3': '高'
                    }[filters.priority] || filters.priority;
                    filterDescriptions.push(`優先級: ${priorityText}`);
                }
                
                if (filters.date_from) {
                    filterDescriptions.push(`從: ${filters.date_from}`);
                }
                
                if (filters.date_to) {
                    filterDescriptions.push(`到: ${filters.date_to}`);
                }

                const sortText = {
                    'created_at': '建立時間',
                    'updated_at': '更新時間',
                    'title': '標題',
                    'priority': '優先級',
                    'status': '狀態',
                    'due_date': '截止日期'
                }[filters.sort_by] || filters.sort_by;

                const orderText = filters.sort_order === 'asc' ? '升序' : '降序';
                filterDescriptions.push(`排序: ${sortText} (${orderText})`);

                // 添加新的篩選結果資訊
                const container = document.getElementById('tasksContainer');
                const filterInfo = document.createElement('div');
                filterInfo.className = 'filter-summary';
                filterInfo.innerHTML = `
                    <i class="fas fa-filter"></i>
                    篩選結果: ${filterDescriptions.join(', ')} | 共 ${count} 個任務
                `;
                
                container.insertBefore(filterInfo, container.firstChild);
            }

            showAlert(message, type) {
                const container = document.getElementById('alertContainer');
                const alert = document.createElement('div');
                alert.className = `alert alert-${type}`;
                alert.innerHTML = `
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    ${message}
                `;
                
                container.appendChild(alert);
                
                // 3秒後自動移除
                setTimeout(() => {
                    alert.remove();
                }, 3000);
            }
        }

        // 初始化應用
        document.addEventListener('DOMContentLoaded', () => {
            new TodoApp();
        });
    </script>
</body>
</html>
