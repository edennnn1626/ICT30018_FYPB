<?php
// Turn off all error reporting for production
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering
ob_start();

require_once("auth.php");
require_once "db_config.php";
require_login();

// Set JSON header
header('Content-Type: application/json');

// Get survey ID
$surveyId = isset($_GET['survey_id']) ? intval($_GET['survey_id']) : 0;
if ($surveyId <= 0) {
    echo json_encode(['error' => 'Valid Survey ID required']);
    ob_end_flush();
    exit;
}

// Prepare response
$response = [
    'success' => true,
    'stats' => [
        'total_responses' => 0,
        'unique_respondents' => 0,
        'first_response' => 'N/A',
        'last_response' => 'N/A'
    ],
    'aggregated' => [],
    'responses' => []
];

try {
    // Get survey statistics
    $statsSql = "SELECT 
                    COUNT(*) as total_responses,
                    DATE_FORMAT(MIN(submitted_at), '%Y-%m-%d') as first_response,
                    DATE_FORMAT(MAX(submitted_at), '%Y-%m-%d') as last_response
                FROM " . SURVEY_RESPONSES_TABLE . " 
                WHERE survey_id = ?";

    $stmt = $conn->prepare($statsSql);
    if ($stmt) {
        $stmt->bind_param("i", $surveyId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $response['stats']['total_responses'] = intval($row['total_responses']);
            $response['stats']['first_response'] = $row['first_response'] ?: 'N/A';
            $response['stats']['last_response'] = $row['last_response'] ?: 'N/A';
        }
        $stmt->close();
    }

    // Get aggregated answers
    $answersSql = "SELECT answers FROM " . SURVEY_RESPONSES_TABLE . " WHERE survey_id = ?";
    $stmt = $conn->prepare($answersSql);
    if ($stmt) {
        $stmt->bind_param("i", $surveyId);
        $stmt->execute();
        $result = $stmt->get_result();

        $allAnswers = [];
        $uniqueEmails = [];

        while ($row = $result->fetch_assoc()) {
            $answers = json_decode($row['answers'], true);
            if (is_array($answers)) {
                // Track unique emails
                if (!empty($answers['email'])) {
                    $email = is_array($answers['email']) ?
                        implode(',', $answers['email']) :
                        strval($answers['email']);
                    $uniqueEmails[md5($email)] = true;
                }

                // Process each question-answer pair
                foreach ($answers as $question => $answer) {
                    // Decode Unicode escape sequences (\uXXXX)
                    $cleanQuestion = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
                        return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
                    }, $question);

                    // Decode HTML entities but DO NOT strip tags
                    $cleanQuestion = html_entity_decode($cleanQuestion, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                    // Trim whitespace
                    $cleanQuestion = trim($cleanQuestion);

                    if (empty($cleanQuestion)) continue;

                    if (!isset($allAnswers[$cleanQuestion])) {
                        $allAnswers[$cleanQuestion] = [];
                    }

                    if (is_array($answer)) {
                        foreach ($answer as $item) {
                            $item = trim(strval($item));
                            if ($item !== '' && $item !== 'N/A') {
                                $allAnswers[$cleanQuestion][$item] = ($allAnswers[$cleanQuestion][$item] ?? 0) + 1;
                            }
                        }
                    } else {
                        $answer = trim(strval($answer));
                        if ($answer !== '' && $answer !== 'N/A') {
                            $allAnswers[$cleanQuestion][$answer] = ($allAnswers[$cleanQuestion][$answer] ?? 0) + 1;
                        }
                    }
                }
            }
        }

        $response['aggregated'] = $allAnswers;
        $response['stats']['unique_respondents'] = count($uniqueEmails);
        $stmt->close();
    }

    // Get recent responses
    $responsesSql = "SELECT id, answers, submitted_at FROM " . SURVEY_RESPONSES_TABLE . " 
                     WHERE survey_id = ? 
                     ORDER BY submitted_at DESC 
                     LIMIT 10";

    $stmt = $conn->prepare($responsesSql);
    if ($stmt) {
        $stmt->bind_param("i", $surveyId);
        $stmt->execute();
        $result = $stmt->get_result();

        $responses = [];
        while ($row = $result->fetch_assoc()) {
            $answers = json_decode($row['answers'], true);
            if (is_array($answers)) {
                // Clean up answers for display
                $cleanedAnswers = [];
                foreach ($answers as $question => $answer) {
                    $cleanQuestion = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
                        return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
                    }, $question);

                    // Decode HTML entities
                    $cleanQuestion = html_entity_decode($cleanQuestion, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                    $cleanQuestion = trim($cleanQuestion);

                    // Process answer
                    if (is_array($answer)) {
                        $cleanedAnswer = array_map(function ($item) {
                            return trim(strval($item));
                        }, $answer);
                        $cleanedAnswers[$cleanQuestion] = array_filter($cleanedAnswer, function ($item) {
                            return $item !== '' && $item !== 'N/A';
                        });
                    } else {
                        $cleanedAnswer = trim(strval($answer));
                        if ($cleanedAnswer !== '' && $cleanedAnswer !== 'N/A') {
                            $cleanedAnswers[$cleanQuestion] = $cleanedAnswer;
                        }
                    }
                }

                $responses[] = [
                    'id' => intval($row['id']),
                    'answers' => $cleanedAnswers,
                    'submitted_at' => date('F j, Y, g:i a', strtotime($row['submitted_at']))
                ];
            }
        }
        $response['responses'] = $responses;
        $stmt->close();
    }
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = 'Server error';
}

// Clear all output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Output clean JSON
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
