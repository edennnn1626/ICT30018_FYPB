<?php
ob_start(); // Start output buffering

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE); // Suppress warnings
ini_set('display_errors', 0);

require_once "db_config.php";
require_once "auth.php";

// Clear any previous output
ob_clean();

// Check login
if (!isset($_SESSION['user']['id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit;
}

$surveyId = $_GET['survey_id'] ?? null;
$type = $_GET['type'] ?? 'aggregated';

if (!$surveyId || !is_numeric($surveyId)) {
    echo "Valid Survey ID required";
    ob_end_flush();
    exit;
}

// Get survey title
$stmt = $conn->prepare("SELECT title FROM " . TABLE_SURVEYS . " WHERE id = ?");
$stmt->bind_param("i", $surveyId);
$stmt->execute();
$result = $stmt->get_result();
$survey = $result->fetch_assoc();
$surveyTitle = $survey['title'] ?? 'Survey_' . $surveyId;
$stmt->close();

if ($type === 'aggregated') {
    // Export aggregated data as CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $surveyTitle) . '_aggregated.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Question', 'Answer', 'Count', 'Percentage']);

    // Get aggregated data
    $responseSql = "SELECT answers FROM " . SURVEY_RESPONSES_TABLE . " WHERE survey_id = ?";
    $stmt = $conn->prepare($responseSql);
    $stmt->bind_param("i", $surveyId);
    $stmt->execute();
    $result = $stmt->get_result();

    $allAnswers = [];
    while ($row = $result->fetch_assoc()) {
        $answers = json_decode($row['answers'], true);
        if (is_array($answers)) {
            foreach ($answers as $question => $answer) {
                // Clean question text
                $cleanQuestion = $question;
                // Decode Unicode escape sequences
                $cleanQuestion = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
                    return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
                }, $cleanQuestion);
                // Decode HTML entities
                $cleanQuestion = html_entity_decode($cleanQuestion, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                // Remove HTML tags
                $cleanQuestion = strip_tags($cleanQuestion);

                if (!isset($allAnswers[$cleanQuestion])) {
                    $allAnswers[$cleanQuestion] = [];
                }

                if (is_array($answer)) {
                    foreach ($answer as $item) {
                        $item = trim($item);
                        if ($item !== '') {
                            $allAnswers[$cleanQuestion][$item] = ($allAnswers[$cleanQuestion][$item] ?? 0) + 1;
                        }
                    }
                } else {
                    $answer = trim($answer);
                    if ($answer !== '') {
                        $allAnswers[$cleanQuestion][$answer] = ($allAnswers[$cleanQuestion][$answer] ?? 0) + 1;
                    }
                }
            }
        }
    }
    $stmt->close();

    foreach ($allAnswers as $question => $answers) {
        $total = array_sum($answers);
        if ($total > 0) {
            foreach ($answers as $answer => $count) {
                $percentage = ($count / $total) * 100;
                fputcsv($output, [
                    $question,
                    $answer,
                    $count,
                    number_format($percentage, 2) . '%'
                ]);
            }
        }
    }

    fclose($output);
} else {
    // Export individual responses as CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $surveyTitle) . '_responses.csv"');

    $output = fopen('php://output', 'w');

    $responseSql = "SELECT id, answers, submitted_at FROM " . SURVEY_RESPONSES_TABLE . " WHERE survey_id = ? ORDER BY submitted_at DESC";
    $stmt = $conn->prepare($responseSql);
    $stmt->bind_param("i", $surveyId);
    $stmt->execute();
    $result = $stmt->get_result();

    $headersWritten = false;
    $firstRow = true;
    while ($row = $result->fetch_assoc()) {
        $answers = json_decode($row['answers'], true);
        if (is_array($answers)) {
            if (!$headersWritten) {
                // Write headers
                $headers = ['Response ID', 'Timestamp'];
                // Clean question headers
                foreach (array_keys($answers) as $question) {
                    $cleanQuestion = html_entity_decode($question, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $cleanQuestion = strip_tags($cleanQuestion);
                    $headers[] = $cleanQuestion;
                }
                fputcsv($output, $headers);
                $headersWritten = true;
            }

            $rowData = [$row['id'], $row['submitted_at']];
            foreach ($answers as $answer) {
                if (is_array($answer)) {
                    $rowData[] = implode('; ', array_filter($answer, function ($a) {
                        return trim($a) !== '';
                    }));
                } else {
                    $rowData[] = $answer;
                }
            }
            fputcsv($output, $rowData);
        }
    }

    if (!$headersWritten) {
        fputcsv($output, ['No responses found']);
    }

    $stmt->close();
    fclose($output);
}

// Clear buffer and exit
ob_end_flush();
exit();
?>