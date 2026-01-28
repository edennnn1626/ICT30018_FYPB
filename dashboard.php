<?php
require_once("database.php");
require_once("auth.php");
require_once "db_config.php";
require_login();

$responsesFile = __DIR__ . '/js/mock_responses.json';
$data = [];
if (file_exists($responsesFile)) {
	$json = file_get_contents($responsesFile);
	$data = json_decode($json, true) ?: [];
}

$selectedCourse = isset($_GET['course']) ? $_GET['course'] : 'All';

// Determine years filter
if (isset($_GET['years'])) {
	if ($_GET['years'] === 'custom' && isset($_GET['customYears']) && intval($_GET['customYears']) > 0) {
		$years = intval($_GET['customYears']);
		$isCustom = true;
	} else {
		$years = intval($_GET['years']);
		$isCustom = false;
	}
} else {
	$years = 2; // default 2 years
	$isCustom = false;
}

// Fetch surveys
$totalSurveys = 0;
$surveysFromDb = [];
if (isset($conn) && $conn) {
	$sql = "SELECT id, title, created_at, expiry_date FROM " . TABLE_SURVEYS . " ORDER BY created_at DESC";
	$result = $conn->query($sql);
	if ($result && $result->num_rows > 0) {
		$surveysFromDb = $result->fetch_all(MYSQLI_ASSOC);
		$totalSurveys = count($surveysFromDb);
	}
}

// Fetch alumni with year filter
$alumniFromDb = [];
if (isset($conn) && $conn) {
	$alumniSql = "SELECT id, name, student_id, graduation_date, program, email FROM " . TABLE_ALUMNI;
	$dateLimit = date('Y-m-d', strtotime("-$years years"));
	$alumniSql .= " WHERE graduation_date >= '$dateLimit'";
	$alumniSql .= " ORDER BY name ASC";
	$result = $conn->query($alumniSql);
	if ($result && $result->num_rows > 0) {
		$alumniFromDb = $result->fetch_all(MYSQLI_ASSOC);
	}
}

// Fetch survey responses
$responsesFromDb = [];
if (isset($conn) && $conn) {
	$sql = "SELECT id, survey_id, answers, submitted_at FROM " . SURVEY_RESPONSES_TABLE . " ORDER BY submitted_at DESC";
	$result = $conn->query($sql);
	if ($result && $result->num_rows > 0) {
		$responsesFromDb = $result->fetch_all(MYSQLI_ASSOC);
	}
}

// Fetch data for charts
$chartData = [];

// Fetch response count by survey for Pie Chart
$surveyResponseCounts = [];
if (isset($conn) && $conn) {
	$sql = "SELECT s.title, COUNT(r.id) as response_count 
            FROM " . TABLE_SURVEYS . " s 
            LEFT JOIN " . SURVEY_RESPONSES_TABLE . " r ON s.id = r.survey_id 
            GROUP BY s.id, s.title 
            ORDER BY response_count DESC";
	$result = $conn->query($sql);
	if ($result && $result->num_rows > 0) {
		$surveyResponseCounts = $result->fetch_all(MYSQLI_ASSOC);
	}
}

// Fetch 6-month interval response trend for Line Chart
$monthlyTrends = [];
if (isset($conn) && $conn) {
	// Get data for last 30 months (2.5 years) grouped into 6-month intervals
	$sql = "SELECT 
                CONCAT(
                    YEAR(DATE_SUB(NOW(), INTERVAL (floor_num * 6) MONTH)), 
                    '-', 
                    LPAD(((MONTH(DATE_SUB(NOW(), INTERVAL (floor_num * 6) MONTH)) - 1) DIV 6) * 6 + 1, 2, '0')
                ) as period_start,
                DATE_FORMAT(DATE_SUB(NOW(), INTERVAL (floor_num * 6) MONTH), '%b %Y') as period_label,
                COALESCE(SUM(r.response_count), 0) as response_count
            FROM (
                SELECT 0 as floor_num UNION SELECT 1 UNION SELECT 2 
                UNION SELECT 3 UNION SELECT 4
            ) numbers
            LEFT JOIN (
                SELECT 
                    FLOOR(TIMESTAMPDIFF(MONTH, submitted_at, NOW()) / 6) as period_num,
                    COUNT(*) as response_count
                FROM " . SURVEY_RESPONSES_TABLE . " 
                WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 30 MONTH)
                GROUP BY period_num
            ) r ON numbers.floor_num = r.period_num
            WHERE numbers.floor_num <= 4
            GROUP BY numbers.floor_num, period_start, period_label
            ORDER BY numbers.floor_num DESC";

	$result = $conn->query($sql);
	if ($result && $result->num_rows > 0) {
		$monthlyTrends = $result->fetch_all(MYSQLI_ASSOC);
	}
}

// Fetch program distribution for Bar Chart
$programDistribution = [];
if (isset($conn) && $conn) {
	$sql = "SELECT 
                program,
                COUNT(*) as alumni_count
            FROM " . TABLE_ALUMNI . " 
            WHERE program IS NOT NULL AND program != ''
            GROUP BY program
            ORDER BY alumni_count DESC
            LIMIT 10";
	$result = $conn->query($sql);
	if ($result && $result->num_rows > 0) {
		$programDistribution = $result->fetch_all(MYSQLI_ASSOC);
	}
}

// Convert to JSON for JavaScript
$surveyResponseCountsJson = json_encode($surveyResponseCounts);
$monthlyTrendsJson = json_encode($monthlyTrends);
$programDistributionJson = json_encode($programDistribution);

$totalResponses = count($responsesFromDb);
$totalAlumni = count($alumniFromDb);

$surveysJson = json_encode($surveysFromDb);
$responsesJson = json_encode($responsesFromDb);
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<title>Dashboard</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
	<link rel="stylesheet" href="styles/main.css">
	<link rel="stylesheet" href="styles/dashboard.css">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
	<meta name="viewport" content="width=device-width,initial-scale=1">
</head>

<body>
	<?php include 'navbar.php'; ?>

	<div class="main">
		<div class="cover-container">
			<img src="images/swinburne.jpg" alt="Cover Image">
		</div>

		<div class="card-container">
			<div class="card">
				<i class="bi bi-file-earmark-text card-icon"></i>
				<h4>Total Surveys</h4>
				<p id="cardSurveys"><?= $totalSurveys ?></p>
			</div>
			<div class="card">
				<i class="bi bi-people card-icon"></i>
				<h4>Total Responses</h4>
				<p id="cardResponses"><?= $totalResponses ?></p>
			</div>
			<div class="card">
				<i class="bi bi-person-badge card-icon"></i>
				<h4>Total Alumni</h4>
				<p id="cardAlumni"><?= $totalAlumni ?></p>
			</div>
		</div>

		<div class="year-filter-wrap">
			<form method="GET" id="yearFilterForm" class="d-flex align-items-center justify-content-center flex-wrap">
				<label for="yearSelect" style="margin-right:10px; font-weight:500; color:#1f2937;">
					Show Survey from the last:
				</label>
				<select name="years" id="yearSelect" class="year-filter-select">
					<option value="1" <?= ($years == 1 && !$isCustom) ? 'selected' : '' ?>>1 Year</option>
					<option value="2" <?= ($years == 2 && !$isCustom) ? 'selected' : '' ?>>2 Years</option>
					<option value="3" <?= ($years == 3 && !$isCustom) ? 'selected' : '' ?>>3 Years</option>
					<option value="4" <?= ($years == 4 && !$isCustom) ? 'selected' : '' ?>>4 Years</option>
					<option value="5" <?= ($years == 5 && !$isCustom) ? 'selected' : '' ?>>5 Years</option>
					<option value="custom" <?= $isCustom ? 'selected' : '' ?>>Custom</option>
				</select>
				<div class="d-flex align-items-center ms-2">
					<input type="number"
						name="customYears"
						id="customYearsInput"
						min="1"
						placeholder="Years"
						value="<?= $isCustom ? $years : '' ?>"
						style="display: <?= $isCustom ? 'inline-block' : 'none' ?>; width: 80px;"
						class="me-2" />
					<button type="submit" id="applyYearsBtn" style="display: <?= $isCustom ? 'inline-block' : 'none' ?>;"
						class="btn btn-sm btn-primary">Apply</button>
				</div>
			</form>
		</div>

		<div class="btn-group text-center d-flex justify-content-center mt-3">
			<a href="create_survey.php" class="btn">Create Survey</a>
			<a href="survey_management.php" class="btn">Manage Surveys</a>
			<a href="alumni.php" class="btn">Alumni Directory</a>
		</div>

		<div class="container mt-4 mb-5 data-section" id="resultsSection">
			<div class="row g-4">
				<div class="col-lg-5">
					<h6 class="mb-3">Surveys <small class="text-muted">(Click to analyze)</small></h6>
					<input type="text" id="surveyFilter" placeholder="Search Surveys..." class="form-control mb-3">
					<div id="surveysList">
						<?php if (count($surveysFromDb) === 0): ?>
							<div class="card p-3">No surveys found.</div>
						<?php else: ?>
							<?php foreach ($surveysFromDb as $index => $s): ?>
								<div class="survey-box mb-3 survey-item <?= $index === 0 ? 'active-survey' : '' ?>"
									data-survey-id="<?= htmlspecialchars($s['id']) ?>"
									data-survey-title="<?= htmlspecialchars(strtolower($s['title'])) ?>">
									<div class="d-flex justify-content-between align-items-start">
										<div>
											<div class="survey-title"><?= htmlspecialchars($s['title']) ?></div>
											<div class="small text-muted">
												<i class="bi bi-calendar-plus"></i> Created: <?= htmlspecialchars($s['created_at']) ?>
											</div>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
					<div class="survey-pagination mt-2" id="surveyPagination"></div>
				</div>

				<div class="col-lg-7">
					<div class="d-flex justify-content-between align-items-center mb-3">
						<h6 class="mb-0">Survey Analytics</h6>
						<div class="d-flex gap-2">
							<button class="btn btn-outline-primary btn-sm px-3" id="toggleViewBtn">
								<i class="bi bi-list-ul"></i> Switch to Individual View
							</button>
							<button class="btn btn-outline-success btn-sm px-3" id="exportSurveyBtn">
								<i class="bi bi-download"></i> Export
							</button>
						</div>
					</div>

					<!-- Quick Stats Cards -->
					<div class="row mb-3" id="surveyStats">
						<?php
						if (!empty($surveysFromDb)):
							$latestSurveyId = $surveysFromDb[0]['id'];
							// Fetch survey statistics
							$statsSql = "SELECT 
                            COUNT(*) as total_responses,
                            COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(answers, '$.email'))) as unique_respondents,
                            DATE_FORMAT(MIN(submitted_at), '%Y-%m-%d') as first_response,
                            DATE_FORMAT(MAX(submitted_at), '%Y-%m-%d') as last_response
                        FROM " . SURVEY_RESPONSES_TABLE . " 
                        WHERE survey_id = ?";
							$stmt = $conn->prepare($statsSql);
							$stmt->bind_param("i", $latestSurveyId);
							$stmt->execute();
							$statsResult = $stmt->get_result();
							$stats = $statsResult->fetch_assoc();
							$stmt->close();
						?>
							<div class="col-md-3">
								<div class="stat-card">
									<div class="stat-icon"><i class="bi bi-chat-square-text"></i></div>
									<div class="stat-value"><?= $stats['total_responses'] ?? 0 ?></div>
									<div class="stat-label">Total Responses</div>
								</div>
							</div>
							<div class="col-md-3">
								<div class="stat-card">
									<div class="stat-icon"><i class="bi bi-people"></i></div>
									<div class="stat-value"><?= $stats['unique_respondents'] ?? 0 ?></div>
									<div class="stat-label">Unique Respondents</div>
								</div>
							</div>
							<div class="col-md-3">
								<div class="stat-card">
									<div class="stat-icon"><i class="bi bi-calendar-plus"></i></div>
									<div class="stat-value"><?= $stats['first_response'] ?? 'N/A' ?></div>
									<div class="stat-label">First Response</div>
								</div>
							</div>
							<div class="col-md-3">
								<div class="stat-card">
									<div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
									<div class="stat-value"><?= $stats['last_response'] ?? 'N/A' ?></div>
									<div class="stat-label">Last Response</div>
								</div>
							</div>
						<?php endif; ?>
					</div>

					<!-- Aggregated Answers View -->
					<div id="aggregatedView" style="display: block;">
						<div class="card">
							<div class="card-header d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-0">Aggregated Response Analysis</h6>
									<small class="text-muted">Shows most common answers for each question</small>
								</div>
								<small class="text-muted" id="aggregatedCount">0 questions</small>
							</div>
							<!-- Scrollable container for questions -->
							<div class="card-body aggregated-scroll-container" id="aggregatedAnswers"
								style="max-height: 400px; overflow-y: auto; padding-right: 8px;">
								<div class="text-center py-4">
									<i class="bi bi-graph-up display-4 text-muted mb-3"></i>
									<p class="text-muted">Select a survey to view aggregated responses</p>
								</div>
							</div>
							<div class="card-footer py-2">
								<div class="d-flex justify-content-between align-items-center">
									<button class="btn btn-sm btn-outline-secondary" id="firstAggregated" disabled>
										<i class="bi bi-chevron-double-left"></i> First
									</button>
									<div class="d-flex align-items-center gap-1">
										<button class="btn btn-sm btn-outline-secondary" id="prevAggregated" disabled>
											<i class="bi bi-chevron-left"></i> Prev
										</button>
										<div class="mx-2" id="aggregatedPagination">
											<!-- Page numbers will be inserted here -->
										</div>
										<button class="btn btn-sm btn-outline-secondary" id="nextAggregated" disabled>
											Next <i class="bi bi-chevron-right"></i>
										</button>
									</div>
									<button class="btn btn-sm btn-outline-secondary" id="lastAggregated" disabled>
										Last <i class="bi bi-chevron-double-right"></i>
									</button>
								</div>
							</div>
						</div>
					</div>

					<!-- Individual Responses View (Hidden by default) -->
					<div id="individualView" style="display: none;">
						<div class="card">
							<div class="card-header d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-0">Individual Responses</h6>
									<small class="text-muted" id="responseCountText">Total: 0 responses</small>
								</div>
								<div class="d-flex gap-2 align-items-center">
									<input type="text" id="responseSearch" placeholder="Search responses..." class="form-control form-control-sm" style="width: 180px;">
								</div>
							</div>
							<div class="card-body" id="responsesListContainer">
								<div class="text-center py-4">
									<i class="bi bi-inbox display-4 text-muted mb-2"></i>
									<p class="text-muted">No responses found.</p>
								</div>
							</div>
							<div class="card-footer py-2">
								<div class="d-flex justify-content-between align-items-center">
									<button class="btn btn-sm btn-outline-secondary" id="prevResponse" disabled>
										<i class="bi bi-chevron-left"></i> Prev
									</button>
									<div class="d-flex align-items-center gap-1" id="responsePagination">
										<!-- Page numbers will be inserted here -->
									</div>
									<button class="btn btn-sm btn-outline-secondary" id="nextResponse" disabled>
										Next <i class="bi bi-chevron-right"></i>
									</button>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Charts Section -->
			<div class="charts-section mt-4">
				<div class="row g-3">
					<div class="col-md-6">
						<div class="card p-3">
							<h6 class="chart-title">Response Distribution by Survey (Pie Chart)</h6>
							<div class="chart-container" style="height: 300px;">
								<canvas id="pieChart"></canvas>
							</div>
						</div>
					</div>
					<div class="col-md-6">
						<div class="card p-3">
							<h6 class="chart-title">Response Trend (6-Month Intervals)</h6>
							<div class="chart-container" style="height: 300px;">
								<canvas id="lineChart"></canvas>
							</div>
						</div>
					</div>
				</div>
				<div class="row mt-3">
					<div class="col-12">
						<div class="card p-3">
							<h6 class="chart-title">Program Distribution (Bar Chart)</h6>
							<div class="chart-container" style="height: 300px;">
								<canvas id="barChart"></canvas>
							</div>
						</div>
					</div>
				</div>
			</div>

		</div>
	</div>

	<button onclick="scrollToTop()" id="backToTop" title="Go to top">↑</button>

	<footer class="site-footer fixed-bottom py-3 mt-auto">
		<div class="container text-center">
			<p class="mb-0">ICT30017 ICT PROJECT A | <span class="team-name">TEAM Deadline Dominators</span></p>
		</div>
	</footer>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	<script src="js/dashboard.js"></script>
	<script src="js/charts.js"></script>

	<script>
		// Pass PHP data to JavaScript for charts
		const chartData = {
			surveyResponseCounts: <?= json_encode($surveyResponseCounts) ?>,
			monthlyTrends: <?= json_encode($monthlyTrends) ?>,
			programDistribution: <?= json_encode($programDistribution) ?>
		};

		// Debug log to check data
		console.log('Chart data loaded:', chartData);
	</script>
</body>

</html>