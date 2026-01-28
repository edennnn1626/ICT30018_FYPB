<?php
require_once 'auth.php';
require_once 'db_config.php';
require_login();

// Handle filtering and pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10; // Limit to 10 surveys per page
$offset = ($page - 1) * $per_page;

// Get filter parameters
$filter_edited = isset($_GET['filter_edited']) ? intval($_GET['filter_edited']) : 0;
$filter_expiry = isset($_GET['filter_expiry']) ? intval($_GET['filter_expiry']) : 0;
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle sorting
$sortable_columns = ['id', 'title', 'created_at', 'expiry_date'];
$sort = in_array($_GET['sort'] ?? '', $sortable_columns) ? $_GET['sort'] : 'created_at';
$order = ($_GET['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

// Build WHERE clause for filtering
$where_conditions = [];
$params = [];
$param_types = '';

// Filter: Edited surveys only
if ($filter_edited) {
	$where_conditions[] = "updated_at IS NOT NULL AND updated_at > created_at";
}

// Filter: Not expired surveys only
if ($filter_expiry) {
	$where_conditions[] = "(expiry_date IS NULL OR expiry_date > NOW())";
}

// Filter: Date range for created_at
if ($filter_date_from) {
	$where_conditions[] = "created_at >= ?";
	$params[] = $filter_date_from . ' 00:00:00';
	$param_types .= 's';
}
if ($filter_date_to) {
	$where_conditions[] = "created_at <= ?";
	$params[] = $filter_date_to . ' 23:59:59';
	$param_types .= 's';
}

// Filter: Search by title
if ($search_query) {
	$where_conditions[] = "title LIKE ?";
	$params[] = "%$search_query%";
	$param_types .= 's';
}

// Build the WHERE clause
$where_clause = '';
if (!empty($where_conditions)) {
	$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM surveys $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
	$count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $per_page);

// Fetch paginated and filtered data
$sql = "SELECT id, title, created_at, updated_at, expiry_date, token 
			FROM surveys 
			$where_clause 
			ORDER BY $sort $order 
			LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$stmt = $conn->prepare($sql);
if (!empty($params)) {
	$stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Build toggle URL with filters preserved
function build_sort_link($column, $currentSort, $currentOrder, $filters = [])
{
	$newOrder = ($currentSort === $column && $currentOrder === 'asc') ? 'desc' : 'asc';
	$query = array_merge($_GET, ['sort' => $column, 'order' => $newOrder]);
	return '?' . http_build_query($query);
}

// Show arrow icons with style
function sort_arrows($column, $currentSort, $currentOrder)
{
	$up = '▲';
	$down = '▼';
	if ($column === $currentSort) {
		return $currentOrder === 'asc' ? "<span style='font-weight:bold;'>$up</span> $down" : "$up <span style='font-weight:bold;'>$down</span>";
	} else {
		return "<span style='color:gray;'>$up $down</span>";
	}
}

// Get base URL for sharing
$base_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="utf-8" />
	<title>Survey Management</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="styles/main.css">
	<link rel="stylesheet" href="styles/surveymanagement.css">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
	<meta name="viewport" content="width=device-width,initial-scale=1">
</head>

<body>
	<!-- Navigation Bar -->
	<?php include 'navbar.php'; ?>

	<!-- Main Content -->
	<main class="body-survey-management">
		<div class="text-box-view">
			<h1 class="text-title-view">Survey Management</h1>
		</div>

		<!-- Filter Section -->
		<div class="filter-section mb-4">
			<form method="GET" class="row g-3 align-items-center">
				<!-- Search Field -->
				<div class="col-md-3">
					<label class="form-label mb-1">Search Survey</label>
					<input type="text" name="search" class="form-control form-control-sm" placeholder="Search by title..."
						value="<?= htmlspecialchars($search_query) ?>">
				</div>

				<!-- Date Created Range -->
				<div class="col-md-4">
					<div class="row g-2">
						<div class="col-6">
							<label class="form-label mb-1">Date From</label>
							<input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_date_from) ?>">
						</div>
						<div class="col-6">
							<label class="form-label mb-1">Date To</label>
							<input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_date_to) ?>">
						</div>
					</div>
				</div>

				<!-- Checkbox Filters in a single row -->
				<div class="col-md-3">
					<div class="d-flex flex-column gap-2">
						<!-- Edited Survey Filter -->
						<div class="form-check d-flex align-items-center">
							<input class="form-check-input me-2" type="checkbox" name="filter_edited" value="1"
								id="filterEdited" <?= $filter_edited ? 'checked' : '' ?>>
							<label class="form-check-label mb-0" for="filterEdited">
								Edited Surveys Only
							</label>
						</div>

						<!-- Expiry Date Filter -->
						<div class="form-check d-flex align-items-center">
							<input class="form-check-input me-2" type="checkbox" name="filter_expiry" value="1"
								id="filterExpiry" <?= $filter_expiry ? 'checked' : '' ?>>
							<label class="form-check-label mb-0" for="filterExpiry">
								Active Surveys (Not Expired)
							</label>
						</div>
					</div>
				</div>

				<!-- Filter Buttons -->
				<div class="col-md-2">
					<div class="d-flex gap-2">
						<button type="submit" class="btn btn-primary btn-sm flex-grow-1">
							<i class="bi bi-funnel"></i> Filter
						</button>
						<a href="survey_management.php" class="btn btn-secondary btn-sm flex-grow-1">
							<i class="bi bi-x-circle"></i> Clear
						</a>
					</div>
				</div>
			</form>
		</div>

		<!-- Displaying count -->
		<div class="mb-3">
			<p class="text-muted">
				Showing <?= min($per_page, $total_rows) ?> of <?= $total_rows ?> surveys
				<?php if ($filter_edited || $filter_expiry || $search_query || $filter_date_from || $filter_date_to): ?>
					(filtered)
				<?php endif; ?>
			</p>
		</div>

		<section class="view-table-container">
			<table id="surveyTable" class="view-table">
				<thead>
					<tr>
						<th style="width: 5%;"><a href="<?= build_sort_link('id', $sort, $order, $_GET) ?>">No. <?= sort_arrows('id', $sort, $order) ?></a></th>
						<th style="width: 25%;"><a href="<?= build_sort_link('title', $sort, $order, $_GET) ?>">Survey Name <?= sort_arrows('title', $sort, $order) ?></a></th>
						<th style="width: 15%;"><a href="<?= build_sort_link('created_at', $sort, $order, $_GET) ?>">Date Created <?= sort_arrows('created_at', $sort, $order) ?></a></th>
						<th style="width: 35%;">Operations</th>
						<th style="width: 15%;"><a href="<?= build_sort_link('expiry_date', $sort, $order, $_GET) ?>">Expiry Date <?= sort_arrows('expiry_date', $sort, $order) ?></a></th>
						<th style="width: 5%;">Status</th>
					</tr>
				</thead>
				<tbody>
					<?php if ($result->num_rows > 0): ?>
						<?php
						$start_number = ($page - 1) * $per_page + 1;
						while ($row = $result->fetch_assoc()):
						?>
							<tr class="fade-in-row">
								<td><?= $start_number++ ?>.</td>
								<td><?= htmlspecialchars($row['title']) ?></td>
								<td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
								<td>
									<div class="d-flex gap-2 flex-wrap">
										<button class="btn btn-sm btn-primary" onclick="location.href='edit_survey.php?id=<?= $row['id'] ?>'">
											<i class="bi bi-pencil"></i> Edit
										</button>
										<button class="btn btn-sm btn-info" onclick="location.href='view_survey.php?id=<?= $row['id'] ?>'">
											<i class="bi bi-eye"></i> View
										</button>
										<button class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $row['id'] ?>)">
											<i class="bi bi-trash"></i> Delete
										</button>
										<button class="btn btn-sm btn-success" onclick="showShareModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['title']) ?>', this)" data-token="<?= htmlspecialchars($row['token']) ?>">
											<i class="bi bi-share"></i> Share
										</button>
									</div>
								</td>
								<td>
									<?php
									if (!empty($row['expiry_date'])) {
										$expiry_date = strtotime($row['expiry_date']);
										$now = time();
										if ($expiry_date < $now) {
											echo '<span class="text-danger fw-bold">' . date('d/m/Y H:i', $expiry_date) . '</span>';
										} else {
											echo date('d/m/Y H:i', $expiry_date);
										}
									} else {
										echo '<span class="text-muted" style="font-style:italic;">No expiry</span>';
									}
									?>
								</td>
								<!-- EDITED STATUS -->
								<td class="text-center">
									<?php if (!empty($row['updated_at']) && $row['updated_at'] != $row['created_at']): ?>
										<span class="badge bg-warning text-dark"
											title="Last edited: <?= date('d/m/Y H:i', strtotime($row['updated_at'])) ?>">
											<i class="bi bi-pencil-square"></i> Edited
										</span>
										<small class="d-block text-muted" style="font-size: 0.7rem;">
											<?= date('d/m/Y', strtotime($row['updated_at'])) ?>
										</small>
									<?php else: ?>
										<span class="badge bg-secondary">Original</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endwhile; ?>
					<?php else: ?>

						<tr>
							<td colspan="6" class="text-center text-muted py-4" style="font-style: italic;">
								<?php if ($filter_edited || $filter_expiry || $search_query || $filter_date_from || $filter_date_to): ?>
									No surveys match your filters. <a href="survey_management.php">Clear filters</a>
								<?php else: ?>
									No surveys found. Create your first survey!
								<?php endif; ?>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ($total_pages > 1): ?>
				<nav aria-label="Survey pagination" class="mt-4">
					<ul class="pagination justify-content-center">
						<!-- First & Previous -->
						<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
							<a class="page-link" href="<?= '?' . http_build_query(array_merge($_GET, ['page' => 1])) ?>" aria-label="First">
								<span aria-hidden="true">&laquo;&laquo;</span>
							</a>
						</li>
						<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
							<a class="page-link" href="<?= '?' . http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" aria-label="Previous">
								<span aria-hidden="true">&laquo;</span>
							</a>
						</li>

						<!-- Page Numbers -->
						<?php
						$start_page = max(1, $page - 2);
						$end_page = min($total_pages, $start_page + 4);
						$start_page = max(1, $end_page - 4);

						for ($i = $start_page; $i <= $end_page; $i++):
						?>
							<li class="page-item <?= $i == $page ? 'active' : '' ?>">
								<a class="page-link" href="<?= '?' . http_build_query(array_merge($_GET, ['page' => $i])) ?>">
									<?= $i ?>
								</a>
							</li>
						<?php endfor; ?>

						<!-- Next & Last -->
						<li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
							<a class="page-link" href="<?= '?' . http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" aria-label="Next">
								<span aria-hidden="true">&raquo;</span>
							</a>
						</li>
						<li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
							<a class="page-link" href="<?= '?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" aria-label="Last">
								<span aria-hidden="true">&raquo;&raquo;</span>
							</a>
						</li>
					</ul>

					<!-- Page Info -->
					<div class="text-center mt-2 text-muted">
						Page <?= $page ?> of <?= $total_pages ?>
					</div>
				</nav>
			<?php endif; ?>
		</section>
	</main>

	<!-- Share Modal -->
	<div class="modal fade" id="shareModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Share Survey: <span id="surveyTitle"></span></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<!-- Share Mode Selection -->
					<div class="mb-3">
						<label class="form-label fw-bold">Share Mode:</label>
						<div class="btn-group w-100" role="group">
							<input type="radio" class="btn-check" name="shareMode" id="singleShare" value="single" checked>
							<label class="btn btn-outline-primary" for="singleShare">
								<i class="bi bi-person"></i> Single Share
							</label>
							<input type="radio" class="btn-check" name="shareMode" id="bulkShare" value="bulk">
							<label class="btn btn-outline-primary" for="bulkShare">
								<i class="bi bi-people-fill"></i> Bulk Share
							</label>
						</div>
					</div>

					<!-- Single Share Section -->
					<div id="singleShareSection">
						<div class="mb-3">
							<label class="form-label text-dark">Insert Alumni Email:</label>
							<input type="text" class="form-control" id="alumniSearch" placeholder="Type at least 3 letters...">

							<!-- Dropdown results -->
							<div id="alumniResults"
								class="list-group mt-1"
								style="max-height: 250px; overflow-y:auto; display:none;">
							</div>
						</div>

						<!-- Email type: shown ONLY when a valid alumni is selected -->
						<div class="mb-3" id="emailTypeSection" style="display:none;">
							<label class="form-label text-primary">Send to:</label>
							<div class="form-check">
								<input class="form-check-input" type="radio" name="singleEmailType" id="singleStudentEmail" value="student" checked>
								<label class="form-check-label text-dark" for="singleStudentEmail">
									Student (University) Email
								</label>
							</div>
							<div class="form-check">
								<input class="form-check-input" type="radio" name="singleEmailType" id="singlePersonalEmail" value="personal">
								<label class="form-check-label text-dark" for="singlePersonalEmail">
									Personal Email
								</label>
							</div>
							<div class="form-check">
								<input class="form-check-input" type="radio" name="singleEmailType" id="singleBothEmails" value="both">
								<label class="form-check-label text-dark" for="singleBothEmails">
									Both Emails
								</label>
							</div>
						</div>
					</div>

					<!-- Bulk Share Section -->
					<div id="bulkShareSection" style="display: none;">
						<div class="mb-3">
							<label class="form-label text-dark">Choose Course:</label>
							<select class="form-select" id="courseSelect">
								<option value="">-- All Courses --</option>
								<?php
								// Fetch unique courses from alumni program column
								$courseResult = $conn->query("
									SELECT DISTINCT program 
									FROM alumni 
									WHERE program IS NOT NULL 
									AND program != '' 
									AND program != 'N/A'
									ORDER BY program
								");

								if ($courseResult && $courseResult->num_rows > 0) {
									while ($courseRow = $courseResult->fetch_assoc()) {
										$courseName = htmlspecialchars($courseRow['program']);
										echo '<option value="' . $courseName . '">' . $courseName . '</option>';
									}
								}
								?>
							</select>
						</div>

						<div class="mb-3">
							<label class="form-label text-dark">Choose Graduation Dates:</label>
							<select class="form-select" id="yearSelect">
								<option value="">-- All Dates --</option>
								<?php
								// Fetch graduation dates with both date and label
								$yearResult = $conn->query("
            SELECT date, label 
            FROM graduation_dates 
            WHERE date IS NOT NULL 
            ORDER BY date DESC
        ");

								if ($yearResult && $yearResult->num_rows > 0) {
									while ($yearRow = $yearResult->fetch_assoc()) {
										$date = $yearRow['date'];  // The actual date for filtering
										$label = $yearRow['label']; // The display label
										// Store the date in value attribute
										echo '<option value="' . htmlspecialchars($date) . '">' .
											htmlspecialchars($label) . '</option>';
									}
								} else {
									// Fallback: extract years from alumni graduation_date
									$fallbackResult = $conn->query("
                SELECT DISTINCT graduation_date 
                FROM alumni 
                WHERE graduation_date IS NOT NULL 
                ORDER BY graduation_date DESC
            ");

									if ($fallbackResult && $fallbackResult->num_rows > 0) {
										while ($dateRow = $fallbackResult->fetch_assoc()) {
											$date = $dateRow['graduation_date'];
											$year = date('Y', strtotime($date));
											echo '<option value="' . htmlspecialchars($date) . '">' .
												htmlspecialchars($year) . '</option>';
										}
									}
								}
								?>
							</select>
						</div>

						<div class="mb-3">
							<label class="form-label text-primary">Send survey to students in selected course/year:</label>
							<div class="form-check">
								<input class="form-check-input" type="checkbox" value="1" id="sendStudentEmail" checked>
								<label class="form-check-label text-dark" for="sendStudentEmail">Send to student (university) email</label>
							</div>
							<div class="form-check">
								<input class="form-check-input" type="checkbox" value="1" id="sendPersonalEmail">
								<label class="form-check-label text-dark" for="sendPersonalEmail">Send to personal email (if available)</label>
							</div>
						</div>
					</div>

					<!-- Share Button -->
					<div class="mb-2">
						<button class="btn btn-success w-100" id="shareSurveyBtn" type="button">
							<i class="bi bi-send-fill"></i> Share Survey Link
						</button>
						<!-- Progress Bar -->
						<div id="sendProgressWrapper" style="display:none;" class="my-2">
							<div class="progress" style="height: 6px; margin: 2px 0;">
								<div id="sendProgressBar" class="progress-bar progress-bar-striped progress-bar-animated"
									role="progressbar" style="width: 0%"></div>
							</div>
							<div class="text-end mt-1">
								<small id="progressPercentage" class="text-muted">0%</small>
							</div>
						</div>
						<div id="shareStatus" class="mt-1" style="display:none;"></div>
					</div>

					<!-- Survey Link -->
					<div class="mb-3">
						<label class="form-label text-dark">Survey Link:</label>
						<div class="input-group">
							<input type="text" class="form-control" id="surveyLink" readonly>
							<button class="btn btn-outline-secondary copy-btn" onclick="copyToClipboard()">
								<i class="bi bi-clipboard"></i> Copy
							</button>
						</div>
					</div>

					<!-- QR Code -->
					<div class="mb-3">
						<label class="form-label text-dark">QR Code:</label>
						<div class="text-center p-3 bg-white rounded border">
							<img id="qrCodeImg" class="qr-code-img" src="" alt="QR Code">
						</div>
						<button class="btn btn-primary mt-2 w-100" onclick="downloadQRCode()">
							<i class="bi bi-download"></i> Download QR Code
						</button>
					</div>
				</div>

				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
				</div>
			</div>
		</div>
	</div>

	<footer class="site-footer fixed-bottom py-3 mt-auto">
		<div class="container text-center">
			<p class="mb-0">ICT30017 ICT PROJECT A | <span class="team-name">TEAM Deadline Dominators</span></p>
		</div>
	</footer>

	<!-- JavaScript Libraries -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
	<script src="js/main.js"></script>
	<script src="js/surveymanagement.js"></script>
</body>

</html>