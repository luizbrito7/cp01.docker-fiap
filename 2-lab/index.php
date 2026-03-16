<?php
// ─── Database connection ──────────────────────────────────────────────────────
$host = getenv('DB_HOST') ?: 'cp01-mysql';
$db   = getenv('DB_NAME') ?: 'tasks_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'root';

$error = null;
$pdo   = null;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    $error = 'Não foi possível conectar ao banco de dados. Aguarde alguns segundos e recarregue a página.';
}

// ─── Actions ─────────────────────────────────────────────────────────────────
if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($title !== '') {
            $stmt = $pdo->prepare('INSERT INTO tasks (title, description) VALUES (?, ?)');
            $stmt->execute([$title, $description]);
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare(
            "UPDATE tasks SET status = IF(status = 'pending', 'done', 'pending') WHERE id = ?"
        );
        $stmt->execute([$id]);
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = ?');
        $stmt->execute([$id]);
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ─── Fetch tasks ──────────────────────────────────────────────────────────────
$tasks = [];
if ($pdo) {
    $tasks = $pdo->query('SELECT * FROM tasks ORDER BY created_at DESC')->fetchAll();
}

$total   = count($tasks);
$pending = count(array_filter($tasks, fn($t) => $t['status'] === 'pending'));
$done    = $total - $pending;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tasks</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:        #0f0f0f;
      --surface:   #1a1a1a;
      --border:    #2a2a2a;
      --muted:     #525252;
      --text:      #e5e5e5;
      --text-dim:  #a3a3a3;
      --accent:    #6366f1;
      --accent-h:  #818cf8;
      --done:      #22c55e;
      --danger-h:  #f87171;
      --radius:    10px;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      padding: 48px 16px;
    }

    .container { max-width: 640px; margin: 0 auto; }

    /* ── Header ── */
    .header {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      margin-bottom: 32px;
    }

    .header h1 { font-size: 1.5rem; font-weight: 600; letter-spacing: -0.02em; }

    .stats { font-size: 0.8rem; color: var(--text-dim); display: flex; gap: 12px; }
    .stats span { display: flex; align-items: center; gap: 4px; }
    .dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
    .dot-pending { background: var(--accent); }
    .dot-done    { background: var(--done); }

    /* ── Error banner ── */
    .error-banner {
      background: #1f0a0a;
      border: 1px solid #7f1d1d;
      color: #fca5a5;
      border-radius: var(--radius);
      padding: 14px 16px;
      font-size: 0.875rem;
      margin-bottom: 24px;
    }

    /* ── New task form ── */
    .form-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 20px;
      margin-bottom: 24px;
    }

    .form-card label {
      display: block;
      font-size: 0.75rem;
      font-weight: 500;
      color: var(--text-dim);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 6px;
    }

    .form-card input,
    .form-card textarea {
      width: 100%;
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 6px;
      color: var(--text);
      font-family: inherit;
      font-size: 0.9rem;
      padding: 9px 12px;
      outline: none;
      transition: border-color 0.15s;
      resize: vertical;
    }

    .form-card input:focus,
    .form-card textarea:focus { border-color: var(--accent); }

    .form-card textarea { min-height: 72px; }
    .form-row { margin-bottom: 14px; }

    .btn-primary {
      background: var(--accent);
      color: #fff;
      border: none;
      border-radius: 6px;
      font-family: inherit;
      font-size: 0.875rem;
      font-weight: 500;
      padding: 9px 18px;
      cursor: pointer;
      transition: background 0.15s;
    }

    .btn-primary:hover { background: var(--accent-h); }

    /* ── Task list ── */
    .task-list { display: flex; flex-direction: column; gap: 8px; }

    .task-item {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 14px 16px;
      display: flex;
      align-items: flex-start;
      gap: 12px;
      transition: border-color 0.15s;
    }

    .task-item:hover { border-color: var(--muted); }
    .task-item.done  { opacity: 0.55; }

    .task-toggle { flex-shrink: 0; margin-top: 2px; }

    .task-toggle button {
      width: 20px;
      height: 20px;
      border-radius: 50%;
      border: 2px solid var(--muted);
      background: transparent;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: border-color 0.15s, background 0.15s;
      padding: 0;
    }

    .task-item.done .task-toggle button {
      border-color: var(--done);
      background: var(--done);
    }

    .task-toggle button:hover { border-color: var(--done); }

    .check-icon {
      display: none;
      width: 10px;
      height: 10px;
      stroke: #fff;
      stroke-width: 2.5;
      fill: none;
    }

    .task-item.done .check-icon { display: block; }

    .task-body { flex: 1; min-width: 0; }

    .task-title { font-size: 0.9rem; font-weight: 500; word-break: break-word; }

    .task-item.done .task-title {
      text-decoration: line-through;
      color: var(--text-dim);
    }

    .task-desc {
      margin-top: 4px;
      font-size: 0.8rem;
      color: var(--text-dim);
      word-break: break-word;
    }

    .task-meta { margin-top: 6px; font-size: 0.72rem; color: var(--muted); }

    .task-delete form button {
      background: transparent;
      border: none;
      cursor: pointer;
      padding: 4px;
      color: var(--muted);
      transition: color 0.15s;
      display: flex;
      align-items: center;
    }

    .task-delete form button:hover { color: var(--danger-h); }

    /* ── Empty state ── */
    .empty {
      text-align: center;
      padding: 48px 0;
      color: var(--muted);
      font-size: 0.875rem;
    }

    .empty strong { display: block; font-size: 1rem; color: var(--text-dim); margin-bottom: 6px; }
  </style>
</head>
<body>
<div class="container">

  <div class="header">
    <h1>Tasks</h1>
    <?php if (!$error): ?>
    <div class="stats">
      <span><span class="dot dot-pending"></span><?= $pending ?> pendente<?= $pending !== 1 ? 's' : '' ?></span>
      <span><span class="dot dot-done"></span><?= $done ?> concluída<?= $done !== 1 ? 's' : '' ?></span>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($error): ?>
    <div class="error-banner"><?= htmlspecialchars($error) ?></div>
  <?php else: ?>

  <div class="form-card">
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="form-row">
        <label for="title">Título</label>
        <input type="text" id="title" name="title" placeholder="O que precisa ser feito?" required autofocus>
      </div>
      <div class="form-row">
        <label for="description">Descrição <span style="font-weight:400;text-transform:none;letter-spacing:0">(opcional)</span></label>
        <textarea id="description" name="description" placeholder="Detalhes adicionais..."></textarea>
      </div>
      <button type="submit" class="btn-primary">Adicionar tarefa</button>
    </form>
  </div>

  <div class="task-list">
    <?php if (empty($tasks)): ?>
      <div class="empty">
        <strong>Nenhuma tarefa ainda</strong>
        Use o formulário acima para criar a primeira.
      </div>
    <?php else: ?>
      <?php foreach ($tasks as $task):
        $isDone = $task['status'] === 'done';
        $date   = date('d/m/Y H:i', strtotime($task['created_at']));
      ?>
      <div class="task-item <?= $isDone ? 'done' : '' ?>">

        <div class="task-toggle">
          <form method="POST">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= $task['id'] ?>">
            <button type="submit" title="<?= $isDone ? 'Marcar como pendente' : 'Marcar como concluída' ?>">
              <svg class="check-icon" viewBox="0 0 12 12">
                <polyline points="1.5,6 5,9.5 10.5,2.5"/>
              </svg>
            </button>
          </form>
        </div>

        <div class="task-body">
          <div class="task-title"><?= htmlspecialchars($task['title']) ?></div>
          <?php if ($task['description']): ?>
            <div class="task-desc"><?= nl2br(htmlspecialchars($task['description'])) ?></div>
          <?php endif; ?>
          <div class="task-meta"><?= $date ?></div>
        </div>

        <div class="task-delete">
          <form method="POST" onsubmit="return confirm('Remover esta tarefa?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $task['id'] ?>">
            <button type="submit" title="Remover">
              <svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M3 3l9 9M12 3l-9 9"/>
              </svg>
            </button>
          </form>
        </div>

      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php endif; ?>
</div>
</body>
</html>
