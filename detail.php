<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

// 详情页数据随浏览量实时变化，禁止缓存（含浏览器后退缓存）
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$db = getDB();

// 获取详情：仅已通过(status=1)的留言可访问，无效留言直接跳走且不计数
$stmt = $db->prepare("SELECT * FROM messages WHERE id = ? AND status = 1");
$stmt->execute([$id]);
$msg = $stmt->fetch();

if (!$msg) {
    header('Location: index.php');
    exit;
}

// 有效留言才记录浏览；同一访客对同一条留言只计一次
if (recordMessageView($msg['id'])) {
    $msg['views']++;
}

// 返回链接：同源来路为列表页时回到原位置（保留分页/排序/筛选），否则回首页
$backUrl = 'index.php';
$ref = $_SERVER['HTTP_REFERER'] ?? '';
if ($ref) {
    $refHost = parse_url($ref, PHP_URL_HOST);
    $refPath = (string) parse_url($ref, PHP_URL_PATH);
    if ($refHost !== null && $refHost === ($_SERVER['HTTP_HOST'] ?? '')
        && in_array(basename($refPath), ['index.php', 'favorites.php'], true)) {
        $backUrl = $ref;
    }
}

$pageTitle = cleanInput($msg['title']) . ' - 社区便民留言板';
$currentPage = '';
$cssPath = 'assets/css/style.css';
$jsPath = 'assets/js/main.js';

include __DIR__ . '/includes/header.php';
?>

<section class="detail-section">
    <div class="container">
        <div class="detail-card">
            <div class="detail-header">
                <span class="card-type type-<?= $msg['type'] ?>"><?= getTypeIcon($msg['type']) ?> <?= getTypeLabel($msg['type']) ?></span>
                <div class="detail-meta">
                    <span>👤 <?= cleanInput($msg['nickname']) ?></span>
                    <span>🕐 <?= $msg['created_at'] ?></span>
                    <span>👁 <?= $msg['views'] ?> 次浏览</span>
                </div>
            </div>

            <h1 class="detail-title"><?= cleanInput($msg['title']) ?></h1>

            <div class="detail-content">
                <?= nl2br(cleanInput($msg['content'])) ?>
            </div>

            <?php if ($msg['image']): ?>
            <div class="detail-image">
                <img src="<?= cleanInput($msg['image']) ?>" alt="留言图片" onclick="window.open(this.src)">
            </div>
            <?php endif; ?>

            <?php if ($msg['phone']): ?>
            <div class="detail-contact">
                <span>📞 联系方式：<?= cleanInput($msg['phone']) ?></span>
            </div>
            <?php endif; ?>

            <div class="detail-actions">
                <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary">← 返回列表</a>
                <?php $isFav = isFavorited($msg['id']); ?>
                <button class="btn favorite-detail-btn <?= $isFav ? 'btn-warning' : 'btn-secondary' ?>" data-message-id="<?= $msg['id'] ?>" onclick="toggleFavorite(event, this)">
                    <span class="favorite-icon"><?= $isFav ? '⭐' : '☆' ?></span>
                    <span class="favorite-text"><?= $isFav ? '已收藏' : '收藏' ?></span>
                </button>
                <?php $hasReported = hasReported($msg['id']); ?>
                <button class="btn <?= $hasReported ? 'btn-secondary' : 'btn-danger' ?> report-btn" data-message-id="<?= $msg['id'] ?>" onclick="openReportModal(<?= $msg['id'] ?>)" <?= $hasReported ? 'disabled' : '' ?>>
                    <span>🚩</span>
                    <span class="report-text"><?= $hasReported ? '已举报' : '举报' ?></span>
                </button>
                <a href="submit.php" class="btn btn-primary">发布留言</a>
            </div>
        </div>
    </div>
</section>

<!-- 举报弹窗 -->
<div class="modal" id="reportModal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>🚩 举报留言</h3>
            <button class="modal-close" onclick="closeReportModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="reportForm">
                <input type="hidden" id="reportMessageId" name="message_id">
                <div class="form-group">
                    <label>举报类型 <span class="required">*</span></label>
                    <div class="report-type-options">
                        <label class="report-type-option">
                            <input type="radio" name="report_type" value="spam" required>
                            <span>🗑️ 垃圾信息</span>
                        </label>
                        <label class="report-type-option">
                            <input type="radio" name="report_type" value="abuse">
                            <span>😡 辱骂攻击</span>
                        </label>
                        <label class="report-type-option">
                            <input type="radio" name="report_type" value="illegal">
                            <span>⚖️ 违法违规</span>
                        </label>
                        <label class="report-type-option">
                            <input type="radio" name="report_type" value="porn">
                            <span>🔞 色情低俗</span>
                        </label>
                        <label class="report-type-option">
                            <input type="radio" name="report_type" value="other">
                            <span>📝 其他</span>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="reportDescription">补充说明 <span class="text-muted">(可选，最多500字)</span></label>
                    <textarea id="reportDescription" name="description" rows="4" maxlength="500" placeholder="请描述具体的违规内容，帮助我们更好地处理..."></textarea>
                    <span class="char-count"><span id="reportDescCount">0</span>/500</span>
                </div>
                <div class="form-tip">
                    <p>⚠️ 恶意举报将被限制功能使用，请如实填写举报内容。</p>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeReportModal()">取消</button>
                    <button type="submit" class="btn btn-danger" id="reportSubmitBtn">提交举报</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
