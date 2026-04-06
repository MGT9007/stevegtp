<?php
/**
 * SteveGPT API Client
 * Handles direct communication with Anthropic Claude API
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class SteveGPT_Client {
    
    private $api_key;
    private $api_url = 'https://api.anthropic.com/v1/messages';
    private $model;
    private $provider = 'anthropic';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = get_option('stevegpt_api_key', '');
        $this->model = get_option('stevegpt_model', 'claude-opus-4-6');
    }
    
    /**
     * Send chat completion request to Claude
     * 
     * @param array $messages Conversation messages
     * @param array $options Request options
     * @return string AI response text
     * @throws Exception on API error
     */
    public function chat_completion($messages, $options = array()) {
        
        if (empty($this->api_key)) {
            throw new Exception('Anthropic API key not configured. Please add your API key in SteveGPT Settings.');
        }
        
        // Extract options
        $model = $options['model'] ?? $this->model;
        $max_tokens = $options['max_tokens'] ?? 4096;
        $temperature = $options['temperature'] ?? 0.7;
        $chatbot_id = $options['chatbot_id'] ?? null;
        
        // Separate system messages from conversation
        $system_message = '';
        $user_messages = array();
        
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system_message .= $msg['content'] . "\n\n";
            } else {
                $user_messages[] = $msg;
            }
        }
        
        // Build request payload for Anthropic API
        $payload = array(
            'model' => $model,
            'max_tokens' => $max_tokens,
            'temperature' => $temperature,
            'messages' => $user_messages
        );
        
        // Add system message if present
        if (!empty($system_message)) {
            $payload['system'] = trim($system_message);
        }
        
        // Log request start time
        $request_start = microtime(true);
        
        // Make HTTP request to Anthropic API
        $response = wp_remote_post($this->api_url, array(
            'headers' => array(
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($payload),
            'timeout' => 60,
            'data_format' => 'body'
        ));
        
        // Calculate request duration
        $request_duration = microtime(true) - $request_start;
        
        // Error handling
        if (is_wp_error($response)) {
            throw new Exception('Anthropic API request failed: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            
            $error_message = 'Anthropic API error (HTTP ' . $status_code . ')';
            if (isset($error_data['error']['message'])) {
                $error_message .= ': ' . $error_data['error']['message'];
            }
            
            throw new Exception($error_message);
        }
        
        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['content'][0]['text'])) {
            throw new Exception('Invalid Anthropic API response format: ' . substr($body, 0, 200));
        }
        
        // Extract response text
        $response_text = $data['content'][0]['text'];
        
        // Log usage for cost tracking
        $this->log_usage($data, $request_duration, $chatbot_id);
        
        return $response_text;
    }
    
    /**
     * Log API usage for cost tracking and analytics
     * 
     * @param array $response_data API response data
     * @param float $request_duration Request duration in seconds
     * @param string $chatbot_id Optional chatbot ID
     */
    private function log_usage($response_data, $request_duration, $chatbot_id = null) {
        global $wpdb;
        
        $usage = $response_data['usage'] ?? array();
        
        $input_tokens = $usage['input_tokens'] ?? 0;
        $output_tokens = $usage['output_tokens'] ?? 0;
        $total_tokens = $input_tokens + $output_tokens;
        
        // Calculate cost based on Claude Opus 4.6 pricing
        // Input: $3.00 per million tokens
        // Output: $15.00 per million tokens
        $input_cost = ($input_tokens / 1000000) * 3.00;
        $output_cost = ($output_tokens / 1000000) * 15.00;
        $total_cost = $input_cost + $output_cost;
        
        // Determine plugin source from backtrace
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $plugin_source = 'unknown';
        
        foreach ($backtrace as $trace) {
            if (isset($trace['file'])) {
                if (strpos($trace['file'], 'mfsd-weekly-rag') !== false) {
                    $plugin_source = 'mfsd-weekly-rag';
                    break;
                } elseif (strpos($trace['file'], 'mfsd-personality-test') !== false) {
                    $plugin_source = 'mfsd-personality-test';
                    break;
                } elseif (strpos($trace['file'], 'mfsd-word-association') !== false) {
                    $plugin_source = 'mfsd-word-association';
                    break;
                } elseif (strpos($trace['file'], 'mfsd-') !== false) {
                    // Generic MFSD plugin
                    preg_match('/mfsd-([a-z-]+)/', $trace['file'], $matches);
                    if (!empty($matches[1])) {
                        $plugin_source = 'mfsd-' . $matches[1];
                        break;
                    }
                }
            }
        }
        
        // Insert into usage log
        $wpdb->insert(
            $wpdb->prefix . 'stevegpt_usage_log',
            array(
                'timestamp' => current_time('mysql'),
                'provider' => $this->provider,
                'model' => $this->model,
                'user_id' => get_current_user_id(),
                'plugin_source' => $plugin_source,
                'chatbot_id' => $chatbot_id,
                'input_tokens' => $input_tokens,
                'output_tokens' => $output_tokens,
                'total_tokens' => $total_tokens,
                'cost' => $total_cost
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%f')
        );
        
        // Also log to error_log for debugging (optional - remove in production)
        error_log(sprintf(
            'SteveGPT: %s | %d in + %d out = %d tokens | $%.4f | %.2fs | Source: %s | Chatbot: %s',
            $this->model,
            $input_tokens,
            $output_tokens,
            $total_tokens,
            $total_cost,
            $request_duration,
            $plugin_source,
            $chatbot_id ?: 'none'
        ));
    }
    
    /**
     * Get usage statistics
     * 
     * @param int $days Number of days to analyze
     * @return array Usage statistics
     */
    public function get_usage_stats($days = 30) {
        global $wpdb;
        
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_requests,
                SUM(input_tokens) as total_input_tokens,
                SUM(output_tokens) as total_output_tokens,
                SUM(total_tokens) as total_tokens,
                SUM(cost) as total_cost,
                AVG(total_tokens) as avg_tokens_per_request
            FROM {$wpdb->prefix}stevegpt_usage_log
            WHERE timestamp >= %s",
            $cutoff
        ), ARRAY_A);
        
        // Get usage by plugin
        $by_plugin = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                plugin_source,
                COUNT(*) as requests,
                SUM(total_tokens) as tokens,
                SUM(cost) as cost
            FROM {$wpdb->prefix}stevegpt_usage_log
            WHERE timestamp >= %s
            GROUP BY plugin_source
            ORDER BY cost DESC",
            $cutoff
        ), ARRAY_A);
        
        return array(
            'total_requests' => (int) $stats['total_requests'],
            'total_input_tokens' => (int) $stats['total_input_tokens'],
            'total_output_tokens' => (int) $stats['total_output_tokens'],
            'total_tokens' => (int) $stats['total_tokens'],
            'total_cost' => (float) $stats['total_cost'],
            'avg_tokens_per_request' => (float) $stats['avg_tokens_per_request'],
            'by_plugin' => $by_plugin,
            'period_days' => $days
        );
    }
    
    /**
     * Test API connection
     * 
     * @return array Test result with success status and message
     */
    public function test_connection() {
        try {
            $messages = array(
                array(
                    'role' => 'user',
                    'content' => 'Say "Connection test successful" and nothing else.'
                )
            );
            
            $response = $this->chat_completion($messages, array(
                'max_tokens' => 50,
                'temperature' => 0
            ));
            
            return array(
                'success' => true,
                'message' => 'API connection successful!',
                'response' => $response,
                'model' => $this->model
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'API connection failed',
                'error' => $e->getMessage()
            );
        }
    }
}