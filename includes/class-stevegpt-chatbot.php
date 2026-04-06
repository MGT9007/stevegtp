<?php
/**
 * SteveGPT Chatbot Model
 * Handles chatbot configuration and conversation management
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class SteveGPT_Chatbot {
    
    private $chatbot_id;
    private $config;
    private $client;
    
    /**
     * Constructor
     */
    public function __construct($chatbot_id) {
        $this->chatbot_id = $chatbot_id;
        $this->config = $this->load_config();
        $this->client = new SteveGPT_Client();
    }
    
    /**
     * Get chatbot instance by ID
     */
    public static function get($chatbot_id) {
        return new self($chatbot_id);
    }
    
    /**
     * Load chatbot configuration from database
     */
    private function load_config() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'stevegpt_chatbots';
        $config = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE chatbot_id = %s AND is_active = 1",
            $this->chatbot_id
        ), ARRAY_A);
        
        if (!$config) {
            throw new Exception("Chatbot '{$this->chatbot_id}' not found or inactive");
        }
        
        // Decode JSON fields
        $config['tools'] = json_decode($config['tools'], true) ?: array();
        $config['appearance'] = json_decode($config['appearance'], true) ?: array();
        
        return $config;
    }
    
    /**
     * Send message to chatbot
     * 
     * @param string $prompt User message
     * @param int $user_id WordPress user ID
     * @param string $conversation_id Optional conversation ID
     * @return string AI response
     */
    public function send_message($prompt, $user_id, $conversation_id = null) {
        
        // Create or get conversation
        if (!$conversation_id) {
            $conversation_id = $this->create_conversation($user_id);
        }
        
        // Build message history
        $messages = $this->build_messages($conversation_id, $prompt);
        
        // Apply thresholds
        $messages = $this->apply_thresholds($messages);
        
        // Call AI
        try {
            $response = $this->client->chat_completion($messages, array(
                'model' => $this->config['model'],
                'max_tokens' => $this->config['max_tokens'],
                'temperature' => $this->config['temperature'],
                'chatbot_id' => $this->chatbot_id
            ));
            
            // Save messages to database
            $this->save_message($conversation_id, 'user', $prompt);
            $this->save_message($conversation_id, 'assistant', $response);
            
            return $response;
            
        } catch (Exception $e) {
            error_log('SteveGPT Chatbot Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Simple text query (MWAI compatibility)
     */
    public function simpleTextQuery($prompt, $options = array()) {
        $user_id = get_current_user_id() ?: 0;
        return $this->send_message($prompt, $user_id);
    }
    
    /**
     * Build messages array for API call
     */
    private function build_messages($conversation_id, $current_prompt) {
        global $wpdb;
        
        $messages = array();
        
        // Add system message (instructions)
        $messages[] = array(
            'role' => 'system',
            'content' => $this->config['instructions']
        );
        
        // Get conversation history
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT role, content FROM {$wpdb->prefix}stevegpt_messages 
             WHERE conversation_id = %s 
             ORDER BY created_at ASC",
            $conversation_id
        ), ARRAY_A);
        
        // Add history to messages
        foreach ($history as $msg) {
            $messages[] = array(
                'role' => $msg['role'],
                'content' => $msg['content']
            );
        }
        
        // Add current user message
        $messages[] = array(
            'role' => 'user',
            'content' => $current_prompt
        );
        
        return $messages;
    }
    
    /**
     * Apply thresholds to messages
     */
    private function apply_thresholds($messages) {
        $max_messages = $this->config['max_messages'];
        $context_max = $this->config['context_max_length'];
        
        // Limit message history
        if (count($messages) > $max_messages) {
            // Keep system message + recent messages
            $system_msgs = array_filter($messages, fn($m) => $m['role'] === 'system');
            $other_msgs = array_filter($messages, fn($m) => $m['role'] !== 'system');
            $other_msgs = array_slice($other_msgs, -($max_messages - 1));
            $messages = array_merge($system_msgs, $other_msgs);
        }
        
        // Truncate long messages
        foreach ($messages as &$message) {
            if (strlen($message['content']) > $context_max) {
                $message['content'] = substr($message['content'], 0, $context_max) . '...';
            }
        }
        
        return $messages;
    }
    
    /**
     * Create new conversation
     */
    private function create_conversation($user_id) {
        global $wpdb;
        
        $conversation_id = 'conv_' . uniqid();
        
        $wpdb->insert(
            $wpdb->prefix . 'stevegpt_conversations',
            array(
                'conversation_id' => $conversation_id,
                'chatbot_id' => $this->chatbot_id,
                'user_id' => $user_id,
                'title' => 'New Conversation'
            )
        );
        
        return $conversation_id;
    }
    
    /**
     * Save message to database
     */
    private function save_message($conversation_id, $role, $content) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'stevegpt_messages',
            array(
                'conversation_id' => $conversation_id,
                'role' => $role,
                'content' => $content,
                'created_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * Get chatbot configuration
     */
    public function get_config() {
        return $this->config;
    }
    
    /**
     * Create new chatbot
     */
    public static function create($data) {
        global $wpdb;
        
        $defaults = array(
            'chatbot_id' => 'chatbot_' . uniqid(),
            'name' => 'New Chatbot',
            'description' => '',
            'instructions' => '',
            'model' => 'claude-sonnet-4-6',
            'reasoning' => 'medium',
            'max_tokens' => 4096,
            'temperature' => 0.7,
            'input_max_length' => 512,
            'max_messages' => 15,
            'context_max_length' => 16384,
            'content_aware' => 0,
            'tools' => json_encode(array()),
            'appearance' => json_encode(array(
                'theme' => 'ChatGPT',
                'avatar' => '🤖',
                'ai_name' => 'AI',
                'start_sentence' => 'How can I help?',
                'user_name' => 'You',
                'send_text' => 'Send',
                'clear_text' => 'Clear'
            )),
            'is_active' => 1
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Ensure JSON fields are encoded
        if (is_array($data['tools'])) {
            $data['tools'] = json_encode($data['tools']);
        }
        if (is_array($data['appearance'])) {
            $data['appearance'] = json_encode($data['appearance']);
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'stevegpt_chatbots',
            $data
        );
        
        if ($result) {
            return $data['chatbot_id'];
        }
        
        return false;
    }
    
    /**
     * Update chatbot
     */
    public static function update($chatbot_id, $data) {
        global $wpdb;
        
        // Ensure JSON fields are encoded
        if (isset($data['tools']) && is_array($data['tools'])) {
            $data['tools'] = json_encode($data['tools']);
        }
        if (isset($data['appearance']) && is_array($data['appearance'])) {
            $data['appearance'] = json_encode($data['appearance']);
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'stevegpt_chatbots',
            $data,
            array('chatbot_id' => $chatbot_id)
        );
        
        return $result !== false;
    }
    
    /**
     * Delete chatbot
     */
    public static function delete($chatbot_id) {
        global $wpdb;
        
        // Don't allow deleting default chatbot
        if ($chatbot_id === 'default') {
            return false;
        }
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'stevegpt_chatbots',
            array('chatbot_id' => $chatbot_id)
        );
        
        return $result !== false;
    }
    
    /**
     * Get all chatbots
     */
    public static function get_all($active_only = true) {
        global $wpdb;
        
        $where = $active_only ? "WHERE is_active = 1" : "";
        
        $chatbots = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}stevegpt_chatbots $where ORDER BY created_at DESC",
            ARRAY_A
        );
        
        // Decode JSON fields
        foreach ($chatbots as &$chatbot) {
            $chatbot['tools'] = json_decode($chatbot['tools'], true) ?: array();
            $chatbot['appearance'] = json_decode($chatbot['appearance'], true) ?: array();
        }
        
        return $chatbots;
    }
    
    /**
     * Clone chatbot
     */
    public static function clone_chatbot($chatbot_id) {
        global $wpdb;
        
        $original = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}stevegpt_chatbots WHERE chatbot_id = %s",
            $chatbot_id
        ), ARRAY_A);
        
        if (!$original) {
            return false;
        }
        
        // Create new chatbot with same config
        $original['chatbot_id'] = 'chatbot_' . uniqid();
        $original['name'] = $original['name'] . ' (Copy)';
        unset($original['id']);
        unset($original['created_at']);
        unset($original['updated_at']);
        
        return self::create($original);
    }
}
