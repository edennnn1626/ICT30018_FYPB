<?php
require_once 'auth.php';
require_once 'db_config.php';
require_login();

$survey_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$survey = null;
$sections = [];

if ($survey_id > 0) {
	$res = $conn->query("SELECT * FROM surveys WHERE id = $survey_id");
	if ($res) {
		$survey = $res->fetch_assoc();

		if ($survey && !empty($survey['form_json'])) {
			$decoded = json_decode($survey['form_json'], true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {

				// New JSON structure with sections
				if (isset($decoded['sections']) && is_array($decoded['sections'])) {
					$sections = $decoded['sections'];
				}

				// Get title and description from new structure
				if (!empty($decoded['title'])) {
					$survey['title'] = $decoded['title'];
				}
				if (!empty($decoded['description'])) {
					$survey['description'] = $decoded['description'];
				}
			} else {
				// invalid JSON: keep $sections empty and optionally log / show message
				error_log("Invalid form_json for survey id $survey_id: " . json_last_error_msg());
			}
		}
	}
}

// Display only allowed html tag to display text
function displayQuestionText($text)
{
    if (empty($text)) return '';
    
    // Allow only safe HTML tags for display
    $allowed_tags = '<p><strong><b><em><i><u><br><span><sup><sub><small><h1><h2><h3><h4><h5><h6><ul><ol><li>';
    
    // Strip unsafe tags but keep allowed ones
    $text = strip_tags($text, $allowed_tags);
    
    return $text;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title><?= htmlspecialchars($survey['title'] ?? 'Survey') ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
	<link rel="stylesheet" href="styles/main.css">
	<link rel="stylesheet" href="styles/viewsurvey.css">
	<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100..900&display=swap" rel="stylesheet">
	<meta name="viewport" content="width=device-width,initial-scale=1">
</head>

<body>
	<!-- Navigation Bar -->
	<?php include 'navbar.php'; ?>

	<main class="body-view-survey">
		<div class="form-wrapper container py-4">
			<h1 class="text-title-view"><?= htmlspecialchars($survey['title'] ?? 'Survey Form') ?></h1>

			<?php if (!$survey): ?>
				<div class="alert alert-danger">Survey not found.</div>
			<?php else: ?>

				<?php if (!empty($survey['description'])): ?>
					<div class="survey-description mb-4 p-3 bg-light rounded">
						<?= $survey['description'] ?>
					</div>
				<?php endif; ?>

				<!-- Top Action Buttons -->
				<div class="top-action-buttons d-flex justify-content-between">
					<button type="button" class="btn btn-secondary" onclick="window.location.href='survey_management.php'">Back to Surveys</button>
					<button type="button" class="btn btn-primary" onclick="window.location.href='edit_survey.php?id=<?= $survey_id ?>'">Edit Questions</button>
				</div>

				<?php if (empty($sections)): ?>
					<div class="alert alert-secondary">This survey has no questions yet.</div>
				<?php else: ?>

					<form method="POST" action="submit_response.php" id="surveyForm">
						<input type="hidden" name="survey_id" value="<?= $survey_id ?>">

						<!-- Sections Container -->
						<div id="sectionsContainer">
							<?php
							$global_question_counter = 0;
							foreach ($sections as $section_index => $section):
								$section_title = isset($section['secTitle']) ? $section['secTitle'] : (isset($section['title']) ? $section['title'] : "Section " . ($section_index + 1));
								$section_questions = isset($section['questions']) && is_array($section['questions']) ? $section['questions'] : [];
								$is_active = $section_index === 0 ? 'active' : '';
							?>

								<div class="survey-section <?= $is_active ?>" data-section-index="<?= $section_index ?>">
									<?php if (!empty($section_title)): ?>
										<div class="section-header mb-4 p-3 bg-primary text-white rounded">
											<h3 class="h4 mb-0"><?= htmlspecialchars($section_title) ?></h3>
										</div>
									<?php endif; ?>

									<?php foreach ($section_questions as $question_index => $q): ?>
										<?php
										// Defensive defaults for new structure
										$q_text = isset($q['text']) ? displayQuestionText($q['text']) : (isset($q['question_text']) ? displayQuestionText($q['question_text']) : 'Untitled question');
										$q_type = isset($q['type']) ? $q['type'] : (isset($q['question_type']) ? $q['question_type'] : 'short');
										$options = isset($q['options']) && is_array($q['options']) ? $q['options'] : [];
										$required = isset($q['required']) ? (bool)$q['required'] : false;
										$scaleMax = isset($q['scaleMax']) ? intval($q['scaleMax']) : 5;
										$labelLeft = isset($q['scaleLabelLeft']) ? $q['scaleLabelLeft'] : (isset($q['labelLeft']) ? $q['labelLeft'] : '');
										$labelRight = isset($q['scaleLabelRight']) ? $q['scaleLabelRight'] : (isset($q['labelRight']) ? $q['labelRight'] : '');

										// normalize type for easier matching
										$type_norm = strtolower(trim($q_type));
										// Use combined section and question index as unique ID
										$qid = $section_index . '_' . $question_index;
										$global_question_counter++;
										?>

										<div class="vs-container question-block mb-4 <?= $required ? 'required-field' : '' ?>">
											<div class="question-header d-flex align-items-start mb-3">
												<div class="question-number fw-bold me-2" style="min-width: 25px;">
													<?= $global_question_counter ?>.
												</div>
												<div class="question-content flex-grow-1">
													<label class="form-label fw-bold mb-0 d-flex align-items-start">
														<?php if ($required): ?>
															<span class="required-asterisk text-danger fw-bold me-1" style="color: #dc3545 !important;">*</span>
														<?php endif; ?>
														<span class="question-text"><?= $q_text ?></span>
													</label>
													
													<!-- Options container - MULTIPLE CHOICE -->
													<div class="options-container mt-2">
														<?php if ($type_norm === 'multiple choice' || $type_norm === 'multiple' || $type_norm === 'mcq'): ?>
															<div class="radio-options ms-5">
																<?php foreach ($options as $opt_idx => $opt): ?>
																	<div class="form-check">
																		<input class="form-check-input" type="radio"
																			id="q<?= $qid ?>_opt<?= $opt_idx ?>"
																			name="answers[<?= $qid ?>]"
																			value="<?= htmlspecialchars($opt) ?>">
																		<label class="form-check-label" for="q<?= $qid ?>_opt<?= $opt_idx ?>"><?= htmlspecialchars($opt) ?></label>
																	</div>
																<?php endforeach; ?>
															</div>

														<!-- CHECKBOX -->
														<?php elseif ($type_norm === 'checkbox' || $type_norm === 'check box' || $type_norm === 'checkbox question'): ?>
															<div class="checkbox-options ms-5">
																<?php foreach ($options as $opt_idx => $opt): ?>
																	<div class="form-check">
																		<input class="form-check-input" type="checkbox"
																			id="q<?= $qid ?>_cb<?= $opt_idx ?>"
																			name="answers[<?= $qid ?>][]"
																			value="<?= htmlspecialchars($opt) ?>">
																		<label class="form-check-label" for="q<?= $qid ?>_cb<?= $opt_idx ?>"><?= htmlspecialchars($opt) ?></label>
																	</div>
																<?php endforeach; ?>
															</div>

														<?php elseif ($type_norm === 'dropdown' || $type_norm === 'select'): ?>
															<select class="form-select mt-2" name="answers[<?= $qid ?>]" <?= $required ? 'required' : '' ?>>
																<option value="" disabled selected>Select an option</option>
																<?php foreach ($options as $opt): ?>
																	<option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
																<?php endforeach; ?>
															</select>

														<?php elseif ($type_norm === 'linear scale' || $type_norm === 'linear_scale'): ?>
															<div class="linear-scale-container mt-2">
																<?php if (!empty($labelLeft) || !empty($labelRight)): ?>
																	<div class="scale-labels d-flex justify-content-between mb-2">
																		<small class="text-muted"><?= htmlspecialchars($labelLeft) ?></small>
																		<small class="text-muted"><?= htmlspecialchars($labelRight) ?></small>
																	</div>
																<?php endif; ?>
																<div class="scale-options d-flex justify-content-between">
																	<?php for ($i = 1; $i <= $scaleMax; $i++): ?>
																		<div class="scale-option text-center">
																			<input type="radio"
																				id="q<?= $qid ?>_scale<?= $i ?>"
																				name="answers[<?= $qid ?>]"
																				value="<?= $i ?>"
																				<?= $required ? 'required' : '' ?>>
																			<label for="q<?= $qid ?>_scale<?= $i ?>" class="d-block mt-1">
																				<small class="text-muted"><?= $i ?></small>
																			</label>
																		</div>
																	<?php endfor; ?>
																</div>
															</div>

														<?php elseif ($type_norm === 'short answer' || $type_norm === 'short' || $type_norm === 'shortanswer'): ?>
															<input type="text" class="form-control mt-2" name="answers[<?= $qid ?>]" <?= $required ? 'required' : '' ?>>

														<?php elseif ($type_norm === 'paragraph' || $type_norm === 'long answer' || $type_norm === 'longanswer'): ?>
															<textarea class="form-control mt-2" rows="3" name="answers[<?= $qid ?>]" <?= $required ? 'required' : '' ?>></textarea>

														<?php elseif ($type_norm === 'date picker' || $type_norm === 'date' || $type_norm === 'datepicker'): ?>
															<input type="text" class="form-control date-picker mt-2" name="answers[<?= $qid ?>]" placeholder="Select date" readonly <?= $required ? 'required' : '' ?>>

														<?php else: ?>
															<!-- Fallback to text input -->
															<input type="text" class="form-control mt-2" name="answers[<?= $qid ?>]" <?= $required ? 'required' : '' ?>>
														<?php endif; ?>
													</div>
												</div>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endforeach; ?>
						</div>
						<!-- Section Navigation -->
						<?php if (count($sections) > 1): ?>
							<div class="section-navigation">
								<div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
									<button type="button" class="btn btn-outline-primary" id="prevSectionBtn" disabled>
										<i class="bi bi-chevron-left me-1"></i>Previous Section
									</button>

									<div class="section-info text-center">
										<small class="text-muted d-block">Current Section</small>
										<span id="currentSectionTitle" class="fw-bold fs-5"><?= htmlspecialchars($sections[0]['secTitle'] ?? $sections[0]['title'] ?? 'Section 1') ?></span>
										<small class="text-muted d-block mt-1 section-counter">
											Section <span id="currentSectionNumber">1</span> of <?= count($sections) ?>
										</small>
									</div>

									<button type="button" class="btn btn-outline-primary" id="nextSectionBtn">
										Next Section<i class="bi bi-chevron-right ms-1"></i>
									</button>
								</div>
							</div>
						<?php endif; ?>
					</form>

				<?php endif; ?>
			<?php endif; ?>

		</div>
	</main>

	<footer class="site-footer fixed-bottom py-3 mt-auto">
		<div class="container text-center">
			<p class="mb-0">ICT30017 ICT PROJECT A | <span class="team-name">TEAM Deadline Dominators</span></p>
		</div>
	</footer>

	<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
	<script src="js/main.js"></script>
	<script src="js/viewsurvey.js"></script>
</body>

</html>