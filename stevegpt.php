<?php
/**
 * Plugin Name: SteveGPT - AI Integration for MFSD
 * Plugin URI: https://mfsd.me
 * Description: Custom AI integration for My Future Self Digital. Drop-in replacement for MWAI with direct Anthropic Claude API access.
 * Version: 2.0.0
 * Author: MFSD Development Team
 * Author URI: https://mfsd.me
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: stevegpt
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('STEVEGPT_VERSION', '2.0.0');
define('STEVEGPT_PATH', plugin_dir_path(__FILE__));
define('STEVEGPT_URL', plugin_dir_url(__FILE__));
define('STEVEGPT_BASENAME', plugin_basename(__FILE__));

/**
 * Main SteveGPT class
 */
class SteveGPT {
    
    private static $instance = null;
    private $client;
    
    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize
        add_action('init', array($this, 'init'));
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        }
        
        // Make available globally for MWAI compatibility
        $GLOBALS['stevegpt'] = $this;
    }
    
    /**
     * Load dependencies
     */
    private function load_dependencies() {
        require_once STEVEGPT_PATH . 'includes/class-stevegpt-client.php';
        require_once STEVEGPT_PATH . 'includes/class-stevegpt-chatbot.php';
        require_once STEVEGPT_PATH . 'includes/class-stevegpt-admin.php';
        require_once STEVEGPT_PATH . 'includes/class-stevegpt-chatbots-admin.php';
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Create database tables if needed
        $this->maybe_create_tables();
    }
    
    /**
     * Create database tables for usage tracking and chatbot management
     */
    private function maybe_create_tables() {
        global $wpdb;
        
        $installed_version = get_option('stevegpt_db_version', '0');
        
        if (version_compare($installed_version, STEVEGPT_VERSION, '<')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            $charset_collate = $wpdb->get_charset_collate();
            
            // Usage log table
            $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}stevegpt_usage_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                timestamp DATETIME NOT NULL,
                provider VARCHAR(50) NOT NULL,
                model VARCHAR(100) NOT NULL,
                user_id BIGINT UNSIGNED,
                plugin_source VARCHAR(100),
                chatbot_id VARCHAR(100),
                input_tokens INT UNSIGNED NOT NULL,
                output_tokens INT UNSIGNED NOT NULL,
                total_tokens INT UNSIGNED NOT NULL,
                cost DECIMAL(10,6) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_timestamp (timestamp),
                INDEX idx_provider (provider),
                INDEX idx_user (user_id),
                INDEX idx_chatbot (chatbot_id)
            ) $charset_collate;";
            
            dbDelta($sql);
            
            // Chatbots configuration table
            $sql_chatbots = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}stevegpt_chatbots (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chatbot_id VARCHAR(100) NOT NULL UNIQUE,
                name VARCHAR(200) NOT NULL,
                description TEXT,
                instructions TEXT,
                model VARCHAR(100) NOT NULL DEFAULT 'claude-sonnet-4-6',
                reasoning VARCHAR(20) DEFAULT 'medium',
                max_tokens INT UNSIGNED DEFAULT 4096,
                temperature DECIMAL(3,2) DEFAULT 0.7,
                input_max_length INT UNSIGNED DEFAULT 512,
                max_messages INT UNSIGNED DEFAULT 15,
                context_max_length INT UNSIGNED DEFAULT 16384,
                content_aware TINYINT(1) DEFAULT 0,
                tools JSON,
                appearance JSON,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_chatbot_id (chatbot_id),
                INDEX idx_active (is_active)
            ) $charset_collate;";
            
            dbDelta($sql_chatbots);
            
            // Conversations table
            $sql_conversations = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}stevegpt_conversations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                conversation_id VARCHAR(100) NOT NULL UNIQUE,
                chatbot_id VARCHAR(100) NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(255),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_conversation_id (conversation_id),
                INDEX idx_chatbot (chatbot_id),
                INDEX idx_user (user_id),
                INDEX idx_created (created_at)
            ) $charset_collate;";
            
            dbDelta($sql_conversations);
            
            // Messages table
            $sql_messages = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}stevegpt_messages (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                conversation_id VARCHAR(100) NOT NULL,
                role VARCHAR(20) NOT NULL,
                content TEXT NOT NULL,
                tokens_used INT UNSIGNED DEFAULT 0,
                cost DECIMAL(10,6) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_conversation (conversation_id),
                INDEX idx_role (role),
                INDEX idx_created (created_at)
            ) $charset_collate;";
            
            dbDelta($sql_messages);
            
            // Create default chatbot if none exist
            $this->maybe_create_default_chatbot();
            
            update_option('stevegpt_db_version', STEVEGPT_VERSION);
        }
    }
    
    /**
     * Create default chatbot configuration
     */
    private function maybe_create_default_chatbot() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'stevegpt_chatbots';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        
        if ($count == 0) {
            // Create default chatbot
            $wpdb->insert($table, array(
                'chatbot_id' => 'default',
                'name' => 'Default SteveGPT',
                'description' => 'Default chatbot for general MFSD support',
                'instructions' => $this->get_steve_base_persona(),
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
                    'avatar' => '🧑‍🏫',
                    'ai_name' => 'Steve',
                    'start_sentence' => 'Just ask Steve...',
                    'user_name' => 'You',
                    'send_text' => 'Send',
                    'clear_text' => 'Clear'
                )),
                'is_active' => 1
            ));
        }
    }
    
    /**
     * Register admin menu
     */
    public function admin_menu() {
        add_menu_page(
            'SteveGPT',
            'SteveGPT',
            'manage_options',
            'stevegpt',
            array('SteveGPT_Admin', 'render_dashboard_page'),
            'dashicons-format-chat',
            30
        );
        
        add_submenu_page(
            'stevegpt',
            'Chatbots',
            'Chatbots',
            'manage_options',
            'stevegpt-chatbots',
            array('SteveGPT_Chatbots_Admin', 'render_chatbots_page')
        );
        
        add_submenu_page(
            'stevegpt',
            'Settings',
            'Settings',
            'manage_options',
            'stevegpt-settings',
            array('SteveGPT_Admin', 'render_settings_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_scripts($hook) {
        // Only load on SteveGPT admin pages
        if (strpos($hook, 'stevegpt') === false) {
            return;
        }
        
        wp_enqueue_style(
            'stevegpt-admin',
            STEVEGPT_URL . 'assets/css/admin.css',
            array(),
            STEVEGPT_VERSION
        );
    }
    
    /**
     * Main API method - Simple text query
     * MWAI-compatible interface
     * 
     * @param string $prompt User prompt
     * @param array $options Optional parameters
     * @return string AI response
     */
    public function simpleTextQuery($prompt, $options = array()) {
        try {
            // Get API client
            if (!$this->client) {
                $this->client = new SteveGPT_Client();
            }
            
            // Build messages array
            $messages = array();
            
            // Add Steve's base persona as system message
            $messages[] = array(
                'role' => 'system',
                'content' => $this->get_steve_base_persona()
            );
            
            // Add user prompt
            $messages[] = array(
                'role' => 'user',
                'content' => $prompt
            );
            
            // Extract options
            $max_tokens = $options['max_tokens'] ?? 4096;
            $temperature = $options['temperature'] ?? 0.7;
            
            // Call Claude API
            $response = $this->client->chat_completion($messages, array(
                'max_tokens' => $max_tokens,
                'temperature' => $temperature
            ));
            
            return $response;
            
        } catch (Exception $e) {
            error_log('SteveGPT Error: ' . $e->getMessage());
            
            // Return fallback message
            return "Steve says: I'm having trouble connecting right now. Please try again in a moment. - Steve";
        }
    }
    
    /**
     * Get Steve's base persona
     */
    private function get_steve_base_persona() {
        return <<<EOT
You are Steve Sallis, a motivational teacher-coach for students aged 12-14 working on their High Performance Pathway program.

YOUR ROLE:
- Help students discover their potential and build self-awareness
- Encourage growth mindset and resilience
- Make complex concepts simple and relatable for young teens
- Be warm, authentic, and genuinely supportive

YOUR VOICE:
- Conversational and friendly, not formal or academic
- Use "you" and "your" to speak directly to the student
- Be specific and personal, avoid generic praise
- Age-appropriate language (12-14 year olds)
- Positive but realistic - acknowledge challenges honestly

SOLUTIONS MINDSET PRINCIPLES (use these naturally):
1. What is the solution to every problem I face?
2. If you have a solutions mindset, marginal gains will occur
3. There is no Failure only Feedback
4. A smooth sea never made a skilled sailor
5. If one person can do it, anyone can do it
6. Happiness is a journey, not an outcome
7. You never lose…you either win or learn
8. Character over Calibre is the best way to succeed
9. The person with the most passion has the greatest impact
10. Hard work beats talent when talent does not work hard
11. Everybody knows more than somebody
12. Be the person your dog thinks you are
13. It is nice to be important, but more important to be nice

FORMATTING:
- Start substantial responses with "Steve says:" when giving guidance
- End with "- Steve" on a new line for longer responses
- Keep responses concise (2-3 sentences) unless detail is needed
- Use natural paragraph breaks
EOT;
    }
}

/**
 * Initialize the plugin
 */
function stevegpt_init() {
    return SteveGPT::instance();
}

// Start the plugin
stevegpt_init();